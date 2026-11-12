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
 * Evaluates instructor formulas such as "{mass} / {volume}" against a set of values.
 *
 * The heavy lifting is done by Moodle's core calc_formula / EvalMath parser (the same
 * one used by the gradebook), so formulas are parsed safely and never passed to eval().
 *
 * @package    qtype_labcalc
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evaluator {

    /** @var int Tolerance is a percentage of the expected value. */
    const TOLERANCE_RELATIVE = 1;

    /** @var int Tolerance is an absolute difference. */
    const TOLERANCE_ABSOLUTE = 2;

    /** @var string Prefix used internally so that only {braced} names act as variables. */
    const INTERNAL_PREFIX = 'qv_';

    /** @var string Pattern for a valid variable name. */
    const NAME_PATTERN = '[a-z][a-z0-9_]*';

    /**
     * Is this a valid variable name? (lower case letter, then letters, digits or _)
     *
     * @param string $name
     * @return bool
     */
    public static function is_valid_name(string $name): bool {
        return (bool) preg_match('/^' . self::NAME_PATTERN . '$/', $name) && strlen($name) <= 40;
    }

    /**
     * Find the {variable} names used in a formula, in order of first appearance.
     *
     * @param string $formula
     * @return string[]
     */
    public static function find_variables(string $formula): array {
        preg_match_all('/\{\s*([A-Za-z][A-Za-z0-9_]*)\s*\}/', $formula, $matches);
        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }

    /**
     * Convert "{mass} / {volume}" to the "=qv_mass / qv_volume" syntax calc_formula understands.
     *
     * @param string $formula
     * @return string
     */
    public static function to_calc_formula(string $formula): string {
        $converted = preg_replace_callback('/\{\s*([A-Za-z][A-Za-z0-9_]*)\s*\}/',
                fn($m) => self::INTERNAL_PREFIX . strtolower($m[1]), trim($formula));
        // Allow ** as a synonym for ^ (power), which many people type out of habit.
        $converted = str_replace('**', '^', $converted);
        return '=' . $converted;
    }

    /**
     * Evaluate a formula.
     *
     * @param string $formula instructor formula using {name} placeholders.
     * @param float[] $values variable name => value.
     * @return float the result.
     * @throws evaluation_exception if the formula is invalid or the result is not a finite number.
     */
    public static function evaluate(string $formula, array $values): float {
        global $CFG;
        require_once($CFG->libdir . '/mathslib.php');

        if (trim($formula) === '') {
            throw new evaluation_exception(get_string('error_formulaempty', 'qtype_labcalc'));
        }
        if (strpos($formula, '=') !== false && !preg_match('/[<>=]=|[<>]/', $formula)) {
            throw new evaluation_exception(get_string('error_formulaequals', 'qtype_labcalc'));
        }
        $params = [];
        foreach ($values as $name => $value) {
            $params[self::INTERNAL_PREFIX . strtolower($name)] = (float) $value;
        }

        $calc = new \calc_formula(self::to_calc_formula($formula), $params);
        $result = $calc->evaluate();
        if ($result === false) {
            $error = (string) $calc->get_error();
            throw new evaluation_exception(str_replace(self::INTERNAL_PREFIX, '', $error));
        }
        $result = (float) $result;
        if (!is_finite($result)) {
            throw new evaluation_exception(get_string('error_notfinite', 'qtype_labcalc'));
        }
        return $result;
    }

    /**
     * How far from the expected value may an answer be?
     *
     * @param float $expected
     * @param float $tolerance
     * @param int $type TOLERANCE_RELATIVE or TOLERANCE_ABSOLUTE.
     * @return float the allowed absolute difference.
     */
    public static function allowed_error(float $expected, float $tolerance, int $type): float {
        if ($type == self::TOLERANCE_RELATIVE) {
            return abs($expected) * abs($tolerance) / 100;
        }
        return abs($tolerance);
    }

    /**
     * Is the given value within tolerance of the expected value?
     *
     * @param float $given
     * @param float $expected
     * @param float $tolerance
     * @param int $type TOLERANCE_RELATIVE or TOLERANCE_ABSOLUTE.
     * @return bool
     */
    public static function within_tolerance(float $given, float $expected, float $tolerance, int $type): bool {
        $allowed = self::allowed_error($expected, $tolerance, $type);
        // Small slack so that floating-point noise (e.g. 1.01 - 1.00) does not mark a boundary answer wrong.
        $slack = max(abs($expected), $allowed) * 1e-9;
        return abs($given - $expected) <= $allowed + $slack;
    }

    /**
     * Round a number to a number of significant figures.
     *
     * @param float $value
     * @param int $sigfigs
     * @return array [rounded value, number of decimal places to display (may be 0), use scientific notation?]
     */
    public static function round_sigfigs(float $value, int $sigfigs): array {
        $sigfigs = max(1, $sigfigs);
        if ($value == 0.0) {
            return [0.0, max(0, $sigfigs - 1), false];
        }
        $magnitude = (int) floor(log10(abs($value)));
        $rounded = round($value, $sigfigs - 1 - $magnitude);
        // Rounding may push the value up a magnitude (e.g. 9.996 -> 10.00).
        $magnitude = (int) floor(log10(abs($rounded)));
        $scientific = $magnitude >= 6 || $magnitude <= -4;
        return [$rounded, max(0, $sigfigs - 1 - $magnitude), $scientific];
    }

    /**
     * Format a number to a given number of significant figures for display.
     *
     * @param float $value
     * @param int $sigfigs
     * @param bool $localised use the current language's decimal separator.
     * @return string
     */
    public static function format_sigfigs(float $value, int $sigfigs, bool $localised = true): string {
        [$rounded, $dp, $scientific] = self::round_sigfigs($value, $sigfigs);
        if ($scientific) {
            $str = sprintf('%.' . (max(1, $sigfigs) - 1) . 'e', $rounded);
            // Tidy 1.23e+5 to 1.23e5.
            $str = preg_replace('/e\+?(-?)0*(\d)/', 'e$1$2', $str);
        } else {
            $str = number_format($rounded, $dp, '.', '');
        }
        if ($localised) {
            $str = str_replace('.', get_string('decsep', 'langconfig'), $str);
        }
        return $str;
    }

    /**
     * Turn the instructor's formula into something readable for display (without braces).
     *
     * @param string $formula
     * @return string
     */
    public static function display_formula(string $formula): string {
        return preg_replace('/\{\s*([A-Za-z][A-Za-z0-9_]*)\s*\}/', '$1', trim($formula));
    }
}
