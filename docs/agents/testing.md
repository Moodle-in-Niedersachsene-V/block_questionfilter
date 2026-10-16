# Testing

PHPUnit läuft in einer isolierten Moodle-Testinstallation. CI prüft Moodle 5.1/PHP 8.3 mit MariaDB 10.11 und Moodle 5.2/PHP 8.4 mit PostgreSQL 16. Beide Jobs installieren `block_kursfilter` von `main` per `moodle-plugin-ci add-plugin` und führen `moodle-plugin-ci phpunit --fail-on-warning` aus. phpcs, phpdoc, savepoints, mustache und grunt bleiben aktiv.

`tests/hook_callbacks_test.php` prüft zusätzlich die echte Registrierung und den Dispatch von `core\hook\after_config` sowie die Delegation der Pool-Schutzgrenzen an den installierten Kursfilter.

## Lokale Vorbereitung

Keine gemeinsam genutzte Installation, Datenbank oder laufende Instanz verwenden. Eine eigene Moodle-Kopie, eine eigene leere Datenbank und eigene Datenverzeichnisse anlegen. PHP benötigt die Moodle-Erweiterungen, insbesondere cURL und den passenden Datenbanktreiber, sowie `max_input_vars=5000` und ausreichend Speicher (lokal 512 MB).

1. Moodle 5.1 oder 5.2 in ein eigenes Verzeichnis auschecken, Abhängigkeiten mit `composer install` installieren. Dieses Repo nach `public/blocks/questionfilter` verlinken oder kopieren, `block_kursfilter` von `main` nach `public/blocks/kursfilter` auschecken. Keine Secrets oder `config.php` aus einer anderen Installation übernehmen.
2. Eine eigene `config.php` im Moodle-Root nach `config-dist.php` erstellen: Datenbanktreiber, Host, Name, Benutzer und Passwort ausschließlich der Testdatenbank, `prefix = 'mdl_'`, `wwwroot` und ein eigenes `dataroot`. Zusätzlich `phpunit_prefix = 'phpu_'` und ein separates `phpunit_dataroot` setzen. Beide Datenverzeichnisse müssen für CLI und Webserver schreibbar sein.
3. Vor `require_once(__DIR__ . '/lib/setup.php');` die Umleitung einfügen:

   ```php
   if (getenv('QUESTIONFILTER_WEB_TESTS')) {
       $CFG->prefix = $CFG->phpunit_prefix;
       $CFG->dataroot = $CFG->phpunit_dataroot;
       $CFG->wwwroot = getenv('QUESTIONFILTER_WEB_TESTS');
       unset($CFG->phpunit_prefix, $CFG->phpunit_dataroot);
   }
   ```

   Das `unset` verhindert, dass Moodle den Webserver als PHPUnit-Prozess erkennt. CI bindet denselben Code als `qf-web-config.php` ein.
4. Im Moodle-Root **ohne** `QUESTIONFILTER_WEB_TESTS` initialisieren und auf Exit 0 warten:

   ```bash
   php public/admin/tool/phpunit/cli/init.php
   ```

   Dies installiert auch beide Plugins in den PHPUnit-Tabellen und erzeugt `phpunit.xml`. Nach Plugin- oder Versionsänderungen erneut ausführen. Während Initialisierung und Testlauf darf kein anderer Test dieselbe Datenbank verwenden.
   Den strengen CI-Nachinstallationsschritt bei Änderungen an Bootstrap/Hooks zusätzlich ausführen:

   ```bash
   php public/admin/tool/phpunit/cli/util.php --buildconfig
   ```

   Exit 0 allein genügt hier nicht: Der Schritt darf keine PHP-Fehler oder Moodle-`debugging()`-Meldungen ausgeben. `moodle-plugin-ci install` erzwingt das über `MoodleProcess`; insbesondere dürfen keine veralteten `after_config`-Callbacks verbleiben. Hook-Registrierungen liegen in `db/hooks.php`; nach Änderungen die Plugin-Version erhöhen und die isolierte Testinstallation aktualisieren. Wenn nur die PHPUnit-Tabellen initialisiert sind, das CLI-Upgrade gezielt auf diese umleiten und danach wieder ohne Umleitung initialisieren:

   ```bash
   QUESTIONFILTER_WEB_TESTS=http://127.0.0.1:8080 php admin/cli/upgrade.php --non-interactive
   php public/admin/tool/phpunit/cli/init.php
   ```

5. In einem zweiten Terminal aus dem Moodle-Root den echten Testwebserver starten:

   ```bash
   QUESTIONFILTER_WEB_TESTS=http://127.0.0.1:8080 php -S 127.0.0.1:8080 -t public > webserver.log 2>&1
   ```

6. Nach erfolgreichem Start die Suite im ersten Terminal ausführen:

   ```bash
   QUESTIONFILTER_WEB_URL=http://127.0.0.1:8080 vendor/bin/phpunit --fail-on-warning --testsuite block_questionfilter_testsuite
   ```

   `QUESTIONFILTER_WEB_TESTS` gehört in den Webserver-Prozess oder das oben gezeigte gezielte CLI-Upgrade. Im PHPUnit-Prozess darf diese Variable nicht gesetzt sein. Für einen einzelnen Test kann statt `--testsuite` der Pfad `public/blocks/questionfilter/tests/request_test.php` und optional `--filter test_anonymous_search_returns_a_visible_question` verwendet werden.

