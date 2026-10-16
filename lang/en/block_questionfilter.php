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

$string['action_approve'] = 'Approve';
$string['action_approveall'] = 'Approve all {$a}';
$string['action_reject'] = 'Reject';
$string['action_rejectall'] = 'Reject all';
$string['addtoquiz'] = 'Add to quiz';
$string['ai_backend_claude'] = 'Anthropic Claude API';
$string['ai_backend_ollama'] = 'Ollama (local)';
$string['approved'] = 'Tag suggestion has been approved.';
$string['approved_all'] = '{$a} tag suggestions have been approved.';
$string['block_questionfilter:addinstance'] = 'Add a Question bank filter block';
$string['block_questionfilter:export'] = 'Export questions';
$string['block_questionfilter:myaddinstance'] = 'Add a Question bank filter block to Dashboard';
$string['block_questionfilter:reviewtags'] = 'Review and approve AI tag suggestions';
$string['block_questionfilter:view'] = 'Use the question bank filter block';
$string['categories'] = 'Question collections';
$string['col_actions'] = 'Actions';
$string['col_question'] = 'Question';
$string['col_tags'] = 'Tags';
$string['col_type'] = 'Type';
$string['confirm_approveall'] = 'Really approve all pending suggestions?';
$string['confirm_rejectall'] = 'Really reject all pending suggestions?';
$string['coresystem'] = 'System-wide';
$string['difficulty'] = 'Difficulty levels';
$string['entertosearch'] = 'Set filters to search for questions.';
$string['error_ai_answer_invalid'] = 'AI response does not contain a valid JSON object: {$a}';
$string['error_ai_key'] = 'Configure the Claude API key before using this backend.';
$string['error_ai_request_failed'] = 'AI request failed: {$a}';
$string['error_ai_request_limit'] = 'AI request exceeds the 16 KiB limit. Reduce the title or configured categories/model.';
$string['error_ai_response'] = 'Invalid AI response structure. Check the configured provider.';
$string['error_ai_response_limit'] = 'AI response exceeds the 16 KiB limit. Check the configured provider.';
$string['error_ai_tagging_failed'] = 'AI tagging: {$a->failed} of {$a->total} questions could not be processed.';
$string['error_ai_target'] = 'Invalid AI target. Use an HTTP(S) service URL without credentials, query or fragment.';
$string['error_ai_transport'] = 'AI request failed or returned a non-200 status. Check the provider, TLS certificate and Moodle HTTP security settings.';
$string['error_ai_unavailable'] = 'AI tagging is enabled but no AI provider is configured in Moodle (Site administration → AI → AI providers).';
$string['error_preview_needs_post'] = 'Start the preview using the preview button.';
$string['error_review_needs_post'] = 'Tag review actions require a POST request.';
$string['error_review_unavailable'] = 'This suggestion is unavailable or you cannot review its question.';
$string['event_suggestion_reviewed'] = 'AI tag suggestion reviewed';
$string['export_csv'] = 'CSV spreadsheet';
$string['export_gift'] = 'GIFT format';
$string['export_xml'] = 'Moodle XML';
$string['exportfailed'] = 'Export failed. Please check the Moodle logs.';
$string['invalidquestion'] = 'Question not found.';
$string['loading'] = 'Loading …';
$string['loadingcategories'] = 'Loading collections …';
$string['no_tags'] = '(no tags)';
$string['nopermission'] = 'You do not have permission to view the question bank.';
$string['noquestionsselected'] = 'No questions selected.';
$string['nosessionhash'] = 'No session hash provided for guest rating.';
$string['pluginname'] = 'Question bank filter';
$string['preview_back_btn'] = 'Back to home page';
$string['preview_expectedanswer'] = 'Expected answer';
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
$string['questionfilter:reviewtags'] = 'Review and approve AI tag suggestions';
$string['questionfilter:view'] = 'Use the question bank filter block';
$string['questiontypes'] = 'Question type';
$string['ratelimitexceeded'] = 'Too many requests. Please wait {$a->seconds} seconds.';
$string['rejected'] = 'Tag suggestion has been rejected.';
$string['rejected_all'] = 'All pending tag suggestions have been rejected.';
$string['review_empty'] = 'No pending tag suggestions found.';
$string['review_title'] = 'Review AI tag suggestions';
$string['role_preview_desc'] = 'Automatically created role for pool users of the question bank filter block. Grants question preview access (moodle/question:usemine) and export (block/questionfilter:export).';
$string['role_preview_name'] = 'Question preview guest';
$string['scope_all'] = 'All question collections (cross-course)';
$string['scope_course'] = 'Current course only';
$string['scope_system'] = 'System question bank only';
$string['searchplaceholder'] = 'Search question or tag …';
$string['selectall'] = 'Select all';
$string['settings_ai_autoapply'] = 'Automatically apply suggestions';
$string['settings_ai_autoapply_help'] = 'When enabled, AI suggestions are written directly as Moodle tags. Otherwise they are stored for manual review.';
$string['settings_ai_backend'] = 'AI backend';
$string['settings_ai_backend_desc'] = 'Choose whether to use Ollama (local) or the Anthropic Claude API for tag generation.';
$string['settings_ai_backend_heading'] = 'AI backend selection';
$string['settings_ai_backend_help'] = 'Ollama runs locally on the server. Claude API requires an Anthropic API key.';
$string['settings_ai_batch_size'] = 'Questions per run';
$string['settings_ai_batch_size_help'] = 'Questions per task run, limited to 1–20. Provider quotas must also be configured.';
$string['settings_ai_claude_apikey'] = 'Claude API key';
$string['settings_ai_claude_apikey_help'] = 'Anthropic API key. Stored in Moodle plugin configuration, accessible to site administrators and database/backup operators. Never include it in an Ollama URL.';
$string['settings_ai_claude_desc'] = 'Enter your Anthropic API key and the desired model. Question titles and text are minimised (no author name, no Moodle IDs) but not anonymised before being sent.';
$string['settings_ai_claude_heading'] = 'Claude API settings';
$string['settings_ai_claude_model'] = 'Claude model';
$string['settings_ai_claude_model_help'] = 'Model identifier, e.g. claude-haiku-4-5-20251001. Haiku is fast and cost-effective for tag generation.';
$string['settings_ai_desc'] = 'Direct AI tag suggestions via Ollama or Anthropic. Question text is minimised, not anonymised. The configured administrator release applies to all question banks.';
$string['settings_ai_enabled'] = 'Enable AI tagging';
$string['settings_ai_enabled_help'] = 'Releases titles and up to 800 characters of question text from all question banks to the configured AI provider. Free text may contain personal data; check the content, provider and HTTP security settings before enabling. Disabled by default.';
$string['settings_ai_heading'] = 'AI tagging';
$string['settings_ai_max_tags'] = 'Maximum number of tags';
$string['settings_ai_max_tags_help'] = 'Maximum number of tags the AI may suggest per question (default: 5, limited to 1–10).';
$string['settings_ai_ollama_heading'] = 'Ollama settings';
$string['settings_ai_ollama_model'] = 'Ollama model';
$string['settings_ai_ollama_model_help'] = 'Name of the Ollama model, e.g. gemma3:4b or llama3.2. The model must be available on the Ollama server.';
$string['settings_ai_ollama_url'] = 'Ollama URL';
$string['settings_ai_ollama_url_help'] = 'Base URL of the Ollama server, e.g. http://localhost:11434. No trailing slash. Only HTTP(S) URLs without credentials or query are used and redirects are not followed; limit internal targets in Moodle\'s HTTP security settings (blocked hosts, allowed ports).';
$string['settings_ai_tag_categories'] = 'Subject areas';
$string['settings_ai_tag_categories_help'] = 'Possible subject areas for the AI (one per line). The AI selects matching ones and ignores those that do not apply.';
$string['settings_ai_tagging_heading'] = 'Tagging options';
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
$string['settings_review_heading'] = 'Review tag suggestions';
$string['settings_review_link'] = 'Go to review and approval page';
$string['settings_searchscope'] = 'Search scope';
$string['settings_searchscope_desc'] = 'Defines which question collections the block searches across.';
$string['settings_searchscope_heading'] = 'Search scope';
$string['settings_searchscope_help'] = 'All: system-wide and cross-course. Course: current course only. System: system question bank only.';
$string['stat_approved'] = 'Approved: {$a}';
$string['stat_pending'] = 'Pending: {$a}';
$string['stat_rejected'] = 'Rejected: {$a}';
$string['tagplaceholder'] = 'Enter tag and press Enter …';
$string['tags'] = 'Tags';
$string['task_generate_tags'] = 'AI tag generation for questions';
