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

namespace block_questionfilter\task;

use block_questionfilter\tag_suggester;

/**
 * Scheduled task: AI-based tag generation for questions without a tag suggestion.
 *
 * Each question is processed once; the outcome is stored in
 * block_questionfilter_tag_pending with status:
 *   'applied'  — tags were written directly to Moodle's tag system
 *   'pending'  — suggestion stored, waiting for manual review
 *   'empty'    — no suitable tag found
 *
 * A failed request leaves no record, so the question is retried on the next run.
 *
 * @package    block_questionfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @author     Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_tags extends \core\task\scheduled_task {
    /** Questions per run when the setting is missing. */
    const DEFAULT_BATCH_SIZE = 20;

    /**
     * Returns the task name shown in the admin interface.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_generate_tags', 'block_questionfilter');
    }

    /**
     * Processes the next batch of questions; a failed question does not stop
     * the others, but causes the task to report a failure at the end.
     *
     * @throws \moodle_exception If AI tagging is enabled but no provider is configured,
     *                           or if any question could not be processed.
     */
    public function execute(): void {
        if (!get_config('block_questionfilter', 'ai_enabled')) {
            mtrace('block_questionfilter: KI-Verschlagwortung ist in den Einstellungen deaktiviert.');
            return;
        }

        if (!tag_suggester::is_available()) {
            throw new \moodle_exception('error_ai_unavailable', 'block_questionfilter');
        }

        $autoapply = (bool)get_config('block_questionfilter', 'ai_autoapply');
        $batchsize = (int)(get_config('block_questionfilter', 'ai_batch_size') ?: self::DEFAULT_BATCH_SIZE);
        $userid    = (int)get_admin()->id;

        $questions = self::next_questions($batchsize);
        if (empty($questions)) {
            mtrace('block_questionfilter: Keine unverarbeiteten Fragen gefunden.');
            return;
        }

        $failed = 0;
        foreach ($questions as $question) {
            try {
                $tags   = tag_suggester::suggest($question, $question->categoryname, $userid);
                $status = self::record_outcome($question, $tags, $autoapply);
                mtrace("block_questionfilter: Frage {$question->id} ({$question->name}): "
                    . $status . ' (' . implode(', ', $tags) . ')');
            } catch (\Throwable $e) {
                mtrace("block_questionfilter: Frage {$question->id} fehlgeschlagen: " . $e->getMessage());
                $failed++;
            }
        }

        if ($failed > 0) {
            throw new \moodle_exception(
                'error_ai_tagging_failed',
                'block_questionfilter',
                '',
                (object)['failed' => $failed, 'total' => count($questions)]
            );
        }
    }

    /**
     * Returns questions that have not yet been processed by AI tagging.
     *
     * Only ready, non-template questions are included. Questions with an
     * existing entry in block_questionfilter_tag_pending are skipped.
     *
     * @param int $limit Maximum number of questions.
     * @return \stdClass[] Question records with categoryname.
     */
    private static function next_questions(int $limit): array {
        global $DB;

        $sql = "SELECT q.id, q.name, q.qtype, q.questiontext,
                       qc.name AS categoryname
                  FROM {question} q
                  JOIN {question_versions} qv  ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
             LEFT JOIN {block_questionfilter_tag_pending} tp ON tp.questionid = q.id
                 WHERE q.parent = 0
                   AND qv.status = 'ready'
                   AND tp.id IS NULL
              ORDER BY q.id ASC";

        return $DB->get_records_sql($sql, [], 0, $limit);
    }

    /**
     * Stores the tagging result and optionally writes tags to Moodle's tag system.
     *
     * @param \stdClass $question   Question record (must have id, questionid via qbe).
     * @param string[]  $tags       Suggested tag values.
     * @param bool      $autoapply  Whether to write tags immediately.
     * @return string               Recorded status ('applied', 'pending' or 'empty').
     */
    private static function record_outcome(\stdClass $question, array $tags, bool $autoapply): string {
        global $DB;

        if (empty($tags)) {
            $status = 'empty';
        } else if ($autoapply) {
            self::apply_tags((int)$question->id, $tags);
            $status = 'applied';
        } else {
            $status = 'pending';
        }

        $now = time();
        $DB->insert_record('block_questionfilter_tag_pending', (object)[
            'questionid'   => (int)$question->id,
            'tags'         => implode(',', $tags),
            'status'       => $status,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        return $status;
    }

    /**
     * Writes approved tags directly into Moodle's question tag system.
     *
     * Tags are attached to the question_bank_entry (Moodle 4.x+) with the
     * correct category context, so they appear in the question bank UI.
     *
     * @param int      $questionid Question ID (mdl_question.id).
     * @param string[] $tags       Tag values to write.
     */
    public static function apply_tags(int $questionid, array $tags): void {
        global $DB;

        // Moodle 4.x stores question tags on question_bank_entries, not on question directly.
        $qbe = $DB->get_record_sql(
            "SELECT qbe.id, qc.contextid
               FROM {question_bank_entries} qbe
               JOIN {question_versions} qv  ON qv.questionbankentryid = qbe.id
               JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
              WHERE qv.questionid = :qid
           ORDER BY qv.version DESC",
            ['qid' => $questionid],
            IGNORE_MULTIPLE
        );

        if (!$qbe) {
            debugging(
                'block_questionfilter generate_tags::apply_tags(): '
                    . 'question_bank_entry not found for question ' . $questionid,
                DEBUG_DEVELOPER
            );
            return;
        }

        $context = \context::instance_by_id($qbe->contextid, IGNORE_MISSING);
        if (!$context) {
            $context = \context_system::instance();
        }

        \core_tag_tag::set_item_tags(
            'core_question',
            'question_bank_entry',
            (int)$qbe->id,
            $context,
            $tags
        );
    }
}
