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
 * @copyright  2026 Edward Bethel <edcbeth@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qtype_labcalc\evaluator;
use qtype_labcalc\evaluation_exception;
use qtype_labcalc\groupdata;
use qtype_labcalc\knownvalue;
use qtype_labcalc\sigfigs;

defined('MOODLE_INTERNAL') || die();

/**
 * Represents a Lab calculation question.
 *
 * The student enters one value for each experimental variable plus one or more calculated values.
 * Each expected value is computed by applying the instructor's formula to the student's own
 * experimental values, and the student's value is compared to it within a tolerance. Optionally the
 * number of significant figures is checked, and the measurements are compared with the values the
 * instructor recorded for the student's group.
 *
 * @copyright  2026 Edward Bethel <edcbeth@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_labcalc_question extends question_graded_automatically {

    /** @var string Prefix of the response fields holding experimental values. */
    const VAR_PREFIX = 'exp_';

    /** @var string Prefix of the response fields holding calculated values. */
    const RESULT_PREFIX = 'res_';

    /** @var string Attempt step variable remembering which group's data applies. */
    const GROUP_VAR = '_labcalcgroup';

    /** @var stdClass[] experimental variables: name, label, unit, rangemin, rangemax, samplevalue, checktolerance. */
    public $vars = [];

    /** @var stdClass[] calculated values: name, label, unit, formula, tolerance, tolerancetype, partialtolerance,
     *      partialfraction, sigfigsmode, sigfigs, sigfigsslack, sigfigspenalty, weight, displaysigfigs. */
    public $results = [];

    /** @var bool when a formula uses an earlier result, use the student's value for it. */
    public $carryforward = true;
    /** @var bool show the expected values (from the student's data) in feedback. */
    public $showexpected = true;
    /** @var bool show the formulas in feedback. */
    public $showformula = false;
    /** @var int groupdata::MODE_* */
    public $groupcheckmode = groupdata::MODE_OFF;
    /** @var float fraction of the mark removed when measurements do not match the group data. */
    public $grouppenalty = 0.5;
    /** @var string the group data table. */
    public $groupdata = '';
    /** @var string|null key of the group data row that applies to this attempt. */
    public $groupkey = null;

    /** @var string */
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

    /** @var array|null cache of the parsed group data. */
    protected $grouprows = null;

    /**
     * Response field name for an experimental variable.
     *
     * @param stdClass $var
     * @return string
     */
    public function field_for_var(stdClass $var): string {
        return self::VAR_PREFIX . $var->name;
    }

    /**
     * Response field name for a calculated value.
     *
     * @param stdClass $result
     * @return string
     */
    public function field_for_result(stdClass $result): string {
        return self::RESULT_PREFIX . $result->name;
    }

    public function get_expected_data() {
        $expected = [];
        foreach ($this->vars as $var) {
            $expected[$this->field_for_var($var)] = PARAM_RAW_TRIMMED;
        }
        foreach ($this->results as $result) {
            $expected[$this->field_for_result($result)] = PARAM_RAW_TRIMMED;
        }
        return $expected;
    }

    public function start_attempt(question_attempt_step $step, $variant) {
        $this->groupkey = $this->find_group_key((int) $step->get_user_id());
        $step->set_qt_var(self::GROUP_VAR, $this->groupkey ?? '');
    }

    public function apply_attempt_state(question_attempt_step $step) {
        $key = $step->get_qt_var(self::GROUP_VAR);
        $this->groupkey = ($key === null || $key === '') ? null : $key;
    }

    /**
     * Work out which row of the group data applies to the student starting this attempt.
     *
     * The question engine does not know which course an attempt belongs to, so this uses the page
     * the attempt is being started from (the quiz), as set up by require_login().
     *
     * @param int $userid
     * @return string|null
     */
    protected function find_group_key(int $userid): ?string {
        global $PAGE;
        if (!$this->get_group_rows()) {
            return null;
        }
        if ($this->groupcheckmode == groupdata::MODE_OFF && !$this->uses_group_known_values()) {
            return null;
        }
        try {
            $cm = $PAGE->cm;
            $courseid = $cm ? (int) $cm->course : (int) ($PAGE->course->id ?? 0);
            $groupingid = $cm ? (int) $cm->groupingid : 0;
            return groupdata::find_row_for_user($this->get_group_rows(), $userid, $courseid, $groupingid);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * The parsed group data.
     *
     * @return array normalised key => ['label' => ..., 'values' => [name => float]]
     */
    public function get_group_rows(): array {
        if ($this->grouprows === null) {
            [$this->grouprows] = groupdata::parse($this->groupdata, $this->group_data_columns());
        }
        return $this->grouprows;
    }

    /**
     * Column names allowed in the group data: experimental values, and {name}_known for each
     * calculated value that is compared with a known value.
     *
     * @return string[]
     */
    public function group_data_columns(): array {
        $columns = array_map(fn($v) => $v->name, $this->vars);
        foreach ($this->results as $res) {
            if ($res->knownmode != knownvalue::MODE_OFF) {
                $columns[] = $res->name . knownvalue::SUFFIX;
            }
        }
        return $columns;
    }

    /**
     * Does the group data give a known value for any calculated value?
     *
     * @return bool
     */
    protected function uses_group_known_values(): bool {
        foreach ($this->get_group_rows() as $row) {
            foreach (array_keys($row['values']) as $column) {
                if (substr($column, -strlen(knownvalue::SUFFIX)) === knownvalue::SUFFIX) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * The known value for a calculated value: from the student's group data if given there,
     * otherwise from the question's known value formula.
     *
     * @param stdClass $res
     * @param array $env values available to the formula.
     * @return float|null null if there is no known value (or it cannot be calculated).
     */
    public function get_known_value(stdClass $res, array $env): ?float {
        if ($this->known_value_from_group($res)) {
            return $this->get_group_rows()[$this->groupkey]['values'][$res->name . knownvalue::SUFFIX];
        }
        return $this->get_known_value_from_formula($res, $env);
    }

    /**
     * Does the known value for this calculated value come from the student's group data?
     *
     * @param stdClass $res
     * @return bool
     */
    public function known_value_from_group(stdClass $res): bool {
        if ($res->knownmode == knownvalue::MODE_OFF || $this->groupkey === null) {
            return false;
        }
        $rows = $this->get_group_rows();
        return isset($rows[$this->groupkey]['values'][$res->name . knownvalue::SUFFIX]);
    }

    /**
     * The known value from the question's known value formula.
     *
     * @param stdClass $res
     * @param array $env values available to the formula.
     * @return float|null
     */
    protected function get_known_value_from_formula(stdClass $res, array $env): ?float {
        if ($res->knownmode == knownvalue::MODE_OFF || $res->knownformula === '') {
            return null;
        }
        try {
            return evaluator::evaluate($res->knownformula, $env);
        } catch (evaluation_exception $e) {
            return null;
        }
    }

    /**
     * Normalise a number typed by a student: local decimal separator to '.', powers of ten to e-notation.
     *
     * @param mixed $value
     * @return string|null null if blank.
     */
    public static function normalise_number($value): ?string {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $value = str_replace(['−', ' ', "\u{00A0}"], ['-', '', ''], $value);
        $value = preg_replace('/(?:x|X|×|\*)10\^([+-]?\d+)$/u', 'e$1', $value);
        $decsep = get_string('decsep', 'langconfig');
        if ($decsep !== '.') {
            $value = str_replace($decsep, '.', $value);
        }
        return $value;
    }

    /**
     * Parse a number typed by a student. Accepts the local decimal separator and e-notation.
     *
     * @param mixed $value
     * @return float|null null if blank or not a valid number.
     */
    public static function parse_number($value): ?float {
        $value = self::normalise_number($value);
        if ($value === null || !is_numeric($value) || !is_finite((float) $value)) {
            return null;
        }
        return (float) $value;
    }

    /**
     * The display label for a variable or result.
     *
     * @param stdClass $item
     * @return string
     */
    public function label_for(stdClass $item): string {
        return $item->label !== '' ? $item->label : $item->name;
    }

    /**
     * Analyse a response fully. This is the heart of the question type.
     *
     * @param array $response
     * @return stdClass with fields:
     *      status   'blank', 'invalid', 'outofrange', 'formulaerror' or 'graded'
     *      message  explanation for validation errors, may be ''
     *      values   name => float of the parsed experimental values
     *      raw      name => normalised string of every value the student typed
     *      results  list of stdClass, one per calculated value (when graded):
     *               name, given, expected, valuestatus ('correct'|'partial'|'incorrect'),
     *               requiredsigfigs (int|null), sigfigs (null if not checked, or stdClass: required,
     *               given [min, max], ok), calculationscore (mark for the calculation alone),
     *               known (null, or the comparison with the known value from knownvalue::compare()),
     *               score (overall mark for this value, 0..1)
     *      datacheck null, or stdClass: group (label or null), mismatches (list), penalised (bool)
     *      fraction overall fraction
     */
    public function analyse_response(array $response): stdClass {
        $analysis = (object) ['status' => 'blank', 'message' => '', 'values' => [], 'raw' => [],
                'results' => [], 'datacheck' => null, 'fraction' => 0.0];

        $anything = false;
        $problems = [];
        $given = [];
        $items = array_merge(
            array_map(fn($v) => [$v, $this->field_for_var($v), true], $this->vars),
            array_map(fn($r) => [$r, $this->field_for_result($r), false], $this->results)
        );
        foreach ($items as [$item, $field, $isvar]) {
            $raw = self::normalise_number($response[$field] ?? null);
            if ($raw === null) {
                $problems[] = get_string('error_missingvalue', 'qtype_labcalc', $this->label_for($item));
                continue;
            }
            $anything = true;
            $value = self::parse_number($raw);
            if ($value === null) {
                $problems[] = get_string('error_notanumber', 'qtype_labcalc', $this->label_for($item));
                continue;
            }
            $analysis->raw[$item->name] = $raw;
            if ($isvar) {
                $analysis->values[$item->name] = $value;
            } else {
                $given[$item->name] = $value;
            }
        }
        if (!$anything) {
            return $analysis;
        }
        if ($problems) {
            $analysis->status = 'invalid';
            $analysis->message = implode(' ', $problems);
            return $analysis;
        }

        // Plausible range of each measurement.
        foreach ($this->vars as $var) {
            $value = $analysis->values[$var->name];
            if (($var->rangemin !== null && $value < $var->rangemin) ||
                    ($var->rangemax !== null && $value > $var->rangemax)) {
                $analysis->status = 'outofrange';
                $analysis->message = get_string('error_outofrange', 'qtype_labcalc', (object) [
                    'label' => $this->label_for($var),
                    'range' => $this->describe_range($var),
                ]);
                return $analysis;
            }
        }

        // Calculated values, in order. Later formulas may use earlier results.
        $env = $analysis->values;
        $required = [];
        $totalweight = 0.0;
        $total = 0.0;
        foreach ($this->results as $res) {
            try {
                $expected = evaluator::evaluate($res->formula, $env);
            } catch (evaluation_exception $e) {
                $analysis->status = 'formulaerror';
                $analysis->message = get_string('error_cannotevaluate', 'qtype_labcalc', $e->getMessage());
                $analysis->results = [];
                return $analysis;
            }
            $item = (object) ['name' => $res->name, 'given' => $given[$res->name], 'expected' => $expected,
                    'valuestatus' => 'incorrect', 'sigfigs' => null, 'requiredsigfigs' => null, 'score' => 0.0];
            if (evaluator::within_tolerance($item->given, $expected, $res->tolerance, $res->tolerancetype)) {
                $item->valuestatus = 'correct';
                $item->score = 1.0;
            } else if ($res->partialtolerance !== null && evaluator::within_tolerance(
                    $item->given, $expected, $res->partialtolerance, $res->tolerancetype)) {
                $item->valuestatus = 'partial';
                $item->score = $res->partialfraction;
            }

            $required[$res->name] = $this->required_sigfigs($res, $analysis->raw, $required);
            $item->requiredsigfigs = $required[$res->name];
            if ($res->sigfigsmode != sigfigs::MODE_OFF && $required[$res->name] !== null && $item->score > 0) {
                $range = sigfigs::count($analysis->raw[$res->name]);
                if ($range !== null) {
                    $ok = sigfigs::satisfies($range, $required[$res->name], $res->sigfigsslack);
                    $item->sigfigs = (object) ['required' => $required[$res->name], 'given' => $range, 'ok' => $ok];
                    if (!$ok) {
                        $item->score *= (1 - $res->sigfigspenalty);
                    }
                }
            }

            // Comparison with the known value.
            $item->calculationscore = $item->score;
            $item->known = null;
            $known = $this->get_known_value($res, $env);
            if ($known !== null) {
                $finding = $res->knowncompare == knownvalue::COMPARE_FROMDATA ? $expected : $item->given;
                $item->known = knownvalue::compare($finding, $known, $res);
                if ($res->knownmode == knownvalue::MODE_GRADED) {
                    $item->score = knownvalue::combine($item->calculationscore, $item->known->score, $res->accuracyweight);
                }
                $env[$res->name . knownvalue::SUFFIX] = $known;
            }

            $analysis->results[] = $item;
            $weight = max(0.0, $res->weight);
            $totalweight += $weight;
            $total += $weight * $item->score;
            $env[$res->name] = $this->carryforward ? $item->given : $expected;
        }
        if ($totalweight > 0) {
            $analysis->fraction = $total / $totalweight;
        } else if ($analysis->results) {
            $analysis->fraction = array_sum(array_map(fn($i) => $i->score, $analysis->results)) / count($analysis->results);
        }

        $analysis->datacheck = $this->check_group_data($analysis->values);
        if ($analysis->datacheck && $analysis->datacheck->penalised) {
            $analysis->fraction *= (1 - $this->grouppenalty);
        }
        $analysis->status = 'graded';
        return $analysis;
    }

    /**
     * How many significant figures are required for a result?
     *
     * In automatic mode this is the fewest significant figures among the experimental values (and
     * earlier results) that the formula uses: the usual rule for multiplication and division.
     *
     * @param stdClass $res
     * @param string[] $raw normalised strings typed by the student.
     * @param array $required name => required sig figs of earlier results.
     * @return int|null
     */
    protected function required_sigfigs(stdClass $res, array $raw, array $required): ?int {
        if ($res->sigfigsmode == sigfigs::MODE_FIXED) {
            return max(1, $res->sigfigs);
        }
        if ($res->sigfigsmode != sigfigs::MODE_AUTO) {
            return null;
        }
        $counts = [];
        $varnames = array_map(fn($v) => $v->name, $this->vars);
        foreach (evaluator::find_variables($res->formula) as $name) {
            if (in_array($name, $varnames) && isset($raw[$name])) {
                $range = sigfigs::count($raw[$name]);
                if ($range) {
                    // Be generous with ambiguous trailing zeros ("10" may mean 1 or 2 sig figs).
                    $counts[] = $range[1];
                }
            } else if (isset($required[$name])) {
                $counts[] = $required[$name];
            }
        }
        return $counts ? min($counts) : null;
    }

    /**
     * Compare the measurements with the values recorded for the student's group.
     *
     * @param float[] $values
     * @return stdClass|null null if group checks are off.
     */
    public function check_group_data(array $values): ?stdClass {
        if ($this->groupcheckmode == groupdata::MODE_OFF) {
            return null;
        }
        $rows = $this->get_group_rows();
        $check = (object) ['group' => null, 'mismatches' => [], 'penalised' => false];
        if ($this->groupkey === null || !isset($rows[$this->groupkey])) {
            return $check;
        }
        $row = $rows[$this->groupkey];
        $check->group = $row['label'];
        foreach ($this->vars as $var) {
            if (!array_key_exists($var->name, $row['values']) || !isset($values[$var->name])) {
                continue;
            }
            $recorded = $row['values'][$var->name];
            $tolerance = groupdata::parse_tolerance($var->checktolerance);
            if ($tolerance) {
                $ok = evaluator::within_tolerance($values[$var->name], $recorded, $tolerance[0], $tolerance[1]);
            } else {
                $ok = evaluator::within_tolerance($values[$var->name], $recorded, 0, evaluator::TOLERANCE_ABSOLUTE);
            }
            if (!$ok) {
                $check->mismatches[] = (object) ['var' => $var, 'recorded' => $recorded, 'given' => $values[$var->name]];
            }
        }
        $check->penalised = $check->mismatches && $this->groupcheckmode == groupdata::MODE_PENALTY;
        return $check;
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
     * Describe the tolerance of a result, e.g. "±1%" or "±0.05 g/mL".
     *
     * @param stdClass $res
     * @return string
     */
    public function describe_tolerance(stdClass $res): string {
        if ($res->tolerancetype == evaluator::TOLERANCE_RELATIVE) {
            return '±' . format_float($res->tolerance, -1) . '%';
        }
        return trim('±' . format_float($res->tolerance, -1) . ' ' . $res->unit);
    }

    public function is_complete_response(array $response) {
        return $this->analyse_response($response)->status === 'graded';
    }

    public function is_gradable_response(array $response) {
        return in_array($this->analyse_response($response)->status, ['graded', 'outofrange', 'formulaerror']);
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
            $value = $response[$this->field_for_var($var)] ?? '';
            if ($value !== '') {
                $parts[] = trim($this->label_for($var) . ' = ' . $value . ' ' . $var->unit);
            }
        }
        foreach ($this->results as $res) {
            $value = $response[$this->field_for_result($res)] ?? '';
            if ($value !== '') {
                $parts[] = trim($this->label_for($res) . ' = ' . $value . ' ' . $res->unit);
            }
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
        $response = [];
        $raw = [];
        $env = $this->get_sample_values();
        foreach ($this->vars as $var) {
            $response[$this->field_for_var($var)] = self::to_response_string($env[$var->name]);
            $raw[$var->name] = (string) $env[$var->name];
        }
        $required = [];
        foreach ($this->results as $res) {
            try {
                $expected = evaluator::evaluate($res->formula, $env);
            } catch (evaluation_exception $e) {
                return null;
            }
            $required[$res->name] = $this->required_sigfigs($res, $raw, $required);
            if ($res->sigfigsmode != sigfigs::MODE_OFF && $required[$res->name] !== null) {
                $string = evaluator::format_sigfigs($expected, $required[$res->name], false);
            } else {
                $string = (string) $expected;
            }
            $raw[$res->name] = $string;
            $response[$this->field_for_result($res)] = str_replace('.', get_string('decsep', 'langconfig'), $string);
            $known = $this->get_known_value($res, $env);
            if ($known !== null) {
                $env[$res->name . knownvalue::SUFFIX] = $known;
            }
            $env[$res->name] = $this->carryforward ? (float) $string : $expected;
        }
        return $response;
    }

    /**
     * Convert a float to a string that parse_number() will read back exactly.
     *
     * @param float $value
     * @return string
     */
    protected static function to_response_string(float $value): string {
        return str_replace('.', get_string('decsep', 'langconfig'), (string) $value);
    }

    public function get_right_answer_summary() {
        $parts = [];
        foreach ($this->results as $res) {
            $part = $this->label_for($res) . ' = ' . evaluator::display_formula($res->formula) .
                    ' (' . $this->describe_tolerance($res) . ')';
            if ($res->knownmode != knownvalue::MODE_OFF && $res->knownformula !== '') {
                $part .= ', ' . get_string('knownvaluesummary', 'qtype_labcalc', evaluator::display_formula($res->knownformula));
            }
            $parts[] = $part;
        }
        return get_string('rightanswersummary', 'qtype_labcalc', implode('; ', $parts));
    }

    public function grade_response(array $response) {
        $fraction = $this->analyse_response($response)->fraction;
        return [$fraction, question_state::graded_state_for_fraction($fraction)];
    }

    public function classify_response(array $response) {
        $analysis = $this->analyse_response($response);
        $classified = [];
        foreach ($this->results as $i => $res) {
            if (in_array($analysis->status, ['blank', 'invalid'])) {
                $classified[$i] = question_classified_response::no_response();
                continue;
            }
            $value = $response[$this->field_for_result($res)] ?? '';
            if ($analysis->status !== 'graded') {
                $classified[$i] = new question_classified_response('invalid', $value, 0);
                continue;
            }
            $item = $analysis->results[$i];
            $class = $item->valuestatus;
            if ($class !== 'incorrect' && $item->sigfigs && !$item->sigfigs->ok) {
                $class .= '_badsf';
            }
            $classified[$i] = new question_classified_response($class, $value, $item->score);
        }
        return $classified;
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
