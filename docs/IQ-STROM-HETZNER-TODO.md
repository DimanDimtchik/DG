# IQ Strom GmbH + Hetzner — Implementierungs-ToDo

> **Stand:** 2026-11-08 (Phase A tokenarm: [`PHASE-A-REST-PLAN.md`](PHASE-A-REST-PLAN.md); Lohn: zuerst Lexware-Office-Niveau, Baulohn später)  
> **Ziel:** DG CRM als CRM+FiBu (+ Lohn) — Einsatz u. a. **IQ Strom GmbH** (Cloud, ≥3 Nutzer remote; perspektivisch ohne Steuerberater).  
> **Reihenfolge:** A jetzt → B Hetzner → C nach Umzug → D Lohn / Jahresblock / Rest.  
> Detailquellen: [`TODOS.md`](TODOS.md) · [`VOR-HETZNER-CHECK.md`](VOR-HETZNER-CHECK.md) · [`ELSTER-ERIC-TODO.md`](ELSTER-ERIC-TODO.md) · [`TESTLISTE-2026-09-22.md`](TESTLISTE-2026-09-22.md) · [`buchhaltung-todo.html`](buchhaltung-todo.html) · [`MULTI-FIRMA-KONZEPT.md`](MULTI-FIRMA-KONZEPT.md) · [`LOHNABRECHNUNG-ERWEITERUNG.md`](LOHNABRECHNUNG-ERWEITERUNG.md) · [`ERP-ERWEITERUNG-LASTENHEFT-V2.md`](ERP-ERWEITERUNG-LASTENHEFT-V2.md)  
> **Phase A tokenarm (1 Chat = 1 Mini-ID):** [`PHASE-A-REST-PLAN.md`](PHASE-A-REST-PLAN.md)

**Hinweis Behörden:** Keine Finanzamt-„Softwarefreigabe“. Extern: ELSTER-ID ✅ `34573`, Softwarezertifikat, ERiC, Hetzner-AVV. **Lohn:** ITSG erst für direkten SV-Versand aus DG (Stufe B). **Baulohn** ist Produktfeature **nach** Normal-Lohn (Stufe C), nicht an die IQ-Strom-SOKA-Jahresprüfung gekoppelt.

---

## A · Jetzt (vor Hetzner) — erledigen in diesem Zustand

> **Mini-Schritte (1 Chat = 1 ID):** [`PHASE-A-REST-PLAN.md`](PHASE-A-REST-PLAN.md) — PA1…PA6.

### A1 · GoBD / Belege / FiBu-Härte (Blocker für Produktiv-FiBu)

- [x] **GoBD Änderungshistorie / Unveränderbarkeit** schließen  
  Ausgang: Revision/Storno; Eingang nach Buchung gesperrt; manuelle Buchung = Gegenbuchung (kein DELETE).  
  → [`VOR-HETZNER-CHECK.md`](VOR-HETZNER-CHECK.md) §3 · [`FIBU-VERKAUFSREIFE.md`](FIBU-VERKAUFSREIFE.md)
- [x] **Stichprobe §14 UStG** je Dokumentart (Angebot, AB, Rechnung, Schluss, Gutschrift) — Druck/PDF  
  → ✅ PA1a 2026-11-08 Dev `ganz-om.de` · [`USTG-14-DRUCKSTICHPROBE.md`](USTG-14-DRUCKSTICHPROBE.md) · Gutschrift-Titel-Fix
- [ ] **Stichprobe gemischte Steuersätze** 19 % / 7 % / 0 % auf einem Beleg
- [ ] **§19 / §13b / PV §12 Abs. 3** Klauseln am Druck prüfen (Firma-Flags in Einstellungen)
- [x] **PV-Automatik:** bei Firma-Flag Photovoltaik und 0 %-Zeilen Hinweis §12 Abs. 3 im Druck/PDF/E-Mail wie bei §19 (Firmenstatus, nicht Beleg-Checkbox) — ✅ `VoucherDocumentPrintService` + `bin/pv-legal-clause-selftest.php`
- [x] **ARAP:** automatischer Prozent-Vorschlag aus Liefer-/Belegdatum (Restlaufzeit) ist im Belegformular-Code vorhanden.
  Wichtig Jahresabschluss: kein zweiter ARAP-Prozess bauen — `VoucherAccrual` splittet die Belegbuchung und `LedgerPostingService` erzeugt die Auflösung zum 01.01. des Folgejahres; der Jahresabschluss sperrt/übernimmt danach nur die bestehenden Buchungen.
  → `assets/js/buchhaltung-belege.js` · `src/Accounting/VoucherAccrual.php` · `src/Accounting/LedgerPostingService.php`
