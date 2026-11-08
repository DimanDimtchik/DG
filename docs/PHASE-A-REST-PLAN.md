# Phase A — Rest (tokenarm)

> **Eine Chat-Session = eine Mini-ID.**  
> Fachquelle: [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) §A · Vor-Check: [`VOR-HETZNER-CHECK.md`](VOR-HETZNER-CHECK.md) · Tests: [`TESTLISTE-2026-09-22.md`](TESTLISTE-2026-09-22.md)

Stand: **2026-11-08** · Branch-Empfehlung: `cursor/phase-a-…`

---

## Warum tokenarm

| Regel | Bedeutung |
|-------|-----------|
| **Kein Explorer-Chat** | Nur Datei(en) / Checkliste der Mini-ID |
| **Kein Hetzner-Cutover** | Phase B separat — hier nur A |
| **Kein ERiC live** | C1 nach Umzug |
| **1 ID = 1 Commit-fähig** | Doc-Haken, 1 Fix, 1 Selftest oder 1 manueller Nachweis |
| **Kein Tenant im Produktcode** | Firmendaten nur Instanz-DB; Hosts nur in Ops-Docs |

**Chat-Prompt-Vorlage (kopieren):**  
`Nur Mini-ID PA1b laut docs/PHASE-A-REST-PLAN.md. Nur genannte Datei(en)/Checkliste. Kein Refactor. Kein Hetzner-Cutover.`

**Nicht im Agent-Chat:** Phase B/C Cutover, Baulohn, ITSG-Versand, Live-ELSTER, parallele Mini-IDs.

---

## Token-Stufen

| Symbol | Wann | Typisch |
|--------|------|---------|
| 🟢 | Doc, Checkliste abhaken, 1 View-Hinweis, Guard | Free / lokal |
| 🟡 | 1 kleiner Code-Fix + Lint | Kurz-Agent |
| 🔴 | Manuell auf Instanz, AVV, Deploy-Ops, Parallelbetrieb | Mensch |

---

## Ist-Stand Inventar (2026-11-08)

| Block | Stand |
|-------|--------|
| A1 GoBD-Kern (Revision/Storno/ARAP/Kontohinweise) | ✅ Code |
| A1 Druck-Stichproben §14 / gemischt / §19·§13b·PV | ✅ PA1a/b/c (2026-11-08) |
| A1 PV-Automatik Klausel bei 0 %-Zeilen | ✅ Print + Firma-Flag (`e22d9d7` / PA1d) |
| A2 Manuelle Smoke-Tests | ❌ Mensch auf Master/Dev |
| A3 Shared-Core / Minify / AVV / Deploy-Prep | Doc ✅ PA3a–f · AVV-Unterschrift Org [~] |
| A4 Kunden-Instanz vorbereiten | [~] Domain/TLS · Rest offen |
| A5 Soll/Ist + Prüferexport | ✅ · ZUGFeRD ✅ · GmbH-UI ✅ · §13b-XML ✅ |
| A6 Telefon CTI | [~] manuell |
| A6 Multi-Firma Feinschliff | nur falls nötig |
| A6 ERP Maschinenblock + Gemeinkosten + Wissen | ✅ M0–M5 + ERP-Rest E1/E2 |

---

## Parent-Übersicht

| ID | Thema | Status | Token |
|----|--------|--------|-------|
| PA0 | Inventar / dieser Plan | [x] 2026-11-08 | 🟢 |
| PA1 | GoBD / Druck / PV-Automatik (A1 Rest) | [x] PA1a–e ✅ 2026-11-08 | 🟢🟡🔴 |
| PA2 | Manuelle Testliste (A2) | [~] PA2a–i ✅ | 🔴 |
| PA3 | Vor-Hetzner Infra (A3) | [x] PA3a–f Doc ✅ · AVV-Signatur Org [~] | 🔴🟢 |
| PA4 | Instanz-Vorbereitung (A4) | [~] PA4a/f/g ✅ · PA4b–e/h/i offen | 🔴 |
| PA5 | Kleine FiBu-Fixes (A5 Rest) | [x] PA5a–c ✅ | 🟢🟡 |
| PA6 | Nebenpunkte (A6 Rest) | [~] ERP ✅ | 🔴 |

---

## Abhackplan (Mini-IDs)

Status: `[ ]` offen · `[~]` angefangen · `[x]` erledigt

### PA0 — Doc

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| PA0a | Dieser Plan + Verweis in `IQ-STROM-HETZNER-TODO.md` / `TODOS.md` | 🟢 | [x] 2026-11-08 |

---

