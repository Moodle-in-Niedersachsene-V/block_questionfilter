<?php
// This file is part of Moodle - https://moodle.org/
// Copyright: 2026 Moodle in Niedersachsen e. V.
// License:   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Datei-Download-Handler für Export-Dateien.
 * Wird von Moodle automatisch aufgerufen bei /pluginfile.php/…/block_questionfilter/export/…
 */
function block_questionfilter_pluginfile(
    $course, $cm, $context, $filearea, $args, $forcedownload, array $options = []
): void {
    require_login();

    if ($filearea !== 'export') {
        send_file_not_found();
    }

    if (!has_capability('moodle/question:viewall', context_system::instance())) {
        send_file_not_found();
    }

    $itemid   = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs   = get_file_storage();
    $file = $fs->get_file($context->id, 'block_questionfilter', 'export', $itemid, $filepath, $filename);

    if (!$file) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, true, $options);
}
