# Buchhaltung — PC-Handoff (Deploy & Weiterarbeit)

> **Stand:** 21.08.2026  
> **Branch:** `cursor/buchhaltung-phase-abc-6a0c`  
> **Deploy & Migration:** vom PC (Kasserver) — nicht im Cloud-Agent

Allgemeiner PC-Handoff (SSH, Shop, Import): [`docs/PC-HANDOFF.md`](PC-HANDOFF.md)

---

## 1. Branch holen

```powershell
cd C:\Users\dietr\Projects\DG   # dein Pfad
git fetch origin
git checkout cursor/buchhaltung-phase-abc-6a0c
git pull origin cursor/buchhaltung-phase-abc-6a0c
```

**Enthalten in diesem Branch (kumuliert):**

| Thema | Commit-Kontext |
|-------|----------------|
| DATEV-Doppelbuchung A–D | Journal, EXTF, OPOS, Kasse |
| DIY UStVA / EÜR-CSV | ohne Steuerberater |
| ELSTER/ERiC-Vorbereitung | Stub, Settings, Doku (kein Live-ERiC) |
| **Phasen A–C (neu)** | Filter, Skonto-USt, Druck, BWA, SuSa, MT940, Tagesabschluss |

**PR manuell:** https://github.com/DimanDimtchik/DG/compare/master...cursor/buchhaltung-phase-abc-6a0c

**Nach Tests mergen:**

```powershell
git checkout master
git pull origin master
git merge cursor/buchhaltung-phase-abc-6a0c
git push origin master
```

---

## 2. Deploy (Kasserver)

```powershell
.\deploy.bat
```

oder mit Plugins:

```powershell
.\deploy.ps1 -WithPlugins
```

Deploy lädt u. a. `database/migrations/` mit hoch — **kein separates SQL-Skript nötig**.

---

## 3. Migrationen

Migrationen laufen **automatisch** beim nächsten CRM-Zugriff (`MigrationRunner::runOnCrmAccess` in `index.php`).

**Neu in diesem Stand:**

| Datei | Inhalt |
|-------|--------|
| `053_elster_submissions.sql` | Tabelle `dg_elster_submissions` (ERiC-Historie, später) |
| `054_cash_day_closing.sql` | Tabelle `dg_cash_day_closings` (Kassentagesabschluss) |

### Nach Deploy prüfen

1. Einmal CRM öffnen: https://dg.ganz-om.de/app  
2. Optional per SSH/phpMyAdmin:

```sql
SELECT id, applied_at FROM dg_migrations
WHERE id IN ('053_elster_submissions.sql', '054_cash_day_closing.sql');
```

Erwartung: beide Zeilen mit `applied_at`.

### Falls Migration hängt

```powershell
ssh allinkl-ganzom
cd www/htdocs/.../dg.ganz-om.de
php bin/ledger-selftest.php
```

(`ledger-selftest.php` ruft `MigrationRunner::runPending()` auf und zeigt Fehler.)

---

## 4. Was ist neu? (Kurzüberblick)

### Phase A
- **Zeitraum-Filter** (Jahr / Monat / von–bis): Belege, Konten, Kasse, Auswertungen, UStVA
- **Skonto + USt** im Journal und in der UStVA
- **Druck/PDF**: Button „Drucken / PDF“ → Browser-Druckdialog (HTML, kein dompdf)

### Phase B
- **UStVA**: Nullmeldung-Hinweis, Berichtigung-Checkbox, Skonto in Kennziffern
- **Kassenbuch Tagesabschluss** (Formular + Historie)
- **BWA** — Menü: Buchhaltung → BWA

### Phase C
- **MT940-Import** — Bankabgleich (zusätzlich zu CAMT.053)
- **SuSa** — Menü: Buchhaltung → SuSa
- **OPOS Auto-Match**: IBAN, Datumsfenster, Skontobetrag
- **Steuerberater-Komplett-Paket** — Steuerberater-Export → „Komplett-Paket (ZIP)“

### Bewusst zurückgestellt (Sie bereiten Belege vor)
- OCR / Beleg-Upload-Automatik — siehe `docs/buchhaltung-todo.html`

---

## 5. Testliste (nach Deploy)

Abhaken in **`docs/TESTLISTE-2026-08-21.md`** → Abschnitt **J**.

Kurz:

| # | Test | Wo |
|---|------|-----|
| 1 | Zeitraum Monat wählen → Belege gefiltert | Buchhaltung → Belege |
| 2 | Kontoauszug mit Zeitraum + Druck | Kontenübersicht → Konto anklicken |
| 3 | Skonto-Beleg speichern → Journal 3 Zeilen (Skonto + USt) | Belegformular |
| 4 | UStVA Berichtigung + Nullmeldung-Hinweis | Buchhaltung → UStVA |
| 5 | Kassentagesabschluss speichern | Buchhaltung → Kassenbuch |
| 6 | BWA / SuSa mit Monatsfilter | neue Menüpunkte |
| 7 | MT940 oder CAMT importieren | Bankabgleich |
| 8 | Komplett-Paket ZIP laden | Steuerberater-Export |

Nach Deploy: **Shift+Strg+R** im Browser.

---

## 6. Offene Punkte / andere Branches

| Thema | Branch / Doku |
|-------|----------------|
| OCR / Beleg-Auslesen | zurückgestellt — `docs/buchhaltung-todo.html` |
| ERiC live (nach Server-Umzug) | `cursor/server-elster-prep-6a0c`, `docs/ELSTER-ERIC-TODO.md`, `docs/SERVER-MIGRATION.md` |
| Install-Datenimport | `cursor/install-data-import-6a0c` (separat) |

**Server-Favorit für ERiC:** Hetzner SX65-2 (Ryzen 7, 64 GB RAM) — Details in `docs/SERVER-MIGRATION.md`.

---

## 7. Wichtige Dateien

```
src/Accounting/AccountingPeriodFilter.php   # Zeitraum-Filter
src/Accounting/AccountingPrintService.php   # Druck-HTML
src/Accounting/BwaReportService.php
src/Accounting/SusaReportService.php
src/Accounting/Mt940Importer.php
src/Accounting/CashDayCloseService.php
src/Accounting/SteuerberaterPaketService.php
database/migrations/054_cash_day_closing.sql
docs/buchhaltung-todo.html                  # aktualisierte Checkliste
views/partials/accounting-period-filter.php   # Filter-UI (wiederverwendet)
```

---

## 8. Schnellstart am PC (5 Min)

```powershell
git fetch origin
git checkout cursor/buchhaltung-phase-abc-6a0c
git pull
.\deploy.bat
# Browser: CRM öffnen → Migration läuft → Abschnitt J testen
```

Fragen / Blocker: Branch-Name und Pfade in dieser Datei sind die Ankerpunkte.
