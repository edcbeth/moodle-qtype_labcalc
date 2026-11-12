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
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/type/labcalc/question.php');

/**
 * The Lab calculation question type.
 *
 * Students enter their experimental (measured) values and the value they calculated from them.
 * The calculated value is checked by applying the instructor's formula to the student's own data.
 *
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_labcalc extends question_type {

    /** @var string[] Option fields stored in qtype_labcalc_options (other than feedback). */
    const OPTION_FIELDS = ['formula', 'resultlabel', 'resultunit', 'tolerance', 'tolerancetype',
            'partialtolerance', 'partialfraction', 'displaysigfigs', 'showexpected', 'showformula'];

    /** @var string[] Fields stored per experimental value in qtype_labcalc_vars. */
    const VAR_FIELDS = ['name', 'label', 'unit', 'rangemin', 'rangemax', 'samplevalue'];

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
        return true;
    }

    public function save_question_options($formdata) {
        global $DB;
        $context = $formdata->context;

        $options = $DB->get_record('qtype_labcalc_options', ['questionid' => $formdata->id]);
        if (!$options) {
            $options = new stdClass();
            $options->questionid = $formdata->id;
            $options->formula = '';
            $options->resultlabel = '';
            $options->resultunit = '';
            $options->tolerance = '1';
            $options->partialtolerance = '';
            $options->correctfeedback = '';
            $options->partiallycorrectfeedback = '';
            $options->incorrectfeedback = '';
            $options->id = $DB->insert_record('qtype_labcalc_options', $options);
        }

        $options->formula = trim($formdata->formula ?? '');
        $options->resultlabel = trim($formdata->resultlabel ?? '');
        $options->resultunit = trim($formdata->resultunit ?? '');
        $options->tolerance = self::clean_number($formdata->tolerance ?? '1', '1');
        $options->tolerancetype = (int) ($formdata->tolerancetype ?? qtype_labcalc\evaluator::TOLERANCE_RELATIVE);
        $options->partialtolerance = self::clean_number($formdata->partialtolerance ?? '', '');
        $options->partialfraction = (float) ($formdata->partialfraction ?? 0.5);
        $options->displaysigfigs = (int) ($formdata->displaysigfigs ?? 4);
        $options->showexpected = empty($formdata->showexpected) ? 0 : 1;
        $options->showformula = empty($formdata->showformula) ? 0 : 1;
        $options = $this->save_combined_feedback_helper($options, $formdata, $context, false);
        $DB->update_record('qtype_labcalc_options', $options);

        // Experimental values: replace them all.
        $DB->delete_records('qtype_labcalc_vars', ['questionid' => $formdata->id]);
        $sortorder = 0;
        foreach ($formdata->varname ?? [] as $i => $name) {
            $name = core_text::strtolower(trim($name));
            if ($name === '') {
                continue;
            }
            $var = new stdClass();
            $var->questionid = $formdata->id;
            $var->name = $name;
            $var->label = trim($formdata->varlabel[$i] ?? '');
            $var->unit = trim($formdata->varunit[$i] ?? '');
            $var->rangemin = self::clean_number($formdata->varmin[$i] ?? '', '');
            $var->rangemax = self::clean_number($formdata->varmax[$i] ?? '', '');
            $var->samplevalue = self::clean_number($formdata->varsample[$i] ?? '', '');
            $var->sortorder = $sortorder++;
            $DB->insert_record('qtype_labcalc_vars', $var);
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
        $question->formula = $o->formula;
        $question->resultlabel = $o->resultlabel;
        $question->resultunit = $o->resultunit;
        $question->tolerance = (float) $o->tolerance;
        $question->tolerancetype = (int) $o->tolerancetype;
        $question->partialtolerance = ($o->partialtolerance === '' || $o->partialtolerance === null)
                ? null : (float) $o->partialtolerance;
        $question->partialfraction = (float) $o->partialfraction;
        $question->displaysigfigs = (int) $o->displaysigfigs;
        $question->showexpected = (bool) $o->showexpected;
        $question->showformula = (bool) $o->showformula;
        $question->vars = [];
        foreach ($o->vars as $var) {
            $question->vars[] = (object) [
                'name' => $var->name,
                'label' => $var->label,
                'unit' => $var->unit,
                'rangemin' => $var->rangemin === '' ? null : (float) $var->rangemin,
                'rangemax' => $var->rangemax === '' ? null : (float) $var->rangemax,
                'samplevalue' => $var->samplevalue === '' ? null : (float) $var->samplevalue,
            ];
        }
        $this->initialise_combined_feedback($question, $questiondata, false);
    }

    public function delete_question($questionid, $contextid) {
        global $DB;
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
        $partial = isset($questiondata->options->partialtolerance) && $questiondata->options->partialtolerance !== '';
        $responses = [
            'correct' => new question_possible_response(get_string('status_correct', 'qtype_labcalc'), 1),
        ];
        if ($partial) {
            $responses['partial'] = new question_possible_response(get_string('status_partial', 'qtype_labcalc'),
                    (float) $questiondata->options->partialfraction);
        }
        $responses['incorrect'] = new question_possible_response(get_string('status_incorrect', 'qtype_labcalc'), 0);
        $responses['outofrange'] = new question_possible_response(get_string('status_outofrange', 'qtype_labcalc'), 0);
        $responses['formulaerror'] = new question_possible_response(get_string('status_formulaerror', 'qtype_labcalc'), 0);
        $responses[null] = question_possible_response::no_response();
        return [$questiondata->id => $responses];
    }

    public function export_to_xml($question, qformat_xml $format, $extra = null) {
        $o = $question->options;
        $out = '';
        foreach (self::OPTION_FIELDS as $field) {
            $out .= "    <{$field}>" . $format->xml_escape($o->$field) . "</{$field}>\n";
        }
        $out .= $format->write_combined_feedback($o, $question->id, $question->contextid);
        foreach ($o->vars as $var) {
            $out .= "    <variable>\n";
            foreach (self::VAR_FIELDS as $field) {
                $out .= "      <{$field}>" . $format->xml_escape($var->$field) . "</{$field}>\n";
            }
            $out .= "    </variable>\n";
        }
        return $out;
    }

    public function import_from_xml($data, $question, qformat_xml $format, $extra = null) {
        if (!isset($data['@']['type']) || $data['@']['type'] != $this->name()) {
            return false;
        }
        $qo = $format->import_headers($data);
        $qo->qtype = $this->name();

        $defaults = ['tolerance' => '1', 'tolerancetype' => qtype_labcalc\evaluator::TOLERANCE_RELATIVE,
                'partialfraction' => 0.5, 'displaysigfigs' => 4, 'showexpected' => 1, 'showformula' => 0];
        foreach (self::OPTION_FIELDS as $field) {
            $qo->$field = $format->getpath($data, ['#', $field, 0, '#'], $defaults[$field] ?? '', true);
        }
        $format->import_combined_feedback($qo, $data, false);

        $qo->varname = $qo->varlabel = $qo->varunit = $qo->varmin = $qo->varmax = $qo->varsample = [];
        foreach ($format->getpath($data, ['#', 'variable'], []) as $i => $var) {
            $qo->varname[$i] = $format->getpath($var, ['#', 'name', 0, '#'], '', true);
            $qo->varlabel[$i] = $format->getpath($var, ['#', 'label', 0, '#'], '', true);
            $qo->varunit[$i] = $format->getpath($var, ['#', 'unit', 0, '#'], '', true);
            $qo->varmin[$i] = $format->getpath($var, ['#', 'rangemin', 0, '#'], '', true);
            $qo->varmax[$i] = $format->getpath($var, ['#', 'rangemax', 0, '#'], '', true);
            $qo->varsample[$i] = $format->getpath($var, ['#', 'samplevalue', 0, '#'], '', true);
        }

        $format->import_hints($qo, $data, false, false, $format->get_format($qo->questiontextformat));
        return $qo;
    }
}
