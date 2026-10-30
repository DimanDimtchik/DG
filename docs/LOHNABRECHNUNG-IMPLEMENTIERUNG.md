# Lohnabrechnung — Implementierungsplan (token-sparend)

> **Stand:** 2026-10-30 (L0–L2 ✅ · L3a ✅)  
> **Fachquellen:** [`LOHNABRECHNUNG-ERWEITERUNG.md`](LOHNABRECHNUNG-ERWEITERUNG.md) (Detailkonzept) · [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) § D0 · [`ERP-ERWEITERUNG-LASTENHEFT-V2.md`](ERP-ERWEITERUNG-LASTENHEFT-V2.md) §2  
> **Vorbild Betriebsstil:** [`ZEITERFASSUNG-PLAN.md`](ZEITERFASSUNG-PLAN.md) Serien Z2–Z6  
> **Status:** L3a Spec Lohnarten ✅ · nächster Chat: **`l3b`** (Migration/CRUD)

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

**MA-Lohnfelder (L2):** bleiben in **Kontakt → Mitarbeiterkarte** (kein eigener Menüpunkt). Speichern laut **L2a Rechte-Gate** (unten).

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

> Einstieg: `l1a` · **Serie L1 ✅**

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

### L1c — Settings-UI AG-Stamm ✅ 2026-10-30

| Lieferobjekt | Ort |
|--------------|-----|
| Settings-Tab HR → **Lohn & Gehalt** | `SettingsRegistry` tab `lohn` |
| View | `views/settings/tab-lohn.php` |
| POST `payroll_lohn_save` | `index.php` (Admin + CSRF) |
| `forForm` / `saveFromPost` / `collectWarnings` | `PayrollEmployerSettings` |

Verhalten:

- A-Felder nur Anzeige + Link Firmendaten (keine Doppelpflege).
- B-Felder editierbar; Speichern via `saveLohn` + Audit.
- Warnungen (Betriebsnummer ≠ 8 Ziffern, fehlende Steuernr./FA/Bundesland/IBAN) — Speichern erlaubt.
- `baulohn_enabled` mit Hinweis ohne Funktion bis LC.
- Recht: wie andere Settings-Tabs = Admin (`SettingsRegistry::canAccess`).

#### Abnahme L1c

| Prüfung | Ergebnis |
|---------|----------|
| Tab unter Einstellungen → HR | ✅ |
| Betriebsnummer-Plausibilität als Warnung | ✅ |
| Kein SV-Versand / keine Behörden-API | ✅ |
| Keine MA-Felder / keine Berechnung | ✅ |

**Nicht:** SV-Meldung senden, Modul-Seed `lohn` in Abteilungen (später mit Menü L5+), Monatslauf.

---

## Serie L2 — Mitarbeiter Steuer / SV / Beschäftigung

> Einstieg: `l2a` · **Serie L2 ✅**  
> Baut auf `EmployeeData` auf — **erweitern**, nicht paralleles HR-System.

### L2a — Spec MA-Lohnfelder ✅ 2026-10-30

Nur Spezifikation — Felder/Validierung = **L2b**, UI-Sektion = **L2c**. Kein PHP in L2a.

#### Ziel

Mitarbeiterstammdaten für Stufe-A-Abrechnung (Lexware-Office-Niveau) in der bestehenden Kontakt-Mitarbeiterkarte. Kein zweites HR-/Payroll-Personenmodell.

#### Speicher-Entscheidung (verbindlich für L2b)

| Ansatz | Entscheidung |
|--------|--------------|
| Neue Tabelle `dg_payroll_employee` | **Nein** in L2 |
| Paralleles JSON neben `employee_data` | **Nein** |
| Erweiterung | Keys in **`EmployeeData::fields()`** / JSON `employee_data` |
| Neue UI-Sektion | `section` = **`payroll`** Label „Lohnabrechnung“ |
| IBAN Auszahlung | Primär **Kontakt-`bank_accounts`** (Index/`is_primary`); optional Key `payroll_payout_bank_index` in employee_data |

