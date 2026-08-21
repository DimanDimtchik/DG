# Datenimport — Formate & Quellsysteme

## Prinzip für Kunden ohne technisches Vorwissen

1. **Datei hochladen, fertig** — kein „erst in CSV umwandeln“
2. **Quellsystem wählen** — kurze Anleitung, wo man im alten Programm auf Export klickt
3. **Spalten automatisch erkennen** — inkl. programmspezifischer Aliasse (DATEV, ShiftBase, …)
4. **Was nicht geht** — ehrlich sagen und Alternative anbieten (Support-Import)

## Unterstützte Dateiformate (Installation)

| Datentyp | Excel (.xlsx) | CSV | XML | JSON | PDF |
|----------|---------------|-----|-----|------|-----|
| Kontakte | ✓ | ✓ | ✓ | ✓ | — |
| Mitarbeiter | ✓ | ✓ | ✓ | ✓ | — |
| Termine | ✓ | ✓ | ✓ | ✓ | — |
| Artikel/Leistungen | ✓ | ✓ | ✓ | ✓ | ✓ (Tabellen) |
| Belege | — | — | — | — | ✓ (Staging, Verarbeitung TODO) |

Alte Excel-Dateien (.xls): Hinweis im Assistenten, einmal als .xlsx speichern.

## Quellsystem-Presets (`InstallImportSourcePresets`)

- Excel / LibreOffice
- Outlook, Google Kontakte
- DATEV, Lexware, sevDesk/Lexoffice
- ShiftBase
- Anderes / unsicher

Presets erweitern die Spalten-Erkennung (z. B. Outlook `Given Name` → Vorname).

## Bewusst nicht im Installationsassistenten

| Quelle | Warum | Alternative |
|--------|-------|-------------|
| Direkte Datenbank-Verbindung | Zu fehleranfällig, Sicherheit, Kunde versteht es nicht | Datei-Export oder Support |
| DATEV EXTF Buchungsstapel | Eigenes Format, Buchhaltungslogik | Phase 2 Beleg-Import |
| ShiftBase API | OAuth, laufende Sync-Jobs | Export-Datei jetzt, API später |
| Lexware proprietäre DB | Kein Zugang beim Kunden | Excel-Export aus Lexware |

## Roadmap

1. **Jetzt:** Datei-Upload + Presets + Auto-Spalten
2. **Als Nächstes:** Beleg-OCR und DATEV-Stammdaten-Profile verfeinern
3. **Später:** API-Anbindungen (ShiftBase, sevDesk), optional „Wir importieren für Sie“ im Support
