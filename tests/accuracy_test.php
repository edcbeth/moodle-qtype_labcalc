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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');

/**
 * Tests of grading against a known value, using the 'accuracy' test question.
 *
 * The known density is 2.70 g/mL (aluminium); 25% of the density mark is for accuracy: full within
 * ±2%, half within ±5%. The percent error is calculated from {density_known}. Group B has a copper
 * block, known density 8.96 g/mL.
 *
 * @package    qtype_labcalc
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_labcalc_question
 */
final class accuracy_test extends \advanced_testcase {

    /**
     * Get the accuracy test question.
     *
     * @param string|null $groupkey
     * @return \qtype_labcalc_question
     */
    protected function make(?string $groupkey = null): \qtype_labcalc_question {
        $q = \test_question_maker::make_question('labcalc', 'accuracy');
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

    public function test_accurate_finding(): void {
        // 2.71 is 0.37% from 2.70.
        $analysis = $this->make()->analyse_response($this->response('27.1', '10.0', '2.71', '0.37'));
        $this->assertEquals('accurate', $analysis->results[0]->known->status);
        $this->assertEqualsWithDelta(0.37037, $analysis->results[0]->known->percent, 1e-5);
        $this->assertEquals(1.0, $analysis->fraction);
    }

    public function test_partially_accurate_finding(): void {
        // Correct calculation 2.80, which is 3.7% from 2.70: half of the accuracy marks.
        $q = $this->make();
        $analysis = $q->analyse_response($this->response('28.0', '10.0', '2.80', '3.7'));
        $this->assertEquals(1.0, $analysis->results[0]->calculationscore);
        $this->assertEquals('partial', $analysis->results[0]->known->status);
        $this->assertEqualsWithDelta(0.875, $analysis->results[0]->score, 1e-9);
        $this->assertEqualsWithDelta((0.875 + 1) / 2, $analysis->fraction, 1e-9);
        $this->assertEquals(question_state::$gradedpartial, $q->grade_response($this->response('28.0', '10.0', '2.80', '3.7'))[1]);
    }

    public function test_inaccurate_finding(): void {
        // Correct calculation 3.00, which is 11% from 2.70: no accuracy marks.
        $analysis = $this->make()->analyse_response($this->response('30.0', '10.0', '3.00', '11.1'));
        $this->assertEquals('inaccurate', $analysis->results[0]->known->status);
        $this->assertEqualsWithDelta(0.75, $analysis->results[0]->score, 1e-9);
        $this->assertEqualsWithDelta((0.75 + 1) / 2, $analysis->fraction, 1e-9);
    }

    public function test_feedback_only_mode(): void {
        $q = $this->make();
        $q->results[0]->knownmode = knownvalue::MODE_FEEDBACK;
        $analysis = $q->analyse_response($this->response('30.0', '10.0', '3.00', '11.1'));
        $this->assertEquals('inaccurate', $analysis->results[0]->known->status);
        $this->assertEquals(1.0, $analysis->fraction);
    }

    public function test_compare_reported_or_from_data(): void {
        // Measurements give 2.71 (accurate), but the student reports 2.90 (a 7% slip, and 7.4% from 2.70).
        $response = $this->response('27.1', '10.0', '2.90', '7.4');

        $q = $this->make();
        $analysis = $q->analyse_response($response);
        $this->assertEquals(0.0, $analysis->results[0]->calculationscore);
        $this->assertEquals('inaccurate', $analysis->results[0]->known->status);
        $this->assertEquals(0.0, $analysis->results[0]->score);

        $q->results[0]->knowncompare = knownvalue::COMPARE_FROMDATA;
        $analysis = $q->analyse_response($response);
        $this->assertEqualsWithDelta(2.71, $analysis->results[0]->known->finding, 1e-12);
        $this->assertEquals('accurate', $analysis->results[0]->known->status);
        $this->assertEqualsWithDelta(0.25, $analysis->results[0]->score, 1e-9);
        // The percent error (from the reported 2.90, error carried forward) is still right.
        $this->assertEquals(1.0, $analysis->results[1]->score);
    }

    public function test_known_value_from_group_data(): void {
        // Group B had copper (8.96). Their 2.71 is 70% off, but the percent error from 8.96 is right.
        $q = $this->make('group b');
        $analysis = $q->analyse_response($this->response('27.1', '10.0', '2.71', '69.8'));
        $this->assertEquals(8.96, $analysis->results[0]->known->known);
        $this->assertEquals('inaccurate', $analysis->results[0]->known->status);
        $this->assertEquals(1.0, $analysis->results[1]->score);
        $this->assertEqualsWithDelta((0.75 + 1) / 2, $analysis->fraction, 1e-9);
    }

    public function test_group_key_found_for_known_values_only(): void {
        global $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Group B']);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);
        $this->setUser($student);
        $PAGE->set_course($course);

        // Measurement checks are off, but the group data gives known values, so the group is still found.
        $q = $this->make();
        $step = new \question_attempt_step();
        $q->start_attempt($step, 1);
        $this->assertEquals('group b', $q->groupkey);
    }

    public function test_unusable_known_formula_is_skipped(): void {
        $q = $this->make();
        $q->results[0]->knownformula = '{mass} / ({volume} - 10)';
        $q->results[1]->formula = '100 * abs({density} - 2.70) / 2.70';
        $analysis = $q->analyse_response($this->response('27.1', '10.0', '2.71', '0.37'));
        $this->assertNull($analysis->results[0]->known);
        $this->assertEquals(1.0, $analysis->fraction);
    }

    public function test_correct_response_uses_known_value(): void {
        $q = $this->make();
        $correct = $q->get_correct_response();
        $this->assertEquals('2.71', $correct['res_density']);
        $this->assertEquals([1.0, question_state::$gradedright], $q->grade_response($correct));
        $this->assertStringContainsString('known value 2.70', $q->get_right_answer_summary());
    }
}