#### Feldkatalog

**A — Wiederverwenden (Ist)**

| Bedarf | Quelle | Pflicht Lauf? | Hinweis |
|--------|--------|---------------|---------|
| Name / Kontakt | Kontaktstamm | ja | kein Duplikat |
| Geburtsdatum | `birth_date` | ja (SV/LSt) | schon `personal` |
| SV-Nummer | `social_security_number` | ja* | *bei sv-pflichtig / Status received |
| SV-Status / Meldestelle | `social_security_status`, `social_filing_office` | ja | Hinweise/DEÜV-Draft behalten |
| Krankenkasse Name | `health_insurance` | ja* | Text; Kassennummer optional neu |
| Eintritt / Austritt | `entry_date`, `exit_date` | Eintritt ja | Austritt nur wenn beendet |
| Vertragsdaten / Sollzeit | `contract_*`, `daily_work_minutes`, `working_hours` | nein für Netto | Zeiterfassung |
| Beschäftigungsart (Zeit) | `employment_type` | erweitern → B | heute: regular/minijob/intern |
| Freitext Gehalt | `salary` | nein | bleibt Hinweis; **Beträge/Lohnarten = L3** |
| Fremde Lohn-PDF | `payroll_slip` | nein | parallel bis L6 |
| Bankkonten | Kontakt `bank_accounts` | für SEPA | Auswahl Index |

**B — Neu in `EmployeeData` (section `payroll`)**

| Key | Typ | Default | Pflicht Lauf | Bedeutung |
|-----|-----|---------|--------------|-----------|
| `tax_id` | text | `''` | ja | Steuerliche Identifikationsnummer (11 Ziffern Plausibilität L2b/c) |
| `tax_class` | select 1–6 / `''` | `''` | ja | Steuerklasse; Stufe A manuell (L0b D4, kein ELStAM) |
| `child_allowances` | number 0–99 Schritt 0,5 | `''` | ja (0 ok) | Kinderfreibeträge (Zähler) |
| `religion_code` | select | `''` | ja | KiSt-Merkmal: `none` / `ek` / `rk` / `other` (Labels DE) |
| `health_insurance_company_number` | text | `''` | empfohlen | Betriebsnummer der Krankenkasse (8-stellig wenn gesetzt) |
| `contribution_group_key` | text | `''` | ja* | Beitragsgruppenschlüssel (KV/RV/AV/PV-Stellen, Format L2b) |
| `person_group_key` | text | `''` | ja* | Personengruppenschlüssel DEÜV (z. B. 101) |
| `payroll_employment_type` | select | `''` | ja | `fulltime` / `parttime` / `minijob` / `midijob` / `managing_director` |
| `annual_earnings_estimate_eur` | number | `''` | wenn midijob | Jahresentgelt-Schätzer Midi |
| `controlling_managing_director` | checkbox | `0` | wenn GF | Beherrschender GF (L0b D2) |
| `controlling_md_rationale` | textarea | `''` | wenn Flag | Begründung + ideal Datum der Prüfung im Text |
| `sv_exempt` | checkbox | `0` | nein | nur setzbar wenn GF-Flag; sonst ignorieren/warnen |
| `payroll_payout_bank_index` | int ≥ 0 | `0` | für L8 | Index in Kontakt-`bank_accounts` |
| `payroll_notes` | textarea | `''` | nein | interne Lohn-Hinweise |

\*bei `payroll_employment_type` ∈ {fulltime, parttime, midijob}; bei minijob/GF abweichende Pflichtmatrix siehe unten.

**C — Bewusst nicht in L2**

| Thema | Wohin |
|-------|--------|
| Brutto→Netto / Tabellen | L4 |
| Lohnarten-Zuordnung Betrag | L3 |
| ELStAM-Abruf | LB |
| Baulohn/SOKA-Felder | LC |
| Eigene Netto-PDF | L6 |

#### Beschäftigungsart: Mapping Zeit ↔ Lohn

