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
 * Form to create a report from the report repository.
 *
 * @package    block_configurable_reports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/formslib.php');

/**
 * Class repository_form
 *
 * @package    block_configurable_reports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_form extends moodleform {

    /**
     * Form definition
     */
    public function definition(): void {
        global $DB;

        $mform =& $this->_form;

        $mform->addElement('header', 'createfromrepository', get_string('createfromrepository', 'block_configurable_reports'));

        $select = $mform->createElement('select', 'repositoryreport', get_string('repositoryreport', 'block_configurable_reports'));
        $this->_customdata['repository']->add_options($select, $DB->get_dbfamily());
        $mform->addElement($select);
        $mform->addHelpButton('repositoryreport', 'createfromrepository', 'block_configurable_reports');
        $mform->addRule('repositoryreport', null, 'required');

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons(false, get_string('createfromrepository', 'block_configurable_reports'));
    }

    /**
     * Only report paths can be chosen.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!\block_configurable_reports\report_repository::is_report_path($data['repositoryreport'] ?? '')) {
            $errors['repositoryreport'] = get_string('required');
        }

        return $errors;
    }

}
