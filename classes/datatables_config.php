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
 * Per-report DataTables options
 *
 * @copyright  2026 Likeminded Learning
 * @package    block_configurable_reports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_configurable_reports;

use stdClass;

/**
 * Read the DataTables options that a SQL report stores in its configuration.
 *
 * The options are a JSON object. The view page merges them over the plugin's defaults, so they
 * only need the keys that change. Reports from the 2019 fork stored a JavaScript object literal
 * that was eval()'d. legacy_to_json() converts those once, when the report is edited or viewed.
 *
 * @package   block_configurable_reports
 */
class datatables_config {

    /** @var string The key of the language options. The defaults supply them from the language strings. */
    const LANGUAGE_KEY = 'oLanguage';

    /** @var string The key of the bulk action ids. It is not a DataTables option. See hook\report_actions. */
    const ACTIONS_KEY = 'actions';

    /**
     * Remove the bulk action ids from the options, before the options go to DataTables.
     *
     * @param stdClass $options The decoded options. The method removes the actions key.
     * @return string[] The action ids. Values that are not strings are ignored.
     */
    public static function take_actions(stdClass $options): array {
        $ids = $options->{self::ACTIONS_KEY} ?? [];
        unset($options->{self::ACTIONS_KEY});

        return is_array($ids) ? array_values(array_filter($ids, 'is_string')) : [];
    }

    /**
     * Get the options of a report.
     *
     * @param string|null $config The stored configuration: JSON, or a legacy JavaScript object literal.
     * @return stdClass|null The options, or null when there are none or they cannot be read.
     */
    public static function decode(?string $config): ?stdClass {
        if ($config === null || trim($config) === '') {
            return null;
        }

        $options = self::decode_json($config);
        if ($options === null) {
            $json = self::legacy_to_json($config);
            $options = $json === null ? null : self::decode_json($json);
        }

        return $options;
    }

    /**
     * Check a configuration that a user submits.
     *
     * @param string $config
     * @return bool True when it is empty, a JSON object, or an object literal that converts to JSON.
     */
    public static function is_valid(string $config): bool {
        return trim($config) === '' || self::decode($config) !== null;
    }

    /**
     * Get the configuration to store and show in the form.
     *
     * @param string $config
     * @return string|null The JSON object, as it is or converted from an object literal. An empty string
     *     when the configuration is empty. Null when it cannot be converted.
     */
    public static function to_json(string $config): ?string {
        if (trim($config) === '') {
            return '';
        }

        return self::decode_json($config) === null ? self::legacy_to_json($config) : $config;
    }

    /**
     * Convert a legacy JavaScript object literal to JSON.
     *
     * It removes the language options, quotes the keys, changes single quotes to double quotes and
     * removes trailing commas. It refuses anything else that is not JSON, such as functions or
     * references to variables.
     *
     * @param string $config
     * @return string|null Pretty-printed JSON, or null when the literal cannot be converted.
     */
    public static function legacy_to_json(string $config): ?string {
        $tokens = self::tokenise($config);
        if ($tokens === null) {
            return null;
        }

        $tokens = self::remove_language($tokens);
        if ($tokens === null) {
            return null;
        }

        $json = '';
        foreach ($tokens as $i => [$type, $value]) {
            $next = $tokens[$i + 1][1] ?? '';
            if ($type === 'punct' && $value === ',' && ($next === '}' || $next === ']')) {
                continue;
            }
            if ($type === 'word') {
                if ($next === ':') {
                    $value = json_encode($value);
                } else if (!in_array($value, ['true', 'false', 'null'], true)) {
                    return null;
                }
            }
            $json .= $value;
        }

        $options = self::decode_json($json);
        if ($options === null) {
            return null;
        }

        return json_encode($options, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Decode a JSON object.
     *
     * @param string $config
     * @return stdClass|null The object, or null when the text is not a JSON object.
     */
    private static function decode_json(string $config): ?stdClass {
        $options = json_decode($config);

        return $options instanceof stdClass ? $options : null;
    }

    /**
     * Split a JavaScript object literal into tokens. Strings come out as JSON strings.
     *
     * @param string $config
     * @return array|null [type, value] pairs, or null when the literal has an error.
     */
    private static function tokenise(string $config): ?array {
        $tokens = [];
        $length = strlen($config);
        $i = 0;
        while ($i < $length) {
            $char = $config[$i];
            if (ctype_space($char)) {
                $i++;
            } else if ($char === '"' || $char === "'") {
                $value = '';
                for ($i++; $i < $length && $config[$i] !== $char; $i++) {
                    if ($config[$i] === '\\') {
                        $i++;
                        if ($i >= $length) {
                            return null;
                        }
                        $value .= $config[$i] === "'" ? "'" : '\\' . $config[$i];
                    } else {
                        $value .= $config[$i] === '"' ? '\\"' : $config[$i];
                    }
                }
                if ($i >= $length) {
                    return null;
                }
                $decoded = json_decode('"' . $value . '"');
                if ($decoded === null) {
                    return null;
                }
                $tokens[] = ['string', json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)];
                $i++;
            } else if (strpos('{}[]:,', $char) !== false) {
                $tokens[] = ['punct', $char];
                $i++;
            } else if (preg_match('/\G-?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?/', $config, $match, 0, $i)) {
                if (!is_numeric($match[0])) {
                    return null;
                }
                $tokens[] = ['number', json_encode($match[0] + 0)];
                $i += strlen($match[0]);
            } else if (preg_match('/\G[A-Za-z_$][\w$]*(?:\.[A-Za-z_$][\w$]*)*/', $config, $match, 0, $i)) {
                // A dotted name, such as M.str.x, is a token too, so that the language options can be removed.
                $tokens[] = ['word', $match[0]];
                $i += strlen($match[0]);
            } else {
                return null;
            }
        }

        return $tokens;
    }

    /**
     * Remove the top-level language options, with their comma.
     *
     * @param array $tokens
     * @return array|null The tokens without the language options, or null when the braces do not match.
     */
    private static function remove_language(array $tokens): ?array {
        $depth = 0;
        foreach ($tokens as $start => [$type, $value]) {
            if ($type === 'punct' && ($value === '{' || $value === '[')) {
                $depth++;
            } else if ($type === 'punct' && ($value === '}' || $value === ']')) {
                $depth--;
            }

            $key = $type === 'string' ? json_decode($value) : $value;
            if ($depth !== 1 || $key !== self::LANGUAGE_KEY || ($tokens[$start + 1][1] ?? '') !== ':') {
                continue;
            }

            // Find the end of the value, then take a comma after it, if there is one.
            $level = 0;
            for ($end = $start + 2; $end < count($tokens); $end++) {
                $value = $tokens[$end][1];
                if ($tokens[$end][0] === 'punct' && ($value === '{' || $value === '[')) {
                    $level++;
                } else if ($tokens[$end][0] === 'punct' && ($value === '}' || $value === ']')) {
                    $level--;
                }
                if ($level === 0) {
                    break;
                }
            }
            if ($level !== 0) {
                return null;
            }
            if (($tokens[$end + 1][1] ?? '') === ',') {
                $end++;
            }

            array_splice($tokens, $start, $end - $start + 1);

            return $tokens;
        }

        return $tokens;
    }
}
