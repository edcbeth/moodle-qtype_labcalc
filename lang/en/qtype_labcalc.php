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

$string['addmoreresults'] = 'Add another calculated value';
$string['addmorevars'] = 'Add another experimental value';
$string['calculatedvalues'] = 'Calculated values';
$string['calculatedvalues_desc'] = 'List the values students must calculate. Each one is checked by applying its formula to the student\'s own experimental values. A formula can also use earlier calculated values by name, for example a percent error that uses {density}.';
$string['carryforward'] = 'Carry errors forward';
$string['carryforward_help'] = 'When a formula uses an earlier calculated value, should it use the value the student entered (ticked) or the correct value (unticked)?

With this ticked, a student who gets the first value wrong but then uses it correctly in the next calculation is only penalised once.';
$string['displaysigfigs'] = 'Significant figures when showing the expected value';
$string['doesnotmatchgroup'] = 'Does not match your group\'s data';
$string['error_atleastoneresult'] = 'You must define at least one calculated value.';
$string['error_atleastonevar'] = 'You must define at least one experimental value.';
$string['error_cannotevaluate'] = 'Your values cannot be used in the calculation ({$a}). Please check them.';
$string['error_checktolerance'] = 'Enter a number (an absolute difference, e.g. 0.5) or a percentage (e.g. 2%), or leave blank to require an exact match.';
$string['error_formulaempty'] = 'Please enter a formula.';
$string['error_formulaequals'] = 'Enter only the right-hand side of the formula, for example {mass} / {volume}, without "result =".';
$string['error_formulainvalid'] = 'The formula could not be evaluated using the sample values: {$a}';
$string['error_groupdatarequired'] = 'Enter the data recorded for each group, or turn group data checks off.';
$string['error_minmax'] = 'The minimum must not be greater than the maximum.';
$string['error_missingvalue'] = 'Please enter a value for {$a}.';
$string['error_notanumber'] = 'The value for {$a} is not a valid number.';
$string['error_notfinite'] = 'the result is not a finite number (for example, the square root or logarithm of a negative number)';
$string['error_notnumeric'] = 'This must be a number.';
$string['error_novarsinformula'] = 'The formula does not use any experimental values. Refer to them using braces, for example {mass}.';
$string['error_outofrange'] = 'Your value for {$a->label} is outside the acceptable range ({$a->range}). Please check your measurement.';
$string['error_partialtolerance'] = 'The partial credit tolerance must be larger than the full marks tolerance.';
$string['error_resnamerequired'] = 'Please give this calculated value a name, or clear all its fields.';
$string['error_sampleoutofrange'] = 'The sample value must be within the minimum and maximum.';
$string['error_selfreference'] = 'A formula cannot use its own calculated value.';
$string['error_tolerance'] = 'The tolerance must be a number greater than or equal to zero.';
$string['error_unknownvars'] = 'These names are used in the formula but are not experimental values or earlier calculated values: {$a}';
$string['error_varnameduplicate'] = 'The name "{$a}" is used more than once. Experimental and calculated values must all have different names.';
$string['error_varnameinvalid'] = 'Names must start with a lower-case letter and contain only lower-case letters, digits and underscores (max 40 characters).';
$string['error_varnamerequired'] = 'Please give this experimental value a name, or clear all its fields.';
$string['error_weight'] = 'The weight must be a number greater than or equal to zero.';
$string['expectedfromyourdata'] = '{$a->label}: <strong>{$a->value}</strong> {$a->unit} (answers within {$a->tolerance} are accepted).';
$string['experimentalvalues'] = 'Experimental values';
$string['experimentalvalues_desc'] = 'List the measurements students must enter. Give each one a short name, which you use in the formulas as {name}. Leave unused rows blank.';
$string['feedbackoptions'] = 'Calculation and feedback options';
$string['formula'] = 'Formula';
$string['formula_help'] = 'The formula used to calculate the correct answer from the student\'s own values. Refer to experimental values, and to calculated values listed above this one, by name in braces.

Examples:

* `{mass} / {volume}`
* `100 * abs({density} - 2.70) / 2.70`
* `({m_final} - {m_initial}) / {m_initial} * 100`
* `{p} * {v} / (8.314 * ({t_c} + 273.15))`

Operators: `+ - * / ^` (power), and brackets. Functions include `sqrt()`, `abs()`, `ln()`, `log()` (natural log), `exp()`, `sin()`, `cos()`, `tan()` (radians), `asin()`, `acos()`, `atan()`, `pi()`, `power(x, y)`, `min()`, `max()`, `average()`, `sum()`, `round(x, dp)`, `floor()`, `ceil()`. Numbers may use e-notation, e.g. `6.022e23`.

