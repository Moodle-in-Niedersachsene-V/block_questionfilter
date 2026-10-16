# Sicherheits-Fallplan Fragenfilter

## Prüfstand

| Kopf | Wert |
| --- | --- |
| Projekt / Komponenten | Materialien-Hub: `block_questionfilter` mit `block_kursfilter` |
| Planversion / Prüfdatum | 1 / 16.10.2026; ergänzt um Semgrep und Vorschau-CSRF zu #7; initialer Plan zu [#6](https://github.com/Moodle-in-Niedersachsene-V/block_questionfilter/issues/6), Teil [Spec #2](https://github.com/Moodle-in-Niedersachsene-V/block_questionfilter/issues/2) |
| Geprüfter Commit | E1–E4/R1–R3: `a4943ea3765c2d49854d116fc368675362f5556e`; S1/S2/S3: #7-Stand `d18828648441fb16bddc9fbe3c8ff1518123c2e8`, genaue geprüfte Dateihashes in `/tmp/questionfilter-spec2-issue7/source-hashes.json`. S3 belegt die CI an genau diesem Commit; nachfolgende reine Nachweisdokumentation ändert diesen Verhaltensnachweis nicht. Kein Release-/Paketnachweis. |
| Build im Quellstand | `2026101602`, Release `v1.3.10` laut `version.php`; noch kein festgelegter Release-Build für Spec #2 |
| Zugeordnetes Paket | **Nicht vorhanden / nicht geprüft.** Erst #13 erstellt `block_questionfilter_<build>.zip` aus dem geprüften Release-Commit und belegt die Paketinstallation. |
| Unterstützte Versionen / Auswahl | `requires = 2025100600` (Moodle 5.1); vereinbarte Matrix 5.1/PHP 8.3/MariaDB 10.11 und 5.2/PHP 8.4/PostgreSQL 16. Nur Materialien-Hub, keine vollständige DB-Kreuzmatrix ([ADR 0001](adr/0001-fragenfilter-runs-only-in-the-materials-hub.md)). |
| Tatsächlich getestet (historische CI E1) | Moodle **5.1.8 (Build 20261005), PHP 8.3.35**, MariaDB-10.11-Service; Moodle **5.2.4+ (Build 20261013), PHP 8.4.26**, PostgreSQL-16-Service. DB-Patchstände hier nicht festgestellt. Kursfilter jeweils von `main` mitinstalliert. |
| Tatsächlich getestet (CI S3) | Moodle **5.1.8 (Build 20261005), PHP 8.3.35, MariaDB 10.11.19**; Moodle **5.2.4+ (Build 20261013), PHP 8.4.26, PostgreSQL 16.15**. Kursfilter jeweils von `main` mitinstalliert. |
| Zuständigkeit | Bearbeiter des jeweiligen Folgetickets liefert Belege; Freigabeverantwortliche Person des Vereins und Freigabetermin sind noch zu benennen (#13). |

## Maßstab und Belege

Verbindliche Arbeitsgrundlage dieser Spec ist der Vereins-[Prüfstandard-Entwurf v2](https://github.com/Moodle-in-Niedersachsene-V/security-checks/blob/37b1702d79bb5eb8d01abfc286a1ae88060123a3/docs/pruefstandard-entwurf.md)
(Stand 05.10.2026, abgerufen 16.10.2026, Quellen-Commit `37b1702d79bb5eb8d01abfc286a1ae88060123a3`). Er ist ein Entwurf, keine beschlossene NLQ-Richtlinie.
Standards und lokale Abweichungen: [CODING_STANDARDS.md](../CODING_STANDARDS.md).
Das historische Kursfilter-[Prüfprotokoll](https://github.com/Moodle-in-Niedersachsene-V/block_kursfilter/blob/b312d77260b8fcc976dc8db60076b824e79126c8/Sicherheitspruefung_block_kursfilter_2026062216.docx)
(Build 2026062216, Prüfdatum 05.07.2026) dient als Vorbild für Build-Zuordnung, Befund und Zuständigkeit. Seine Sicherheitsurteile werden nicht übertragen; die Fallzählung folgt dem neueren v2-Entwurf.

| Beleg | Art / genaue Quelle / Aussagegrenze |
| --- | --- |
| E1 | Automatisierter CI-Lauf [38883284402](https://github.com/Moodle-in-Niedersachsene-V/block_questionfilter/actions/runs/38883284402) am oben genannten Commit: [5.1/MariaDB-Job 116914884332](https://github.com/Moodle-in-Niedersachsene-V/block_questionfilter/actions/runs/38883284402/job/116914884332), [5.2/PostgreSQL-Job 116914883842](https://github.com/Moodle-in-Niedersachsene-V/block_questionfilter/actions/runs/38883284402/job/116914883842). Je **3 Tests / 31 Assertions**, `phpunit --fail-on-warning`, echte HTTP-Requests, beide Jobs erfolgreich. Installation, phplint, phpcs, phpdoc, validate, savepoints, grunt und mustache erfolgreich. PHP-Mess-Detector ebenfalls erfolgreich, im Workflow jedoch nicht blockierend. Kein Semgrep-, Upgrade- oder Paketinstallationsnachweis. |
| E2 | Automatisierter Test aus #5: [`request_test::test_anonymous_search_returns_a_visible_question`](https://github.com/Moodle-in-Niedersachsene-V/block_questionfilter/blob/a4943ea3765c2d49854d116fc368675362f5556e/tests/request_test.php), ausgeführt in E1: AJAX ohne Cookie/Sesskey liefert genau die angelegte sichtbare Frage. Kein allgemeiner Sichtbarkeitsnachweis. |
| E3 | Automatisierter Test aus #5: [`request_test::test_anonymous_export_after_search_is_rejected_without_data_changes`](https://github.com/Moodle-in-Niedersachsene-V/block_questionfilter/blob/a4943ea3765c2d49854d116fc368675362f5556e/tests/request_test.php), ausgeführt in E1: Suche und anschließender CSV-Export im selben anonymen AJAX-Batch; `servicerequireslogin`, kein `data`, unveränderte Datensätze in `question`, `question_versions`, `question_bank_entries`, `question_categories`, `question_answers`, `block_questionfilter_ratings`, `block_questionfilter_tag_pending`, `files`. Kein Export-Gegenfall mit Pool-Konto. |
| E4 | Automatisierter [`hook_callbacks_test::test_registered_after_config_preserves_kursfilter_delegation`](https://github.com/Moodle-in-Niedersachsene-V/block_questionfilter/blob/a4943ea3765c2d49854d116fc368675362f5556e/tests/hook_callbacks_test.php), ausgeführt in E1: reale Hook-Registrierung/Dispatch erhält Identität und Messaging-Einstellung für anonym, Gast, reguläres Konto und Pool-Konto bei installiertem Kursfilter. Kein Test der tatsächlichen Kursfilter-Seitensperren oder des Vorschau-Logins. |
| R1 | Review am geprüften Commit: [`guest_preview.php`](https://github.com/Moodle-in-Niedersachsene-V/block_questionfilter/blob/a4943ea3765c2d49854d116fc368675362f5556e/guest_preview.php) liest `qid`, wählt das erste vorhandene Konto in eigener Schleife und ruft `complete_user_login()` ohne POST-/Sesskey-Prüfung oder Pool-Belegung auf. Bestätigt die beiden Planungsbefunde, kein ausgeführter Angriffstest. |
| R2 | Review am geprüften Commit: [`amd/src/filter.js`](https://github.com/Moodle-in-Niedersachsene-V/block_questionfilter/blob/a4943ea3765c2d49854d116fc368675362f5556e/amd/src/filter.js) erzeugt `sessionHash` mit `Math.random`/`sessionStorage`; [`classes/external.php`](https://github.com/Moodle-in-Niedersachsene-V/block_questionfilter/blob/a4943ea3765c2d49854d116fc368675362f5556e/classes/external.php) nimmt `sessionhash` an und ordnet Bewertungen darüber bzw. über `userid` zu. Kein Nachweis des ADR-0003-Cookie-Vertrags. |
| R3 | Review am geprüften Commit: [`classes/privacy/provider.php`](https://github.com/Moodle-in-Niedersachsene-V/block_questionfilter/blob/a4943ea3765c2d49854d116fc368675362f5556e/classes/privacy/provider.php) implementiert `null_provider`; [`db/install.xml`](https://github.com/Moodle-in-Niedersachsene-V/block_questionfilter/blob/a4943ea3765c2d49854d116fc368675362f5556e/db/install.xml) speichert `userid`/`sessionhash` und Zeitstempel. Metadatenaussage widerspricht personenbezogener Speicherung. |

### Ergänzende Nachweise zu #7

- **S1 – Scanner/Gate (16.10.2026):** unveränderte 23 Vereinsregeln, Quellen-Commit
  `37b1702d79bb5eb8d01abfc286a1ae88060123a3`, Herkunft/Hash in
  [`.github/semgrep/README.md`](../.github/semgrep/README.md). Semgrep 1.179.0,
  fixierter Image-Digest. Vollständiger Erstlauf ohne den alten Dateiausschluss:
  23 PHP-Dateien, **1 ERROR / 4 WARNING / 0 INFO**, keine Scanfehler.
  Der frühere Lauf mit altem `.semgrepignore` erfasste nur 22 Dateien und ist
  ausdrücklich kein vollständiger Erstlauf. Der Lauf ohne `.semgrepignore`
  ließ zusätzlich Tests durch Scanner-Defaults aus; maßgeblich ist
  `first-complete.json` mit ausschlussfreier `.semgrepignore`.
  Gate-Proben: unsicher (`$_GET`, `eval`) Exit 1; sicher (typisierter Parameter,
  SQL-Bindung, maskierte Ausgabe) Exit 0; reine WARNING/INFO im Vollbericht
  erkannt, beim ERROR-Gate Exit 0. Keine PHP-Ausführung dieser Fixtures.
  Vollscan nach Änderung: **0 ERROR / 6 WARNING / 0 INFO**, keine Scanfehler;
  ERROR-Gate lokal Exit 0. Das prüft Gate-Auswahl und Exitverhalten, nicht jede
  Wirksamkeitsgrenze aller Regeln. Den erfolgreichen CI-Gatelauf belegt S3.
- **S2 – HTTP-Verhalten:**
  `request_test::test_preview_rejects_get_and_invalid_sesskeys_before_logging_in`
  gegen echte isolierte Testwebserver: GET ohne/falschen/gültigen Sesskey sowie
  POST ohne/falschen Sesskey werden ohne Pool-Anmeldung und ohne Änderung der
  geprüften Nutzer-, Fragen-, Bewertungs- und Tag-Daten abgewiesen. Gegenfall:
  gültiger POST meldet das vorhandene Pool-Konto an und liefert das Vorschauziel.
  Der rote Test vor der Änderung belegt die bisherige GET-Anmeldung.
  Lokale Moodle-Stände: 5.1.7+ (Build 20260928)/PHP 8.3.35 und
  5.2.4 (Build 20261005)/PHP 8.4.25, jeweils PostgreSQL 16.14.
  Vollsuite mit `moodle-plugin-ci phpunit --fail-on-warning`: jeweils
  **4 Tests / 77 Assertions**, Exit 0. Belege in `suite51-final.log`/`suite52-final.log`; keine MariaDB-Abnahme
  aus diesen lokalen PostgreSQL-Läufen ableiten. Keine Pool-Belegungs- oder
  allgemeine Sichtbarkeitsprüfung.

- **S3 – CI-Abnahme des Implementierungsstands (16.10.2026):**
  [Lauf 38905237233](https://github.com/Moodle-in-Niedersachsene-V/block_questionfilter/actions/runs/38905237233)
  an **`d18828648441fb16bddc9fbe3c8ff1518123c2e8`**, alle drei Jobs
  `completed/success`:
  [Semgrep 116987024137](https://github.com/Moodle-in-Niedersachsene-V/block_questionfilter/actions/runs/38905237233/job/116987024137)
  (Abschluss 13:06:05Z),
  [Moodle 5.1/MariaDB 116987025093](https://github.com/Moodle-in-Niedersachsene-V/block_questionfilter/actions/runs/38905237233/job/116987025093)
  (13:08:25Z),
  [Moodle 5.2/PostgreSQL 116987023825](https://github.com/Moodle-in-Niedersachsene-V/block_questionfilter/actions/runs/38905237233/job/116987023825)
  (13:08:54Z). Das Semgrep-Rohlog zeigt den tatsächlich ausgeführten Befehl
  `--severity ERROR --error --strict`, **11 ERROR-Regeln**, **23 PHP-Dateien**,
  **0 Treffer**. Die übrigen 12 Vereinsregeln werden durch diesen ERROR-Filter
  nicht ausgeführt; deren Bewertung bleibt der vollständige lokale S1-Lauf.
  Beide Matrixjobs führen `moodle-plugin-ci phpunit --fail-on-warning` mit
  **4 Tests / 77 Assertions** erfolgreich aus, ohne übersprungene Tests.
  Damit laufen die drei bisherigen Tests aus E2–E4 und der neue S2-Vorschautest
  tatsächlich auf beiden vereinbarten Datenbankständen. Die Webserverartefakte
  belegen echte AJAX-/Vorschau-HTTP-Requests. Installation, phplint, phpcs,
  phpdoc, validate, savepoints, grunt und mustache sind ebenfalls erfolgreich;
  PHP-Mess-Detector erfolgreich, weiterhin nicht blockierend.
  Keine allgemeine Pool-, Sichtbarkeits-, Upgrade- oder Paketfreigabe.
  Unveränderte Rohbelege: `ci-accepted-run.json`, `ci-accepted-jobs.json`,
  `ci-accepted-logs.zip`, `ci-accepted-raw.log`, `ci-accepted-web83/`,
  `ci-accepted-web84/`; SHA-256-Manifest `ci-accepted-sha256.json`, jeweils unter
  `/tmp/questionfilter-spec2-issue7/`. Diese CI-Abnahme wird keinem späteren
  Commit zugeschrieben. Die Pflicht-CI einer nachfolgenden reinen
  Nachweisdokumentation und der endgültige Issue-Abschluss werden separat
  im Übergabebeleg festgehalten.

| Fund / geprüfter Ort | Fachliche Bewertung / Maßnahme / Grenze |
| --- | --- |
| ERROR `moodle-einstiegsdatei-ohne-login`, `guest_preview.php` (Erstlauf Zeile 31) | Öffentlicher Login-Einstieg nach ADR 0002 braucht selbst kein `require_login`. Der ungeschützte Login war trotzdem ein echter CSRF-Befund: in #7 minimal durch POST-/Sesskey-Prüfung **vor** Fragenzugriff/Pool-Auswahl abgesichert und per S2 nachgewiesen. Erst danach regelgenaue `nosemgrep`-Ausnahme mit Begründung direkt am Einstieg; keine Dateiausnahme mehr. Die weiterhin eigene Pool-Auswahl bleibt FEHLER B2-04/#8; keine Unterdrückung eines Pool-Belegungschecks (der Regelsatz hat keinen solchen Check). |
| WARNING `moodle-echo-unescaped`, `guest_preview.php`: `$wwwrootjson`, `$previewurljson` (Erstlauf Zeilen 114/119) | Kein ungefilterter Nutzertext: `json_encode` kodiert die Betreiberkonfiguration `wwwroot` und die aus dieser Konfiguration sowie typisiertem Integer-`qid` aufgebaute Core-URL für JS. Am geprüften Datenfluss kein bestätigter XSS-Befund; keine Unterdrückung. Allgemeine Ausgabe-/Sichtbarkeitsprüfungen B4-03/B2-03 bleiben offen. |
| WARNING `moodle-curl-statt-moodle-curl`, `tests/request_test.php`: `ajax()` und neues `preview_request()` (je `curl_init`/`curl_exec`) | Erstlauf zwei, danach vier Treffer: ausschließlich Testadapter gegen die explizite isolierte `QUESTIONFILTER_WEB_URL`. Native cURL hält den echten HTTP-Aufruf vom PHPUnit-Prozess getrennt; kein Produktivrequest und keine Nutzerziel-URL. Keine Ausnahme nötig, WARNING blockiert nicht. Produktiver KI-Connector nutzt Moodle-`\curl`; NW-01–03 bleiben offen und werden durch diesen Testbefund nicht freigegeben. |
| INFO | Erst- und Plugin-Folgelauf ohne INFO-Treffer. Gate-Probe `html_writer::div($content)` liefert INFO; sie belegt, dass INFO im Vollbericht sichtbar ist und das ERROR-Gate nicht blockiert. Keine allgemeine Maskierungsfreigabe daraus. |

Alle #7-Logs, Exitdateien, JSON-Berichte, unveränderter Quellensnapshot,
Fixture-Quellen und Review-/Übergabekontext: `/tmp/questionfilter-spec2-issue7/`.
S1–S3 ändern keine historischen E1–E4/R1–R3-Laufgrenzen. S3 bestätigt die
Implementierung an seinem oben genannten SHA in der vereinbarten CI-Matrix;
nachfolgende Dokumentationscommits sind davon getrennte Pflicht-CI-Grenzen.

Arbeitsbelege dieser Planerstellung: `/tmp/questionfilter-spec2-issue6/` (Quellensnapshots, CI-JSON/Log, Reviews, mechanische Prüfung, Übergabe).
Die älteren lokalen #5-Belege unter `/tmp/questionfilter-spec2-issue5/` sind Entwicklungsnachweise früherer Commits; insbesondere `suite-reviewed.log` mit 2 Tests/22 Assertions ersetzt E1 nicht.

## Falltabelle

**Status:** OK = erfolgreich belegt; HINWEIS = belegte Einschränkung mit Bewertung;
FEHLER = bestätigter Befund, bleibt bis zur Behebung und Nachprüfung offen;
offen = noch nicht untersucht oder erforderlicher Nachweis fehlt (je Zeile angegeben);
ENTFÄLLT = mit Begründung nicht anwendbar.
Belegart steht separat. Ein geplanter Test ist kein Beleg. Nur erfolgreich belegte Fälle zählen im Fortschrittszähler; FEHLER zählen trotz Review nicht als erfolgreich belegt.
Jede Zeile ist ein Fall; enthält sie mehrere Varianten, müssen alle belegt sein. Negativ-/Gegenfälle einer Grenze sind ausdrücklich genannt, auch wenn der positive Nachweis noch offen ist.

### B1 – Qualität und Kompatibilität

| Fall-ID / Bereich | Konkreter Fall und erwartetes Ergebnis | Status | Belegart / Quelle | Befund oder N/A-Begründung / Folgeticket |
| --- | --- | --- | --- | --- |
| B1-01 / B1 | Frische Installation mit Kursfilter und eigene Suite sowie Syntax-, Code-/Dokumentations-, Struktur-, Savepoint-, Mustache-/Grunt-Prüfungen in beiden vereinbarten Matrixständen erfolgreich. | OK | Automatisierter Test / E1, S3 | Quellinstallation geprüft; kein Release-Paket. |
| B1-02 / B1, Migration | Upgrade von Vor-Spec-Stand: Rollenzuweisung für bestehende und später angelegte Pool-Konten; Bewertungsschema umgestellt. Gegenfall: leerer Bestand; bei Altbestand Sterne erhalten, alte Kennungen verworfen; kein Verlust fremder Daten. | offen | — | Nachweis fehlt; #8/#10. Live-Bestand vor Upgrade prüfen, kein Hub-Zugriff in dieser Prüfung. |
| B1-03 / B1 | Developer Debugging, abweichender Tabellenpräfix, zentrale Hub-Funktionen Suche/Filter/Vorschau/Export/Bewertung/Tag-Prüfung ohne neue Debugmeldungen oder Regressionen. | offen | — | Vollständiger Funktionsnachweis fehlt; E1 deckt nur Tracer ab. #8–#12, Endabnahme #13. |
| B1-04 / B1, Release | Finaler Commit, Metadaten/Abhängigkeiten, Build `YYYYMMDDNN`, Maintainer und Paket `block_questionfilter_<build>.zip` stimmen überein; Paket sauber installierbar; vereinbarte Checks am Release-Stand grün. | offen | — | Paket noch nicht vorhanden; einziger Paketinstallationsfall, #13. Nach Codeänderung erneut prüfen. |
| B1-05 / ergänzendes Werkzeug | Vereins-Semgrep-Regeln mit Herkunft, unsicheren Treffern und sicheren Gegenfällen; ERROR blockiert, WARNING/INFO fachlich bewertet. | OK | Automatisierte Gate-Proben, Review und CI / S1, S3 | Herkunft, Gate-Auswahl/Exits und Befundbewertung belegt; ERROR-Job am S3-SHA erfolgreich. Kein allgemeiner Ersatz für Verhaltenstests. |

### B2 – Anmeldung und Berechtigungen (ADR 0002)

| Fall-ID / Bereich | Konkreter Fall und erwartetes Ergebnis | Status | Belegart / Quelle | Befund oder N/A-Begründung / Folgeticket |
| --- | --- | --- | --- | --- |
| B2-01 / B2 | Zulässiger Gegenfall zur Export-Anmeldegrenze EX-01: Besucher ohne Konto sucht öffentlich und erhält eine sichtbare Frage. | OK | Automatisierter Test / E2, E1; erneut S3 | Nur öffentliche Suche, keine Vorschau-/Exportberechtigung daraus ableiten. |
| B2-02 / B2, B4 | Suche, Kategorien und Fragetypen: ohne `view` oder mit fremdem/nicht sichtbarem Kontext keine geschützten Informationen; Gegenfall mit `view`, `question:viewmine`/`question:viewall` und passendem Suchbereich liefert nur erlaubte Ergebnisse. | offen | — | Nachweis fehlt; Rollen-/Eigentümer-/System-/Kurs-/Modulkontexte und alle Suchbereiche prüfen. #9. |
| B2-03 / B2 | Vorschau eines fremden/nicht sichtbaren bzw. unbekannten `qid` abgewiesen ohne Identitätswechsel; Gegenfall sichtbare Frage mit regulärer Anmeldung führt zur Core-Vorschau. | offen | — | Noch nicht untersucht; #8, Sichtbarkeitsbelege #9. |
| B2-04 / B2, B3 | Zwei Besucher dürfen kein gleichzeitig belegtes Pool-Konto erhalten; Gegenfall delegiert Auswahl, Belegung und Anmeldung an den Kursfilter und erhält ein freies eigenes Pool-Konto. Eigene Auswahl und `questionfilter_guest` entfallen. | FEHLER | Review / R1, ADR 0002 | Geteilte Pool-Identität: erste vorhandene Identität ohne Belegung; Behebung #8 (abhängig von Kursfilter #22). Request-Nachweis für beide Besucher fehlt. |
| B2-05 / B2 | Echte Anmeldung bleibt beim Vorschau-Start erhalten; Gegenfall anonymer Besucher erhält ausschließlich über den Kursfilter ein Pool-Konto. | offen | — | Nachweis fehlt; #8. E4 prüft allein den Hook, nicht den Vorschau-Start. |
| B2-06 / B2 | Pool voll oder IP schon belegt: Hinweis, keine unzulässige neue Belegung/Anmeldung; Gegenfall freie IP und freies Konto erlaubt. | offen | — | Nachweis fehlt; #8 prüft Fragenfilter-Integration. Kursfilter verantwortet Belegung je Besucher, höchstens eine Vorschau je IP. |
| B2-07 / B2 | Pool-Konto erreicht Profil-/Kennwortseiten nicht; reguläres Konto erreicht eigene erlaubte Seiten. Fragenfilter delegiert die Zugangssperren vollständig an Kursfilter. | offen | — | Nachweis fehlt; #8 referenziert genaue Kursfilter-Negativ-/Gegenfälle samt Commit/Lauf. E4 allein belegt keine Seitensperre; Pool-Logik nicht im Fragenfilter nachbauen. |
| B2-08 / B2 | Capability-Anzeige (`check_capabilities`, Export-Widget) bietet anonym keine Exportfreigabe; Gegenfall berechtigtes Pool-/echtes Konto zeigt Export nach Anmeldung. Manipuliertes Widget umgeht Serverprüfung nicht. | offen | — | Noch nicht untersucht; #8/#9. |
| B2-09 / B2 | Hook-Integration bei installiertem Kursfilter überschreibt weder Identität noch Messaging-Einstellung: anonyme/Gast-/reguläre und Pool-Identität bleiben erhalten. | OK | Automatisierter Test / E4, E1; erneut S3 | Enger Delegations-/Registrierungsnachweis, tatsächliche Pool-Grenzen bleiben B2-04–B2-07. |

### B3 – Zustandsänderungen (ADR 0002/0003)

| Fall-ID / Bereich | Konkreter Fall und erwartetes Ergebnis | Status | Belegart / Quelle | Befund oder N/A-Begründung / Folgeticket |
| --- | --- | --- | --- | --- |
| B3-01 / B3 | Vorschau-Start über GET oder POST ohne/mit ungültigem Sesskey abgewiesen, ohne Anmeldung/Belegungsänderung; Gegenfall POST mit gültigem Sesskey startet zulässige Vorschau. | OK | Automatisierter HTTP-Test / S2, S3; historisch R1 | Login-CSRF in #7 durch POST und Sesskey behoben, negative Fälle mit unveränderten Daten/ohne Pool-Anmeldung und gültiger Gegenfall lokal und in beiden CI-Matrixjobs am S3-SHA belegt. Pool-Belegung bleibt separat B2-04/#8. |
| B3-02 / B3 | Bewertung ohne/mit ungültigem Sesskey oder unzulässiger Methode speichert nichts; Gegenfall gültiger schreibender AJAX-Aufruf bewertet erlaubte Frage mit 1–5 Sternen. | offen | — | Nachweis fehlt; #10, höchstens eine Bewertung nach PD-02. Ablehnung einschließlich unveränderter Tabellen belegen. |
| B3-03 / B2, B3, KI | Prüfseite ohne `reviewtags`, mit fremder Vorschlags-/Fragen-ID oder Aktion ohne/mit ungültigem Sesskey abgewiesen ohne Änderungen; Gegenfall berechtigtes Annehmen/Verwerfen per POST mit Sesskey wirkt nur auf erlaubte Vorschläge. | offen | — | Noch nicht untersucht; #12. Rechte beim Anzeigen und Ausführen prüfen, Tag-/Pending-Datensätze vergleichen. |

### B4 – Eingaben und Ausgaben

| Fall-ID / Bereich | Konkreter Fall und erwartetes Ergebnis | Status | Belegart / Quelle | Befund oder N/A-Begründung / Folgeticket |
| --- | --- | --- | --- | --- |
| B4-01 / B4 | Suchbegriffe/Filter mit SQL-Sonderzeichen beeinflussen keine Abfragestruktur; Gegenfall normale Suche/Filter korrekt. Review bestätigt gebundenes SQL und typisierte Parameter an allen externen Einstiegspunkten. | offen | — | Noch nicht untersucht; #9. Öffentlicher Tracer E2 ersetzt Injektionsfall nicht. |
| B4-02 / B4 | Sterne 0/6, manipulierte Typen und unbekannte Frage abgewiesen ohne Speicherung; Gegenfall bekannte sichtbare Frage mit 1 bzw. 5 Sternen korrekt. | offen | — | Nachweis fehlt; #10. |
| B4-03 / B4 | Fragennamen, Tags und Filterwerte mit HTML/JS führen in Block/Ergebnisanzeige kein Skript aus; Gegenfall normale Texte korrekt. | offen | — | Noch nicht untersucht; #9, KI-Prüfseite separat KI-02/#12. |
| B4-04 / B4 | Manipulierte IDs, Suchbereich, Listen und Limit-Parameter werden validiert, Ergebnisumfang serverseitig begrenzt; Gegenfall zulässige Werte liefert erwartete Ergebnisse. | offen | — | Noch nicht untersucht; #9; Bewertungseingaben B4-02, KI-Mengen KI-03. |

### Dateien und Export

| Fall-ID / Bereich | Konkreter Fall und erwartetes Ergebnis | Status | Belegart / Quelle | Befund oder N/A-Begründung / Folgeticket |
| --- | --- | --- | --- | --- |
| EX-01 / Dateien, B2 | Ohne Pool-Konto/echte Anmeldung: Export auch nach öffentlicher Suche im selben AJAX-Batch abgewiesen; keine Fachdatenänderung. | OK | Automatisierter Test / E3, E1; erneut S3 | Negativfall belegt. Zulässiger Export-Gegenfall EX-02 bleibt offen; #9. |
| EX-02 / Dateien, B2 | Mit berechtigtem Pool-Konto bzw. echter Anmeldung: sichtbare ausgewählte Fragen in aktivierten Formaten Moodle XML, CSV und GIFT exportierbar; Inhalt korrekt. | offen | — | Nachweis fehlt; #9. Gegenfall zu EX-01 und EX-03/04. |
| EX-03 / Dateien, B2 | Fremde Fragen-ID aus nicht sichtbarer Fragensammlung oder gemischte erlaubte/fremde IDs geben keine fremden Fragen/Dateiinhalte preis; manipulierter Kontext hilft nicht. Gegenfall sichtbare eigene/erlaubte Fragen nach EX-02. | offen | — | Noch nicht untersucht; #9. Je Frage prüfen, Kontext allein genügt nicht. |
| EX-04 / Dateien, B2 | Deaktiviertes/unbekanntes Format abgewiesen ohne Datei-/Datenänderung; Gegenfall aktiviertes Format exportiert nach EX-02. | offen | — | Nachweis fehlt; #9. |
| EX-05 / Dateien, B2 | Erzeugte Exportdatei über `block_questionfilter_pluginfile` in `lib.php`: anonym, fremde Datei-ID/fremder Eigentümer, Kontextmanipulation oder Pfadtraversierung liefert keine fremden Dateien; Gegenfall berechtigter Exportierender kann eigene Datei nutzen. | offen | — | Noch nicht untersucht; #9: eigenen Download-Handler und tatsächlichen Dateifluss, Typ-/Mengenlimit und Bereinigung belegen. |
| EX-06 / Dateien | Eigene Upload-Endpunkte absichern. | ENTFÄLLT | Review / Dateibaum am geprüften Commit | Keine eigenen Upload-Endpunkte; eigener `pluginfile`-Download ist anwendbar und in EX-05 erfasst. Bei Einführung Plan erweitern. |

### Personenbezogene Daten und Cookie-Bewertung (ADR 0003)

| Fall-ID / Bereich | Konkreter Fall und erwartetes Ergebnis | Status | Belegart / Quelle | Befund oder N/A-Begründung / Folgeticket |
| --- | --- | --- | --- | --- |
| PD-01 / personenbezogene Daten, B2 | Client-Parameter oder neue Browser-Kennung darf keine zusätzliche Besucheridentität wählen; Gegenfall Server verwendet für beide Plugins dasselbe `kf_rater_id`, bei fehlendem Cookie serverseitige Erzeugung. | FEHLER | Review / R2, ADR 0003 | Clientseitig erzeugte Bewertungskennung akzeptiert; Behebung #10. Keine Konto-ID als Besucherzuordnung (Pool-Konten werden wiederverwendet). |
| PD-02 / personenbezogene Daten, B3 | Gleiche Cookie-Kennung und Frage zählt auch wiederholt/parallel höchstens einmal; Gegenfälle andere Frage oder andere serverseitige Besucherkennung unabhängig. Zwei Besucher am nacheinander genutzten Pool-Konto werden nicht vermischt. | offen | — | Nachweis fehlt; #10 einschließlich Schema-Eindeutigkeit. |
| PD-03 / personenbezogene Daten | Cookie nicht aus JS lesbar, über unsicheren Transport nicht gesendet und Cross-Site-Verhalten begrenzt; Gegenfall HTTPS nutzt zufällige serverseitige SHA-256-Kennung mit `secure`, `httponly`, `samesite=Lax`, einem Jahr Laufzeit. | offen | — | Nachweis fehlt; #10 referenziert Kursfilter-Cookie-Implementierung samt Commit/Tests und belegt Fragenfilter-Nutzung. |
| PD-04 / personenbezogene Daten | Cookie gelöscht/Browser gewechselt: neue serverseitige Kennung darf erneut bewerten; Gegenfall erhaltenes Cookie erzeugt keine zweite Bewertung derselben Frage. | offen | — | Nachweis fehlt; #10. Bewusst akzeptierte Modellgrenze aus ADR 0003, kein belastbarerer Identitätsnachweis zugesagt. |
| PD-05 / personenbezogene Daten | Provider verschweigt weder Bewertungstabelle/Cookie noch ggf. Ereignisse und KI-Weitergabe; Gegenfall vollständige korrekte Metadaten statt `null_provider`. | FEHLER | Review / R3, ADR 0003 | `null_provider` trotz personenbezogener Daten; Behebung #11. |
| PD-06 / personenbezogene Daten, B2 | Privacy-Export/-Löschung darf fremde Kontodaten nicht betreffen; Gegenfall zuordenbare eigene Daten über Moodle-Privacy-API korrekt exportiert/gelöscht. Nicht kontozuordenbare Cookie-Daten und Löschgrenze ausdrücklich erklären. | offen | — | Noch nicht untersucht; #11 auf Moodles Provider-Testbasis. Keine künstliche Konto-Zuordnung herstellen. |
| PD-07 / personenbezogene Daten | Empfänger, Zwecke, Aufbewahrung/Cookie-Laufzeit und ggf. Ereignisse/Datenlöschung nachvollziehbar; Gegenfall keine undeklarierte personenbezogene Übermittlung an KI. | offen | — | Noch nicht untersucht; #11 zusammen mit KI-01/#12; Verantwortliche klären Aufbewahrung bis #13. |

### Netzwerk und KI-Direktanbindung

| Fall-ID / Bereich | Konkreter Fall und erwartetes Ergebnis | Status | Belegart / Quelle | Befund oder N/A-Begründung / Folgeticket |
| --- | --- | --- | --- | --- |
| NW-01 / Netzwerk | Unzulässiges Ziel der konfigurierbaren Ollama-URL (interne/metadatenbezogene Ziele, unzulässige Schemes und Redirect-Ziele) wird blockiert; Gegenfall ausdrücklich zugelassener lokaler Ollama-Dienst bzw. Claude-Ziel erreichbar. | offen | — | Noch nicht untersucht; #12 bewertet erlaubte Ziele und Moodle-cURL-/SSRF-Konfiguration. Lokales Ollama braucht eine begründete Betriebsfreigabe, kein pauschales Verbot interner Ziele. |
| NW-02 / Netzwerk | TLS-Zertifikatsfehler/unerlaubter Klartexttransfer wird abgewiesen; Gegenfall verifiziertes HTTPS, ggf. begründeter lokaler Ollama-Transport. API-Key nicht in Ausgabe, URL oder Logs offengelegt. | offen | — | Noch nicht untersucht; #12. API-Key auch in Einstellungen, Fehlerpfaden und Redirects prüfen. |
| NW-03 / Netzwerk | Langsames Ziel und übergroße Antwort/Request werden begrenzt; Gegenfall normale Antwort rechtzeitig verarbeitet; Fehler für Betreiber sichtbar. | offen | — | Nachweis fehlt; #12. Vorhandene Timeout-Konfiguration allein ist kein ausgeführter Grenznachweis. |
| KI-01 / KI, personenbezogene Daten | Nicht berechtigte/unerforderliche Personen-/Frageninhalte verlassen den Hub nicht; Gegenfall nur erlaubte minimierte Inhalte an deklarierten Anbieter. Fragentext mit personenbezogenen Angaben als Negativfall aufnehmen. | offen | — | Noch nicht untersucht; #12/#11. Entfernte IDs allein anonymisieren Freitext nicht; K5/K6, Datenfreigabe und Empfänger prüfen. |
| KI-02 / KI, B4 | HTML/JS und Anweisungen in KI-Vorschlägen werden als unvertrauenswürdige Daten validiert/maskiert, nie als Befehl ausgeführt; Gegenfall normale Tags korrekt angezeigt und erst berechtigt übernommen. | offen | — | Nachweis fehlt; #12. Maskierung in Block und Prüfseite; Autorisierung nach B3-03 erneut prüfen. |
| KI-03 / KI | Überlange Prompts/Tags, massenhafte Aufrufe und unerwartete Antwortstruktur überschreiten keine festgelegten Mengen-/Kostenlimits; Gegenfall zulässige Anfrage liefert begrenzte Tag-Vorschläge. | offen | — | Noch nicht untersucht; #12. Limits und Fehlerbewertung festlegen, kein Umbau auf `core_ai`. |

### Weitere Modulprüfung

| Fall-ID / Bereich | Konkreter Fall und erwartetes Ergebnis | Status | Belegart / Quelle | Befund oder N/A-Begründung / Folgeticket |
| --- | --- | --- | --- | --- |
| NA-01 / OAuth, Tokens | Eigener OAuth-/Token-Ausgabe-, Ablauf- oder Widerrufsmechanismus. | ENTFÄLLT | Review / `db/services.php`, `classes/ai_connector.php` | Kein eigener OAuth-/Token-Issuer; Moodle-AJAX nach B2/B3, externer API-Key nach NW-02. |
| NA-02 / eigene Drittbibliotheken | Separat gebündelte Drittbibliotheken inventarisieren/auditieren. | ENTFÄLLT | Review / Dateibaum | Keine eigenen gebündelten Drittbibliotheken; Moodle/Core und Kursfilter bleiben Abhängigkeiten im B1-Prüfstand. |
| NA-03 / Backup/Restore | Eigene Backup-/Restore-Implementierung erhält Daten/Eigentümer. | ENTFÄLLT | Review / Dateibaum | Keine eigene Backup-/Restore-Implementierung; Upgrade und personenbezogene Löschung sind anwendbar (B1-02, PD-06). |

## Fortschritt und Freigabe

Planversion 1, historische E1–E4-Grenze und #7-Nachweise S1–S3 gemäß Prüfkopf:

- Festgelegt: **43 Fälle**, davon **4 ENTFÄLLT**, also **39 anwendbar**.
- Status anwendbar: **6 OK, 3 FEHLER, 30 offen, 0 HINWEIS**.
- Prüfplan-Fortschritt: **6 / 39 = 15,38 %** erfolgreich belegt (B1-01, B1-05, B2-01, B2-09, B3-01, EX-01).
- Automatisiert erfolgreich geprüft: **6 / 39 = 15,38 %** (dieselben Fälle, E1–E4 und S1–S3; Befundbewertung von B1-05 zusätzlich per Review). Ein Lauf kann mehrere Fälle belegen; keine Zählung pro Assertion oder Matrixjob.
- Code-Liniencoverage: **nicht erhoben**; aus PHPUnit-Assertions nicht ableitbar.

**Keine Freigabe.** Drei FEHLER und offene Prüffälle verhindern die Freigabe nach Spec #2; Paketinstallation und Freigabeverantwortung fehlen.
Den FEHLER B2-04 verantwortet #8, PD-01 #10, PD-05 #11; Termine sind noch nicht festgelegt.
Weitere offene Fälle sind je Zeile den Folgetickets zugeordnet. #13 hält den abschließenden Freigabeentscheid samt verantwortlicher Person fest.

Bei **jedem Folgeticket der Spec #2** die betroffenen Fälle in derselben Änderung aktualisieren:
Fall-ID, tatsächlich geprüftes Szenario mit Negativ-/Gegenfall, Belegart und konkrete Quelle (Testname, Lauf/Log, Commit, Versionen), Status und Restbefund.
Ein abgelehnter Schreibaufruf braucht einen Nachweis unveränderter Daten/Identität. Kursfilter-Belege nur für dessen Grenzen mit genauem Stand referenzieren.
Nach relevanten Codeänderungen betroffene alte Belege als historischen Stand kennzeichnen und die Fälle bis zur erneuten Prüfung öffnen.
Bei Umfangsänderung Planversion erhöhen und Nenner, Statussummen sowie beide Fortschrittswerte neu berechnen. #13 ersetzt den Prüfkopf durch den finalen Commit/Build/Paketstand.
