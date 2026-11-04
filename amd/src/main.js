define(
    [
        'jquery',
        'block_configurable_reports/jquery.tablesorter',
        'core/config',
        'core/notification',
        'require'
    ], function($, tablesorter, Config, Notification, require) {

        // The editor wrapping the SQL query textarea, when syntax highlighting is on. Setting the
        // textarea's own value is not enough once CodeMirror has taken it over.
        var queryeditor = null;

        // The "render" value in the report's DataTables options that asks for a row checkbox column.
        var SELECT_RENDER = 'select';

        // The course the AJAX scripts check access in. Totara has no M.cfg.courseId, so PHP gives it.
        var courseid = null;

        /**
         * Ask one of the plugin's AJAX scripts for data.
         *
         * @param {String} script The script to call, relative to the plugin.
         * @param {Object} params The parameters it takes, beyond the course and the sesskey.
         * @return {Promise} Resolved with the decoded response.
         */
        var request = function(script, params) {
            params.courseid = courseid;
            params.sesskey = Config.sesskey;

            var url = Config.wwwroot + '/blocks/configurable_reports/' + script + '?' +
                new URLSearchParams(params).toString();

            return fetch(url, {credentials: 'same-origin'}).then(function(response) {
                if (!response.ok) {
                    throw new Error(response.statusText);
                }
                return response.json();
            }).then(function(data) {
                // An AJAX script answers with the usual 200 and reports the failure in the body.
                if (data && data.error) {
                    throw new Error(data.error);
                }
                return data;
            });
        };

        return {
            js_order: function(params) {
                $(params.selector).tablesorter();
                $(params.selector + ' th.header').css({
                    'background-image': 'url(' + params.background + ')',
                    'background-position': 'right center',
                    'background-repeat': 'no-repeat',
                    'cursor': 'pointer'
                });

                $(params.selector + ' th.headerSortUp').css({
                    'background-image': 'url(' + params.backgroundasc + ')'
                });

                $(params.selector + ' th.headerSortDown').css({
                    'background-image': 'url(' + params.backgrounddesc + ')'
                });
            },
            add_jsdatatables: function(params) {
                var str = M.str.block_configurable_reports;
                // camelCase keys: DataTables copies a legacy (Hungarian) key over its camelCase key, so a legacy
                // default would replace the report's own camelCase option.
                var defaults = {
                    autoWidth: false,
                    pagingType: 'full_numbers',
                    order: [],
                    language: {
                        paginate: {
                            first: str.datatables_first,
                            last: str.datatables_last,
                            next: str.datatables_next,
                            previous: str.datatables_previous
                        },
                        emptyTable: str.datatables_emptytable,
                        info: str.datatables_info,
                        infoEmpty: str.datatables_infoempty,
                        infoFiltered: str.datatables_infofiltered,
                        thousands: M.str.langconfig.thousandssep,
                        lengthMenu: str.datatables_lengthmenu,
                        loadingRecords: str.datatables_loadingrecords,
                        processing: str.datatables_processing,
                        search: str.datatables_search,
                        zeroRecords: str.datatables_zerorecords
                    }
                };

                // The report's own options replace the defaults with the same keys. Arrays are replaced, not merged.
                var options = $.extend(true, {}, defaults, params.options || {});
                Object.keys(params.options || {}).forEach(function(key) {
                    if (Array.isArray(params.options[key])) {
                        options[key] = params.options[key];
                    }
                });

                // Load DataTables and the extensions only on the report page. The extensions register themselves.
                require([
                    'block_configurable_reports/jquery.dataTables',
                    'block_configurable_reports/dataTables.buttons',
                    'block_configurable_reports/buttons.colVis',
                    'block_configurable_reports/buttons.print',
                    'block_configurable_reports/dataTables.rowGroup',
                    'block_configurable_reports/dataTables.select'
                ], function(DataTable) {
                    // JSON cannot hold a function, so a column with "render": "select" gets the Select checkbox.
                    (options.columns || []).concat(options.columnDefs || []).forEach(function(column) {
                        if (column && column.render === SELECT_RENDER) {
                            column.render = DataTable.render.select();
                        }
                    });
                    // The 'display' class gives the DataTables default style: row lines, stripes, hover and ordered column.
                    var table = document.querySelector(params.selector);
                    table.classList.add('display');
                    new DataTable(table, options);
                });
            },
            cmirror: function(options) {
                courseid = options.courseid;
                var query = document.getElementById('id_querysql');
                if (!query) {
                    return;
                }

                // The editor is a large module, so only this form loads it.
                require(['block_configurable_reports/sqleditor-lazy'], function(sqleditor) {
                    queryeditor = sqleditor.fromTextArea(query, {
                        dialect: options.dialect,
                        placeholders: options.placeholders,
                        syntaxerror: options.syntaxerror,
                        autofocus: true,
                    });

                    // Complete table and column names when the database has given them.
                    request('schema.php', {})
                        .then(function(response) {
                            queryeditor.setTables(response.tables);
                            return;
                        })
                        .catch(Notification.exception);
                });
            },
            remote_reports: function(course, strings) {
                courseid = course;
                var reports = document.getElementById('id_repositoryreport');

                // Only on the page when the report repository could be read.
                if (!reports) {
                    return;
                }

                /**
                 * Fill a field with one part of the chosen repository report.
                 *
                 * @param {String} buttonid The button that pulls the part.
                 * @param {String} part 'sql' or 'datatables'.
                 * @param {String} fieldid The field that the part goes in.
                 * @param {String} confirmation The question to ask before the field's text is replaced.
                 */
                var pull = function(buttonid, part, fieldid, confirmation) {
                    var button = document.getElementById(buttonid);
                    var field = document.getElementById(fieldid);
                    if (!button || !field) {
                        return;
                    }

                    // The SQL field is a CodeMirror editor when syntax highlighting is on.
                    var editor = function() {
                        return fieldid === 'id_querysql' ? queryeditor : null;
                    };
                    var fill = function() {
                        request('repository.php', {path: reports.value, part: part})
                            .then(function(response) {
                                field.value = response.content;
                                if (editor()) {
                                    editor().setValue(response.content);
                                }
                                return;
                            })
                            .catch(Notification.exception);
                    };

                    button.addEventListener('click', function() {
                        if (!reports.value) {
                            return;
                        }
                        var text = editor() ? editor().getValue() : field.value;
                        if (text.trim() === '') {
                            fill();
                            return;
                        }
                        Notification.confirm(button.textContent, confirmation, strings.yes, strings.cancel, fill);
                    });
                };

                pull('id_pullsql', 'sql', 'id_querysql', strings.confirmsql);
                pull('id_pulldatatables', 'datatables', 'id_datatablesconfig', strings.confirmdatatables);
            }
        };
    });