### PA1 — GoBD / Belege / Druck (A1 Rest)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| PA1a | Checkliste §14 UStG Druck/PDF je Dokumentart abarbeiten · Ergebnis in [`USTG-14-DRUCKSTICHPROBE.md`](USTG-14-DRUCKSTICHPROBE.md) | 🔴 | [x] 2026-11-08 Dev `ganz-om.de` · Gutschrift-Titel-Fix |
| PA1b | Stichprobe gemischte Steuersätze 19/7/0 auf einem Beleg (manuell + kurze Notiz im Stichproben-Doc) | 🔴 | [x] 2026-11-08 Dev · [`USTG-14-DRUCKSTICHPROBE.md`](USTG-14-DRUCKSTICHPROBE.md) |
| PA1c | §19 / §13b / PV §12 Abs. 3 Klauseln am Druck gegen Firma-Flags prüfen (Checkliste) | 🔴 | [x] 2026-11-08 Dev · [`USTG-14-DRUCKSTICHPROBE.md`](USTG-14-DRUCKSTICHPROBE.md) |
| PA1d | **Code:** PV-Automatik — bei Firma-Flag Photovoltaik + 0 %-Zeilen Klausel §12 Abs. 3 automatisch (wie §19); nur betroffene Beleg-/Klausel-Dateien | 🟡 | [x] 2026-11-08 |
| PA1e | A1-Haken in `IQ-STROM-HETZNER-TODO.md` nachziehen | 🟢 | [x] 2026-11-08 · A1-Standbanner + alle A1-Punkte [x] |

**Prompt PA1a:**  
`Nur Mini-ID PA1a laut docs/PHASE-A-REST-PLAN.md. Nur Druck-Stichprobe §14 laut USTG-14-DRUCKSTICHPROBE.md. Kein Refactor.`

**Prompt PA1d:**  
`Nur Mini-ID PA1d laut docs/PHASE-A-REST-PLAN.md. PV-Klausel-Automatik bei 0%-Zeilen. Nur genannte Beleg-/Klausel-Dateien. Kein Refactor. php -l.`

---

### PA2 — Manuelle Tests (A2)

Je Mini-ID = ein Smoke-Block auf Dev/Master; Ergebnis kurz in diesem Plan oder Testliste abhaken.

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| PA2a | Belegkette Angebot → AB → LS → Rechnung → Schluss | 🔴 | [x] 2026-11-08 Dev · Angebot→AB→LS→Abschlag→Schluss + Nebenkette→Rechnung |
| PA2b | Dokumentstatus / Filter / Freitext / Klauseln / Skonto / Mahnung / Teilzahlungen / OPOS | 🔴 | [x] 2026-11-08 Dev · Skonto-Tier-Fix + Dunning `email_2` |
| PA2c | Bank Geisterumsätze erkennen + ausblenden | 🔴 | [x] 2026-11-08 Dev · Duplikat + payment_exists · manuell ignored |
| PA2d | UStVA: Nullmeldung, Berichtigung, §13b-KZ, ELSTER-CSV | 🔴 | [x] 2026-11-08 Dev · KZ-Cast-Fix `UstvaReportService` · CSV Null + §13b 48/67 |
| PA2e | Jahresabschluss-Checkliste + Jahr sperren / Saldenvortrag | 🔴 | [x] 2026-11-08 Dev · Checkliste 7 Punkte · close/reopen 2097 · Sperre + Vortrag |
| PA2f | DATEV EXTF + Beleg-ZIP Stichprobe | 🔴 | [x] 2026-11-08 Dev · EXTF 700/21 · Beleg-ZIP · Steuerberater-Paket |
| PA2g | Zeiterfassung Z2–Z6 Smoke | 🔴 | [x] 2026-11-08 Dev · Stempel/Schicht/Urlaub/Rückstellung/Lohn-Export |
| PA2h | Kichel Smoke | 🔴 | [x] 2026-11-08 Dev · Widget/Nav/Antworten/Protokoll/Skonto |
| PA2i | Postfächer Smoke (falls offen) | 🔴 | [x] 2026-11-08 Dev · Menü/Liste/Rechte/Suche/Compose/Auth-Fehler (ohne Live-IMAP) |
| PA2j | Dokumentenverfasser Smoke | 🔴 | [x] 2026-11-08 Dev · Menü/Draft/Link/Export/Finalize·Reopen·Archiv · Fix SettingsStore DDL mid-txn |
| PA2k | A2-Haken in IQ-STROM-TODO nachziehen | 🟢 | [x] 2026-11-08 Standzeile A2 vollständig · PA2a–j ✅ |

