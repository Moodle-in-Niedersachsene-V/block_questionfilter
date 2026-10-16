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
 * Upgrade steps for block_fragenfilter.
 *
 * @package    block_fragenfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @author     Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs the plugin's upgrade steps.
 *
 * @param int $oldversion Previously installed version.
 * @return bool Always true.
 */
function xmldb_block_fragenfilter_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // Transfer values from old configuration keys to the current keys.
    $migrations = [
        'nivelevel_levels' => 'difficulty_levels',
        'nivelevel_values' => 'difficulty_levels',
    ];
    foreach ($migrations as $oldkey => $newkey) {
        $oldval = get_config('block_fragenfilter', $oldkey);
        if ($oldval !== false) {
            $newval  = get_config('block_fragenfilter', $newkey);
            $default = "Leicht\nMittel\nSchwer";
            if ($newval === false || trim($newval) === trim($default)) {
                set_config($newkey, $oldval, 'block_fragenfilter');
            }
            unset_config($oldkey, 'block_fragenfilter');
        }
    }

    // Build 2026080501: create the ratings table.
    if ($oldversion < 2026080501) {
        $table = new xmldb_table('block_fragenfilter_ratings');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null);
            $table->add_field('sessionhash', XMLDB_TYPE_CHAR, '64', null);
            $table->add_field('rating', XMLDB_TYPE_INTEGER, '1', XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_question', XMLDB_KEY_FOREIGN, ['questionid'], 'question', ['id']);
            $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('idx_userid_question', XMLDB_INDEX_NOTUNIQUE, ['userid', 'questionid']);
            $table->add_index('idx_session_question', XMLDB_INDEX_NOTUNIQUE, ['sessionhash', 'questionid']);
            $dbman->create_table($table);
        }
        upgrade_block_savepoint(true, 2026080501, 'fragenfilter');
    }

    // Build 2026090514: create the question preview role and give it to the pool accounts.
    // The later role steps repeat the idempotent assignment, because each savepoint must stand on its own.
    if ($oldversion < 2026090514) {
        \block_fragenfilter\preview_role::assign_to_pool_accounts();
        upgrade_block_savepoint(true, 2026090514, 'fragenfilter');
    }

    // Build 2026090524: add the export capability to the preview role.
    if ($oldversion < 2026090524) {
        $roleshortname = 'fragenfilter_preview';
        $sysctx = context_system::instance();
        $role = $DB->get_record('role', ['shortname' => $roleshortname]);
        if ($role) {
            assign_capability('block/fragenfilter:export', CAP_ALLOW, $role->id, $sysctx->id, true);
        }
        upgrade_block_savepoint(true, 2026090524, 'fragenfilter');
    }

    // Build 2026101101: create the AI tag suggestions table.
    if ($oldversion < 2026101101) {
        $table = new xmldb_table('block_fragenfilter_tag_pending');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL);
            $table->add_field('tags', XMLDB_TYPE_TEXT, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '10', XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_questionid', XMLDB_INDEX_UNIQUE, ['questionid']);
            $table->add_index('idx_status', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $dbman->create_table($table);
        }
        upgrade_block_savepoint(true, 2026101101, 'fragenfilter');
    }

    // Build 2026101601: Assign the preview role to renamed Kursfilter pool accounts (kursfilter_pool*).
    if ($oldversion < 2026101601) {
        \block_fragenfilter\preview_role::assign_to_pool_accounts();
        upgrade_block_savepoint(true, 2026101601, 'fragenfilter');
    }

    // Build 2026101603: The preview role goes to all pool accounts of block_kursfilter, including later created ones.
    if ($oldversion < 2026101603) {
        \block_fragenfilter\preview_role::assign_to_pool_accounts();
        upgrade_block_savepoint(true, 2026101603, 'fragenfilter');
    }

    return true;
}