| `payroll_employment_type` (neu, führend für Lauf) | Empfohlenes `employment_type` (Zeit) | Pflichtmatrix Lauf (Kurz) |
|---------------------------------------------------|--------------------------------------|---------------------------|
| `fulltime` / `parttime` | `regular` | tax_id, tax_class, child_allowances, religion, SV-Nr., KK, Beitrags-/Personengruppe, Eintritt, Bank |
| `minijob` | `minijob` (+ oft `kbs_minijob`) | tax_id, tax_class o. Pauschale-Hinweis, Eintritt, Bank; SV-Nr. wenn vorhanden; Personengruppe Minijob |
| `midijob` | `regular` | wie Voll/Teil + `annual_earnings_estimate_eur` |
| `managing_director` | `regular` | tax_*; wenn `controlling_managing_director`: Begründung Pflicht; `sv_exempt` nur dann erlaubt |

`employment_type` (Zeit) **nicht löschen** — Zeiterfassung/Minijob-Hilfen bleiben. L2b darf bei Speichern Lohn-Typ → Zeit-Typ **weich syncen** (Dokumentation in L2b), ohne stilles Überschreiben gegen Nutzerabsicht ohne Hinweis.

#### Rechte-Gate (verbindlich, löst L0c-Offenheit)

| Aktion | Bedingung |
|--------|-----------|
| Sektion „Lohnabrechnung“ **sehen** | Kontakt Mitarbeiterkarte sichtbar **und** (`lohn` ≥ `partial` **oder** Admin **oder** bis Modul `lohn` noch nicht geseedet: Admin/HR wie heutige EmployeeData-Sicht) |
| Sektion **speichern** | Kontakt speichern erlaubt **und** (`lohn` ≥ `partial` **oder** Admin). Ohne `lohn`-Recht: Felder read-only bzw. nicht im POST übernehmen |
| Übergang bis Modul geseedet | Speichern der neuen Keys für **Admin** erlaubt (wie Settings L1c); sobald `DepartmentAccess::MODULE_LABELS['lohn']` live: Gate wie Tabelle |

L2b implementiert Helper z. B. `PayrollEmployerSettings`-analog `PayrollEmployeeAccess::canView/canEdit(User)` — Spec-Name frei, Verhalten fest.

#### Validierung (für L2b/c — Regeln)

| Feld | Regel |
|------|--------|
| `tax_id` | Nur Ziffern; Länge 11 wenn gesetzt; leer = Warnung, Speichern ok; Lauf blockt später |
| `tax_class` | `''` oder 1–6 |
| `child_allowances` | leer oder Zahl ≥ 0, Schritt 0,5 |
| `religion_code` | Allowlist |
| `contribution_group_key` / `person_group_key` | Zeichenvorrat/Länge plausibel (L2b konkret); keine Behörden-API |
| `controlling_md_rationale` | wenn Flag: min. 10 Zeichen |
| `sv_exempt` | nur mit GF-Flag; sonst false erzwingen |
| IBAN gewählt | `BankDirectory::validateIban` wenn IBAN vorhanden; leer → SEPA später blocken |

Keine Live-Abfrage ELStAM/ITSG/KK in L2.

#### Abnahme L2a

| Prüfung | Ergebnis |
|---------|----------|
| Speicher = EmployeeData section `payroll`, keine Parallel-Tabelle | ✅ |
| A/B/C-Feldkatalog + Pflichtmatrix | ✅ |
| Rechte-Gate spezifiziert | ✅ |
| GF-Flag + Begründung (D2) | ✅ |
| Kein Code / keine Brutto-Netto-Formel | ✅ |

**Nicht:** Migration, UI, Berechnung, ELStAM, Baulohn-Felder.

### L2b — Migration + Validierung ✅ 2026-10-30

Keine SQL-Migration (`employee_data` JSON).