**Prompt PA2a:**  
`Nur Mini-ID PA2a laut docs/PHASE-A-REST-PLAN.md. Manuelle Belegkette laut TESTLISTE/BUCHHALTUNG-BELEGKETTE. Ergebnis in Plan abhaken. Kein Code.`

---

### PA3 — Vor-Hetzner Infrastruktur (A3)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| PA3a | Shared-Core-Ordnerplan dokumentieren/prüfen ([`VOR-HETZNER-CHECK.md`](VOR-HETZNER-CHECK.md) §1) | 🟢 | [x] 2026-11-08 §1.1 Ist/Ziel |
| PA3b | Backup/Restore-Test durchführen + Notiz ([`VOR-HETZNER-CHECK.md`](VOR-HETZNER-CHECK.md) §4) | 🔴 | [x] 2026-11-08 → [`BACKUP-RESTORE-TEST.md`](BACKUP-RESTORE-TEST.md) |
| PA3c | Asset-Minify im Deploy verifizieren (`dg.app.min.css` / minify-Skript) | 🟢 | [x] 2026-11-08 Selftest grün; Deploy: rsync/sftp/`deploy.bat` → `minify-dg-css.sh` |
| PA3d | Hetzner-AVV / TOMs organisatorisch (Vertrag) — nur Checkliste/Status | 🔴 | [x] 2026-11-08 → [`HETZNER-AVV-TOM-CHECKLISTE.md`](HETZNER-AVV-TOM-CHECKLISTE.md) · Signatur ⬜ |
| PA3e | Deploy-Pipeline Prep (rsync/SSH) dokumentieren — **ohne** Cutover | 🟢 | [x] 2026-11-08 → [`DEPLOY-PIPELINE-PREP.md`](DEPLOY-PIPELINE-PREP.md) |
| PA3f | A3-Haken in IQ-STROM-TODO | 🟢 | [x] 2026-11-08 Standzeile + PA3b/d klar offen |

**Prompt PA3a:**  
`Nur Mini-ID PA3a laut docs/PHASE-A-REST-PLAN.md. Shared-Core laut VOR-HETZNER-CHECK §1. Nur Docs. Kein Cutover.`

**Prompt PA3c:**  
`Nur Mini-ID PA3c laut docs/PHASE-A-REST-PLAN.md. Asset-Minify prüfen (ggf. bin/asset-css-minify-selftest.php). Kein Cutover.`

---

### PA4 — Instanz vorbereiten (A4)

Ops/Mensch. Keine Firmendaten hardcoden im CRM-Code.

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| PA4a | Code-Sync + `config/*.local.php` an Instanz-DB (kein Sondercode) | 🔴 | [x] 2026-11-08 Code-Sync ✅ · `database.local.php`+`app.local.php` (Secrets `IQ_CRM_DB_*`) ✅ · Schema via `php83 bin/run-migrations.php` (169 Tabellen / 153 Migrationen bis `155_cash_tse_deferred.sql`) · `/install.php` offen für PA4b–d |
| PA4b | Firmendaten GmbH in Einstellungen (Steuernr., USt-IdNr., FA, Bilanz, SKR) | 🔴 | [ ] |
| PA4c | Flags: Photovoltaik §12 Abs. 3 + §13b Bauleistung | 🔴 | [ ] |
| PA4d | ≥3 Benutzer + Rechte | 🔴 | [ ] |
| PA4e | Nummernkreise, Bank, Briefpapier/Logo | 🔴 | [ ] |
| PA4f | Migrationskonzept Lexoffice → DG (Kontakte/Artikel/Belege/Bank) als Doc-Checkliste | 🟢 | [x] 2026-11-08 → [`LEXOFFICE-DG-MIGRATION.md`](LEXOFFICE-DG-MIGRATION.md) |
| PA4g | Parallelbetrieb festlegen (Zeitraum, Originalbeleg-Regel) — Doc | 🟢 | [x] 2026-11-08 → [`LEXOFFICE-DG-PARALLELBETRIEB.md`](LEXOFFICE-DG-PARALLELBETRIEB.md) |
| PA4h | Domain/TLS abschließen ([~] SSL) | 🔴 | [ ] |
| PA4i | A4-Haken in IQ-STROM-TODO | 🟢 | [ ] |

**Prompt PA4f:**  
`Nur Mini-ID PA4f laut docs/PHASE-A-REST-PLAN.md. Lexoffice→DG Migrationscheckliste als Doc. Kein Produktiv-Import. Kein Tenant-Hardcode.`

