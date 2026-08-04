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
 * Administrationseinstellungen des Plugins block_questionfilter.
 *
 * @package    block_questionfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @author     Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Suchbereich.
    $settings->add(new admin_setting_heading(
        'block_questionfilter/searchscope_heading',
        get_string('settings_searchscope_heading', 'block_questionfilter'),
        get_string('settings_searchscope_desc', 'block_questionfilter')
    ));

    $settings->add(new admin_setting_configselect(
        'block_questionfilter/searchscope',
        get_string('settings_searchscope', 'block_questionfilter'),
        get_string('settings_searchscope_help', 'block_questionfilter'),
        'all',
        [
            'all' => get_string('scope_all', 'block_questionfilter'),
            'course' => get_string('scope_course', 'block_questionfilter'),
            'system' => get_string('scope_system', 'block_questionfilter'),
        ]
    ));

    // Fragetypen-Anzeige.
    $settings->add(new admin_setting_heading(
        'block_questionfilter/questiontypes_heading',
        get_string('settings_questiontypes_heading', 'block_questionfilter'),
        ''
    ));

    $settings->add(new admin_setting_configselect(
        'block_questionfilter/questiontypes_source',
        get_string('settings_questiontypes_source', 'block_questionfilter'),
        get_string('settings_questiontypes_source_help', 'block_questionfilter'),
        'installed',
        [
            'installed' => get_string('qtypes_installed', 'block_questionfilter'),
            'existing' => get_string('qtypes_existing', 'block_questionfilter'),
        ]
    ));

    // Niveaustufen (erweiterbar).
    $settings->add(new admin_setting_heading(
        'block_questionfilter/difficulty_heading',
        get_string('settings_difficulty_heading', 'block_questionfilter'),
        get_string('settings_difficulty_desc', 'block_questionfilter')
    ));

    $settings->add(new admin_setting_configtextarea(
        'block_questionfilter/difficulty_levels',
        get_string('settings_difficulty_levels', 'block_questionfilter'),
        get_string('settings_difficulty_levels_help', 'block_questionfilter'),
        "Leicht\nMittel\nSchwer"
    ));

    // Benutzerdefinierte Filterfelder.
    $settings->add(new admin_setting_heading(
        'block_questionfilter/customfields_heading',
        get_string('settings_customfields_heading', 'block_questionfilter'),
        ''
    ));

    $settings->add(new admin_setting_configtextarea(
        'block_questionfilter/custom_tags',
        get_string('settings_custom_tags', 'block_questionfilter'),
        get_string('settings_custom_tags_help', 'block_questionfilter'),
        ''
    ));

    // Export-Formate.
    $settings->add(new admin_setting_heading(
        'block_questionfilter/export_heading',
        get_string('settings_export_heading', 'block_questionfilter'),
        ''
    ));

    $settings->add(new admin_setting_configmulticheckbox(
        'block_questionfilter/exportformats',
        get_string('settings_exportformats', 'block_questionfilter'),
        get_string('settings_exportformats_help', 'block_questionfilter'),
        ['xml' => 1, 'csv' => 1, 'gift' => 1],
        [
            'xml' => get_string('export_xml', 'block_questionfilter'),
            'csv' => get_string('export_csv', 'block_questionfilter'),
            'gift' => get_string('export_gift', 'block_questionfilter'),
        ]
    ));

    // Ergebnislimit.
    $settings->add(new admin_setting_configtext(
        'block_questionfilter/resultlimit',
        get_string('settings_resultlimit', 'block_questionfilter'),
        get_string('settings_resultlimit_help', 'block_questionfilter'),
        '200',
        PARAM_INT
    ));
}
