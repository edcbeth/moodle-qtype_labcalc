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
 * Question type class for the Lab calculation question type.
 *
 * @package    qtype_labcalc
 * @copyright  2026 Edward Bethel <edcbeth@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qtype_labcalc\evaluator;
use qtype_labcalc\groupdata;
use qtype_labcalc\knownvalue;
use qtype_labcalc\sigfigs;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/type/labcalc/question.php');

/**
 * The Lab calculation question type.
 *
 * Students enter their experimental (measured) values and one or more values they calculated from
 * them. Each calculated value is checked by applying the instructor's formula to the student's own
 * data, optionally checking significant figures, and the measurements can be checked against values
 * the instructor recorded for the student's group.
 *
 * @copyright  2026 Edward Bethel <edcbeth@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_labcalc extends question_type {

    /** @var string[] Question-level option fields (other than combined feedback). */
    const OPTION_FIELDS = ['carryforward', 'showexpected', 'showformula', 'groupcheckmode', 'grouppenalty', 'groupdata'];

    /** @var string[] Fields stored per experimental value. */
    const VAR_FIELDS = ['name', 'label', 'unit', 'rangemin', 'rangemax', 'samplevalue', 'checktolerance'];

    /** @var string[] Fields stored per calculated value. */
    const RESULT_FIELDS = ['name', 'label', 'unit', 'formula', 'tolerance', 'tolerancetype', 'partialtolerance',
            'partialfraction', 'sigfigsmode', 'sigfigs', 'sigfigsslack', 'sigfigspenalty', 'weight', 'displaysigfigs',
            'knownmode', 'knownformula', 'knowncompare', 'accuracytolerance', 'accuracytolerancetype',
            'accuracypartialtolerance', 'accuracypartialfraction', 'accuracyweight'];

    /** @var array form field name => result field name. */
    const RESULT_FORM_FIELDS = [
        'resname' => 'name', 'reslabel' => 'label', 'resunit' => 'unit', 'resformula' => 'formula',
        'restolerance' => 'tolerance', 'restolerancetype' => 'tolerancetype',
        'respartialtolerance' => 'partialtolerance', 'respartialfraction' => 'partialfraction',
        'ressigfigsmode' => 'sigfigsmode', 'ressigfigs' => 'sigfigs', 'ressigfigsslack' => 'sigfigsslack',
        'ressigfigspenalty' => 'sigfigspenalty', 'resweight' => 'weight', 'resdisplaysigfigs' => 'displaysigfigs',
        'resknownmode' => 'knownmode', 'resknownformula' => 'knownformula', 'resknowncompare' => 'knowncompare',
        'resaccuracytolerance' => 'accuracytolerance', 'resaccuracytolerancetype' => 'accuracytolerancetype',
        'resaccuracypartialtolerance' => 'accuracypartialtolerance',
        'resaccuracypartialfraction' => 'accuracypartialfraction', 'resaccuracyweight' => 'accuracyweight',
    ];

    /** @var array form field name => variable field name. */
    const VAR_FORM_FIELDS = [
        'varname' => 'name', 'varlabel' => 'label', 'varunit' => 'unit', 'varmin' => 'rangemin',
        'varmax' => 'rangemax', 'varsample' => 'samplevalue', 'varchecktolerance' => 'checktolerance',
    ];

    public function get_question_options($question) {
        global $DB;
        parent::get_question_options($question);

        $options = $DB->get_record('qtype_labcalc_options', ['questionid' => $question->id]);
        if (!$options) {
            debugging('Failed to load qtype_labcalc_options for question id ' . $question->id);
            return false;
        }
        foreach ((array) $options as $field => $value) {
            $question->options->$field = $value;
        }
        $question->options->vars = $DB->get_records('qtype_labcalc_vars',
                ['questionid' => $question->id], 'sortorder ASC, id ASC');
        $question->options->results = $DB->get_records('qtype_labcalc_results',
                ['questionid' => $question->id], 'sortorder ASC, id ASC');
        return true;
    }

    public function save_question_options($formdata) {
        global $DB;
        $context = $formdata->context;

        $options = $DB->get_record('qtype_labcalc_options', ['questionid' => $formdata->id]);
        if (!$options) {
            $options = (object) [
                'questionid' => $formdata->id,
                'correctfeedback' => '',
                'partiallycorrectfeedback' => '',
                'incorrectfeedback' => '',
            ];
            $options->id = $DB->insert_record('qtype_labcalc_options', $options);
        }
        $options->carryforward = empty($formdata->carryforward) ? 0 : 1;
        $options->showexpected = empty($formdata->showexpected) ? 0 : 1;
        $options->showformula = empty($formdata->showformula) ? 0 : 1;
        $options->groupcheckmode = (int) ($formdata->groupcheckmode ?? groupdata::MODE_OFF);
        $options->grouppenalty = (float) ($formdata->grouppenalty ?? 0.5);
        $options->groupdata = trim($formdata->groupdata ?? '');
        $options = $this->save_combined_feedback_helper($options, $formdata, $context, false);
        $DB->update_record('qtype_labcalc_options', $options);

        // Experimental values.
        $DB->delete_records('qtype_labcalc_vars', ['questionid' => $formdata->id]);
        $sortorder = 0;
        foreach ($formdata->varname ?? [] as $i => $name) {
            $name = core_text::strtolower(trim($name));
            if ($name === '') {
                continue;
            }
            $DB->insert_record('qtype_labcalc_vars', (object) [
                'questionid' => $formdata->id,
                'name' => $name,
                'label' => trim($formdata->varlabel[$i] ?? ''),
                'unit' => trim($formdata->varunit[$i] ?? ''),
                'rangemin' => self::clean_number($formdata->varmin[$i] ?? '', ''),
                'rangemax' => self::clean_number($formdata->varmax[$i] ?? '', ''),
                'samplevalue' => self::clean_number($formdata->varsample[$i] ?? '', ''),
                'checktolerance' => trim($formdata->varchecktolerance[$i] ?? ''),
                'sortorder' => $sortorder++,
            ]);
        }

        // Calculated values.
        $DB->delete_records('qtype_labcalc_results', ['questionid' => $formdata->id]);
        $sortorder = 0;
        foreach ($formdata->resname ?? [] as $i => $name) {
            $name = core_text::strtolower(trim($name));
            if ($name === '') {
                continue;
            }
            $DB->insert_record('qtype_labcalc_results', (object) [
                'questionid' => $formdata->id,
                'name' => $name,
                'label' => trim($formdata->reslabel[$i] ?? ''),
                'unit' => trim($formdata->resunit[$i] ?? ''),
                'formula' => trim($formdata->resformula[$i] ?? ''),
                'tolerance' => self::clean_number($formdata->restolerance[$i] ?? '1', '1'),
                'tolerancetype' => (int) ($formdata->restolerancetype[$i] ?? evaluator::TOLERANCE_RELATIVE),
                'partialtolerance' => self::clean_number($formdata->respartialtolerance[$i] ?? '', ''),
                'partialfraction' => (float) ($formdata->respartialfraction[$i] ?? 0.5),
                'sigfigsmode' => (int) ($formdata->ressigfigsmode[$i] ?? sigfigs::MODE_OFF),
                'sigfigs' => (int) ($formdata->ressigfigs[$i] ?? 3),
                'sigfigsslack' => (int) ($formdata->ressigfigsslack[$i] ?? 0),
                'sigfigspenalty' => (float) ($formdata->ressigfigspenalty[$i] ?? 0.25),
                'weight' => (float) self::clean_number($formdata->resweight[$i] ?? '1', '1'),
                'displaysigfigs' => (int) ($formdata->resdisplaysigfigs[$i] ?? 4),
                'knownmode' => (int) ($formdata->resknownmode[$i] ?? knownvalue::MODE_OFF),
                'knownformula' => trim($formdata->resknownformula[$i] ?? ''),
                'knowncompare' => (int) ($formdata->resknowncompare[$i] ?? knownvalue::COMPARE_REPORTED),
                'accuracytolerance' => self::clean_number($formdata->resaccuracytolerance[$i] ?? '5', '5'),
                'accuracytolerancetype' => (int) ($formdata->resaccuracytolerancetype[$i] ?? evaluator::TOLERANCE_RELATIVE),
                'accuracypartialtolerance' => self::clean_number($formdata->resaccuracypartialtolerance[$i] ?? '', ''),
                'accuracypartialfraction' => (float) ($formdata->resaccuracypartialfraction[$i] ?? 0.5),
                'accuracyweight' => (float) ($formdata->resaccuracyweight[$i] ?? 0.25),
                'sortorder' => $sortorder++,
            ]);
        }

        $this->save_hints($formdata);
        return true;
    }

    /**
     * Normalise a (possibly localised) number typed in the form to a PHP-format string.
     *
     * @param mixed $value
     * @param string $default returned when blank or not a number.
     * @return string
     */
    public static function clean_number($value, string $default): string {
        $value = trim((string) $value);
        if ($value === '') {
            return $default;
        }
        $number = unformat_float($value, true);
        if ($number === false || $number === null) {
            return $default;
        }
        return (string) $number;
    }

    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);
        $o = $questiondata->options;
        $question->carryforward = (bool) $o->carryforward;
        $question->showexpected = (bool) $o->showexpected;
        $question->showformula = (bool) $o->showformula;
        $question->groupcheckmode = (int) $o->groupcheckmode;
        $question->grouppenalty = (float) $o->grouppenalty;
        $question->groupdata = (string) ($o->groupdata ?? '');

        $num = fn($v) => ($v === '' || $v === null) ? null : (float) $v;
        $question->vars = [];
        foreach ($o->vars as $var) {
            $question->vars[] = (object) [
                'name' => $var->name,
                'label' => $var->label,
                'unit' => $var->unit,
                'rangemin' => $num($var->rangemin),
                'rangemax' => $num($var->rangemax),
                'samplevalue' => $num($var->samplevalue),
                'checktolerance' => (string) ($var->checktolerance ?? ''),
            ];
        }
        $question->results = [];
        foreach ($o->results as $res) {
            $question->results[] = (object) [
                'name' => $res->name,
                'label' => $res->label,
                'unit' => $res->unit,
                'formula' => $res->formula,
                'tolerance' => (float) $res->tolerance,
                'tolerancetype' => (int) $res->tolerancetype,
                'partialtolerance' => $num($res->partialtolerance),
                'partialfraction' => (float) $res->partialfraction,
                'sigfigsmode' => (int) $res->sigfigsmode,
                'sigfigs' => (int) $res->sigfigs,
                'sigfigsslack' => (int) $res->sigfigsslack,
                'sigfigspenalty' => (float) $res->sigfigspenalty,
                'weight' => (float) $res->weight,
                'displaysigfigs' => (int) $res->displaysigfigs,
                'knownmode' => (int) ($res->knownmode ?? knownvalue::MODE_OFF),
                'knownformula' => trim((string) ($res->knownformula ?? '')),
                'knowncompare' => (int) ($res->knowncompare ?? knownvalue::COMPARE_REPORTED),
                'accuracytolerance' => (float) ($res->accuracytolerance ?? 5),
                'accuracytolerancetype' => (int) ($res->accuracytolerancetype ?? evaluator::TOLERANCE_RELATIVE),
                'accuracypartialtolerance' => $num($res->accuracypartialtolerance ?? ''),
                'accuracypartialfraction' => (float) ($res->accuracypartialfraction ?? 0.5),
                'accuracyweight' => (float) ($res->accuracyweight ?? 0.25),
            ];
        }
        $this->initialise_combined_feedback($question, $questiondata, false);
    }

    public function delete_question($questionid, $contextid) {
        global $DB;
        $DB->delete_records('qtype_labcalc_results', ['questionid' => $questionid]);
        $DB->delete_records('qtype_labcalc_vars', ['questionid' => $questionid]);
        $DB->delete_records('qtype_labcalc_options', ['questionid' => $questionid]);
        parent::delete_question($questionid, $contextid);
    }

    public function move_files($questionid, $oldcontextid, $newcontextid) {
        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_combined_feedback($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_hints($questionid, $oldcontextid, $newcontextid);
    }

    protected function delete_files($questionid, $contextid) {
        parent::delete_files($questionid, $contextid);
        $this->delete_files_in_combined_feedback($questionid, $contextid);
        $this->delete_files_in_hints($questionid, $contextid);
    }

    public function get_random_guess_score($questiondata) {
        return 0;
    }

    public function get_possible_responses($questiondata) {
        $parts = [];
        foreach (array_values($questiondata->options->results) as $i => $res) {
            $label = ($res->label !== '' ? $res->label : $res->name) . ': ';
            $sf = (int) $res->sigfigsmode !== sigfigs::MODE_OFF;
            $sfpenalty = 1 - (float) $res->sigfigspenalty;
            $responses = [];
            $responses['correct'] = new question_possible_response(
                    $label . get_string('status_correct', 'qtype_labcalc'), 1);
            if ($sf) {
                $responses['correct_badsf'] = new question_possible_response(
                        $label . get_string('status_correct_badsf', 'qtype_labcalc'), $sfpenalty);
            }
            if ($res->partialtolerance !== '' && $res->partialtolerance !== null) {
                $responses['partial'] = new question_possible_response(
                        $label . get_string('status_partial', 'qtype_labcalc'), (float) $res->partialfraction);
                if ($sf) {
                    $responses['partial_badsf'] = new question_possible_response(
                            $label . get_string('status_partial_badsf', 'qtype_labcalc'),
                            (float) $res->partialfraction * $sfpenalty);
                }
            }
            $responses['incorrect'] = new question_possible_response(
                    $label . get_string('status_incorrect', 'qtype_labcalc'), 0);
            $responses['invalid'] = new question_possible_response(
                    $label . get_string('status_invaliddata', 'qtype_labcalc'), 0);
            $responses[null] = question_possible_response::no_response();
            $parts[$i] = $responses;
        }
        return $parts;
    }

    public function export_to_xml($question, qformat_xml $format, $extra = null) {
        $o = $question->options;
        $out = '';
        foreach (self::OPTION_FIELDS as $field) {
            $out .= "    <{$field}>" . $format->xml_escape((string) ($o->$field ?? '')) . "</{$field}>\n";
        }
        $out .= $format->write_combined_feedback($o, $question->id, $question->contextid);
        foreach ($o->vars as $var) {
            $out .= "    <variable>\n";
            foreach (self::VAR_FIELDS as $field) {
                $out .= "      <{$field}>" . $format->xml_escape((string) ($var->$field ?? '')) . "</{$field}>\n";
            }
            $out .= "    </variable>\n";
        }
        foreach ($o->results as $res) {
            $out .= "    <result>\n";
            foreach (self::RESULT_FIELDS as $field) {
                $out .= "      <{$field}>" . $format->xml_escape((string) $res->$field) . "</{$field}>\n";
            }
            $out .= "    </result>\n";
        }
        return $out;
    }

    public function import_from_xml($data, $question, qformat_xml $format, $extra = null) {
        if (!isset($data['@']['type']) || $data['@']['type'] != $this->name()) {
            return false;
        }
        $qo = $format->import_headers($data);
        $qo->qtype = $this->name();

        $defaults = ['carryforward' => 1, 'showexpected' => 1, 'showformula' => 0,
                'groupcheckmode' => groupdata::MODE_OFF, 'grouppenalty' => 0.5, 'groupdata' => ''];
        foreach (self::OPTION_FIELDS as $field) {
            $qo->$field = $format->getpath($data, ['#', $field, 0, '#'], $defaults[$field], true);
        }
        $format->import_combined_feedback($qo, $data, false);

        foreach (self::VAR_FORM_FIELDS as $formfield => $field) {
            $qo->$formfield = [];
        }
        foreach ($format->getpath($data, ['#', 'variable'], []) as $i => $var) {
            foreach (self::VAR_FORM_FIELDS as $formfield => $field) {
                // Files from version 1.0 used minvalue / maxvalue.
                $legacy = ['rangemin' => 'minvalue', 'rangemax' => 'maxvalue'][$field] ?? $field;
                $value = $format->getpath($var, ['#', $field, 0, '#'], null, true);
                if ($value === null) {
                    $value = $format->getpath($var, ['#', $legacy, 0, '#'], '', true);
                }
                $qo->{$formfield}[$i] = $value;
            }
        }

        $resultdefaults = ['tolerance' => '1', 'tolerancetype' => evaluator::TOLERANCE_RELATIVE,
                'partialfraction' => 0.5, 'sigfigsmode' => sigfigs::MODE_OFF, 'sigfigs' => 3, 'sigfigsslack' => 0,
                'sigfigspenalty' => 0.25, 'weight' => 1, 'displaysigfigs' => 4,
                'knownmode' => knownvalue::MODE_OFF, 'knowncompare' => knownvalue::COMPARE_REPORTED,
                'accuracytolerance' => '5', 'accuracytolerancetype' => evaluator::TOLERANCE_RELATIVE,
                'accuracypartialfraction' => 0.5, 'accuracyweight' => 0.25];
        foreach (self::RESULT_FORM_FIELDS as $formfield => $field) {
            $qo->$formfield = [];
        }
        $results = $format->getpath($data, ['#', 'result'], []);
        if ($results) {
            foreach ($results as $i => $res) {
                foreach (self::RESULT_FORM_FIELDS as $formfield => $field) {
                    $qo->{$formfield}[$i] = $format->getpath($res, ['#', $field, 0, '#'],
                            $resultdefaults[$field] ?? '', true);
                }
            }
        } else if (($formula = $format->getpath($data, ['#', 'formula', 0, '#'], '', true)) !== '') {
            // A file exported from version 1.0, with a single calculated value.
            $qo->resname[0] = in_array('result', $qo->varname) ? 'result2' : 'result';
            $qo->resformula[0] = $formula;
            $qo->reslabel[0] = $format->getpath($data, ['#', 'resultlabel', 0, '#'], '', true);
            $qo->resunit[0] = $format->getpath($data, ['#', 'resultunit', 0, '#'], '', true);
            foreach (['tolerance', 'tolerancetype', 'partialtolerance', 'partialfraction', 'displaysigfigs'] as $field) {
                $qo->{'res' . $field}[0] = $format->getpath($data, ['#', $field, 0, '#'],
                        $resultdefaults[$field] ?? '', true);
            }
            foreach (self::RESULT_FORM_FIELDS as $formfield => $field) {
                if (!isset($qo->{$formfield}[0])) {
                    $qo->{$formfield}[0] = $resultdefaults[$field] ?? '';
                }
            }
        }

        $format->import_hints($qo, $data, false, false, $format->get_format($qo->questiontextformat));
        return $qo;
    }
}
