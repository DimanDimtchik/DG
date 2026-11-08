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
| A1 Druck-Stichproben §14 / gemischt / §19·§13b·PV | ❌ manuell / teilweise Code |
| A1 PV-Automatik Klausel bei 0 %-Zeilen | ✅ Print + Firma-Flag (`e22d9d7` / PA1d) |
| A2 Manuelle Smoke-Tests | ❌ Mensch auf Master/Dev |
| A3 Shared-Core / Minify / AVV / Deploy-Prep | ❌ Ops |
| A4 Kunden-Instanz vorbereiten | [~] Domain/TLS · Rest offen |
| A5 Soll/Ist + Prüferexport | ✅ · ZUGFeRD-Entscheidung ✅ · GmbH-UI / §13b-XML offen |
| A6 Telefon CTI | [~] manuell |
| A6 Multi-Firma Feinschliff | nur falls nötig |
| A6 ERP Maschinenblock + Gemeinkosten + Wissen | ✅ M0–M5 + ERP-Rest E1/E2 |

---

## Parent-Übersicht

| ID | Thema | Status | Token |
|----|--------|--------|-------|
| PA0 | Inventar / dieser Plan | [x] 2026-11-08 | 🟢 |
| PA1 | GoBD / Druck / PV-Automatik (A1 Rest) | [~] PA1d ✅ | 🟢🟡🔴 |
| PA2 | Manuelle Testliste (A2) | [ ] | 🔴 |
| PA3 | Vor-Hetzner Infra (A3) | [~] PA3c ✅ | 🔴🟢 |
| PA4 | Instanz-Vorbereitung (A4) | [~] | 🔴 |
| PA5 | Kleine FiBu-Fixes (A5 Rest) | [~] PA5b ✅ | 🟢🟡 |
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
| PA1a | Checkliste §14 UStG Druck/PDF je Dokumentart abarbeiten · Ergebnis in [`USTG-14-DRUCKSTICHPROBE.md`](USTG-14-DRUCKSTICHPROBE.md) | 🔴 | [ ] |
| PA1b | Stichprobe gemischte Steuersätze 19/7/0 auf einem Beleg (manuell + kurze Notiz im Stichproben-Doc) | 🔴 | [ ] |
| PA1c | §19 / §13b / PV §12 Abs. 3 Klauseln am Druck gegen Firma-Flags prüfen (Checkliste) | 🔴 | [ ] |
| PA1d | **Code:** PV-Automatik — bei Firma-Flag Photovoltaik + 0 %-Zeilen Klausel §12 Abs. 3 automatisch (wie §19); nur betroffene Beleg-/Klausel-Dateien | 🟡 | [x] 2026-11-08 |
| PA1e | A1-Haken in `IQ-STROM-HETZNER-TODO.md` nachziehen | 🟢 | [ ] |

**Prompt PA1a:**  
`Nur Mini-ID PA1a laut docs/PHASE-A-REST-PLAN.md. Nur Druck-Stichprobe §14 laut USTG-14-DRUCKSTICHPROBE.md. Kein Refactor.`

**Prompt PA1d:**  
`Nur Mini-ID PA1d laut docs/PHASE-A-REST-PLAN.md. PV-Klausel-Automatik bei 0%-Zeilen. Nur genannte Beleg-/Klausel-Dateien. Kein Refactor. php -l.`

---

### PA2 — Manuelle Tests (A2)

Je Mini-ID = ein Smoke-Block auf Dev/Master; Ergebnis kurz in diesem Plan oder Testliste abhaken.

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| PA2a | Belegkette Angebot → AB → LS → Rechnung → Schluss | 🔴 | [ ] |
| PA2b | Dokumentstatus / Filter / Freitext / Klauseln / Skonto / Mahnung / Teilzahlungen / OPOS | 🔴 | [ ] |
| PA2c | Bank Geisterumsätze erkennen + ausblenden | 🔴 | [ ] |
| PA2d | UStVA: Nullmeldung, Berichtigung, §13b-KZ, ELSTER-CSV | 🔴 | [ ] |
| PA2e | Jahresabschluss-Checkliste + Jahr sperren / Saldenvortrag | 🔴 | [ ] |
| PA2f | DATEV EXTF + Beleg-ZIP Stichprobe | 🔴 | [ ] |
| PA2g | Zeiterfassung Z2–Z6 Smoke | 🔴 | [ ] |
| PA2h | Kichel Smoke | 🔴 | [ ] |
| PA2i | Postfächer Smoke (falls offen) | 🔴 | [ ] |
| PA2j | Dokumentenverfasser Smoke | 🔴 | [ ] |
| PA2k | A2-Haken in IQ-STROM-TODO nachziehen | 🟢 | [ ] |

**Prompt PA2a:**  
`Nur Mini-ID PA2a laut docs/PHASE-A-REST-PLAN.md. Manuelle Belegkette laut TESTLISTE/BUCHHALTUNG-BELEGKETTE. Ergebnis in Plan abhaken. Kein Code.`

