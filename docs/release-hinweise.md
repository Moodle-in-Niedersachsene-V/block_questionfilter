# Hinweise für den Deploy (Friedhelm)

- **Umbenennung:** Komponente heißt jetzt `block_fragenfilter` (früher `block_questionfilter`). Das Zip heißt `block_fragenfilter_<build>.zip`, Wurzelordner `fragenfilter/`. Moodle sieht das als neues Plugin. Falls auf dem Hub ein altes `block_questionfilter` installiert ist: vorher deinstallieren. Auf dem Live-Hub gibt es laut Matthias keinen Altbestand.
- **Kursfilter:** `block_kursfilter` mindestens Build 2026101501; nach Plan zusammen mit diesem Release aktualisieren.
- **Nach der Installation:** Moodle-Upgrade ausführen (legt die Vorschau-Rolle für die Pool-Konten an).
- **KI-Backend:** in den Plugin-Einstellungen wählen und konfigurieren (Ollama-URL, API-Key, Quoten wie beim Kursfilter). Ollama ist nicht der Standard im Betrieb. Der Code-Default ist noch `ollama`.
- **Datenschutz:** kein AV-Vertrag nötig, siehe `docs/datenschutz.md`. Falls das anderswo (Vereinsunterlagen) geklärt ist, dort verlinken.
- **HTTPS-/XSS-Test:** am 18.10.2026 auf der Spike-Instanz bestanden (Cookie `kf_rater_id` Secure/HttpOnly/Lax, Fragenname mit Attributfragmenten wird nicht ausgeführt). Auf dem Live-Hub reicht ein Blick auf das Cookie nach dem Deploy.
- **Build-Nummer** muss höher sein als die live installierte (aktuell 2026101702).
