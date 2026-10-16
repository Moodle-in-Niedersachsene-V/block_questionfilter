# Bewertung über das Kursfilter-Cookie

Besucher bewerten Fragen ohne eigenes Konto. Fragenfilter nutzt dafür dieselbe pseudonyme Kennung wie der Kursfilter: das Cookie `kf_rater_id` (zufällige SHA-256-Kennung, serverseitig erzeugt, ein Jahr Laufzeit, `secure`, `httponly`, `samesite=Lax`; Kursfilter ADR 0003 und 0005). Je Cookie und Frage gilt höchstens eine **Bewertung** (1–5 Sterne). Schreibende Aufrufe brauchen einen gültigen Sesskey.

Bisher erzeugte der Browser die Kennung selbst (`sessionStorage`, `Math.random`) und schickte sie mit; jeder Aufruf mit neuer Kennung zählte als neue Bewertung. Ein eigenes Cookie neben `kf_rater_id` hätte für denselben Hub eine zweite Kennung und einen zweiten Datenschutzhinweis bedeutet. Wie beim Kursfilter zählt nicht die Konto-ID: Pool-Konten bedienen nacheinander verschiedene Besucher ([0002](0002-public-search-preview-and-export-via-kursfilter-pool.md)).

## Consequences

Die Kennung ist personenbezogen (pseudonym). Fragenfilter braucht deshalb einen echten Privacy-Provider statt `null_provider`, der Tabelle, Cookie und gegebenenfalls Ereignisse deklariert. Wer das Cookie löscht oder den Browser wechselt, kann erneut bewerten; das ist wie beim Kursfilter hingenommen. Ändert der Kursfilter sein Bewertungsmodell, wird diese ADR mit überprüft.