Enter only the right-hand side: write `{mass} / {volume}`, not `density = {mass} / {volume}`.';
$string['groupcheck_mismatch'] = 'Some of your measurements do not match the values recorded for {$a}:';
$string['groupcheck_mismatchitem'] = '{$a->label}: you entered {$a->given} {$a->unit}; your group recorded {$a->recorded} {$a->unit}.';
$string['groupcheck_nogroup'] = 'No recorded group data was found for you, so your measurements were not checked against it.';
$string['groupcheck_ok'] = 'Your measurements match the values recorded for {$a}.';
$string['groupcheck_penalty'] = 'Your mark for this question has been reduced by {$a}%.';
$string['groupcheckmode'] = 'Check measurements against group data';
$string['groupcheckmode_feedback'] = 'Yes, mention differences in the feedback only';
$string['groupcheckmode_help'] = 'If you record the measurements each lab group actually obtained, each student\'s measurements can be compared with their group\'s. The student\'s group is found from their groups in the course (using the quiz\'s grouping, if it has one) when they start the attempt.

* **Feedback only** – differences are pointed out in the feedback; the mark is not affected.
* **Feedback and penalty** – differences are pointed out and the mark is reduced.

Students who are not in a group listed in the data are not checked.';
$string['groupcheckmode_off'] = 'No';
$string['groupcheckmode_penalty'] = 'Yes, mention differences and reduce the mark';
$string['groupdata'] = 'Recorded group data';
$string['groupdata_duplicate'] = 'Line {$a->line}: group "{$a->group}" appears more than once.';
$string['groupdata_help'] = 'One line per group. The first line is a heading: the word "group" followed by the names of the experimental values you recorded. Each following line starts with the group name (or group ID number) exactly as in the course, followed by the values. Separate columns with commas, semicolons or tabs (you can paste from a spreadsheet). Leave a cell blank to skip that check.

    group, mass, volume
    Group A, 26.4, 9.8
    Group B, 27.1, 10.0';
$string['groupdata_nocolumns'] = 'The first line must be a heading listing the experimental value names, e.g. "group, mass, volume".';
$string['groupdata_nogroup'] = 'Line {$a->line} has no group name.';
$string['groupdata_norows'] = 'Add at least one line of group data below the heading.';
$string['groupdata_notnumber'] = 'Line {$a->line} (group "{$a->group}"): "{$a->value}" is not a number.';
$string['groupdata_unknowncolumn'] = 'The heading "{$a}" is not the name of an experimental value.';
$string['groupdatachecks'] = 'Group data checks';
$string['grouppenalty'] = 'Mark reduction when measurements differ';
$string['partialfraction'] = 'Partial credit';
$string['partialfractionfor'] = 'earns';
$string['partialtolerance'] = 'Partial credit within (optional)';
$string['partialtolerance_help'] = 'If set, answers outside the full-marks tolerance but inside this wider tolerance receive the chosen share of this value\'s marks. Uses the same tolerance type. Leave blank for no partial credit.';
$string['pleaseenterall'] = 'Please enter all of your experimental values and calculated values.';
$string['pluginname'] = 'Lab calculation';
$string['pluginname_help'] = 'Students enter the experimental values they measured and the values they calculated from them. Each calculated value is checked by applying your formula to the student\'s own measurements. Significant figures and the group\'s recorded measurements can also be checked.';
$string['pluginname_link'] = 'question/type/labcalc';
$string['pluginnameadding'] = 'Adding a Lab calculation question';
$string['pluginnameediting'] = 'Editing a Lab calculation question';
$string['pluginnamesummary'] = 'Students enter their experimental measurements and calculated results. Results are checked against your formulas applied to the student\'s own measurements, with optional significant-figure grading and checks against each group\'s recorded data.';
$string['privacy:metadata'] = 'The Lab calculation question type plugin does not store any personal data. Responses are stored by the question engine.';
$string['range_between'] = 'between {$a->min} and {$a->max} {$a->unit}';
$string['range_max'] = 'at most {$a->max} {$a->unit}';
$string['range_min'] = 'at least {$a->min} {$a->unit}';
$string['reslabel'] = 'Label shown to students';
$string['resname'] = 'Name';
$string['resname_help'] = 'A short name for this calculated value. Later formulas can use it as {name}. Use lower-case letters, digits and underscores; it must differ from the experimental value names.';
$string['resnameno'] = 'Calculated value {$a}: name';
$string['resunit'] = 'Unit';
$string['rightanswersummary'] = 'Calculated from the student\'s values: {$a}';
$string['showexpected'] = 'Show the expected values in feedback';
$string['showexpected_help'] = 'If ticked, the "right answer" feedback tells students the correct calculated values for their own experimental values, and how many significant figures were expected.';
$string['showformula'] = 'Show the formulas in feedback';
$string['sigfigsexpected'] = 'Expected significant figures: {$a}.';
$string['sigfigsgrading'] = 'Significant figures';
$string['sigfigsgrading_help'] = 'Check that the student reports this value to the right number of significant figures. This is only checked when the value itself is correct (or earns partial credit).

