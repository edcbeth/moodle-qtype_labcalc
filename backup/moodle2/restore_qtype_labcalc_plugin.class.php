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
 * Restores Lab calculation questions.
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
        ];
    }

    /**
     * Process the qtype/labcalc (options) element.
     *
     * @param array $data
     */
    public function process_labcalc($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;

        $oldquestionid = $this->get_old_parentid('question');
        $newquestionid = $this->get_new_parentid('question');
        if ($this->get_mappingid('question_created', $oldquestionid)) {
            $data->questionid = $newquestionid;
            if (!$DB->record_exists('qtype_labcalc_options', ['questionid' => $newquestionid])) {
                $newitemid = $DB->insert_record('qtype_labcalc_options', $data);
                $this->set_mapping('qtype_labcalc_options', $oldid, $newitemid);
            }
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
        $oldid = $data->id;

        $oldquestionid = $this->get_old_parentid('question');
        $newquestionid = $this->get_new_parentid('question');
        if ($this->get_mappingid('question_created', $oldquestionid)) {
            $data->questionid = $newquestionid;
            $newitemid = $DB->insert_record('qtype_labcalc_vars', $data);
            $this->set_mapping('qtype_labcalc_vars', $oldid, $newitemid);
        }
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
        $questiondata->options->vars = array_map(
            fn($var) => (object) $var,
            $backupdata['plugin_qtype_labcalc_question']['labcalc_vars']['labcalc_var'] ?? [],
        );
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
        ];
    }
}
