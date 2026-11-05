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
 * Unit tests for the bulk actions of a report
 *
 * @package    block_configurable_reports
 * @copyright  2026 Likeminded Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_configurable_reports;

use advanced_testcase;
use block_configurable_reports\fixtures\report_actions_watcher;
use block_configurable_reports\hook\report_actions;
use coding_exception;
use moodle_url;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/configurable_reports/locallib.php');
require_once($CFG->dirroot . '/blocks/configurable_reports/report.class.php');
require_once($CFG->dirroot . '/blocks/configurable_reports/reports/sql/report.class.php');
require_once(__DIR__ . '/fixtures/report_actions_watcher.php');

// Totara has no advanced_testcase. A class whose parent is not known yet is declared when this line has run.
if (!class_exists('advanced_testcase')) {
    class_alias(\core_phpunit\testcase::class, 'advanced_testcase');
}

/**
 * Tests for the report_actions hook and report::get_bulk_actions()
 *
 * @package    block_configurable_reports
 * @covers     \block_configurable_reports\hook\report_actions
 * @covers     \report_base::get_bulk_actions
 */
#[CoversClass(report_actions::class)]
final class report_actions_test extends advanced_testcase {

    /**
     * Skip on Moodle: the hook is Totara only.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists(\totara_core\hook\manager::class)) {
            $this->markTestSkipped('The report_actions hook needs the Totara hook manager.');
        }
        \totara_core\hook\manager::phpunit_replace_watchers([[
            'hookname' => report_actions::class,
            'callback' => report_actions_watcher::class . '::add_actions',
        ]]);
    }

    /**
     * Create a SQL report record.
     *
     * @return \report_sql
     */
    private function create_report(): \report_sql {
        global $DB, $SITE, $USER;
        $components = ['customsql' => ['config' => (object) ['querysql' => 'SELECT id FROM {user}']]];
        $id = $DB->insert_record('block_configurable_reports', (object) [
            'courseid' => $SITE->id, 'ownerid' => $USER->id, 'visible' => 1, 'name' => 'Actions', 'summary' => '',
            'type' => 'sql', 'components' => cr_serialize($components), 'remote' => 0,
        ]);
        return new \report_sql($DB->get_record('block_configurable_reports', ['id' => $id]));
    }

    /**
     * The page gets only the actions that the report asks for and a watcher gives, in the order of the report.
     */
    public function test_report_gets_requested_actions_in_report_order(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $report = $this->create_report();

        $actions = $report->get_bulk_actions(['local_test/first', 'local_other/none', 'local_test/second']);

        $this->assertSame(['local_test/first', 'local_test/second'], array_column($actions, 'id'));
        $this->assertSame('Label of local_test/first', $actions[0]['label']);
        $this->assertSame((new moodle_url('/local/test/action.php', ['id' => 'local_test/first']))->out(false),
            $actions[0]['url']);
    }

    /**
     * A report that asks for no action does not run the hook.
     */
    public function test_report_without_actions_gets_none(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame([], $this->create_report()->get_bulk_actions([]));
    }

    /**
     * A watcher cannot add an action that the report does not ask for.
     */
    public function test_action_not_requested_is_refused(): void {
        $hook = new report_actions((object) ['id' => 7], ['local_test/first']);

        $this->expectException(coding_exception::class);
        $hook->add_action('local_test/second', 'Second', new moodle_url('/local/test/action.php'));
    }
}
