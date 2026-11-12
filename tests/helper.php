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

use qtype_labcalc\evaluator;
use qtype_labcalc\groupdata;
use qtype_labcalc\sigfigs;

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

    /** @var string Group data used by the test question. */
    const GROUPDATA = "group, mass, volume\nGroup A, 26.4, 9.8\nGroup B, 27.1, 10.0";

    public function get_test_questions() {
        return ['density'];
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
            (object) ['name' => 'density', 'label' => 'Density', 'unit' => 'g/mL', 'formula' => '{mass} / {volume}',
                    'tolerance' => 1.0, 'tolerancetype' => evaluator::TOLERANCE_RELATIVE,
                    'partialtolerance' => 5.0, 'partialfraction' => 0.5,
                    'sigfigsmode' => sigfigs::MODE_AUTO, 'sigfigs' => 3, 'sigfigsslack' => 0, 'sigfigspenalty' => 0.25,
                    'weight' => 2.0, 'displaysigfigs' => 4],
            (object) ['name' => 'error', 'label' => 'Percent error', 'unit' => '%',
                    'formula' => '100 * abs({density} - 2.70) / 2.70',
                    'tolerance' => 0.5, 'tolerancetype' => evaluator::TOLERANCE_ABSOLUTE,
                    'partialtolerance' => null, 'partialfraction' => 0.5,
                    'sigfigsmode' => sigfigs::MODE_FIXED, 'sigfigs' => 2, 'sigfigsslack' => 0, 'sigfigspenalty' => 0.5,
                    'weight' => 1.0, 'displaysigfigs' => 3],
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
}
