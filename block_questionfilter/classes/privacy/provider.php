<?php
// This file is part of Moodle - https://moodle.org/
// Copyright: 2026 Moodle in Niedersachsen e. V.
// License:   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

namespace block_questionfilter\privacy;

/**
 * Privacy provider für block_questionfilter.
 *
 * Der Block speichert keine personenbezogenen Daten selbst.
 * Export-Dateien werden temporär im Moodle-Filestore abgelegt
 * und sind nur für den exportierenden Nutzer zugänglich.
 * Es werden keine Nutzerdaten persistent gespeichert.
 */
class provider implements \core_privacy\local\metadata\null_provider {

    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
