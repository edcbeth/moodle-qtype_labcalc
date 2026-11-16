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
 * Upgrade code for the Lab calculation question type.
 *
 * @package    qtype_labcalc
 * @copyright  2026 Edward Bethel <edcbeth@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the Lab calculation question type.
 *
 * @param int $oldversion the version we are upgrading from.
 * @return bool
 */
function xmldb_qtype_labcalc_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026111200) {
        // Version 2.0: several calculated values per question, significant-figure grading and group data checks.

        // 1. New table for the calculated values.
        $table = new xmldb_table('qtype_labcalc_results');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('name', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('label', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('unit', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('formula', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('tolerance', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('tolerancetype', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('partialtolerance', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('partialfraction', XMLDB_TYPE_NUMBER, '12, 7', null, XMLDB_NOTNULL, null, '0.5');
        $table->add_field('sigfigsmode', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sigfigs', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '3');
        $table->add_field('sigfigsslack', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sigfigspenalty', XMLDB_TYPE_NUMBER, '12, 7', null, XMLDB_NOTNULL, null, '0.25');
        $table->add_field('weight', XMLDB_TYPE_NUMBER, '12, 7', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('displaysigfigs', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '4');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('questionid', XMLDB_KEY_FOREIGN, ['questionid'], 'question', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // 2. New option and variable fields.
        $options = new xmldb_table('qtype_labcalc_options');
        $newfields = [
            new xmldb_field('carryforward', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1'),
            new xmldb_field('groupcheckmode', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0'),
            new xmldb_field('grouppenalty', XMLDB_TYPE_NUMBER, '12, 7', null, XMLDB_NOTNULL, null, '0.5'),
            new xmldb_field('groupdata', XMLDB_TYPE_TEXT, null, null, null, null, null),
        ];
        foreach ($newfields as $field) {
            if (!$dbman->field_exists($options, $field)) {
                $dbman->add_field($options, $field);
            }
        }
        $vars = new xmldb_table('qtype_labcalc_vars');
        $field = new xmldb_field('checktolerance', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        if (!$dbman->field_exists($vars, $field)) {
            $dbman->add_field($vars, $field);
        }

        // 3. Move each question's single result into the new table, and rename the stored
        // student responses from 'answer' to the new field name.
        $oldfields = ['formula', 'resultlabel', 'resultunit', 'tolerance', 'tolerancetype',
                'partialtolerance', 'partialfraction', 'displaysigfigs'];
        if ($dbman->field_exists($options, new xmldb_field('formula'))) {
            $rs = $DB->get_recordset('qtype_labcalc_options', null, '', 'id, questionid, ' . implode(', ', $oldfields));
            foreach ($rs as $old) {
                if ($DB->record_exists('qtype_labcalc_results', ['questionid' => $old->questionid])) {
                    continue;
                }
                $taken = $DB->get_fieldset_select('qtype_labcalc_vars', 'name', 'questionid = ?', [$old->questionid]);
                $name = 'result';
                for ($i = 2; in_array($name, $taken); $i++) {
                    $name = 'result' . $i;
                }
                $DB->insert_record('qtype_labcalc_results', (object) [
                    'questionid' => $old->questionid,
                    'name' => $name,
                    'label' => (string) $old->resultlabel,
                    'unit' => (string) $old->resultunit,
                    'formula' => (string) $old->formula,
                    'tolerance' => (string) $old->tolerance,
                    'tolerancetype' => (int) $old->tolerancetype,
                    'partialtolerance' => (string) $old->partialtolerance,
                    'partialfraction' => (float) $old->partialfraction,
                    'sigfigsmode' => 0,
                    'sigfigs' => 3,
                    'sigfigsslack' => 0,
                    'sigfigspenalty' => 0.25,
                    'weight' => 1,
                    'displaysigfigs' => (int) $old->displaysigfigs,
                    'sortorder' => 0,
                ]);
                $DB->execute("
                        UPDATE {question_attempt_step_data}
                           SET name = ?
                         WHERE name = 'answer'
                           AND attemptstepid IN (
                               SELECT qas.id
                                 FROM {question_attempt_steps} qas
                                 JOIN {question_attempts} qa ON qa.id = qas.questionattemptid
                                WHERE qa.questionid = ?)",
                        ['res_' . $name, $old->questionid]);
            }
            $rs->close();

            // 4. Drop the old fields.
            foreach ($oldfields as $fieldname) {
                $field = new xmldb_field($fieldname);
                if ($dbman->field_exists($options, $field)) {
                    $dbman->drop_field($options, $field);
                }
            }
        }

        upgrade_plugin_savepoint(true, 2026111200, 'qtype', 'labcalc');
    }

    if ($oldversion < 2026111600) {
        // Version 3.0: compare calculated values with a known (accepted / theoretical) value.
        $table = new xmldb_table('qtype_labcalc_results');
        $fields = [
            new xmldb_field('knownmode', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'displaysigfigs'),
            new xmldb_field('knownformula', XMLDB_TYPE_TEXT, null, null, null, null, null, 'knownmode'),
            new xmldb_field('knowncompare', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'knownformula'),
            new xmldb_field('accuracytolerance', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '5', 'knowncompare'),
            new xmldb_field('accuracytolerancetype', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1',
                    'accuracytolerance'),
            new xmldb_field('accuracypartialtolerance', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null,
                    'accuracytolerancetype'),
            new xmldb_field('accuracypartialfraction', XMLDB_TYPE_NUMBER, '12, 7', null, XMLDB_NOTNULL, null, '0.5',
                    'accuracypartialtolerance'),
            new xmldb_field('accuracyweight', XMLDB_TYPE_NUMBER, '12, 7', null, XMLDB_NOTNULL, null, '0.25',
                    'accuracypartialfraction'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026111600, 'qtype', 'labcalc');
    }

    return true;
}
