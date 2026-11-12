# Lab calculation question type for Moodle (`qtype_labcalc`)

A question type for laboratory work. In the quiz, students enter

1. the **experimental values** they measured (e.g. mass, volume, temperature), and
2. the **values they calculated** from those measurements (e.g. density, percent error).

Moodle works out each correct answer by putting **the student's own measurements** into
**your formulas**, and checks the student's values against them. Every student can have different
data and still be marked fairly on whether they did the calculations correctly.

Optionally it also checks that answers are reported to the right number of **significant figures**,
and compares each student's measurements with the values **their lab group recorded**.

## Example

| Setting | Value |
|---|---|
| Experimental value 1 | `mass`, *Mass of block*, g, range 0–500 |
| Experimental value 2 | `volume`, *Volume of water displaced*, mL, min 0.1 |
| Calculated value 1 | `density` = `{mass} / {volume}`, g/mL, ±1 %, sig figs automatic, weight 2 |
| Calculated value 2 | `error` = `100 * abs({density} - 2.70) / 2.70`, %, ±0.5, 2 sig figs, weight 1 |
| Group data | `group, mass, volume` / `Group A, 26.4, 9.8` / `Group B, 27.1, 10.0` |

A student in Group B enters 27.1 g, 10.0 mL, density 2.71 g/mL and error 0.37 % → **full marks**.

* Density written as `2.710` (4 sig figs, but the measurements only justify 3) → that value loses
  the sig-fig penalty.
* Density wrong (3.00) but the percent error correctly worked out from 3.00 (11 %) → with
  *carry errors forward* on, only the density is marked wrong.
* A student in Group A who reports 27.1 g when their group recorded 26.4 g is told so in the
  feedback, and (if you choose) their mark is reduced.

## Features

**Experimental values** – any number, each with a name (used in formulas as `{name}`), a label,
unit, optional acceptable range (sanity check), sample value and allowed difference from the
group data.

**Calculated values** – any number, each with:

* a formula using the experimental values and any earlier calculated values;
* relative (%) or absolute tolerance, with optional wider tolerance for partial credit;
* **significant figures**: not checked, a fixed number, or automatic (the fewest significant
  figures among the measurements and earlier values the formula uses — the multiplication /
  division rule), optionally ±1 or ±2, with a penalty you choose;
* a weight (share of the question's marks).

Formulas are evaluated with Moodle's built-in safe maths parser (the same one as the gradebook —
no PHP `eval` of user input). Supports `+ - * / ^ **`, brackets, `sqrt abs ln log exp sin cos tan
asin acos atan pi() power min max average sum round floor ceil`, and e-notation.

**Error carried forward** – when a formula uses an earlier calculated value, it can use the
student's value (default) or the correct one.

**Significant figure counting** follows the usual rules: leading zeros are not significant; with a
decimal point, trailing zeros are. Trailing zeros in a whole number (`2700`) are ambiguous, so they
are accepted as any of the possible counts (2, 3 or 4). Students can write `2.70e3` or
`2.70x10^3` to be explicit.

**Group data checks** – paste a table of the values each lab group recorded (from a spreadsheet,
comma, semicolon or tab separated). When a student starts the quiz, their group is found from the
course groups (by group name or ID number, within the quiz's grouping if it has one). Their
measurements are compared with the group's using each value's allowed difference (e.g. `2%` or
`0.5`). Choose *feedback only* or *feedback and mark reduction*. Students not in a listed group are
not checked.

Also: numbers in the local decimal format, e-notation or `1.2x10^-3`; range checks that ask the
student to re-check implausible measurements; combined feedback and hints; all quiz behaviours;
Moodle XML import/export (including files from version 1.0); backup/restore; per-value response
analysis in quiz statistics; privacy API.

## Requirements

Moodle 4.1 or later. Tested on Moodle 4.5 (PostgreSQL) and Moodle 5.3 (MySQL).

## Installation and upgrading

Copy the `labcalc` folder to `question/type/labcalc` in your Moodle code (for Moodle 5.1+,
`public/question/type/labcalc`), replacing any older version, then visit
*Site administration → Notifications*.

Upgrading from 1.0 converts each existing question's calculated value into the first (and only)
calculated value, named `result`, and updates saved quiz attempts to match. Nothing else changes
for existing questions until you edit them.

### Troubleshooting: "Table 'mdl_qtype_labcalc_options' already exists"

Version 1.0.0 used a column name (`maxvalue`) that MySQL/MariaDB reserve, so installing it on MySQL
failed part-way and left one table behind. Before installing a later version, remove the leftover
table (e.g. in phpMyAdmin, adjusting `mdl_` if your table prefix differs):

```sql
DROP TABLE IF EXISTS mdl_qtype_labcalc_options;
DROP TABLE IF EXISTS mdl_qtype_labcalc_vars;
DELETE FROM mdl_config_plugins WHERE plugin = 'qtype_labcalc';
```

## Creating a question

In the question bank choose **Add → Lab calculation**.

1. **Experimental values** – one row per measurement.
2. **Calculated values** – one block per value the students calculate. A later formula can use an
   earlier value, e.g. `{density}`.
3. **Group data checks** (optional) – choose a mode and paste the group data table.
4. **Calculation and feedback options** – carry errors forward, show expected values / formulas.

Tip: use *Preview → Fill in correct responses* to see the question answered with your sample
values. A teacher previewing the question is not in a group, so group checks are skipped there;
to try them, attempt the quiz as a student in a group.

## Running the tests

```
vendor/bin/phpunit --testsuite qtype_labcalc_testsuite
```

## Licence

GNU GPL v3 or later.
