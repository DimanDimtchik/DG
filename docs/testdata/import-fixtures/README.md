# Import-Testfixtures (Entwicklungsseite)

Testdaten für **dg.ganz-om.de** — Kontakt-Massenimport und Arbeitsstunden-Import.
Alle Personen sind **erfunden**; E-Mails nutzen `@demo-import.ganz-om.invalid` (kein Versand).

## Generieren / aktualisieren

```bash
# lokal (Python)
py -3 bin/generate-import-fixtures.py

# oder auf dem Server / mit PHP
php bin/generate-import-fixtures.php
```

Beide Skripte erzeugen denselben Inhalt unter `docs/testdata/import-fixtures/`.

## Roster (12 Mitarbeiter)

| Login | Name | E-Mail | Testrolle |
|-------|------|--------|-----------|
| `demo-dup-01` | Anna Duplikat | anna.duplikat@demo-import.ganz-om.invalid | Seed → Duplikat beim Vollimport |
| `demo-dup-02` | Bernd Doppel | bernd.doppel@demo-import.ganz-om.invalid | Seed → Duplikat beim Vollimport |
| `demo-ma-03` | Clara Fischer | clara.fischer@… | neu |
| `demo-ma-04` | David Hoffmann | david.hoffmann@… | neu |
| `demo-ma-05` | Elena Jung | elena.jung@… | neu |
| `demo-ma-06` | Felix Keller | felix.keller@… | neu |
| `demo-ma-07` | Greta Lange | greta.lange@… | neu |
| `demo-ma-08` | Hans Meier | hans.meier@… | neu |
| `demo-ma-09` | Ina Neumann | ina.neumann@… | neu |
| `demo-ma-10` | Jonas Otto | jonas.otto@… | neu |
| `demo-ma-11` | Karla Peters | karla.peters@… | neu |
| `demo-ma-12` | Leon Richter | leon.richter@… | neu |

Rolle in den Dateien: `mitarbeiter`. Domain jeweils `@demo-import.ganz-om.invalid`.

**Firma:** Spalte bewusst leer. Beim Import von Mitarbeiter-Rollen setzt das CRM den Firmennamen aus **Einstellungen → Firma** (`CompanySettings`), sofern dort gesetzt.

**Kundennummer / Mitarbeiternummer:** Spalte bewusst leer. Beim Speichern vergibt das CRM automatisch die nächste Nummer aus dem Nummernkreis **Kundennummer** (gleiches Feld wie für Kunden).

## Erwartete Zähler (Kontakte)

| Schritt | Datei | Quellsystem | on_duplicate | Erwartung |
|---------|-------|-------------|--------------|-----------|
| 1 Seed | `kontakte/00-seed-duplikate.csv` | Excel | — | **2** imported |
| 2 Vollimport | `kontakte/kontakte-*.csv` (12 Zeilen) | passend zur Datei | **skip** | **10** imported, **2** duplicates |

## Kontakt-Dateien

### Pro Quellsystem (CSV)

| Datei | Quellsystem in UI |
|-------|-------------------|
| `kontakte/kontakte-excel.csv` | Excel / LibreOffice |
| `kontakte/kontakte-outlook.csv` | Microsoft Outlook |
| `kontakte/kontakte-google.csv` | Google Kontakte |
| `kontakte/kontakte-datev.csv` | DATEV |
| `kontakte/kontakte-lexware.csv` | Lexware |
| `kontakte/kontakte-sevdesk.csv` | sevDesk / Lexoffice |
| `kontakte/kontakte-shiftbase.csv` | ShiftBase |
| `kontakte/kontakte-other.csv` | Anderes Programm |

### Format-Pack (Excel-Roster)

| Datei | Typ |
|-------|-----|
| `kontakte/kontakte-excel.txt` | CSV-Inhalt, Endung `.txt` |
| `kontakte/kontakte-excel.xlsx` | Excel |
| `kontakte/kontakte-excel.xml` | tabellarisch XML (`row`) |
| `kontakte/kontakte-excel.json` | JSON (`rows`) |

## Stunden-Dateien (01.06.2026–19.09.2026, Werktage)

| Datei | Quellsystem |
|-------|-------------|
| `stunden/stunden-excel.csv` | Excel / eigene Tabelle |
| `stunden/stunden-excel.xlsx` | Excel |
| `stunden/stunden-shiftbase.csv` | Shiftbase |
| `stunden/stunden-crewmeister.csv` | Crewmeister |
| `stunden/stunden-other.csv` | Sonstiges (teilweise nur Stunden-Spalte) |

Zuordnung über Login oder E-Mail. Ca. 80 Werktage × 12 MA.

---

## Abnahmeschritte (dg.ganz-om.de)

Voraussetzung: eingeloggt als Admin/HR mit Kontakt-Import und Stunden-Import (`canViewTeam`).

### A — Kontakte + Doppelgänger

1. **Kontakte** → „Kontakte aus Datei importieren“.
2. Quellsystem **Excel**, Datei `00-seed-duplikate.csv`, Rolle **Mitarbeiter**, **Rolle erzwingen**, bei Duplikat **Überspringen** → Import → erwarten **2** neu.
3. Dieselbe UI: `kontakte-excel.csv`, wieder **Überspringen** → erwarten **10** imported, **2** duplicates (Anna/Bernd).
   Bereits importierte MA mit leerer Firma: erneut mit **„nur leere Felder füllen“** importieren (dann Firmenname aus Einstellungen).
4. Optional Format-Test (je eine Datei, Quellsystem Excel): `.txt`, `.xlsx`, `.xml`, `.json` — nach Löschen der 10 `demo-ma-*` oder auf frischer DB; bei bereits vorhandenen 12er: nur Duplikate.
5. Optional Quellen-CSVs: jeweils passendes Quellsystem wählen (`outlook`, `google`, …).

### B — Stunden

1. Seite **Zeiterfassung → Stunden-Import** (`/app?page=zeiterfassung-stundenimport`).
2. Quellsystem **Excel**, Datei `stunden-excel.csv` (oder `.xlsx`), Konflikt **Überspringen** oder **Ersetzen**.
3. Stichprobe: **Monatsblatt** Juni 2026 für `demo-ma-03` — Werktage mit Ist-Zeiten ab 01.06.
4. Weitere Läufe (nach Ersetzen oder leerer DB): Shiftbase- / Crewmeister- / other-CSV mit passendem Quellsystem.

### C — Cleanup (optional)

Kontakte mit Login `demo-dup-*` / `demo-ma-*` und zugehörige Stempel (`source=import`) manuell entfernen oder Instanz-DB zurücksetzen — Fixtures sind wiederholbar.