- [x] **Kontohinweise** redaktionell geprüft (SKR-Hilfetexte) — Bewirtung: 70-%-Grenze als Betriebsausgabenabzug, Vorsteuer nicht pauschal kuerzen

### A2 · Manuelle Tests (ganz-soft.de / Master)

- [ ] Belegkette Angebot → AB → LS → Rechnung → Schlussrechnung  
  → [`BUCHHALTUNG-BELEGKETTE.md`](BUCHHALTUNG-BELEGKETTE.md) · [`TESTLISTE-2026-09-22.md`](TESTLISTE-2026-09-22.md) §6
- [ ] Dokumentstatus / Filter / Freitext / Klauseln / Skonto / Mahnung / Teilzahlungen / OPOS
- [ ] **Bank Geisterumsätze** erkennen + manuell ausblenden (Migration 064)
- [ ] UStVA: Nullmeldung, Berichtigung, §13b-Kennziffern, ELSTER-CSV-Export
- [ ] Jahresabschluss-Checkliste + Jahr sperren / Saldenvortrag (operativ)
- [ ] DATEV EXTF + Beleg-ZIP Stichprobe (Berater/Mandant)
- [ ] Zeiterfassung Z2–Z6 Smoke (Stempel, Schichten, Urlaub, Rückstellungen, Lohn-Export)  
  → [`TESTLISTE-2026-09-22.md`](TESTLISTE-2026-09-22.md) §1–5
- [ ] Kichel Smoke
- [ ] Kommunikation Postfächer Smoke (falls noch nicht)  
  → [`KOMMUNIKATION-POSTFAECHER-TODO.md`](KOMMUNIKATION-POSTFAECHER-TODO.md)
- [ ] Dokumentenverfasser Smoke  
  → [`DOKUMENTENVERFASSER-TODO.md`](DOKUMENTENVERFASSER-TODO.md)

### A3 · Vor-Hetzner Infrastruktur (ohne ERiC)

> **Stand PA3d (2026-11-08):** A3 Doc-Mini-IDs PA3a–f ✅ · AVV-**Unterzeichnung** weiter Mensch (Checkliste da).  
> Tokenarm: [`PHASE-A-REST-PLAN.md`](PHASE-A-REST-PLAN.md) · AVV: [`HETZNER-AVV-TOM-CHECKLISTE.md`](HETZNER-AVV-TOM-CHECKLISTE.md)

- [x] **Shared-Core-Ordnerplan** dokumentiert (Ist Full-Tree-Sync · Ziel Shared+Instanz) — ✅ PA3a 2026-11-08  
  → [`VOR-HETZNER-CHECK.md`](VOR-HETZNER-CHECK.md) §1.1  
- [x] **Backup/Restore-Test** + Notiz — ✅ PA3b 2026-11-08 (Dev `ganz-om.de`, Staging-Restore, kein Live-DB-Overwrite)  
  → [`BACKUP-RESTORE-TEST.md`](BACKUP-RESTORE-TEST.md) · Konzept: [`CRON-AUTOSTART.md`](CRON-AUTOSTART.md)
- [x] **Asset-Minify** im Deploy — App: `dg.app.min.css` via `bin/minify-dg-css.sh` (rsync/sftp/`deploy.bat`); `dg.min.css` = Offline-Stub  
  → Selftest `bin/asset-css-minify-selftest.php` ✅ 2026-11-08 (PA3c) · [`VOR-HETZNER-CHECK.md`](VOR-HETZNER-CHECK.md) §2
- [~] **Hetzner-AVV / TOMs** — Checkliste/Status ✅ PA3d · **Unterzeichnung/Konto noch offen** (vor Cutover)  
  → [`HETZNER-AVV-TOM-CHECKLISTE.md`](HETZNER-AVV-TOM-CHECKLISTE.md)
