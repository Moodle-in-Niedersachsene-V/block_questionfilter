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

namespace block_fragenfilter;

/**
 * Loads a visible question as sanitised preview data.
 *
 * @package block_fragenfilter
 * @copyright 2026 Moodle in Niedersachsen e. V.
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class question_preview {
    /**
     * Returns preview data after the external API checks access to the block.
     *
     * @param int $questionid Selected question.
     * @return array Sanitised preview data.
     */
    public static function get(int $questionid): array {
        global $CFG, $DB;

        $qid = (int)$questionid;

        if (!question_visibility::is_visible($qid)) {
            throw new \moodle_exception('invalidquestion', 'block_fragenfilter');
        }

        // Load the question's base data.
        $q = $DB->get_record('question', ['id' => $qid, 'parent' => 0], '*', MUST_EXIST);

        // Sanitise question text (HTML allowed, but only safe tags).
        require_once($CFG->libdir . '/weblib.php');
        $sysctx     = \context_system::instance();
        $questiontext = format_text($q->questiontext, $q->questiontextformat, [
            'context' => $sysctx,
            'noclean' => false,
            'filter'  => false,
        ]);

        // Remove general feedback fields to avoid spoilers.
        $result = [
            'questionid'   => $qid,
            'qtype'        => $q->qtype,
            'name'         => $q->name,
            'questiontext' => $questiontext,
            'answers'      => [],
            'hinttext'     => '',
        ];

        // Load answer options according to the question type.
        switch ($q->qtype) {
            case 'multichoice':
                $answers = $DB->get_records(
                    'question_answers',
                    ['question' => $qid],
                    'fraction DESC, id ASC'
                );
                foreach ($answers as $a) {
                    $result['answers'][] = [
                        'text'    => format_text($a->answer, $a->answerformat, [
                            'context' => $sysctx,
                            'noclean' => false,
                            'filter'  => false,
                        ]),
                        'correct' => ($a->fraction > 0),
                    ];
                }
                break;

            case 'truefalse':
                $answers = $DB->get_records(
                    'question_answers',
                    ['question' => $qid],
                    'id ASC'
                );
                foreach ($answers as $a) {
                    $result['answers'][] = [
                        'text'    => $a->answer === 'True' ? get_string('true', 'qtype_truefalse')
                                                            : get_string('false', 'qtype_truefalse'),
                        'correct' => ($a->fraction > 0),
                    ];
                }
                break;

            case 'shortanswer':
                // Show only the first model answer to avoid a full spoiler.
                $answer = $DB->get_record_sql(
                    "SELECT answer FROM {question_answers}
                      WHERE question = :qid
                   ORDER BY fraction DESC, id ASC",
                    ['qid' => $qid],
                    IGNORE_MULTIPLE
                );
                if ($answer) {
                    $result['hinttext'] = get_string('preview_expectedanswer', 'block_fragenfilter')
                        . ': ' . s($answer->answer);
                }
                break;

            case 'numerical':
                $answer = $DB->get_record_sql(
                    "SELECT answer, tolerance FROM {question_answers}
                      WHERE question = :qid
                   ORDER BY fraction DESC, id ASC",
                    ['qid' => $qid],
                    IGNORE_MULTIPLE
                );
                if ($answer) {
                    $result['hinttext'] = get_string('preview_expectedanswer', 'block_fragenfilter')
                        . ': ' . s($answer->answer)
                        . ($answer->tolerance > 0 ? ' ± ' . s($answer->tolerance) : '');
                }
                break;

            case 'match':
                $subquestions = $DB->get_records(
                    'qtype_match_subquestions',
                    ['questionid' => $qid],
                    'id ASC'
                );
                foreach ($subquestions as $sub) {
                    if (!empty($sub->questiontext)) {
                        $result['answers'][] = [
                            'text'    => s($sub->questiontext) . ' → ' . s($sub->answertext),
                            'correct' => true,
                        ];
                    }
                }
                break;

            default:
                // Show only the question text for all other types.
                break;
        }

        return $result;
    }
}
