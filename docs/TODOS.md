# DG CRM — ToDos & aktueller Stand

> **Regeln (nicht hier):** [`AGENTS.md`](../AGENTS.md) · SSH: [`CLOUD-AGENT-ACCESS.md`](CLOUD-AGENT-ACCESS.md)

Stand: **2026-11-08** — Roadmap: **[`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md)** (A→C FiBu/Hetzner · **D0 Lohn:** zuerst Lexware-Office-Niveau, dann SV-Automatik, dann Baulohn-Produkt · D1 Jahresblock). Lohn-Detailquelle: [`LOHNABRECHNUNG-ERWEITERUNG.md`](LOHNABRECHNUNG-ERWEITERUNG.md).

**Nächste Session:** IQ Nutzer-Aktivierung **oder** Unterschrift **ES2d** (Smoke Dev) [`CONTACT-SIGNATURE-REST-PLAN.md`](CONTACT-SIGNATURE-REST-PLAN.md). Phase B wartet auf GEX45. AVV ✅.

---

## Aktive Implementierungs-Liste (verbindlich abarbeiten)

| Phase | Datei | Inhalt |
|-------|--------|--------|
| **A → D** | [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) · A tokenarm: [`PHASE-A-REST-PLAN.md`](PHASE-A-REST-PLAN.md) | GoBD/Tests/IQ → Hetzner → ELSTER/LDAP → **Lohn D0 A→B→C** → E-Bilanz/KSt/GewSt & Rest |
| Vor-Check | [`VOR-HETZNER-CHECK.md`](VOR-HETZNER-CHECK.md) | Shared Core, Minify, GoBD-Stichprobe |
| ELSTER | [`ELSTER-ERIC-TODO.md`](ELSTER-ERIC-TODO.md) | Phases nach Umzug |
| Tests | [`TESTLISTE-2026-09-22.md`](TESTLISTE-2026-09-22.md) | Manuelle Smoke ganz-soft |
| ERP V2 | [`ERP-ERWEITERUNG-LASTENHEFT-V2.md`](ERP-ERWEITERUNG-LASTENHEFT-V2.md) · Rest tokenarm: [`ERP-REST-PLAN.md`](ERP-REST-PLAN.md) | Lohn/Baulohn, Zertifizierungen, Maschinen-Gemeinkosten, Akademie-Wissensbrücke |

**Nächster Block:** Unterschrift **ES2d** Smoke oder IQ-Nutzer · Phase B erst nach GEX45-Lieferung [`PHASE-B-REST-PLAN.md`](PHASE-B-REST-PLAN.md). UI/CSS U0–U5 ✅.

**Backlog (nicht jetzt):** Recruiting in HR — Plan [`RECRUITING-HR-PLAN.md`](RECRUITING-HR-PLAN.md) (eigene `/rekrut`-Seite; Xing/LinkedIn nur Anzeigen-Link).

**FiBu 2026-11-01:** Soll/Ist-Versteuerung + GoBD-Härte + Prüferexport + Docs — [`FIBU-VERKAUFSREIFE.md`](FIBU-VERKAUFSREIFE.md) · [`UST-IST-SOLL-VERSTEUERUNG.md`](UST-IST-SOLL-VERSTEUERUNG.md) · Nachweis-Stufen [`FIRMEN-NACHWEIS-MATRIX.md`](FIRMEN-NACHWEIS-MATRIX.md). Verkaufsblocker weiter: AVV/AGB, ELSTER live (nach Hetzner).

**Lohn (Produkt):** **D0 Stufe A** = normale L+G wie Lexware Office (SV vorerst Portal) → **B** ITSG-Automatik → **C** Baulohn-Modul (später, aber vorgesehen). IQ-SOKA-Jahresprüfung ist nur Betriebsakte, kein Produkt-Stopper. Detailkonzept: [`LOHNABRECHNUNG-ERWEITERUNG.md`](LOHNABRECHNUNG-ERWEITERUNG.md). **Umsetzung:** [`LOHNABRECHNUNG-IMPLEMENTIERUNG.md`](LOHNABRECHNUNG-IMPLEMENTIERUNG.md) — **L0–L10c ✅** · **G4 ✅** · **LB0a ✅** · **master+Deploy** · **G3 offen**. **UI 2026-10-30:** Scan+Jargon+Deploy ✅ · **offen nächste Session: CSS/Spacing-Probleme.**

---

## Git & Branch

| Branch | Status |
|--------|--------|
| `master` | **Produktionslinie** — Akademie, Lager, Kichel, Terminkalender, Rechtstexte, LDAP-Prep, Kontakt-Notiz |

**Merge 2026-09-16:**

1. `cursor/arbeitsvertrag-artifacts-ignore-2ec8`
2. `cursor/akademie-lager-konten-kichel-1dc6` (Lager / Akademie / Kichel / Termin / Recht)
3. `cursor/kontakt-bemerkung-ip-1dc6` (LDAP + Kontakt-Notiz + IQ-SSH)

Zwischenschritte nicht einzeln gemergt — Inhalt steckte in 2/3. Remote-`cursor/*`-Branches danach gelöscht.

Deploy: `bash bin/deploy-via-rsync.sh` (Cloud) oder `deploy.bat` (PC) → `bash bin/sync-crm-from-master.sh` (auf Server).

---

## Prioritäten (jetzt)

> Offene Arbeitspakete: Phase **A** Mini-IDs **[`PHASE-A-REST-PLAN.md`](PHASE-A-REST-PLAN.md)** · Fach-Checkboxen [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md).  
> Kurz: GoBD/Druck · FiBu-Tests · IQ-Instanz · Vor-Hetzner — danach Umzug, nicht vorher Live-ELSTER.

- [x] **UI/CSS-Rest:** [`UI-CSS-REST-PLAN.md`](UI-CSS-REST-PLAN.md) — U0–U5 ✅ (2026-11-08; Guards/Scan grün) · optional später: U2b Sichtcheck, margin-left/Print
- [~] **Phase A (tokenarm):** [`PHASE-A-REST-PLAN.md`](PHASE-A-REST-PLAN.md) — PA0–PA5 ✅ · **PA4a–i ✅** · **PA6a ✅** · **Hetzner-AVV ✅** · PA6b deferred · Fach: [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md)
- [x] **ERP-Maschinenblock M0–M5** — Kapazität, Zusatzmaterial, Wartung, Belegung/Kalender, Kalkulation/Snapshot (Branch gemergt 2026-10-29) → [`MASCHINENBLOCK-M0A-DATENMODELL.md`](MASCHINENBLOCK-M0A-DATENMODELL.md)
- [x] **ERP-Rest E0–E2 (tokenarm):** [`ERP-REST-PLAN.md`](ERP-REST-PLAN.md) ✅ 2026-11-08 · E3–E5 🔴 Gates (G3/Hetzner/Baulohn)
- [~] **Telefon/CTI (Fritzbox):** Code ✅ · Statusnotiz ✅ PA6a → [`CTI-FRITZBOX-STATUS.md`](CTI-FRITZBOX-STATUS.md) · **Aktivierung Org/LAN** (CTI in CRM noch aus)
- [x] Master auf Server deployen + Instanzen syncen (2026-09-03)
- [x] Lizenzserver repariert; kontur/Master `/login` OK
- [x] Feature-Branches gemergt: Bank-Geisterumsätze, Arbeitsvertrag-Doku
- [x] Cloud-Sammelbranches in `master` (2026-09-16)
- [x] **Multi-Firma / Umfirmierung** — Konzept dokumentiert (`MULTI-FIRMA-KONZEPT.md`)
- [x] **Master erneut deployen + sync** (nach Merge 16.09. / laufende Deploys Sep 2026)
- [x] Migrationen **062–081** / **064–078 fertig** (Master + ganz-soft.de + kontur, 2026-09-17) — runPending + Schema OK
- [x] **LDAP Phase 1 (Kasserver):** `ldap-readiness.php` gelaufen — php-ldap OK, Modus `local`, `ldap.local.php` fehlt (erwartet bis Hetzner)
- [x] Smoke: Akademie, Lager, Kichel, Terminkalender, Rechtstexte, Kontakt-Notiz, LDAP-UI
- [x] Fix: „Finanzamt ermitteln“ — lokale ESt-Nr. (z. B. 127/219/40770) nicht mehr als Steuer-ID verwerfen
- [x] Firmen-E-Mail in ganz-soft.de CRM eintragen (Einstellungen → Firma) — `info@ganz-om.de`
- [~] Manuelle Testliste **ganz-soft.de** — Block A erledigt; **neu:** [`TESTLISTE-2026-09-22.md`](TESTLISTE-2026-09-22.md) + Canvas `testliste-sep-2026` (Z2–Z6 + Beleg/Bank/Kichel)
- [x] **Kalender-Mitarbeiter:** `contact_id` Pflicht (Migration **095**) — Orphans deaktiviert / Auto-Link nach Name
- [x] **Kalender-Abwesenheiten:** Spiegel genehmigter Z4-Einträge (Buchungsblockade + Read-only-Liste)
- [x] **Kontakte-Massenimport** im laufenden CRM (Install-Engine, Excel/CSV, optional Kalender-Link)
- [x] Multi-Firma Phase 0 — KDV Org↔Firma Registry (`082`, Formular/Liste) — Switcher = Phase 1
- [x] **ELSTER Phase 2:** Hersteller-ID **34573** in CRM-Einstellungen (ganz-soft.de) hinterlegt; danach ERiC Linux laden, Test-Zertifikat (nach Server-Umzug)
- [x] **Lager Phase 3+4:** Einkaufsliste/Ignore (081), In-Auslieferung, Beleg-Live-Hinweis
- [x] **Belegkette Kundendarstellung** — Deploy 2026-09-17 (Druck, Summen, Belegdarstellung, AB→Druck+Unterschrift)
- [x] **AB aus Angebots-Mail-Antwort** — Migration 083 · Auto-AB + Annahme-Vermerk (Inbound In-Reply-To)
- [x] **Anzahlung materialbasiert** — EK aus `dg_article_purchase_sources` (Preferred)
- [x] **Kommunikation / E-Mail-Postfächer (eigen):** Menü + UI — Plan [`KOMMUNIKATION-POSTFAECHER-TODO.md`](KOMMUNIKATION-POSTFAECHER-TODO.md) (P1–P6 erledigt; Smoke/Deploy laut P6-Checkliste)
- [x] **Dokumentenverfasser:** Briefpapier + Editor/Export — Plan [`DOKUMENTENVERFASSER-TODO.md`](DOKUMENTENVERFASSER-TODO.md) (B1–B4, D1–D7 Code ✅; manuelle Smoke-UI + Sync nach Deploy)
- [x] **Vor Hetzner AVV:** [`HETZNER-AVV-TOM-CHECKLISTE.md`](HETZNER-AVV-TOM-CHECKLISTE.md) — AVV **07.11.2026** v1.2 · TÜV-Audit-PDF ✅ (PDFs nicht in Git)  
- [x] **Hetzner-Server:** **GEX45** bestellt · OS **Ubuntu 26** · Docs [`SERVER-MIGRATION.md`](SERVER-MIGRATION.md) · Mini-IDs [`PHASE-B-REST-PLAN.md`](PHASE-B-REST-PLAN.md) (Lieferung Wochen)  
- [ ] **Kontakt-Unterschrift Brief/Mail:** tokenarm [`CONTACT-SIGNATURE-REST-PLAN.md`](CONTACT-SIGNATURE-REST-PLAN.md) — ES1 + ES2a–c ✅ · nächster Schritt **ES2d** Smoke Dev  
- [~] **Vor Hetzner Rest:** `VOR-HETZNER-CHECK.md` (A1–A4 ✅; SSH/Stack nach Lieferung PB1+)
- [x] **ARAP-Prozent-Vorschlag:** Code bereits vorhanden; Jahresabschluss nutzt die resultierenden Ledger-/Folgejahresbuchungen, kein separater ARAP-Abschlussprozess
- [ ] **Mobile Apps (Flutter):** Spec [`MOBILE-APPS-PLAN.md`](MOBILE-APPS-PLAN.md) — Serie M0 API → M1 Kalender → M2 Mitarbeiter (token-sparend `m0a`…)
- [x] **Multi-Firma Betrieb MB1–MB4** — SSO-Secret, Switcher-Smoke, Contact-Export/Import, Provision-Gates (`MULTI-FIRMA-KONZEPT.md` §15); **MB1–MB4 ✅** (KAS-Voll-Lauf nur auf expliziten Befehl)
- [ ] **LDAP Phase 2 (nach Hetzner):** `ldap.local.php` + Hybrid-Login testen · Plugin-Code aus No-Repo-Chat
- [~] **Amazon Business Stufe 2** — Code A0–A5 ✅ (Selftest `bin/amazon-business-stufe2-selftest.php`) · Fach: [`AMAZON-BUSINESS-STUFE2.md`](AMAZON-BUSINESS-STUFE2.md) · Plan: [`AMAZON-BUSINESS-REST-PLAN.md`](AMAZON-BUSINESS-REST-PLAN.md) · **[x] erst nach Live-Bestellung** · Onboarding API = Mensch parallel
- [x] **Zeiterfassung Phase 2** — Z2a–Z2e ✅
- [x] **Zeiterfassung Phase 3 Schichten** — Z3a–Z3d ✅
- [x] **Zeiterfassung Phase 4 Urlaub/Krankheit** — Z4a–Z4e ✅
- [x] **Zeiterfassung Phase 5 Rückstellungen** — Z5a–Z5d ✅
- [x] **Zeiterfassung Phase 6 Lohn-Export** — Z6a–Z6f ✅ (Auszahlung + Zeitkontoregelung/Vorschlag)
- [x] **Arbeitsstunden-Import** — Excel/CSV Shiftbase/Crewmeister → Stempelzeiten (`zeiterfassung-stundenimport`)
- [x] **Kiosk/Tablet PIN (Z7)** — `/stempeluhr` ohne CRM-Login · Entwurf-Seite · PIN-Reset über HR
- [x] **Stempeluhr-Seite Nachzug Alt-Instanzen** — Migration 097 + `ensureDraftWebsitePage` (Master/ganz-soft/kontur: Entwurf „Stempeluhr“ angelegt)
- [x] **Import-Testfixtures** — `docs/testdata/import-fixtures/` (12 MA, Duplikate, Stunden ab 01.06.2026)

---

## Inhalt auf `master` (Deploy-Quelle)

| Block | Migration / Ort |
|-------|-----------------|
| Belegkette | 055 |
| Dokument-Workflow / Statusfilter | 056 |
| Freitext vor/nach Positionen | 057 |
| Gesetzliche Klauseln | 058 |
| Skonto-Stufen, Mahnwesen + Autostart | 059 |
| Teilzahlungen | 060 |
| Zeiterfassung Ph.1 (Stempeluhr) | 061 |
| Überstunden / ArbZG-Erinnerung | 062–063 |
| **Bank Geisterumsätze** | **064** · `BankGhostDetectionService` |
| Lagerwirtschaft | **065–069** · `views/modules/lager.php` |
| Akademie | **070–072** |
| Terminkalender Website | **073–075** |
| **Rechtstexte Mehrprodukt** | **076** · `LegalProductSettings` |
| Kichel-Protokoll | **077** |
| **LDAP-Vorbereitung** | **078** · `LdapAuthenticator` / Einstellungen |
| Kontakt-Notiz (`contact_note`) | Kontakte |
| **Multi-Firma Phase 0** | **082** · `dg_kdv_orgs` / Org-Felder an KDV-Kunden |
| Wartungsmodus einheitlicher Code | `WebsiteMaintenanceRenderer` |
| Website-Menü Icons (Lucide) | `src/Website/` |

Doku: `BUCHHALTUNG-BELEGKETTE.md`, `BELEGKETTE-DARSTELLUNG.md`, `ARBEITSVERTRAG-VORLAGEN-HINWEIS.md`, `LDAP-INTEGRATION.md`, `LAGER-WIRTSCHAFT.md`, `ZEITERFASSUNG-PLAN.md`

---

## Live-Instanzen (Kurz)

| Instanz | Wartung | Bemerkung |
|---------|---------|-----------|
| dg.ganz-om.de | AN | Master, `/login` OK |
| ganz-soft.de | AN | Haupt-Testinstanz |
| kontur-cosmetics.de | AN | `/login` OK |
| ganz-om.de | Platzhalter | Sync vom Master, keine DB |
| shop.ganz-soft.de | — | Stripe nicht live |

---

## Test-Checkliste (Randfälle — `master` auf ganz-soft.de)

**Erledigt 2026-09-17 (Nutzer):**

- [x] **Lager / Einkauf** komplett
- [x] **Akademie** öffnet; Kurs/Video erreichbar
- [x] **Terminkalender / Online-Buchung** (Vorschau)
- [x] **Rechtstexte** (Impressum o. Ä.) ohne Fehler
- [x] Öffentlich `/` = Wartungsseite
- [x] Kontakt-Link aus CRM (Firma/Wartung)

**Noch offen:**

- [ ] Belegkette, Workflow, Klauseln, Skonto, Teilzahlungen, Zeiterfassung
- [x] **Bankabgleich:** Geisterumsätze erkennen, manuell ausblenden (Migration 064) — ✅ PA2c 2026-11-08
- [x] **Kichel** — ✅ PA2h 2026-11-08 Dev (Widget/Nav/Antworten/Protokoll/Skonto)

Basis: [`TESTLISTE-2026-08-21.md`](TESTLISTE-2026-08-21.md) Abschnitt K

---

## Geplant (Konzept, noch nicht gebaut)

| Thema | Doku |
|-------|------|
| Multi-Firma Code + Betrieb MB1–MB4 | erledigt 2026-09-21 |
| **Zeiterfassung Z2a–Z2e** | erledigt 2026-09-21 |
| **Zeiterfassung Z3a–Z3d** (Schichten) | **Code ✅** |
| **Zeiterfassung Z4a–Z4e** (Urlaub/Krankheit) | **Code ✅** |
| **Zeiterfassung Z5a–Z5d** (Rückstellungen) | **Code ✅** |
| **Zeiterfassung Z6a–Z6d** (Lohn-Export) | Spec + Code ✅ (CSV/DATEV/Lexoffice + payroll_slip) |

---

## Bewusst zurückgestellt

| Thema | Doku |
|-------|------|
| Stripe Live | `SHOP-TODO.md` |
| ELSTER/ERiC live | `ELSTER-ERIC-TODO.md` |
| LDAP / dg-user live | `LDAP-INTEGRATION.md` |
| PHP 8.5 KAS-Umstellung | `PHP85-TEST-HANDOFF.md` |
| Nextcloud cloud.ganz-om.de | `CLOUD-NEXTCLOUD-RESTORE.md` |
| Zeiterfassung Ph.3–6 (nach Z2) | `ZEITERFASSUNG-PLAN.md` *(Code Z2–Z7 erledigt — Zeile historisch)* |
| Baulohn-Modul (Produkt) | `IQ-STROM-HETZNER-TODO.md` § D0 Stufe C — **nach** Normal-Lohn (A), ideal nach B |
| ERP-Erweiterung V2 | `ERP-REST-PLAN.md` (E0–E2 ✅; E3–E5 Gates) · Fach: `ERP-ERWEITERUNG-LASTENHEFT-V2.md` |
| Lohn SV-Direktversand (ITSG) | `IQ-STROM-HETZNER-TODO.md` § D0 Stufe B — nach Stufe A |

---

## Chats aufräumen

Nur **1 Cloud-Chat** + **1 Lokal-Chat** behalten. Agent kann nicht archivieren — du: Rechtsklick → Archive.

---

## Detail-Handoffs (Referenz)

| Datei | Inhalt |
|-------|--------|
| `IQ-STROM-HETZNER-TODO.md` | **Haupt-ToDo** A/B/C/D — IQ Strom FiBu + Hetzner + GmbH-Jahresblock |
| `PHASE-A-REST-PLAN.md` | Phase A tokenarm Mini-IDs PA0–PA6 (1 Chat = 1 ID) |
| `PHASE-B-REST-PLAN.md` | Phase B tokenarm · GEX45 / Ubuntu 26 |
| `SERVER-MIGRATION.md` | Hetzner-Zielserver GEX45 (bestellt) |
| `CONTACT-SIGNATURE-REST-PLAN.md` | Kontakt-Unterschriftsbild Brief/Mail (ES0–ES5, kein QES) |
| `ZUGFERD-AUSGANG-ENTSCHEIDUNG.md` | PA5b: PDF+XRechnung behalten; Factur-X = D2-Ticket |
| `LEXOFFICE-DG-MIGRATION.md` | PA4f: Lexoffice→DG Checkliste (kein Produktiv-Import) |
| `LEXOFFICE-DG-PARALLELBETRIEB.md` | PA4g: Parallelbetrieb Zeitraum + Originalbeleg-Regel |
| `DEPLOY-PIPELINE-PREP.md` | PA3e: Deploy rsync/SSH Prep ohne Cutover |
| `BACKUP-RESTORE-TEST.md` | PA3b: Backup/Restore-Nachweis (Dev) |
| `HETZNER-AVV-TOM-CHECKLISTE.md` | PA3d: Hetzner AVV/TOM Checkliste (Signatur Org) |
| `PC-HANDOFF.md` | PC-Deploy, Import |
| `BUCHHALTUNG-PC-HANDOFF.md` | Buchhaltung Deploy |
| `CLOUD-AGENT-ACCESS.md` | Secrets, rsync-Deploy |
| `ARBEITSVERTRAG-VORLAGEN-HINWEIS.md` | Vertragsvorlagen (Rechtliches) |
| `MULTI-FIRMA-KONZEPT.md` | Multi-Firma, Umfirmierung, Pakete/Rabatt |
| `HANDOFF-WEBSITE-MENU-ICONS.md` | Menü-Icons Phase 2 |
| `LDAP-INTEGRATION.md` | LDAP-Vorbereitung |
| `LAGER-WIRTSCHAFT.md` | Lager |
| `KOMMUNIKATION-POSTFAECHER-TODO.md` | Kommunikation: Postfächer P1–P6 (eigene UI) |
| `DOKUMENTENVERFASSER-TODO.md` | Dokumentenverfasser: Briefpapier + Editor (B/D) |
| `USTG-14-DRUCKSTICHPROBE.md` | Manuelle §14-UStG Druck-/PDF-Stichprobe für Ausgangsbelege |
| `LOHNABRECHNUNG-ERWEITERUNG.md` | Acrobat-Plan zur CRM-Erweiterung Lohnabrechnung: Normal-Lohn, ITSG, Baulohn |
| `LOHNABRECHNUNG-IMPLEMENTIERUNG.md` | Token-sparende Serien L0–L10 / LB / LC (Spec→Code, ein Chat = ein Paket) |
