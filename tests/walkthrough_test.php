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

    public function test_deferred_feedback(): void {
        $q = \test_question_maker::make_question('labcalc', 'density');
        $this->start_attempt_at_question($q, 'deferredfeedback', 1);
        $this->check_current_state(question_state::$todo);
        $this->render();
        $this->assertStringContainsString('Your experimental values', $this->currentoutput);
        $this->assertStringContainsString('g/mL', $this->currentoutput);

        $this->process_submission(['exp_mass' => '26.4', 'exp_volume' => '9.8', 'answer' => '2.69']);
        $this->check_current_state(question_state::$complete);

        $this->finish();
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(1);

        $this->displayoptions->rightanswer = \question_display_options::VISIBLE;
        $this->render();
        $this->assertStringContainsString('2.69', $this->currentoutput);
        $this->assertStringContainsString('mass / volume', $this->currentoutput);
    }

    public function test_interactive_out_of_range_is_invalid(): void {
        $q = \test_question_maker::make_question('labcalc', 'density');
        $q->hints = [new \question_hint(1, 'Check your units.', FORMAT_HTML)];
        $this->start_attempt_at_question($q, 'interactive', 1);

        $this->process_submission(['exp_mass' => '600', 'exp_volume' => '10', 'answer' => '60', '-submit' => 1]);
        $this->check_current_state(question_state::$invalid);
        $this->render();
        $this->assertStringContainsString('outside the acceptable range', $this->currentoutput);

        $this->process_submission(['exp_mass' => '27', 'exp_volume' => '10', 'answer' => '2.7', '-submit' => 1]);
        $this->check_current_state(question_state::$gradedright);
    }
}