| Lieferobjekt | Ort |
|--------------|-----|
| Felder Katalog B + section `payroll` | `EmployeeData::fields()` / `sectionLabels()` |
| Select-Optionen | tax_class, religion_code, payroll_employment_type |
| Sanitize / Soft-Sync Zeit-Typ | `sanitizePayrollFields`, `softSyncEmploymentTypeFromPayroll` |
| Warnungen / Lauf-Lücken | `collectPayrollWarnings`, `missingPayrollFieldsForRun` |
| Preserve ohne Edit-Recht | `preservePayrollIfDenied` + Hook in `ContactRepository` |
| Rechte-Helper | `PayrollEmployeeAccess` (`canView`/`canEdit`) |
| Selftest | `bin/payroll-employee-l2b-selftest.php` |

Verhalten:

- Speichern mit unvollständigen Pflichtfeldern **erlaubt**; Lücken nur Warnung / `missingPayrollFieldsForRun` für L5.
- `sv_exempt` nur mit beherrschendem GF; GF-Flag nur bei Typ `managing_director`.
- Soft-Sync: `payroll_employment_type` → `employment_type` nur wenn leer oder bereits kompatibel.
- Modul `lohn` noch **nicht** in `MODULE_LABELS`: `canEdit` nur Admin; `canView` Admin/Edit/HR.

#### Abnahme L2b

| Prüfung | Ergebnis |
|---------|----------|
| Keys in EmployeeData, keine Parallel-Tabelle | ✅ |
| Sanitize laut L2a | ✅ |
| Access-Helper | ✅ |
| Keine Brutto-Netto-Berechnung / keine UI-Sektion neu gestaltet | ✅ (UI = L2c) |

**Nicht:** Berechnung; dedizierte UI-Gestaltung (L2c) — generisches Formular zeigt neue Felder ggf. schon mit, Feinschliff L2c.

### L2c — UI Kontakt/Mitarbeiterkarte ✅ 2026-10-30

| Lieferobjekt | Ort |
|--------------|-----|
| Formular-Sektion + Gate/Warnungen | `views/partials/employee-data-form.php` |
| Bank-Index als Select | Kontakt-`bankAccounts` |
| Detail-Ansicht Sektion | `views/modules/kontakte-detail.php` |
| Checkbox-Anzeige Ja/Nein | `EmployeeData::detailFields` |

Verhalten:

- Sektion nur bei `PayrollEmployeeAccess::canView`; Felder `disabled` ohne `canEdit` (Preserve beim Speichern bleibt L2b).
- Warnungen aus `collectPayrollWarnings`; Speichern nicht blockiert.
- Meldestelle-/SV-Hinweise unverändert in Sektion Sozialversicherung.
- Kein PDF erzeugen.

#### Abnahme L2c

| Prüfung | Ergebnis |
|---------|----------|
| Getrennte Sektion Lohnabrechnung | ✅ |
| Gate + Warnungen | ✅ |
| Kein PDF / keine Berechnung | ✅ |

**Nicht:** PDF erzeugen, Lohnarten-UI (L3), Monatslauf.

---

## Serie L3 — Lohnarten

> Einstieg: `l3a` · L3a ✅ · weiter mit `l3b`

### L3a — Spec Lohnarten-Katalog ✅ 2026-10-30

Nur Spezifikation — Schema/CRUD = **L3b**, MA-Zuordnung = **L3c**. Kein PHP in L3a.

#### Ziel

Mandantenfähiger Lohnarten-Stamm für Stufe A (Lexware-Office-Niveau): gleiche Codebasis, Daten je Instanz-DB. Lohnarten steuern Brutto-Positionen und Steuer-/SV-Behandlung in L4/L5 sowie FiBu-Hinweise in L7 — **ohne** Branchen-Sonderlogik Bau/Gastro/TVöD (kommt über Flags/spätere Arten in LC o. Ä.).

#### Speicher-Entscheidung (verbindlich für L3b)

| Ansatz | Entscheidung |
|--------|--------------|
| Settings-JSON wie AG-`lohn` | **Nein** — Katalog wächst, braucht CRUD/Liste |
| Tabelle Instanz-DB | **`dg_payroll_wage_types`** |
| Seed | Standardkatalog einmalig je Instanz (idempotent nach `code`) |
| Sonderarten Bau/Gastro | **nicht** im Seed; keine Codes `BAU_*` / `GASTRO_*` in A |

