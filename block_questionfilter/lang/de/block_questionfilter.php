<?php
defined('MOODLE_INTERNAL') || die();

// Plugin-Name
$string['pluginname'] = 'Fragebank-Filter';
$string['block_questionfilter:addinstance'] = 'Block „Fragebank-Filter" hinzufügen';
$string['block_questionfilter:myaddinstance'] = 'Block „Fragebank-Filter" zum Dashboard hinzufügen';

// UI-Texte
$string['searchplaceholder']  = 'Frage oder Tag suchen …';
$string['categories']         = 'Fragesammlungen';
$string['questiontypes']      = 'Fragetyp';
$string['difficulty']         = 'Niveaustufen';
$string['tags']               = 'Tags';
$string['tagplaceholder']     = 'Tag eingeben und Enter …';
$string['selectall']          = 'Alle wählen';
$string['addtoquiz']          = 'Zum Test';
$string['loading']            = 'Wird geladen …';
$string['loadingcategories']  = 'Sammlungen werden geladen …';
$string['entertosearch']      = 'Filter setzen, um Fragen zu suchen.';
$string['nopermission']       = 'Sie haben keine Berechtigung, die Fragebank einzusehen.';
$string['noquestionsselected']= 'Keine Fragen ausgewählt.';
$string['coresystem']         = 'Systemweit';

// Export
$string['export_xml']  = 'Moodle XML';
$string['export_csv']  = 'CSV-Tabelle';
$string['export_gift'] = 'GIFT-Format';

// Admin-Einstellungen
$string['settings_searchscope_heading'] = 'Suchbereich';
$string['settings_searchscope_desc']    = 'Legt fest, über welche Fragesammlungen der Block sucht.';
$string['settings_searchscope']         = 'Suchbereich';
$string['settings_searchscope_help']    = 'Alle: system- und kursübergreifend. Kurs: nur der aktuelle Kurs. System: nur die systemweite Fragebank.';

$string['scope_all']    = 'Alle Fragesammlungen (kursübergreifend)';
$string['scope_course'] = 'Nur aktueller Kurs';
$string['scope_system'] = 'Nur systemweite Fragebank';

$string['settings_difficulty_heading']      = 'Niveaustufen';
$string['settings_difficulty_desc']         = 'Niveaustufen werden als Tag-Filter angeboten. Erweiterbar – eine Stufe pro Zeile.';
$string['settings_nivelevel_desc']         = 'Die Stufen werden als Tag-Filter angeboten. Erweiterbar – eine Stufe pro Zeile.';
$string['settings_difficulty_levels']       = 'Niveaustufen';
$string['settings_difficulty_levels_help']  = 'Eine Stufe pro Zeile, z. B. Leicht, Mittel, Schwer. Beliebig erweiterbar.';

$string['settings_customfields_heading']    = 'Benutzerdefinierte Tags';
$string['settings_custom_tags']             = 'Vorgeschlagene Tags';
$string['settings_custom_tags_help']        = 'Kommagetrennte Liste von Tags, die im Block als Schnellfilter angeboten werden.';

$string['settings_export_heading']          = 'Export-Formate';
$string['settings_exportformats']           = 'Verfügbare Formate';
$string['settings_exportformats_help']      = 'Welche Export-Formate im Block angezeigt werden.';

$string['settings_resultlimit']             = 'Max. Ergebnisse';
$string['settings_resultlimit_help']        = 'Maximale Anzahl Fragen, die pro Suchanfrage zurückgegeben werden (Standard: 200).';

// Capabilities
$string['block_questionfilter:view']   = 'Fragebank-Filter-Block verwenden';
$string['block_questionfilter:export'] = 'Fragen exportieren';
$string['exportfailed'] = 'Export fehlgeschlagen. Bitte Moodle-Logs prüfen.';

// Fragetypen-Quelle
$string['settings_questiontypes_heading']      = 'Fragetypen';
$string['settings_questiontypes_source']       = 'Angezeigte Fragetypen';
$string['settings_questiontypes_source_help']  = 'Alle installierten: zeigt alle qtype-Plugins. Nur vorhandene: zeigt nur Typen die tatsächlich Fragen in der Bank haben.';
$string['qtypes_installed'] = 'Alle installierten Fragetypen';
$string['qtypes_existing']  = 'Nur Typen mit vorhandenen Fragen';

// Capability-Strings ohne block_-Präfix (von Moodle accesslib benötigt)
$string['questionfilter:view']         = 'Fragebank-Filter-Block verwenden';
$string['questionfilter:export']       = 'Fragen exportieren';
$string['questionfilter:addinstance']  = 'Block „Fragebank-Filter" hinzufügen';
$string['questionfilter:myaddinstance']= 'Block „Fragebank-Filter" zum Dashboard hinzufügen';
$string['privacy:metadata'] = 'Der Fragebank-Filter-Block speichert keine personenbezogenen Daten. Export-Dateien werden nur temporär erzeugt und nicht dauerhaft gespeichert.';
