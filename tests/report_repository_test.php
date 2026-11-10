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
 * Unit tests for the report repository
 *
 * @package    block_configurable_reports
 * @copyright  2026 Likeminded Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_configurable_reports;

use advanced_testcase;
use moodle_exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/configurable_reports/locallib.php');

// Totara has no advanced_testcase. A class whose parent is not known yet is declared when this line has run.
if (!class_exists('advanced_testcase')) {
    class_alias(\core_phpunit\testcase::class, 'advanced_testcase');
}

/**
 * A GitHub client that reads a folder instead of a repository.
 */
class fixture_github extends github {

    /** @var string The folder that stands in for the repository. */
    private string $root;

    /** @var array The refs that were asked for. */
    public array $refs = [];

    /**
     * Constructor.
     *
     * @param string $root
     */
    public function __construct(string $root) {
        parent::__construct();
        $this->root = $root;
    }

    #[\Override]
    public function get_tree(?string $ref): array {
        $this->refs[] = $ref;
        $tree = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root,
            \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($files as $file) {
            $tree[] = (object) [
                'path' => substr($file->getPathname(), strlen($this->root) + 1),
                'type' => $file->isDir() ? 'tree' : 'blob',
            ];
        }

        return $tree;
    }

    #[\Override]
    public function get_contents(string $path, ?string $ref): ?string {
        $this->refs[] = $ref;
        $file = $this->root . '/' . $path;

        return is_file($file) ? file_get_contents($file) : null;
    }
}

/**
 * Tests for report_repository
 *
 * @package    block_configurable_reports
 * @covers     \block_configurable_reports\report_repository
 */
#[CoversClass(report_repository::class)]
final class report_repository_test extends advanced_testcase {

    /** @var string The example report of the company repository. */
    const REPORT = 'completion/course-completion-by-user';

    /**
     * Get a repository that reads the fixture folder.
     *
     * @param string $source
     * @return array [report_repository, fixture_github]
     */
    private function repository(string $source = 'likeminded-learning/totara-configurable-reports@main'): array {
        global $CFG;
        $github = new fixture_github($CFG->dirroot . '/blocks/configurable_reports/tests/fixtures/report_repository');

        return [new report_repository($source, '', $github), $github];
    }

    /**
     * The setting is owner/repo with an optional @ref.
     */
    public function test_the_setting_names_the_repository_and_the_ref(): void {
        $this->assertSame(['likeminded-learning/totara-configurable-reports', 'main'],
            report_repository::parse_source('likeminded-learning/totara-configurable-reports@main'));
        $this->assertSame(['owner/repo', 'release/2026.1'], report_repository::parse_source(' owner/repo@release/2026.1 '));
        $this->assertSame(['owner/repo', null], report_repository::parse_source('owner/repo'));
    }

    /**
     * Settings that are not owner/repo@ref are refused.
     *
     * @dataProvider bad_sources_provider
     * @param string $source
     */
    #[DataProvider('bad_sources_provider')]
    public function test_a_bad_setting_is_refused(string $source): void {
        $this->expectException(moodle_exception::class);
        report_repository::parse_source($source);
    }

    /**
     * Settings that are not owner/repo@ref.
     *
     * @return array
     */
    public static function bad_sources_provider(): array {
        return [
            'URL' => ['https://github.com/owner/repo'],
            'owner only' => ['owner'],
            'empty ref' => ['owner/repo@'],
            'path' => ['owner/repo/folder'],
        ];
    }

    /**
     * MySQL and MariaDB sites use query.sql, PostgreSQL sites query.pgsql.sql, and other databases have no SQL.
     */
    public function test_each_database_family_has_its_sql_file(): void {
        $this->assertSame('query.sql', report_repository::sql_file('mysql'));
        $this->assertSame('query.pgsql.sql', report_repository::sql_file('postgres'));
        $this->assertNull(report_repository::sql_file('mssql'));
    }

    /**
     * A report is available only when it has the SQL file of the database. There is no fallback to the other file.
     */
    public function test_reports_are_available_only_with_sql_for_the_database(): void {
        $tree = array_map(fn(string $path) => (object) ['path' => $path, 'type' => 'blob'], [
            'README.md',
            'users/active/report.json',
            'users/active/query.sql',
            'users/active/query.pgsql.sql',
            'users/mysql-only/report.json',
            'users/mysql-only/query.sql',
            'completion/pgsql-only/report.json',
            'completion/pgsql-only/query.pgsql.sql',
            'completion/no-settings/query.sql',
            'too/deep/report/report.json',
        ]);

        $this->assertSame(
            ['completion' => ['completion/pgsql-only' => false], 'users' => ['users/active' => true, 'users/mysql-only' => true]],
            report_repository::reports_from_tree($tree, 'mysql')
        );
        $this->assertSame(
            ['completion' => ['completion/pgsql-only' => true], 'users' => ['users/active' => true, 'users/mysql-only' => false]],
            report_repository::reports_from_tree($tree, 'postgres')
        );
    }

    /**
     * The repository is read at the ref of the setting.
     */
    public function test_the_example_report_is_listed_at_the_ref(): void {
        [$repository, $github] = $this->repository();

        $this->assertSame(['completion' => [self::REPORT => true]], $repository->list_reports('postgres'));
        $this->assertSame(['main'], $github->refs);
    }