* **Fixed** – the number you choose.
* **Automatic** – the fewest significant figures among the measurements (and earlier calculated values) the formula uses. This is the usual rule for multiplication and division; for addition and subtraction use a fixed number instead.

Trailing zeros in a whole number such as 2700 are ambiguous, so 2700 is accepted as 2, 3 or 4 significant figures. Students can write 2.70e3 or 2.70x10^3 to be explicit.

The tolerance option accepts answers with one or two figures more or fewer.';
$string['sigfigsmode'] = 'Check';
$string['sigfigsmode_auto'] = 'Automatic (from the measurements)';
$string['sigfigsmode_fixed'] = 'Fixed number';
$string['sigfigsmode_off'] = 'Do not check';
$string['sigfigspenalty'] = 'Penalty for wrong significant figures';
$string['sigfigsrequired'] = 'Required';
$string['sigfigsslack'] = 'Tolerance';
$string['sigfigsslack0'] = 'exactly';
$string['sigfigsslack1'] = '± 1';
$string['sigfigsslack2'] = '± 2';
$string['sigfigswrong'] = '{$a->label}: you gave {$a->given} significant figures, but {$a->required} were expected (−{$a->penalty}% for this value).';
$string['status_correct'] = 'Correct';
$string['status_correct_badsf'] = 'Correct value, wrong significant figures';
$string['status_incorrect'] = 'Incorrect';
$string['status_invaliddata'] = 'Measurements out of range or unusable';
$string['status_partial'] = 'Within partial credit tolerance';
$string['status_partial_badsf'] = 'Within partial credit tolerance, wrong significant figures';
$string['tolerance'] = 'Tolerance for full marks';
$string['tolerance_help'] = 'How close the student\'s calculated value must be to the value given by the formula. For a relative tolerance this is a percentage of the correct value (e.g. 1 means ±1%); for an absolute tolerance it is in the units of the calculated value (e.g. 0.05).';
$string['toleranceabsolute'] = 'Absolute';
$string['tolerancerelative'] = 'Relative (%)';
$string['tolerancetype'] = 'Tolerance type';
$string['usingyourdata'] = 'Using your experimental values, the correct answers are:';
$string['varchecktolerance'] = 'Allowed difference from group data';
$string['varchecktolerance_help'] = 'How far a student\'s measurement may be from the value recorded for their group. Enter a percentage (e.g. 2%) or an absolute difference in this value\'s unit (e.g. 0.5). Leave blank to require an exact match.';
$string['varlabel'] = 'Label shown to students';
$string['varmax'] = 'Maximum';
$string['varmin'] = 'Minimum';
$string['varname'] = 'Variable name';
$string['varname_help'] = 'A short name for this measurement, used in formulas as {name}. For example, if the name is mass, write {mass} in the formula. Use lower-case letters, digits and underscores.';
$string['varnameno'] = 'Experimental value {$a}: name';
$string['varrange'] = 'Acceptable range (optional)';
$string['varrange_help'] = 'Optional sanity check. If a student enters a value outside this range, they are asked to check it, and the question scores zero if they submit it anyway. Leave blank for no limit.';
$string['varsample'] = 'Sample value';
$string['varsample_help'] = 'A typical value. It is used to check that your formulas work and when previewing the question with "Fill in correct responses". If blank, the middle of the range (or 1) is used.';
$string['varunit'] = 'Unit';
$string['weight'] = 'Weight';
$string['weight_help'] = 'This value\'s share of the question\'s marks, relative to the other calculated values. For example, weights of 2 and 1 give two-thirds and one-third of the marks.';
$string['yourcalculatedvalue'] = 'Your calculated value';
$string['yourcalculatedvalues'] = 'Your calculated values';
$string['yourexperimentalvalues'] = 'Your experimental values';
