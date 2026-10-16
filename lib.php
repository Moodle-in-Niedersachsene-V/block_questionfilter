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
 * Bibliotheksfunktionen des Plugins block_questionfilter.
 *
 * @package    block_questionfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @author     Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Called by Moodle after config.php is loaded (every request).
 *
 * Delegates pool-user restrictions entirely to block_kursfilter when that
 * plugin is installed (it already handles messaging suppression and URL
 * redirects for pool users). If block_kursfilter is not present, this
 * callback applies the same guards independently so pool users cannot reach
 * profile, preferences, messaging or similar pages even when only
 * block_questionfilter is installed.
 */
function block_questionfilter_after_config(): void {
    global $CFG, $USER;

    // Only act on real pool users.
    if (!isloggedin() || isguestuser()) {
        return;
    }

    // If block_kursfilter is installed its own after_config already ran;
    // Its is_pool_user() check covers the same accounts, so avoid duplicate work.
    if (class_exists('\block_kursfilter\pool_manager')) {
        // Block_kursfilter handles everything — nothing to do here.
        return;
    }

    // Standalone guard: mirror block_kursfilter logic using the shared username prefix.
    $prefix = 'kursfilter_pool';
    if (empty($USER->username) || strpos($USER->username, $prefix) !== 0) {
        return;
    }

    // Suppress messaging icon for this session.
    $CFG->messaging = false;

    // Redirect blocked paths to site home.
    $blockedprefixes = [
        '/user/profile.php',
        '/user/edit.php',
        '/user/preferences.php',
        '/user/managefiles.php',
        '/message/',
        '/calendar/',
        '/grade/report/overview/',
        '/grade/report/user/',
        '/badges/',
        '/blog/',
        '/my/',
        '/user/files.php',
    ];

    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $wwwroot = rtrim(parse_url($CFG->wwwroot, PHP_URL_PATH) ?? '', '/');
    if ($wwwroot !== '' && strpos($script, $wwwroot) === 0) {
        $script = substr($script, strlen($wwwroot));
    }

    foreach ($blockedprefixes as $prefix) {
        if (strpos($script, $prefix) === 0) {
            header('Location: ' . $CFG->wwwroot . '/');
            exit;
        }
    }
}

/**
 * Datei-Download-Handler fuer Export-Dateien.
 *
 * Wird von Moodle automatisch aufgerufen bei
 * /pluginfile.php/.../block_questionfilter/export/...
 *
 * @param stdClass $course Kursobjekt.
 * @param stdClass $cm Kursmodulobjekt.
 * @param context $context Kontext der Datei.
 * @param string $filearea Dateibereich.
 * @param array $args Restliche Pfadbestandteile.
 * @param bool $forcedownload Ob der Download erzwungen wird.
 * @param array $options Zusaetzliche Optionen fuer die Auslieferung.
 * @return void
 */
function block_questionfilter_pluginfile(
    $course,
    $cm,
    $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = []
): void {
    require_login();

    if ($filearea !== 'export') {
        send_file_not_found();
    }

    if (!has_capability('moodle/question:viewall', context_system::instance())) {
        send_file_not_found();
    }

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'block_questionfilter', 'export', $itemid, $filepath, $filename);

    if (!$file) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, true, $options);
}