---

### PA3 — Vor-Hetzner Infrastruktur (A3)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| PA3a | Shared-Core-Ordnerplan dokumentieren/prüfen ([`VOR-HETZNER-CHECK.md`](VOR-HETZNER-CHECK.md) §1) | 🟢 | [ ] |
| PA3b | Backup/Restore-Test durchführen + Notiz ([`VOR-HETZNER-CHECK.md`](VOR-HETZNER-CHECK.md) §4) | 🔴 | [ ] |
| PA3c | Asset-Minify im Deploy verifizieren (`dg.app.min.css` / minify-Skript) | 🟢 | [x] 2026-11-08 Selftest grün; Deploy: rsync/sftp/`deploy.bat` → `minify-dg-css.sh` |
| PA3d | Hetzner-AVV / TOMs organisatorisch (Vertrag) — nur Checkliste/Status | 🔴 | [ ] |
| PA3e | Deploy-Pipeline Prep (rsync/SSH) dokumentieren — **ohne** Cutover | 🟢 | [ ] |
| PA3f | A3-Haken in IQ-STROM-TODO | 🟢 | [ ] |

**Prompt PA3a:**  
`Nur Mini-ID PA3a laut docs/PHASE-A-REST-PLAN.md. Shared-Core laut VOR-HETZNER-CHECK §1. Nur Docs. Kein Cutover.`

**Prompt PA3c:**  
`Nur Mini-ID PA3c laut docs/PHASE-A-REST-PLAN.md. Asset-Minify prüfen (ggf. bin/asset-css-minify-selftest.php). Kein Cutover.`

---

### PA4 — Instanz vorbereiten (A4)

Ops/Mensch. Keine Firmendaten hardcoden im CRM-Code.

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| PA4a | Code-Sync + `config/*.local.php` an Instanz-DB (kein Sondercode) | 🔴 | [ ] |
| PA4b | Firmendaten GmbH in Einstellungen (Steuernr., USt-IdNr., FA, Bilanz, SKR) | 🔴 | [ ] |
| PA4c | Flags: Photovoltaik §12 Abs. 3 + §13b Bauleistung | 🔴 | [ ] |
| PA4d | ≥3 Benutzer + Rechte | 🔴 | [ ] |
| PA4e | Nummernkreise, Bank, Briefpapier/Logo | 🔴 | [ ] |
| PA4f | Migrationskonzept Lexoffice → DG (Kontakte/Artikel/Belege/Bank) als Doc-Checkliste | 🟢 | [ ] |
| PA4g | Parallelbetrieb festlegen (Zeitraum, Originalbeleg-Regel) — Doc | 🟢 | [ ] |
| PA4h | Domain/TLS abschließen ([~] SSL) | 🔴 | [ ] |
| PA4i | A4-Haken in IQ-STROM-TODO | 🟢 | [ ] |

**Prompt PA4f:**  
`Nur Mini-ID PA4f laut docs/PHASE-A-REST-PLAN.md. Lexoffice→DG Migrationscheckliste als Doc. Kein Produktiv-Import. Kein Tenant-Hardcode.`

---

### PA5 — Kleine FiBu-Fixes (A5 Rest)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| PA5a | GmbH-Hinweise: Jahresabschluss-UI nicht als „EÜR ohne Kanzlei“ wenn Bilanz/GmbH — nur betroffene View(s) | 🟡 | [ ] |
| PA5b | ZUGFeRD-Ausgang: Entscheidung dokumentieren (PDF+XRechnung ok bis Frist; Ticket Factur-X) | 🟢 | [x] 2026-11-08 → [`ZUGFERD-AUSGANG-ENTSCHEIDUNG.md`](ZUGFERD-AUSGANG-ENTSCHEIDUNG.md) |
| PA5c | E-Rechnung §13b: XRechnung Reverse-Charge BT-Felder prüfen; nur Fix wenn klar defekt | 🟡 | [ ] |
| PA5d | A5-Haken in IQ-STROM-TODO | 🟢 | [ ] |

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

1. Parallel Mensch: **PA1a–c**, **PA2a…** Smoke  
2. **PA5a** (GmbH-UI) · **PA5c** (§13b-XML)  
3. **PA3a/e** Docs (Minify PA3c ✅)  
4. **PA4f/g** Migrations-/Parallel-Docs  
5. Rest **PA4** / **PA3b/d** / **PA2** Mensch · **PA1e** wenn A1 Druck-Stichproben grün  
6. Phase B erst wenn A1–A3 weitgehend grün  

**Prompt Start (nächster Chat):**  
`Nur Mini-ID PA5a laut docs/PHASE-A-REST-PLAN.md. GmbH/Bilanz-Hinweis Jahresabschluss-UI. Nur genannte View(s). Katalog. Kein Refactor.`  
*(Oder PA3a Shared-Core-Doc / PA5c §13b-XML.)*

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
