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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * English language strings for block_questionfilter.
 *
 * @package    block_questionfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addtoquiz'] = 'Add to quiz';
$string['block_questionfilter:addinstance'] = 'Add a Question bank filter block';
$string['block_questionfilter:export'] = 'Export questions';
$string['block_questionfilter:myaddinstance'] = 'Add a Question bank filter block to Dashboard';
$string['block_questionfilter:view'] = 'Use the question bank filter block';
$string['categories'] = 'Question collections';
$string['coresystem'] = 'System-wide';
$string['difficulty'] = 'Difficulty levels';
$string['entertosearch'] = 'Set filters to search for questions.';
$string['error_ai_answer_invalid'] = 'AI response does not contain a valid JSON object: {$a}';
$string['error_ai_request_failed'] = 'AI request failed: {$a}';
$string['error_ai_tagging_failed'] = 'AI tagging: {$a->failed} of {$a->total} questions could not be processed.';
$string['error_ai_unavailable'] = 'AI tagging is enabled but no AI provider is configured in Moodle (Site administration → AI → AI providers).';
$string['export_csv'] = 'CSV spreadsheet';
$string['export_gift'] = 'GIFT format';
$string['export_xml'] = 'Moodle XML';
$string['exportfailed'] = 'Export failed. Please check the Moodle logs.';
$string['invalidquestion'] = 'Question not found.';
$string['loading'] = 'Loading …';
$string['loadingcategories'] = 'Loading collections …';
$string['nopermission'] = 'You do not have permission to view the question bank.';
$string['noquestionsselected'] = 'No questions selected.';
$string['nosessionhash'] = 'No session hash provided for guest rating.';
$string['pluginname'] = 'Question bank filter';
$string['preview_back_btn'] = 'Back to home page';
$string['preview_nopooluser'] = 'Preview not available: No guest pool account found. Please contact the administrator.';
$string['preview_open_btn'] = 'Open preview:';
$string['preview_ready_desc'] = 'You are now logged in. Open the preview and then return to the home page to export questions.';
$string['preview_ready_title'] = 'Question preview ready';
$string['privacy:metadata'] = 'The question bank filter block does not store any personal data. Export files are generated temporarily and not stored permanently.';
$string['qtypes_existing'] = 'Only types with existing questions';
$string['qtypes_installed'] = 'All installed question types';
$string['questionfilter:addinstance'] = 'Add a Question bank filter block';
$string['questionfilter:export'] = 'Export questions';
$string['questionfilter:myaddinstance'] = 'Add a Question bank filter block to Dashboard';
$string['questionfilter:view'] = 'Use the question bank filter block';
$string['questiontypes'] = 'Question type';
$string['ratelimitexceeded'] = 'Too many requests. Please wait {$a->seconds} seconds.';
$string['role_preview_desc'] = 'Automatically created role for pool users of the question bank filter block. Grants question preview access (moodle/question:usemine) and export (block/questionfilter:export).';
$string['role_preview_name'] = 'Question preview guest';
$string['scope_all'] = 'All question collections (cross-course)';
$string['scope_course'] = 'Current course only';
$string['scope_system'] = 'System question bank only';
$string['searchplaceholder'] = 'Search question or tag …';
$string['selectall'] = 'Select all';
$string['settings_ai_autoapply'] = 'Automatically apply suggestions';
$string['settings_ai_autoapply_help'] = 'When enabled, AI suggestions are written directly as Moodle tags. Otherwise they are stored for manual review.';
$string['settings_ai_batch_size'] = 'Questions per run';
$string['settings_ai_batch_size_help'] = 'Maximum number of questions processed per scheduled task run (default: 20).';
$string['settings_ai_desc'] = 'Automatic tag suggestions for questions via Moodle\'s AI subsystem (core_ai). Requires a configured AI provider under Site administration → AI.';
$string['settings_ai_enabled'] = 'Enable AI tagging';
$string['settings_ai_enabled_help'] = 'Enables the scheduled task that generates daily tag suggestions for untagged questions.';
$string['settings_ai_heading'] = 'AI tagging';
$string['settings_custom_tags'] = 'Suggested tags';
$string['settings_custom_tags_help'] = 'Comma-separated list of tags offered as quick filters in the block.';
$string['settings_customfields_heading'] = 'Custom tags';
$string['settings_difficulty_desc'] = 'Levels are offered as tag filters. Extensible — one level per line.';
$string['settings_difficulty_heading'] = 'Difficulty levels';
$string['settings_difficulty_levels'] = 'Difficulty levels';
$string['settings_difficulty_levels_help'] = 'One level per line, e.g. Easy, Medium, Hard. Freely extensible.';
$string['settings_export_heading'] = 'Export formats';
$string['settings_exportformats'] = 'Available formats';
$string['settings_exportformats_help'] = 'Which export formats are shown in the block.';
$string['settings_questiontypes_heading'] = 'Question types';
$string['settings_questiontypes_source'] = 'Displayed question types';
$string['settings_questiontypes_source_help'] = 'All installed: shows all qtype plugins. Existing only: shows only types that have questions in the bank.';
$string['settings_resultlimit'] = 'Max. results';
$string['settings_resultlimit_help'] = 'Maximum number of questions returned per search request (default: 200).';
$string['settings_searchscope'] = 'Search scope';
$string['settings_searchscope_desc'] = 'Defines which question collections the block searches across.';
$string['settings_searchscope_heading'] = 'Search scope';
$string['settings_searchscope_help'] = 'All: system-wide and cross-course. Course: current course only. System: system question bank only.';
$string['tagplaceholder'] = 'Enter tag and press Enter …';
$string['tags'] = 'Tags';
$string['task_generate_tags'] = 'AI tag generation for questions';
