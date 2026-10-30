# Lohnabrechnung — Implementierungsplan (token-sparend)

> **Stand:** 2026-10-30  
> **Fachquellen:** [`LOHNABRECHNUNG-ERWEITERUNG.md`](LOHNABRECHNUNG-ERWEITERUNG.md) (Detailkonzept) · [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) § D0 · [`ERP-ERWEITERUNG-LASTENHEFT-V2.md`](ERP-ERWEITERUNG-LASTENHEFT-V2.md) §2  
> **Vorbild Betriebsstil:** [`ZEITERFASSUNG-PLAN.md`](ZEITERFASSUNG-PLAN.md) Serien Z2–Z6  
> **Status:** Plan / Spec-Rahmen — **kein** Produktivcode in diesem Dokument

---

## 0. Rolle & Prüfbarkeit (verbindlich)

Arbeiten mit kombinierter Sicht:

| Rolle | Pflicht im Plan |
|-------|-----------------|
| **RA Steuerrecht** | Formulierungen, Belegpflicht, LSt/EStG/USt nur wo einschlägig, keine „Soft-Overwrite“-Korrekturen |
| **Steuerberater** | Lexware-Office-Niveau zuerst; DATEV/Export-Anschluss; Mandant/Berater; Skonto/USt **nicht** mit Lohn vermischen |
| **Betriebsprüfer** | Entwurf → Freigabe → Sperre → Storno/Korrektur; AuditLog; versionierte Beitragstabellen; Meldungs-Payload speichern |

**Rechtliche Leitplanken (Kurz):**

- Lohnsteuer: Berechnung und (später) Anmeldung/Bescheinigung — ELSTER/ERiC erst nach Hetzner/C1.
- SV: bis Stufe B nur **Vorbereitung + Portal-Export**; direkter Versand erst nach ITSG (§ 95b SGB IV).
- GoBD/Personal: keine stille Überschreibung freigegebener Abrechnungen; Korrektur = neue Korrekturabrechnung / Storno mit Begründung.
- Gleicher Code alle Instanzen; Betriebsnummer, Zertifikate, Tabellenwerte = **Instanz-DB**.

---

## 1. Token-Spar-Regeln (Cursor)

> Pro Chat **genau ein** Unterpunkt (`l0a`, `l1b`, …). Spec nur **dieser** Abschnitt + maximal die genannten 1–2 Dateipfade.  
> **Nicht** das ganze Dokument, nicht Detailkonzept und ERP-Lastenheft gleichzeitig laden.  
> **Nicht** Stufe B/C-Pakete in einem A-Chat anfassen.  
> Prompt-Vorlage unten kopieren — Rest weglassen.

### Prompt-Vorlage (kopieren)

```text
Lohn Implementierung: <PAKET-ID> laut docs/LOHNABRECHNUNG-IMPLEMENTIERUNG.md
Nur Abschnitt <PAKET-ID>. Nicht andere L-/LB-/LC-Pakete.
Abgrenzung „Nicht:“ aus dem Abschnitt einhalten.
Nach Abschluss: Checkbox/Status im Plan-Abschnitt setzen, kurzer Abnahme-Check.
```

### Was Tokens frisst — und vermeiden

| Vermeiden | Stattdessen |
|-----------|-------------|
| „Bau Lohn komplett“ | Ein Paket-ID |
| Ganzes Detailkonzept + Lastenheft + Plan | Nur Plan-Abschnitt + bei Bedarf **eine** Quelldatei |
| Code + Spec + Deploy + Tests in einem Chat | Spec-Chat → Code-Chat → Smoke-Chat |
| Baulohn/ITSG „gleich mitdenken“ im A-Code | Nur Flag/`Nicht:` notieren |
| Große Refactors neben Lohn | Bestehende `EmployeeData`, Z6-Export, Ledger-Muster wiederverwenden |

### Reihenfolge der Serien (A zuerst)

