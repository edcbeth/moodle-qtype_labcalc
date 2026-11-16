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
 * Lab calculation question renderer.
 *
 * @package    qtype_labcalc
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qtype_labcalc\evaluator;
use qtype_labcalc\knownvalue;
use qtype_labcalc\sigfigs;

defined('MOODLE_INTERNAL') || die();

/**
 * Generates the output for Lab calculation questions.
 *
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_labcalc_renderer extends qtype_with_combined_feedback_renderer {

    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        /** @var qtype_labcalc_question $question */
        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();
        $analysis = $question->analyse_response($response);
        $graded = $options->correctness && $analysis->status === 'graded';
        $invalid = $options->correctness && in_array($analysis->status, ['outofrange', 'formulaerror']);

        $output = html_writer::tag('div', $question->format_questiontext($qa), ['class' => 'qtext']);

        // Experimental values.
        $mismatched = [];
        if ($options->feedback && $analysis->datacheck) {
            foreach ($analysis->datacheck->mismatches as $mismatch) {
                $mismatched[$mismatch->var->name] = true;
            }
        }
        $rows = '';
        foreach ($question->vars as $var) {
            $rows .= $this->input_row($qa, $options, $question->field_for_var($var), $question->label_for($var),
                    $var->unit, $question->describe_range($var), null, isset($mismatched[$var->name]));
        }
        $output .= $this->fieldset(get_string('yourexperimentalvalues', 'qtype_labcalc'), $rows, 'labcalc-experimental');

        // Calculated values.
        $rows = '';
        foreach ($question->results as $i => $res) {
            $fraction = null;
            if ($graded) {
                $fraction = $analysis->results[$i]->score;
            } else if ($invalid) {
                $fraction = 0.0;
            }
            $rows .= $this->input_row($qa, $options, $question->field_for_result($res), $question->label_for($res),
                    $res->unit, '', $fraction, false);
        }
        $legend = count($question->results) > 1 ? 'yourcalculatedvalues' : 'yourcalculatedvalue';
        $output .= $this->fieldset(get_string($legend, 'qtype_labcalc'), $rows, 'labcalc-result');

        if ($qa->get_state() == question_state::$invalid) {
            $output .= html_writer::nonempty_tag('div', $question->get_validation_error($response),
                    ['class' => 'validationerror']);
        }

        return html_writer::div($output, 'ablock labcalc');
    }

    /**
     * Wrap rows in a fieldset with a legend.
     *
     * @param string $legend
     * @param string $rows HTML
     * @param string $class
     * @return string HTML
     */
    protected function fieldset(string $legend, string $rows, string $class): string {
        return html_writer::tag('fieldset',
                html_writer::tag('legend', $legend, ['class' => 'labcalc-legend']) . html_writer::div($rows, 'labcalc-rows'),
                ['class' => 'labcalc-group ' . $class]);
    }

    /**
     * Render one labelled input row.
     *
     * @param question_attempt $qa
     * @param question_display_options $options
     * @param string $field response field name.
     * @param string $label
     * @param string $unit
     * @param string $hint extra text, e.g. acceptable range.
     * @param float|null $fraction show a correctness icon for this fraction, or null for none.
     * @param bool $mismatch mark this measurement as not matching the group data.
     * @return string HTML
     */
    protected function input_row(question_attempt $qa, question_display_options $options, string $field,
            string $label, string $unit, string $hint, ?float $fraction, bool $mismatch): string {
        $inputname = $qa->get_qt_field_name($field);
        $attributes = [
            'type' => 'text',
            'name' => $inputname,
            'id' => $inputname,
            'value' => $qa->get_last_qt_var($field) ?? '',
            'size' => 14,
            'class' => 'form-control d-inline-block labcalc-input',
            'autocomplete' => 'off',
            'inputmode' => 'decimal',
        ];
        if ($options->readonly) {
            $attributes['readonly'] = 'readonly';
        }
        $after = '';
        if ($fraction !== null) {
            $attributes['class'] .= ' ' . $this->feedback_class($fraction);
            $after = $this->feedback_image($fraction);
        }
        if ($mismatch) {
            $attributes['class'] .= ' labcalc-mismatch';
            $after .= html_writer::span(get_string('doesnotmatchgroup', 'qtype_labcalc'), 'labcalc-mismatch-note');
        }
        if ($hint !== '') {
            $attributes['aria-describedby'] = $inputname . '_hint';
        }

        $control = html_writer::empty_tag('input', $attributes);
        if ($unit !== '') {
            $control .= html_writer::span(format_string($unit), 'labcalc-unit');
        }
        $control .= $after;
        if ($hint !== '') {
            $control .= html_writer::span(s($hint), 'labcalc-hint text-muted', ['id' => $inputname . '_hint']);
        }
        return html_writer::div(
                html_writer::label(format_string($label), $inputname, false, ['class' => 'labcalc-label']) .
                html_writer::span($control, 'labcalc-control'), 'labcalc-row');
    }

    protected function specific_feedback(question_attempt $qa) {
        /** @var qtype_labcalc_question $question */
        $question = $qa->get_question();
        $analysis = $question->analyse_response($qa->get_last_qt_data());

        $output = '';
        if (in_array($analysis->status, ['outofrange', 'formulaerror'])) {
            $output .= html_writer::div(s($analysis->message), 'labcalc-problem');
        }

        // Significant figures.
        $sfnotes = [];
        foreach ($analysis->results as $i => $item) {
            if ($item->sigfigs && !$item->sigfigs->ok) {
                $res = $question->results[$i];
                $sfnotes[] = html_writer::tag('li', get_string('sigfigswrong', 'qtype_labcalc', (object) [
                    'label' => format_string($question->label_for($res)),
                    'given' => sigfigs::describe($item->sigfigs->given),
                    'required' => $item->sigfigs->required,
                    'penalty' => format_float($res->sigfigspenalty * 100, -1),
                ]));
            }
        }
        if ($sfnotes) {
            $output .= html_writer::div(html_writer::tag('ul', implode('', $sfnotes)), 'labcalc-sigfigs');
        }

        // Comparison with known values.
        $knownnotes = [];
        foreach ($analysis->results as $i => $item) {
            if ($item->known) {
                $knownnotes[] = html_writer::tag('li', $this->known_comparison($question, $question->results[$i], $item,
                        $analysis->raw));
            }
        }
        if ($knownnotes) {
            $output .= html_writer::div(html_writer::tag('ul', implode('', $knownnotes)), 'labcalc-known');
        }

        // Group data.
        $check = $analysis->datacheck;
        if ($check && $analysis->status === 'graded') {
            if ($check->group === null) {
                $output .= html_writer::div(get_string('groupcheck_nogroup', 'qtype_labcalc'), 'labcalc-groupcheck');
            } else if ($check->mismatches) {
                $items = '';
                foreach ($check->mismatches as $m) {
                    $items .= html_writer::tag('li', get_string('groupcheck_mismatchitem', 'qtype_labcalc', (object) [
                        'label' => format_string($question->label_for($m->var)),
                        'given' => format_float($m->given, -1),
                        'recorded' => format_float($m->recorded, -1),
                        'unit' => format_string($m->var->unit),
                    ]));
                }
                $text = get_string('groupcheck_mismatch', 'qtype_labcalc', format_string($check->group)) .
                        html_writer::tag('ul', $items);
                if ($check->penalised) {
                    $text .= get_string('groupcheck_penalty', 'qtype_labcalc', format_float($question->grouppenalty * 100, -1));
                }
                $output .= html_writer::div($text, 'labcalc-groupcheck labcalc-problem');
            } else {
                $output .= html_writer::div(get_string('groupcheck_ok', 'qtype_labcalc', format_string($check->group)),
                        'labcalc-groupcheck');
            }
        }

        $output .= $this->combined_feedback($qa);
        return $output;
    }

    /**
     * Describe how one finding compares with its known value.
     *
     * @param qtype_labcalc_question $question
     * @param stdClass $res the calculated value's settings.
     * @param stdClass $item its analysis.
     * @param string[] $analysisraw the values the student typed (normalised), from the analysis.
     * @return string HTML
     */
    protected function known_comparison(qtype_labcalc_question $question, stdClass $res, stdClass $item,
            array $analysisraw): string {
        $k = $item->known;
        if ($res->knowncompare == knownvalue::COMPARE_FROMDATA) {
            $finding = evaluator::format_sigfigs($k->finding, $item->requiredsigfigs ?? $res->displaysigfigs);
        } else {
            // The student's value, as they wrote it.
            $finding = str_replace('.', get_string('decsep', 'langconfig'), $analysisraw[$res->name] ?? (string) $k->finding);
        }
        $a = (object) [
            'label' => format_string($question->label_for($res)),
            'finding' => s($finding),
            'known' => $this->format_known_value($question, $res, $k->known),
            'unit' => format_string($res->unit),
            'percent' => $k->percent === null ? '' : format_float($k->percent, $k->percent < 10 ? 2 : 1),
            'difference' => evaluator::format_sigfigs(abs($k->difference), 3),
        ];
        $which = $res->knowncompare == knownvalue::COMPARE_FROMDATA ? 'fromdata' : 'reported';
        $how = $k->percent === null ? 'absolute' : 'percent';
        $text = get_string("knowncomparison_{$which}_{$how}", 'qtype_labcalc', $a);

        if ($res->knownmode == knownvalue::MODE_GRADED) {
            $b = (object) [
                'tolerance' => $res->accuracytolerancetype == evaluator::TOLERANCE_RELATIVE
                        ? '±' . format_float($res->accuracytolerance, -1) . '%'
                        : trim('±' . format_float($res->accuracytolerance, -1) . ' ' . $a->unit),
                'weight' => format_float($res->accuracyweight * 100, -1),
                'partial' => format_float($res->accuracypartialfraction * 100, -1),
            ];
            $text .= ' ' . get_string('accuracy_' . $k->status, 'qtype_labcalc', $b);
        }
        return $text;
    }

    /**
     * Show a known value as the instructor gave it: a number typed in the question or the group data
     * is shown as written; a value calculated from a formula is shown to the display precision.
     *
     * @param qtype_labcalc_question $question
     * @param stdClass $res
     * @param float $known
     * @return string
     */
    protected function format_known_value(qtype_labcalc_question $question, stdClass $res, float $known): string {
        if ($question->known_value_from_group($res)) {
            return format_float($known, -1);
        }
        $formula = trim($res->knownformula);
        if (is_numeric($formula)) {
            return s(str_replace('.', get_string('decsep', 'langconfig'), $formula));
        }
        return evaluator::format_sigfigs($known, $res->displaysigfigs);
    }

    protected function correct_response(question_attempt $qa) {
        /** @var qtype_labcalc_question $question */
        $question = $qa->get_question();
        $analysis = $question->analyse_response($qa->get_last_qt_data());
        $items = '';

        foreach ($question->results as $i => $res) {
            $parts = [];
            $item = $analysis->results[$i] ?? null;
            if ($question->showexpected && $item) {
                // When significant figures are graded, show the expected value to that precision.
                $digits = $item->requiredsigfigs ?? $res->displaysigfigs;
                $a = (object) [
                    'label' => format_string($question->label_for($res)),
                    'value' => evaluator::format_sigfigs($item->expected, $digits),
                    'unit' => format_string($res->unit),
                    'tolerance' => $question->describe_tolerance($res),
                ];
                $parts[] = get_string('expectedfromyourdata', 'qtype_labcalc', $a);
                if ($item->requiredsigfigs !== null) {
                    $parts[] = get_string('sigfigsexpected', 'qtype_labcalc', $item->requiredsigfigs);
                }
                if ($item->known) {
                    $parts[] = get_string('knownvalueis', 'qtype_labcalc', (object) [
                        'value' => $this->format_known_value($question, $res, $item->known->known),
                        'unit' => format_string($res->unit),
                    ]);
                }
            }
            if ($question->showformula) {
                $parts[] = html_writer::tag('code', s(format_string($question->label_for($res)) . ' = ' .
                        evaluator::display_formula($res->formula)));
            }
            if ($parts) {
                $items .= html_writer::tag('li', implode(' ', $parts));
            }
        }
        if ($items === '') {
            return '';
        }
        $intro = $question->showexpected && $analysis->results ? get_string('usingyourdata', 'qtype_labcalc') : '';
        return $intro . html_writer::tag('ul', $items, ['class' => 'labcalc-expected']);
    }
}
