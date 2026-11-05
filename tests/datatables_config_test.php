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
 * Unit tests for the per-report DataTables options
 *
 * @package    block_configurable_reports
 * @copyright  2026 Likeminded Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_configurable_reports;

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

// Totara has no advanced_testcase. A class whose parent is not known yet is declared when this line has run.
if (!class_exists('advanced_testcase')) {
    class_alias(\core_phpunit\testcase::class, 'advanced_testcase');
}

/**
 * Tests for datatables_config
 *
 * @package    block_configurable_reports
 * @covers     \block_configurable_reports\datatables_config
 */
#[CoversClass(datatables_config::class)]
final class datatables_config_test extends advanced_testcase {

    /** @var string The default options of the 2019 fork, as its SQL form stored them. */
    const LEGACY_DEFAULT = "{
    'bAutoWidth': false,
    'sPaginationType': 'full_numbers',
    'fixedHeader': true,
    'lengthMenu': [ [-1, 100, 250, 500], ['All', 100, 250, 500] ],
    'oLanguage': {
        'oAria': {
            'sSortAscending': M.str.block_configurable_reports.datatables_sortascending,
            'sSortDescending': M.str.block_configurable_reports.datatables_sortdescending,
        },
        'sSearch': M.str.block_configurable_reports.datatables_search,
        'sZeroRecords': M.str.block_configurable_reports.datatables_zerorecords
    }
}";

    /**
     * A JSON object is used as it is, and empty text means the defaults.
     */
    public function test_json_options_are_used(): void {
        $this->assertEquals((object) ['paging' => false, 'rowGroup' => (object) ['dataSrc' => 3]],
            datatables_config::decode('{"paging": false, "rowGroup": {"dataSrc": 3}}'));
        $this->assertNull(datatables_config::decode(null));
        $this->assertNull(datatables_config::decode("  \n"));
        $this->assertTrue(datatables_config::is_valid(''));
        $this->assertTrue(datatables_config::is_valid('{"paging": false}'));
    }

    /**
     * The 2019 default converts to JSON without the language options, which now come from the defaults.
     */
    public function test_the_legacy_default_converts(): void {
        $expected = (object) [
            'bAutoWidth' => false,
            'sPaginationType' => 'full_numbers',
            'fixedHeader' => true,
            'lengthMenu' => [[-1, 100, 250, 500], ['All', 100, 250, 500]],
        ];

        $this->assertTrue(datatables_config::is_valid(self::LEGACY_DEFAULT));
        $this->assertEquals($expected, json_decode(datatables_config::legacy_to_json(self::LEGACY_DEFAULT)));
        $this->assertEquals($expected, datatables_config::decode(self::LEGACY_DEFAULT));
    }

    /**
     * Unquoted keys, escaped quotes and trailing commas from an edited legacy literal convert too.
     */
    public function test_an_edited_legacy_literal_converts(): void {
        $legacy = "{ rowGroup: { dataSrc: 3 }, buttons: ['colvis', 'print',], s: 'it\\'s \"q\"', \"paging\": false, }";

        $this->assertEquals(
            (object) ['rowGroup' => (object) ['dataSrc' => 3], 'buttons' => ['colvis', 'print'], 's' => 'it\'s "q"',
                'paging' => false],
            datatables_config::decode($legacy)
        );
    }

    /**
     * A user can type trailing commas. The form accepts them and stores JSON.
     */
    public function test_trailing_commas_are_accepted_and_stored_as_json(): void {
        $typed = '{
  "order": [[2, "asc"], [0, "asc"],],
  "rowGroup": { "dataSrc": [2, 0], },
  "columnDefs": [{ "targets": [0], "visible": false, },],
}';
        $expected = (object) [
            'order' => [[2, 'asc'], [0, 'asc']],
            'rowGroup' => (object) ['dataSrc' => [2, 0]],
            'columnDefs' => [(object) ['targets' => [0], 'visible' => false]],
        ];

        $this->assertTrue(datatables_config::is_valid($typed));
        $this->assertEquals($expected, json_decode(datatables_config::to_json($typed)));
    }

    /**
     * JSON is stored as the user typed it, and empty text stays empty.
     */
    public function test_json_is_stored_as_typed(): void {
        $this->assertSame('{"paging": false}', datatables_config::to_json('{"paging": false}'));
        $this->assertSame('', datatables_config::to_json("  \n"));
        $this->assertNull(datatables_config::to_json('{ x: function() {} }'));
    }

    /**
     * Code is never converted, so nothing that the view page uses can run as JavaScript.
     *
     * @dataProvider not_options_provider
     * @param string $config
     */
    #[DataProvider('not_options_provider')]
    public function test_code_and_other_values_are_refused(string $config): void {
        $this->assertFalse(datatables_config::is_valid($config));
        $this->assertNull(datatables_config::decode($config));
    }

    /**
     * Configurations that are not options.
     *
     * @return array
     */
    public static function not_options_provider(): array {
        return [
            'function' => ['{ initComplete: function() { alert(1); } }'],
            'variable outside the language options' => ['{ pageLength: M.cfg.pagelength }'],
            'JSON array' => ['[1, 2]'],
            'JSON string' => ['"paging"'],
            'unclosed object' => ["{ 'paging': "],
            'unclosed string' => ["{ 'paging: false }"],
        ];
    }

    /**
     * The bulk action ids leave the options, so that DataTables does not get them.
     */
    public function test_take_actions_removes_the_ids(): void {
        $options = datatables_config::decode('{"actions": ["local_reportactions/unenrol", 5], "paging": false}');

        $this->assertSame(['local_reportactions/unenrol'], datatables_config::take_actions($options));
        $this->assertEquals((object) ['paging' => false], $options);
        $this->assertSame([], datatables_config::take_actions($options));
        $this->assertSame([], datatables_config::take_actions((object) ['actions' => 'local_reportactions/unenrol']));
    }
}
