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


namespace qtype_labcalc;

use question_state;
use question_classified_response;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');

/**
 * Unit tests for the Lab calculation question definition and evaluator.
 *
 * @package    qtype_labcalc
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_labcalc_question
 * @covers     \qtype_labcalc\evaluator
 */
final class question_test extends \advanced_testcase {

    /**
     * Get the density test question.
     *
     * @return \qtype_labcalc_question
     */
    protected function make(): \qtype_labcalc_question {
        return \test_question_maker::make_question('labcalc', 'density');
    }

    public function test_evaluator(): void {
        $this->assertEqualsWithDelta(2.5, evaluator::evaluate('{mass} / {volume}', ['mass' => 12.5, 'volume' => 5]), 1e-12);
        $this->assertEqualsWithDelta(16 + 4 + 2, evaluator::evaluate('{x}^2 + {x}**1 + sqrt({x})', ['x' => 4]), 1e-12);
        $this->assertEquals(['m2', 'm1', 'v'], evaluator::find_variables('({m2} - {M1}) / { v } + {m2}'));
        $this->assertTrue(evaluator::within_tolerance(1.01, 1.00, 0.01, evaluator::TOLERANCE_ABSOLUTE));
        $this->assertFalse(evaluator::within_tolerance(1.0101, 1.00, 0.01, evaluator::TOLERANCE_ABSOLUTE));
        $this->assertTrue(evaluator::within_tolerance(2.525, 2.5, 1, evaluator::TOLERANCE_RELATIVE));
        $this->assertFalse(evaluator::within_tolerance(2.526, 2.5, 1, evaluator::TOLERANCE_RELATIVE));
        $this->assertEquals('1.235e-4', evaluator::format_sigfigs(0.000123456, 4, false));
        $this->assertEquals('10.00', evaluator::format_sigfigs(9.9996, 4, false));
    }

    public function test_evaluator_errors(): void {
        foreach (['{a} / {b}' => ['a' => 1, 'b' => 0], 'sqrt({a})' => ['a' => -1],
                'mass / {a}' => ['a' => 1], 'x = {a}' => ['a' => 1], '' => []] as $formula => $values) {
            try {
                evaluator::evaluate($formula, $values);
                $this->fail('Expected an exception for ' . $formula);
            } catch (evaluation_exception $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function test_grading_uses_students_own_data(): void {
        $q = $this->make();
        // Student A: 27 g / 10 mL = 2.7.
        $this->assertEquals([1.0, question_state::$gradedright],
                $q->grade_response(['exp_mass' => '27', 'exp_volume' => '10', 'answer' => '2.7']));
        // Student B measured different values: 26.4 g / 9.8 mL = 2.6939.
        $this->assertEquals([1.0, question_state::$gradedright],
                $q->grade_response(['exp_mass' => '26.4', 'exp_volume' => '9.8', 'answer' => '2.69']));
        // Same answer, but it does not match student B's data within 1% → partial (within 5%).
        $this->assertEquals([0.5, question_state::$gradedpartial],
                $q->grade_response(['exp_mass' => '26.4', 'exp_volume' => '9.8', 'answer' => '2.75']));
        // Way off.
        $this->assertEquals([0.0, question_state::$gradedwrong],
                $q->grade_response(['exp_mass' => '26.4', 'exp_volume' => '9.8', 'answer' => '0.371']));
        // Scientific notation and powers of ten accepted.
        $this->assertEquals([1.0, question_state::$gradedright],
                $q->grade_response(['exp_mass' => '2.7e1', 'exp_volume' => '1.0x10^1', 'answer' => '2.70']));
    }

    public function test_out_of_range(): void {
        $q = $this->make();
        $response = ['exp_mass' => '27', 'exp_volume' => '0.01', 'answer' => '2700'];
        $this->assertFalse($q->is_complete_response($response));
        $this->assertTrue($q->is_gradable_response($response));
        $this->assertStringContainsString('Volume', $q->get_validation_error($response));
        $this->assertEquals([0.0, question_state::$gradedwrong], $q->grade_response($response));
    }

    public function test_completeness_and_validation(): void {
        $q = $this->make();
        $this->assertFalse($q->is_complete_response([]));
        $this->assertFalse($q->is_gradable_response([]));
        $this->assertFalse($q->is_complete_response(['exp_mass' => '27', 'answer' => '2.7']));
        $this->assertFalse($q->is_gradable_response(['exp_mass' => '27', 'exp_volume' => 'ten', 'answer' => '2.7']));
        $this->assertStringContainsString('Volume',
                $q->get_validation_error(['exp_mass' => '27', 'exp_volume' => 'ten', 'answer' => '2.7']));
        $this->assertTrue($q->is_complete_response(['exp_mass' => '27', 'exp_volume' => '10', 'answer' => '3']));
    }

    public function test_correct_response_and_summary(): void {
        $q = $this->make();
        $correct = $q->get_correct_response();
        $this->assertEquals([1.0, question_state::$gradedright], $q->grade_response($correct));
        $this->assertEquals('Mass = 27 g; Volume = 10 mL; Density = 2.7 g/mL',
                $q->summarise_response(['exp_mass' => '27', 'exp_volume' => '10', 'answer' => '2.7']));
        $this->assertTrue($q->is_same_response(['exp_mass' => '27'], ['exp_mass' => '27', 'answer' => '']));
        $this->assertFalse($q->is_same_response(['exp_mass' => '27'], ['exp_mass' => '28']));
    }

    public function test_classify_response(): void {
        $q = $this->make();
        $q->start_attempt(new \question_attempt_step(), 1);
        $this->assertEquals([$q->id => new question_classified_response('correct',
                'Mass = 27 g; Volume = 10 mL; Density = 2.7 g/mL', 1.0)],
                $q->classify_response(['exp_mass' => '27', 'exp_volume' => '10', 'answer' => '2.7']));
        $this->assertEquals([$q->id => question_classified_response::no_response()],
                $q->classify_response([]));
    }
}
