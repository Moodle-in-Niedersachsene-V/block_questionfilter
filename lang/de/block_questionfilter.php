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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * German language strings for block_questionfilter.
 *
 * @package    block_questionfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action_approve'] = 'Freigeben';
$string['action_approveall'] = 'Alle {$a} freigeben';
$string['action_reject'] = 'Ablehnen';
$string['action_rejectall'] = 'Alle ablehnen';
$string['addtoquiz'] = 'Zum Test';
$string['ai_backend_claude'] = 'Anthropic Claude API';
$string['ai_backend_ollama'] = 'Ollama (lokal)';
$string['approved'] = 'Tag-Vorschlag wurde freigegeben.';
$string['approved_all'] = '{$a} Tag-Vorschläge wurden freigegeben.';
$string['block_questionfilter:addinstance'] = 'Block „Fragebank-Filter" hinzufügen';
$string['block_questionfilter:export'] = 'Fragen exportieren';
$string['block_questionfilter:myaddinstance'] = 'Block „Fragebank-Filter" zum Dashboard hinzufügen';
$string['block_questionfilter:reviewtags'] = 'KI-Tag-Vorschläge prüfen und freigeben';
$string['block_questionfilter:view'] = 'Fragebank-Filter-Block verwenden';
$string['categories'] = 'Fragesammlungen';
$string['col_actions'] = 'Aktionen';
$string['col_question'] = 'Frage';
$string['col_tags'] = 'Tags';
$string['col_type'] = 'Typ';
$string['confirm_approveall'] = 'Wirklich alle ausstehenden Vorschläge freigeben?';
$string['confirm_rejectall'] = 'Wirklich alle ausstehenden Vorschläge ablehnen?';
$string['coresystem'] = 'Systemweit';
$string['difficulty'] = 'Niveaustufen';
$string['entertosearch'] = 'Filter setzen, um Fragen zu suchen.';
$string['error_ai_answer_invalid'] = 'KI-Antwort enthält kein gültiges JSON-Objekt: {$a}';
$string['error_ai_request_failed'] = 'KI-Anfrage fehlgeschlagen: {$a}';
$string['error_ai_tagging_failed'] = 'KI-Verschlagwortung: {$a->failed} von {$a->total} Fragen konnten nicht verarbeitet werden.';
$string['error_ai_unavailable'] = 'KI-Verschlagwortung ist aktiviert, aber kein KI-Anbieter ist in Moodle konfiguriert (Website-Administration → KI → KI-Anbieter).';
$string['error_preview_needs_post'] = 'Starte die Vorschau über die Vorschau-Schaltfläche.';
$string['export_csv'] = 'CSV-Tabelle';
$string['export_gift'] = 'GIFT-Format';
$string['export_xml'] = 'Moodle XML';
$string['exportfailed'] = 'Export fehlgeschlagen. Bitte Moodle-Logs prüfen.';
$string['invalidquestion'] = 'Frage nicht gefunden.';
$string['loading'] = 'Wird geladen …';
$string['loadingcategories'] = 'Sammlungen werden geladen …';
$string['no_tags'] = '(keine Tags)';
$string['nopermission'] = 'Sie haben keine Berechtigung, die Fragebank einzusehen.';
$string['noquestionsselected'] = 'Keine Fragen ausgewählt.';
$string['nosessionhash'] = 'Kein Session-Hash fuer Gast-Bewertung uebermittelt.';
$string['pluginname'] = 'Fragebank-Filter';
$string['preview_back_btn'] = 'Zurück zur Startseite';
$string['preview_expectedanswer'] = 'Erwartete Antwort';
$string['preview_nopooluser'] = 'Vorschau nicht verfügbar: Kein Gast-Account im Pool gefunden. Bitte wenden Sie sich an den Administrator.';
$string['preview_open_btn'] = 'Vorschau öffnen:';
$string['preview_ready_desc'] = 'Sie sind jetzt angemeldet. Öffnen Sie die Vorschau und kehren Sie danach zur Startseite zurück um Fragen zu exportieren.';
$string['preview_ready_title'] = 'Fragenvorschau bereit';
$string['privacy:metadata'] = 'Der Fragebank-Filter-Block speichert keine personenbezogenen Daten. Export-Dateien werden nur temporär erzeugt und nicht dauerhaft gespeichert.';
$string['qtypes_existing'] = 'Nur Typen mit vorhandenen Fragen';
$string['qtypes_installed'] = 'Alle installierten Fragetypen';
$string['questionfilter:addinstance'] = 'Block „Fragebank-Filter" hinzufügen';
$string['questionfilter:export'] = 'Fragen exportieren';
$string['questionfilter:myaddinstance'] = 'Block „Fragebank-Filter" zum Dashboard hinzufügen';
$string['questionfilter:view'] = 'Fragebank-Filter-Block verwenden';
$string['questiontypes'] = 'Fragetyp';
$string['ratelimitexceeded'] = 'Zu viele Anfragen. Bitte warten Sie {$a->seconds} Sekunden.';
$string['rejected'] = 'Tag-Vorschlag wurde abgelehnt.';
$string['rejected_all'] = 'Alle ausstehenden Tag-Vorschläge wurden abgelehnt.';
$string['review_empty'] = 'Keine ausstehenden Tag-Vorschläge vorhanden.';
$string['review_title'] = 'KI-Tag-Vorschläge prüfen';
$string['role_preview_desc'] = 'Automatisch angelegte Rolle fuer Pool-Nutzer des Fragebank-Filter-Blocks. Erlaubt Fragenvorschau (moodle/question:usemine) und Export (block/questionfilter:export).';
$string['role_preview_name'] = 'Fragenvorschau-Gast';
$string['scope_all'] = 'Alle Fragesammlungen (kursübergreifend)';
$string['scope_course'] = 'Nur aktueller Kurs';
$string['scope_system'] = 'Nur systemweite Fragebank';
$string['searchplaceholder'] = 'Suche … oder #tag eingeben + Enter';
$string['selectall'] = 'Alle wählen';
$string['settings_ai_autoapply'] = 'Vorschläge automatisch übernehmen';
$string['settings_ai_autoapply_help'] = 'Wenn aktiv, werden KI-Vorschläge sofort als Moodle-Tags eingetragen. Andernfalls werden sie zur manuellen Prüfung gespeichert.';
$string['settings_ai_backend'] = 'KI-Backend';
$string['settings_ai_backend_desc'] = 'Wählen Sie, ob Ollama (lokal) oder die Anthropic Claude API für die Tag-Generierung verwendet wird.';
$string['settings_ai_backend_heading'] = 'KI-Backend-Auswahl';
$string['settings_ai_backend_help'] = 'Ollama läuft lokal auf dem Server. Claude API erfordert einen Anthropic-API-Schlüssel.';
$string['settings_ai_batch_size'] = 'Fragen pro Durchlauf';
$string['settings_ai_batch_size_help'] = 'Maximale Anzahl Fragen, die bei einem Task-Durchlauf verarbeitet werden (Standard: 20).';
$string['settings_ai_claude_apikey'] = 'Claude API-Schlüssel';
$string['settings_ai_claude_apikey_help'] = 'Ihr Anthropic API-Schlüssel (beginnt mit „sk-ant-…"). Wird sicher gespeichert und nur für KI-Anfragen verwendet.';
$string['settings_ai_claude_desc'] = 'Geben Sie Ihren Anthropic API-Schlüssel und das gewünschte Modell ein. Fragen werden anonymisiert (kein Autorenname, keine Moodle-IDs) übermittelt.';
$string['settings_ai_claude_heading'] = 'Claude API-Einstellungen';
$string['settings_ai_claude_model'] = 'Claude-Modell';
$string['settings_ai_claude_model_help'] = 'Modellbezeichnung, z. B. claude-haiku-4-5-20251001. Haiku ist schnell und kostengünstig für Tag-Generierung.';
$string['settings_ai_desc'] = 'Automatische Tag-Vorschläge für Fragen. Direkter Zugriff auf Ollama oder die Anthropic Claude API – kein Moodle core_ai erforderlich.';
$string['settings_ai_enabled'] = 'KI-Verschlagwortung aktivieren';
$string['settings_ai_enabled_help'] = 'Aktiviert den geplanten Task, der täglich Tag-Vorschläge für nicht verschlagwortete Fragen generiert.';
$string['settings_ai_heading'] = 'KI-Verschlagwortung';
$string['settings_ai_max_tags'] = 'Maximale Tag-Anzahl';
$string['settings_ai_max_tags_help'] = 'Wie viele Tags die KI pro Frage maximal vorschlagen darf (Standard: 5).';
$string['settings_ai_ollama_heading'] = 'Ollama-Einstellungen';
$string['settings_ai_ollama_model'] = 'Ollama-Modell';
$string['settings_ai_ollama_model_help'] = 'Name des Ollama-Modells, z. B. gemma3:4b oder llama3.2. Das Modell muss auf dem Ollama-Server verfügbar sein.';
$string['settings_ai_ollama_url'] = 'Ollama-URL';
$string['settings_ai_ollama_url_help'] = 'Basis-URL des Ollama-Servers, z. B. http://localhost:11434. Kein abschließender Schrägstrich.';
$string['settings_ai_tag_categories'] = 'Fachgebiete';
$string['settings_ai_tag_categories_help'] = 'Mögliche Fachgebiete für die KI (eine pro Zeile). Die KI wählt die passenden aus und ignoriert nicht zutreffende.';
$string['settings_ai_tagging_heading'] = 'Tagging-Optionen';
$string['settings_custom_tags'] = 'Vorgeschlagene Tags';
$string['settings_custom_tags_help'] = 'Kommagetrennte Liste von Tags, die im Block als Schnellfilter angeboten werden.';
$string['settings_customfields_heading'] = 'Benutzerdefinierte Tags';
$string['settings_difficulty_desc'] = 'Niveaustufen werden als Tag-Filter angeboten. Erweiterbar – eine Stufe pro Zeile.';
$string['settings_difficulty_heading'] = 'Niveaustufen';
$string['settings_difficulty_levels'] = 'Niveaustufen';
$string['settings_difficulty_levels_help'] = 'Eine Stufe pro Zeile, z. B. Leicht, Mittel, Schwer. Beliebig erweiterbar.';
$string['settings_export_heading'] = 'Export-Formate';
$string['settings_exportformats'] = 'Verfügbare Formate';
$string['settings_exportformats_help'] = 'Welche Export-Formate im Block angezeigt werden.';
$string['settings_questiontypes_heading'] = 'Fragetypen';
$string['settings_questiontypes_source'] = 'Angezeigte Fragetypen';
$string['settings_questiontypes_source_help'] = 'Alle installierten: zeigt alle qtype-Plugins. Nur vorhandene: zeigt nur Typen die tatsächlich Fragen in der Bank haben.';
$string['settings_resultlimit'] = 'Max. Ergebnisse';
$string['settings_resultlimit_help'] = 'Maximale Anzahl Fragen, die pro Suchanfrage zurückgegeben werden (Standard: 200).';
$string['settings_review_heading'] = 'Tag-Vorschläge prüfen';
$string['settings_review_link'] = 'Zur Prüf- und Freigabe-Seite';
$string['settings_searchscope'] = 'Suchbereich';
$string['settings_searchscope_desc'] = 'Legt fest, über welche Fragesammlungen der Block sucht.';
$string['settings_searchscope_heading'] = 'Suchbereich';
$string['settings_searchscope_help'] = 'Alle: system- und kursübergreifend. Kurs: nur der aktuelle Kurs. System: nur die systemweite Fragebank.';
$string['stat_approved'] = 'Freigegeben: {$a}';
$string['stat_pending'] = 'Ausstehend: {$a}';
$string['stat_rejected'] = 'Abgelehnt: {$a}';
$string['tagplaceholder'] = 'Tag eingeben und Enter …';
$string['tags'] = 'Tags';
$string['task_generate_tags'] = 'KI-Verschlagwortung für Fragen';
