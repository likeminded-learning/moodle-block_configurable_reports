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
 * Unit tests for links that SQL reports put in their cells
 *
 * @package    block_configurable_reports
 * @copyright  2026 Likeminded Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_configurable_reports;

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/configurable_reports/locallib.php');
require_once($CFG->dirroot . '/blocks/configurable_reports/report.class.php');
require_once($CFG->dirroot . '/blocks/configurable_reports/reports/sql/report.class.php');

// Totara has no advanced_testcase. A class whose parent is not known yet is declared when this line has run.
if (!class_exists('advanced_testcase')) {
    class_alias(\core_phpunit\testcase::class, 'advanced_testcase');
}

/**
 * Tests for the links in SQL report cells
 *
 * @package    block_configurable_reports
 * @covers     \report_sql
 */
#[CoversClass(\report_sql::class)]
final class sql_report_links_test extends advanced_testcase {

    /**
     * A link with a query string keeps its "?" in the report table.
     */
    public function test_a_link_keeps_its_question_mark(): void {
        global $DB, $SITE, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user(['username' => 'crlink']);

        $query = "SELECT CONCAT('<a href=\"/blocks/configurable_reports/viewreport.php?id=88&filter_var=', u.id, '\">View</a>')
                         AS link
                    FROM {user} u
                   WHERE u.username = 'crlink'";
        $components = ['customsql' => ['config' => (object) ['querysql' => $query]]];
        $id = $DB->insert_record('block_configurable_reports', (object) [
            'courseid' => $SITE->id, 'ownerid' => $USER->id, 'visible' => 1, 'name' => 'Links', 'summary' => '',
            'type' => 'sql', 'components' => cr_serialize($components), 'remote' => 0,
        ]);
        $report = new \report_sql($DB->get_record('block_configurable_reports', ['id' => $id]));

        $this->assertTrue($report->create_report());

        $this->assertSame(
            '<a href="/blocks/configurable_reports/viewreport.php?id=88&amp;filter_var=' . $user->id . '">View</a>',
            $report->finalreport->table->data[0][0]
        );
    }
}
