define(
    [
        'jquery',
        'block_configurable_reports/jquery.tablesorter',
        'block_configurable_reports/jquery.dataTables',
        'block_configurable_reports/codemirror',
        'block_configurable_reports/javascript',
        'block_configurable_reports/sql',
        // datatables extensions:
        'block_configurable_reports/dataTables.select',
        'block_configurable_reports/dataTables.buttons',
        'block_configurable_reports/buttons.colVis',
        'block_configurable_reports/buttons.print',
        'block_configurable_reports/dataTables.rowGroup',
        'block_configurable_reports/dataTables.autoFill',
        'block_configurable_reports/dataTables.colReorder',
        'block_configurable_reports/dataTables.fixedColumns',
        'block_configurable_reports/dataTables.fixedHeader',
        'block_configurable_reports/dataTables.keyTable',
        'block_configurable_reports/dataTables.responsive',
        'block_configurable_reports/dataTables.rowReorder',
        'block_configurable_reports/dataTables.scroller'
    ], function ($, tablesorter, dataTable, CodeMirror) {
    return {
        js_order: function (params) {
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
        add_jsdatatables: function (params) {
            var configuration = null;
            eval('configuration = ' + params.dtconfig);

            var vaa = {
                rowGroup: {
                    dataSrc: 3
                },
                columnDefs: [
                    {
                        targets: [3],
                        visible: false
                    }
                ],
                "paging": false,
                "autoWidth": true,
                "buttons": [
                    'colvis',
                    'print'
                ]
            };

            $(params.selector).dataTable(configuration);
        },
        cmirror: function() {
            // Documentation can be found @ http://codemirror.net/
            CodeMirror.fromTextArea(document.getElementById('id_querysql'), {
                mode: "text/x-mysql",
                rtlMoveVisually: true,
                indentWithTabs: true,
                smartIndent: true,
                lineNumbers: true,
                autofocus: true,
            });

            CodeMirror.fromTextArea(document.getElementById('id_remotequerysql'), {
                mode: "text/x-mysql",
                rtlMoveVisually: true,
                indentWithTabs: true,
                smartIndent: true,
                lineNumbers: true,
                //    autofocus: true
            });

            CodeMirror.fromTextArea(document.getElementById('id_datatablesconfig'), {
                mode: "text/javascript",
                rtlMoveVisually: true,
                smartIndent: true,
                lineNumbers: true,
            });
        }
    };
});