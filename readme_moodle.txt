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

Instructions to import 'DataTables' into Moodle:

1. Download the latest release for 'jquery.dataTables.js' from
https://cdn.datatables.net/releases.html

2. Copy 'jquery.dataTables.js' into 'amd/src/jquery.dataTables.js':

   2.a. Add /*eslint-ignore*/ to the beginning of the file.

3. Download 'css/jquery.dataTables.css' and replace all the references from:
   ------------------
    url('../images/xxxxx.png')
   ------------------

   to
   ------------------
    url([[pix:block_configurable_reports|datatable/xxxxx]])
   ------------------

4. Copy the new 'css/jquery.dataTables.css' after replacing the references to
the image folder into the "DataTables styles" section in 'styles.css'.

   4.a. Prefix every selector with '#page-blocks-configurable_reports-viewreport '.
   Moodle serves each plugin's styles.css on every page of the site, so selectors
   as broad as 'table.dataTable' would otherwise restyle the tables of any other
   plugin that uses DataTables.

   4.b. Remove the '@charset' line. Change the ':root' selector, which holds the
   '--dt-*' colour variables, to '#page-blocks-configurable_reports-viewreport'.

5. Execute grunt to compile js
   grunt js



=======================
DataTables extensions (Buttons, RowGroup)
=======================
https://cdn.datatables.net/releases.html

Use the extension releases made for the DataTables version in
'amd/src/jquery.dataTables.js'.

1. Download these files from https://cdn.datatables.net/:
   buttons/<version>/js/dataTables.buttons.js
   buttons/<version>/js/buttons.colVis.js
   buttons/<version>/js/buttons.print.js
   rowgroup/<version>/js/dataTables.rowGroup.js

2. Copy each one into 'amd/src/' with the same name:

   2.a. Add /*eslint-disable*/ to the beginning of the file.

   2.b. In the define() call only, replace the module names:
   ------------------
    'datatables.net'          -> 'block_configurable_reports/jquery.dataTables'
    'datatables.net-buttons'  -> 'block_configurable_reports/dataTables.buttons'
   ------------------

3. Download 'css/buttons.dataTables.css' and 'css/rowGroup.dataTables.css'.
Prefix every selector with '#page-blocks-configurable_reports-viewreport '
(after 'html.dark ' where a selector starts with it), and put them into the
"DataTables Buttons styles" and "DataTables RowGroup styles" sections in
'styles.css'.

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