#### Entity `WageType` (Felder)

| Spalte / Key | Typ | Pflicht | Bedeutung |
|--------------|-----|---------|-----------|
| `id` | PK | ja | |
| `code` | varchar 32 unique | ja | Stabiler Schlüssel, z. B. `SALARY_MONTH` |
| `label` | varchar 120 | ja | Anzeigename DE |
| `category` | enum/string | ja | siehe Kategorien |
| `unit` | enum | ja | `month` / `hour` / `amount` / `percent` |
| `direction` | enum | ja | `earning` (Bezug) / `deduction` (Abzug) |
| `taxable` | bool | ja | unterliegt LSt |
| `subject_to_sv` | bool | ja | unterliegt SV (AN/AG-Beitragsgrundlage) |
| `flat_tax_possible` | bool | ja | Pauschalversteuerung möglich (Hinweis für L4, z. B. Minijob/GwV) |
| `increases_net` | bool | ja | fließt in Netto positiv (meist = earning) |
| `skr03_expense_hint` | varchar 16 | nein | Vorschlag Aufwandskonto SKR03 (nur Hint) |
| `skr04_expense_hint` | varchar 16 | nein | Vorschlag SKR04 |
| `skr03_liability_hint` | varchar 16 | nein | optional Verbindlichkeit |
| `skr04_liability_hint` | varchar 16 | nein | |
| `sort_order` | int | ja | UI-Reihenfolge |
| `is_system` | bool | ja | Seed-Satz; `code` nicht löschen, nur deaktivieren |
| `is_active` | bool | ja | Default true |
| `notes` | text | nein | interne Prüfungshinweise |
| `created_at` / `updated_at` | datetime | ja | |

**Konten-Hints:** keine harten Buchungen in L3 — L7 mappt freigegebene Läufe; Hints sind Berater-/Mandanten-Vorschläge und dürfen leer sein. Kein Instanz-Sondercode: abweichende Konten später in Mandanten-Mapping (eigene Spalte optional ab L7, nicht in L3a erzwingen).

#### Kategorien (`category`)

| category | Bedeutung |
|----------|-----------|
| `salary` | Festgehalt / Monatslohn |
| `hourly` | Stundenlohn |
| `benefit` | Sachbezug / geldwerter Vorteil |
| `allowance` | Zuschuss / Zulage (steuerpflichtig i. d. R.) |
| `tax_free` | steuerfreie Erstattung (z. B. Reisekosten-Pauschale — nur wenn fachlich steuerfrei) |
| `deduction` | Abzug (Nettolohnabzug / Vorschuss-Verrechnung o. Ä.) |
| `other` | Sonstiges (sparsam) |

#### Seed-Standardkatalog (Stufe A)

| code | label | category | unit | direction | taxable | subject_to_sv | flat_tax_possible | sort |
|------|-------|----------|------|-----------|---------|---------------|-------------------|------|
| `SALARY_MONTH` | Festgehalt / Monatslohn | salary | month | earning | 1 | 1 | 0 | 10 |
| `WAGE_HOUR` | Stundenlohn | hourly | hour | earning | 1 | 1 | 0 | 20 |
| `OVERTIME_PAY` | Überstundenvergütung | hourly | hour | earning | 1 | 1 | 0 | 25 |
| `BONUS` | Prämie / Zulage | allowance | amount | earning | 1 | 1 | 0 | 30 |
| `BENEFIT_IN_KIND` | Sachbezug | benefit | amount | earning | 1 | 1 | 1 | 40 |
| `FRINGE_BENEFIT` | Geldwerter Vorteil | benefit | amount | earning | 1 | 1 | 1 | 45 |
| `TAX_FREE_REFUND` | Steuerfreie Erstattung | tax_free | amount | earning | 0 | 0 | 0 | 50 |
| `EMPLOYER_ALLOWANCE` | Zuschuss Arbeitgeber | allowance | amount | earning | 1 | 1 | 0 | 55 |
| `NET_DEDUCTION` | Nettolohnabzug | deduction | amount | deduction | 0 | 0 | 0 | 80 |
| `ADVANCE_RECOVERY` | Vorschuss-Verrechnung | deduction | amount | deduction | 0 | 0 | 0 | 85 |

