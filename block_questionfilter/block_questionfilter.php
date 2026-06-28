<?php
// This file is part of Moodle - https://moodle.org/
// Copyright: 2026 Moodle in Niedersachsen e. V.
// License:   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

class block_questionfilter extends block_base {

    public function init(): void {
        $this->title = get_string('pluginname', 'block_questionfilter');
    }

    public function applicable_formats(): array {
        return [
            'site-index' => true,   // Startseite
            'course-view' => true,  // Kursseiten
            'my' => true,           // Dashboard
        ];
    }

    public function has_config(): bool {
        return true;
    }

    public function instance_allow_multiple(): bool {
        return false;
    }

    public function get_content(): stdClass {
        global $PAGE, $USER, $CFG;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();

        // Eigene View-Capability — kann für Gäste aktiviert werden
        if (!has_capability('block/questionfilter:view', context_system::instance())) {
            $this->content->text = get_string('nopermission', 'block_questionfilter');
            return $this->content;
        }

        // Export-Berechtigung an JS übergeben
        $canExport = has_capability('block/questionfilter:export', context_system::instance());

        // AMD-Modul laden
        $PAGE->requires->js_call_amd('block_questionfilter/filter', 'init', [
            [
                'blockid'       => (int)$this->instance->id,
                'contextid'     => (int)$PAGE->context->id,
                'searchscope'   => get_config('block_questionfilter', 'searchscope')        ?: 'all',
                'exportformats' => get_config('block_questionfilter', 'exportformats')      ?: 'xml,csv,gift',
                'qtypessource'  => get_config('block_questionfilter', 'questiontypes_source') ?: 'installed',
                'wwwroot'       => $CFG->wwwroot,
                'canexport'     => $canExport,
            ]
        ]);

        $renderer = $PAGE->get_renderer('block_questionfilter');
        $this->content->text = $renderer->render_block((int)$this->instance->id, $canExport);

        return $this->content;
    }
}