- [x] Deploy-Pipeline auf neuen Host vorbereiten (rsync/SSH, ohne Cutover) — ✅ PA3e 2026-11-08  
  → [`DEPLOY-PIPELINE-PREP.md`](DEPLOY-PIPELINE-PREP.md)

### A4 · IQ Strom GmbH — Instanz vorbereiten (noch Kasserver oder parallele Domain ok)

- [ ] **CRM-Instanz IQ Strom** auf **`office.iqstrom.com`** (All-Inkl `w01f1176`): Code syncen + `config/*.local.php` an DB „DB Office IQ CRM“ — kein Sondercode
- [ ] Firmendaten GmbH: Steuernummer, USt-IdNr., Finanzamt, Gewinnermittlung **Bilanz**, SKR wählen
- [ ] Steuerliche Sonderfälle aktivieren: **Photovoltaik §12 Abs. 3**, **§13b Bauleistung**
- [ ] ≥ **3 Benutzer** + Rechte (Buchhaltung / Angebote / Admin)
- [ ] Nummernkreise, Bankverbindung, Briefpapier/Logo
- [x] Migrationskonzept Lexoffice → DG: Kontakte, Artikel, offene Belege, Bank (CSV/Excel) — Checkliste ✅ PA4f 2026-11-08  
  → [`LEXOFFICE-DG-MIGRATION.md`](LEXOFFICE-DG-MIGRATION.md) · Parallelbetrieb = PA4g
- [x] **Parallelbetrieb** festlegen: Zeitraum Lex + DG (1–2 Monate), Originalbeleg-Regel — ✅ PA4g 2026-11-08  
  → [`LEXOFFICE-DG-PARALLELBETRIEB.md`](LEXOFFICE-DG-PARALLELBETRIEB.md) · Instanz trägt Stichtage ein
- [~] Domain/TLS: Subdomain + DB angelegt, SSL beantragt → [`SERVER-DOMAIN-DATENBANK.md`](SERVER-DOMAIN-DATENBANK.md) · SSH [`CLOUD-AGENT-ACCESS.md`](CLOUD-AGENT-ACCESS.md) (`IQ_ALLINKL_*`)

### A5 · Kleine FiBu-Fixes (jetzt sinnvoll)

- [x] **Soll-/Ist-Versteuerung** inkl. FA-Bescheid, UStVA nach Zahlung, Verrechnungskonto — [`UST-IST-SOLL-VERSTEUERUNG.md`](UST-IST-SOLL-VERSTEUERUNG.md)
- [x] **GoBD-Prüferexport** + Verfahrensdoku-Vorlage — [`FIBU-VERKAUFSREIFE.md`](FIBU-VERKAUFSREIFE.md)
- [x] GmbH-Hinweise: Jahresabschluss-UI nicht als „EÜR ohne Kanzlei“ bei GmbH/Bilanz — ✅ 2026-11-08 PA5a (`views/modules/buchhaltung-jahresabschluss.php`)
- [x] ZUGFeRD-Ausgang: Entscheidung dokumentiert — PDF + XRechnung-XML bewusst behalten; Factur-X-Einbettung = D2  
  → [`ZUGFERD-AUSGANG-ENTSCHEIDUNG.md`](ZUGFERD-AUSGANG-ENTSCHEIDUNG.md) (PA5b ✅ 2026-11-08)
- [x] E-Rechnung §13b: XRechnung Reverse-Charge → Kategorie **AE**, BT-120/121 (`VATEX-EU-AE`) — ✅ PA5c 2026-11-08  
  → `VoucherEInvoiceXmlService` · Selftest `bin/einvoice-reverse-charge-selftest.php`

### A6 · Laufende Nebenpunkte (jetzt, niedrige Priorität neben A1–A4)

