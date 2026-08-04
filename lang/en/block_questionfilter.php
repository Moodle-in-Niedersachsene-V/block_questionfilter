<?php
defined('MOODLE_INTERNAL') || die();

// Plugin name
$string['pluginname'] = 'Question bank filter';
$string['block_questionfilter:addinstance']   = 'Add a Question bank filter block';
$string['block_questionfilter:myaddinstance'] = 'Add a Question bank filter block to Dashboard';

// UI
$string['searchplaceholder']   = 'Search question or tag …';
$string['categories']          = 'Question collections';
$string['questiontypes']       = 'Question type';
$string['difficulty']          = 'Difficulty levels';
$string['tags']                = 'Tags';
$string['tagplaceholder']      = 'Enter tag and press Enter …';
$string['selectall']           = 'Select all';
$string['addtoquiz']           = 'Add to quiz';
$string['loading']             = 'Loading …';
$string['loadingcategories']   = 'Loading collections …';
$string['entertosearch']       = 'Set filters to search for questions.';
$string['nopermission']        = 'You do not have permission to view the question bank.';
$string['noquestionsselected'] = 'No questions selected.';
$string['coresystem']          = 'System-wide';
$string['exportfailed']        = 'Export failed. Please check the Moodle logs.';

// Export
$string['export_xml']  = 'Moodle XML';
$string['export_csv']  = 'CSV spreadsheet';
$string['export_gift'] = 'GIFT format';

// Admin settings — search scope
$string['settings_searchscope_heading'] = 'Search scope';
$string['settings_searchscope_desc']    = 'Defines which question collections the block searches across.';
$string['settings_searchscope']         = 'Search scope';
$string['settings_searchscope_help']    = 'All: system-wide and cross-course. Course: current course only. System: system question bank only.';

$string['scope_all']    = 'All question collections (cross-course)';
$string['scope_course'] = 'Current course only';
$string['scope_system'] = 'System question bank only';

// Admin settings — difficulty levels
$string['settings_difficulty_heading']     = 'Difficulty levels';
$string['settings_difficulty_desc']        = 'Levels are offered as tag filters. Extensible — one level per line.';
$string['settings_difficulty_levels']      = 'Difficulty levels';
$string['settings_difficulty_levels_help'] = 'One level per line, e.g. Easy, Medium, Hard. Freely extensible.';

// Admin settings — custom tags
$string['settings_customfields_heading'] = 'Custom tags';
$string['settings_custom_tags']          = 'Suggested tags';
$string['settings_custom_tags_help']     = 'Comma-separated list of tags offered as quick filters in the block.';

// Admin settings — export
$string['settings_export_heading']   = 'Export formats';
$string['settings_exportformats']    = 'Available formats';
$string['settings_exportformats_help'] = 'Which export formats are shown in the block.';

// Admin settings — result limit
$string['settings_resultlimit']      = 'Max. results';
$string['settings_resultlimit_help'] = 'Maximum number of questions returned per search request (default: 200).';

// Capabilities
$string['block_questionfilter:view']   = 'Use the question bank filter block';
$string['block_questionfilter:export'] = 'Export questions';

// Question types source
$string['settings_questiontypes_heading']      = 'Question types';
$string['settings_questiontypes_source']       = 'Displayed question types';
$string['settings_questiontypes_source_help']  = 'All installed: shows all qtype plugins. Existing only: shows only types that have questions in the bank.';
$string['qtypes_installed'] = 'All installed question types';
$string['qtypes_existing']  = 'Only types with existing questions';

// Capability strings without block_ prefix (required by Moodle accesslib)
$string['questionfilter:view']         = 'Use the question bank filter block';
$string['questionfilter:export']       = 'Export questions';
$string['questionfilter:addinstance']  = 'Add a Question bank filter block';
$string['questionfilter:myaddinstance']= 'Add a Question bank filter block to Dashboard';
$string['privacy:metadata'] = 'The question bank filter block does not store any personal data. Export files are generated temporarily and not stored permanently.';
