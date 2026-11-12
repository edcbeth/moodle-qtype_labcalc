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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/question/type/edit_question_form.php');
require_once($CFG->dirroot . '/question/type/labcalc/edit_labcalc_form.php');

/**
 * Tests for saving, loading, import/export and form validation.
 *
 * @package    qtype_labcalc
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_labcalc
 * @covers     \qtype_labcalc_edit_form
 */
final class questiontype_test extends \advanced_testcase {

    /**
     * Create a density question in the database.
     *
     * @return \stdClass question data.
     */
    protected function create_density(): \stdClass {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $created = $generator->create_question('labcalc', 'density', ['category' => $category->id]);
        return \question_bank::load_question_data($created->id);
    }

    public function test_save_and_load(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $data = $this->create_density();

        $this->assertEquals('{mass} / {volume}', $data->options->formula);
        $this->assertEquals('g/mL', $data->options->resultunit);
        $vars = array_values($data->options->vars);
        $this->assertCount(2, $vars);
        $this->assertEquals('mass', $vars[0]->name);
        $this->assertEquals('500', $vars[0]->rangemax);
        $this->assertEquals('', $vars[1]->rangemax);

        $q = \question_bank::load_question($data->id);
        $this->assertInstanceOf(\qtype_labcalc_question::class, $q);
        $this->assertSame(500.0, $q->vars[0]->rangemax);
        $this->assertNull($q->vars[1]->rangemax);
        $this->assertSame(5.0, $q->partialtolerance);
        $this->assertEquals(1.0, $q->grade_response(['exp_mass' => '30', 'exp_volume' => '12', 'answer' => '2.5'])[0]);

        // Deleting removes the plugin's rows.
        global $DB;
        question_delete_question($data->id);
        $this->assertFalse($DB->record_exists('qtype_labcalc_options', ['questionid' => $data->id]));
        $this->assertFalse($DB->record_exists('qtype_labcalc_vars', ['questionid' => $data->id]));
    }

    public function test_xml_round_trip(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $data = $this->create_density();

        $exporter = new \qformat_xml();
        $xml = $exporter->writequestion($data);
        $this->assertStringContainsString('<formula>{mass} / {volume}</formula>', $xml);
        $this->assertStringContainsString('<name>volume</name>', $xml);

        $importer = new \qformat_xml();
        $questions = $importer->readquestions(explode("\n", "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<quiz>\n" .
                $xml . "</quiz>\n"));
        $this->assertCount(1, $questions);
        $imported = reset($questions);
        $this->assertEquals('labcalc', $imported->qtype);
        $this->assertEquals('{mass} / {volume}', $imported->formula);
        $this->assertEquals(['mass', 'volume'], $imported->varname);
        $this->assertEquals(['500', ''], $imported->varmax);
        $this->assertEquals('5', $imported->partialtolerance);
        $this->assertEquals(1, $imported->showformula);
    }

    /**
     * Run the editing form's validation on some data.
     *
     * @param array $overrides
     * @return array errors
     */
    protected function validate(array $overrides): array {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $question = new \stdClass();
        $question->category = $category->id;
        $question->contextid = $category->contextid;
        $question->qtype = 'labcalc';
        $question->createdby = get_admin()->id;
        $question->formoptions = new \stdClass();
        $question->formoptions->canedit = true;
        $question->formoptions->canmove = true;
        $question->formoptions->cansaveasnew = false;
        $question->formoptions->repeatelements = true;
        $question->inpopup = false;
        $contexts = new \core_question\local\bank\question_edit_contexts(\context::instance_by_id($category->contextid));
        $form = new \qtype_labcalc_edit_form(new \moodle_url('/'), $question, $category, $contexts);

        $data = array_merge([
            'category' => $category->id . ',' . $category->contextid,
            'name' => 'Test', 'questiontext' => ['text' => 'Q', 'format' => FORMAT_HTML],
            'defaultmark' => 1, 'generalfeedback' => ['text' => '', 'format' => FORMAT_HTML],
            'varname' => ['mass', 'volume'], 'varlabel' => ['Mass', 'Volume'], 'varunit' => ['g', 'mL'],
            'varmin' => ['', ''], 'varmax' => ['', ''], 'varsample' => ['27', '10'],
            'formula' => '{mass} / {volume}', 'tolerance' => '1', 'partialtolerance' => '',
        ], $overrides);
        $method = new \ReflectionMethod($form, 'validation');
        return $method->invoke($form, $data, []);
    }

    public function test_form_validation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertEmpty($this->validate([]));

        $errors = $this->validate(['formula' => '{mass} / {density}']);
        $this->assertStringContainsString('{density}', $errors['formula']);

        $errors = $this->validate(['formula' => '{mass} / ({volume} - 10)']);
        $this->assertArrayHasKey('formula', $errors); // Division by zero with the sample values.

        $errors = $this->validate(['formula' => 'density = {mass} / {volume}']);
        $this->assertArrayHasKey('formula', $errors);

        $errors = $this->validate(['varname' => ['mass', 'mass']]);
        $this->assertArrayHasKey('varname[1]', $errors);

        $errors = $this->validate(['varname' => ['Mass 1', 'volume']]);
        $this->assertArrayHasKey('varname[0]', $errors);

        $errors = $this->validate(['varmin' => ['10', ''], 'varmax' => ['5', '']]);
        $this->assertArrayHasKey('varrange[0]', $errors);

        $errors = $this->validate(['varmin' => ['30', '']]);
        $this->assertArrayHasKey('varsample[0]', $errors);

        $errors = $this->validate(['tolerance' => '-1']);
        $this->assertArrayHasKey('tolerance', $errors);

        $errors = $this->validate(['partialtolerance' => '0.5']);
        $this->assertArrayHasKey('partialtolerance', $errors);
    }
}
