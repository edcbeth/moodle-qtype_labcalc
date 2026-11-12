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
 * Walk-through tests of Lab calculation questions in the quiz behaviours.
 *
 * @package    qtype_labcalc
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_labcalc_question
 * @covers     \qtype_labcalc_renderer
 */
final class walkthrough_test extends \qbehaviour_walkthrough_test_base {

    /**
     * Put a new student in a course group and make the course the current page's course, as when a quiz is attempted.
     *
     * @param string $groupname
     */
    protected function student_in_group(string $groupname): void {
        global $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => $groupname]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);
        $this->setUser($student);
        $PAGE->set_course($course);
    }

    public function test_deferred_feedback_with_group_check(): void {
        $this->student_in_group('Group A');
        $q = \test_question_maker::make_question('labcalc', 'density');
        $this->start_attempt_at_question($q, 'deferredfeedback', 3);
        $this->assertEquals('group a', $this->quba->get_question_attempt($this->slot)->get_last_qt_var('_labcalcgroup'));
        $this->render();
        $this->assertStringContainsString('Your calculated values', $this->currentoutput);
        $this->assertStringContainsString('Percent error', $this->currentoutput);

        // Group A recorded 26.4 g; the student reports 27.1 g. Everything else is right.
        $this->process_submission(['exp_mass' => '27.1', 'exp_volume' => '10.0', 'res_density' => '2.71', 'res_error' => '0.37']);
        $this->finish();
        $this->check_current_state(question_state::$gradedpartial);
        $this->check_current_mark(1.5);

        $this->displayoptions->rightanswer = \question_display_options::VISIBLE;
        $this->render();
        $this->assertStringContainsString('do not match the values recorded for Group A', $this->currentoutput);
        $this->assertStringContainsString('reduced by 50%', $this->currentoutput);
        $this->assertStringContainsString('labcalc-mismatch', $this->currentoutput);
        $this->assertStringContainsString('2.710', $this->currentoutput); // Expected density to 4 s.f.
    }

    public function test_deferred_feedback_sigfigs(): void {
        $this->student_in_group('Group B');
        $q = \test_question_maker::make_question('labcalc', 'density');
        $this->start_attempt_at_question($q, 'deferredfeedback', 3);
        $this->process_submission(['exp_mass' => '27.1', 'exp_volume' => '10.0', 'res_density' => '2.710', 'res_error' => '0.37']);
        $this->finish();
        $this->check_current_mark(2.5); // Density loses 25% of its 2 marks.
        $this->render();
        $this->assertStringContainsString('you gave 4 significant figures, but 3 were expected', $this->currentoutput);
        $this->assertStringContainsString('match the values recorded for Group B', $this->currentoutput);
    }

    public function test_student_not_in_listed_group(): void {
        $this->student_in_group('Some other group');
        $q = \test_question_maker::make_question('labcalc', 'density');
        $this->start_attempt_at_question($q, 'deferredfeedback', 3);
        $this->process_submission(['exp_mass' => '27.1', 'exp_volume' => '10.0', 'res_density' => '2.71', 'res_error' => '0.37']);
        $this->finish();
        $this->check_current_mark(3);
        $this->render();
        $this->assertStringContainsString('No recorded group data was found for you', $this->currentoutput);
    }

    public function test_interactive_out_of_range_is_invalid(): void {
        $q = \test_question_maker::make_question('labcalc', 'density');
        $q->groupcheckmode = groupdata::MODE_OFF;
        $q->hints = [new \question_hint(1, 'Check your units.', FORMAT_HTML)];
        $this->start_attempt_at_question($q, 'interactive', 1);

        $this->process_submission(['exp_mass' => '600', 'exp_volume' => '10.0', 'res_density' => '60.0',
                'res_error' => '2100', '-submit' => 1]);
        $this->check_current_state(question_state::$invalid);
        $this->render();
        $this->assertStringContainsString('outside the acceptable range', $this->currentoutput);

        $this->process_submission(['exp_mass' => '27.1', 'exp_volume' => '10.0', 'res_density' => '2.71',
                'res_error' => '0.37', '-submit' => 1]);
        $this->check_current_state(question_state::$gradedright);
    }
}
