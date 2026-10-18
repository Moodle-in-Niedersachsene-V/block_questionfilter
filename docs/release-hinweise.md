# Hinweise für den Deploy (Friedhelm)

## Zusammenfassung

- **Umbenennung:** Komponente heißt jetzt `block_fragenfilter` (früher `block_questionfilter`). Zip: `block_fragenfilter_<build>.zip`, Wurzelordner `fragenfilter/`. Moodle sieht das als neues Plugin; das alte muss vorher deinstalliert werden.
- **Kursfilter:** `block_kursfilter` mindestens Build 2026101501 (Stand `main`, inkl. #22/#23), zusammen mit diesem Release.
- **Build-Nummern:** live `block_questionfilter` 2026101211, `block_kursfilter` 2026101028. Neu: `block_fragenfilter` 2026101702, `block_kursfilter` 2026101501. Beide höher als live.
- **Upgrade-Schritte:** laufen nicht automatisch. Beim Hochladen im File-Picker jeden Teilschritt mit „OK“ bestätigen (kein Konsolen-Upgrade nötig).
- **Datenschutz:** kein AV-Vertrag nötig, siehe `docs/datenschutz.md`.

## Was beim Umbenennen verloren geht

- Es gibt keine Instanz-Einstellungen am Block (kein `edit_form`), nur die **Website-Einstellungen** des Plugins. Beim Deinstallieren werden sie gelöscht, also vorher notieren.
- **Bleibt:** bereits angewendete Tags (liegen in den Moodle-Tags `core_tag`).
- **Geht verloren:** die Tabellen `…_tag_pending` (KI-Vorschläge, auch abgelehnte) und `…_ratings` (Bewertungen). Danach verarbeitet der Cron-Task `generate_tags` alle Fragen erneut (in Batches). Bereits gesetzte Tags werden nur ergänzt. Abgelehnte Vorschläge kommen wieder. Das kostet Claude-Guthaben (keine automatische Aufladung). Da noch nichts produktiv genutzt wird, ist das in Ordnung. Wer den Neulauf nicht will: vor dem Deinstallieren `ai_enabled` ausschalten und erst nach der Prüfung wieder einschalten.

## Ablauf (Schritt für Schritt)

1. **Sichern:** Datenbank-Backup. Einstellungen des alten Blocks notieren (Screenshot): Website-Administration → Plugins → Blöcke → Fragenfilter. Wichtig: KI-Backend, Claude-API-Key, Batchgröße, Auto-Apply, Rollen/Fächerlisten.
2. **Alten Block von der Startseite entfernen** (Seite bearbeiten → Block löschen). Optional, es geht auch beim Deinstallieren.
3. **Altes Plugin deinstallieren:** Website-Administration → Plugins → Plugin-Übersicht → `block_questionfilter` → Deinstallieren.
4. **Neue Plugins hochladen:** Plugin installieren (File-Picker) mit `block_fragenfilter_2026101702.zip` und `block_kursfilter` (2026101501). Jeden Teilschritt mit „OK“ bestätigen. Das Upgrade legt die Vorschau-Rolle für die Pool-Konten an.
5. **Einstellungen neu eintragen:** KI-Backend = **Claude**, API-Key neu eintragen (Ollama nicht verwenden; der Code-Default ist noch `ollama`). Eine Quota gibt es nicht.
6. **Block wieder hinzufügen:** Startseite → Seite bearbeiten → Block hinzufügen „Fragenfilter“. Den Block des Kursfilters anschließend prüfen.
7. **Prüfen:** Suche mit einer Frage; Cookie `kf_rater_id` im Browser (Secure, HttpOnly, SameSite=Lax); eine Bewertung abgeben. Beim Cron den ersten `generate_tags`-Lauf beobachten.
8. **Weitere Tests:** HTTPS-/XSS-Test am 18.10.2026 auf Spike bestanden (siehe Fallplan PD-03, B4-03).