```
L0 Spec-Rahmen → L1 AG-Stamm → L2 MA-Steuer/SV → L3 Lohnarten
→ L4 Brutto-Netto-Kern → L5 Monatslauf/Freigabe → L6 PDF/Lohnkonto
→ L7 FiBu-Buchung → L8 SEPA → L9 SV-Portal-Export
→ L10 LSt-Anmeldung ELSTER (Gate: Hetzner/ERiC)
─── erst wenn A produktiv ───
LB ITSG/SV-Direkt (Stufe B)
LC Baulohn/SOKA (Stufe C)
```

**Betriebsregel bis L6 live:** weiter Lexware Office / externes Lohnbüro; DG nur Zeiten + Z6-Export + fremde `payroll_slip`.

---

## 2. Ist-Stand (nicht neu bauen)

| Baustein | Ort | Bewertung |
|----------|-----|-----------|
| Stempel / Monat / Urlaub / Krankheit / Rückstellung | Zeiterfassung Z2–Z5 | Vorbereitung ✅ |
| Lohn-Zeitenexport CSV/DATEV/Lex + Protokoll | Z6, `zeiterfassung-lohnexport` | ✅ behalten |
| Überstunden-Auszahlung vor Export | Z6e/f | ✅ Input für L5 |
| Fremde Lohn-PDF | `payroll_slip` | ✅ bis eigene PDF (L6) parallel ok |
| Freitext `salary`, SV-Status, Meldestelle-Hinweise | `EmployeeData` | **unzureichend** für Abrechnung → L2 erweitert |
| Eigene Brutto→Netto / Monatslauf | — | **fehlt** |
| ITSG / Baulohn | — | Stufe B/C — **nicht** in L0–L10 |

---

## 3. Gates (nicht überspringen)

| Gate | Bedingung | Blockiert |
|------|-----------|-----------|
| **G0** | Nutzer bestätigt: Start Serie L0 (dieser Plan akzeptiert) | L1+ |
| **G1** | Phase A FiBu-Smoke / GoBD-Stichprobe weitgehend grün (`IQ-STROM` A) | Produktivnutzung L7 Buchung |
| **G2** | Hetzner + ERiC Testpfad (C1) | **L10** LSt-Anmeldung live |
| **G3** | Mind. 2 vollständige Testmonate L5–L9 auf einer Instanz | Pilot „ohne Lexware“ |
| **G4** | ITSG-Vorbereitung (Pflichtenheft) | **LB**-Code produktiv |
| **G5** | Stufe A produktiv + Spec LC | **LC** Baulohn-Code |

---

## 4. Entscheide (Defaults — token-sparend fest)

| # | Frage | Default für L0–L10 |
|---|--------|---------------------|
| E1 | Erstes Produktziel | Lexware-Office-**Niveau** Normal-Lohn (Festgehalt, Stunden, Mini/Midi, GF) |
| E2 | SV-Versand | Portal-Export (L9); kein ITSG in A |
| E3 | Beitragstabellen | Importierte Jahreswerte + Versionierung; Pflege-UI schlank |
| E4 | SEPA | In L8; Default erst nach G3 produktiv schalten (Flag) |
| E5 | ELStAM | Abruf erst LB; in A manuelle Steuerklasse/Freibeträge |
| E6 | Baulohn | Nur Mandanten-Flag-Platzhalter in L1 — **kein** Rechenkern |
| E7 | Strategy-Pattern Engines | Interface ab L4; nur `StandardPayrollEngine` in A |
| E8 | Rechte | Lohn-Modulrecht + `canViewTeam`/Buchhaltung analog Z6; Freigabe enger als Entwurf |

Offene Fachfragen aus Detailkonzept (nicht im Code raten) → Paket **L0b**.

---

## Serie L0 — Spec-Rahmen & Abgrenzung

> Einstieg: `l0a`  
> **Kein** Code außer Plan-Pflege.

### L0a — Spec-Rahmen dieses Plans ✅ 2026-10-30

