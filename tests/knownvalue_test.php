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
 * Unit tests for comparing findings with a known value.
 *
 * @package    qtype_labcalc
 * @copyright  2026 Edward Bethel <edcbeth@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_labcalc\knownvalue
 */
final class knownvalue_test extends \basic_testcase {

    /**
     * Accuracy settings: full within ±2%, half within ±5%.
     *
     * @return \stdClass
     */
    protected function settings(): \stdClass {
        return (object) ['accuracytolerance' => 2.0, 'accuracytolerancetype' => evaluator::TOLERANCE_RELATIVE,
                'accuracypartialtolerance' => 5.0, 'accuracypartialfraction' => 0.5];
    }

    public function test_compare(): void {
        $c = knownvalue::compare(2.71, 2.70, $this->settings());
        $this->assertEquals('accurate', $c->status);
        $this->assertEquals(1.0, $c->score);
        $this->assertEqualsWithDelta(0.37037, $c->percent, 1e-5);
        $this->assertEqualsWithDelta(0.01, $c->difference, 1e-12);

        $c = knownvalue::compare(2.80, 2.70, $this->settings());
        $this->assertEquals('partial', $c->status);
        $this->assertEquals(0.5, $c->score);

        $c = knownvalue::compare(2.50, 2.70, $this->settings());
        $this->assertEquals('inaccurate', $c->status);
        $this->assertEquals(0.0, $c->score);
        $this->assertEqualsWithDelta(-0.2, $c->difference, 1e-12);
    }

    public function test_compare_absolute_and_zero(): void {
        // Known value of zero: no percentage, absolute tolerance still works.
        $settings = (object) ['accuracytolerance' => 0.5, 'accuracytolerancetype' => evaluator::TOLERANCE_ABSOLUTE,
                'accuracypartialtolerance' => null, 'accuracypartialfraction' => 0.5];
        $c = knownvalue::compare(0.3, 0.0, $settings);
        $this->assertNull($c->percent);
        $this->assertEquals('accurate', $c->status);
        $c = knownvalue::compare(-0.6, 0.0, $settings);
        $this->assertEquals('inaccurate', $c->status);
    }

    public function test_combine(): void {
        $this->assertEquals(1.0, knownvalue::combine(1.0, 1.0, 0.25));
        $this->assertEquals(0.75, knownvalue::combine(1.0, 0.0, 0.25));
        $this->assertEquals(0.25, knownvalue::combine(0.0, 1.0, 0.25));
        $this->assertEquals(0.875, knownvalue::combine(1.0, 0.5, 0.25));
        $this->assertEquals(1.0, knownvalue::combine(0.0, 1.0, 1.5)); // Weight capped at 100%.
    }
}
