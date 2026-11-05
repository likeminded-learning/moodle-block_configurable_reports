=======================
CodeMirror
=======================
https://github.com/codemirror/codemirror

Instructions to import 'CodeMirror' into Moodle:

1. Download the latest release from https://github.com/codemirror/codemirror

2. Execute 'npm install'

3. Copy 'lib/codemirror.js' into 'amd/src/codemirror.js':

   3.a. Add /*eslint-ignore*/ to the beginning of the file.


4. Copy 'mode/sql/sql.js' into 'amd/src/sql.js':

   4.a. Add /*eslint-ignore*/ to the beginning of the file.

   4.b. Replace lib reference reference
   ------------------
    define(["../../lib/codemirror"], mod);
   ------------------

   to
   ------------------
    define(["block_configurable_reports/codemirror"], mod);
   ------------------

5. Copy all the styles from 'lib/codemirror.css' into the
"CodeMirror styles" section in 'styles.css'.


6. Execute grunt to compile js
   grunt js


=======================
DataTables
=======================
https://cdn.datatables.net/releases.html

Instructions to import 'DataTables' into Moodle (Likeminded fork: DataTables 3):

1. Download the npm packages 'datatables.net' and 'datatables.net-dt' of the
same version, for example: npm pack datatables.net@3.1.5 datatables.net-dt@3.1.5

2. Copy 'js/dataTables.js' into 'amd/src/jquery.dataTables.js':

   2.a. Add /*eslint-disable*/ to the beginning of the file.

   2.b. DataTables 3 does not need jQuery. Give it the page jQuery, so that
   $.fn.dataTable and the jQuery events ('init.dt') work. Replace the AMD define:
   ------------------
		define([], function () {
			return factory(window, document);
		});
   ------------------

   to
   ------------------
		define(['jquery'], function ($) {
			var DataTable = factory(window, document);
			DataTable.use($);
			return DataTable;
		});
   ------------------

3. Put 'css/dataTables.dataTables.css' of 'datatables.net-dt' into the
"DataTables styles" section in 'styles.css'. It has no images.

   3.a. Prefix every selector with '#page-blocks-configurable_reports-viewreport '.
   Moodle serves each plugin's styles.css on every page of the site, so selectors
   as broad as 'table.dataTable' would otherwise restyle the tables of any other
   plugin that uses DataTables.

   3.b. Remove the '@charset' line. Change the ':root' selector, which holds the
   '--dt-*' colour variables, to '#page-blocks-configurable_reports-viewreport'.

4. Execute grunt to compile js, then remove the module names (see below).
   grunt js



=======================
Totara: AMD builds without module names (Likeminded fork)
=======================
Totara adds the module name to the first "define(" of each AMD file when it serves it.
A build that already names its module, as the Moodle 4 grunt builds do, then has two
names, and RequireJS reads the module as a string. After a merge from upstream or a
rebuild, remove the names from the builds:

    sed -i -E '0,/define\("block_configurable_reports\/[^"]+",\s*/s//define(/' amd/build/*.min.js


=======================
CodeMirror 6 SQL editor (Likeminded fork)
=======================
The Custom SQL form uses CodeMirror 6 with @codemirror/lang-sql: the dialect of the site
database, completion of prefix_ tables, columns and %%...%% placeholders, and a mark for
unbalanced brackets. The source is cm6/src/sqleditor.js; the versions are pinned in
cm6/package.json. CodeMirror 6 is only published as ES modules, so esbuild bundles it into
one AMD module:

    cd cm6 && npm ci --ignore-scripts && ./build.sh

This writes amd/src/sqleditor-lazy.js and amd/build/sqleditor-lazy.min.js. The "-lazy"
name keeps the module out of the JavaScript that every page loads. The CodeMirror 5 files
above stay for merges from upstream, but main.js does not use them.


=======================
DataTables extensions (Buttons, RowGroup, Select)
=======================
https://www.npmjs.com/package/datatables.net

Use the extension releases made for the DataTables version in
'amd/src/jquery.dataTables.js' (see the "peerDependencies" of each package).
They are free (MIT). Only the "DataTables Plus" extensions, such as Editor, are paid.

1. Download the npm packages 'datatables.net-buttons', 'datatables.net-rowgroup'
and 'datatables.net-select', and their '-dt' style packages. Take these files:
   js/dataTables.buttons.js
   js/buttons.colVis.js
   js/buttons.print.js
   js/dataTables.rowGroup.js
   js/dataTables.select.js

2. Copy each one into 'amd/src/' with the same name:

   2.a. Add /*eslint-disable*/ to the beginning of the file.

   2.b. In the define() call only, replace the module names:
   ------------------
    'datatables.net'          -> 'block_configurable_reports/jquery.dataTables'
    'datatables.net-buttons'  -> 'block_configurable_reports/dataTables.buttons'
   ------------------

3. Take 'css/buttons.dataTables.css', 'css/rowGroup.dataTables.css' and
'css/select.dataTables.css'. Prefix them as in step 3 above, and put them into
the "DataTables Buttons styles", "DataTables RowGroup styles" and
"DataTables Select styles" sections in 'styles.css'.

4. Execute grunt to compile js
   grunt js


=======================
TableSorter
=======================
https://github.com/Mottie/tablesorter

Instructions to import 'TableSorter' into Moodle:

1. Download the latest release from https://github.com/Mottie/tablesorter

2. Copy 'js/jquery.tablesorter.js' into 'amd/src/jquery.tablesorter.js':

   2.a. Add the following lines to the beginning of the file:
   ------------------
   /*eslint-ignore*/
   (function(factory){
     if (typeof define === 'function' && define.amd){
        define(['jquery'], factory);
     } else if (typeof module === 'object' && typeof module.exports === 'object'){
        module.exports = factory(require('jquery'));
     } else {
        factory(jQuery);
     }
   }(function(jQuery){
   ------------------

   2.b. Add the following lines at the end of the file:
   ------------------
    return jQuery.tablesorter;}));
   ------------------


3. Execute grunt to compile js
   grunt js


=======================
pChart
=======================
http://www.pchart.net/

Instructions to import 'pChart' into Moodle:

1. Access to the 'lib/' folder

2. Remove the 'pChart2' folder.

3. Download the latest release for this library compatible with PHP 7 from
https://github.com/bozhinov/pChart2.0-for-PHP7 using the following command:

git clone -b 7.x-compatible https://github.com/bozhinov/pChart2.0-for-PHP7.git pChart2

   3.a. Remove the folders 'examples' and 'cache' and the file 'index.php'.

4. The charts use a font shipped with pChart2, not one of our own. The Tahoma,
GeosansLight and MankSans fonts that used to live in 'lib/Fonts/' were removed
because they are not redistributable, so do not restore them: keep
components/plot/line/graph.php and components/plot/pie/graph.php pointing at
'lib/pChart2/fonts/Cairo-Regular.ttf' (OFL licensed).

5. In 'class/pDraw.php', drop the $num_points argument from every
imagefilledpolygon() call, keeping the three argument form:

   ------------------
    imagefilledpolygon($this->Picture, $Points, $PointCount / 2, $color);
   ------------------

   to
   ------------------
    imagefilledpolygon($this->Picture, $Points, $color);
   ------------------

   PHP 8.1 deprecated that argument, so the released library writes a
   deprecation notice into the middle of every image it draws. Check whether
   the release has fixed this before patching it again.


=======================
After importing any of them
=======================

Update 'thirdpartylibs.xml' with the new version and licence of the library.
