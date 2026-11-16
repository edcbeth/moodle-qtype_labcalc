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
 * @copyright  2026 Edward Bethel <edcbeth@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qtype_labcalc\evaluator;
use qtype_labcalc\groupdata;
use qtype_labcalc\knownvalue;
use qtype_labcalc\sigfigs;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/labcalc/question.php');

/**
 * Test helper class for the Lab calculation question type.
 *
 * @copyright  2026 Edward Bethel <edcbeth@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_labcalc_test_helper extends question_test_helper {

    /** @var string Group data used by the test question. */
    const GROUPDATA = "group, mass, volume\nGroup A, 26.4, 9.8\nGroup B, 27.1, 10.0";

    /** @var array Settings for a calculated value that is not compared with a known value. */
    const NO_KNOWN = ['knownmode' => knownvalue::MODE_OFF, 'knownformula' => '', 'knowncompare' => knownvalue::COMPARE_REPORTED,
            'accuracytolerance' => 5.0, 'accuracytolerancetype' => evaluator::TOLERANCE_RELATIVE,
            'accuracypartialtolerance' => null, 'accuracypartialfraction' => 0.5, 'accuracyweight' => 0.25];

    public function get_test_questions() {
        return ['density', 'accuracy'];
    }

    /**
     * A density question with two calculated values:
     *  - density = {mass} / {volume}, ±1%, partial within 5%, automatic sig figs, weight 2;
     *  - error = 100 * abs({density} - 2.70) / 2.70, ±0.5 absolute, 2 sig figs, weight 1.
     * Group data checks with a 50% penalty.
     *
     * @return qtype_labcalc_question
     */
    public function make_labcalc_question_density() {
        question_bank::load_question_definition_classes('labcalc');
        $q = new qtype_labcalc_question();
        test_question_maker::initialise_a_question($q);
        $q->name = 'Density of an aluminium block';
        $q->questiontext = 'Measure the mass and volume of the block. Calculate its density and the percent error.';
        $q->generalfeedback = '';
        $q->qtype = question_bank::get_qtype('labcalc');
        $q->carryforward = true;
        $q->showexpected = true;
        $q->showformula = true;
        $q->groupcheckmode = groupdata::MODE_PENALTY;
        $q->grouppenalty = 0.5;
        $q->groupdata = self::GROUPDATA;
        $q->vars = [
            (object) ['name' => 'mass', 'label' => 'Mass', 'unit' => 'g',
                    'rangemin' => 0.0, 'rangemax' => 500.0, 'samplevalue' => 27.1, 'checktolerance' => '2%'],
            (object) ['name' => 'volume', 'label' => 'Volume', 'unit' => 'mL',
                    'rangemin' => 0.1, 'rangemax' => null, 'samplevalue' => 10.0, 'checktolerance' => '0.2'],
        ];
        $q->results = [
            (object) (['name' => 'density', 'label' => 'Density', 'unit' => 'g/mL', 'formula' => '{mass} / {volume}',
                    'tolerance' => 1.0, 'tolerancetype' => evaluator::TOLERANCE_RELATIVE,
                    'partialtolerance' => 5.0, 'partialfraction' => 0.5,
                    'sigfigsmode' => sigfigs::MODE_AUTO, 'sigfigs' => 3, 'sigfigsslack' => 0, 'sigfigspenalty' => 0.25,
                    'weight' => 2.0, 'displaysigfigs' => 4] + self::NO_KNOWN),
            (object) (['name' => 'error', 'label' => 'Percent error', 'unit' => '%',
                    'formula' => '100 * abs({density} - 2.70) / 2.70',
                    'tolerance' => 0.5, 'tolerancetype' => evaluator::TOLERANCE_ABSOLUTE,
                    'partialtolerance' => null, 'partialfraction' => 0.5,
                    'sigfigsmode' => sigfigs::MODE_FIXED, 'sigfigs' => 2, 'sigfigsslack' => 0, 'sigfigspenalty' => 0.5,
                    'weight' => 1.0, 'displaysigfigs' => 3] + self::NO_KNOWN),
        ];
        test_question_maker::set_standard_combined_feedback_fields($q);
        return $q;
    }

    /**
     * Form data for creating the density question through the question generator.
     *
     * @return stdClass
     */
    public function get_labcalc_question_form_data_density() {
        $form = new stdClass();
        $form->name = 'Density of an aluminium block';
        $form->questiontext = ['text' => 'Measure the mass and volume of the block. Calculate its density and the percent error.',
                'format' => FORMAT_HTML];
        $form->defaultmark = 1.0;
        $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->carryforward = 1;
        $form->showexpected = 1;
        $form->showformula = 1;
        $form->groupcheckmode = groupdata::MODE_PENALTY;
        $form->grouppenalty = '0.5';
        $form->groupdata = self::GROUPDATA;
        $form->varname = ['mass', 'volume'];
        $form->varlabel = ['Mass', 'Volume'];
        $form->varunit = ['g', 'mL'];
        $form->varmin = ['0', '0.1'];
        $form->varmax = ['500', ''];
        $form->varsample = ['27.1', '10.0'];
        $form->varchecktolerance = ['2%', '0.2'];
        $form->resname = ['density', 'error'];
        $form->reslabel = ['Density', 'Percent error'];
        $form->resunit = ['g/mL', '%'];
        $form->resformula = ['{mass} / {volume}', '100 * abs({density} - 2.70) / 2.70'];
        $form->restolerance = ['1', '0.5'];
        $form->restolerancetype = [evaluator::TOLERANCE_RELATIVE, evaluator::TOLERANCE_ABSOLUTE];
        $form->respartialtolerance = ['5', ''];
        $form->respartialfraction = ['0.5', '0.5'];
        $form->ressigfigsmode = [sigfigs::MODE_AUTO, sigfigs::MODE_FIXED];
        $form->ressigfigs = [3, 2];
        $form->ressigfigsslack = [0, 0];
        $form->ressigfigspenalty = ['0.25', '0.5'];
        $form->resweight = ['2', '1'];
        $form->resdisplaysigfigs = [4, 3];
        test_question_maker::set_standard_combined_feedback_form_data($form);
        $form->penalty = '0.3333333';
        $form->hint = [];
        return $form;
    }
    /**
     * A density question compared with the known density of aluminium, 2.70 g/mL:
     *  - density = {mass} / {volume}, ±1%, weight 1; known value 2.70, graded: 25% of its mark for
     *    accuracy, full within ±2%, half within ±5%;
     *  - error = 100 * abs({density} - {density_known}) / {density_known}, ±0.5 absolute, weight 1.
     * Group B was given a copper block, so its known density is 8.96.
     *
     * @return qtype_labcalc_question
     */
    public function make_labcalc_question_accuracy() {
        $q = $this->make_labcalc_question_density();
        $q->name = 'Density of a metal block, compared with the known value';
        $q->groupcheckmode = groupdata::MODE_OFF;
        $q->groupdata = "group, density_known\nGroup B, 8.96";
        $q->results[0] = (object) (['name' => 'density', 'label' => 'Density', 'unit' => 'g/mL',
                'formula' => '{mass} / {volume}', 'tolerance' => 1.0, 'tolerancetype' => evaluator::TOLERANCE_RELATIVE,
                'partialtolerance' => null, 'partialfraction' => 0.5, 'sigfigsmode' => sigfigs::MODE_OFF, 'sigfigs' => 3,
                'sigfigsslack' => 0, 'sigfigspenalty' => 0.25, 'weight' => 1.0, 'displaysigfigs' => 3,
                'knownmode' => knownvalue::MODE_GRADED, 'knownformula' => '2.70', 'knowncompare' => knownvalue::COMPARE_REPORTED,
                'accuracytolerance' => 2.0, 'accuracytolerancetype' => evaluator::TOLERANCE_RELATIVE,
                'accuracypartialtolerance' => 5.0, 'accuracypartialfraction' => 0.5, 'accuracyweight' => 0.25]);
        $q->results[1] = (object) (['name' => 'error', 'label' => 'Percent error', 'unit' => '%',
                'formula' => '100 * abs({density} - {density_known}) / {density_known}',
                'tolerance' => 0.5, 'tolerancetype' => evaluator::TOLERANCE_ABSOLUTE,
                'partialtolerance' => null, 'partialfraction' => 0.5, 'sigfigsmode' => sigfigs::MODE_OFF, 'sigfigs' => 2,
                'sigfigsslack' => 0, 'sigfigspenalty' => 0.5, 'weight' => 1.0, 'displaysigfigs' => 3] + self::NO_KNOWN);
        return $q;
    }

    /**
     * Form data for creating the accuracy question through the question generator.
     *
     * @return stdClass
     */
    public function get_labcalc_question_form_data_accuracy() {
        $form = $this->get_labcalc_question_form_data_density();
        $form->name = 'Density of a metal block, compared with the known value';
        $form->groupcheckmode = groupdata::MODE_OFF;
        $form->groupdata = "group, density_known\nGroup B, 8.96";
        $form->resformula = ['{mass} / {volume}', '100 * abs({density} - {density_known}) / {density_known}'];
        $form->respartialtolerance = ['', ''];
        $form->ressigfigsmode = [sigfigs::MODE_OFF, sigfigs::MODE_OFF];
        $form->resweight = ['1', '1'];
        $form->resknownmode = [knownvalue::MODE_GRADED, knownvalue::MODE_OFF];
        $form->resknownformula = ['2.70', ''];
        $form->resknowncompare = [knownvalue::COMPARE_REPORTED, knownvalue::COMPARE_REPORTED];
        $form->resaccuracytolerance = ['2', '5'];
        $form->resaccuracytolerancetype = [evaluator::TOLERANCE_RELATIVE, evaluator::TOLERANCE_RELATIVE];
        $form->resaccuracypartialtolerance = ['5', ''];
        $form->resaccuracypartialfraction = ['0.5', '0.5'];
        $form->resaccuracyweight = ['0.25', '0.25'];
        return $form;
    }
}