- Serien L0–L10 / LB / LC und Token-Regeln verbindlich.
- Verweis Detailkonzept; Inhalte hier **nicht** verdoppeln.
- Abnahme: Gates + Entscheide dokumentiert; ein Chat = ein Paket.

**Nicht:** Migration, UI, Beitragssätze einbauen.

### L0b — Offene Fachentscheide (Checkliste)

Kurzer Chat: nur die offenen Punkte aus Detailkonzept § „Offene Entscheidungen“ + Defaults E1–E8 bestätigen oder ändern.

| Thema | Vorschlag |
|-------|-----------|
| Tabellenpflege | Jahresimport + Version; manuelle Korrektur mit Audit |
| Beherrschender GF | Flag + Begründungsfeld; sv-frei nur mit dokumentierter Prüfung |
| SEPA produktiv | Flag `payroll_sepa_live` default aus bis G3 |
| ELStAM vs. manuell | A = manuell; Konfliktfeld erst LB |
| Berater-Export | Lohnkonto-PDF + CSV-Kontrolle; DATEV-Lohn weiter Z6 parallel |

**Nicht:** Code, Schema.

### L0c — Modulrecht & Menü-Skizze (Spec)

- Modul-Slug z. B. `lohn` (Level view/edit/full).
- Menü: Stammdaten AG · MA-Lohnfelder · Lohnarten · Monatslauf · Auswertungen.
- Abgrenzung zu `zeiterfassung-lohnexport` (bleibt Zeiten-Export).

**Nicht:** Migration Rechte-Seed außer Spec-Tabelle.

---

## Serie L1 — Arbeitgeber-Stammdaten

> Einstieg: `l1a`

### L1a — Spec AG-Stamm

Felder (Minimum Lexware-Niveau): Betriebsnummer, Steuernummer, Finanzamt-Ref, Bundesland, BG/UV, Umlagen U1/U2/U3-Teilnahme, Bank/IBAN Absender, Name Arbeitgeber, ggf. `baulohn_enabled` Flag (ohne Logik).

**Nicht:** MA-Felder, Berechnung, Code.

### L1b — Migration + Repository AG-Stamm

Eine Tabelle/Setting-Block je Instanz; Audit bei Änderung.

**Nicht:** UI außer minimal Settings-Tab wenn nötig in L1c.

### L1c — Settings-UI AG-Stamm

Validierung Betriebsnummer-Format (Plausibilität, keine Behörden-API).

**Nicht:** SV-Meldung senden.

---

## Serie L2 — Mitarbeiter Steuer / SV / Beschäftigung

> Einstieg: `l2a`  
> Baut auf `EmployeeData` auf — **erweitern**, nicht paralleles HR-System.

### L2a — Spec MA-Lohnfelder

Pflicht für Abrechnung: Steuer-ID, Steuerklasse, Kinderfreibeträge, Konfession/KiSt-Merkmal, SV-Nummer, Krankenkasse, Beitragsgruppenschlüssel, Personengruppe, Beschäftigungsart (Voll/Teil/Mini/Midi/GF), Eintritt/Austritt, IBAN Auszahlung, Jahresentgelt-Schätzer (Midi), Flag beherrschender GF + Begründung.

**Nicht:** Brutto-Netto-Formel.

### L2b — Migration + Validierung

Masken/Constraints; fehlende Pflichtfelder blockieren Monatslauf später (L5), nicht Speichern grundsätzlich (Entwurf erlaubt).

**Nicht:** Berechnung.

### L2c — UI Kontakt/Mitarbeiterkarte

Klar getrennte Sektion „Lohnabrechnung“; Hinweise Meldestelle behalten.

**Nicht:** PDF erzeugen.

---

## Serie L3 — Lohnarten

### L3a — Spec Lohnarten-Katalog

Arten: Festgehalt, Stundenlohn, Sachbezug, GwV, steuerfreie Erstattung, Zuschuss, Abzug; Steuer-/SV-Flags je Art; SKR-Gegenkonten-Hinweis (Buchung L7).

