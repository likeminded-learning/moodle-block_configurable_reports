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
 * Get the SQL or the DataTables options of a report in the report repository.
 *
 * @copyright  2020 Juan Leyva <juan@moodle.com>
 * @package    block_configurable_reports
 * @author     Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require(dirname(__FILE__, 3) . '/config.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/blocks/configurable_reports/locallib.php');

use block_configurable_reports\report_repository;

$context = block_configurable_reports_require_course_login();
require_sesskey();
if (!block_configurable_reports_can_managesqlreports($context)) {
    throw new moodle_exception('nosqlpermissions', 'block_configurable_reports');
}

$path = required_param('path', PARAM_PATH);
$part = required_param('part', PARAM_ALPHA);
if (!in_array($part, [report_repository::PART_SQL, report_repository::PART_DATATABLES], true)) {
    throw new invalid_parameter_exception('part');
}

if (!$repository = report_repository::from_config()) {
    throw new moodle_exception('errorrepositorysetting', 'block_configurable_reports', '', '');
}

$content = $repository->get_part($path, $part, $DB->get_dbfamily());
if ($content === null) {
    throw new moodle_exception('repositoryreportnotavailable', 'block_configurable_reports', '', s($path));
}

echo json_encode(['content' => $content]);
