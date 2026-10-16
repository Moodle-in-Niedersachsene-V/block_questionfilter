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
 * Scheduled task: AI-based tag generation for questions.
 *
 * Uses block_fragenfilter\ai_connector (direct Ollama/Claude call),
 * NOT Moodle's core_ai subsystem.
 *
 * Each question is processed once; the outcome is stored in
 * block_fragenfilter_tag_pending with status:
 *   'applied'  — tags were written directly to Moodle's tag system
 *   'pending'  — suggestion stored, waiting for manual review
 *   'empty'    — no suitable tag found
 *
 * @package    block_fragenfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @author     Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_fragenfilter\task;

use block_fragenfilter\ai_connector;

/**
 * Processes a batch of questions without AI-generated tags and
 * stores suggestions in the pending-review table.
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
        return get_string('task_generate_tags', 'block_fragenfilter');
    }

    /**
     * Processes the next batch of questions.
     *
     * A failed question does not stop the others, but causes the task to
     * report a failure at the end.
     *
     * @throws \moodle_exception If AI tagging is disabled or any question could not be processed.
     */
    public function execute(): void {
        if (!get_config('block_fragenfilter', 'ai_enabled')) {
            mtrace('block_fragenfilter: KI-Verschlagwortung ist in den Einstellungen deaktiviert.');
            return;
        }

        $autoapply = (bool)get_config('block_fragenfilter', 'ai_autoapply');
        $batchsize = (int)(get_config('block_fragenfilter', 'ai_batch_size') ?: self::DEFAULT_BATCH_SIZE);
        $connector = new ai_connector();

        $questions = self::next_questions($batchsize);

        if (empty($questions)) {
            mtrace('block_fragenfilter: Keine unverarbeiteten Fragen gefunden.');
            return;
        }

        $failed = 0;
        foreach ($questions as $question) {
            try {
                $tags   = $connector->suggest_tags($question->name, $question->questiontext, $question->qtype);
                $status = self::record_outcome($question, $tags, $autoapply);
                mtrace("block_fragenfilter: Frage {$question->id} ({$question->name}): "
                    . $status . ' (' . implode(', ', $tags) . ')');
            } catch (\Throwable $e) {
                mtrace("block_fragenfilter: Frage {$question->id} fehlgeschlagen: " . $e->getMessage());
                $failed++;
            }
        }

        if ($failed > 0) {
            throw new \moodle_exception(
                'error_ai_tagging_failed',
                'block_fragenfilter',
                '',
                (object)['failed' => $failed, 'total' => count($questions)]
            );
        }
    }

    /**
     * Returns questions that have not yet been processed by AI tagging.
     *
     * Only ready, non-template questions are included. Questions with an
     * existing entry in block_fragenfilter_tag_pending are skipped.
     *
     * @param int $limit Maximum number of questions.
     * @return \stdClass[] Question records.
     */
    private static function next_questions(int $limit): array {
        global $DB;

        $sql = "SELECT q.id, q.name, q.qtype, q.questiontext
                  FROM {question} q
                 WHERE q.parent = 0
                   AND NOT EXISTS (
                       SELECT 1 FROM {block_fragenfilter_tag_pending} tp
                        WHERE tp.questionid = q.id
                   )
              ORDER BY q.id ASC";

        return $DB->get_records_sql($sql, [], 0, $limit);
    }

    /**
     * Stores the tagging result and optionally writes tags to Moodle's tag system.
     *
     * @param \stdClass $question   Question record (must have id, name, qtype).
     * @param string[]  $tags       Suggested tag values.
     * @param bool      $autoapply  Whether to write tags immediately.
     * @return string               Recorded status ('applied', 'pending', or 'empty').
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
        $DB->insert_record('block_fragenfilter_tag_pending', (object)[
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

        // Moodle 4.x+ stores question tags on question_bank_entries.
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
                'block_fragenfilter generate_tags::apply_tags(): '
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
