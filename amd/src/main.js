define(
    [
        'jquery',
        'block_configurable_reports/jquery.tablesorter',
        'block_configurable_reports/jquery.dataTables',
        'block_configurable_reports/codemirror',
        'block_configurable_reports/sql',
        'core/config',
        'core/notification',
        'require'
    ], function($, tablesorter, dataTable, CodeMirror, sql, Config, Notification, require) {

        // The editor wrapping the SQL query textarea, when syntax highlighting is on. Setting the
        // textarea's own value is not enough once CodeMirror has taken it over.
        var queryeditor = null;

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
                var defaults = {
                    'bAutoWidth': false,
                    'sPaginationType': 'full_numbers',
                    'fixedHeader': true,
                    'aaSorting': [],
                    'oLanguage': {
                        'oAria': {
                            'sSortAscending': M.str.block_configurable_reports.datatables_sortascending,
                            'sSortDescending': M.str.block_configurable_reports.datatables_sortdescending,
                        },
                        'oPaginate': {
                            'sFirst': M.str.block_configurable_reports.datatables_first,
                            'sLast': M.str.block_configurable_reports.datatables_last,
                            'sNext': M.str.block_configurable_reports.datatables_next,
                            'sPrevious': M.str.block_configurable_reports.datatables_previous
                        },
                        'sEmptyTable': M.str.block_configurable_reports.datatables_emptytable,
                        'sInfo': M.str.block_configurable_reports.datatables_info,
                        'sInfoEmpty': M.str.block_configurable_reports.datatables_infoempty,
                        'sInfoFiltered': M.str.block_configurable_reports.datatables_infofiltered,
                        'sInfoThousands': M.str.langconfig.thousandssep,
                        'sLengthMenu': M.str.block_configurable_reports.datatables_lengthmenu,
                        'sLoadingRecords': M.str.block_configurable_reports.datatables_loadingrecords,
                        'sProcessing': M.str.block_configurable_reports.datatables_processing,
                        'sSearch': M.str.block_configurable_reports.datatables_search,
                        'sZeroRecords': M.str.block_configurable_reports.datatables_zerorecords
                    }
                };

                // The report's own options replace the defaults with the same keys. Arrays are replaced, not merged.
                var options = $.extend(true, {}, defaults, params.options || {});
                Object.keys(params.options || {}).forEach(function(key) {
                    if (Array.isArray(params.options[key])) {
                        options[key] = params.options[key];
                    }
                });

                // Load the extensions only on the report page. They register themselves with DataTables.
                require([
                    'block_configurable_reports/dataTables.buttons',
                    'block_configurable_reports/buttons.colVis',
                    'block_configurable_reports/buttons.print',
                    'block_configurable_reports/dataTables.rowGroup'
                ], function() {
                    // The 'display' class gives the DataTables default style: row lines, stripes, hover and ordered column.
                    $(params.selector).addClass('display').dataTable(options);
                });
            },
            cmirror: function() {
                // Documentation can be found @ http://codemirror.net/
                var query = document.getElementById('id_querysql');
                if (query) {
                    queryeditor = CodeMirror.fromTextArea(query, {
                        mode: "text/x-mysql",
                        rtlMoveVisually: true,
                        indentWithTabs: true,
                        smartIndent: true,
                        lineNumbers: true,
                        autofocus: true,
                    });
                }
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