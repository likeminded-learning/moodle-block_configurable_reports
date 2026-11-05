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
 * Download an SQL report in the format of the report repository, as a zip of its folder.
 *
 * @package    block_configurable_reports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");

require_once($CFG->dirroot . "/blocks/configurable_reports/locallib.php");

use block_configurable_reports\report_repository;

$id = required_param('id', PARAM_INT);

if (!$report = $DB->get_record('block_configurable_reports', ['id' => $id])) {
    throw new moodle_exception('reportdoesnotexists', 'block_configurable_reports');
}

if (!$course = $DB->get_record('course', ['id' => $report->courseid])) {
    throw new moodle_exception('nosuchcourseid', 'block_configurable_reports');
}

// Force user login in course (SITE or Course).
if ((int) $course->id === SITEID) {
    require_login();
    $context = context_system::instance();
} else {
    require_login($course->id);
    $context = context_course::instance($course->id);
}

$PAGE->set_context($context);
require_sesskey();

if (!has_capability('block/configurable_reports:managereports', $context) &&
    !(has_capability('block/configurable_reports:manageownreports', $context) && $report->ownerid == $USER->id)) {
    throw new moodle_exception('badpermissions', 'block_configurable_reports');
}
if (!block_configurable_reports_can_managesqlreports($context)) {
    throw new moodle_exception('nosqlpermissions', 'block_configurable_reports');
}

$folder = trim(preg_replace('/[^a-z0-9]+/', '-', core_text::strtolower(format_string($report->name))), '-') ?: 'report-' . $report->id;
$files = [];
foreach (report_repository::export_files($report, $DB->get_dbfamily()) as $name => $content) {
    $files[$folder . '/' . $name] = [$content];
}

\block_configurable_reports\event\report_exported::create_from_report($report, $context, ['format' => 'repository'])->trigger();

$zipfile = make_request_directory() . '/' . $folder . '.zip';
if (!get_file_packer('application/zip')->archive_to_pathname($files, $zipfile)) {
    throw new moodle_exception('errorrepositoryexport', 'block_configurable_reports');
}
send_temp_file($zipfile, $folder . '.zip');
