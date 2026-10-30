# Lohnabrechnung — Implementierungsplan (token-sparend)

> **Stand:** 2026-10-30 (L0 ✅ · L1a ✅ · L1b ✅)  
> **Fachquellen:** [`LOHNABRECHNUNG-ERWEITERUNG.md`](LOHNABRECHNUNG-ERWEITERUNG.md) (Detailkonzept) · [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) § D0 · [`ERP-ERWEITERUNG-LASTENHEFT-V2.md`](ERP-ERWEITERUNG-LASTENHEFT-V2.md) §2  
> **Vorbild Betriebsstil:** [`ZEITERFASSUNG-PLAN.md`](ZEITERFASSUNG-PLAN.md) Serien Z2–Z6  
> **Status:** L1b Store ✅ · nächster Chat: **`l1c`** (Settings-UI AG-Stamm)

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
| **G0** | Nutzer bestätigt: Start Serie L0 (dieser Plan akzeptiert) | L1+ · **✅ 2026-10-30** |
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
| E8 | Rechte | Modul `lohn` (`none`/`partial`/`full`) analog `DepartmentAccess`; Freigabe/Sperre/FiBu/SEPA nur `full` · ✅ L0b |

Offene Fachfragen aus Detailkonzept → **L0b ✅** (unten verbindlich).

---

## Serie L0 — Spec-Rahmen & Abgrenzung ✅

> Einstieg war: `l0a` · Serie **abgeschlossen 2026-10-30**  
> **Kein** Code außer Plan-Pflege.

### L0a — Spec-Rahmen dieses Plans ✅ 2026-10-30

- Serien L0–L10 / LB / LC und Token-Regeln verbindlich.
- Verweis Detailkonzept; Inhalte hier **nicht** verdoppeln.
- Abnahme: Gates + Entscheide dokumentiert; ein Chat = ein Paket.

**Nicht:** Migration, UI, Beitragssätze einbauen.

### L0b — Offene Fachentscheide ✅ 2026-10-30

Gate **G0** mit Start „L0“ angenommen. Defaults E1–E8 und Detailkonzept-Fragen sind für Stufe A **verbindlich** (Änderung nur durch neuen Spec-Chat, nicht im Code raten).

| # | Thema | Entscheidung | Fachliche Begründung (RA/StB/Prüfer) |
|---|--------|--------------|--------------------------------------|
| D1 | Beitragstabellen LSt/SV | **Jahresimport** + `gültig_ab`-Versionierung; manuelle Korrektur nur mit AuditLog (wer/wann/alt/neu) | Jahreswechsel prüfbar; stille Satzänderung = GoBD-Risiko |
| D2 | Beherrschender GF | Pflicht-**Flag** + **Begründungsfeld** (Freitext, mind. Datum der Prüfung); `sv_frei` nur wenn Flag gesetzt | Statusklärung dokumentieren (Sozialversicherungspflicht GF); keine Automatik „GmbH-GF = sv-frei“ |
| D3 | SEPA in Stufe A | Generator in **L8**; Flag `payroll_sepa_live` **default aus** bis Gate **G3** | Fehlbuchungen/Doppelzahlungen vor Pilot vermeiden |
| D4 | ELStAM vs. manuell | Stufe A: Steuerklasse/Freibeträge/KiSt **manuell** pflegen; ELStAM-Abruf erst **LB**; bei späterem Abruf: Konflikt = Abruf gewinnt erst nach Bestätigung | Keine Scheingenauigkeit ohne BZSt-Anbindung |
| D5 | Berater-Exporte | Ab L6: **Lohnkonto-PDF** + **Kontroll-CSV**; **Z6 DATEV/Lex Zeitenexport bleibt** parallel; keine DATEV-Lohnabrechnungsersatz-API in A | Übergangsphase Lexware/Kanzlei; Zeiten ≠ Nettoabrechnung |
| D6 | Produktumfang A | Wie E1: Festgehalt, Stundenlohn, Mini/Midi, GF — **kein** Baulohn-Rechenkern (nur Flag-Platzhalter L1) | Lexware-Office-Niveau zuerst |
| D7 | SV-Versand A | Nur **Portal-Vorbereitung/Export** (L9); kein ITSG-Direktversand | § 95b SGB IV / Systemuntersuchung = Stufe B |
| D8 | Korrektur freigegebener Monate | Nur **Storno/Korrekturabrechnung** mit Begründung — kein Überschreiben | GoBD / Lohnkonto-Nachvollziehbarkeit |

