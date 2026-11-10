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
 * Configurable Reports a Moodle block for creating customizable reports
 *
 * @copyright  2020 Juan Leyva <juan@moodle.com>
 * @package   block_configurable_reports
 * @author    Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once($CFG->dirroot . "/blocks/configurable_reports/locallib.php");
require_once('import_form.php');
require_once('repository_form.php');

use block_configurable_reports\report_repository;

$courseid = optional_param('courseid', SITEID, PARAM_INT);

if (!$course = $DB->get_record("course", ['id' => $courseid])) {
    throw new moodle_exception("No such course id");
}

// Force user login in course (SITE or Course).
if ($course->id == SITEID) {
    require_login();
    $context = context_system::instance();
} else {
    require_login($course->id);
    $context = context_course::instance($course->id);
}

if (!has_capability('block/configurable_reports:managereports', $context) &&
    !has_capability('block/configurable_reports:manageownreports', $context)) {
    throw new moodle_exception('badpermissions', 'block_configurable_reports');
}

$PAGE->set_url('/blocks/configurable_reports/managereport.php', ['courseid' => $course->id]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

$mform = new import_form(null, $course->id);

if ($data = $mform->get_data()) {
    if ($xml = $mform->get_file_content('userfile')) {
        if (cr_import_xml($xml, $course)) {
            redirect(
                "$CFG->wwwroot/blocks/configurable_reports/managereport.php?courseid={$course->id}",
                get_string('reportcreated', 'block_configurable_reports')
            );
        } else {
            throw new moodle_exception('errorimporting', 'block_configurable_reports');
        }
    }
}

// Reports from the report repository. Only SQL reports are in it.
$repositoryform = null;
$repositoryerror = '';
if (block_configurable_reports_can_managesqlreports($context) && $repository = report_repository::from_config()) {
    try {
        $repositoryform = new repository_form(null, ['repository' => $repository, 'courseid' => $course->id]);
    } catch (moodle_exception $e) {
        // The page still works without the repository, so show why it is not there.
        $repositoryerror = $e->getMessage();
    }
}

if ($repositoryform && $data = $repositoryform->get_data()) {
    $entry = $repository->get_report($data->repositoryreport, $DB->get_dbfamily());
    $newreport = report_repository::build_report($entry, $course->id, $USER->id);
    $newreport->id = $DB->insert_record('block_configurable_reports', $newreport);
    \block_configurable_reports\event\report_created::create_from_report($newreport, null, ['imported' => true])->trigger();
    redirect(new moodle_url('/blocks/configurable_reports/editreport.php', ['id' => $newreport->id]),
        get_string('reportcreated', 'block_configurable_reports'));
}

$reports = cr_get_my_reports($course->id, $USER->id);

$title = get_string('reports', 'block_configurable_reports');

$PAGE->navbar->add(get_string('managereports', 'block_configurable_reports'));

$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_cacheable(true);

echo $OUTPUT->header();

if ($reports) {
    $table = new stdclass;
    $table->width = "100%";
    $table->head = [
        get_string('name'),
        get_string('reportsmanage', 'admin') . ' ' . get_string('course'),
        get_string('type', 'block_configurable_reports'),
        get_string('username'),
        get_string('edit'),
        get_string('download', 'block_configurable_reports'),
    ];
    $table->align = ['left', 'left', 'left', 'left', 'center', 'center'];
    $table->size = ['30%', '10%', '10%', '10%', '20%', '20%'];
    $stredit = get_string('edit');
    $strdelete = get_string('delete');
    $strhide = get_string('hide');
    $strshow = get_string('show');
    $strcopy = get_string('duplicate');
    $strexport = get_string('exportreport', 'block_configurable_reports');
    $strexportrepository = get_string('exportrepository', 'block_configurable_reports');
    $exportformats = cr_get_export_plugins();

    foreach ($reports as $r) {
        if ($r->courseid == 1) {
            $coursename = '<a href="' . $CFG->wwwroot . '">' . get_string('site') . '</a>';
        } else if (!$coursename = $DB->get_field('course', 'fullname', ['id' => $r->courseid])) {
            $coursename = get_string('deleted');
        } else {
            $coursename = format_string($coursename);
            $coursename =
                '<a href="' . $CFG->wwwroot . '/blocks/configurable_reports/managereport.php?courseid=' . $r->courseid . '">' .
                $coursename . '</a>';
        }

        if ($owneruser = $DB->get_record('user', ['id' => $r->ownerid])) {
            $owner = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $r->ownerid . '">' . fullname($owneruser) . '</a>';
        } else {
            $owner = get_string('deleted');
        }
        if ($r->type === 'sql' && !block_configurable_reports_can_managesqlreports($context)) {
            $editcell = '';
        } else {
            $editcell = '<a title="' . $stredit . '"  href="editreport.php?id=' . $r->id . '">' .
                $OUTPUT->pix_icon('t/edit', $stredit) .
                '</a>&nbsp;&nbsp;';
            $editcell .= '<a title="' . $strdelete . '"  href="editreport.php?id=' . $r->id . '&amp;delete=1&amp;sesskey=' .
                $USER->sesskey . '">' .
                $OUTPUT->pix_icon('t/delete', $strdelete) .
                '</a>&nbsp;&nbsp;';

            if (!empty($r->visible)) {
                $editcell .= '<a title="' . $strhide . '" href="editreport.php?id=' . $r->id . '&amp;hide=1&amp;sesskey=' .
                    $USER->sesskey . '">' .
                    $OUTPUT->pix_icon('t/hide', $strhide) .
                    '</a> ';
            } else {
                $editcell .= '<a title="' . $strshow . '" href="editreport.php?id=' . $r->id . '&amp;show=1&amp;sesskey=' .
                    $USER->sesskey . '">' .
                    $OUTPUT->pix_icon('t/show', $strshow) .
                    '</a> ';
            }
            $editcell .= '<a title="' . $strcopy . '" href="editreport.php?id=' . $r->id . '&amp;duplicate=1&amp;sesskey=' .
                $USER->sesskey . '">' .
                $OUTPUT->pix_icon('t/copy', $strcopy) .
                '</a>&nbsp;&nbsp;';
            $editcell .= '<a title="' . $strexport . '" href="export.php?id=' . $r->id . '&amp;sesskey=' . $USER->sesskey . '">' .
                $OUTPUT->pix_icon('t/backup', $strexport) .
                '</a>&nbsp;&nbsp;';
            if ($r->type === report_repository::REPORT_TYPE) {
                $url = new moodle_url('/blocks/configurable_reports/export_repository.php',
                    ['id' => $r->id, 'sesskey' => sesskey()]);
                $editcell .= html_writer::link($url, $OUTPUT->pix_icon('t/download', $strexportrepository),
                    ['title' => $strexportrepository]) . '&nbsp;&nbsp;';
            }

        }

        $download = '';
        $export = explode(',', $r->export);
        if (!empty($export)) {
            foreach ($export as $e) {
                // Only known formats, the value may come from an imported report.
                if ($e && isset($exportformats[$e])) {
                    $url = new moodle_url('/blocks/configurable_reports/viewreport.php',
                        ['id' => $r->id, 'download' => 1, 'format' => $e]);
                    $icon = html_writer::empty_tag('img',
                        ['src' => new moodle_url('/blocks/configurable_reports/export/' . $e . '/pix.gif'), 'alt' => $e]);
                    $download .= html_writer::link($url, $icon . '&nbsp;' . s(strtoupper($e))) . '&nbsp;&nbsp;';
                }
            }
        }

        $table->data[] = [
            '<a href="viewreport.php?id=' . $r->id . '">' . format_string($r->name) . '</a>',
            $coursename,
            get_string('report_' . $r->type, 'block_configurable_reports'),
            $owner,
            $editcell,
            $download,
        ];
    }

    $table->id = 'reportslist';
    cr_add_jsordering("#reportslist", $PAGE);
    cr_print_table($table);
} else {
    echo $OUTPUT->heading(get_string('noreportsavailable', 'block_configurable_reports'));
}

$addreporturl = $CFG->wwwroot . '/blocks/configurable_reports/editreport.php?courseid=' . $course->id;
echo $OUTPUT->heading(
    '<div class="addbutton">
                       <a href="' . $addreporturl . '" class="btn btn-secondary">' .
    get_string('addreport', 'block_configurable_reports') .
    '</a> </div>'
);

if ($repositoryform) {
    $repositoryform->display();
} else if ($repositoryerror !== '') {
    echo $OUTPUT->notification(s($repositoryerror), 'notifyproblem');
}

$mform->display();

echo $OUTPUT->footer();
