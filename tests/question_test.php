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
 * Unit tests for the Lab calculation question definition.
 *
 * @package    qtype_labcalc
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_labcalc_question
 */
final class question_test extends \advanced_testcase {

    /**
     * Get the density test question, with the student in group B unless told otherwise.
     *
     * @param string|null $groupkey
     * @return \qtype_labcalc_question
     */
    protected function make(?string $groupkey = 'group b'): \qtype_labcalc_question {
        $q = \test_question_maker::make_question('labcalc', 'density');
        $q->groupkey = $groupkey;
        return $q;
    }

    /**
     * Build a response.
     *
     * @param string $mass
     * @param string $volume
     * @param string $density
     * @param string $error
     * @return array
     */
    protected function response(string $mass, string $volume, string $density, string $error): array {
        return ['exp_mass' => $mass, 'exp_volume' => $volume, 'res_density' => $density, 'res_error' => $error];
    }

    public function test_evaluator(): void {
        $this->assertEqualsWithDelta(2.5, evaluator::evaluate('{mass} / {volume}', ['mass' => 12.5, 'volume' => 5]), 1e-12);
        $this->assertEquals(['m2', 'm1', 'v'], evaluator::find_variables('({m2} - {M1}) / { v } + {m2}'));
        $this->assertTrue(evaluator::within_tolerance(1.01, 1.00, 0.01, evaluator::TOLERANCE_ABSOLUTE));
        $this->assertFalse(evaluator::within_tolerance(1.0101, 1.00, 0.01, evaluator::TOLERANCE_ABSOLUTE));
        $this->assertEquals('1.235e-4', evaluator::format_sigfigs(0.000123456, 4, false));
        $this->assertEquals('10.00', evaluator::format_sigfigs(9.9996, 4, false));
        $this->expectException(evaluation_exception::class);
        evaluator::evaluate('{a} / {b}', ['a' => 1, 'b' => 0]);
    }

    public function test_everything_right(): void {
        // 27.1 / 10.0 = 2.71 (3 s.f. required); error = 100 * 0.01 / 2.70 = 0.370 (2 s.f.).
        $q = $this->make();
        $analysis = $q->analyse_response($this->response('27.1', '10.0', '2.71', '0.37'));
        $this->assertEquals('graded', $analysis->status);
        $this->assertEquals(3, $analysis->results[0]->sigfigs->required);
        $this->assertTrue($analysis->results[0]->sigfigs->ok);
        $this->assertTrue($analysis->results[1]->sigfigs->ok);
        $this->assertEquals('Group B', $analysis->datacheck->group);
        $this->assertEmpty($analysis->datacheck->mismatches);
        $this->assertEquals([1.0, question_state::$gradedright], $q->grade_response($this->response('27.1', '10.0', '2.71', '0.37')));
    }

    public function test_wrong_sigfigs(): void {
        $q = $this->make();
        // Density to 4 s.f. instead of 3: density scores 0.75 (weight 2), error scores 1 (weight 1).
        [$fraction] = $q->grade_response($this->response('27.1', '10.0', '2.710', '0.37'));
        $this->assertEqualsWithDelta((2 * 0.75 + 1) / 3, $fraction, 1e-9);
        // Error to 3 s.f. instead of 2: error scores 0.5.
        [$fraction] = $q->grade_response($this->response('27.1', '10.0', '2.71', '0.370'));
        $this->assertEqualsWithDelta((2 + 0.5) / 3, $fraction, 1e-9);
        // Automatic sig figs follow the least precise measurement: 9.8 has 2 s.f., so 2.8 is right.
        $analysis = $this->make('group a')->analyse_response($this->response('27.1', '9.8', '2.8', '3.7'));
        $this->assertEquals(2, $analysis->results[0]->sigfigs->required);
        $this->assertTrue($analysis->results[0]->sigfigs->ok);
    }

    public function test_sigfigs_not_checked_when_value_wrong(): void {
        $q = $this->make();
        $analysis = $q->analyse_response($this->response('27.1', '10.0', '9.99', '0.37'));
        $this->assertEquals('incorrect', $analysis->results[0]->valuestatus);
        $this->assertEquals(0.0, $analysis->results[0]->score);
        $this->assertNull($analysis->results[0]->sigfigs);
    }