- [~] **Telefon/CTI Fritzbox:** manuell aktivieren (Code da) — Linphone, CallMonitor-Bridge
- [ ] Multi-Firma Switcher Feinschliff nur falls für IQ/Holding nötig (MB1–MB4 Code ✅)
- [x] **ERP-Maschinenblock** (M0–M5) + Gemeinkosten (nicht buchungswirksam) + Maschinenwissen/Akademie — Code ✅ 2026-11-08  
  → [`ERP-ERWEITERUNG-LASTENHEFT-V2.md`](ERP-ERWEITERUNG-LASTENHEFT-V2.md) · [`ERP-REST-PLAN.md`](ERP-REST-PLAN.md) E0–E2 ✅ · M0a: [`MASCHINENBLOCK-M0A-DATENMODELL.md`](MASCHINENBLOCK-M0A-DATENMODELL.md)

---

## B · Umzug auf Hetzner (Cutover)

Voraussetzung: A1–A3 weitgehend grün.

- [ ] Server **Hetzner SX65-2** (oder gleichwertig) bereit  
  → [`SERVER-MIGRATION.md`](SERVER-MIGRATION.md) · [`ELSTER-ERIC-TODO.md`](ELSTER-ERIC-TODO.md) Phase 1
- [ ] OS Debian 12 / Ubuntu 22.04 · `Europe/Berlin` · Firewall · User `deploy`
- [ ] nginx + PHP 8.2 FPM + MariaDB 10.11 + Let’s Encrypt
- [ ] CRM deployen · DB-Dump Kasserver → Import · `storage/` migrieren
- [ ] Cronjobs (`cron.php`, Mitarbeiter-Purge)
- [ ] SMTP testen · DNS Cutover · **48 h Monitoring**
- [ ] Kasserver-Instanzen read-only / abschalten
- [ ] `config/elster.local.php` auf Server (nicht in Git)
- [ ] Alle Instanzen syncen inkl. **IQ Strom**-Instanz

---

## C · Nach Hetzner — als Nächstes

### C1 · ELSTER / ERiC live (UStVA)

- [ ] ERiC Linux laden, Lizenz, `/opt/eric/`, Worker  
  → [`ELSTER-ERIC-TODO.md`](ELSTER-ERIC-TODO.md) Phase 2–3
- [ ] Test-Softwarezertifikat (Mein ELSTER) · Testmerker `700000004`
- [ ] `ElsterXmlBuilder` · Dry-Run · Testfinanzamt · Antwort-PDF in UI
- [ ] Produktions-Softwarezertifikat · Modus `eric` · „An Finanzamt senden“
- [ ] Abgabe-Historie `dg_elster_submissions` + AuditLog
- [ ] CSV-Fallback behalten

### C2 · Auth / Betrieb

- [ ] **LDAP Phase 2:** `ldap.local.php` + Hybrid-Login  
  → [`LDAP-INTEGRATION.md`](LDAP-INTEGRATION.md)
- [ ] Backup-Konzept Hetzner (DB + Belege + Zertifikate offline)
- [ ] Retention / Löschkonzepte Übersicht vervollständigen

### C3 · IQ Strom Produktiv-Schnitt

- [ ] Parallelbetrieb beenden: Cutover-Datum, Lex nur noch Archiv/Leserecht
- [ ] Erste echte UStVA aus DG (erst CSV oder schon ERiC — je nach C1)
- [ ] Schulung 3 Nutzer (Akademie-Szenarien Belege / Bank / UStVA)

---

## D · Rest (nach C / bewusst gestaffelt)

### D0 · Lohnabrechnung (Produkt — von Anfang an vorgesehen)

**Reihenfolge fest:** zuerst **normale Lohn- und Gehaltsabrechnung** wie derzeit in **Lexware Office**, danach Automatik SV, **danach** Baulohn als eigenes Modul.  
IQ-Strom-SOKA-Jahresprüfung (Ordner `Buchhaltung/BaulohnUntersuchung/`) ist **Betrieb/Compliance** und steuert **nicht**, ob DG CRM Baulohn kann — das Produkt soll Baulohn später können.

**Heute im CRM:** nur Zeiterfassung + Lohn-**Export** (DATEV/Lex) + Upload fremder `payroll_slip` — **keine** Abrechnung.

