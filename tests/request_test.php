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

    /** @var string Cookie file for preview requests. */
    private string $cookiejar = '';

    /** @var string Password unique to this test run. */
    private string $password = '';

    /** @var bool Whether Moodle's test setup ran. */
    private bool $setupdone = false;

    protected function setUp(): void {
        $url = getenv('QUESTIONFILTER_WEB_URL');
        if (!$url) {
            $this->markTestSkipped('QUESTIONFILTER_WEB_URL is not set.');
        }
        parent::setUp();
        $this->setupdone = true;
        $this->password = bin2hex(random_bytes(16));
        $this->resetAfterTest();
        // The materials hub exposes search to anonymous visitors.
        set_config('forcelogin', 0);
        $this->baseurl = rtrim($url, '/');
    }

    protected function tearDown(): void {
        if (!$this->setupdone) {
            return;
        }
        if ($this->cookiejar !== '') {
            unlink($this->cookiejar);
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

    /**
     * Calls the preview endpoint with the same visitor session.
     *
     * @param array $fields Query or form fields.
     * @param bool $post Whether to submit a POST.
     * @param string $path Endpoint path.
     * @return string Response body.
     */
    private function preview_request(array $fields, bool $post, string $path = '/blocks/questionfilter/guest_preview.php'): string {
        $this->preventResetByRollback();
        if ($this->cookiejar === '') {
            $this->cookiejar = tempnam(sys_get_temp_dir(), 'qfjar');
        }
        $url = $this->baseurl . $path;
        if (!$post && $fields) {
            $url .= '?' . http_build_query($fields, '', '&');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE => $this->cookiejar,
            CURLOPT_COOKIEJAR => $this->cookiejar,
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields, '', '&'));
        }
        $body = curl_exec($ch);
        $this->assertNotFalse($body, 'Test web server not reachable: ' . curl_error($ch));
        curl_close($ch);
        return $body;
    }

    public function test_preview_rejects_get_and_invalid_sesskeys_before_logging_in(): void {
        global $DB;

        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $question = $generator->create_question('truefalse', null, ['category' => $category->id]);
        $pooluser = $DB->get_record('user', ['username' => 'kursfilter_pool01'], '*', MUST_EXIST);
        $loginpage = $this->preview_request([], false, '/login/index.php');
        $this->assertSame(1, preg_match('/"sesskey":"([A-Za-z0-9]+)"/', $loginpage, $matches));
        $sesskey = $matches[1];
        $before = [];
        foreach (['user', 'question', 'block_questionfilter_ratings', 'block_questionfilter_tag_pending'] as $table) {
            $before[$table] = $DB->get_records($table, null, 'id');
        }

        $requests = [
            [false, ['qid' => $question->id], 'error_preview_needs_post'],
            [false, ['qid' => $question->id, 'sesskey' => 'wrong12345'], 'error_preview_needs_post'],
            [false, ['qid' => $question->id, 'sesskey' => $sesskey], 'error_preview_needs_post'],
            [true, ['qid' => $question->id], 'missingparam'],
            [true, ['qid' => $question->id, 'sesskey' => 'wrong12345'], 'invalidsesskey'],
        ];
        foreach ($requests as [$post, $fields, $error]) {
            $body = $this->preview_request($fields, $post);
            $component = $error === 'error_preview_needs_post' ? 'block_questionfilter' : 'error';
            $this->assertStringContainsString(get_string($error, $component, 'sesskey'), $body, $error);
            $this->assertStringNotContainsString('qf_pool_login_done', $body);
            $this->assertSame(0, $DB->count_records('sessions', ['userid' => $pooluser->id]));
            foreach ($before as $table => $records) {
                $this->assertEquals($records, $DB->get_records($table, null, 'id'), $table . ' changed.');
            }
        }

        $body = $this->preview_request(['qid' => $question->id, 'sesskey' => $sesskey], true);
        $this->assertStringContainsString('qf_pool_login_done', $body);
        $this->assertStringContainsString(
            '/question/bank/previewquestion/preview.php?id=' . $question->id,
            str_replace('\\/', '/', $body)
        );
        $this->assertSame(1, $DB->count_records('sessions', ['userid' => $pooluser->id]));
    }

    /**
     * Logs a fixture user into the real HTTP session.
     *
     * @param \stdClass $user User with the test password.
     * @return string Session key of the logged-in user.
     */
    private function login_reviewer(\stdClass $user): string {
        $page = $this->preview_request([], false, '/login/index.php');
        $this->assertSame(1, preg_match('/name="logintoken" value="([^"]+)"/', $page, $matches));
        $this->preview_request([
            'username' => $user->username,
            'password' => $this->password,
            'logintoken' => $matches[1],
        ], true, '/login/index.php');
        $page = $this->preview_request([], false, '/my/');
        $this->assertSame(1, preg_match('/"sesskey":"([A-Za-z0-9]+)"/', $page, $matches));
        return $matches[1];
    }

    public function test_review_requires_capability_post_and_sesskey_before_any_write(): void {
        global $DB;

        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user(['password' => $this->password]);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $question = $generator->create_question('truefalse', null, ['category' => $category->id]);
        $pendingid = $DB->insert_record('block_questionfilter_tag_pending', (object)[
            'questionid' => $question->id, 'tags' => 'Mathematik', 'status' => 'pending',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $anonymous = $this->preview_request([], false, '/blocks/questionfilter/review.php');
        $this->assertStringNotContainsString('Mathematik', $anonymous);
        $this->assertStringContainsString('/login/index.php', $anonymous);
        $sesskey = $this->login_reviewer($user);
        $before = [];
        foreach (['block_questionfilter_tag_pending', 'tag', 'tag_instance', 'question'] as $table) {
            $before[$table] = $DB->get_records($table, null, 'id');
        }
        $path = '/blocks/questionfilter/review.php';
        foreach (['', 'approve', 'reject', 'approveall', 'rejectall'] as $action) {
            $body = $this->preview_request(['action' => $action, 'id' => $pendingid, 'sesskey' => $sesskey], true, $path);
            $this->assertStringNotContainsString('Mathematik', $body);
            $this->assertStringContainsString(get_string('nopermissions', 'error', get_string(
                'block_questionfilter:reviewtags',
                'block_questionfilter'
            )), $body);
            foreach ($before as $table => $records) {
                $this->assertEquals($records, $DB->get_records($table, null, 'id'), $table);
            }
        }

        $role = $this->getDataGenerator()->create_role();
        foreach (['block/questionfilter:reviewtags', 'moodle/question:viewall', 'moodle/question:tagall'] as $capability) {
            assign_capability($capability, CAP_ALLOW, $role, \context_system::instance());
        }
        role_assign($role, $user->id, \context_system::instance());
        foreach (['approve', 'reject', 'approveall', 'rejectall'] as $action) {
            foreach ([[false, $sesskey], [true, null], [true, 'wrong12345']] as [$post, $key]) {
                $fields = ['action' => $action, 'id' => $pendingid];
                if ($key !== null) {
                    $fields['sesskey'] = $key;
                }
                $this->preview_request($fields, $post, $path);
                foreach ($before as $table => $records) {
                    $this->assertEquals($records, $DB->get_records($table, null, 'id'), "$action changed $table");
                }
            }
        }
        $body = $this->preview_request([], false, $path);
        $this->assertStringContainsString('Mathematik', $body);
        $this->assertStringContainsString('method="post"', $body);
        $this->preview_request(['action' => 'approve', 'id' => $pendingid, 'sesskey' => $sesskey], true, $path);
        $this->assertSame('applied', $DB->get_field('block_questionfilter_tag_pending', 'status', ['id' => $pendingid]));
        $this->assertSame(['Mathematik'], array_values(\core_tag_tag::get_item_tags_array(
            'core_question',
            'question',
            $question->id
        )));
    }

    public function test_review_masks_suggestions_and_restricts_single_and_bulk_actions_to_allowed_questions(): void {
        global $DB;

        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user(['password' => $this->password]);
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $context->id]);
        $own = $generator->create_question('truefalse', null, [
            'category' => $category->id, 'createdby' => $user->id,
        ]);
        $foreign = $generator->create_question('truefalse', null, ['category' => $category->id]);
        $hiddencategory = $generator->create_question_category();
        $hidden = $generator->create_question('truefalse', null, ['category' => $hiddencategory->id]);
        $script = '<script>alert(1)</script>';
        $ids = [];
        foreach ([$own->id, $foreign->id, $hidden->id, 999999999] as $questionid) {
            $ids[$questionid] = $DB->insert_record('block_questionfilter_tag_pending', (object)[
                'questionid' => $questionid, 'tags' => $script, 'status' => 'pending',
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        \core_tag_tag::set_item_tags('core_question', 'question', $own->id, $context, ['Existing']);
        $role = $this->getDataGenerator()->create_role();
        assign_capability('block/questionfilter:reviewtags', CAP_ALLOW, $role, \context_system::instance());
        role_assign($role, $user->id, \context_system::instance());
        // Ownership permits tagging only the reviewer's own question, even with viewall.
        assign_capability('moodle/question:viewall', CAP_ALLOW, $role, $context);
        assign_capability('moodle/question:tagmine', CAP_ALLOW, $role, $context);
        $sesskey = $this->login_reviewer($user);
        $path = '/blocks/questionfilter/review.php';
        $page = $this->preview_request([], false, $path);
        $this->assertStringContainsString(s($script), $page);
        $this->assertStringNotContainsString($script, $page);
        foreach ([$foreign->id, $hidden->id, 999999999] as $questionid) {
            $before = [];
            foreach (['block_questionfilter_tag_pending', 'tag', 'tag_instance'] as $table) {
                $before[$table] = $DB->get_records($table, null, 'id');
            }
            foreach (['approve', 'reject'] as $action) {
                $page = $this->preview_request([
                    'action' => $action, 'id' => $ids[$questionid], 'sesskey' => $sesskey,
                ], true, $path);
                $this->assertStringContainsString(get_string('error_review_unavailable', 'block_questionfilter'), $page);
                foreach ($before as $table => $records) {
                    $this->assertEquals($records, $DB->get_records($table, null, 'id'), $table);
                }
            }
        }
        foreach (['reject', 'approveall', 'rejectall'] as $action) {
            $DB->set_field('block_questionfilter_tag_pending', 'status', 'pending', ['id' => $ids[$own->id]]);
            $this->preview_request(['action' => $action, 'id' => $ids[$own->id], 'sesskey' => $sesskey], true, $path);
            $expected = $action === 'approveall' ? 'applied' : 'rejected';
            $this->assertSame($expected, $DB->get_field('block_questionfilter_tag_pending', 'status', ['id' => $ids[$own->id]]));
            foreach ([$foreign->id, $hidden->id, 999999999] as $questionid) {
                $this->assertSame(
                    'pending',
                    $DB->get_field('block_questionfilter_tag_pending', 'status', ['id' => $ids[$questionid]])
                );
            }
        }
        $this->assertContains('Existing', \core_tag_tag::get_item_tags_array('core_question', 'question', $own->id));
        // A stale pending ID cannot be applied again.
        $this->preview_request(['action' => 'approve', 'id' => $ids[$own->id], 'sesskey' => $sesskey], true, $path);
        $this->assertSame('rejected', $DB->get_field('block_questionfilter_tag_pending', 'status', ['id' => $ids[$own->id]]));
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
