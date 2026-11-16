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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Backup and restore tests for the Lab calculation question type.
 *
 * @package    qtype_labcalc
 * @copyright  2026 Edward Bethel <edcbeth@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_qtype_labcalc_plugin
 * @covers     \restore_qtype_labcalc_plugin
 */
final class backup_test extends \advanced_testcase {

    public function test_backup_and_restore_course(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => \context_course::instance($course->id)->id]);
        $question = $generator->create_question('labcalc', 'density', ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $quiz);

        // Back up the course.
        $bc = new \backup_controller(\backup::TYPE_1COURSE, $course->id, \backup::FORMAT_MOODLE,
                \backup::INTERACTIVE_NO, \backup::MODE_IMPORT, $USER->id);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Restore into a new course.
        $newcourseid = \restore_dbops::create_new_course('Restored', 'restored', $course->category);
        $rc = new \restore_controller($backupid, $newcourseid, \backup::INTERACTIVE_NO, \backup::MODE_IMPORT,
                $USER->id, \backup::TARGET_NEW_COURSE);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        // Find the restored question. (In Moodle 5.0+ question banks belong to mod_qbank instances
        // inside the course, so search every context within the new course.)
        $newcontext = \context_course::instance($newcourseid);
        $restored = $DB->get_records_sql("
                SELECT q.id
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                  JOIN {context} ctx ON ctx.id = qc.contextid
                 WHERE (ctx.id = ? OR ctx.path LIKE ?) AND q.qtype = 'labcalc'",
                [$newcontext->id, $newcontext->path . '/%']);
        $this->assertCount(1, $restored);
        $newid = reset($restored)->id;
        $this->assertNotEquals($question->id, $newid);

        $data = \question_bank::load_question_data($newid);
        $this->assertEquals(['mass', 'volume'], array_values(array_map(fn($v) => $v->name, $data->options->vars)));
        $this->assertEquals(['density', 'error'], array_values(array_map(fn($r) => $r->name, $data->options->results)));
        $this->assertEquals('0.2', array_values($data->options->vars)[1]->checktolerance);
        $this->assertStringContainsString('Group B', $data->options->groupdata);

        $q = \question_bank::load_question($newid);
        $q->groupkey = 'group b';
        $this->assertEquals(1.0, $q->grade_response(['exp_mass' => '27.1', 'exp_volume' => '10.0',
                'res_density' => '2.71', 'res_error' => '0.37'])[0]);
    }
}
