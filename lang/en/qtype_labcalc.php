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
 * Strings for the Lab calculation question type.
 *
 * @package    qtype_labcalc
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addmorevars'] = 'Blanks for {$a} more experimental values';
$string['calculatedvalue'] = 'Calculated value';
$string['displaysigfigs'] = 'Significant figures when showing the expected value';
$string['error_atleastonevar'] = 'You must define at least one experimental value.';
$string['error_cannotevaluate'] = 'Your experimental values cannot be used in the calculation ({$a}). Please check them.';
$string['error_formulaempty'] = 'The formula is empty.';
$string['error_formulaequals'] = 'Enter only the right-hand side of the formula, for example {mass} / {volume}, without "result =".';
$string['error_formulainvalid'] = 'The formula could not be evaluated using the sample values: {$a}';
$string['error_minmax'] = 'The minimum must not be greater than the maximum.';
$string['error_missingvalue'] = 'Please enter a value for {$a}.';
$string['error_notanumber'] = 'The value for {$a} is not a valid number.';
$string['error_notfinite'] = 'the result is not a finite number (for example, the square root or logarithm of a negative number)';
$string['error_notnumeric'] = 'This must be a number.';
$string['error_novarsinformula'] = 'The formula does not use any experimental values. Refer to them using braces, for example {mass}.';
$string['error_outofrange'] = 'Your value for {$a->label} is outside the acceptable range ({$a->range}). Please check your measurement.';
$string['error_partialtolerance'] = 'The partial credit tolerance must be larger than the full marks tolerance.';
$string['error_sampleoutofrange'] = 'The sample value must be within the minimum and maximum.';
$string['error_tolerance'] = 'The tolerance must be a number greater than or equal to zero.';
$string['error_unknownvars'] = 'These names are used in the formula but are not defined as experimental values: {$a}';
$string['error_varnameduplicate'] = 'The name "{$a}" is used more than once.';
$string['error_varnameinvalid'] = 'Names must start with a lower-case letter and contain only lower-case letters, digits and underscores (max 40 characters).';
$string['error_varnamerequired'] = 'Please give this experimental value a name, or clear all its fields.';
$string['expectedfromyourdata'] = 'Using your experimental values, the correct {$a->label} is <strong>{$a->value}</strong> {$a->unit} (answers within {$a->tolerance} are accepted).';
$string['experimentalvalues'] = 'Experimental values';
$string['experimentalvalues_desc'] = 'List the measurements students must enter. Give each one a short name, which you use in the formula as {name}. Leave unused rows blank.';
$string['formula'] = 'Formula for the calculated value';
$string['formula_help'] = 'The formula used to calculate the correct answer from the student\'s own experimental values. Refer to each experimental value by its name in braces.

Examples:

* `{mass} / {volume}`
* `({m_final} - {m_initial}) / {m_initial} * 100`
* `100 * abs({g_exp} - 9.81) / 9.81`
* `{p} * {v} / (8.314 * ({t_c} + 273.15))`

Operators: `+ - * / ^` (power), and brackets. Functions include `sqrt()`, `abs()`, `ln()`, `log()` (natural log), `exp()`, `sin()`, `cos()`, `tan()` (radians), `asin()`, `acos()`, `atan()`, `pi()`, `power(x, y)`, `min()`, `max()`, `average()`, `sum()`, `round(x, dp)`, `floor()`, `ceil()`. Numbers may use e-notation, e.g. `6.022e23`.

Enter only the right-hand side: write `{mass} / {volume}`, not `density = {mass} / {volume}`.';
$string['formulaused'] = 'Formula: {$a}';
$string['grading'] = 'Grading';
$string['partialfraction'] = 'Partial credit';
$string['partialtolerance'] = 'Tolerance for partial credit (optional)';
$string['partialtolerance_help'] = 'If set, answers outside the full-marks tolerance but inside this wider tolerance receive partial credit. Uses the same tolerance type. Leave blank for no partial credit.';
$string['pleaseenterall'] = 'Please enter all of your experimental values and your calculated value.';
$string['pluginname'] = 'Lab calculation';
$string['pluginname_help'] = 'Students enter the experimental values they measured and the value they calculated from them. The calculated value is checked by applying your formula to the student\'s own measurements, so each student is graded on whether they did the calculation correctly with their data.';
$string['pluginname_link'] = 'question/type/labcalc';
$string['pluginnameadding'] = 'Adding a Lab calculation question';
$string['pluginnameediting'] = 'Editing a Lab calculation question';
$string['pluginnamesummary'] = 'Students enter their experimental measurements and a calculated result. The result is checked against your formula applied to the student\'s own measurements.';
$string['privacy:metadata'] = 'The Lab calculation question type plugin does not store any personal data. Responses are stored by the question engine.';
$string['range_between'] = 'between {$a->min} and {$a->max} {$a->unit}';
$string['range_max'] = 'at most {$a->max} {$a->unit}';
$string['range_min'] = 'at least {$a->min} {$a->unit}';
$string['resultlabel'] = 'Label for the calculated value';
$string['resultunit'] = 'Unit of the calculated value';
$string['rightanswersummary'] = '{$a->label} = {$a->formula}, calculated from the student\'s values ({$a->tolerance})';
$string['showexpected'] = 'Show the expected value in feedback';
$string['showexpected_help'] = 'If ticked, the "right answer" feedback tells students the correct calculated value for their own experimental values.';
$string['showformula'] = 'Show the formula in feedback';
$string['status_correct'] = 'Correct calculation';
$string['status_formulaerror'] = 'Values could not be used in the formula';
$string['status_incorrect'] = 'Incorrect calculation';
$string['status_outofrange'] = 'Experimental value out of range';
$string['status_partial'] = 'Within partial credit tolerance';
$string['tolerance'] = 'Tolerance for full marks';
$string['tolerance_help'] = 'How close the student\'s calculated value must be to the value given by the formula. For a relative tolerance this is a percentage of the correct value (e.g. 1 means ±1%); for an absolute tolerance it is in the units of the calculated value (e.g. 0.05).';
$string['toleranceabsolute'] = 'Absolute';
$string['tolerancerelative'] = 'Relative (%)';
$string['tolerancetype'] = 'Tolerance type';
$string['varlabel'] = 'Label shown to students';
$string['varmax'] = 'Maximum';
$string['varmin'] = 'Minimum';
$string['varname'] = 'Variable name';
$string['varname_help'] = 'A short name for this measurement, used in the formula as {name}. For example, if the name is mass, write {mass} in the formula. Use lower-case letters, digits and underscores.';
$string['varnameno'] = 'Experimental value {$a}: name';
$string['varrange'] = 'Acceptable range (optional)';
$string['varrange_help'] = 'Optional sanity check. If a student enters a value outside this range, they are asked to check it, and the question scores zero if they submit it anyway. Leave blank for no limit.';
$string['varsample'] = 'Sample value';
$string['varsample_help'] = 'A typical value. It is used to check that your formula works and when previewing the question with "Fill in correct responses". If blank, the middle of the range (or 1) is used.';
$string['varunit'] = 'Unit';
$string['yourcalculatedvalue'] = 'Your calculated value';
$string['yourexperimentalvalues'] = 'Your experimental values';
