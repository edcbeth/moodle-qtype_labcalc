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

/**
 * Unit tests for parsing group data and finding a user's group.
 *
 * @package    qtype_labcalc
 * @copyright  2026 Edward Bethel <edcbeth@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_labcalc\groupdata
 */
final class groupdata_test extends \advanced_testcase {

    public function test_parse(): void {
        [$rows, $errors] = groupdata::parse("Group, Mass, Volume\nGroup A, 26.4, 9.8\n\n Group  B , 27.1,\n", ['mass', 'volume']);
        $this->assertEmpty($errors);
        $this->assertEquals(['group a', 'group b'], array_keys($rows));
        $this->assertEquals(['mass' => 26.4, 'volume' => 9.8], $rows['group a']['values']);
        $this->assertEquals(['mass' => 27.1], $rows['group b']['values']);
        $this->assertEquals('Group  B', $rows['group b']['label']);
    }

    public function test_parse_spreadsheet_paste(): void {
        // Tab separated with decimal commas, as pasted from a spreadsheet in many locales.
        [$rows, $errors] = groupdata::parse("group\tmass\tvolume\nG1\t26,4\t9,8", ['mass', 'volume']);
        $this->assertEmpty($errors);
        $this->assertEquals(['mass' => 26.4, 'volume' => 9.8], $rows['g1']['values']);
        // Semicolon separated.
        [$rows, $errors] = groupdata::parse("group;mass\nG1;26,4", ['mass']);
        $this->assertEmpty($errors);
        $this->assertEquals(['mass' => 26.4], $rows['g1']['values']);
    }

    public function test_parse_errors(): void {
        [, $errors] = groupdata::parse("group, mass, density\nA, 1, 2\nA, 3, 4\nB, x, 5\n, 6", ['mass', 'volume']);
        $this->assertCount(4, $errors);
        $this->assertStringContainsString('density', $errors[0]);
        $this->assertStringContainsString('more than once', $errors[1]);
        $this->assertStringContainsString('"x"', $errors[2]);
        $this->assertStringContainsString('no group name', $errors[3]);
    }

    public function test_parse_tolerance(): void {
        $this->assertSame([2.0, evaluator::TOLERANCE_RELATIVE], groupdata::parse_tolerance('2%'));
        $this->assertSame([0.5, evaluator::TOLERANCE_ABSOLUTE], groupdata::parse_tolerance(' 0,5 '));
        $this->assertNull(groupdata::parse_tolerance(''));
        $this->assertFalse(groupdata::parse_tolerance('abc'));
        $this->assertFalse(groupdata::parse_tolerance('-1'));
    }

    public function test_find_row_for_user(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course);
        $other = $this->getDataGenerator()->create_and_enrol($course);
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Group A']);
        $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Bench 7', 'idnumber' => 'G-B']);
        $groupb = groups_get_group_by_idnumber($course->id, 'G-B');
        $this->getDataGenerator()->create_group_member(['groupid' => $groupb->id, 'userid' => $user->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $other->id]);

        [$rows] = groupdata::parse("group, mass\nGroup A, 26.4\ng-b, 27.1", ['mass']);
        // Matched by ID number.
        $this->assertEquals('g-b', groupdata::find_row_for_user($rows, $user->id, $course->id));
        // Matched by name.
        $this->assertEquals('group a', groupdata::find_row_for_user($rows, $other->id, $course->id));
        // Not in any listed group / not in a course.
        $stranger = $this->getDataGenerator()->create_and_enrol($course);
        $this->assertNull(groupdata::find_row_for_user($rows, $stranger->id, $course->id));
        $this->assertNull(groupdata::find_row_for_user($rows, $user->id, SITEID));
    }
}
