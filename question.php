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


/**
 * Lab calculation question definition class.
 *
 * @package    qtype_labcalc
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qtype_labcalc\evaluator;
use qtype_labcalc\evaluation_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Represents a Lab calculation question.
 *
 * The student enters one value for each experimental variable plus their calculated result.
 * The expected result is computed by applying the instructor's formula to the student's
 * experimental values, and the student's result is compared to it within a tolerance.
 *
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_labcalc_question extends question_graded_automatically {

    /** @var string Response field for the student's calculated value. */
    const ANSWER_FIELD = 'answer';

    /** @var string Prefix of the response fields holding experimental values. */
    const VAR_PREFIX = 'exp_';

    /** @var string formula using {name} placeholders. */
    public $formula;
    /** @var string label for the calculated value. */
    public $resultlabel = '';
    /** @var string unit for the calculated value. */
    public $resultunit = '';
    /** @var float tolerance for full marks. */
    public $tolerance = 1.0;
    /** @var int evaluator::TOLERANCE_RELATIVE or evaluator::TOLERANCE_ABSOLUTE. */
    public $tolerancetype = evaluator::TOLERANCE_RELATIVE;
    /** @var float|null wider tolerance for partial credit, or null for none. */
    public $partialtolerance = null;
    /** @var float fraction for partial credit. */
    public $partialfraction = 0.5;
    /** @var int significant figures when displaying the expected value. */
    public $displaysigfigs = 4;
    /** @var bool show the expected value (from the student's data) in feedback. */
    public $showexpected = true;
    /** @var bool show the formula in feedback. */
    public $showformula = false;
    /** @var stdClass[] experimental variables: name, label, unit, rangemin, rangemax, samplevalue. */
    public $vars = [];

    /** @var string[] feedback fields, here for clarity. */
    public $correctfeedback;
    /** @var int */
    public $correctfeedbackformat;
    /** @var string */
    public $partiallycorrectfeedback;
    /** @var int */
    public $partiallycorrectfeedbackformat;
    /** @var string */
    public $incorrectfeedback;
    /** @var int */
    public $incorrectfeedbackformat;

    /**
     * Name of the response field for one experimental variable.
     *
     * @param stdClass $var
     * @return string
     */
    public function field_for(stdClass $var): string {
        return self::VAR_PREFIX . $var->name;
    }

    public function get_expected_data() {
        $expected = [];
        foreach ($this->vars as $var) {
            $expected[$this->field_for($var)] = PARAM_RAW_TRIMMED;
        }
        $expected[self::ANSWER_FIELD] = PARAM_RAW_TRIMMED;
        return $expected;
    }

    /**
     * Parse a number typed by a student. Accepts the local decimal separator and e-notation.
     *
     * @param mixed $value
     * @return float|null null if blank or not a valid number.
     */
    public static function parse_number($value): ?float {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        // Allow a few common ways of writing powers of ten: 1.2x10^-3, 1.2×10^-3, 1.2*10^-3.
        $value = preg_replace('/\s*(?:x|×|\*)\s*10\s*\^\s*([+-]?\d+)$/u', 'e$1', $value);
        // Allow a unicode minus sign.
        $value = str_replace('−', '-', $value);
        $number = unformat_float($value, true);
        if ($number === false || $number === null || !is_finite((float) $number)) {
            return null;
        }
        return (float) $number;
    }

    /**
     * The display label for a variable.
     *
     * @param stdClass $var
     * @return string
     */
    public function var_label(stdClass $var): string {
        return $var->label !== '' ? $var->label : $var->name;
    }

    /**
     * The display label for the calculated value.
     *
     * @return string
     */
    public function result_label(): string {
        return $this->resultlabel !== '' ? $this->resultlabel : get_string('calculatedvalue', 'qtype_labcalc');
    }

    /**
     * Analyse a response fully. This is the heart of the question type.
     *
     * @param array $response
     * @return stdClass with fields:
     *      status   one of 'blank', 'invalid', 'outofrange', 'formulaerror', 'correct', 'partial', 'incorrect'
     *      message  explanation (for validation errors / feedback), may be ''
     *      values   name => float of the parsed experimental values
     *      given    float|null the parsed calculated value
     *      expected float|null value expected from the student's data
     *      fraction float
     */
    public function analyse_response(array $response): stdClass {
        $result = (object) ['status' => 'blank', 'message' => '', 'values' => [],
                'given' => null, 'expected' => null, 'fraction' => 0.0];

        $anything = false;
        $problems = [];
        foreach ($this->vars as $var) {
            $raw = $response[$this->field_for($var)] ?? '';
            if (trim((string) $raw) === '') {
                $problems[] = get_string('error_missingvalue', 'qtype_labcalc', $this->var_label($var));
                continue;
            }
            $anything = true;
            $value = self::parse_number($raw);
            if ($value === null) {
                $problems[] = get_string('error_notanumber', 'qtype_labcalc', $this->var_label($var));
                continue;
            }
            $result->values[$var->name] = $value;
        }

        $rawanswer = $response[self::ANSWER_FIELD] ?? '';
        if (trim((string) $rawanswer) === '') {
            $problems[] = get_string('error_missingvalue', 'qtype_labcalc', $this->result_label());
        } else {
            $anything = true;
            $result->given = self::parse_number($rawanswer);
            if ($result->given === null) {
                $problems[] = get_string('error_notanumber', 'qtype_labcalc', $this->result_label());
            }
        }

        if (!$anything) {
            return $result;
        }
        if ($problems) {
            $result->status = 'invalid';
            $result->message = implode(' ', $problems);
            return $result;
        }

        // Check each experimental value is within the instructor's plausible range.
        foreach ($this->vars as $var) {
            $value = $result->values[$var->name];
            if (($var->rangemin !== null && $value < $var->rangemin) ||
                    ($var->rangemax !== null && $value > $var->rangemax)) {
                $result->status = 'outofrange';
                $result->message = get_string('error_outofrange', 'qtype_labcalc', (object) [
                    'label' => $this->var_label($var),
                    'range' => $this->describe_range($var),
                ]);
                return $result;
            }
        }

        try {
            $result->expected = evaluator::evaluate($this->formula, $result->values);
        } catch (evaluation_exception $e) {
            $result->status = 'formulaerror';
            $result->message = get_string('error_cannotevaluate', 'qtype_labcalc', $e->getMessage());
            return $result;
        }

        if (evaluator::within_tolerance($result->given, $result->expected, $this->tolerance, $this->tolerancetype)) {
            $result->status = 'correct';
            $result->fraction = 1.0;
        } else if ($this->partialtolerance !== null && evaluator::within_tolerance(
                $result->given, $result->expected, $this->partialtolerance, $this->tolerancetype)) {
            $result->status = 'partial';
            $result->fraction = $this->partialfraction;
        } else {
            $result->status = 'incorrect';
        }
        return $result;
    }

    /**
     * Describe the allowed range of a variable, e.g. "between 1 and 10 g".
     *
     * @param stdClass $var
     * @return string '' if there is no range.
     */
    public function describe_range(stdClass $var): string {
        $a = (object) [
            'min' => $var->rangemin === null ? '' : format_float($var->rangemin, -1),
            'max' => $var->rangemax === null ? '' : format_float($var->rangemax, -1),
            'unit' => $var->unit,
        ];
        if ($var->rangemin !== null && $var->rangemax !== null) {
            $str = get_string('range_between', 'qtype_labcalc', $a);
        } else if ($var->rangemin !== null) {
            $str = get_string('range_min', 'qtype_labcalc', $a);
        } else if ($var->rangemax !== null) {
            $str = get_string('range_max', 'qtype_labcalc', $a);
        } else {
            return '';
        }
        return trim($str);
    }

    /**
     * Format a value for display with the configured significant figures.
     *
     * @param float $value
     * @return string
     */
    public function format_value(float $value): string {
        return evaluator::format_sigfigs($value, $this->displaysigfigs);
    }

    /**
     * Describe the tolerance, e.g. "±1%" or "±0.05 g/mL".
     *
     * @param float $tolerance
     * @return string
     */
    public function describe_tolerance(float $tolerance): string {
        if ($this->tolerancetype == evaluator::TOLERANCE_RELATIVE) {
            return '±' . format_float($tolerance, -1) . '%';
        }
        return trim('±' . format_float($tolerance, -1) . ' ' . $this->resultunit);
    }

    public function is_complete_response(array $response) {
        $status = $this->analyse_response($response)->status;
        return !in_array($status, ['blank', 'invalid', 'outofrange', 'formulaerror']);
    }

    public function is_gradable_response(array $response) {
        $status = $this->analyse_response($response)->status;
        return !in_array($status, ['blank', 'invalid']);
    }

    public function get_validation_error(array $response) {
        $analysis = $this->analyse_response($response);
        if ($analysis->status === 'blank') {
            return get_string('pleaseenterall', 'qtype_labcalc');
        }
        return $analysis->message;
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        foreach (array_keys($this->get_expected_data()) as $key) {
            if (!question_utils::arrays_same_at_key_missing_is_blank($prevresponse, $newresponse, $key)) {
                return false;
            }
        }
        return true;
    }

    public function summarise_response(array $response) {
        $parts = [];
        foreach ($this->vars as $var) {
            $value = $response[$this->field_for($var)] ?? '';
            if ($value !== '') {
                $parts[] = trim($this->var_label($var) . ' = ' . $value . ' ' . $var->unit);
            }
        }
        if (($response[self::ANSWER_FIELD] ?? '') !== '') {
            $parts[] = trim($this->result_label() . ' = ' . $response[self::ANSWER_FIELD] . ' ' . $this->resultunit);
        }
        return $parts ? implode('; ', $parts) : null;
    }

    /**
     * Sample experimental values (used for previews and "fill in correct responses").
     *
     * @return float[] name => value
     */
    public function get_sample_values(): array {
        $values = [];
        foreach ($this->vars as $var) {
            if ($var->samplevalue !== null) {
                $value = $var->samplevalue;
            } else if ($var->rangemin !== null && $var->rangemax !== null) {
                $value = ($var->rangemin + $var->rangemax) / 2;
            } else if ($var->rangemin !== null) {
                $value = $var->rangemin;
            } else if ($var->rangemax !== null) {
                $value = $var->rangemax;
            } else {
                $value = 1.0;
            }
            $values[$var->name] = $value;
        }
        return $values;
    }

    public function get_correct_response() {
        $values = $this->get_sample_values();
        try {
            $expected = evaluator::evaluate($this->formula, $values);
        } catch (evaluation_exception $e) {
            return null;
        }
        $response = [];
        foreach ($this->vars as $var) {
            $response[$this->field_for($var)] = self::to_response_string($values[$var->name]);
        }
        $response[self::ANSWER_FIELD] = self::to_response_string($expected);
        return $response;
    }

    /**
     * Convert a float to a string that parse_number() will read back exactly.
     *
     * @param float $value
     * @return string
     */
    protected static function to_response_string(float $value): string {
        $str = (string) $value;
        return str_replace('.', get_string('decsep', 'langconfig'), $str);
    }

    public function get_right_answer_summary() {
        return get_string('rightanswersummary', 'qtype_labcalc', (object) [
            'label' => $this->result_label(),
            'formula' => evaluator::display_formula($this->formula),
            'tolerance' => $this->describe_tolerance($this->tolerance),
        ]);
    }

    public function grade_response(array $response) {
        $analysis = $this->analyse_response($response);
        return [$analysis->fraction, question_state::graded_state_for_fraction($analysis->fraction)];
    }

    public function classify_response(array $response) {
        $analysis = $this->analyse_response($response);
        if (in_array($analysis->status, ['blank', 'invalid'])) {
            return [$this->id => question_classified_response::no_response()];
        }
        return [$this->id => new question_classified_response($analysis->status,
                $this->summarise_response($response), $analysis->fraction)];
    }

    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        if ($component == 'question' && in_array($filearea,
                ['correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'])) {
            return $this->check_combined_feedback_file_access($qa, $options, $filearea, $args);
        } else if ($component == 'question' && $filearea == 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);
        }
        return parent::check_file_access($qa, $options, $component, $filearea, $args, $forcedownload);
    }
}