    public function test_error_carried_forward(): void {
        // Density wrong (3.00 instead of 2.71), but the percent error correctly calculated from 3.00.
        $q = $this->make();
        $response = $this->response('27.1', '10.0', '3.00', '11');
        $this->assertEqualsWithDelta(1 / 3, $q->grade_response($response)[0], 1e-9);

        $q->carryforward = false;
        $this->assertEquals(0.0, $q->grade_response($response)[0]);
    }

    public function test_partial_credit(): void {
        // 2.78 is 2.6% from 2.71: partial (0.5) for density; error from 2.78 = 2.96... -> 3.0.
        $q = $this->make();
        $analysis = $q->analyse_response($this->response('27.1', '10.0', '2.78', '3.0'));
        $this->assertEquals('partial', $analysis->results[0]->valuestatus);
        $this->assertEqualsWithDelta((2 * 0.5 + 1) / 3, $analysis->fraction, 1e-9);
        $this->assertEquals(question_state::$gradedpartial, $q->grade_response($this->response('27.1', '10.0', '2.78', '3.0'))[1]);
    }

    public function test_group_data(): void {
        // Group A recorded 26.4 g and 9.8 mL. 27.1 g is 2.7% away (more than 2%); 10.0 mL is 0.2 away (allowed).
        $q = $this->make('group a');
        $analysis = $q->analyse_response($this->response('27.1', '10.0', '2.71', '0.37'));
        $this->assertCount(1, $analysis->datacheck->mismatches);
        $this->assertEquals('mass', $analysis->datacheck->mismatches[0]->var->name);
        $this->assertTrue($analysis->datacheck->penalised);
        $this->assertEqualsWithDelta(0.5, $analysis->fraction, 1e-9);

        // Feedback-only mode: no penalty.
        $q->groupcheckmode = groupdata::MODE_FEEDBACK;
        $this->assertEquals(1.0, $q->grade_response($this->response('27.1', '10.0', '2.71', '0.37'))[0]);

        // Student not in a listed group: not checked.
        $q = $this->make(null);
        $analysis = $q->analyse_response($this->response('27.1', '10.0', '2.71', '0.37'));
        $this->assertNull($analysis->datacheck->group);
        $this->assertEquals(1.0, $analysis->fraction);
    }

    public function test_validation(): void {
        $q = $this->make();
        $this->assertFalse($q->is_complete_response([]));
        $this->assertFalse($q->is_gradable_response(['exp_mass' => '27.1']));
        $this->assertStringContainsString('Percent error', $q->get_validation_error(
                ['exp_mass' => '27.1', 'exp_volume' => '10', 'res_density' => '2.71']));
        $outofrange = $this->response('27.1', '0.01', '2710', '100000');
        $this->assertFalse($q->is_complete_response($outofrange));
        $this->assertTrue($q->is_gradable_response($outofrange));
        $this->assertEquals(0.0, $q->grade_response($outofrange)[0]);
        // Powers of ten and e-notation.
        $this->assertEquals(1.0, $q->grade_response($this->response('2.71x10^1', '1.00e1', '2.71', '3.7e-1'))[0]);
    }

    public function test_correct_response_and_summary(): void {
        $q = $this->make();
        // Sample values are 27.1 g and 10 mL; "10" has 1-2 significant figures, so 2 are expected.
        $correct = $q->get_correct_response();
        $this->assertEquals('2.7', $correct['res_density']);
        $this->assertEquals('0.0', $correct['res_error']);
        $this->assertEquals([1.0, question_state::$gradedright], $q->grade_response($correct));
        $this->assertEquals('Mass = 27.1 g; Volume = 10.0 mL; Density = 2.71 g/mL; Percent error = 0.37 %',
                $q->summarise_response($this->response('27.1', '10.0', '2.71', '0.37')));
        $this->assertStringContainsString('Density = mass / volume', $q->get_right_answer_summary());
    }

    public function test_classify_response(): void {
        $q = $this->make();
        $this->assertEquals([
            0 => new question_classified_response('correct_badsf', '2.710', 0.75),
            1 => new question_classified_response('correct', '0.37', 1.0),
        ], $q->classify_response($this->response('27.1', '10.0', '2.710', '0.37')));
        $this->assertEquals([0 => question_classified_response::no_response(), 1 => question_classified_response::no_response()],
                $q->classify_response([]));
    }
}
