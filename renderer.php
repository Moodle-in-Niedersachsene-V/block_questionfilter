<?php
// This file is part of Moodle - https://moodle.org/
// Copyright: 2026 Moodle in Niedersachsen e. V.
// License:   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

class block_questionfilter_renderer extends plugin_renderer_base {

    public function render_block(int $blockid, bool $canExport = false): string {
        // Konfigurierbare Niveaustufen aus Admin-Settings
        $diffRaw      = get_config('block_questionfilter', 'difficulty_levels') ?? "Leicht\nMittel\nSchwer";
        $difficulties = array_filter(array_map('trim', explode("\n", $diffRaw)));

        $exportformats = get_config('block_questionfilter', 'exportformats');
        $fmts = is_array($exportformats) ? array_keys(array_filter($exportformats)) : ['xml', 'csv', 'gift'];

        $templatedata = [
            'blockid'      => $blockid,
            'difficulties' => array_values($difficulties),
            'export_xml'   => $canExport && in_array('xml',  $fmts),
            'export_csv'   => $canExport && in_array('csv',  $fmts),
            'export_gift'  => $canExport && in_array('gift', $fmts),
            'canexport'    => $canExport,
            'scope_all'    => (get_config('block_questionfilter', 'searchscope') ?: 'all') === 'all',
        ];

        return $this->render_from_template('block_questionfilter/block', $templatedata);
    }
}
