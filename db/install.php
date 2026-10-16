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
 * Post-installation steps for block_questionfilter.
 *
 * Creates the site-wide question preview guest role and
 * assigns it to all kursfilter_pool* pool accounts so they
 * can use Moodle's question preview.
 *
 * @package    block_questionfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs after the plugin is installed.
 */
function xmldb_block_questionfilter_install(): void {
    block_questionfilter_setup_preview_role();
}

/**
 * Creates the question preview guest role if needed and
 * assigns it to all available pool accounts in the system context.
 *
 * The role only receives moodle/question:usemine so pool accounts
 * can open the question preview without receiving
 * additional permissions.
 */
function block_questionfilter_setup_preview_role(): void {
    global $DB;

    $roleshortname = 'questionfilter_preview';
    $sysctx        = context_system::instance();

    // Capabilities from db/access.php are not yet in the database during
    // installation; assign_capability() fails without this step.
    update_capabilities('block_questionfilter');

    // Create the role or reuse an existing one.
    $role = $DB->get_record('role', ['shortname' => $roleshortname]);
    if (!$role) {
        $roleid = create_role(
            get_string('role_preview_name', 'block_questionfilter'),
            $roleshortname,
            get_string('role_preview_desc', 'block_questionfilter'),
            ''
        );
        // The role is available only at system level.
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        // Dedicated question preview capability.
        assign_capability('moodle/question:usemine', CAP_ALLOW, $roleid, $sysctx->id, true);
        // Export capability for pool accounts.
        assign_capability('block/questionfilter:export', CAP_ALLOW, $roleid, $sysctx->id, true);
        $role = $DB->get_record('role', ['id' => $roleid]);
    }

    // Assign all pool accounts (kursfilter_pool* and questionfilter_guest*).
    $prefixes = ['kursfilter_pool', 'questionfilter_guest'];
    foreach ($prefixes as $prefix) {
        $users = $DB->get_records_select(
            'user',
            $DB->sql_like('username', ':prefix') . " AND deleted = 0 AND suspended = 0",
            ['prefix' => $prefix . '%']
        );
        foreach ($users as $user) {
            // Assign only if not already assigned.
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
