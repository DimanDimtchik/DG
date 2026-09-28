# Szenario: Einstellungen — Termine — Kalender Design

Stand: 2026-09-28 · Ziel-Dauer: ca. 2–3 Minuten

> **Regeln:** [`VIDEO-REGELN.md`](VIDEO-REGELN.md) · **Text:** [`locales/de/einstellungen-kalender-design.json`](locales/de/einstellungen-kalender-design.json)

## Ziel

Zeigen, wo die **Farben des öffentlichen Buchungskalenders** eingestellt werden: Farbvorlagen wählen, einzelne Farben anpassen, Live-Vorschau prüfen, speichern.

## Ablauf

| # | Bild | Inhalt |
|---|------|--------|
| 1 | Dashboard | Einstieg: Kalender Design = Farben für die Kunden-Buchungsseite |
| 2 | Dashboard → Einstellungen | Navigation |
| 3 | Kalender Design | Einstellungen → Termine → Kalender Design; Überblick |
| 4 | Farbvorlagen | Fertige Sets (z. B. Kaffee Braun, Klassisch Blau) |
| 5 | Einzelne Farben | Bereich mit sieben Farbfeldern |
| 6 | Primärfarbe | Buttons und aktive Elemente |
| 7 | Button Hover | Hover über Buttons |
| 8 | Termin-Slot Hintergrund | Freie Zeiten |
| 9 | Termin-Slot Hover | Hover über freie Zeiten |
| 10 | Ausgewählt Hintergrund | Gewählte Zeit |
| 11 | Ausgewählt Rahmen | Rahmen der gewählten Zeit |
| 12 | Gebucht Hintergrund | Bereits vergebene Zeiten |
| 13 | Vorschau | Live-Vorschau rechts |
| 14 | Speichern | „Kalender Design speichern“ |
| 15 | Abschluss | Wirkt auf Shortcode und Online-Buchung |

## Beschreibung (Akademie)

- **DB-Kurzbeschreibung:** Locale-Intro (erster Segment-Text)
- **Zum Nachlesen:** alle Narrations aus dem Locale-JSON
- **Praxis-Button:** Einstellungen → Tab `kalender-darstellung`

## Technik

- Export: `bin/academy-export-einstellungen-kalender-design-html.php`
- Locale: `docs/akademie/locales/de/einstellungen-kalender-design.json`
- Build: `bin/academy-build-einstellungen-kalender-design-video.sh`
- Medien: `storage/media/training/einstellungen/`
- Kurs: Terminkalender