Detailkonzept: [`LOHNABRECHNUNG-ERWEITERUNG.md`](LOHNABRECHNUNG-ERWEITERUNG.md).  
**Implementierungsplan (token-sparend, Serien L0–L10 / LB / LC):** [`LOHNABRECHNUNG-IMPLEMENTIERUNG.md`](LOHNABRECHNUNG-IMPLEMENTIERUNG.md).  
Architekturleitlinie: Strategy-Pattern fuer Berechnungs-Engines, gemeinsamer CRM-Code fuer alle Instanzen, Mandanten-/Branchenlogik ueber Stammdaten und Lohnarten.

#### Stufe A — Normaler Lohn/Gehalt (Lexware-Office-Niveau) — **zuerst bauen**

- [ ] Arbeitgeber-Stammdaten (Betriebsnummer, BG, Umlagen, Steuernummer, Bundesland)
- [ ] Beschäftigte: GF (auch sv-frei beherrschend), Angestellte Festgehalt, Mini-/Midijob
- [ ] Brutto→Netto: LSt, SolZ, KiSt, SV-Beiträge (Jahres-/Beitragspflege)
- [ ] Lohnabrechnung PDF + Lohnkonto
- [ ] Buchung freigegebener Monate ins Hauptbuch (Aufwand / Verbindlichkeiten)
- [ ] SEPA-XML fuer Nettoauszahlungen
- [ ] **Lohnsteuer-Anmeldung** per ELSTER (nach C1 / ERiC)
- [ ] SV-Beiträge und Meldewerte **vorbereiten**; Versand zunächst über **SV-Meldeportal** (Übergang ok)
- [ ] Anbindung an bestehende Zeiterfassung / Lohn-Export (Z6) wo sinnvoll

#### Stufe B — Automatik SV (nach Stufe A)

- [ ] ITSG-**Systemuntersuchung** (§ 95b SGB IV) — auch Eigenbau prüfungspflichtig
- [ ] Pilot ≥ 2 Anwender · Prod-MOD-ID · **jährliche** QS
- [ ] ITSG-Trust-Center-Schluessel / Zertifikatsbetrieb und Meldeprotokolle
- [ ] Beitragsnachweise + DEÜV-Meldungen **direkt aus DG** (ohne Portal-Manuell)
- [ ] ELStAM-Abruf / Sofortmeldung soweit für Standardlohn nötig

#### Stufe C — Baulohn / SOKA (Produktfeature — **nach** A, idealerweise nach B)

Vorgesehen für DG CRM (Mandanten mit Bau/SOKA). **Nicht** in Phase A–C FiBu; Spezifikation erst wenn Stufe A steht.

- [ ] Spec: SOKA-Beiträge, Meldungen, Winterbau/Umlagen, Tarif Bau / Sofortmeldung Bau wo nötig
- [ ] Abgrenzung: Baulohn-Lohnarten vs. Normal-Lohn; Mandanten-Flag „Baulohn aktiv“
- [ ] Implementierung Baulohn-Modul
- [ ] ITSG-/Meldewege für Baulohn-Anteile klären (ggf. Erweiterung Stufe B)

### D1 · GmbH ohne Steuerberater — Jahresblock (größtes Fach-Gap)

Ohne diese Punkte ersetzt DG Lex **nicht** vollständig für eine bilanzierungspflichtige GmbH:

- [ ] **E-Bilanz** (Taxonomie, Übermittlung)
- [ ] **Körperschaftsteuererklärung** per ELSTER
- [ ] **Gewerbesteuererklärung** per ELSTER
- [ ] **USt-Jahreserklärung** per ERiC (Phase 6)
- [ ] Anhang / Offenlegung (verkürzte Bilanz) Workflow dokumentieren
- [ ] Anlagenbuch / AfA an Abschluss anbinden (heute nur Anschaffungsrechner leicht)

### D2 · E-Rechnung / Automatisierung

- [ ] **ZUGFeRD/Factur-X eingebettet** im Ausgangs-PDF (statt nur PDF + XML)  
  → Entscheidung/Ticket: [`ZUGFERD-AUSGANG-ENTSCHEIDUNG.md`](ZUGFERD-AUSGANG-ENTSCHEIDUNG.md)
