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
 * Shared SQL reports in a GitHub repository.
 *
 * @package    block_configurable_reports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_configurable_reports;

use moodle_exception;
use stdClass;

/**
 * Shared SQL reports in a GitHub repository.
 *
 * Each report is a folder in a category folder: <category>/<report>/. The folder holds report.json (the report
 * settings), query.sql (MySQL), query.pgsql.sql (PostgreSQL) and optionally datatables.json (the DataTables options).
 * A report is available on a site only when it has the SQL file for the site's database.
 *
 * @package    block_configurable_reports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_repository {

    /** @var string The setting that names the repository: owner/repo@ref. */
    const SETTING = 'reportrepository';

    /** @var string The setting that holds the GitHub token. */
    const TOKEN_SETTING = 'reportrepositorytoken';

    /** @var string The file of the report settings. */
    const REPORT_FILE = 'report.json';

    /** @var string The file of the DataTables options. */
    const DATATABLES_FILE = 'datatables.json';

    /** @var string[] The SQL file of each database family, as moodle_database::get_dbfamily() names it. */
    const SQL_FILES = ['mysql' => 'query.sql', 'postgres' => 'query.pgsql.sql'];

    /** @var string The part of a report that holds its SQL. */
    const PART_SQL = 'sql';

    /** @var string The part of a report that holds its DataTables options. */
    const PART_DATATABLES = 'datatables';

    /** @var string owner/repo, then an optional @ref. */
    const SOURCE_PATTERN = '~^([A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+)(?:@([A-Za-z0-9_./-]+))?$~';

    /** @var string A path segment: a category or a report folder. */
    const SEGMENT_PATTERN = '~^[A-Za-z0-9_][A-Za-z0-9_.-]*$~';

    /** @var string The only report type that the repository holds. */
    const REPORT_TYPE = 'sql';

    /** @var github */
    private github $github;

    /** @var string|null The branch, tag or commit. Null for the default branch. */
    private ?string $ref;

    /**
     * Constructor.
     *
     * @param string $source The repository: owner/repo@ref.
     * @param string $token A GitHub token. Empty for a public repository.
     * @param github|null $github The client. Tests give their own.
     */
    public function __construct(string $source, string $token = '', ?github $github = null) {
        [$repo, $this->ref] = self::parse_source($source);
        $this->github = $github ?? new github();
        $this->github->set_repo($repo);
        if ($token !== '') {
            $this->github->set_token($token);
        }
    }

    /**
     * Get the repository of the plugin settings.
     *
     * @return self|null Null when no repository is set.
     */
    public static function from_config(): ?self {
        $source = trim((string) get_config('block_configurable_reports', self::SETTING));
        if ($source === '') {
            return null;
        }

        return new self($source, trim((string) get_config('block_configurable_reports', self::TOKEN_SETTING)));
    }

    /**
     * Split the repository setting.
     *
     * @param string $source owner/repo, with an optional @ref.
     * @return array [owner/repo, ref or null]
     * @throws moodle_exception When the setting is not owner/repo@ref.
     */
    public static function parse_source(string $source): array {
        if (!preg_match(self::SOURCE_PATTERN, trim($source), $matches)) {
            throw new moodle_exception('errorrepositorysetting', 'block_configurable_reports', '', s($source));
        }

        return [$matches[1], $matches[2] ?? null];
    }

    /**
     * Get the SQL file of a database family.
     *
     * @param string $dbfamily As moodle_database::get_dbfamily() returns it.
     * @return string|null Null when the repository has no SQL for the family.
     */
    public static function sql_file(string $dbfamily): ?string {
        return self::SQL_FILES[$dbfamily] ?? null;
    }

    /**
     * Check a report path: <category>/<report>.
     *
     * @param string $path
     * @return bool
     */
    public static function is_report_path(string $path): bool {
        $segments = explode('/', $path);
        if (count($segments) !== 2) {
            return false;
        }
        foreach ($segments as $segment) {
            if (!preg_match(self::SEGMENT_PATTERN, $segment)) {
                return false;
            }
        }

        return true;
    }

    /**
     * List the reports of the repository.
     *
     * @param string $dbfamily The database family of the site.
     * @return array [category => [report path => available]], sorted by name.
     */
    public function list_reports(string $dbfamily): array {
        return self::reports_from_tree($this->github->get_tree($this->ref), $dbfamily);
    }

    /**
     * List the reports in the entries of a repository tree.
     *
     * @param \stdClass[] $tree The tree entries, each with a path and a type.
     * @param string $dbfamily The database family of the site.
     * @return array [category => [report path => available]], sorted by name.
     */
    public static function reports_from_tree(array $tree, string $dbfamily): array {
        $files = [];
        foreach ($tree as $entry) {
            if (($entry->type ?? '') === 'blob') {
                $files[$entry->path] = true;
            }
        }

        $sqlfile = self::sql_file($dbfamily);
        $reports = [];
        foreach (array_keys($files) as $file) {
            $segments = explode('/', $file);
            if (count($segments) !== 3 || $segments[2] !== self::REPORT_FILE) {
                continue;
            }
            $path = $segments[0] . '/' . $segments[1];
            if (!self::is_report_path($path)) {
                continue;
            }
            $reports[$segments[0]][$path] = $sqlfile !== null && isset($files[$path . '/' . $sqlfile]);
        }

        ksort($reports);
        foreach ($reports as &$categoryreports) {
            ksort($categoryreports);
        }

        return $reports;
    }

    /**
     * Add the reports of the repository to a select element.
     *
     * Reports without SQL for the database of the site are listed, but cannot be chosen.
     *
     * @param \MoodleQuickForm_select $select
     * @param string $dbfamily The database family of the site.
     * @return void
     */
    public function add_options(\MoodleQuickForm_select $select, string $dbfamily): void {
        $select->addOption(get_string('choose'), '');
        foreach ($this->list_reports($dbfamily) as $reports) {
            foreach ($reports as $path => $available) {
                if ($available) {
                    $select->addOption($path, $path);
                } else {
                    $select->addOption(get_string('notavailablefordatabase', 'block_configurable_reports', $path), $path,
                        ['disabled' => 'disabled']);
                }
            }
        }
    }

    /**
     * Get one part of a report.
     *
     * @param string $path The report path: <category>/<report>.
     * @param string $part self::PART_SQL or self::PART_DATATABLES.
     * @param string $dbfamily The database family of the site.
     * @return string|null The SQL, or the DataTables options as JSON. Null when the report does not have the part.
     */
    public function get_part(string $path, string $part, string $dbfamily): ?string {
        if (!self::is_report_path($path)) {
            throw new moodle_exception('errorrepositoryreport', 'block_configurable_reports', '', s($path));
        }

        if ($part === self::PART_SQL) {
            $file = self::sql_file($dbfamily);

            return $file === null ? null : $this->github->get_contents($path . '/' . $file, $this->ref);
        }
        if ($part === self::PART_DATATABLES) {
            $config = $this->github->get_contents($path . '/' . self::DATATABLES_FILE, $this->ref);
            if ($config === null) {
                return null;
            }
            $json = datatables_config::to_json($config);
            if ($json === null) {
                throw new moodle_exception('errorrepositoryreport', 'block_configurable_reports', '',
                    s($path . '/' . self::DATATABLES_FILE));
            }

            return $json;
        }

        throw new \coding_exception('Unknown report part: ' . $part);
    }

    /**
     * Get all the parts of a report.
     *
     * @param string $path The report path: <category>/<report>.
     * @param string $dbfamily The database family of the site.
     * @return stdClass With settings (the decoded report.json), sql, and datatables (null when there are none).
     * @throws moodle_exception When the report does not exist or has no SQL for the database.
     */
    public function get_report(string $path, string $dbfamily): stdClass {
        if (!self::is_report_path($path)) {
            throw new moodle_exception('errorrepositoryreport', 'block_configurable_reports', '', s($path));
        }
        $settings = json_decode((string) $this->github->get_contents($path . '/' . self::REPORT_FILE, $this->ref));
        if (!$settings instanceof stdClass) {
            throw new moodle_exception('errorrepositoryreport', 'block_configurable_reports', '',
                s($path . '/' . self::REPORT_FILE));
        }
        $sql = $this->get_part($path, self::PART_SQL, $dbfamily);
        if ($sql === null || trim($sql) === '') {
            throw new moodle_exception('repositoryreportnotavailable', 'block_configurable_reports', '', s($path));
        }

        return (object) [
            'settings' => $settings,
            'sql' => $sql,
            'datatables' => $this->get_part($path, self::PART_DATATABLES, $dbfamily),
        ];
    }

    /**
     * Build a new report from the parts of a repository report.
     *
     * @param stdClass $entry As get_report() returns it.
     * @param int $courseid The course of the report.
     * @param int $ownerid The owner of the report.
     * @return stdClass The report record, without an id. Permissions are not set: they depend on the site.
     * @throws moodle_exception When report.json is not valid.
     */
    public static function build_report(stdClass $entry, int $courseid, int $ownerid): stdClass {
        $settings = $entry->settings;
        $name = clean_param((string) ($settings->name ?? ''), PARAM_TEXT);
        if (trim($name) === '' || ($settings->type ?? '') !== self::REPORT_TYPE) {
            throw new moodle_exception('errorrepositoryreport', 'block_configurable_reports', '', self::REPORT_FILE);
        }

        $exportformats = array_intersect((array) ($settings->export ?? []), array_keys(cr_get_export_plugins()));

        $report = (object) [
            'courseid' => $courseid,
            'ownerid' => $ownerid,
            'visible' => 1,
            'name' => $name,
            'summary' => (string) ($settings->summary ?? ''),
            'summaryformat' => FORMAT_HTML,
            'type' => self::REPORT_TYPE,
            'global' => empty($settings->global) ? 0 : 1,
            'jsordering' => empty($settings->jsordering) ? 0 : 1,
            'pagination' => 0,
            'export' => $exportformats ? implode(',', $exportformats) . ',' : '',
            'components' => '',
        ];

        $components = [
            'customsql' => [
                'config' => (object) [
                    'querysql' => $entry->sql,
                    'courseid' => $courseid,
                    'datatablesconfig' => $entry->datatables ?? '',
                ],
            ],
        ];

        $ids = [];
        foreach ((array) ($settings->filters ?? []) as $filter) {
            $pluginname = (string) ($filter->pluginname ?? '');
            $formdata = ($filter->formdata ?? null) instanceof stdClass ? $filter->formdata : new stdClass();
            $classname = cr_require_component_plugin('filters', $pluginname);
            $plugin = new $classname($report);

            do {
                $id = random_string(15);
            } while (isset($ids[$id]));
            $ids[$id] = true;

            $components['filters']['elements'][] = [
                'id' => $id,
                'formdata' => $formdata,
                'pluginname' => $pluginname,
                'pluginfullname' => $plugin->fullname,
                'summary' => $plugin->summary($formdata),
            ];
        }

        $report->components = cr_serialize(cr_clean_imported_components($components));

        return $report;
    }

    /**
     * Get the repository files of a report, to add it to the repository.
     *
     * @param stdClass $report The report record.
     * @param string $dbfamily The database family of the site, which the SQL is written for.
     * @return string[] [file name => contents]
     * @throws moodle_exception When the report is not an SQL report, or the database has no SQL file.
     */
    public static function export_files(stdClass $report, string $dbfamily): array {
        $sqlfile = self::sql_file($dbfamily);
        if ($report->type !== self::REPORT_TYPE || $sqlfile === null) {
            throw new moodle_exception('errorrepositoryexport', 'block_configurable_reports');
        }
        $components = cr_unserialize($report->components);
        $config = $components['customsql']['config'] ?? new stdClass();

        $filters = [];
        foreach ($components['filters']['elements'] ?? [] as $element) {
            $filters[] = ['pluginname' => $element['pluginname'], 'formdata' => (object) $element['formdata']];
        }

        $settings = [
            'name' => $report->name,
            'summary' => $report->summary,
            'type' => self::REPORT_TYPE,
            'global' => (int) $report->global,
            'jsordering' => (int) $report->jsordering,
            'export' => array_values(array_filter(explode(',', (string) $report->export))),
            'filters' => $filters,
        ];

        $files = [
            self::REPORT_FILE => self::encode($settings),
            $sqlfile => rtrim((string) ($config->querysql ?? '')) . "\n",
        ];
        $datatables = datatables_config::decode($config->datatablesconfig ?? null);
        if ($datatables !== null) {
            $files[self::DATATABLES_FILE] = self::encode($datatables);
        }

        return $files;
    }

    /**
     * Encode a repository JSON file.
     *
     * @param mixed $data
     * @return string
     */
    private static function encode($data): string {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}
