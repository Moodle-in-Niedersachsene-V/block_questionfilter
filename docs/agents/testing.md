# Testing

PHPUnit läuft in einer isolierten Moodle-Testinstallation. CI prüft Moodle 5.1/PHP 8.3 mit MariaDB 10.11 und Moodle 5.2/PHP 8.4 mit PostgreSQL 16. Beide Jobs installieren `block_kursfilter` von `main` per `moodle-plugin-ci add-plugin` und führen `moodle-plugin-ci phpunit --fail-on-warning` aus. phpcs, phpdoc, savepoints, mustache und grunt bleiben aktiv.

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
5. In einem zweiten Terminal aus dem Moodle-Root den echten Testwebserver starten:

   ```bash
   QUESTIONFILTER_WEB_TESTS=http://127.0.0.1:8080 php -S 127.0.0.1:8080 -t public > webserver.log 2>&1
   ```

6. Nach erfolgreichem Start die Suite im ersten Terminal ausführen:

   ```bash
   QUESTIONFILTER_WEB_URL=http://127.0.0.1:8080 vendor/bin/phpunit --fail-on-warning --testsuite block_questionfilter_testsuite
   ```

   `QUESTIONFILTER_WEB_TESTS` gehört ausschließlich in den Webserver-Prozess. Im PHPUnit-Prozess darf diese Variable nicht gesetzt sein. Für einen einzelnen Test kann statt `--testsuite` der Pfad `public/blocks/questionfilter/tests/request_test.php` und optional `--filter test_anonymous_search_returns_a_visible_question` verwendet werden.

Ohne `QUESTIONFILTER_WEB_URL` überspringen sich die Request-Tests gezielt; eine gesetzte, aber unerreichbare URL lässt sie fehlschlagen. Der Webserver muss dieselben PHPUnit-Tabellen und dasselbe PHPUnit-Datenverzeichnis lesen. Der Suchtest prüft eine echte Frage über `/lib/ajax/service.php`, der Exporttest die Ablehnung ohne Anmeldung und unveränderte Fragen-, Bewertungs- und Exportdaten. Die HTTP-Anfragen senden weder Cookie noch Sesskey. Vor dem Request schließen die Tests ihre Transaktion, damit Fixtures sichtbar sind; danach erzwingen sie den vollständigen Datenbank-Reset, auch für HTTP-Schreibzugriffe außerhalb von PHPUnit.

Der lokale Nachweis für das Testgerüst wurde unter `/tmp/questionfilter-spec2-issue5/` mit einer eigenen Moodle-Kopie, separatem PostgreSQL-Container und PHP-Container geführt. PostgreSQL ist lokal verfügbar; die verbindlichen MariaDB-/PostgreSQL-Matrixnachweise liefert CI. Der Webserver und die Datenbank waren ausschließlich für diesen Lauf angelegt. Logs und tatsächliche Exitcodes liegen getrennt vor.

- Änderungen an `amd/src/` brauchen einen Grunt-Build (`amd/build/`).
- Nach Änderungen an `db/access.php`, `db/services.php`, `db/install.xml` oder `version.php` Moodle-Upgrade ausführen.