#### Abnahme L0b

| Prüfung | Ergebnis |
|---------|----------|
| D1–D8 dokumentiert | ✅ |
| E1–E8 mit L0b konsistent | ✅ |
| Kein Code / kein Schema | ✅ |

**Nicht:** Migration, UI, Beitragssätze, Rechte-Seed in DB.

### L0c — Modulrecht & Menü-Skizze ✅ 2026-10-30

Anschluss an bestehendes Muster `DepartmentAccess` (`none` / `partial` / `full`) — **nicht** view/edit/full.

#### Modul

| Feld | Wert |
|------|------|
| `module_key` | `lohn` |
| Label | `Lohn & Gehalt` |
| Default neuer Abteilungen | `none` (wie `buchhaltung`) |
| Seed-Empfehlung (später L1+) | HR- und Buchhaltungs-Abteilungen: mind. `partial`; Freigabe-Rollen: `full` |

#### Bedeutungen der Level

| Level | Darf | Darf nicht |
|-------|------|------------|
| `none` | — | Alle Lohn-Seiten |
| `partial` | Monatslauf **ansehen**, Entwurf berechnen/Positionen editieren (wenn `RoleResolver::canEdit`), Lohnarten/MA-Lohnfelder pflegen, Kontroll-CSV/PDF Entwurf | Freigabe, Sperre, FiBu-Übernahme, SEPA-Download live, AG-kritische Stammdaten ändern |
| `full` | alles partial + Freigabe/Sperre/Storno, FiBu-Übernahme, SEPA (wenn Flag), AG-Stamm, Tabellenimport | ITSG-Prod (LB), Baulohn-Kern (LC) |

Admin/`RoleResolver` mit System-Vollzugriff: wie bei anderen Modulen üblich mitbehandlen (kein Sonderweg pro Domain).

#### Seiten-Slugs (Menü später; Code erst ab L1+)

| Slug | Label | Mindest-Level | Bemerkung |
|------|-------|---------------|-----------|
| `lohn` | Übersicht / Einstieg | `partial` | Kachel + Kurzstatus offener Monate |
| `lohn-monatslauf` | Monatslauf | `partial` (Freigabe-Aktionen: `full`) | Kern-UI L5 |
| `lohn-lohnarten` | Lohnarten | `partial` | L3 |
| `lohn-lohnkonto` | Lohnkonto | `partial` | L6 |
| `lohn-auswertungen` | Kontrolllisten / SV-Vorbereitung | `partial` | L9; Versand bleibt Portal |
| Settings-Tab `lohn` bzw. Seite AG | Arbeitgeber-Stamm | **`full`** | L1 — Betriebsnummer etc. |

**MA-Lohnfelder (L2):** bleiben in **Kontakt → Mitarbeiterkarte** (kein eigener Menüpunkt). Speichern: wer Kontakte/`EmployeeData` editieren darf **und** `lohn` ≥ `partial` (sonst nur Anzeige ohne Lohn-Sektion-Edit). Exact Gate in L2a festziehen.

#### Menü-Platzierung

- Eigene Sidebar-Sektion **„Lohn & Gehalt“** zwischen **HR** und **Buchhaltung** (nicht unter Zeiterfassung verstecken).
- Icon-Vorschlag: an Buchhaltung/HR angelehnt (konkret in UI-Chat).

#### Abgrenzung Zeiterfassung Z6

| Thema | Modul / Seite | Bleibt |
|-------|---------------|--------|
| Zeiten CSV / DATEV Lohn-Zeiten / Lexoffice Zeiten | `zeiterfassung` · `zeiterfassung-lohnexport` | ✅ unverändert; Recht weiter `TimePayrollExportService::canExport` (= `canViewTeam`) |
| Fremde `payroll_slip` | Kontaktakte | ✅ |
| Eigene Netto-Abrechnung / FiBu-Lohnbuchung | Modul `lohn` | neu ab L4+ |

