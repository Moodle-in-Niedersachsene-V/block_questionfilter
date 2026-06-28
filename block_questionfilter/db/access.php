<?php
// This file is part of Moodle - https://moodle.org/
// Copyright: 2026 Moodle in Niedersachsen e. V.
// License:   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Berechtigung den Block zu sehen und zu nutzen.
    // Kann für Gäste aktiviert werden: Website-Administration → Nutzer → Rollen → Gast → block/questionfilter:view erlauben
    'block/questionfilter:view' => [
        'captype'     => 'read',
        'contextlevel'=> CONTEXT_SYSTEM,
        'archetypes'  => [
            'guest'          => CAP_ALLOW,   // Standard: Gäste dürfen suchen
            'user'           => CAP_ALLOW,
            'student'        => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // Export nur für Lehrkräfte aufwärts
    'block/questionfilter:export' => [
        'captype'     => 'read',
        'contextlevel'=> CONTEXT_SYSTEM,
        'archetypes'  => [
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'block/questionfilter:addinstance' => [
        'riskbitmask' => RISK_SPAM,
        'captype'     => 'write',
        'contextlevel'=> CONTEXT_BLOCK,
        'archetypes'  => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'block/questionfilter:myaddinstance' => [
        'captype'     => 'write',
        'contextlevel'=> CONTEXT_SYSTEM,
        'archetypes'  => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
];

// Hinweis für Moodle 5.0+:
// Die neuen Capabilities moodle/question:useall und moodle/question:usemine
// (kursübergreifendes Teilen von Fragen) müssen für die Rolle "Non-editing teacher"
// manuell aktiviert werden (Site-Administration → Nutzer → Rollen definieren),
// sofern das System von Moodle 4.x auf 5.x migriert wurde.
// Bei Neuinstallationen ab 5.1 sind sie standardmäßig erlaubt.