- [ ] §13b + E-Rechnung Ende Übergangsfrist (2027/2028) absichern
- [ ] Beleg-OCR / Auto-Auslesen  
  → [`buchhaltung-todo.html`](buchhaltung-todo.html) §6
- [ ] Bank **PSD2/API**-Sync (heute CAMT/MT940-Datei)
- [ ] Auto-AB aus Angebots-Mail Feinschliff (Code teilweise da — Regression)

### D3 · Produkt / Mobile / Shop / Cloud

- [ ] **Mobile Apps** Flutter M0 → M1 Kalender → M2 Mitarbeiter  
  → [`MOBILE-APPS-PLAN.md`](MOBILE-APPS-PLAN.md)
- [ ] Amazon Business Stufe 2  
  → [`AMAZON-BUSINESS-STUFE2.md`](AMAZON-BUSINESS-STUFE2.md)
- [x] ERP-Erweiterung: Maschinen-Gemeinkosten + Maschinenwissen/Akademie (Code ✅ 2026-11-08)  
  → [`ERP-REST-PLAN.md`](ERP-REST-PLAN.md) E0–E2 · Fach: [`ERP-ERWEITERUNG-LASTENHEFT-V2.md`](ERP-ERWEITERUNG-LASTENHEFT-V2.md)
- [ ] Stripe Shop live  
  → [`SHOP-TODO.md`](SHOP-TODO.md)
- [ ] Nextcloud `cloud.ganz-om.de` / IQ-Cloud  
  → [`CLOUD-NEXTCLOUD-RESTORE.md`](CLOUD-NEXTCLOUD-RESTORE.md)
- [ ] PHP 8.5 nur falls noch relevant nach Hetzner  
  → [`PHP85-TEST-HANDOFF.md`](PHP85-TEST-HANDOFF.md)
- [ ] Multi-Firma Phase 1+ Vollausbau Holding (wenn IQ + weitere Gesellschaften)

### D4 · Architektur-Fragen (vor ELSTER Prod klären)

- [ ] Ein ERiC-Worker für alle KDV-Mandanten vs. Worker pro Kunde?
- [ ] Softwarezertifikat pro Mandant vs. zentral?
- [ ] `.pfx`-Backup offline?
- [ ] Lohn Stufe B: ERiC-Worker vs. ITSG-Annahmewege — Architektur mit D0 abstimmen

---

## Reihenfolge (Kurz)

| Phase | Fokus | Grobe Dauer |
|-------|--------|-------------|
| **A** | GoBD, Tests, IQ-Instanz, Lex-Parallel | 2–6 Wochen |
| **B** | Hetzner Cutover | 1–2 Wochen + 48 h |
| **C** | ERiC UStVA, LDAP, IQ Cutover | 2–4 Wochen nach B |
| **D0 A** | Normal-Lohn wie Lexware Office (PDF, FiBu, LSt; SV via Portal) | Monate + Jahrespflege Tabellen |
| **D0 B** | SV-Automatik + ITSG | nach A |
| **D0 C** | Baulohn-Modul (Produkt) | nach A (besser nach B) |
| **D1** | E-Bilanz / KSt / GewSt | 2–4+ Monate Entwickler |
| **D2–D4** | Komfort / Mobile / Shop | laufend |

**Pragmatisch bis D1 fertig:** Tagesgeschäft in DG; Jahresabschluss GmbH weiter extern (StB) **oder** Zwischenlösung nur für Jahressteuern — nicht dauerhaft doppelte FiBu.  
**Lohn bis D0 A live:** weiter Lexware Office Lohn / externes Lohnbüro; DG nur Zeiten + Export.

---

## Erledigt (Kontext, nicht erneut bauen)

- ELSTER Hersteller-ID **34573** · Entwicklerzugang · Newsletter
- Doppelte Buchführung, SKR, Belegkette, §13b, PV-Klauseln, UStVA-CSV, DATEV, Bank CAMT/MT940, OPOS, DIY-Jahresabschluss operativ
- Zeiterfassung Z2–Z7 Code · Multi-Firma MB1–MB4 · LDAP Phase 1 Prep · Lager / Akademie Kern
Bei Abhaken: hier und ggf. kurze Notiz in [`TODOS.md`](TODOS.md) Prioritäten aktualisieren.