Kein Zusammenlegen der Module: Zeitenexport ≠ Lohnabrechnung (prüftechnisch und für Berater klarer).

#### Rechte-Matrix Aktionen (verbindlich für spätere `can*`-Hilfen)

| Aktion | Mindestbedingung |
|--------|------------------|
| Übersicht / Entwurf sehen | `lohn` ≥ `partial` |
| Entwurf berechnen / Positionen ändern | `lohn` ≥ `partial` + `canEdit` |
| Freigabe / Sperre / Storno | `lohn` === `full` + `canEdit` |
| FiBu-Übernahme (L7) | `lohn` === `full` + Buchhaltungszugriff (`canAccessBuchhaltung` oder gleichwertig) |
| SEPA-Datei (L8) | `lohn` === `full` + Flag `payroll_sepa_live` |
| AG-Stamm ändern (L1) | `lohn` === `full` |
| Beitragstabellen-Import (L4) | `lohn` === `full` |
| Z6 Zeitenexport | unverändert **ohne** `lohn`-Pflicht |

#### Abnahme L0c

| Prüfung | Ergebnis |
|---------|----------|
| `module_key=lohn` + Level wie CRM | ✅ |
| Menü getrennt von Z6 | ✅ |
| Freigabe enger als Entwurf | ✅ |
| Kein DB-Seed / kein PHP in diesem Paket | ✅ |

**Nicht:** Migration `dg_department_module_access`, MenuRegistry-Code, UI.

---

## Serie L1 — Arbeitgeber-Stammdaten

> Einstieg: `l1a` · L1a ✅ · L1b ✅ · weiter mit `l1c`

### L1a — Spec AG-Stamm ✅ 2026-10-30

Nur Spezifikation — Umsetzung Store = **L1b**, UI = **L1c**. Kein PHP in L1a.

#### Ziel

Arbeitgeber-Stammdaten auf Lexware-Office-Mindestniveau für spätere L4–L9, **ohne** paralleles Firmenstammsystem. Wiederverwendung von `CompanySettings` / `CompanyExtendedSettings` wo Daten schon existieren; nur lohn-spezifische Lücken neu.

#### Speicher-Entscheidung (verbindlich für L1b)

| Ansatz | Entscheidung |
|--------|--------------|
| Neue Tabelle `dg_payroll_*` nur für AG-Stamm | **Nein** in L1 |
| Neuer Settings-Block | **`company_extended.lohn`** (Nested unter Store-Key `company_extended`) |
| Bestehende Keys | Lesen/Weiterverwenden; bei Speichern Lohn-UI keine Doppelpflege erzwingen — Anzeige „aus Firmendaten“ + Link Einstellungen→Firma |

Audit: Änderungen an `lohn.*` und an referenzierten Pflichtfeldern über bestehendes Firmen-Historien-Muster (`CompanyMasterHistoryRepository` o. Ä.) — Detail in L1b; L1a verlangt nur: **wer/wann/Feld/alt/neu** nachvollziehbar.

#### Feldkatalog

**A — Wiederverwenden (Quelle heute)**

| Lohn-Bedarf | Quelle (Ist) | Pflicht für Monatslauf später? | Hinweis |
|-------------|--------------|--------------------------------|---------|
| Name Arbeitgeber | `company.name` bzw. `company_extended.legal_name` (Anzeige: legal_name falls gesetzt, sonst name) | ja | Kein zweites Namensfeld in `lohn` |
| Anschrift | `company.street/postal/city/country` | ja (PDF L6) | nur lesen |
| Steuernummer (LSt-relevant) | `company_extended.tax_numbers.est` (Fallback `company.tax_number`) | ja | USt-Id (`ust`/vat_id) **nicht** als LSt-Steuernummer missbrauchen |
| Finanzamt | `finanzaemter[]` / `finanzamt_resolved` (BuFö-Nr., Name) | ja | Primär: resolved/erstes FA mit BuFö; keine Behörden-API in L1 |
| Betriebsnummer | `employment_agency.betriebsnummer` | ja | BA-Betriebsnummer des AG; Format-Check in L1c |
| BG / UV | `bg_data.*` (carrier, company_number/member_no, …) | empfohlen (L9 UV später) | Mitgliedsnummer pflegen; kein digitaler Lohnnachweis in A |
| Bankkonten Stamm | `bank_accounts[]` (`BankAccountTypes`) | für SEPA L8 | Auswahl Absenderkonto über Index/Flag in `lohn` |

