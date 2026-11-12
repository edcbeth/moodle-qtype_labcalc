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
 * Test helpers for the Lab calculation question type.
 *
 * @package    qtype_labcalc
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/labcalc/question.php');

/**
 * Test helper class for the Lab calculation question type.
 *
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_labcalc_test_helper extends question_test_helper {

    public function get_test_questions() {
        return ['density'];
    }

    /**
     * A density question: density = mass / volume, ±1%, partial credit within ±5%.
     *
     * @return qtype_labcalc_question
     */
    public function make_labcalc_question_density() {
        question_bank::load_question_definition_classes('labcalc');
        $q = new qtype_labcalc_question();
        test_question_maker::initialise_a_question($q);
        $q->name = 'Density of a metal block';
        $q->questiontext = 'Measure the mass and volume of the block, then calculate its density.';
        $q->generalfeedback = '';
        $q->qtype = question_bank::get_qtype('labcalc');
        $q->formula = '{mass} / {volume}';
        $q->resultlabel = 'Density';
        $q->resultunit = 'g/mL';
        $q->tolerance = 1.0;
        $q->tolerancetype = \qtype_labcalc\evaluator::TOLERANCE_RELATIVE;
        $q->partialtolerance = 5.0;
        $q->partialfraction = 0.5;
        $q->displaysigfigs = 3;
        $q->showexpected = true;
        $q->showformula = true;
        $q->vars = [
            (object) ['name' => 'mass', 'label' => 'Mass', 'unit' => 'g',
                    'rangemin' => 0.0, 'rangemax' => 500.0, 'samplevalue' => 27.0],
            (object) ['name' => 'volume', 'label' => 'Volume', 'unit' => 'mL',
                    'rangemin' => 0.1, 'rangemax' => null, 'samplevalue' => 10.0],
        ];
        test_question_maker::set_standard_combined_feedback_fields($q);
        return $q;
    }

    /**
     * Form data for creating a density question through the question generator.
     *
     * @return stdClass
     */
    public function get_labcalc_question_form_data_density() {
        $form = new stdClass();
        $form->name = 'Density of a metal block';
        $form->questiontext = ['text' => 'Measure the mass and volume of the block, then calculate its density.',
                'format' => FORMAT_HTML];
        $form->defaultmark = 1.0;
        $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->formula = '{mass} / {volume}';
        $form->resultlabel = 'Density';
        $form->resultunit = 'g/mL';
        $form->tolerance = '1';
        $form->tolerancetype = \qtype_labcalc\evaluator::TOLERANCE_RELATIVE;
        $form->partialtolerance = '5';
        $form->partialfraction = '0.5';
        $form->displaysigfigs = 3;
        $form->showexpected = 1;
        $form->showformula = 1;
        $form->varname = ['mass', 'volume'];
        $form->varlabel = ['Mass', 'Volume'];
        $form->varunit = ['g', 'mL'];
        $form->varmin = ['0', '0.1'];
        $form->varmax = ['500', ''];
        $form->varsample = ['27', '10'];
        test_question_maker::set_standard_combined_feedback_form_data($form);
        $form->penalty = '0.3333333';
        $form->hint = [];
        return $form;
    }
}
