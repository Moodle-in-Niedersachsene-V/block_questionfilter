# Testing

Stand: Dieses Repo hat aktuell **keine** eigene Testsuite (kein `tests/`). CI läuft über `.github/workflows/moodle-ci.yml` mit phpcs, phpdoc, savepoints, mustache und grunt; der PHPUnit-Schritt ist bewusst weggelassen.

- Sobald Tests dazukommen, gilt der Kursfilter-Stand: PHPUnit in einer Moodle-Testumgebung (Moodle 5.1/5.2), Testfälle nach [CODING_STANDARDS.md](../../CODING_STANDARDS.md) (T1, T2, T3).
- Änderungen an `amd/src/` brauchen einen Grunt-Build (`amd/build/`), sonst schlägt der CI-Schritt `grunt` fehl.
- Nach Änderungen an `db/access.php`, `db/services.php`, `db/install.xml` oder `version.php` Moodle-Upgrade ausführen.