---

### PA5 — Kleine FiBu-Fixes (A5 Rest)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| PA5a | GmbH-Hinweise: Jahresabschluss-UI nicht als „EÜR ohne Kanzlei“ wenn Bilanz/GmbH — nur betroffene View(s) | 🟡 | [x] 2026-11-08 `buchhaltung-jahresabschluss.php` |
| PA5b | ZUGFeRD-Ausgang: Entscheidung dokumentieren (PDF+XRechnung ok bis Frist; Ticket Factur-X) | 🟢 | [x] 2026-11-08 → [`ZUGFERD-AUSGANG-ENTSCHEIDUNG.md`](ZUGFERD-AUSGANG-ENTSCHEIDUNG.md) |
| PA5c | E-Rechnung §13b: XRechnung Reverse-Charge BT-Felder prüfen; nur Fix wenn klar defekt | 🟡 | [x] 2026-11-08 AE + BT-120/121 · `bin/einvoice-reverse-charge-selftest.php` |
| PA5d | A5-Haken in IQ-STROM-TODO | 🟢 | [x] 2026-11-08 A5 Rest (GmbH/ZUGFeRD/§13b) |

**Prompt PA5a:**  
`Nur Mini-ID PA5a laut docs/PHASE-A-REST-PLAN.md. GmbH/Bilanz-Hinweis Jahresabschluss-UI. Nur genannte View(s). Katalog. Kein Refactor.`

**Prompt PA5b:**  
`Nur Mini-ID PA5b laut docs/PHASE-A-REST-PLAN.md. ZUGFeRD-Entscheidung nur Doc. Kein Factur-X-Code.`

---

### PA6 — Nebenpunkte (A6)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| PA6a | Telefon/CTI Fritzbox manuell aktivieren (Linphone/CallMonitor) — Statusnotiz | 🔴 | [ ] |
| PA6b | Multi-Firma Switcher nur bei konkretem Bedarf | 🟡 | [ ] deferred |
| PA6c | ERP Maschinenblock / Gemeinkosten / Wissen | — | [x] 2026-11-08 M0–M5 + E1/E2 |

---

## Empfohlene Free-Plan-Reihenfolge

1. **PA4b** Firmendaten (Installer `/install.php` oder Einstellungen) · dann **PA4c–e/h** · **PA4i**  
2. Phase B: AVV-Unterschrift laut Checkliste + Hardware (Org)

**Prompt Start (nächster Chat):**  
`Nur Mini-ID PA4b laut docs/PHASE-A-REST-PLAN.md. Firmendaten GmbH über /install.php oder Einstellungen. Kein Tenant-Hardcode im Code.`

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) | Phase A–D Master |
| [`VOR-HETZNER-CHECK.md`](VOR-HETZNER-CHECK.md) | Shared-Core, GoBD, Backup |
| [`TESTLISTE-2026-09-22.md`](TESTLISTE-2026-09-22.md) | Manuelle Smokes |
| [`USTG-14-DRUCKSTICHPROBE.md`](USTG-14-DRUCKSTICHPROBE.md) | §14 Stichprobe |
| [`ERP-REST-PLAN.md`](ERP-REST-PLAN.md) | A6 ERP erledigt |
| [`AMAZON-BUSINESS-REST-PLAN.md`](AMAZON-BUSINESS-REST-PLAN.md) | Muster Mini-IDs |
| [`ZUGFERD-AUSGANG-ENTSCHEIDUNG.md`](ZUGFERD-AUSGANG-ENTSCHEIDUNG.md) | PA5b Entscheidung PDF+XRechnung · Factur-X = D2 |
| [`LEXOFFICE-DG-MIGRATION.md`](LEXOFFICE-DG-MIGRATION.md) | PA4f Migrationscheckliste Kontakte/Artikel/Belege/Bank |
| [`LEXOFFICE-DG-PARALLELBETRIEB.md`](LEXOFFICE-DG-PARALLELBETRIEB.md) | PA4g Parallelbetrieb Zeitraum + Originalbeleg |
| [`DEPLOY-PIPELINE-PREP.md`](DEPLOY-PIPELINE-PREP.md) | PA3e Deploy rsync/SSH Prep ohne Cutover |
| [`BACKUP-RESTORE-TEST.md`](BACKUP-RESTORE-TEST.md) | PA3b Backup/Restore-Nachweis Dev |
| [`HETZNER-AVV-TOM-CHECKLISTE.md`](HETZNER-AVV-TOM-CHECKLISTE.md) | PA3d AVV/TOM Status + Org-Checkliste |
