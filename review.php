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
 * Review and approve AI tag suggestions.
 *
 * @package    block_fragenfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('block/fragenfilter:reviewtags', context_system::instance());

$PAGE->set_url(new moodle_url('/blocks/fragenfilter/review.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('review_title', 'block_fragenfilter'));
$PAGE->set_heading(get_string('review_title', 'block_fragenfilter'));

$action = optional_param('action', '', PARAM_ALPHA);

// Aktion verarbeiten.
if ($action) {
    require_sesskey();
    if ($action === 'approveall') {
        $pending = $DB->get_records('block_fragenfilter_tag_pending', ['status' => 'pending']);
        $count = 0;
        foreach ($pending as $row) {
            $tags = array_filter(array_map('trim', explode(',', $row->tags)));
            if (!empty($tags)) {
                \block_fragenfilter\task\generate_tags::apply_tags((int)$row->questionid, $tags);
                $DB->set_field('block_fragenfilter_tag_pending', 'status', 'applied', ['id' => $row->id]);
                $count++;
            }
        }
        \core\notification::success(
            get_string('approved_all', 'block_fragenfilter', $count)
        );
        redirect($PAGE->url);
    }

    if ($action === 'rejectall') {
        $DB->set_field('block_fragenfilter_tag_pending', 'status', 'rejected', ['status' => 'pending']);
        \core\notification::info(get_string('rejected_all', 'block_fragenfilter'));
        redirect($PAGE->url);
    }

    // Einzelaktion.
    $pendingid = optional_param('id', 0, PARAM_INT);
    if ($pendingid) {
        $pending = $DB->get_record(
            'block_fragenfilter_tag_pending',
            ['id' => $pendingid, 'status' => 'pending'],
            '*',
            IGNORE_MISSING
        );
        if ($pending) {
            if ($action === 'approve') {
                $tags = array_filter(array_map('trim', explode(',', $pending->tags)));
                \block_fragenfilter\task\generate_tags::apply_tags((int)$pending->questionid, $tags);
                $DB->set_field('block_fragenfilter_tag_pending', 'status', 'applied', ['id' => $pendingid]);
                \core\notification::success(get_string('approved', 'block_fragenfilter'));
            } else if ($action === 'reject') {
                $DB->set_field('block_fragenfilter_tag_pending', 'status', 'rejected', ['id' => $pendingid]);
                \core\notification::info(get_string('rejected', 'block_fragenfilter'));
            }
        }
        redirect($PAGE->url);
    }
}

// Statistik.
$totalcount    = $DB->count_records('block_fragenfilter_tag_pending', ['status' => 'pending']);
$appliedcount  = $DB->count_records('block_fragenfilter_tag_pending', ['status' => 'applied']);
$rejectedcount = $DB->count_records('block_fragenfilter_tag_pending', ['status' => 'rejected']);

$perpage = optional_param('perpage', 50, PARAM_INT);
$page    = optional_param('page', 0, PARAM_INT);

$pending = $DB->get_records_sql(
    "SELECT p.*, q.name AS questionname, q.qtype
       FROM {block_fragenfilter_tag_pending} p
       JOIN {question} q ON q.id = p.questionid
      WHERE p.status = 'pending'
   ORDER BY p.timecreated ASC",
    [],
    $page * $perpage,
    $perpage
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('review_title', 'block_fragenfilter'));

// Statistik-Leiste.
echo html_writer::div(
    html_writer::tag(
        'span',
        get_string('stat_pending', 'block_fragenfilter', $totalcount),
        ['class' => 'badge badge-warning mr-2', 'style' => 'font-size:14px;padding:6px 12px']
    )
    . html_writer::tag(
        'span',
        get_string('stat_approved', 'block_fragenfilter', $appliedcount),
        ['class' => 'badge badge-success mr-2', 'style' => 'font-size:14px;padding:6px 12px']
    )
    . html_writer::tag(
        'span',
        get_string('stat_rejected', 'block_fragenfilter', $rejectedcount),
        ['class' => 'badge badge-secondary', 'style' => 'font-size:14px;padding:6px 12px']
    ),
    'mb-3'
);

if (empty($pending) && $totalcount === 0) {
    echo $OUTPUT->notification(
        get_string('review_empty', 'block_fragenfilter'),
        \core\output\notification::NOTIFY_INFO
    );
    echo $OUTPUT->footer();
    exit;
}

// Sammelaktionen oben.
if ($totalcount > 0) {
    $approveallurl = new moodle_url('/blocks/fragenfilter/review.php', [
        'action'  => 'approveall',
        'sesskey' => sesskey(),
    ]);
    $rejectallurl = new moodle_url('/blocks/fragenfilter/review.php', [
        'action'  => 'rejectall',
        'sesskey' => sesskey(),
    ]);

    echo html_writer::div(
        html_writer::link(
            $approveallurl,
            get_string('action_approveall', 'block_fragenfilter', $totalcount),
            ['class' => 'btn btn-success mr-2',
             'onclick' => 'return confirm("' . s(get_string('confirm_approveall', 'block_fragenfilter')) . '")']
        )
        . html_writer::link(
            $rejectallurl,
            get_string('action_rejectall', 'block_fragenfilter'),
            ['class' => 'btn btn-danger',
             'onclick' => 'return confirm("' . s(get_string('confirm_rejectall', 'block_fragenfilter')) . '")']
        ),
        'mb-3'
    );
}

if (empty($pending)) {
    echo $OUTPUT->footer();
    exit;
}

// Tabelle.
$table = new \html_table();
$table->head = [
    get_string('col_question', 'block_fragenfilter'),
    get_string('col_type', 'block_fragenfilter'),
    get_string('col_tags', 'block_fragenfilter'),
    get_string('col_actions', 'block_fragenfilter'),
];
$table->attributes['class'] = 'generaltable';

foreach ($pending as $row) {
    $tags = array_filter(array_map('trim', explode(',', $row->tags)));
    $tagpills = implode(' ', array_map(function ($t) {
        return html_writer::tag('span', s($t), [
            'class' => 'badge badge-info',
            'style' => 'margin:2px',
        ]);
    }, $tags));

    $approveurl = new moodle_url('/blocks/fragenfilter/review.php', [
        'action'  => 'approve',
        'id'      => $row->id,
        'sesskey' => sesskey(),
    ]);
    $rejecturl = new moodle_url('/blocks/fragenfilter/review.php', [
        'action'  => 'reject',
        'id'      => $row->id,
        'sesskey' => sesskey(),
    ]);

    $actions = html_writer::link(
        $approveurl,
        get_string('action_approve', 'block_fragenfilter'),
        ['class' => 'btn btn-sm btn-success mr-1']
    );
    $actions .= html_writer::link(
        $rejecturl,
        get_string('action_reject', 'block_fragenfilter'),
        ['class' => 'btn btn-sm btn-danger']
    );

    $table->data[] = [
        s($row->questionname),
        s($row->qtype),
        $tagpills ?: html_writer::tag('em', get_string('no_tags', 'block_fragenfilter')),
        $actions,
    ];
}

echo html_writer::table($table);

echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $PAGE->url);

echo $OUTPUT->footer();
