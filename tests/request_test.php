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

namespace block_questionfilter;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Anonymous visitors use the real Moodle AJAX endpoint.
 *
 * HTTP requests execute in a separate web server process.
 *
 * @package    block_questionfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class request_test extends \advanced_testcase {
    /** @var string Test web server URL. */
    private string $baseurl = '';

    /** @var bool Whether Moodle's test setup ran. */
    private bool $setupdone = false;

    protected function setUp(): void {
        $url = getenv('QUESTIONFILTER_WEB_URL');
        if (!$url) {
            $this->markTestSkipped('QUESTIONFILTER_WEB_URL is not set.');
        }
        parent::setUp();
        $this->setupdone = true;
        $this->resetAfterTest();
        $this->baseurl = rtrim($url, '/');
    }

    protected function tearDown(): void {
        if (!$this->setupdone) {
            return;
        }
        // HTTP writes bypass PHPUnit's change tracking, requiring a full reset.
        \testing_util::$tableupdated = [];
        \phpunit_util::$lastdbwrites = null;
        self::resetAllData(false);
        parent::tearDown();
    }

    /**
     * Calls AJAX without a login, cookie or session key.
     *
     * @param array $requests AJAX batch with registered functions and arguments.
     * @return array Decoded AJAX responses.
     */
    private function ajax(array $requests): array {
        // Commit fixtures through Moodle's public test API before the separate process reads them.
        $this->preventResetByRollback();
        $ch = curl_init($this->baseurl . '/lib/ajax/service.php');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requests),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $body = curl_exec($ch);
        $this->assertNotFalse($body, 'Test web server not reachable: ' . curl_error($ch));
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $this->assertSame(200, $status, $body);
        $response = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey(0, $response, $body);
        return $response;
    }

    public function test_anonymous_search_returns_a_visible_question(): void {
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $question = $generator->create_question('truefalse', null, [
            'category' => $category->id,
            'name' => 'Visible tracer question',
        ]);

        $result = $this->ajax([[
            'index' => 0,
            'methodname' => 'block_questionfilter_search_questions',
            'args' => [
                'search' => 'Visible tracer question',
                'contextid' => \context_system::instance()->id,
            ],
        ]])[0];

        $this->assertFalse($result['error'], json_encode($result));
        $this->assertSame(1, $result['data']['total']);
        $this->assertSame((int)$question->id, $result['data']['questions'][0]['id']);
        $this->assertSame('Visible tracer question', $result['data']['questions'][0]['name']);
    }

    public function test_anonymous_export_after_search_is_rejected_without_data_changes(): void {
        global $DB;

        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $question = $generator->create_question('truefalse', null, ['category' => $category->id]);
        $contextid = \context_system::instance()->id;
        $before = [];
        $tables = ['question', 'question_versions', 'question_bank_entries', 'question_categories',
            'question_answers', 'block_questionfilter_ratings', 'block_questionfilter_tag_pending', 'files'];
        foreach ($tables as $table) {
            $before[$table] = $DB->get_records($table, null, 'id');
        }

        // An earlier public search in the same request must not grant a login to the export.
        $responses = $this->ajax([
            ['index' => 0, 'methodname' => 'block_questionfilter_search_questions', 'args' => ['contextid' => $contextid]],
            ['index' => 1, 'methodname' => 'block_questionfilter_export_questions', 'args' => [
                'questionids' => (string)$question->id,
                'format' => 'csv',
                'contextid' => $contextid,
            ]],
        ]);

        $this->assertFalse($responses[0]['error'], json_encode($responses));
        $this->assertTrue($responses[1]['error']);
        $this->assertSame('servicerequireslogin', $responses[1]['exception']['errorcode']);
        $this->assertArrayNotHasKey('data', $responses[1]);
        foreach ($tables as $table) {
            $this->assertEquals($before[$table], $DB->get_records($table, null, 'id'), $table . ' changed.');
        }
    }
}
