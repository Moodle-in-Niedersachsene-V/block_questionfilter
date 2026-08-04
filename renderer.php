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
 * Renderer des Plugins block_questionfilter.
 *
 * @package    block_questionfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @author     Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Renderer des Fragebank-Filters.
 *
 * @package    block_questionfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_questionfilter_renderer extends plugin_renderer_base {
    /**
     * Rendert den Blockinhalt aus dem Mustache-Template.
     *
     * @param int $blockid ID der Blockinstanz.
     * @param bool $canexport Ob der Nutzer exportieren darf.
     * @return string Gerendertes HTML.
     */
    public function render_block(int $blockid, bool $canexport = false): string {
        // Konfigurierbare Niveaustufen aus den Administrationseinstellungen.
        $diffraw = get_config('block_questionfilter', 'difficulty_levels') ?? "Leicht\nMittel\nSchwer";
        $difficulties = array_filter(array_map('trim', explode("\n", $diffraw)));

        $exportformats = get_config('block_questionfilter', 'exportformats');
        $fmts = is_array($exportformats) ? array_keys(array_filter($exportformats)) : ['xml', 'csv', 'gift'];

        $templatedata = [
            'blockid' => $blockid,
            'difficulties' => array_values($difficulties),
            'export_xml' => $canexport && in_array('xml', $fmts),
            'export_csv' => $canexport && in_array('csv', $fmts),
            'export_gift' => $canexport && in_array('gift', $fmts),
            'canexport' => $canexport,
            'scope_all' => (get_config('block_questionfilter', 'searchscope') ?: 'all') === 'all',
        ];

        return $this->render_from_template('block_questionfilter/block', $templatedata);
    }
}
