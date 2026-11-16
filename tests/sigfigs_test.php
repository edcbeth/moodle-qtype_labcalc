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
 * Unit tests for significant figure counting.
 *
 * @package    qtype_labcalc
 * @copyright  2026 Edward Bethel <edcbeth@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_labcalc\sigfigs
 */
final class sigfigs_test extends \basic_testcase {

    /**
     * Data provider for {@see test_count()}.
     *
     * @return array
     */
    public static function count_provider(): array {
        return [
            ['2.71', [3, 3]],
            ['2.710', [4, 4]],
            ['0.0250', [3, 3]],
            ['2700', [2, 4]],
            ['2700.', [4, 4]],
            ['100', [1, 3]],
            ['2.70e3', [3, 3]],
            ['2.70E-3', [3, 3]],
            ['1e5', [1, 1]],
            ['-12.0', [3, 3]],
            ['+7', [1, 1]],
            ['.5', [1, 1]],
            ['0', [1, 1]],
            ['0.0', [2, 2]],
            ['0.00', [3, 3]],
            ['007.10', [3, 3]],
            ['abc', null],
            ['', null],
            ['1.2.3', null],
        ];
    }

    /**
     * Test counting significant figures.
     *
     * @dataProvider count_provider
     * @param string $number
     * @param array|null $expected
     */
    public function test_count(string $number, ?array $expected): void {
        $this->assertSame($expected, sigfigs::count($number));
    }

    public function test_satisfies(): void {
        $this->assertTrue(sigfigs::satisfies([3, 3], 3));
        $this->assertFalse(sigfigs::satisfies([4, 4], 3));
        $this->assertTrue(sigfigs::satisfies([4, 4], 3, 1));
        $this->assertTrue(sigfigs::satisfies([2, 4], 3));
        $this->assertFalse(sigfigs::satisfies([2, 4], 5));
        $this->assertEquals('2–4', sigfigs::describe([2, 4]));
        $this->assertEquals('3', sigfigs::describe([3, 3]));
    }
}