SKR-Hints in L3b-Seed als **Platzhalter-Kommentare** setzen (z. B. personalaufwandnahe Konten laut Mandanten-SKR); konkrete Nummern mit Steuerberater je Mandant — Spec verlangt nur Spalten, keine verbindliche Kontonummer-Liste hier (vermeidet falsche GoBD-Buchungssuggestionen).

#### Rechte & UI-Ort (Anschluss L0c)

| Aktion | Recht |
|--------|--------|
| Lohnarten liste/lesen | `lohn` ≥ `partial` (oder Admin) |
| Anlegen/Ändern/Deaktivieren | `lohn` ≥ `partial` + `canEdit`; System-`code` nicht umbenennen/löschen |
| Seite | `lohn-lohnarten` (Menü später mit Modul-Seed) |

#### Abgrenzung MA-Zuordnung (nur Spec-Rahmen für L3c)

Tabelle später z. B. `dg_payroll_employee_wage_assignments`:

| Feld | Bedeutung |
|------|-----------|
| `contact_id` | Mitarbeiter |
| `wage_type_id` | Lohnart |
| `amount` | Betrag oder Stundensatz (je `unit`) |
| `valid_from` / `valid_to` | Zeitraum |
| `priority` | Reihenfolge im Lauf |

**Nicht** in L3a/L3b implementieren — nur damit L3b das FK-Ziel kennt.

#### Bewusst nicht in L3

| Thema | Wohin |
|-------|--------|
| Brutto→Netto | L4 |
| Monatslauf-Positionen | L5 |
| FiBu-Buchung | L7 |
| Baulohn-/SOKA-/SFN-Arten | LC / später |
| Nacht-/So-/Feiertagszuschläge komplex | später (nach A stabil) |

#### Abnahme L3a

| Prüfung | Ergebnis |
|---------|----------|
| Tabelle + Feldkatalog + Seed-Liste dokumentiert | ✅ |
| Steuer-/SV-Flags je Art | ✅ |
| SKR nur als Hint-Spalten | ✅ |
| Keine Bau/Gastro-Arten | ✅ |
| Kein Code in diesem Chat | ✅ |

**Nicht:** Migration, CRUD-UI, Engine, MA-Zuordnung-Code, Branchen-Sonderarten.

### L3b — Migration + CRUD

Tabelle `dg_payroll_wage_types` laut L3a; Seed Standardkatalog; Repository + einfache Liste/Form (Rechte `lohn`); Systemzeilen nur deaktivierbar.

**Nicht:** Engine; keine MA-Zuordnung (L3c).

### L3c — Zuordnung MA ↔ Lohnart (Stamm)

z. B. monatliches Festgehalt / Stundenlohn-Satz; Tabelle Assignments laut L3a-Rahmen; UI an Mitarbeiterkarte oder unter Lohnarten.

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
| — | `l1c` AG UI | ✅ 2026-10-30 |
| — | `l2a` MA-Felder Spec | ✅ 2026-10-30 |
| — | `l2b` MA Felder/Validierung | ✅ 2026-10-30 |
| — | `l2c` MA UI | ✅ 2026-10-30 |
| — | `l3a` Lohnarten Spec | ✅ 2026-10-30 |
| 1 | `l3b` Lohnarten Migration/CRUD | **als Nächstes** |
| 2 | `l3c` MA↔Lohnart Zuordnung | |

Danach L4a → … nicht überspringen. **L4 erst wenn L1–L3 stabil.**

Prompt für nächsten Chat:

```text
Lohn Implementierung: l3b laut docs/LOHNABRECHNUNG-IMPLEMENTIERUNG.md
Nur Abschnitt L3b. Nicht andere L-Pakete.
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
