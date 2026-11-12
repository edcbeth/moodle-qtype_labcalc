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
 * Counting significant figures in numbers as typed by students.
 *
 * Trailing zeros in a whole number without a decimal point (e.g. "2700") are ambiguous: they may
 * or may not be significant. Rather than guess, {@see count()} returns the range of possible
 * counts, and an answer is accepted if the required count falls within that range.
 *
 * @package    qtype_labcalc
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sigfigs {

    /** @var int Significant figures are not checked. */
    const MODE_OFF = 0;

    /** @var int A fixed number of significant figures is required. */
    const MODE_FIXED = 1;

    /** @var int Required = the fewest significant figures among the measurements used. */
    const MODE_AUTO = 2;

    /**
     * Count the significant figures in a number written as a string.
     *
     * The string should already be normalised: '.' as the decimal point, and powers of ten written
     * with 'e' (see {@see \qtype_labcalc_question::normalise_number()}).
     *
     * @param string $number e.g. "0.0250", "2.70e3", "2700", "-12.0"
     * @return int[]|null [min, max] possible count, or null if this is not a plain number.
     */
    public static function count(string $number): ?array {
        $number = trim($number);
        if (!preg_match('/^[+-]?(\d*)(\.?)(\d*)(?:[eE][+-]?\d+)?$/', $number, $m)) {
            return null;
        }
        [, $intpart, $point, $fracpart] = $m;
        if ($intpart === '' && $fracpart === '') {
            return null;
        }
        $digits = $intpart . $fracpart;

        // Zero is a special case: "0" has 1 significant figure, "0.0" has 2 and "0.00" has 3 by
        // convention (count the zeros written after the decimal point, plus one).
        if (trim($digits, '0') === '') {
            $n = $point === '.' ? strlen($fracpart) + 1 : 1;
            return [$n, $n];
        }

        // Leading zeros are never significant.
        $significant = ltrim($digits, '0');

        if ($point === '.') {
            // With a decimal point, every digit after the first non-zero one is significant.
            $n = strlen($significant);
            return [$n, $n];
        }

        // Whole number with no decimal point: trailing zeros are ambiguous.
        $min = strlen(rtrim($significant, '0'));
        return [$min, strlen($significant)];
    }

    /**
     * Does a number written with the given sig-fig range satisfy the requirement?
     *
     * @param int[] $range [min, max] from {@see count()}.
     * @param int $required required number of significant figures.
     * @param int $slack how many more or fewer are tolerated.
     * @return bool
     */
    public static function satisfies(array $range, int $required, int $slack = 0): bool {
        [$min, $max] = $range;
        return $max >= $required - $slack && $min <= $required + $slack;
    }

    /**
     * Describe a sig-fig range for feedback, e.g. "3" or "2–4".
     *
     * @param int[] $range
     * @return string
     */
    public static function describe(array $range): string {
        return $range[0] == $range[1] ? (string) $range[0] : $range[0] . '–' . $range[1];
    }
}
