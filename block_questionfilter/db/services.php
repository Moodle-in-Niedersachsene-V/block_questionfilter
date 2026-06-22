<?php
defined('MOODLE_INTERNAL') || die();

$functions = [

    'block_questionfilter_search_questions' => [
        'classname'      => 'block_questionfilter_external',
        'methodname'     => 'search_questions',
        'description'    => 'Sucht Testfragen über mehrere Fragesammlungen',
        'type'           => 'read',
        'ajax'           => true,
        'loginrequired'  => false,   // Gäste erlaubt — Capability-Prüfung in der Methode
        'capabilities'   => 'block/questionfilter:view',
    ],

    'block_questionfilter_get_categories' => [
        'classname'      => 'block_questionfilter_external',
        'methodname'     => 'get_categories',
        'description'    => 'Lädt alle durchsuchbaren Fragekategorien',
        'type'           => 'read',
        'ajax'           => true,
        'loginrequired'  => false,   // Gäste erlaubt
        'capabilities'   => 'block/questionfilter:view',
    ],

    'block_questionfilter_export_questions' => [
        'classname'      => 'block_questionfilter_external',
        'methodname'     => 'export_questions',
        'description'    => 'Exportiert ausgewählte Fragen (XML, CSV, GIFT)',
        'type'           => 'read',
        'ajax'           => true,
        'loginrequired'  => true,    // Export nur für angemeldete Nutzer mit export-Capability
        'capabilities'   => 'block/questionfilter:export',
    ],
];
