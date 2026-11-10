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

if ($ADMIN->fulltree) {
    // Totara 20 renamed the admin setting classes. Its old names still work, but write a debugging notice.
    $settingclass = function(string $moodleclass, string $totaraclass): string {
        return class_exists($totaraclass) ? $totaraclass : $moodleclass;
    };
    $textsetting = $settingclass('admin_setting_configtext', 'core\setting\type\text');
    $passwordsetting = $settingclass('admin_setting_configpasswordunmask', 'core\setting\type\password_unmask');
    $timesetting = $settingclass('admin_setting_configtime', 'core\setting\type\time');
    $checkboxsetting = $settingclass('admin_setting_configcheckbox', 'core\setting\type\checkbox');
    $selectsetting = $settingclass('admin_setting_configselect', 'core\setting\type\select');

    $settings->add(
        new $textsetting(
            'block_configurable_reports/dbhost', get_string('dbhost', 'block_configurable_reports'),
            get_string('dbhostinfo', 'block_configurable_reports'), '', PARAM_URL, 30
        )
    );
    $settings->add(
        new $textsetting(
            'block_configurable_reports/dbname', get_string('dbname', 'block_configurable_reports'),
            get_string('dbnameinfo', 'block_configurable_reports'), '', PARAM_RAW, 30
        )
    );
    $settings->add(
        new $passwordsetting(
            'block_configurable_reports/dbuser', get_string('dbuser', 'block_configurable_reports'),
            get_string('dbuserinfo', 'block_configurable_reports'), '', PARAM_RAW, 30
        )
    );
    $settings->add(
        new $passwordsetting(
            'block_configurable_reports/dbpass', get_string('dbpass', 'block_configurable_reports'),
            get_string('dbpassinfo', 'block_configurable_reports'), '', PARAM_RAW, 30
        )
    );

    $settings->add(
        new $timesetting(
            'block_configurable_reports/cron_hour',
            'cron_minute',
            get_string('executeat', 'block_configurable_reports'),
            get_string('executeatinfo', 'block_configurable_reports'),
            ['h' => 0, 'm' => 0]
        )
    );

    $settings->add(
        new $checkboxsetting(
            'block_configurable_reports/sqlsecurity', get_string('sqlsecurity', 'block_configurable_reports'),
            get_string('sqlsecurityinfo', 'block_configurable_reports'), 1
        )
    );

    $settings->add(
        new $textsetting(
            'block_configurable_reports/' . \block_configurable_reports\report_repository::SETTING,
            get_string('reportrepository', 'block_configurable_reports'),
            get_string('reportrepositoryinfo', 'block_configurable_reports'),
            'likeminded-learning/totara-configurable-reports@main',
            PARAM_TEXT,
            60
        )
    );

    $settings->add(
        new $passwordsetting(
            'block_configurable_reports/' . \block_configurable_reports\report_repository::TOKEN_SETTING,
            get_string('reportrepositorytoken', 'block_configurable_reports'),
            get_string('reportrepositorytokeninfo', 'block_configurable_reports'),
            ''
        )
    );

    $settings->add(
        new $checkboxsetting(
            'block_configurable_reports/sqlsyntaxhighlight', get_string('sqlsyntaxhighlight', 'block_configurable_reports'),
            get_string('sqlsyntaxhighlightinfo', 'block_configurable_reports'), 1
        )
    );

    $reporttableoptions = [
        'html' => get_string('reporttableui_html', 'block_configurable_reports'),
        'jquery' => get_string('reporttableui_jquery', 'block_configurable_reports'),
        'datatables' => get_string('reporttableui_datatables', 'block_configurable_reports'),
    ];
    $settings->add(
        new $selectsetting(
            'block_configurable_reports/reporttableui', get_string('reporttableui', 'block_configurable_reports'),
            get_string('reporttableuiinfo', 'block_configurable_reports'), 'datatables', $reporttableoptions
        )
    );

    $settings->add(
        new $textsetting(
            'block_configurable_reports/reportlimit', get_string('reportlimit', 'block_configurable_reports'),
            get_string('reportlimitinfo', 'block_configurable_reports'), '5000', PARAM_INT, 6
        )
    );

    // CSV delimiters used in get_delimiter() of moodle lib/csvlib.class.php.
    $csvdelimiteroptions = [];
    foreach (['cfg', 'colon', 'comma', 'semicolon', 'tab'] as $delimiter) {
        $csvdelimiteroptions[$delimiter] = get_string('csvdelimiter_' . $delimiter, 'block_configurable_reports');
    }
    $settings->add(
        new $selectsetting(
            'block_configurable_reports/csvdelimiter', get_string('csvdelimiter', 'block_configurable_reports'),
            get_string('csvdelimiterinfo', 'block_configurable_reports'), 'cfg', $csvdelimiteroptions
        )
    );
    $settings->add(
        new $textsetting(
            'block_configurable_reports/allowedsqlusers', get_string('allowedsqlusers', 'block_configurable_reports'),
            get_string('allowedsqlusersinfo', 'block_configurable_reports'), '', PARAM_TEXT
        )
    );
}
