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
 * Test watcher of the report_actions hook
 *
 * @package    block_configurable_reports
 * @copyright  2026 Likeminded Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_configurable_reports\fixtures;

use block_configurable_reports\hook\report_actions;
use moodle_url;

/**
 * Add the actions that a plugin "local_test" owns, as a real watcher does.
 *
 * @package    block_configurable_reports
 */
class report_actions_watcher {

    /** Action ids that this watcher owns. */
    const ACTIONS = ['local_test/second', 'local_test/first'];

    /**
     * Add each owned action that the report asks for.
     *
     * @param report_actions $hook
     */
    public static function add_actions(report_actions $hook): void {
        foreach (self::ACTIONS as $id) {
            if ($hook->is_requested($id)) {
                $hook->add_action($id, "Label of $id", new moodle_url('/local/test/action.php', ['id' => $id]));
            }
        }
    }
}