**B — Neu unter `company_extended.lohn` (L1b anlegen)**

| Key | Typ | Default | Pflicht Lauf | Bedeutung |
|-----|-----|---------|--------------|-----------|
| `bundesland` | string ISO-3166-2 DE (`DE-BY` …) oder leer | `''` | ja | KiSt/LSt-Tabellenbezug; **nicht** still aus PLZ raten in L1 — Nutzer setzt; optional Hilfsvorschlag erst L1c |
| `umlage_u1` | bool | `false` | ja* | Teilnahme U1 (Krankheit) |
| `umlage_u2` | bool | `false` | ja* | Teilnahme U2 (Mutterschaft) |
| `umlage_u3` | bool | `false` | ja* | Insolvenzgeldumlage (gesetzl. Regelfall oft ja — Default trotzdem bewusst setzen, nicht silent true ohne UI) |
| `u1_erstattungssatz_key` | string | `''` | wenn U1 | z. B. Tarifkennzeichen/Prozent-Schlüssel laut Beitragstabelle (Werte erst L4) |
| `payroll_bank_account_index` | int ≥ 0 | `0` | für L8 | Index in `bank_accounts` = SEPA-Absender |
| `baulohn_enabled` | bool | `false` | nein | **Nur Flag** (L0b D6) — **keine** Bau-Logik, keine SOKA-Felder |
| `notes` | string | `''` | nein | Interne Prüfungshinweise (z. B. GF-SV-Klärung auf Firmenebene) |

\*„Pflicht“ = Feld muss explizit gesetzt/bestätigt sein (auch `false`), nicht „muss true sein“.

**C — Bewusst nicht in L1**

| Thema | Wohin |
|-------|--------|
| MA Steuer/SV/IBAN Empfänger | L2 |
| Lohnarten | L3 |
| Beitragssätze / Jahrestabellen | L4 |
| ITSG-Zertifikat / MOD-ID | LB |
| SOKA / Winterbau | LC |
| ELSTER-Org-Zertifikat | C1 / L10 |

#### Validierung (Spec für L1c — hier nur Regeln)

| Feld | Regel |
|------|--------|
| Betriebsnummer | Nur Ziffern; Länge **8** (übliche BA-Betriebsnummer); leer = Warnung, Speichern erlaubt; Monatslauf-Block erst L5 |
| Steuernummer est | Nicht leer für Lauf; Format Plausibilität wie bestehende FA-Ermittlung — **keine** neue Elster-Prüfziffer-Wissenschaft in L1 |
| BuFö / Finanzamt | Mind. ein Finanzamt-Eintrag oder `finanzamt_resolved` mit Name |
| IBAN Absender | Aus gewähltem `bank_accounts[i]`; IBAN-Normalisierung bestehend nutzen; Leer → SEPA später blocken |
| `bundesland` | Allowlist DE-Länderkürzel |
| `baulohn_enabled` | Bool; UI-Hinweis „ohne Funktion bis LC“ |

Keine Live-Abfrage BA/ITSG/BG in L1.

#### Rechte & UI-Ort (Anschluss L0c)

| Aktion | Recht |
|--------|--------|
| AG-Lohnfelder lesen | `lohn` ≥ `partial` |
| AG-Lohnfelder / Umlagen / Flag schreiben | `lohn` === `full` |
| Firmendaten name/Steuernummer/BG/Betriebsnummer ändern | weiterhin Firmen-Einstellungen; Lohn-UI darf **anzeigen** und bei `full` optional Deep-Link — **kein** zweites Schreibrecht ohne Firma-Recht |

