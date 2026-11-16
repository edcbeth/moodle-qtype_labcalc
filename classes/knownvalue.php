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
 * Comparing a student's finding with a known (accepted or theoretical) value.
 *
 * @package    qtype_labcalc
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class knownvalue {

    /** @var string Suffix for a calculated value's known value, usable in later formulas as {name_known}. */
    const SUFFIX = '_known';

    /** @var int No comparison. */
    const MODE_OFF = 0;

    /** @var int The comparison is shown in the feedback but does not affect the mark. */
    const MODE_FEEDBACK = 1;

    /** @var int Part of the value's mark is given for accuracy. */
    const MODE_GRADED = 2;

    /** @var int Compare the value the student reported. */
    const COMPARE_REPORTED = 0;

    /** @var int Compare the value correctly calculated from the student's measurements. */
    const COMPARE_FROMDATA = 1;

    /**
     * Compare a finding with a known value.
     *
     * @param float $finding the student's value (or the value calculated from their data).
     * @param float $known the known value.
     * @param \stdClass $res the calculated value's settings (accuracy fields).
     * @return \stdClass with fields:
     *      known, finding, difference (finding - known), percent (|difference| as a % of |known|, or null if known is 0),
     *      status ('accurate', 'partial', 'inaccurate'), score (0..1, the accuracy mark).
     */
    public static function compare(float $finding, float $known, \stdClass $res): \stdClass {
        $result = (object) [
            'known' => $known,
            'finding' => $finding,
            'difference' => $finding - $known,
            'percent' => $known != 0.0 ? 100 * abs($finding - $known) / abs($known) : null,
            'status' => 'inaccurate',
            'score' => 0.0,
        ];
        if (evaluator::within_tolerance($finding, $known, $res->accuracytolerance, $res->accuracytolerancetype)) {
            $result->status = 'accurate';
            $result->score = 1.0;
        } else if ($res->accuracypartialtolerance !== null && evaluator::within_tolerance(
                $finding, $known, $res->accuracypartialtolerance, $res->accuracytolerancetype)) {
            $result->status = 'partial';
            $result->score = $res->accuracypartialfraction;
        }
        return $result;
    }

    /**
     * Combine the calculation mark and the accuracy mark for one calculated value.
     *
     * @param float $calculationscore 0..1
     * @param float $accuracyscore 0..1
     * @param float $accuracyweight share of the mark given for accuracy, 0..1
     * @return float
     */
    public static function combine(float $calculationscore, float $accuracyscore, float $accuracyweight): float {
        $accuracyweight = min(1.0, max(0.0, $accuracyweight));
        return (1 - $accuracyweight) * $calculationscore + $accuracyweight * $accuracyscore;
    }
}
