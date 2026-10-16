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

namespace block_questionfilter;

/**
 * Pool account guards for installations without Kursfilter.
 *
 * @package    block_questionfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_callbacks {
    /**
     * Delegates pool restrictions to Kursfilter or applies the standalone guards.
     *
     * @param \core\hook\after_config $hook Configuration hook.
     */
    public static function after_config(\core\hook\after_config $hook): void {
        global $CFG, $USER;

        // Only act on real pool accounts.
        if (!isloggedin() || isguestuser()) {
            return;
        }

        // Kursfilter owns pool restrictions when installed; avoid duplicate guards.
        if (class_exists('\block_kursfilter\pool_manager')) {
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
}
