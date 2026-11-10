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
 * Hook that collects the bulk actions of a report
 *
 * @package    block_configurable_reports
 * @copyright  2026 Likeminded Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_configurable_reports\hook;

use coding_exception;
use moodle_url;
use stdClass;

/**
 * Collect the bulk actions that a report asks for.
 *
 * A SQL report asks for actions with the "actions" key of its DataTables options, for example
 * {"actions": ["local_reportactions/unenrol"]}. The view page runs this hook with those ids. A watcher adds
 * each id that it owns, after it checks the capabilities of the user. The page shows one button for each
 * action. The button posts the values of the selected rows to the action URL:
 *
 * - values[]  : the data of the Select checkbox column ("render": "select") of each selected row
 * - reportid  : the report id
 * - sesskey   : the session key
 * - returnurl : the report page
 *
 * Totara only: the class extends the Totara hook base.
 *
 * @package    block_configurable_reports
 */
class report_actions extends \totara_core\hook\base {

    /** @var stdClass the report record */
    private $report;

    /** @var string[] action ids that the report asks for */
    private $requested;

    /** @var array[] action id => ['id' => string, 'label' => string, 'url' => string] */
    private $actions = [];

    /**
     * Constructor.
     *
     * @param stdClass $report the block_configurable_reports record
     * @param string[] $requested action ids from the report options
     */
    public function __construct(stdClass $report, array $requested) {
        $this->report = $report;
        $this->requested = array_values(array_unique($requested));
    }

    /**
     * Get the report record.
     *
     * @return stdClass
     */
    public function get_report(): stdClass {
        return $this->report;
    }

    /**
     * Return true when the report asks for the action.
     *
     * @param string $id action id, for example "local_reportactions/unenrol"
     * @return bool
     */
    public function is_requested(string $id): bool {
        return in_array($id, $this->requested, true);
    }

    /**
     * Add an action that the report asks for. Check the capabilities of the user before you add it.
     *
     * @param string $id action id
     * @param string $label button text
     * @param moodle_url $url page that gets the POST
     * @throws coding_exception when the report does not ask for the action
     */
    public function add_action(string $id, string $label, moodle_url $url): void {
        if (!$this->is_requested($id)) {
            throw new coding_exception("Report {$this->report->id} does not ask for the action '$id'");
        }
        $this->actions[$id] = ['id' => $id, 'label' => $label, 'url' => $url->out(false)];
    }

    /**
     * Get the added actions, in the order of the report options.
     *
     * @return array[] list of ['id' => string, 'label' => string, 'url' => string]
     */
    public function get_actions(): array {
        $actions = [];
        foreach ($this->requested as $id) {
            if (isset($this->actions[$id])) {
                $actions[] = $this->actions[$id];
            }
        }
        return $actions;
    }
}
