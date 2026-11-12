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

        $this->assertEquals(2, $data->options->groupcheckmode);
        $this->assertStringContainsString('Group A', $data->options->groupdata);
        $vars = array_values($data->options->vars);
        $this->assertCount(2, $vars);
        $this->assertEquals('mass', $vars[0]->name);
        $this->assertEquals('500', $vars[0]->rangemax);
        $this->assertEquals('', $vars[1]->rangemax);
        $this->assertEquals('0.2', $vars[1]->checktolerance);
        $results = array_values($data->options->results);
        $this->assertCount(2, $results);
        $this->assertEquals('100 * abs({density} - 2.70) / 2.70', $results[1]->formula);
        $this->assertEquals(2, $results[0]->sigfigsmode);

        $q = \question_bank::load_question($data->id);
        $this->assertInstanceOf(\qtype_labcalc_question::class, $q);
        $this->assertSame(500.0, $q->vars[0]->rangemax);
        $this->assertNull($q->vars[1]->rangemax);
        $this->assertSame(5.0, $q->results[0]->partialtolerance);
        $this->assertNull($q->results[1]->partialtolerance);
        $this->assertSame(2.0, $q->results[0]->weight);
        $q->groupkey = 'group b';
        $this->assertEquals(1.0, $q->grade_response(['exp_mass' => '27.1', 'exp_volume' => '10.0',
                'res_density' => '2.71', 'res_error' => '0.37'])[0]);

        // Deleting removes the plugin's rows.
        global $DB;
        question_delete_question($data->id);
        $this->assertFalse($DB->record_exists('qtype_labcalc_options', ['questionid' => $data->id]));
        $this->assertFalse($DB->record_exists('qtype_labcalc_vars', ['questionid' => $data->id]));
        $this->assertFalse($DB->record_exists('qtype_labcalc_results', ['questionid' => $data->id]));
    }
    public function test_xml_round_trip(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $data = $this->create_density();

        $exporter = new \qformat_xml();
        $xml = $exporter->writequestion($data);
        $this->assertStringContainsString('<formula>{mass} / {volume}</formula>', $xml);
        $this->assertStringContainsString('<name>volume</name>', $xml);
        $this->assertStringContainsString('<sigfigsmode>2</sigfigsmode>', $xml);

        $imported = $this->import_xml($xml);
        $this->assertEquals(['mass', 'volume'], $imported->varname);
        $this->assertEquals(['500', ''], $imported->varmax);
        $this->assertEquals(['2%', '0.2'], $imported->varchecktolerance);
        $this->assertEquals(['density', 'error'], $imported->resname);
        $this->assertEquals('100 * abs({density} - 2.70) / 2.70', $imported->resformula[1]);
        $this->assertEquals(['5', ''], $imported->respartialtolerance);
        $this->assertEquals(['2', '1'], $imported->ressigfigsmode);
        $this->assertEquals(2, $imported->groupcheckmode);
        $this->assertStringContainsString('Group B', $imported->groupdata);
    }

    public function test_import_version_1_xml(): void {
        $xml = '<question type="labcalc"><name><text>Old</text></name>
            <questiontext format="html"><text>Q</text></questiontext>
            <formula>{mass} / {volume}</formula><resultlabel>Density</resultlabel><resultunit>g/mL</resultunit>
            <tolerance>2</tolerance><tolerancetype>1</tolerancetype><partialtolerance></partialtolerance>
            <partialfraction>0.5</partialfraction><displaysigfigs>3</displaysigfigs>
            <variable><name>mass</name><label>Mass</label><unit>g</unit><minvalue>0</minvalue><maxvalue>500</maxvalue>
            <samplevalue>27</samplevalue></variable>
            <variable><name>volume</name><label>Volume</label><unit>mL</unit><minvalue></minvalue><maxvalue></maxvalue>
            <samplevalue>10</samplevalue></variable></question>';
        $imported = $this->import_xml($xml);
        $this->assertEquals(['result'], $imported->resname);
        $this->assertEquals(['{mass} / {volume}'], $imported->resformula);
        $this->assertEquals(['Density'], $imported->reslabel);
        $this->assertEquals(['2'], $imported->restolerance);
        $this->assertEquals(['0', ''], $imported->varmin);
        $this->assertEquals(['500', ''], $imported->varmax);
    }

    /**
     * Import a single question from Moodle XML.
     *
     * @param string $xml the &lt;question&gt; element.
     * @return \stdClass
     */
    protected function import_xml(string $xml): \stdClass {
        $importer = new \qformat_xml();
        $questions = $importer->readquestions(explode("\n", "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<quiz>\n" .
                $xml . "\n</quiz>\n"));
        $this->assertCount(1, $questions);
        $imported = reset($questions);
        $this->assertEquals('labcalc', $imported->qtype);
        return $imported;
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
            'varmin' => ['', ''], 'varmax' => ['', ''], 'varsample' => ['27', '10'], 'varchecktolerance' => ['2%', ''],
            'resname' => ['density', 'error'], 'reslabel' => ['', ''], 'resunit' => ['', ''],
            'resformula' => ['{mass} / {volume}', '100 * abs({density} - 2.7) / 2.7'],
            'restolerance' => ['1', '0.5'], 'respartialtolerance' => ['', ''], 'resweight' => ['2', '1'],
            'groupcheckmode' => 0, 'groupdata' => '',
        ], $overrides);
        $method = new \ReflectionMethod($form, 'validation');
        return $method->invoke($form, $data, []);
    }

    public function test_form_validation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertEmpty($this->validate([]));

        $errors = $this->validate(['resformula' => ['{mass} / {volum}', '1']]);
        $this->assertStringContainsString('{volum}', $errors['resformula[0]']);

        // A result can only use earlier results.
        $errors = $this->validate(['resformula' => ['{error} * 2', '{mass}']]);
        $this->assertArrayHasKey('resformula[0]', $errors);

        $errors = $this->validate(['resformula' => ['{mass} / {volume}', '{error} * 2']]);
        $this->assertStringContainsString('own', $errors['resformula[1]']);

        $errors = $this->validate(['resformula' => ['{mass} / ({volume} - 10)', '1']]);
        $this->assertArrayHasKey('resformula[0]', $errors); // Division by zero with the sample values.

        $errors = $this->validate(['resformula' => ['density = {mass} / {volume}', '1']]);
        $this->assertArrayHasKey('resformula[0]', $errors);

        $errors = $this->validate(['resname' => ['mass', 'error']]);
        $this->assertArrayHasKey('resname[0]', $errors); // Same name as an experimental value.

        $errors = $this->validate(['resname' => ['', ''], 'resformula' => ['', '']]);
        $this->assertArrayHasKey('resname[0]', $errors); // At least one result.

        $errors = $this->validate(['varname' => ['mass', 'mass']]);
        $this->assertArrayHasKey('varname[1]', $errors);

        $errors = $this->validate(['varname' => ['Mass 1', 'volume']]);
        $this->assertArrayHasKey('varname[0]', $errors);

        $errors = $this->validate(['varmin' => ['10', ''], 'varmax' => ['5', '']]);
        $this->assertArrayHasKey('varrange[0]', $errors);

        $errors = $this->validate(['restolerance' => ['-1', '0.5']]);
        $this->assertArrayHasKey('restolgroup[0]', $errors);

        $errors = $this->validate(['respartialtolerance' => ['0.5', '']]);
        $this->assertArrayHasKey('respartialgroup[0]', $errors);

        $errors = $this->validate(['resweight' => ['abc', '1']]);
        $this->assertArrayHasKey('resweight[0]', $errors);

        // Group data.
        $errors = $this->validate(['groupcheckmode' => 1, 'groupdata' => '']);
        $this->assertArrayHasKey('groupdata', $errors);
        $errors = $this->validate(['groupcheckmode' => 1, 'groupdata' => "group, mass, density\nA, 1, 2"]);
        $this->assertStringContainsString('density', $errors['groupdata']);
        $errors = $this->validate(['groupcheckmode' => 1, 'groupdata' => "group, mass\nA, 26.4",
                'varchecktolerance' => ['two', '']]);
        $this->assertArrayHasKey('varchecktolerance[0]', $errors);
        $this->assertArrayNotHasKey('groupdata', $errors);
    }
}
