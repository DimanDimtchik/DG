# Szenario: Terminkalender — Online-Buchung (Kunde)

Stand: 2026-09-15 · Ziel-Dauer: ca. 2–3 Minuten

## Ziel

Zeigen, wie **Kunden selbst online buchen**: Weg zur Buchungsseite (Link, QR-Code, Google), alle Formularfelder, Abschluss — und eine **detaillierte Beispiel-Bestätigungsmail**. Storno- und Erinnerungs-Mails nur kurz erwähnen.

## Ablauf

| # | Bild | Inhalt |
|---|------|--------|
| 1 | Dashboard | Einstieg: Kunden buchen selbst — Sie richten Link und Seite ein |
| 2 | Dashboard → Einstellungen | Navigation zu Einstellungen → Termine → Kalender-Einbindung |
| 3 | Kalender-Einbindung | Checkbox aktiv, öffentliche URL, QR-Code |
| 4 | Öffentliche Seite Schritt 1 | Leistung wählen |
| 5 | Schritt 2 | Datum, freie Zeiten |
| 6 | Schritt 3 | Name, E-Mail, Telefon, Buchen-Button |
| 7 | Erfolg | Bestätigung auf der Seite |
| 8 | E-Mail-Vorschau | Buchungsbestätigung im Detail (Betreff, Inhalt, Termindaten, Buchungsnummer) |
| 9 | Abschluss | Kurz: Storno/Erinnerung unter Benachrichtigungen anpassbar |

## Demo-Daten

- Leistung: erste buchbare Leistung (z. B. Massage)
- Termin: +3 Tage, 10:45 Uhr
- Kunde: Maria Beispiel, maria.beispiel@example.de
- Buchungsnummer in E-Mail: DG-7K2M9P4Q (Demo-Kontext)

## Technik

- Export: `bin/academy-export-terminkalender-online-html.php`
- Locale: `docs/akademie/locales/de/terminkalender-online-buchung.json`
- Render: `bin/academy-build-terminkalender-videos.sh` (Clip 3)
