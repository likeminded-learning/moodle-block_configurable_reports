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
 * @package    block_configurable_reports
 * @author     Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/formslib.php');

use block_configurable_reports\datatables_config;
use block_configurable_reports\report_repository;

/**
 * Class customsql_form
 *
 * @package   block_configurable_reports
 * @author    Juan leyva <http://www.twitter.com/jleyvadelgado>
 */
class customsql_form extends moodleform {

    // Based on Custom SQL Reports Plugin.
    // See http://moodle.org/mod/data/view.php?d=13&rid=2884.

    /**
     * Form definition
     */
    public function definition(): void {
        global $COURSE, $DB;

        $mform =& $this->_form;

        $mform->addElement('textarea', 'querysql', get_string('querysql', 'block_configurable_reports'),
            'rows="35" cols="80" spellcheck="false" style="font-family: monospace;"');
        $mform->addRule('querysql', get_string('required'), 'required', null, 'client');
        $mform->setType('querysql', PARAM_RAW);

        $mform->addElement('hidden', 'courseid', $COURSE->id);
        $mform->setType('courseid', PARAM_INT);

        if (get_config('block_configurable_reports', 'reporttableui') === 'datatables') {
            $mform->addElement('textarea', 'datatablesconfig', get_string('datatablesconfig', 'block_configurable_reports'),
                'rows="10" cols="80" spellcheck="false" style="font-family: monospace;"');
            $mform->setType('datatablesconfig', PARAM_RAW);
            $mform->addHelpButton('datatablesconfig', 'datatablesconfig', 'block_configurable_reports');
        }

        $this->add_action_buttons();

        $mform->addElement('static', 'note', '', get_string('listofsqlreports', 'block_configurable_reports'));

        if ($repository = report_repository::from_config()) {
            try {
                $select = $mform->createElement('select', 'repositoryreport',
                    get_string('repositoryreport', 'block_configurable_reports'));
                $repository->add_options($select, $DB->get_dbfamily());
                $mform->addElement($select);
                $mform->addHelpButton('repositoryreport', 'repositoryreport', 'block_configurable_reports');

                $buttons = [$mform->createElement('button', 'pullsql', get_string('pullsql', 'block_configurable_reports'))];
                if ($mform->elementExists('datatablesconfig')) {
                    $buttons[] = $mform->createElement('button', 'pulldatatables',
                        get_string('pulldatatables', 'block_configurable_reports'));
                }
                $mform->addGroup($buttons, 'pullbuttons', '', ' ', false);
            } catch (moodle_exception $e) {
                // The form still works without the repository, so show why it is not there.
                $mform->addElement('static', 'repositoryerror', get_string('repositoryreport', 'block_configurable_reports'),
                    $e->getMessage());
            }
        }
    }

    /**
     * Server side rules do not work for uploaded files, implement serverside rules here if needed.
     *
     * @param array $data  array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *                     or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files): array {
        if (get_config('block_configurable_reports', 'sqlsecurity')) {
            $errors = $this->validation_high_security($data, $files);
        } else {
            $errors = $this->validation_low_security($data, $files);
        }

        if (isset($data['datatablesconfig']) && !datatables_config::is_valid($data['datatablesconfig'])) {
            $errors['datatablesconfig'] = get_string('datatablesconfiginvalid', 'block_configurable_reports');
        }

        return $errors;
    }

    /**
     * Load the form data. Show DataTables options from the 2019 fork as JSON, when they can be converted.
     *
     * @param stdClass|array $defaultvalues
     * @return void
     */
    public function set_data($defaultvalues): void {
        $defaultvalues = clone (object) $defaultvalues;
        $config = $defaultvalues->datatablesconfig ?? '';
        $defaultvalues->datatablesconfig = datatables_config::to_json($config) ?? $config;

        parent::set_data($defaultvalues);
    }

    /**
     * Get the submitted data. Store the DataTables options as JSON, also when they were typed as an object literal.
     *
     * @return stdClass|null
     */
    public function get_data() {
        $data = parent::get_data();
        if ($data === null) {
            return null;
        }
        // The repository picker only fills the form, it is not part of the report.
        unset($data->repositoryreport);
        if (isset($data->datatablesconfig)) {
            $data->datatablesconfig = datatables_config::to_json($data->datatablesconfig) ?? $data->datatablesconfig;
        }

        return $data;
    }

    /**
     * validation_high_security
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation_high_security($data, $files): array {
        $errors = parent::validation($data, $files);

        $sql = $data['querysql'];
        $sql = trim($sql);

        // Simple test to avoid evil stuff in the SQL.
        if ($error = report_sql::get_query_security_error($sql)) {
            $errors['querysql'] = get_string($error, 'block_configurable_reports');

        } else if (report_sql::uses_explicit_prefix($sql)) {
            // Make sure prefix is prefix_, not explicit.
            $errors['querysql'] = get_string('noexplicitprefix', 'block_configurable_reports');

        } else {
            // Now try running the SQL, and ensure it runs without errors.

            [$sql, $params] = $this->_customdata['reportclass']->prepare_sql($sql);
            $rs = null;
            try {
                $rs = $this->_customdata['reportclass']->execute_query($sql, $params);
            } catch (dml_read_exception $e) {
                $errors['querysql'] = get_string('queryfailed', 'block_configurable_reports', $e->error);
            }
            if ($rs && !empty($data['singlerow'])) {
                if (iterator_count($rs) === 0) {
                    $errors['querysql'] = get_string('norowsreturned', 'block_configurable_reports');
                }
            }

            if ($rs) {
                $rs->close();
            }
        }

        return $errors;
    }

    /**
     * validation_low_security
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation_low_security($data, $files) {
        global $CFG;

        $errors = parent::validation($data, $files);

        $sql = $data['querysql'];
        $sql = trim($sql);

        if ($error = report_sql::get_query_security_error($sql)) {
            $errors['querysql'] = get_string($error, 'block_configurable_reports');
        } else {
            // Now try running the SQL, and ensure it runs without errors.
            [$sql, $params] = $this->_customdata['reportclass']->prepare_sql($sql);
            $rs = null;
            try {
                $rs = $this->_customdata['reportclass']->execute_query($sql, $params);
            } catch (dml_exception $e) {
                $errors['querysql'] = get_string('queryfailed', 'block_configurable_reports', $e->error ?? $e->getMessage());
            }
            if ($rs && !empty($data['singlerow'])) {
                if (iterator_count($rs) === 0) {
                    $errors['querysql'] = get_string('norowsreturned', 'block_configurable_reports');
                }
            }

            if ($rs) {
                $rs->close();
            }
        }

        return $errors;
    }

}
