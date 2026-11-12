<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.


namespace qtype_labcalc;

/**
 * Parses and checks the per-group reference data an instructor records for a question.
 *
 * The data is a small table, one row per group, pasted into the question form, e.g.
 *
 *     group, mass, volume
 *     Group A, 26.4, 9.8
 *     Group B, 27.1, 10.0
 *
 * Columns may be separated by commas, semicolons or tabs (so it can be pasted from a spreadsheet).
 * The first column is the group name (or group ID number) exactly as in the Moodle course; the other
 * column headings are experimental value names. Blank cells mean "do not check".
 *
 * @package    qtype_labcalc
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class groupdata {

    /** @var int Group data is not checked. */
    const MODE_OFF = 0;

    /** @var int Mismatches are reported in the feedback only. */
    const MODE_FEEDBACK = 1;

    /** @var int Mismatches are reported and the mark is reduced. */
    const MODE_PENALTY = 2;

    /**
     * Normalise a group name / key for matching.
     *
     * @param string $name
     * @return string
     */
    public static function normalise_key(string $name): string {
        return \core_text::strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
    }

    /**
     * Parse the group data table.
     *
     * @param string $text the table as typed or pasted.
     * @param string[] $varnames the experimental value names defined for the question.
     * @return array [rows, errors] where rows is normalised key => ['label' => original name,
     *      'values' => [varname => float]], and errors is a list of problem descriptions.
     */
    public static function parse(string $text, array $varnames): array {
        $rows = [];
        $errors = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($text));
        $lines = array_values(array_filter($lines, fn($l) => trim($l) !== ''));
        if (!$lines) {
            return [[], []];
        }

        $delimiter = self::detect_delimiter($lines[0]);
        $header = array_map('trim', str_getcsv($lines[0], $delimiter, '"', '\\'));
        $columns = [];
        foreach (array_slice($header, 1) as $i => $name) {
            $name = \core_text::strtolower($name);
            if ($name === '') {
                continue;
            }
            if (!in_array($name, $varnames)) {
                $errors[] = get_string('groupdata_unknowncolumn', 'qtype_labcalc', $name);
                continue;
            }
            $columns[$i + 1] = $name;
        }
        if (!$columns && !$errors) {
            $errors[] = get_string('groupdata_nocolumns', 'qtype_labcalc');
        }

        foreach (array_slice($lines, 1) as $lineno => $line) {
            $cells = array_map('trim', str_getcsv($line, $delimiter, '"', '\\'));
            $label = $cells[0] ?? '';
            $key = self::normalise_key($label);
            $a = (object) ['line' => $lineno + 2, 'group' => $label];
            if ($key === '') {
                $errors[] = get_string('groupdata_nogroup', 'qtype_labcalc', $a);
                continue;
            }
            if (isset($rows[$key])) {
                $errors[] = get_string('groupdata_duplicate', 'qtype_labcalc', $a);
                continue;
            }
            $values = [];
            foreach ($columns as $index => $varname) {
                $cell = $cells[$index] ?? '';
                if ($cell === '') {
                    continue;
                }
                if ($delimiter !== ',') {
                    $cell = str_replace(',', '.', $cell);
                }
                $cell = str_replace(' ', '', $cell);
                if (!is_numeric($cell)) {
                    $a->value = $cells[$index];
                    $errors[] = get_string('groupdata_notnumber', 'qtype_labcalc', $a);
                    continue;
                }
                $values[$varname] = (float) $cell;
            }
            $rows[$key] = ['label' => $label, 'values' => $values];
        }
        return [$rows, $errors];
    }

    /**
     * Guess the column separator from the header line.
     *
     * @param string $line
     * @return string
     */
    protected static function detect_delimiter(string $line): string {
        foreach (["\t", ';', ','] as $delimiter) {
            if (strpos($line, $delimiter) !== false) {
                return $delimiter;
            }
        }
        return ',';
    }

    /**
     * Parse a data-check tolerance such as "2%" (relative) or "0.5" (absolute).
     *
     * @param string $tolerance
     * @return array|null|false [value, evaluator::TOLERANCE_*], null if blank (exact match), false if invalid.
     */
    public static function parse_tolerance(string $tolerance) {
        $tolerance = trim($tolerance);
        if ($tolerance === '') {
            return null;
        }
        $type = evaluator::TOLERANCE_ABSOLUTE;
        if (substr($tolerance, -1) === '%') {
            $type = evaluator::TOLERANCE_RELATIVE;
            $tolerance = trim(substr($tolerance, 0, -1));
        }
        $tolerance = str_replace(',', '.', $tolerance);
        if (!is_numeric($tolerance) || (float) $tolerance < 0) {
            return false;
        }
        return [(float) $tolerance, $type];
    }

    /**
     * Find which row of the group data applies to a user, based on their groups in a course.
     *
     * @param array $rows parsed rows from {@see parse()}.
     * @param int $userid
     * @param int $courseid
     * @param int $groupingid restrict to groups in this grouping (0 = all groups).
     * @return string|null the matching row key, or null if none.
     */
    public static function find_row_for_user(array $rows, int $userid, int $courseid, int $groupingid = 0): ?string {
        global $CFG;
        require_once($CFG->libdir . '/grouplib.php');
        if (!$rows || !$userid || !$courseid || $courseid == SITEID) {
            return null;
        }
        $groups = groups_get_all_groups($courseid, $userid, $groupingid, 'g.id, g.name, g.idnumber');
        // Match on name first, then on ID number; sort for a stable choice if several match.
        $matches = [];
        foreach ($groups as $group) {
            foreach ([$group->name, $group->idnumber] as $candidate) {
                $key = self::normalise_key((string) $candidate);
                if ($key !== '' && isset($rows[$key])) {
                    $matches[] = $key;
                    break;
                }
            }
        }
        if (!$matches) {
            return null;
        }
        sort($matches);
        return $matches[0];
    }
}
