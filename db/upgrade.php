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
 * Upgrade-Schritte des Plugins block_questionfilter.
 *
 * @package    block_questionfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @author     Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Fuehrt die Upgrade-Schritte des Plugins aus.
 *
 * @param int $oldversion Bisher installierte Version.
 * @return bool Immer true.
 */
function xmldb_block_questionfilter_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // Werte alter Konfigurationsschluessel auf den aktuellen Schluessel uebertragen.
    $migrations = [
        'nivelevel_levels' => 'difficulty_levels',
        'nivelevel_values' => 'difficulty_levels',
    ];
    foreach ($migrations as $oldkey => $newkey) {
        $oldval = get_config('block_questionfilter', $oldkey);
        if ($oldval !== false) {
            $newval  = get_config('block_questionfilter', $newkey);
            $default = "Leicht\nMittel\nSchwer";
            if ($newval === false || trim($newval) === trim($default)) {
                set_config($newkey, $oldval, 'block_questionfilter');
            }
            unset_config($oldkey, 'block_questionfilter');
        }
    }

    // Build 2026080501: Bewertungstabelle anlegen.
    if ($oldversion < 2026080501) {
        $table = new xmldb_table('block_questionfilter_ratings');
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
        upgrade_block_savepoint(true, 2026080501, 'questionfilter');
    }

    return true;
}
