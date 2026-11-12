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

        $output = html_writer::tag('div', $question->format_questiontext($qa), ['class' => 'qtext']);

        // Experimental values.
        $rows = '';
        foreach ($question->vars as $var) {
            $range = $question->describe_range($var);
            $rows .= $this->input_row($qa, $options, $question->field_for($var),
                    $question->var_label($var), $var->unit, $range, null);
        }
        $output .= html_writer::tag('fieldset',
                html_writer::tag('legend', get_string('yourexperimentalvalues', 'qtype_labcalc'),
                        ['class' => 'labcalc-legend']) .
                html_writer::div($rows, 'labcalc-rows'),
                ['class' => 'labcalc-group labcalc-experimental']);

        // Calculated value.
        $fraction = null;
        if ($options->correctness && in_array($analysis->status, ['correct', 'partial', 'incorrect', 'outofrange',
                'formulaerror'])) {
            $fraction = $analysis->fraction;
        }
        $row = $this->input_row($qa, $options, qtype_labcalc_question::ANSWER_FIELD,
                $question->result_label(), $question->resultunit, '', $fraction);
        $output .= html_writer::tag('fieldset',
                html_writer::tag('legend', get_string('yourcalculatedvalue', 'qtype_labcalc'),
                        ['class' => 'labcalc-legend']) .
                html_writer::div($row, 'labcalc-rows'),
                ['class' => 'labcalc-group labcalc-result']);

        if ($qa->get_state() == question_state::$invalid) {
            $output .= html_writer::nonempty_tag('div', $question->get_validation_error($response),
                    ['class' => 'validationerror']);
        }

        return html_writer::div($output, 'ablock labcalc');
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
     * @return string HTML
     */
    protected function input_row(question_attempt $qa, question_display_options $options, string $field,
            string $label, string $unit, string $hint, ?float $fraction): string {
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
        $feedbackimg = '';
        if ($fraction !== null) {
            $attributes['class'] .= ' ' . $this->feedback_class($fraction);
            $feedbackimg = $this->feedback_image($fraction);
        }
        if ($hint !== '') {
            $attributes['aria-describedby'] = $inputname . '_hint';
        }

        $labelhtml = html_writer::label(format_string($label), $inputname, false, ['class' => 'labcalc-label']);
        $control = html_writer::empty_tag('input', $attributes);
        if ($unit !== '') {
            $control .= html_writer::span(format_string($unit), 'labcalc-unit');
        }
        $control .= $feedbackimg;
        if ($hint !== '') {
            $control .= html_writer::span(s($hint), 'labcalc-hint text-muted', ['id' => $inputname . '_hint']);
        }
        return html_writer::div($labelhtml . html_writer::span($control, 'labcalc-control'), 'labcalc-row');
    }

    protected function specific_feedback(question_attempt $qa) {
        /** @var qtype_labcalc_question $question */
        $question = $qa->get_question();
        $analysis = $question->analyse_response($qa->get_last_qt_data());

        $output = '';
        if (in_array($analysis->status, ['outofrange', 'formulaerror'])) {
            $output .= html_writer::div(s($analysis->message), 'labcalc-problem');
        }
        $output .= $this->combined_feedback($qa);
        return $output;
    }

    protected function correct_response(question_attempt $qa) {
        /** @var qtype_labcalc_question $question */
        $question = $qa->get_question();
        $analysis = $question->analyse_response($qa->get_last_qt_data());
        $output = '';

        if ($question->showexpected && $analysis->expected !== null) {
            $output .= html_writer::div(get_string('expectedfromyourdata', 'qtype_labcalc', (object) [
                'label' => format_string($question->result_label()),
                'value' => $question->format_value($analysis->expected),
                'unit' => format_string($question->resultunit),
                'tolerance' => $question->describe_tolerance($question->tolerance),
            ]), 'labcalc-expected');
        }
        if ($question->showformula) {
            $output .= html_writer::div(get_string('formulaused', 'qtype_labcalc',
                    html_writer::tag('code', s(format_string($question->result_label()) . ' = ' .
                            evaluator::display_formula($question->formula)))), 'labcalc-formula');
        }
        return $output;
    }
}
