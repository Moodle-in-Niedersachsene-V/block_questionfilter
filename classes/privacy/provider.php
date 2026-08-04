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
 * Datenschutz-Provider des Plugins block_questionfilter.
 *
 * @package    block_questionfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @author     Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_questionfilter\privacy;

/**
 * Datenschutz-Provider fuer block_questionfilter.
 *
 * Der Block speichert keine personenbezogenen Daten selbst.
 * Export-Dateien werden temporaer im Moodle-Filestore abgelegt
 * und sind nur fuer den exportierenden Nutzer zugaenglich.
 * Es werden keine Nutzerdaten persistent gespeichert.
 *
 * @package    block_questionfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\null_provider {
    /**
     * Begruendung, warum keine personenbezogenen Daten gespeichert werden.
     *
     * @return string Sprachschluessel der Begruendung.
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
