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
 * Guest preview endpoint for block_questionfilter.
 *
 * Selects a pool account (kursfilter_pool*), logs them in
 * and shows an intermediate page with links to the preview and home page.
 * No require_login(): this endpoint is intentionally public.
 *
 * Usage: /blocks/questionfilter/guest_preview.php?qid=42
 *
 * @package    block_questionfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php'); // @codingStandardsIgnoreLine

$qid = required_param('qid', PARAM_INT);

// The question must exist.
$question = $DB->get_record('question', ['id' => $qid, 'parent' => 0], 'id, name', IGNORE_MISSING);
if (!$question) {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/blocks/questionfilter/guest_preview.php'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        get_string('invalidquestion', 'block_questionfilter'),
        \core\output\notification::NOTIFY_ERROR
    );
    echo $OUTPUT->footer();
    exit;
}

// Redirect already logged-in users directly to the preview.
if (isloggedin() && !isguestuser()) {
    redirect(new moodle_url('/question/bank/previewquestion/preview.php', ['id' => $qid]));
}


// Find pool accounts: kursfilter_pool* or questionfilter_guest*.
$pooluser = null;
$prefixes = ['kursfilter_pool', 'questionfilter_guest'];
foreach ($prefixes as $prefix) {
    for ($i = 1; $i <= 50; $i++) {
        $username = $prefix . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        $user = $DB->get_record(
            'user',
            ['username' => $username, 'deleted' => 0, 'suspended' => 0],
            '*',
            IGNORE_MISSING
        );
        if ($user) {
            $pooluser = $user;
            break 2;
        }
    }
}

if (!$pooluser) {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/blocks/questionfilter/guest_preview.php', ['qid' => $qid]));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        get_string('preview_nopooluser', 'block_questionfilter'),
        \core\output\notification::NOTIFY_WARNING
    );
    echo $OUTPUT->footer();
    exit;
}

// Log in the pool account.
$pooluser = get_complete_user_data('id', $pooluser->id);
complete_user_login($pooluser);

// After login, render an intermediate page that notifies the opener tab
// via postMessage so refreshCapabilities() shows the export buttons,
// then navigate directly to the question preview.
$previewurl = (new moodle_url(
    '/question/bank/previewquestion/preview.php',
    ['id' => $qid]
))->out(false);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/blocks/questionfilter/guest_preview.php', ['qid' => $qid]));
$PAGE->set_pagelayout('embedded');
echo $OUTPUT->header();

$wwwrootjson  = json_encode($CFG->wwwroot);
$previewurljson = json_encode($previewurl);

echo <<<HTML
<script>
(function() {
    // Notify the opener (block page) that pool login has completed.
    if (window.opener && !window.opener.closed) {
        try {
            window.opener.postMessage(
                {type: 'qf_pool_login_done'},
                {$wwwrootjson}
            );
        } catch (e) { /* cross-origin guard */ }
    }
    // Navigate directly to the question preview.
    window.location.replace({$previewurljson});
})();
</script>
HTML;

echo $OUTPUT->footer();
exit;