Ohne `QUESTIONFILTER_WEB_URL` überspringen sich die Request-Tests gezielt; eine gesetzte, aber unerreichbare URL lässt sie fehlschlagen. Der Webserver muss dieselben PHPUnit-Tabellen und dasselbe PHPUnit-Datenverzeichnis lesen. Die Fixtures setzen `forcelogin=0`, weil ADR 0002 eine öffentliche Suche beschreibt (Moodle 5.2 liefert standardmäßig `forcelogin=1`). Der Suchtest prüft eine echte Frage über `/lib/ajax/service.php`, der Exporttest die Ablehnung ohne Anmeldung und unveränderte Fragen-, Bewertungs- und Exportdaten. Die HTTP-Anfragen senden weder Cookie noch Sesskey. Vor dem Request schließen die Tests ihre Transaktion, damit Fixtures sichtbar sind; danach erzwingen sie den vollständigen Datenbank-Reset, auch für HTTP-Schreibzugriffe außerhalb von PHPUnit.

Der lokale Nachweis für das Testgerüst wurde unter `/tmp/questionfilter-spec2-issue5/` mit eigenen Moodle-5.1/PHP-8.3- und Moodle-5.2/PHP-8.4-Installationen geführt, jeweils mit separatem PostgreSQL-Container und PHP-Container. Beide Installationen bestehen die strengen CI-Nachinstallationsschritte (`--buildconfig`, `--buildcomponentconfigs`) und `moodle-plugin-ci phpunit --fail-on-warning` mit echten HTTP-Requests. Die verbindlichen MariaDB-/PostgreSQL-Matrixnachweise liefert CI. Der Webserver und die Datenbank waren ausschließlich für diesen Lauf angelegt. Logs und tatsächliche Exitcodes liegen getrennt vor.

- Änderungen an `amd/src/` brauchen einen Grunt-Build (`amd/build/`).
- Nach Änderungen an `db/access.php`, `db/services.php`, `db/install.xml` oder `version.php` Moodle-Upgrade ausführen.

## Semgrep

Der eigenständige CI-Job `semgrep` verwendet den unveränderten
[Vereinsregelsatz samt Herkunft und Hash](../../.github/semgrep/README.md).
Im Repo-Root mit Docker denselben blockierenden Lauf starten:

```bash
docker run --rm --network none -v "$PWD:/src:ro" -w /src \
  semgrep/semgrep@sha256:93963d9295a366f59e4850127b1550400ee7b388f04fe144e4a1f6325d96e01b \
  semgrep scan --config .github/semgrep/moodle-security.yml \
  --severity ERROR --error --strict --metrics=off --disable-version-check \
  --no-rewrite-rule-ids .
```

Das Image muss vorher vorhanden sein (`docker pull` mit demselben Digest).
Semgrep 1.179.0 liefert bei ERROR-Treffern Exit 1, bei keinem ERROR Exit 0;
`--strict` lässt auch Scan-/Regelfehler scheitern. WARNING und INFO blockieren
nicht. Für die fachliche Bewertung aller Schweregrade `--severity ERROR --error`
weglassen; dieser Bericht liefert auch bei Treffern Exit 0 und ist kein Gate.
Bei Bedarf `--json --output /tmp/questionfilter-semgrep.json` ergänzen.
`.semgrepignore` enthält bewusst keine Ausschlüsse, damit auch Test-PHP-Dateien
geprüft werden; einzelne Regeln haben eigene, unverändert übernommene Pfadgrenzen.
Begründete Ausnahmen stehen nur am betroffenen Fund als `nosemgrep: <Regel-ID>`
mit konkreter Begründung (A2). Bekannte echte Lücken dürfen so nicht ausgeblendet
werden. Der [Fallplan](../fallplan.md) bewertet die tatsächlichen Treffer.

Die Gate-Proben zu #7 liegen unter `/tmp/questionfilter-spec2-issue7/fixtures/`:
`unsafe.php` enthält Superglobal-Zugriff und `eval` (ERROR, Gate-Exit 1),
`safe.php` nutzt typisierte Parameter, gebundenes SQL und `s()` (Exit 0),
`advisory.php` enthält `md5` und einen ungeprüften `html_writer`-Inhalt
(WARNING/INFO im Vollbericht, Gate-Exit 0). `gate-checks.py` dort führt diese
Scans und den Plugin-Scan mit JSON-Bericht, Logs und getrennten Exitdateien aus.
Die Fixtures werden nur gescannt, nie als PHP ausgeführt. Sie prüfen Auswahl und
Exitverhalten des Gates, keine vollständige Wirksamkeit aller 23 Regeln.

Der zusätzliche HTTP-Test
`request_test::test_preview_rejects_get_and_invalid_sesskeys_before_logging_in`
prüft GET ohne, mit falschem und mit gültigem Sesskey sowie POST ohne/mit falschem Sesskey: keine
Pool-Anmeldung und unveränderte Nutzer-, Fragen-, Bewertungs- und Tag-Daten.
Der Gegenfall POST mit gültigem Sesskey startet die Vorschau und meldet das
Pool-Konto an. Pool-Belegung und Fragen-Sichtbarkeit bleiben eigene Fälle (#8/#9).
