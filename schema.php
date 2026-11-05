<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Get the tables and columns of the database, for the completion of the SQL editor.
 *
 * @package    block_configurable_reports
 * @copyright  2026 Likeminded Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require(dirname(__FILE__, 3) . '/config.php');
require_once($CFG->dirroot . '/blocks/configurable_reports/locallib.php');

$context = block_configurable_reports_require_course_login();
require_sesskey();
if (!block_configurable_reports_can_managesqlreports($context)) {
    throw new moodle_exception('nosqlpermissions', 'block_configurable_reports');
}

// Table names without the prefix: the SQL writes them as prefix_<name>. The database meta cache keeps this fast.
$tables = [];
foreach ($DB->get_tables() as $table) {
    $tables[$table] = array_keys($DB->get_columns($table));
}
ksort($tables);

echo json_encode(['tables' => $tables]);
