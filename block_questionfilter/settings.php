<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // --- Suchbereich ---
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
            'all'    => get_string('scope_all', 'block_questionfilter'),
            'course' => get_string('scope_course', 'block_questionfilter'),
            'system' => get_string('scope_system', 'block_questionfilter'),
        ]
    ));

    // --- Niveaustufen (erweiterbar) ---
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

    // --- Benutzerdefinierte Filterfelder ---
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

    // --- Export-Formate ---
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
            'xml'  => get_string('export_xml', 'block_questionfilter'),
            'csv'  => get_string('export_csv', 'block_questionfilter'),
            'gift' => get_string('export_gift', 'block_questionfilter'),
        ]
    ));

    // --- Ergebnislimit ---
    $settings->add(new admin_setting_configtext(
        'block_questionfilter/resultlimit',
        get_string('settings_resultlimit', 'block_questionfilter'),
        get_string('settings_resultlimit_help', 'block_questionfilter'),
        '200',
        PARAM_INT
    ));
}
