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
 * Gast-Vorschau-Endpunkt fuer block_questionfilter.
 *
 * Waehlt einen Pool-Nutzer (kursfilter_guest*) aus, loggt ihn ein
 * und zeigt eine Zwischenseite mit Links zur Vorschau und zur Startseite.
 * Kein require_login() – Endpunkt ist bewusst oeffentlich.
 *
 * Aufruf: /blocks/questionfilter/guest_preview.php?qid=42
 *
 * @package    block_questionfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php'); // @codingStandardsIgnoreLine

$qid = required_param('qid', PARAM_INT);

// Frage muss existieren.
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

// Bereits angemeldete Nutzer direkt zur Vorschau weiterleiten.
if (isloggedin() && !isguestuser()) {
    redirect(new moodle_url('/question/bank/previewquestion/preview.php', ['id' => $qid]));
}

// Pool-Nutzer suchen: kursfilter_guest* oder questionfilter_guest*.
$pooluser = null;
$prefixes = ['kursfilter_guest', 'questionfilter_guest'];
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

// Pool-Nutzer einloggen.
$pooluser = get_complete_user_data('id', $pooluser->id);
complete_user_login($pooluser);

// URLs vorbereiten.
$previewurl = (new moodle_url('/question/bank/previewquestion/preview.php', ['id' => $qid]))->out(false);
$homeurl    = (new moodle_url('/'))->out(false);

// Zwischenseite: Nutzer klickt selbst — kein automatisches window.open (wird von Browsern geblockt).
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/blocks/questionfilter/guest_preview.php', ['qid' => $qid]));
$PAGE->set_title(get_string('preview_ready_title', 'block_questionfilter'));

echo $OUTPUT->header();

echo html_writer::div(
    html_writer::tag('h3', get_string('preview_ready_title', 'block_questionfilter'))
    . html_writer::tag('p', get_string('preview_ready_desc', 'block_questionfilter'))
    . html_writer::div(
        html_writer::link(
            $previewurl,
            get_string('preview_open_btn', 'block_questionfilter') . ' ' . s($question->name),
            ['class' => 'btn btn-primary btn-lg mr-3', 'target' => '_blank']
        )
        . html_writer::link(
            $homeurl,
            get_string('preview_back_btn', 'block_questionfilter'),
            ['class' => 'btn btn-secondary btn-lg']
        ),
        'mt-3'
    ),
    'p-4'
);

echo $OUTPUT->footer();
