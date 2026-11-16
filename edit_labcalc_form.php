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
 * Lab calculation question editing form.
 *
 * @copyright  2026 Edward Bethel <edcbeth@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_labcalc_edit_form extends question_edit_form {

    /** @var int Number of blank experimental value slots shown on a new question. */
    const VARS_AT_START = 2;

    /** @var int Number of blank calculated value slots shown on a new question. */
    const RESULTS_AT_START = 1;

    protected function definition_inner($mform) {
        $this->add_variable_fields($mform);
        $this->add_result_fields($mform);
        $this->add_group_fields($mform);

        $mform->addElement('header', 'displayhdr', get_string('feedbackoptions', 'qtype_labcalc'));
        $mform->addElement('advcheckbox', 'carryforward', get_string('carryforward', 'qtype_labcalc'));
        $mform->setDefault('carryforward', 1);
        $mform->addHelpButton('carryforward', 'carryforward', 'qtype_labcalc');
        $mform->addElement('advcheckbox', 'showexpected', get_string('showexpected', 'qtype_labcalc'));
        $mform->setDefault('showexpected', 1);
        $mform->addHelpButton('showexpected', 'showexpected', 'qtype_labcalc');
        $mform->addElement('advcheckbox', 'showformula', get_string('showformula', 'qtype_labcalc'));
        $mform->setDefault('showformula', 0);

        $this->add_combined_feedback_fields(false);
        $this->add_interactive_settings(false, false);
    }

    /**
     * Fields for the experimental values.
     *
     * @param MoodleQuickForm $mform
     */
    protected function add_variable_fields($mform) {
        $mform->addElement('header', 'varshdr', get_string('experimentalvalues', 'qtype_labcalc'));
        $mform->setExpanded('varshdr', true);
        $mform->addElement('static', 'varsintro', '', get_string('experimentalvalues_desc', 'qtype_labcalc'));

        $repeated = [];
        $repeated[] = $mform->createElement('text', 'varname',
                get_string('varnameno', 'qtype_labcalc', '{no}'), ['size' => 20, 'maxlength' => 40]);
        $repeated[] = $mform->createElement('text', 'varlabel', get_string('varlabel', 'qtype_labcalc'), ['size' => 40]);
        $repeated[] = $mform->createElement('text', 'varunit', get_string('varunit', 'qtype_labcalc'), ['size' => 10]);
        $repeated[] = $mform->createElement('group', 'varrange', get_string('varrange', 'qtype_labcalc'), [
            $mform->createElement('text', 'varmin', get_string('varmin', 'qtype_labcalc'),
                    ['size' => 10, 'placeholder' => get_string('varmin', 'qtype_labcalc')]),
            $mform->createElement('text', 'varmax', get_string('varmax', 'qtype_labcalc'),
                    ['size' => 10, 'placeholder' => get_string('varmax', 'qtype_labcalc')]),
        ], ' ', false);
        $repeated[] = $mform->createElement('text', 'varsample', get_string('varsample', 'qtype_labcalc'), ['size' => 10]);
        $repeated[] = $mform->createElement('text', 'varchecktolerance',
                get_string('varchecktolerance', 'qtype_labcalc'), ['size' => 10]);

        $options = [];
        foreach (array_keys(qtype_labcalc::VAR_FORM_FIELDS) as $field) {
            $options[$field]['type'] = PARAM_RAW_TRIMMED;
        }
        $options['varname']['helpbutton'] = ['varname', 'qtype_labcalc'];
        $options['varrange']['helpbutton'] = ['varrange', 'qtype_labcalc'];
        $options['varsample']['helpbutton'] = ['varsample', 'qtype_labcalc'];
        $options['varchecktolerance']['helpbutton'] = ['varchecktolerance', 'qtype_labcalc'];
        $options['varchecktolerance']['default'] = '1%';
        $options['varchecktolerance']['hideif'] = ['groupcheckmode', 'eq', groupdata::MODE_OFF];

        $count = max(self::VARS_AT_START, count($this->question->options->vars ?? []));
        $this->repeat_elements($repeated, $count, $options, 'novars', 'addvars', 1,
                get_string('addmorevars', 'qtype_labcalc'), true);
    }

    /**
     * Fields for the calculated values.
     *
     * @param MoodleQuickForm $mform
     */
    protected function add_result_fields($mform) {
        $mform->addElement('header', 'resultshdr', get_string('calculatedvalues', 'qtype_labcalc'));
        $mform->setExpanded('resultshdr', true);
        $mform->addElement('static', 'resultsintro', '', get_string('calculatedvalues_desc', 'qtype_labcalc'));

        $fractions = question_bank::fraction_options();
        unset($fractions['0.0'], $fractions['1.0']);
        $penalties = $fractions + ['1.0' => '100%'];
        $sigfigoptions = array_combine(range(1, 10), range(1, 10));

        $repeated = [];
        $repeated[] = $mform->createElement('text', 'resname',
                get_string('resnameno', 'qtype_labcalc', '{no}'), ['size' => 20, 'maxlength' => 40]);
        $repeated[] = $mform->createElement('text', 'reslabel', get_string('reslabel', 'qtype_labcalc'), ['size' => 40]);
        $repeated[] = $mform->createElement('text', 'resunit', get_string('resunit', 'qtype_labcalc'), ['size' => 10]);
        $repeated[] = $mform->createElement('text', 'resformula', get_string('formula', 'qtype_labcalc'), ['size' => 60]);
        $repeated[] = $mform->createElement('group', 'restolgroup', get_string('tolerance', 'qtype_labcalc'), [
            $mform->createElement('text', 'restolerance', get_string('tolerancevalue', 'qtype_labcalc'), ['size' => 8]),
            $mform->createElement('select', 'restolerancetype', get_string('tolerancetype', 'qtype_labcalc'), [
                evaluator::TOLERANCE_RELATIVE => get_string('tolerancerelative', 'qtype_labcalc'),
                evaluator::TOLERANCE_ABSOLUTE => get_string('toleranceabsolute', 'qtype_labcalc'),
            ]),
        ], ' ', false);
        $repeated[] = $mform->createElement('group', 'respartialgroup', get_string('partialtolerance', 'qtype_labcalc'), [
            $mform->createElement('text', 'respartialtolerance', get_string('partialwithin', 'qtype_labcalc'),
                    ['size' => 8, 'placeholder' => get_string('none')]),
            $mform->createElement('select', 'respartialfraction', get_string('partialearns', 'qtype_labcalc'), $fractions),
        ], ' ', false);
        $repeated[] = $mform->createElement('group', 'ressfgroup', get_string('sigfigsgrading', 'qtype_labcalc'), [
            $mform->createElement('select', 'ressigfigsmode', get_string('sigfigsmode', 'qtype_labcalc'), [
                sigfigs::MODE_OFF => get_string('sigfigsmode_off', 'qtype_labcalc'),
                sigfigs::MODE_FIXED => get_string('sigfigsmode_fixed', 'qtype_labcalc'),
                sigfigs::MODE_AUTO => get_string('sigfigsmode_auto', 'qtype_labcalc'),
            ]),
            $mform->createElement('select', 'ressigfigs', get_string('sigfigsrequired', 'qtype_labcalc'), $sigfigoptions),
            $mform->createElement('select', 'ressigfigsslack', get_string('sigfigsslack', 'qtype_labcalc'), [
                0 => get_string('sigfigsslack0', 'qtype_labcalc'),
                1 => get_string('sigfigsslack1', 'qtype_labcalc'),
                2 => get_string('sigfigsslack2', 'qtype_labcalc'),
            ]),
        ], ' ', false);
        $repeated[] = $mform->createElement('select', 'ressigfigspenalty',
                get_string('sigfigspenalty', 'qtype_labcalc'), $penalties);
        $repeated[] = $mform->createElement('text', 'resweight', get_string('weight', 'qtype_labcalc'), ['size' => 5]);
        $repeated[] = $mform->createElement('select', 'resdisplaysigfigs',
                get_string('displaysigfigs', 'qtype_labcalc'), $sigfigoptions);

        // Comparison with a known value.
        $repeated[] = $mform->createElement('select', 'resknownmode', get_string('knownmode', 'qtype_labcalc'), [
            knownvalue::MODE_OFF => get_string('knownmode_off', 'qtype_labcalc'),
            knownvalue::MODE_FEEDBACK => get_string('knownmode_feedback', 'qtype_labcalc'),
            knownvalue::MODE_GRADED => get_string('knownmode_graded', 'qtype_labcalc'),
        ]);
        $repeated[] = $mform->createElement('text', 'resknownformula', get_string('knownformula', 'qtype_labcalc'),
                ['size' => 40]);
        $repeated[] = $mform->createElement('select', 'resknowncompare', get_string('knowncompare', 'qtype_labcalc'), [
            knownvalue::COMPARE_REPORTED => get_string('knowncompare_reported', 'qtype_labcalc'),
            knownvalue::COMPARE_FROMDATA => get_string('knowncompare_fromdata', 'qtype_labcalc'),
        ]);
        $repeated[] = $mform->createElement('group', 'resaccgroup', get_string('accuracytolerance', 'qtype_labcalc'), [
            $mform->createElement('text', 'resaccuracytolerance', get_string('tolerancevalue', 'qtype_labcalc'),
                    ['size' => 8]),
            $mform->createElement('select', 'resaccuracytolerancetype', get_string('tolerancetype', 'qtype_labcalc'), [
                evaluator::TOLERANCE_RELATIVE => get_string('tolerancerelative', 'qtype_labcalc'),
                evaluator::TOLERANCE_ABSOLUTE => get_string('toleranceabsolute', 'qtype_labcalc'),
            ]),
        ], ' ', false);
        $repeated[] = $mform->createElement('group', 'resaccpartialgroup',
                get_string('accuracypartial', 'qtype_labcalc'), [
            $mform->createElement('text', 'resaccuracypartialtolerance', get_string('partialwithin', 'qtype_labcalc'),
                    ['size' => 8, 'placeholder' => get_string('none')]),
            $mform->createElement('select', 'resaccuracypartialfraction', get_string('partialearns', 'qtype_labcalc'),
                    $fractions),
        ], ' ', false);
        $repeated[] = $mform->createElement('select', 'resaccuracyweight',
                get_string('accuracyweight', 'qtype_labcalc'), $penalties);

        $options = [];
        foreach (['resname', 'reslabel', 'resunit', 'resformula', 'restolerance', 'respartialtolerance', 'resweight'] as $f) {
            $options[$f]['type'] = PARAM_RAW_TRIMMED;
        }
        $options['resname']['helpbutton'] = ['resname', 'qtype_labcalc'];
        $options['resformula']['helpbutton'] = ['formula', 'qtype_labcalc'];
        $options['restolgroup']['helpbutton'] = ['tolerance', 'qtype_labcalc'];
        $options['respartialgroup']['helpbutton'] = ['partialtolerance', 'qtype_labcalc'];
        $options['ressfgroup']['helpbutton'] = ['sigfigsgrading', 'qtype_labcalc'];
        $options['resweight']['helpbutton'] = ['weight', 'qtype_labcalc'];
        $options['restolerance']['default'] = '1';
        $options['restolerancetype']['default'] = evaluator::TOLERANCE_RELATIVE;
        $options['respartialfraction']['default'] = '0.5';
        $options['ressigfigsmode']['default'] = sigfigs::MODE_OFF;
        $options['ressigfigs']['default'] = 3;
        $options['ressigfigs']['disabledif'] = ['ressigfigsmode', 'neq', sigfigs::MODE_FIXED];
        $options['ressigfigsslack']['default'] = 0;
        $options['ressigfigsslack']['disabledif'] = ['ressigfigsmode', 'eq', sigfigs::MODE_OFF];
        $options['ressigfigspenalty']['default'] = '0.25';
        $options['ressigfigspenalty']['hideif'] = ['ressigfigsmode', 'eq', sigfigs::MODE_OFF];
        $options['resweight']['default'] = '1';
        $options['resdisplaysigfigs']['default'] = 4;
        foreach (['resknownformula', 'resaccuracytolerance', 'resaccuracypartialtolerance'] as $f) {
            $options[$f]['type'] = PARAM_RAW_TRIMMED;
        }
        $options['resknownmode']['default'] = knownvalue::MODE_OFF;
        $options['resknownmode']['helpbutton'] = ['knownmode', 'qtype_labcalc'];
        $options['resknownformula']['helpbutton'] = ['knownformula', 'qtype_labcalc'];
        $options['resknownformula']['hideif'] = ['resknownmode', 'eq', knownvalue::MODE_OFF];
        $options['resknowncompare']['default'] = knownvalue::COMPARE_REPORTED;
        $options['resknowncompare']['helpbutton'] = ['knowncompare', 'qtype_labcalc'];
        $options['resknowncompare']['hideif'] = ['resknownmode', 'eq', knownvalue::MODE_OFF];
        $options['resaccuracytolerance']['default'] = '5';
        $options['resaccuracytolerancetype']['default'] = evaluator::TOLERANCE_RELATIVE;
        $options['resaccgroup']['helpbutton'] = ['accuracytolerance', 'qtype_labcalc'];
        $options['resaccgroup']['hideif'] = ['resknownmode', 'neq', knownvalue::MODE_GRADED];
        $options['resaccuracypartialfraction']['default'] = '0.5';
        $options['resaccpartialgroup']['hideif'] = ['resknownmode', 'neq', knownvalue::MODE_GRADED];
        $options['resaccuracyweight']['default'] = '0.25';
        $options['resaccuracyweight']['helpbutton'] = ['accuracyweight', 'qtype_labcalc'];
        $options['resaccuracyweight']['hideif'] = ['resknownmode', 'neq', knownvalue::MODE_GRADED];

        $count = max(self::RESULTS_AT_START, count($this->question->options->results ?? []));
        $this->repeat_elements($repeated, $count, $options, 'noresults', 'addresults', 1,
                get_string('addmoreresults', 'qtype_labcalc'), true);
    }

    /**
     * Fields for checking measurements against group data.
     *
     * @param MoodleQuickForm $mform
     */
    protected function add_group_fields($mform) {
        $mform->addElement('header', 'grouphdr', get_string('groupdatachecks', 'qtype_labcalc'));
        if (!empty($this->question->options->groupdata)) {
            $mform->setExpanded('grouphdr', true);
        }

        $mform->addElement('select', 'groupcheckmode', get_string('groupcheckmode', 'qtype_labcalc'), [
            groupdata::MODE_OFF => get_string('groupcheckmode_off', 'qtype_labcalc'),
            groupdata::MODE_FEEDBACK => get_string('groupcheckmode_feedback', 'qtype_labcalc'),
            groupdata::MODE_PENALTY => get_string('groupcheckmode_penalty', 'qtype_labcalc'),
        ]);
        $mform->setDefault('groupcheckmode', groupdata::MODE_OFF);
        $mform->addHelpButton('groupcheckmode', 'groupcheckmode', 'qtype_labcalc');

        $fractions = question_bank::fraction_options();
        unset($fractions['0.0']);
        $mform->addElement('select', 'grouppenalty', get_string('grouppenalty', 'qtype_labcalc'), $fractions);
        $mform->setDefault('grouppenalty', '0.5');
        $mform->hideIf('grouppenalty', 'groupcheckmode', 'neq', groupdata::MODE_PENALTY);

        $mform->addElement('textarea', 'groupdata', get_string('groupdata', 'qtype_labcalc'),
                ['rows' => 8, 'cols' => 60, 'class' => 'text-monospace',
                 'placeholder' => "group, mass, volume\nGroup A, 26.4, 9.8\nGroup B, 27.1, 10.0"]);
        $mform->setType('groupdata', PARAM_RAW);
        $mform->addHelpButton('groupdata', 'groupdata', 'qtype_labcalc');
    }

    protected function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);
        $question = $this->data_preprocessing_combined_feedback($question, false);
        $question = $this->data_preprocessing_hints($question, false, false);

        if (empty($question->options)) {
            return $question;
        }
        $o = $question->options;
        foreach (qtype_labcalc::OPTION_FIELDS as $field) {
            if (isset($o->$field)) {
                $question->$field = $o->$field;
            }
        }

        $numeric = ['rangemin', 'rangemax', 'samplevalue', 'tolerance', 'partialtolerance', 'weight',
                'accuracytolerance', 'accuracypartialtolerance'];
        foreach (array_values($o->vars ?? []) as $i => $var) {
            foreach (qtype_labcalc::VAR_FORM_FIELDS as $formfield => $field) {
                $value = $var->$field ?? '';
                $question->{$formfield}[$i] = in_array($field, $numeric) ? self::localise($value) : $value;
                $this->clear_repeat_default($formfield, $i);
            }
        }
        foreach (array_values($o->results ?? []) as $i => $res) {
            foreach (qtype_labcalc::RESULT_FORM_FIELDS as $formfield => $field) {
                $value = $res->$field ?? '';
                if ($field === 'weight') {
                    $value = (string) (float) $value;
                }
                $question->{$formfield}[$i] = in_array($field, $numeric) ? self::localise($value) : $value;
                $this->clear_repeat_default($formfield, $i);
            }
        }
        return $question;
    }

    /**
     * Remove the default that repeat_elements() set for one repeated field, so the saved value is shown.
     *
     * Formslib stores repeat defaults as _defaultValues['name[0]'], but set_data() stores the loaded
     * values as _defaultValues['name'][0], and the former wins. Core question types work around this
     * the same way (see question_edit_form::data_preprocessing_answers()).
     *
     * @param string $formfield
     * @param int $i
     */
    protected function clear_repeat_default(string $formfield, int $i): void {
        unset($this->_form->_defaultValues["{$formfield}[{$i}]"]);
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
        $names = [];

        // Experimental values.
        $samples = [];
        foreach ($data['varname'] ?? [] as $i => $name) {
            $name = core_text::strtolower(trim($name));
            $other = '';
            foreach (['varlabel', 'varunit', 'varmin', 'varmax', 'varsample'] as $f) {
                $other .= trim($data[$f][$i] ?? '');
            }
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
            if (self::ends_with_known_suffix($name)) {
                $errors["varname[$i]"] = get_string('error_knownsuffix', 'qtype_labcalc');
                continue;
            }
            if (in_array($name, $names)) {
                $errors["varname[$i]"] = get_string('error_varnameduplicate', 'qtype_labcalc', $name);
                continue;
            }
            $names[] = $name;

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
            } else if ($sample !== null && ((is_float($min) && $sample < $min) || (is_float($max) && $sample > $max))) {
                $errors["varsample[$i]"] = get_string('error_sampleoutofrange', 'qtype_labcalc');
            }
            if (($data['groupcheckmode'] ?? 0) != groupdata::MODE_OFF &&
                    groupdata::parse_tolerance($data['varchecktolerance'][$i] ?? '') === false) {
                $errors["varchecktolerance[$i]"] = get_string('error_checktolerance', 'qtype_labcalc');
            }

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
        $varnames = array_keys($samples);

        // Calculated values. Each formula may use the experimental values, earlier results and their known values.
        $env = $samples;
        $knownnames = [];
        $knownfromgroups = [];
        $resultcount = 0;
        foreach ($data['resname'] ?? [] as $i => $name) {
            $name = core_text::strtolower(trim($name));
            $formula = trim($data['resformula'][$i] ?? '');
            if ($name === '') {
                if ($formula !== '' || trim($data['reslabel'][$i] ?? '') !== '') {
                    $errors["resname[$i]"] = get_string('error_resnamerequired', 'qtype_labcalc');
                }
                continue;
            }
            $resultcount++;
            if (!evaluator::is_valid_name($name)) {
                $errors["resname[$i]"] = get_string('error_varnameinvalid', 'qtype_labcalc');
                continue;
            }
            if (self::ends_with_known_suffix($name)) {
                $errors["resname[$i]"] = get_string('error_knownsuffix', 'qtype_labcalc');
                continue;
            }
            if (in_array($name, $names)) {
                $errors["resname[$i]"] = get_string('error_varnameduplicate', 'qtype_labcalc', $name);
                continue;
            }
            $names[] = $name;

            if ($formula === '') {
                $errors["resformula[$i]"] = get_string('error_formulaempty', 'qtype_labcalc');
            } else {
                $used = evaluator::find_variables($formula);
                $unknown = array_diff($used, array_keys($env));
                if (in_array($name, $used)) {
                    $errors["resformula[$i]"] = get_string('error_selfreference', 'qtype_labcalc');
                } else if ($unknown) {
                    $errors["resformula[$i]"] = get_string('error_unknownvars', 'qtype_labcalc',
                            '{' . implode('}, {', $unknown) . '}');
                } else if (!array_intersect($used, $varnames) && !array_intersect($used, array_keys($env))) {
                    $errors["resformula[$i]"] = get_string('error_novarsinformula', 'qtype_labcalc');
                } else {
                    try {
                        $env[$name] = evaluator::evaluate($formula, $env);
                    } catch (evaluation_exception $e) {
                        $errors["resformula[$i]"] = get_string('error_formulainvalid', 'qtype_labcalc', $e->getMessage());
                    }
                }
            }
            if (!isset($env[$name])) {
                $env[$name] = 1.0; // So later formulas can still be checked.
            }

            // Known value. It may use the experimental values and earlier calculated values (and their known values).
            $knownmode = (int) ($data['resknownmode'][$i] ?? knownvalue::MODE_OFF);
            if ($knownmode != knownvalue::MODE_OFF) {
                $knownnames[] = $name . knownvalue::SUFFIX;
                $knownformula = trim($data['resknownformula'][$i] ?? '');
                if ($knownformula === '') {
                    $knownfromgroups[$i] = $name . knownvalue::SUFFIX; // Must then come from the group data.
                } else {
                    $knownenv = array_diff_key($env, [$name => true]);
                    $unknown = array_diff(evaluator::find_variables($knownformula), array_keys($knownenv));
                    if ($unknown) {
                        $errors["resknownformula[$i]"] = get_string('error_unknownvars', 'qtype_labcalc',
                                '{' . implode('}, {', $unknown) . '}');
                    } else {
                        try {
                            $env[$name . knownvalue::SUFFIX] = evaluator::evaluate($knownformula, $knownenv);
                        } catch (evaluation_exception $e) {
                            $errors["resknownformula[$i]"] = get_string('error_formulainvalid', 'qtype_labcalc',
                                    $e->getMessage());
                        }
                    }
                }
                if ($knownmode == knownvalue::MODE_GRADED) {
                    $acc = self::parse($data['resaccuracytolerance'][$i] ?? '');
                    if ($acc === false || $acc === null || $acc < 0) {
                        $errors["resaccgroup[$i]"] = get_string('error_tolerance', 'qtype_labcalc');
                    }
                    $accpartial = self::parse($data['resaccuracypartialtolerance'][$i] ?? '');
                    if ($accpartial === false || ($accpartial !== null && $accpartial < 0)) {
                        $errors["resaccpartialgroup[$i]"] = get_string('error_tolerance', 'qtype_labcalc');
                    } else if ($accpartial !== null && is_float($acc) && $accpartial <= $acc) {
                        $errors["resaccpartialgroup[$i]"] = get_string('error_partialtolerance', 'qtype_labcalc');
                    }
                }
            }

            $tolerance = self::parse($data['restolerance'][$i] ?? '');
            if ($tolerance === false || $tolerance === null || $tolerance < 0) {
                $errors["restolgroup[$i]"] = get_string('error_tolerance', 'qtype_labcalc');
            }
            $partial = self::parse($data['respartialtolerance'][$i] ?? '');
            if ($partial === false || ($partial !== null && $partial < 0)) {
                $errors["respartialgroup[$i]"] = get_string('error_tolerance', 'qtype_labcalc');
            } else if ($partial !== null && is_float($tolerance) && $partial <= $tolerance) {
                $errors["respartialgroup[$i]"] = get_string('error_partialtolerance', 'qtype_labcalc');
            }
            $weight = self::parse($data['resweight'][$i] ?? '1');
            if ($weight === false || ($weight !== null && $weight < 0)) {
                $errors["resweight[$i]"] = get_string('error_weight', 'qtype_labcalc');
            }
        }
        if (!$resultcount && empty($errors['resname[0]'])) {
            $errors['resname[0]'] = get_string('error_atleastoneresult', 'qtype_labcalc');
        }

        // Group data: measurements to check, and/or per-group known values.
        $groupcolumns = [];
        if (trim($data['groupdata'] ?? '') === '') {
            if (($data['groupcheckmode'] ?? 0) != groupdata::MODE_OFF) {
                $errors['groupdata'] = get_string('error_groupdatarequired', 'qtype_labcalc');
            }
        } else {
            [$rows, $problems] = groupdata::parse($data['groupdata'], array_merge($varnames, $knownnames));
            if ($problems) {
                $errors['groupdata'] = implode(' ', array_slice($problems, 0, 5));
            } else if (!$rows) {
                $errors['groupdata'] = get_string('groupdata_norows', 'qtype_labcalc');
            }
            foreach ($rows as $row) {
                $groupcolumns += array_flip(array_keys($row['values']));
            }
        }
        // A known value left blank must be given in the group data.
        foreach ($knownfromgroups as $i => $column) {
            if (!isset($groupcolumns[$column]) && empty($errors["resknownformula[$i]"])) {
                $errors["resknownformula[$i]"] = get_string('error_knownrequired', 'qtype_labcalc', $column);
            }
        }

        return $errors;
    }

    /**
     * Does a name end with the suffix reserved for known values?
     *
     * @param string $name
     * @return bool
     */
    protected static function ends_with_known_suffix(string $name): bool {
        return substr($name, -strlen(knownvalue::SUFFIX)) === knownvalue::SUFFIX;
    }

    public function qtype() {
        return 'labcalc';
    }
}
