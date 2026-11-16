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
 * Backup support for the Lab calculation question type.
 *
 * @package    qtype_labcalc
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Provides the information to back up Lab calculation questions.
 *
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_qtype_labcalc_plugin extends backup_qtype_plugin {

    /**
     * Returns the qtype information to attach to the question element.
     *
     * @return backup_plugin_element
     */
    protected function define_question_plugin_structure() {
        $plugin = $this->get_plugin_element(null, '../../qtype', 'labcalc');
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        $options = new backup_nested_element('labcalc', ['id'], [
            'carryforward', 'showexpected', 'showformula', 'groupcheckmode', 'grouppenalty', 'groupdata',
            'correctfeedback', 'correctfeedbackformat', 'partiallycorrectfeedback',
            'partiallycorrectfeedbackformat', 'incorrectfeedback', 'incorrectfeedbackformat',
        ]);
        $vars = new backup_nested_element('labcalc_vars');
        $var = new backup_nested_element('labcalc_var', ['id'], [
            'name', 'label', 'unit', 'rangemin', 'rangemax', 'samplevalue', 'checktolerance', 'sortorder',
        ]);
        $results = new backup_nested_element('labcalc_results');
        $result = new backup_nested_element('labcalc_result', ['id'], [
            'name', 'label', 'unit', 'formula', 'tolerance', 'tolerancetype', 'partialtolerance', 'partialfraction',
            'sigfigsmode', 'sigfigs', 'sigfigsslack', 'sigfigspenalty', 'weight', 'displaysigfigs',
            'knownmode', 'knownformula', 'knowncompare', 'accuracytolerance', 'accuracytolerancetype',
            'accuracypartialtolerance', 'accuracypartialfraction', 'accuracyweight', 'sortorder',
        ]);

        $pluginwrapper->add_child($options);
        $pluginwrapper->add_child($vars);
        $vars->add_child($var);
        $pluginwrapper->add_child($results);
        $results->add_child($result);

        $options->set_source_table('qtype_labcalc_options', ['questionid' => backup::VAR_PARENTID]);
        $var->set_source_table('qtype_labcalc_vars', ['questionid' => backup::VAR_PARENTID], 'sortorder ASC');
        $result->set_source_table('qtype_labcalc_results', ['questionid' => backup::VAR_PARENTID], 'sortorder ASC');

        return $plugin;
    }

    /**
     * File areas used by this question type, beyond the standard ones.
     *
     * @return array
     */
    public static function get_qtype_fileareas() {
        return [
            'correctfeedback' => 'question_created',
            'partiallycorrectfeedback' => 'question_created',
            'incorrectfeedback' => 'question_created',
        ];
    }
}
