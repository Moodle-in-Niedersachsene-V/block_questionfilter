<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_block_questionfilter_upgrade(int $oldversion): bool {
    // Migration: Falls alte Keys aus früheren Versionen noch existieren,
    // Werte auf den aktuellen Key übertragen und alten Key löschen.
    $migrations = [
        'nivelevel_levels'  => 'difficulty_levels',   // v2026062205 → v2026062206 Umbenennung rückgängig
        'nivelevel_values'  => 'difficulty_levels',   // alternative Schreibweise
    ];

    foreach ($migrations as $oldKey => $newKey) {
        $oldVal = get_config('block_questionfilter', $oldKey);
        if ($oldVal !== false) {
            // Nur übertragen wenn der neue Key noch den Standard-Wert hat
            $newVal = get_config('block_questionfilter', $newKey);
            $default = "Leicht\nMittel\nSchwer";
            if ($newVal === false || trim($newVal) === trim($default)) {
                set_config($newKey, $oldVal, 'block_questionfilter');
            }
            unset_config($oldKey, 'block_questionfilter');
        }
    }

    return true;
}