UI-Ort L1c: Settings-Tab **„Lohn“** (oder Unterbereich Firma→Lohn) — nicht unter Zeiterfassung.

#### Abnahme L0→L1a

| Prüfung | Ergebnis |
|---------|----------|
| Feldkatalog A/B/C vollständig | ✅ |
| Store: `company_extended.lohn`, keine Parallel-AG-Tabelle | ✅ |
| `baulohn_enabled` ohne Logik | ✅ |
| U1/U2/U3 + Bundesland als Lücken geschlossen | ✅ |
| Kein Code / keine Migration in diesem Chat | ✅ |

**Nicht:** Migration, Repository-Code, UI, MA-Felder, Berechnung, SV-Meldung senden.

### L1b — Migration + Repository AG-Stamm ✅ 2026-10-30

Keine SQL-Migration: Settings-JSON `company_extended` (wie L1a).

| Lieferobjekt | Ort |
|--------------|-----|
| Klasse `PayrollEmployerSettings` | `src/Settings/PayrollEmployerSettings.php` |
| Defaults/Normalize `lohn` | in `CompanyExtendedSettings` |
| Speichern nur Lohn-Block | `PayrollEmployerSettings::saveLohn()` |
| Lesen A+B | `PayrollEmployerSettings::employerSnapshot()` |
| Audit Feld-Diff | `AuditLog` action `payroll_employer_lohn` (JSON changes) |
| Autoload | `src/autoload.php` |
| Selftest (ohne DB) | `bin/payroll-employer-l1b-selftest.php` |

Verhalten:

- Bundesland-Allowlist ISO-3166-2 DE; unbekannte Werte → leer.
- U1/U2/U3 und `baulohn_enabled` als Bool (Default false); **keine** Baulohn-Berechnung.
- `saveFromPost` (Firma-UI): `lohn` nur überschreiben wenn im Post; sonst Store behalten.
- `CompanyMasterHistoryRepository` zusätzlich angerufen — bei reinem Lohn-Diff kann Fingerprint unverändert bleiben; **führend ist AuditLog**.

#### Abnahme L1b

| Prüfung | Ergebnis |
|---------|----------|
| Store-Key nested `lohn`, keine `dg_payroll_employer`-Tabelle | ✅ |
| A-Felder nur gelesen im Snapshot | ✅ |
| Audit Diff Feld/alt/neu | ✅ |
| Keine Settings-UI in diesem Paket | ✅ |

**Nicht:** UI/Settings-Tab (L1c), MA-Felder, Berechnung, SV-Meldung.

### L1c — Settings-UI AG-Stamm

Validierung Betriebsnummer-Format (Plausibilität, keine Behörden-API). Anbindung `PayrollEmployerSettings::saveLohn` / Snapshot-Anzeige A-Felder.

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

## 6. Empfohlene Chat-Kette (nächste Chats)

| # | Prompt-Kurz | Status |
|---|-------------|--------|
| — | `l0a`–`l0c` Spec-Rahmen / Entscheide / Rechte | ✅ 2026-10-30 |
| — | `l1a` AG-Stamm Spec | ✅ 2026-10-30 |
| — | `l1b` AG Store/`company_extended.lohn` | ✅ 2026-10-30 |
| 1 | `l1c` AG UI | **als Nächstes** |
| 4 | `l2a` MA-Felder Spec | |
| 5 | `l2b` MA Migration | |
| 6 | `l2c` MA UI | |

Danach L3a → … nicht überspringen. **L4 erst wenn L1–L3 stabil.**

Prompt für nächsten Chat:

```text
Lohn Implementierung: l1c laut docs/LOHNABRECHNUNG-IMPLEMENTIERUNG.md
Nur Abschnitt L1c. Nicht andere L-Pakete.
Abgrenzung „Nicht:“ aus dem Abschnitt einhalten.
Nach Abschluss: Status im Plan-Abschnitt setzen, kurzer Abnahme-Check.
```

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