**Nicht:** Branchen-Sonderarten Bau/Gastro.

### L3b — Migration + CRUD

Mandanten-Lohnarten; Seed Standardkatalog.

**Nicht:** Engine.

### L3c — Zuordnung MA ↔ Lohnart (Stamm)

z. B. monatliches Festgehalt / Stundenlohn-Satz.

**Nicht:** Monatslauf.

---

## Serie L4 — Brutto→Netto-Kern (ohne UI-Lauf)

### L4a — Spec Rechenkern + Tabellen

- Eingaben/Ausgaben je MA-Monat (Brutto, LSt, SolZ, KiSt, KV/PV/RV/AV AN/AG, Umlagen, Netto).
- Jahreswerte versioniert (`gültig_ab`).
- Mini/Midi/Pauschalen; GF sv-frei-Pfad.
- Testvektoren (mind. 5 Fälle) in Spec-Tabelle — **ohne** Behörden-Geheimnisse.

**Nicht:** ITSG-XML, Baulohn, ELSTER.

### L4b — Tabellen-Import + Versionierung (Code)

Import-Pfad für Jahresdatei; Audit.

**Nicht:** Monatslauf-UI.

### L4c — `StandardPayrollEngine` + Unit-Tests

Strategy-Interface; nur Standard-Engine. Tests gegen L4a-Vektoren.

**Nicht:** PDF, FiBu, SEPA.

---

## Serie L5 — Monatslauf, Entwurf, Freigabe, Sperre

### L5a — Spec Lauf & GoBD-Workflow

Zustände: `draft` → `calculated` → `approved` → `locked`.  
Korrektur nur über `storno`/`correction` mit Begründung und Benutzer.  
Übernahme Zeiten: Z6-Datenquellen / Z6e Auszahlung soft-degraden.

**Nicht:** Buchung ins Hauptbuch (L7).

### L5b — Migration Läufe + Positionen + Audit

Tabellen Abrechnungsmonat, Positionen, Statushistorie.

**Nicht:** PDF.

### L5c — UI Monatslauf (Liste, berechnen, Freigabe)

Rechte: berechnen/edit = edit; freigeben/sperren = full oder gesondert.

**Nicht:** SEPA, ELSTER.

---

## Serie L6 — PDF-Abrechnung & Lohnkonto

### L6a — Spec PDF + Lohnkonto

Mindestangaben Abrechnung (Arbeitgeber, MA, Zeitraum, Brutto-Positionen, Abzüge, Netto, AG-Anteile Ausweis wo üblich).  
Lohnkonto: chronologische Übersicht je MA/Jahr.  
Eigene PDF **ersetzt nicht** sofort fremde `payroll_slip`-Ablage — parallel.

**Nicht:** Behördenmeldung.

### L6b — PDF-Generator + Speicherung

Revisionssicher an Mandant/MA/Periode; nach `locked` unveränderlich.

**Nicht:** FiBu.

### L6c — Lohnkonto-UI + Export

**Nicht:** SEPA.

---

## Serie L7 — FiBu-Buchung

> Gate **G1** vor Produktivbuchung.

### L7a — Spec Buchungssätze

Lohnaufwand, AG-Anteile, Verbindlichkeiten FA/SV/Netto; SKR03/04 laut Mandant; Idempotenz bei erneutem Versuch; nur aus `approved`/`locked`.

**Nicht:** steuersubsumtion USt auf Lohn.

### L7b — Buchungs-Service + Protokoll

Anbindung bestehendes Ledger; Rollback-Verbot nach Lock — Storno-Buchung.

**Nicht:** SEPA-Datei.

### L7c — UI „In FiBu übernehmen“ + Stichprobe

Abnahme mit Betriebsprüfer-Checkliste (Belegkette Monat → Buchung → PDF).

---

## Serie L8 — SEPA-Auszahlung

### L8a — Spec SEPA-XML

Sammler Netto; IBAN-Validierung; Protokoll; Flag `payroll_sepa_live`.

