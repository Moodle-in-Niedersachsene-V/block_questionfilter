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
 * Post-Installationsschritte fuer block_fragenfilter.
 *
 * Legt die systemweite Rolle "Fragenvorschau-Gast" an und
 * weist sie allen kursfilter_pool*-Pool-Nutzern zu, damit
 * diese die Moodle-Fragenvorschau nutzen koennen.
 *
 * @package    block_fragenfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Wird nach der Installation des Plugins ausgefuehrt.
 */
function xmldb_block_fragenfilter_install(): void {
    block_fragenfilter_setup_preview_role();
}

/**
 * Legt die Rolle "Fragenvorschau-Gast" an (falls noetig) und
 * weist sie allen verfuegbaren Pool-Nutzern im System-Kontext zu.
 *
 * Die Rolle erhaelt ausschliesslich moodle/question:usemine,
 * damit Pool-Nutzer die Fragenvorschau aufrufen koennen,
 * ohne weitere Rechte zu erhalten.
 */
function block_fragenfilter_setup_preview_role(): void {
    global $DB;

    $roleshortname = 'fragenfilter_preview';
    $sysctx        = context_system::instance();

    // Rolle anlegen oder vorhandene verwenden.
    $role = $DB->get_record('role', ['shortname' => $roleshortname]);
    if (!$role) {
        $roleid = create_role(
            get_string('role_preview_name', 'block_fragenfilter'),
            $roleshortname,
            get_string('role_preview_desc', 'block_fragenfilter'),
            ''
        );
        // Rolle nur auf Systemebene verwendbar.
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        // Einzige Capability: Fragenvorschau.
        assign_capability('moodle/question:usemine', CAP_ALLOW, $roleid, $sysctx->id, true);
        // Export-Capability fuer Pool-Nutzer.
        assign_capability('block/fragenfilter:export', CAP_ALLOW, $roleid, $sysctx->id, true);
        $role = $DB->get_record('role', ['id' => $roleid]);
    }

    // Alle Pool-Nutzer (kursfilter_pool*, fragenfilter_guest* und legacy questionfilter_guest*) zuweisen.
    $prefixes = ['kursfilter_pool', 'fragenfilter_guest', 'questionfilter_guest'];
    foreach ($prefixes as $prefix) {
        $users = $DB->get_records_select(
            'user',
            $DB->sql_like('username', ':prefix') . " AND deleted = 0 AND suspended = 0",
            ['prefix' => $prefix . '%']
        );
        foreach ($users as $user) {
            // Nur zuweisen falls noch nicht vorhanden.
            if (
                !$DB->record_exists('role_assignments', [
                'roleid'    => $role->id,
                'userid'    => $user->id,
                'contextid' => $sysctx->id,
                ])
            ) {
                role_assign($role->id, $user->id, $sysctx->id);
            }
        }
    }
}
