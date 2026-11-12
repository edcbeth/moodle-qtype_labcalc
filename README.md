# Lab calculation question type for Moodle (`qtype_labcalc`)

A question type for laboratory work. In the quiz, students enter

1. the **experimental values** they measured (e.g. mass, volume, temperature), and
2. the **value they calculated** from those measurements (e.g. density).

Moodle then works out the correct answer by putting **the student's own measurements** into
**your formula**, and checks the student's calculated value against it within a tolerance.
Every student can have different data and still be marked fairly on whether they did the
calculation correctly.

## Example

| Setting | Value |
|---|---|
| Experimental value 1 | name `mass`, label *Mass of block*, unit *g*, range 0–500 |
| Experimental value 2 | name `volume`, label *Volume of water displaced*, unit *mL*, min 0.1 |
| Formula | `{mass} / {volume}` |
| Calculated value | label *Density*, unit *g/mL* |
| Tolerance | 1 % (relative), partial credit 50 % within 5 % |

A student enters mass = 26.4 g, volume = 9.8 mL, density = 2.69 g/mL.
Expected value = 26.4 / 9.8 = 2.694 → within 1 % → **full marks**.
Feedback (optional) says: *"Using your experimental values, the correct Density is 2.69 g/mL."*

## Features

* Any number of experimental values, each with a name, student-facing label, unit,
  optional acceptable range (sanity check) and a sample value.
* Formula written with `{name}` placeholders and evaluated with Moodle's built-in safe maths
  parser (the same one as the gradebook — no PHP `eval` of user input). Supports
  `+ - * / ^ **`, brackets, `sqrt abs ln log exp sin cos tan asin acos atan pi() power min max
  average sum round floor ceil`, and e-notation.
* Relative (%) or absolute tolerance, with optional wider tolerance for partial credit.
* Students may type numbers with the local decimal separator, e-notation (`6.02e23`) or
  `1.2x10^-3` / `1.2×10^-3`.
* Values outside the acceptable range are flagged ("please check your measurement"). In
  interactive / immediate feedback mode the student must fix them before the answer is checked;
  if submitted anyway, the question scores zero.
* Feedback can show the expected value computed from the student's data and/or the formula.
* Works with all standard behaviours (deferred, immediate, interactive with hints, adaptive).
* Combined feedback (correct / partially correct / incorrect), hints.
* Moodle XML import/export, backup/restore (including duplicate detection in 4.4+),
  response analysis in quiz statistics, privacy API.
* Form validation: unknown/duplicate variable names, invalid formulas (tested with the sample
  values), invalid ranges and tolerances.

## Requirements

Moodle 4.1 or later. Tested on Moodle 4.5 (PostgreSQL) and Moodle 5.3 (MySQL).

## Installation

**Option A – upload:** *Site administration → Plugins → Install plugins*, upload
`qtype_labcalc.zip`, then follow the upgrade prompts.

**Option B – manual:** copy the `labcalc` folder to `question/type/labcalc` in your Moodle
code (for Moodle 5.1+, `public/question/type/labcalc`), then visit
*Site administration → Notifications*.

## Troubleshooting: "Table 'mdl_qtype_labcalc_options' already exists"

Version 1.0.0 of this plugin used a column name (`maxvalue`) that MySQL/MariaDB reserve, so
installing it on MySQL failed part-way and left one table behind. Before installing 1.0.1,
remove the leftover table (e.g. in phpMyAdmin, adjusting `mdl_` if your table prefix differs):

```sql
DROP TABLE IF EXISTS mdl_qtype_labcalc_options;
DROP TABLE IF EXISTS mdl_qtype_labcalc_vars;
DELETE FROM mdl_config_plugins WHERE plugin = 'qtype_labcalc';
```

Then replace the plugin folder with the new version and visit *Site administration → Notifications*.

## Creating a question

In the question bank choose **Add → Lab calculation**.

1. **Experimental values** – one row per measurement. The *name* (lower-case letters, digits,
   underscores) is what you use in the formula. Leave unused rows blank; use the
   "Blanks for 2 more" button for more.
2. **Calculated value** – label and unit shown to the student, and the formula, e.g.
   `({m_final} - {m_initial}) / {m_initial} * 100`. Enter only the right-hand side.
3. **Grading** – tolerance type and size, optional partial credit, significant figures for
   showing the expected value, and whether to show the expected value / formula in feedback.

Tip: use *Preview → Fill in correct responses* to see the question answered with your
sample values.

## Running the tests

```
vendor/bin/phpunit --testsuite qtype_labcalc_testsuite
```

## Licence

GNU GPL v3 or later.