    /**
     * Pull SQL gives the SQL of the database, and Pull DataTables gives the options as JSON.
     */
    public function test_one_part_of_a_report_can_be_pulled(): void {
        [$repository] = $this->repository('owner/repo');

        $this->assertStringContainsString("to_char(to_timestamp(cc.timecompleted), 'YYYY-MM-DD')",
            $repository->get_part(self::REPORT, report_repository::PART_SQL, 'postgres'));
        $this->assertStringContainsString("DATE_FORMAT(FROM_UNIXTIME(cc.timecompleted), '%Y-%m-%d')",
            $repository->get_part(self::REPORT, report_repository::PART_SQL, 'mysql'));
        $this->assertNull($repository->get_part(self::REPORT, report_repository::PART_SQL, 'mssql'));
        $this->assertEquals((object) ['dataSrc' => [0, 1]],
            json_decode($repository->get_part(self::REPORT, report_repository::PART_DATATABLES, 'mysql'))->rowGroup);
    }

    /**
     * Paths outside <category>/<report> are refused before the repository is read.
     *
     * @dataProvider bad_paths_provider
     * @param string $path
     */
    #[DataProvider('bad_paths_provider')]
    public function test_a_bad_report_path_is_refused(string $path): void {
        [$repository, $github] = $this->repository();

        try {
            $repository->get_part($path, report_repository::PART_SQL, 'mysql');
            $this->fail('No exception for the path ' . $path);
        } catch (moodle_exception $e) {
            $this->assertSame('errorrepositoryreport', $e->errorcode);
        }
        $this->assertSame([], $github->refs);
    }

    /**
     * Paths that are not <category>/<report>.
     *
     * @return array
     */
    public static function bad_paths_provider(): array {
        return [
            'parent folder' => ['../secret'],
            'dot segment' => ['completion/..'],
            'one segment' => ['completion'],
            'three segments' => ['completion/report/query.sql'],
        ];
    }

    /**
     * Create report gives an SQL report with the settings, SQL, DataTables options and filters, and no permissions.
     */
    public function test_a_report_is_built_from_the_example(): void {
        $this->resetAfterTest();
        [$repository] = $this->repository();

        $report = report_repository::build_report($repository->get_report(self::REPORT, 'postgres'), SITEID, 2);

        $this->assertSame('Course completion by user', $report->name);
        $this->assertSame(['sql', (int) SITEID, 2, 1, 1, 'csv,xls,ods,'],
            [$report->type, $report->courseid, $report->ownerid, $report->global, $report->jsordering, $report->export]);
        $components = cr_unserialize($report->components);
        $this->assertSame(['customsql', 'filters'], array_keys($components));
        $this->assertStringContainsString('to_timestamp', $components['customsql']['config']->querysql);
        $this->assertEquals([[0, 1]], [json_decode($components['customsql']['config']->datatablesconfig)->rowGroup->dataSrc]);
        $this->assertSame('courses', $components['filters']['elements'][0]['pluginname']);
        $this->assertSame(15, strlen($components['filters']['elements'][0]['id']));
        $this->assertArrayNotHasKey('permissions', $components);
    }

    /**
     * A report without the SQL of the database cannot be created.
     */
    public function test_a_report_without_sql_for_the_database_is_not_built(): void {
        [$repository] = $this->repository();

        $this->expectExceptionMessage(get_string('repositoryreportnotavailable', 'block_configurable_reports', self::REPORT));
        $repository->get_report(self::REPORT, 'mssql');
    }

    /**
     * Only SQL reports with a name are built, and unknown export formats are dropped.
     */
    public function test_report_settings_are_checked(): void {
        $entry = (object) ['settings' => (object) ['name' => 'Users', 'type' => 'sql', 'export' => ['csv', 'exe']],
            'sql' => 'SELECT 1', 'datatables' => null];
        $this->assertSame('csv,', report_repository::build_report($entry, SITEID, 2)->export);

        $entry->settings->type = 'courses';
        $this->expectException(moodle_exception::class);
        report_repository::build_report($entry, SITEID, 2);
    }

    /**
     * Export to the repository format gives back the files that the report was built from.
     */
    public function test_a_built_report_exports_to_the_same_files(): void {
        global $CFG;
        $this->resetAfterTest();
        [$repository] = $this->repository();
        $folder = $CFG->dirroot . '/blocks/configurable_reports/tests/fixtures/report_repository/' . self::REPORT;

        $report = report_repository::build_report($repository->get_report(self::REPORT, 'postgres'), SITEID, 2);
        $files = report_repository::export_files($report, 'postgres');

        $this->assertSame(['report.json', 'query.pgsql.sql', 'datatables.json'], array_keys($files));
        $this->assertEquals(json_decode(file_get_contents($folder . '/report.json')), json_decode($files['report.json']));
        $this->assertSame(file_get_contents($folder . '/query.pgsql.sql'), $files['query.pgsql.sql']);
        $this->assertEquals(json_decode(file_get_contents($folder . '/datatables.json')), json_decode($files['datatables.json']));
    }
}
