# Vereinsregelsatz

`moodle-security.yml` ist eine bytegleiche Kopie aus
[Moodle-in-Niedersachsene-V/security-checks](https://github.com/Moodle-in-Niedersachsene-V/security-checks/blob/37b1702d79bb5eb8d01abfc286a1ae88060123a3/moodle-security.yml).

- Quellen-Commit: `37b1702d79bb5eb8d01abfc286a1ae88060123a3`
- Regelstand laut Quelle: 05.10.2026; abgerufen: 16.10.2026
- SHA-256: `81806ca5e5d17913832e5baa40fd103bcc8b55f612e56248fadaa9fcf5590487`
- 23 Regeln; keine lokalen Regeländerungen.
- Scanner: Semgrep 1.179.0, Container über Digest in CI und Testanleitung fixiert.

Aktualisierungen übernehmen den vollständigen Quelltext und aktualisieren Commit,
Datum und Hash. Danach unsichere Beispiele, sichere Gegenfälle, Exitverhalten und
alle Schweregrade erneut prüfen. Der Quellenbaum dieses Commits enthält trotz
Hinweis im Regelkopf keine `tests/run_fixtures.py` oder andere Regel-Fixtures;
deshalb wurden für #7 eigene isolierte Gate-Proben verwendet.

Lokaler Lauf: [testing.md](../../docs/agents/testing.md).
Befundbewertung und Aussagegrenzen: [Fallplan](../../docs/fallplan.md).