**Nicht:** Echtgeld ohne Flag.

### L8b — Generator + Download + Audit

**Nicht:** Bank-API.

---

## Serie L9 — SV-Vorbereitung / Meldeportal-Export

### L9a — Spec Kontrolllisten & Beitragsnachweis-Vorbereitung

Werte für manuelles SV-Meldeportal; **kein** direkter Versand.

**Nicht:** ITSG-Zertifikat, DEÜV-Senden.

### L9b — Export CSV/PDF Kontrolle

**Nicht:** LB-Pakete.

---

## Serie L10 — Lohnsteuer-Anmeldung (ELSTER)

> Gate **G2** zwingend.

### L10a — Spec LSt-Anmeldung / Bescheinigung

Anbindung ERiC-Pfad; Testzertifikat zuerst; Payload+Antwort speichern.

**Nicht:** UStVA vermischen; nicht auf Kasserver live.

### L10b — Implementierung Testmodus

### L10c — Produktiv-Gate Checkliste

Nur nach explizitem Nutzerbefehl und Hetzner-Freigabe.

---

## Serie LB — ITSG / SV-Direkt (Stufe B)

> Erst nach G3 + G4. Eigene Chats `lb0a` …

| Paket | Inhalt |
|-------|--------|
| LB0 | Spec Pflichtenheft / Systemdok (token-sparend, nur Outline) |
| LB1 | Testaufgaben / XML-Strukturen (kein Prod-Versand) |
| LB2 | Pilot ≥ 2 Anwender |
| LB3 | Prod-MOD-ID / Schlüsselbetrieb |
| LB4 | Beitragsnachweis + DEÜV direkt |
| LB5 | ELStAM-Abruf soweit nötig |

**Nicht:** Baulohn in LB-Chats.

---

## Serie LC — Baulohn / SOKA (Stufe C)

> Erst nach G5. Chats `lc0a` …

| Paket | Inhalt |
|-------|--------|
| LC0 | Spec SOKA / Winterbau / Urlaub Bau |
| LC1 | Lohnarten Bau + Flag aktiv |
| LC2 | Rechenkern-Strategie `ConstructionPayrollEngine` |
| LC3 | Meldwege klären (ITSG/SOKA) |
| LC4 | Implementierung schrittweise |

**Nicht:** vor Normal-Lohn beginnen.

---

## 5. Abnahme je Paket (Minimal)

Jeder Code-Chat endet mit:

1. Was geliefert (1–3 Bullet)  
2. Was bewusst **nicht**  
3. Manueller oder Unit-Nachweis  
4. Plan-Abschnitt Status ✅ + Datum  

Kein Deploy außer Nutzer sagt „deploy“.

---

## 6. Empfohlene Chat-Kette (erste 8 Chats)

| # | Prompt-Kurz | Warum token-sparend |
|---|-------------|---------------------|
| 1 | `l0b` Offene Entscheide | Nur Tabelle, kein Code |
| 2 | `l0c` Modulrecht Spec | Nur Spec |
| 3 | `l1a` AG-Stamm Spec | Nur Spec |
| 4 | `l1b` AG Migration | Eine Migration |
| 5 | `l1c` AG UI | Eine Settings-Seite |
| 6 | `l2a` MA-Felder Spec | Nur Spec |
| 7 | `l2b` MA Migration | Schema + Validierung |
| 8 | `l2c` MA UI | Eine Sektion |

Danach L3a → … nicht überspringen. **L4 erst wenn L1–L3 stabil.**

---

## 7. Verknüpfung Roadmap

| Roadmap | Dieser Plan |
|---------|-------------|
| D0 Stufe A | Serien L0–L10 |
| D0 Stufe B | Serie LB |
| D0 Stufe C | Serie LC |
| Z6 Lohn-Export | bleibt; speist L5 |
| ERiC/Hetzner | Gate G2 für L10 |

Bis L6 produktiv: Lexware/externes Lohnbüro parallel — kein Big-Bang-Cutover.
