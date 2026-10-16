# Fragebank-Filter

Ein Block, mit dem man Fragen aus allen zugänglichen Fragensammlungen (System-, Kurs- und Modulkontext) findet, filtert und exportiert.

## Fragen

**Fragensammlung**:
Eine Moodle-Fragebank in einem Kontext (System, Kurs oder Modul), aus der der Block Fragen liest.
_Avoid_: Fragenpool, Bank
Code: `question bank`

**Frage**:
Eine einzelne Moodle-Frage mit Name, Typ, Tags und Schwierigkeitsstufe.
_Avoid_: Item, Aufgabe
Code: `question`

**Schwierigkeitsstufe**:
Vom Admin konfigurierbare Stufe einer Frage (z. B. A1/A2/B1), eine pro Zeile in den Einstellungen.
_Avoid_: Level, Niveau
Code: `difficulty level`

**Suchbereich**:
Festlegung, in welchen Kontexten gesucht wird: alle, nur der aktuelle Kurs oder nur System.
_Avoid_: Scope
Code: `search scope`

## Ergebnisse

**Ergebnis**:
Eine Frage, die zu einer Suchanfrage passt; Anzahl ist durch das Limit für Ergebnisse begrenzt.
_Avoid_: Treffer, Hit
Code: `result`

**Export**:
Download ausgewählter Fragen im Format Moodle XML, CSV oder GIFT.
_Avoid_: Download, Backup
Code: `export`

**Format**:
Ein Exportformat (Moodle XML, CSV oder GIFT), einzeln aktivierbar.
_Avoid_: Dateityp
Code: `export format`

## Berechtigungen

**Sichtbarkeit**:
Welche Fragen ein Nutzer sehen darf, geregelt über die Moodle-Capabilities `question:viewmine` und `question:viewall`.
_Avoid_: Rechte bei der Suche
Code: `visibility`
