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

namespace block_fragenfilter;

/**
 * The preview role of the pool accounts of block_kursfilter.
 *
 * The role allows the question preview and the export (ADR 0002). Block_kursfilter owns
 * the pool accounts; this class only gives them the role.
 *
 * @package    block_fragenfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class preview_role {
    /** Role shortname. */
    public const SHORTNAME = 'fragenfilter_preview';

    /**
     * Create the preview role if missing.
     *
     * The role only allows moodle/question:usemine and the export, so pool accounts can open
     * the question preview without receiving additional permissions. An existing role is
     * left unchanged, because administrators may have adjusted its capabilities.
     *
     * @return int Role ID.
     */
    public static function ensure(): int {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => self::SHORTNAME]);
        if ($roleid) {
            return (int)$roleid;
        }
        // Capabilities from db/access.php are not yet in the database during installation.
        update_capabilities('block_fragenfilter');
        $roleid = create_role(
            get_string('role_preview_name', 'block_fragenfilter'),
            self::SHORTNAME,
            get_string('role_preview_desc', 'block_fragenfilter'),
            ''
        );
        $systemcontext = \context_system::instance();
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        assign_capability('moodle/question:usemine', CAP_ALLOW, $roleid, $systemcontext->id, true);
        assign_capability('block/fragenfilter:export', CAP_ALLOW, $roleid, $systemcontext->id, true);
        return (int)$roleid;
    }

    /**
     * Whether the account is a pool account of block_kursfilter.
     *
     * Pool accounts are confirmed manual accounts with a pool username. A self-registered
     * account with such a name is unconfirmed and uses another authentication.
     *
     * @param \stdClass $user User record.
     * @return bool
     */
    public static function is_pool_account(\stdClass $user): bool {
        return $user->auth === 'manual'
            && !empty($user->confirmed)
            && empty($user->deleted)
            && in_array(
                $user->username,
                \block_kursfilter\pool_manager::get_pool_usernames(\block_kursfilter\pool_manager::POOL_SIZE_MAX),
                true
            );
    }

    /**
     * Give the role to a pool account; other accounts and accounts holding the role stay unchanged.
     *
     * @param \stdClass $user User record.
     */
    public static function assign_to_pool_account(\stdClass $user): void {
        if (!self::is_pool_account($user)) {
            return;
        }
        $roleid = self::ensure();
        $contextid = \context_system::instance()->id;
        if (!user_has_role_assignment($user->id, $roleid, $contextid)) {
            role_assign($roleid, $user->id, $contextid);
        }
    }

    /**
     * Give the role to all existing pool accounts, up to the hard cap of the pool size.
     */
    public static function assign_to_pool_accounts(): void {
        global $DB;

        self::ensure();
        $usernames = \block_kursfilter\pool_manager::get_pool_usernames(\block_kursfilter\pool_manager::POOL_SIZE_MAX);
        foreach ($DB->get_records_list('user', 'username', $usernames) as $user) {
            self::assign_to_pool_account($user);
        }
    }
}
