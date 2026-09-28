# Szenario: Einstellungen — Termine — Arbeitszeiten

Stand: 2026-09-28 · Ziel-Dauer: ca. 2–3 Minuten

> **Regeln:** [`VIDEO-REGELN.md`](VIDEO-REGELN.md) · **Text:** [`locales/de/einstellungen-arbeitszeiten.json`](locales/de/einstellungen-arbeitszeiten.json)

## Ziel

Zeigen, wo **globale Öffnungs- und Buchungszeiten** liegen und wie man eine **neue Arbeitszeit** anlegt (Ab-Datum, Start/Ende, Wochentage). Damit versteht der Nutzer, wann Kunden und Mitarbeiter freie Termine sehen.

## Ablauf

| # | Bild | Inhalt |
|---|------|--------|
| 1 | Dashboard | Einstieg: Arbeitszeiten steuern, wann Termine buchbar sind |
| 2 | Dashboard → Einstellungen | Navigation zur Kachel Einstellungen |
| 3 | Arbeitszeiten (Liste) | Weg: Einstellungen → Termine → Arbeitszeiten; bestehende Einträge |
| 4 | Liste | Tabelle: Ab Datum, Wochentage, Start, Ende |
| 5 | Formular öffnen | Abschnitt „Neue Arbeitszeit hinzufügen“ |
| 6 | Ab Datum | Ab wann die Regel gilt |
| 7 | Startzeit / Endzeit | Tagesfenster für Buchungen |
| 8 | Wochentage | Nur angehakte Tage sind buchbar |
| 9 | Speichern | Button „Arbeitszeit hinzufügen“ |
| 10 | Abschluss | Kurz: mehrere Regeln möglich (z. B. Saison), wirkt auf Online-Buchung |

## Demo-Daten (Bild)

- Bestehende Zeile: Mo–Fr, 09:00–17:00 (oder Instanz-Ist)
- Formular-Defaults: Ab 1. Januar des laufenden Jahres, 09:00–17:00, Mo–Fr angehakt
- Keine echten Kundennamen nötig

## Technik

- Export: `bin/academy-export-einstellungen-arbeitszeiten-html.php`
- Capture: `bin/academy-capture-page.py` (Liste + Formular offen)
- Locale: `docs/akademie/locales/de/einstellungen-arbeitszeiten.json`
- Render: `bin/academy-build-einstellungen-arbeitszeiten-video.sh`
- Kurs: Modul im Kurs **Terminkalender** (`academy-setup-terminkalender-course.php`)
- Medien: `storage/media/training/einstellungen/`
