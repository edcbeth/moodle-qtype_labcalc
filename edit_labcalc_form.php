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
 * Editing form for the Lab calculation question type.
 *
 * @package    qtype_labcalc
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qtype_labcalc\evaluator;
use qtype_labcalc\evaluation_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Lab calculation question editing form.
 *
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_labcalc_edit_form extends question_edit_form {

    /** @var int Number of blank experimental value slots shown on a new question. */
    const VARS_AT_START = 2;

    /** @var int Number of slots added by the "add more" button. */
    const VARS_TO_ADD = 2;

    protected function definition_inner($mform) {
        // Experimental values.
        $mform->addElement('header', 'varshdr', get_string('experimentalvalues', 'qtype_labcalc'));
        $mform->setExpanded('varshdr', true);
        $mform->addElement('static', 'varsintro', '', get_string('experimentalvalues_desc', 'qtype_labcalc'));

        $repeated = [];
        $repeated[] = $mform->createElement('text', 'varname',
                get_string('varnameno', 'qtype_labcalc', '{no}'), ['size' => 20, 'maxlength' => 40]);
        $repeated[] = $mform->createElement('text', 'varlabel',
                get_string('varlabel', 'qtype_labcalc'), ['size' => 40]);
        $repeated[] = $mform->createElement('text', 'varunit',
                get_string('varunit', 'qtype_labcalc'), ['size' => 10]);
        $rangegroup = [
            $mform->createElement('text', 'varmin', get_string('varmin', 'qtype_labcalc'),
                    ['size' => 10, 'placeholder' => get_string('varmin', 'qtype_labcalc')]),
            $mform->createElement('text', 'varmax', get_string('varmax', 'qtype_labcalc'),
                    ['size' => 10, 'placeholder' => get_string('varmax', 'qtype_labcalc')]),
        ];
        $repeated[] = $mform->createElement('group', 'varrange', get_string('varrange', 'qtype_labcalc'),
                $rangegroup, ' ', false);
        $repeated[] = $mform->createElement('text', 'varsample',
                get_string('varsample', 'qtype_labcalc'), ['size' => 10]);

        $repeatedoptions = [];
        foreach (['varname', 'varlabel', 'varunit', 'varmin', 'varmax', 'varsample'] as $field) {
            $repeatedoptions[$field]['type'] = PARAM_RAW_TRIMMED;
        }
        $repeatedoptions['varname']['helpbutton'] = ['varname', 'qtype_labcalc'];
        $repeatedoptions['varrange']['helpbutton'] = ['varrange', 'qtype_labcalc'];
        $repeatedoptions['varsample']['helpbutton'] = ['varsample', 'qtype_labcalc'];

        $existing = 0;
        if (!empty($this->question->options->vars)) {
            $existing = count($this->question->options->vars);
        }
        $repeatsatstart = max(self::VARS_AT_START, $existing);
        $this->repeat_elements($repeated, $repeatsatstart, $repeatedoptions, 'novars', 'addvars',
                self::VARS_TO_ADD, get_string('addmorevars', 'qtype_labcalc', self::VARS_TO_ADD), true);

        // Calculated value and formula.
        $mform->addElement('header', 'resulthdr', get_string('calculatedvalue', 'qtype_labcalc'));
        $mform->setExpanded('resulthdr', true);

        $mform->addElement('text', 'resultlabel', get_string('resultlabel', 'qtype_labcalc'), ['size' => 40]);
        $mform->setType('resultlabel', PARAM_TEXT);
        $mform->setDefault('resultlabel', get_string('calculatedvalue', 'qtype_labcalc'));

        $mform->addElement('text', 'resultunit', get_string('resultunit', 'qtype_labcalc'), ['size' => 10]);
        $mform->setType('resultunit', PARAM_TEXT);

        $mform->addElement('text', 'formula', get_string('formula', 'qtype_labcalc'), ['size' => 60]);
        $mform->setType('formula', PARAM_RAW_TRIMMED);
        $mform->addRule('formula', null, 'required', null, 'client');
        $mform->addHelpButton('formula', 'formula', 'qtype_labcalc');

        // Grading.
        $mform->addElement('header', 'gradinghdr', get_string('grading', 'qtype_labcalc'));
        $mform->setExpanded('gradinghdr', true);

        $mform->addElement('select', 'tolerancetype', get_string('tolerancetype', 'qtype_labcalc'), [
            evaluator::TOLERANCE_RELATIVE => get_string('tolerancerelative', 'qtype_labcalc'),
            evaluator::TOLERANCE_ABSOLUTE => get_string('toleranceabsolute', 'qtype_labcalc'),
        ]);
        $mform->setDefault('tolerancetype', evaluator::TOLERANCE_RELATIVE);

        $mform->addElement('text', 'tolerance', get_string('tolerance', 'qtype_labcalc'), ['size' => 10]);
        $mform->setType('tolerance', PARAM_RAW_TRIMMED);
        $mform->setDefault('tolerance', '1');
        $mform->addRule('tolerance', null, 'required', null, 'client');
        $mform->addHelpButton('tolerance', 'tolerance', 'qtype_labcalc');

        $mform->addElement('text', 'partialtolerance', get_string('partialtolerance', 'qtype_labcalc'), ['size' => 10]);
        $mform->setType('partialtolerance', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('partialtolerance', 'partialtolerance', 'qtype_labcalc');

        $fractions = question_bank::fraction_options();
        unset($fractions['0.0'], $fractions['1.0']);
        $mform->addElement('select', 'partialfraction', get_string('partialfraction', 'qtype_labcalc'), $fractions);
        $mform->setDefault('partialfraction', '0.5');
        $mform->hideIf('partialfraction', 'partialtolerance', 'eq', '');

        $mform->addElement('select', 'displaysigfigs', get_string('displaysigfigs', 'qtype_labcalc'),
                array_combine(range(1, 10), range(1, 10)));
        $mform->setDefault('displaysigfigs', 4);

        $mform->addElement('advcheckbox', 'showexpected', get_string('showexpected', 'qtype_labcalc'));
        $mform->setDefault('showexpected', 1);
        $mform->addHelpButton('showexpected', 'showexpected', 'qtype_labcalc');

        $mform->addElement('advcheckbox', 'showformula', get_string('showformula', 'qtype_labcalc'));
        $mform->setDefault('showformula', 0);

        $this->add_combined_feedback_fields(false);
        $this->add_interactive_settings(false, false);
    }

    protected function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);
        $question = $this->data_preprocessing_combined_feedback($question, false);
        $question = $this->data_preprocessing_hints($question, false, false);

        if (empty($question->options)) {
            return $question;
        }
        $o = $question->options;
        foreach (['formula', 'resultlabel', 'resultunit', 'tolerancetype',
                'partialfraction', 'displaysigfigs', 'showexpected', 'showformula'] as $field) {
            if (isset($o->$field)) {
                $question->$field = $o->$field;
            }
        }
        $question->tolerance = self::localise($o->tolerance ?? '');
        $question->partialtolerance = self::localise($o->partialtolerance ?? '');

        $i = 0;
        foreach ($o->vars ?? [] as $var) {
            $question->varname[$i] = $var->name;
            $question->varlabel[$i] = $var->label;
            $question->varunit[$i] = $var->unit;
            $question->varmin[$i] = self::localise($var->rangemin);
            $question->varmax[$i] = self::localise($var->rangemax);
            $question->varsample[$i] = self::localise($var->samplevalue);
            $i++;
        }
        return $question;
    }

    /**
     * Show a stored number with the local decimal separator.
     *
     * @param string|null $value
     * @return string
     */
    protected static function localise($value): string {
        if ($value === null || $value === '') {
            return '';
        }
        return str_replace('.', get_string('decsep', 'langconfig'), (string) $value);
    }

    /**
     * Parse a number from the form.
     *
     * @param string|null $value
     * @return float|null|false null if blank, false if invalid.
     */
    protected static function parse($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $number = unformat_float($value, true);
        return ($number === false || $number === null) ? false : (float) $number;
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Experimental values.
        $samples = [];
        foreach ($data['varname'] ?? [] as $i => $name) {
            $name = core_text::strtolower(trim($name));
            $other = trim($data['varlabel'][$i] ?? '') . trim($data['varunit'][$i] ?? '') .
                    trim($data['varmin'][$i] ?? '') . trim($data['varmax'][$i] ?? '') . trim($data['varsample'][$i] ?? '');
            if ($name === '') {
                if ($other !== '') {
                    $errors["varname[$i]"] = get_string('error_varnamerequired', 'qtype_labcalc');
                }
                continue;
            }
            if (!evaluator::is_valid_name($name)) {
                $errors["varname[$i]"] = get_string('error_varnameinvalid', 'qtype_labcalc');
                continue;
            }
            if (array_key_exists($name, $samples)) {
                $errors["varname[$i]"] = get_string('error_varnameduplicate', 'qtype_labcalc', $name);
                continue;
            }

            $min = self::parse($data['varmin'][$i] ?? '');
            $max = self::parse($data['varmax'][$i] ?? '');
            $sample = self::parse($data['varsample'][$i] ?? '');
            if ($min === false || $max === false) {
                $errors["varrange[$i]"] = get_string('error_notnumeric', 'qtype_labcalc');
            } else if ($min !== null && $max !== null && $min > $max) {
                $errors["varrange[$i]"] = get_string('error_minmax', 'qtype_labcalc');
            }
            if ($sample === false) {
                $errors["varsample[$i]"] = get_string('error_notnumeric', 'qtype_labcalc');
                $sample = null;
            } else if ($sample !== null && (($min !== null && $min !== false && $sample < $min) ||
                    ($max !== null && $max !== false && $sample > $max))) {
                $errors["varsample[$i]"] = get_string('error_sampleoutofrange', 'qtype_labcalc');
            }

            // Value used to test the formula.
            if ($sample !== null) {
                $samples[$name] = $sample;
            } else if (is_float($min) && is_float($max)) {
                $samples[$name] = ($min + $max) / 2;
            } else if (is_float($min)) {
                $samples[$name] = $min;
            } else if (is_float($max)) {
                $samples[$name] = $max;
            } else {
                $samples[$name] = 1.0;
            }
        }
        if (!$samples && empty($errors['varname[0]'])) {
            $errors['varname[0]'] = get_string('error_atleastonevar', 'qtype_labcalc');
        }

        // Formula.
        $formula = trim($data['formula'] ?? '');
        if ($formula !== '' && empty($errors['formula'])) {
            $used = evaluator::find_variables($formula);
            $unknown = array_diff($used, array_keys($samples));
            if ($unknown) {
                $errors['formula'] = get_string('error_unknownvars', 'qtype_labcalc',
                        '{' . implode('}, {', $unknown) . '}');
            } else if ($samples && !$used) {
                $errors['formula'] = get_string('error_novarsinformula', 'qtype_labcalc');
            } else if ($samples) {
                try {
                    evaluator::evaluate($formula, $samples);
                } catch (evaluation_exception $e) {
                    $errors['formula'] = get_string('error_formulainvalid', 'qtype_labcalc', $e->getMessage());
                }
            }
        }

        // Tolerances.
        $tolerance = self::parse($data['tolerance'] ?? '');
        if ($tolerance === false || $tolerance === null || $tolerance < 0) {
            $errors['tolerance'] = get_string('error_tolerance', 'qtype_labcalc');
        }
        $partial = self::parse($data['partialtolerance'] ?? '');
        if ($partial === false || ($partial !== null && $partial < 0)) {
            $errors['partialtolerance'] = get_string('error_tolerance', 'qtype_labcalc');
        } else if ($partial !== null && is_float($tolerance) && $partial <= $tolerance) {
            $errors['partialtolerance'] = get_string('error_partialtolerance', 'qtype_labcalc');
        }

        return $errors;
    }

    public function qtype() {
        return 'labcalc';
    }
}
