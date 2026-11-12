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
 * Restore support for the Lab calculation question type.
 *
 * @package    qtype_labcalc
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restores Lab calculation questions, including backups made with version 1.0.
 *
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qtype_labcalc_plugin extends restore_qtype_plugin {

    /**
     * Returns the paths to be handled by the plugin at question level.
     *
     * @return restore_path_element[]
     */
    protected function define_question_plugin_structure() {
        return [
            new restore_path_element('labcalc', $this->get_pathfor('/labcalc')),
            new restore_path_element('labcalc_var', $this->get_pathfor('/labcalc_vars/labcalc_var')),
            new restore_path_element('labcalc_result', $this->get_pathfor('/labcalc_results/labcalc_result')),
        ];
    }

    /**
     * Is the question being restored new (rather than mapped to an existing one)?
     *
     * @return bool
     */
    protected function question_created(): bool {
        return (bool) $this->get_mappingid('question_created', $this->get_old_parentid('question'));
    }

    /**
     * Process the qtype/labcalc (options) element.
     *
     * @param array $data
     */
    public function process_labcalc($data) {
        global $DB;
        $data = (object) $data;
        if (!$this->question_created()) {
            return;
        }
        $newquestionid = $this->get_new_parentid('question');
        $data->questionid = $newquestionid;
        if ($DB->record_exists('qtype_labcalc_options', ['questionid' => $newquestionid])) {
            return;
        }
        foreach (['carryforward' => 1, 'groupcheckmode' => 0, 'grouppenalty' => 0.5, 'groupdata' => ''] as $f => $default) {
            if (!isset($data->$f)) {
                $data->$f = $default;
            }
        }
        $newitemid = $DB->insert_record('qtype_labcalc_options', $data);
        $this->set_mapping('qtype_labcalc_options', $data->id, $newitemid);

        // Backups from version 1.0 had a single calculated value stored with the options.
        if (isset($data->formula)) {
            $DB->insert_record('qtype_labcalc_results', (object) [
                'questionid' => $newquestionid,
                'name' => 'result',
                'label' => (string) ($data->resultlabel ?? ''),
                'unit' => (string) ($data->resultunit ?? ''),
                'formula' => (string) $data->formula,
                'tolerance' => (string) ($data->tolerance ?? '1'),
                'tolerancetype' => (int) ($data->tolerancetype ?? 1),
                'partialtolerance' => (string) ($data->partialtolerance ?? ''),
                'partialfraction' => (float) ($data->partialfraction ?? 0.5),
                'sigfigsmode' => 0,
                'sigfigs' => 3,
                'sigfigsslack' => 0,
                'sigfigspenalty' => 0.25,
                'weight' => 1,
                'displaysigfigs' => (int) ($data->displaysigfigs ?? 4),
                'sortorder' => 0,
            ]);
        }
    }

    /**
     * Process a qtype/labcalc_vars/labcalc_var element.
     *
     * @param array $data
     */
    public function process_labcalc_var($data) {
        global $DB;
        $data = (object) $data;
        if (!$this->question_created()) {
            return;
        }
        $data->questionid = $this->get_new_parentid('question');
        // Version 1.0 backups used minvalue / maxvalue and had no group data tolerance.
        $data->rangemin = $data->rangemin ?? $data->minvalue ?? '';
        $data->rangemax = $data->rangemax ?? $data->maxvalue ?? '';
        $data->checktolerance = $data->checktolerance ?? '';
        unset($data->minvalue, $data->maxvalue);
        $newitemid = $DB->insert_record('qtype_labcalc_vars', $data);
        $this->set_mapping('qtype_labcalc_vars', $data->id, $newitemid);

        // A 1.0 question with a variable called "result" clashes with the converted result name.
        if ($data->name === 'result') {
            $DB->set_field('qtype_labcalc_results', 'name', 'result2',
                    ['questionid' => $data->questionid, 'name' => 'result']);
        }
    }

    /**
     * Process a qtype/labcalc_results/labcalc_result element.
     *
     * @param array $data
     */
    public function process_labcalc_result($data) {
        global $DB;
        $data = (object) $data;
        if (!$this->question_created()) {
            return;
        }
        $data->questionid = $this->get_new_parentid('question');
        $newitemid = $DB->insert_record('qtype_labcalc_results', $data);
        $this->set_mapping('qtype_labcalc_results', $data->id, $newitemid);
    }

    /**
     * Convert saved responses from version 1.0 backups ('answer') to the new field name.
     *
     * @param int $questionid the new question id.
     * @param int $sequencenumber
     * @param array $response
     * @return array
     */
    public function recode_response($questionid, $sequencenumber, array $response) {
        global $DB;
        if (array_key_exists('answer', $response)) {
            $name = $DB->get_field('qtype_labcalc_results', 'name', ['questionid' => $questionid, 'sortorder' => 0]);
            if ($name) {
                $response['res_' . $name] = $response['answer'];
                unset($response['answer']);
            }
        }
        return $response;
    }

    /**
     * Return the contents of this qtype to be processed by the links decoder.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content('qtype_labcalc_options',
                    ['correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'], 'qtype_labcalc_options'),
        ];
    }

    /**
     * Convert backup data to the structure returned by get_question_options() (used by Moodle 4.4+
     * to detect questions that already exist when restoring).
     *
     * @param array $backupdata
     * @return stdClass
     */
    public static function convert_backup_to_questiondata(array $backupdata): \stdClass {
        $questiondata = parent::convert_backup_to_questiondata($backupdata);
        $plugindata = $backupdata['plugin_qtype_labcalc_question'] ?? [];
        $questiondata->options->vars = array_map(fn($v) => (object) $v, $plugindata['labcalc_vars']['labcalc_var'] ?? []);
        $questiondata->options->results = array_map(fn($r) => (object) $r,
                $plugindata['labcalc_results']['labcalc_result'] ?? []);
        return $questiondata;
    }

    /**
     * Fields to ignore when comparing restored questions with existing ones.
     *
     * @return string[]
     */
    protected function define_excluded_identity_hash_fields(): array {
        return [
            '/options/vars/id',
            '/options/vars/questionid',
            '/options/results/id',
            '/options/results/questionid',
        ];
    }
}
