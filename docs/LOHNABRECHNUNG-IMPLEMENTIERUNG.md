# Lohnabrechnung — Implementierungsplan (token-sparend)

> **Stand:** 2026-10-30 (L0–L6 ✅)  
> **Fachquellen:** [`LOHNABRECHNUNG-ERWEITERUNG.md`](LOHNABRECHNUNG-ERWEITERUNG.md) (Detailkonzept) · [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) § D0 · [`ERP-ERWEITERUNG-LASTENHEFT-V2.md`](ERP-ERWEITERUNG-LASTENHEFT-V2.md) §2  
> **Vorbild Betriebsstil:** [`ZEITERFASSUNG-PLAN.md`](ZEITERFASSUNG-PLAN.md) Serien Z2–Z6  
> **Status:** L6c Lohnkonto-UI ✅ · nächster Chat: **`l7a`** (FiBu-Buchung Spec)

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

> Einstieg: `l3a` · L3a–c ✅ · weiter mit `l4a`

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

### L3b — Migration + CRUD ✅ 2026-10-30

| Lieferobjekt | Ort |
|--------------|-----|
| Migration | `database/migrations/125_payroll_wage_types.sql` |
| Repository + Seed | `PayrollWageTypeRepository` |
| Modul `lohn` in `DepartmentAccess` | default `none` |
| Menü-Sektion | `MenuRegistry::lohnSection` |
| Seite | `/app?page=lohn-lohnarten` · `views/modules/lohn-lohnarten.php` |
| Selftest | `bin/payroll-wage-types-l3b-selftest.php` |

Verhalten: Seed 10 System-Arten; System-`code` readonly; Deaktivieren statt Löschen bei `is_system`; SKR-Hints leer pflegbar.

#### Abnahme L3b

| Prüfung | Ergebnis |
|---------|----------|
| Tabelle + Seed laut L3a | ✅ |
| CRUD mit `PayrollEmployeeAccess` | ✅ |
| Keine Engine / keine MA-Zuordnung | ✅ |

**Nicht:** Engine; keine MA-Zuordnung (L3c).

### L3c — Zuordnung MA ↔ Lohnart (Stamm) ✅ 2026-10-30

| Lieferobjekt | Ort |
|--------------|-----|
| Migration | `database/migrations/126_payroll_employee_wage_assignments.sql` |
| Repository | `PayrollEmployeeWageAssignmentRepository` |
| Speichern | `ContactRepository::save` bei `PayrollEmployeeAccess::canEdit` |
| UI Formular | Mitarbeiterkarte → Sektion Lohnabrechnung → Tabelle Zuordnung |
| UI Detail | `kontakte-detail` unter Lohnabrechnung |
| Selftest | `bin/payroll-employee-wage-assignments-l3c-selftest.php` |

Felder: `contact_id`, `wage_type_id`, `amount`, `valid_from`/`valid_to`, `priority`, `notes`. Sync ersetzt den Satz je MA beim Speichern (nur mit Lohn-Schreibrecht). Ohne Recht bleiben Zuordnungen unverändert.

#### Abnahme L3c

| Prüfung | Ergebnis |
|---------|----------|
| Tabelle laut L3a-Rahmen | ✅ |
| Formular + Detail an MA-Karte | ✅ |
| Rechte über `PayrollEmployeeAccess` | ✅ |
| Kein Monatslauf / keine Engine | ✅ |

**Nicht:** Monatslauf; keine Brutto→Netto-Rechnung (L4).

---

## Serie L4 — Brutto→Netto-Kern (ohne UI-Lauf)

> Einstieg: `l4a` · L4a–c ✅ · weiter mit `l5a`

### L4a — Spec Rechenkern + Tabellen ✅ 2026-10-30

Nur Spezifikation — Import/Versionierung = **L4b**, Engine-Code/Tests = **L4c**. Kein PHP in L4a. Kein Monatslauf-UI (L5).

#### Ziel

Deterministischer **Brutto→Netto-Kern** für Stufe A (Lexware-Office-Niveau): gleiche Codebasis, Tabellenwerte je Instanz-DB, versioniert mit `gültig_ab` (L0b D1). Engine liest nur freigegebene Tabellenversionen + MA-/AG-Stamm (L1–L3). Ergebnis ist **Berechnungsobjekt** für L5 — nicht PDF, nicht FiBu, nicht Meldung.

#### Speicher-Entscheidung (verbindlich für L4b)

| Ansatz | Entscheidung |
|--------|--------------|
| Sätze hardcodiert in PHP | **Nein** (Jahreswechsel / GoBD) |
| Lose Settings-JSON ohne Version | **Nein** |
| Tabellenkopf + Parameterzeilen in Instanz-DB | **Ja** |
| Tabellenname (L4b) | `dg_payroll_rate_sets` + `dg_payroll_rate_params` |
| Ergebnis-Persistenz Monat | **Nein** in L4 — erst L5 (`draft`/`calculated`…) |

##### `dg_payroll_rate_sets` (Kopf)

| Spalte | Typ | Bedeutung |
|--------|-----|-----------|
| `id` | PK | |
| `valid_from` | DATE NOT NULL | Wirksamkeitsbeginn (`gültig_ab`) |
| `valid_to` | DATE NULL | optional Ende; sonst „offen bis nächste Version“ |
| `label` | VARCHAR(120) | z. B. `FIXTURE-2026-A` oder Import-Bezeichnung |
| `source_note` | VARCHAR(255) | Herkunft (Datei/Lieferant); **keine** Behörden-Geheimnisse im Repo |
| `checksum` | VARCHAR(64) | optional Hash der Importdatei |
| `is_active` | TINYINT | 1 = für Engine wählbar |
| `created_at` / `created_by_user_id` | | Audit-Anlage |
| `superseded_at` / `superseded_by_user_id` | | Audit bei Ablösung |

Eindeutigkeit: kein zweiter aktiver Satz mit gleichem `valid_from`. Auswahlregel Engine: für Abrechnungsmonat `M` den Satz mit max(`valid_from`) ≤ Monatsende und (`valid_to` IS NULL OR `valid_to` ≥ Monatsbeginn), sonst Fehler „Tabelle fehlt“.

##### `dg_payroll_rate_params` (Werte)

| Spalte | Typ | Bedeutung |
|--------|-----|-----------|
| `id` | PK | |
| `rate_set_id` | FK | → `dg_payroll_rate_sets` |
| `param_key` | VARCHAR(64) | Allowlist unten |
| `param_value` | DECIMAL(18,8) | numerisch; Prozentsätze als Anteil (0.146 = 14,6 %) oder EUR |
| UNIQUE | (`rate_set_id`, `param_key`) | |

##### Parameter-Allowlist Stufe A (L4b-Seed nur Fixtures / Import)

| `param_key` | Einheit | Verwendung |
|-------------|---------|------------|
| `minijob_month_limit_eur` | EUR | Geringfügigkeitsgrenze Monat |
| `midi_lower_eur` / `midi_upper_eur` | EUR | Übergangsbereich Grenzen |
| `kv_an_rate` / `kv_ag_rate` | Anteil | KV AN/AG (ohne Zusatzbeitrag) |
| `kv_additional_an_rate` | Anteil | Zusatzbeitrag AN (Durchschnitt/Mandant — Import) |
| `rv_an_rate` / `rv_ag_rate` | Anteil | RV |
| `av_an_rate` / `av_ag_rate` | Anteil | AV |
| `pv_an_rate` / `pv_ag_rate` | Anteil | PV Basis |
| `pv_childless_surcharge_an` | Anteil | Zuschlag kinderlos AN (wenn Merkmal gesetzt; Stufe A: optional Flag später / 0 in Fixture) |
| `bbg_kv_month_eur` / `bbg_rv_month_eur` | EUR | BBG Monat (KV/PV bzw. RV/AV) — Import |
| `u1_rate` / `u2_rate` / `u3_rate` | Anteil | Umlagen AG (aus AG-Stamm-Satzart × Tabelle) |
| `minijob_flat_lst_rate` / `minijob_flat_kv_rate` | Anteil | Pauschalen Minijob (wenn Pauschal-Pfad) |
| `solz_rate` | Anteil | Solidaritätszuschlag auf LSt (Vereinfachung Stufe A) |
| `kst_ek_rate` / `kst_rk_rate` | Anteil | Kirchensteuer auf LSt je Merkmal |
| `lst_fixture_factor` | Anteil | **nur Fixture/Tests:** Pseudo-LSt = Faktor × steuerpflichtiges Brutto — **kein** EStG-Tarif im Repo |

**Bewusst nicht in L4a/L4b als Repo-Wahrheit:** amtliche LSt-Jahrestabellen, geheime Schlüssel, ELSTER-Payloads. Produktiv-LSt in L4c: entweder (a) importierte Tabellenzeilen (später erweiterbar) oder (b) bis Import existiert **nur** Fixture-Faktor + dokumentierte Lücke „LSt-Tarif = Importpflicht“. Stufe-A-Produkt: L4b muss vor produktivem Lauf echte Parameter liefern; Unit-Tests laufen gegen `FIXTURE-*`.

#### Engine-Schnittstelle (Rahmen für L4c)

```text
PayrollEngineInterface::calculate(PayrollCalcRequest): PayrollCalcResult
StandardPayrollEngine — einzige Implementierung in Stufe A
(BaulohnEngine / … erst LC — nicht registrieren)
```

| Teil | Inhalt |
|------|--------|
| **Request** | `contact_id`, `period_ymd` (Monatsanfang), Snapshot AG (`PayrollEmployerSettings`), Snapshot MA (`employee_data` payroll + Stammdaten), Lohnarten-Zuordnungen (L3c), optionale Zeit-/Einmalbeträge (L5 füllt; L4c Tests stubben), `rate_set_id` oder Auto-Wahl |
| **Result** | siehe Ausgaben-Katalog; `rate_set_id` + `engine` = `standard`; `warnings[]`; bei Blockern `ok=false` |

Kein Schreiben in DB in L4c — pure Funktion. Persistenz = L5.

#### Eingaben je MA-Monat (Katalog)

| Gruppe | Quelle | Pflicht für `ok` |
|--------|--------|------------------|
| Periode | Aufrufer | ja |
| AG-Stamm | L1 `company_extended.lohn` | Betriebsnummer/Umlage-Satzart wenn SV/Umlage |
| MA Stammdaten | L2 payroll + Kontakt | laut L2a-Pflichtmatrix je `payroll_employment_type` |
| Lohnarten-Beträge | L3c Assignments (gültig in Periode) + Stub-Stunden | mind. eine Brutto-Position oder Stub |
| Beitragsgruppen | `contribution_group_key` / `person_group_key` | ja außer dokumentiertem Mini-/GF-Sonderpfad |
| Steuermerkmale | tax_id, tax_class, child_allowances, religion_code | ja außer Mini-Pauschal ohne LSt-Einzeln |
| Rate-Set | DB | ja |

#### Ausgaben je MA-Monat (Katalog)

Beträge in EUR, 2 Nachkommastellen kaufmännisch; Zwischenschritte intern 4+ Stellen ok.

| Key | Bedeutung |
|-----|-----------|
| `gross_taxable` | steuerpflichtiges Brutto (Summe Lohnarten mit `taxable`) |
| `gross_sv` | SV-Brutto (Flags `subject_to_sv`, nach Mini/Midi/BBG-Regeln) |
| `gross_total` | Summe earnings (Richtung earning) |
| `lst` | Lohnsteuer |
| `solz` | Solidaritätszuschlag |
| `kst` | Kirchensteuer |
| `kv_an` / `kv_ag` | KV AN/AG inkl. Zusatzbeitrag-Anteil laut Param |
| `rv_an` / `rv_ag` | RV |
| `av_an` / `av_ag` | AV |
| `pv_an` / `pv_ag` | PV |
| `u1` / `u2` / `u3` | Umlagen **nur AG** |
| `flat_lst` / `flat_other` | Pauschalsteuern/-abgaben (Mini o. Ä.) |
| `net_deductions` | Nettolohnabzüge (Lohnarten `deduction`) |
| `net_pay` | Auszahlungsnetto |
| `employer_cost` | AG-Kosten ≈ gross_total + AG-SV + Umlagen + AG-Pauschalen (Definition in L4c festhalten) |
| `lines[]` | Positionen: wage_type_id/code, amount, flags-Auswirkung |

**Netto-Formel (Stufe A, verbindlich):**

`net_pay = gross_total − lst − solz − kst − (kv_an+rv_an+av_an+pv_an) − net_deductions − flat_lst_employee_share`

Pauschalen Minijob: soweit gesetzlich AG-getragen → nicht von `net_pay` abziehen; in `flat_*` + `employer_cost` ausweisen. L4c dokumentiert die Aufteilung je Param-Pfad.

#### Rechenpfade nach Beschäftigungsart

| `payroll_employment_type` | Pfad | Kurzregeln |
|---------------------------|------|------------|
| `fulltime` / `parttime` | **Standard** | LSt via Tarif/Import bzw. Fixture-Faktor; SV nach Beitragsgruppe × Sätze × BBG; KiSt/SolZ aus LSt; Umlagen aus AG-Satzart |
| `minijob` | **Mini** | Brutto ≤ `minijob_month_limit_eur` (sonst Warnung/Block „kein Mini“); Pauschal-KV/LSt laut Param **oder** individueller Steuerpfad wenn Stamm so gesetzt; RV-Aufstockung Stufe A: nur wenn MA-Flag später — Default Fixture ohne Aufstockung |
| `midijob` | **Midi / Übergang** | SV-Brutto im Band `midi_lower`…`midi_upper`; reduzierte AN-Last nach importierter Formel-Parametern (L4b Keys erweiterbar, z. B. `midi_factor_*`); LSt wie Standard auf steuerpflichtiges Brutto; `annual_earnings_estimate_eur` nur Plausibilitäts-Warnung in A, keine automatische Umschlüsselung |
| `managing_director` | **GF** | LSt/SolZ/KiSt wie Standard; wenn `controlling_managing_director` **und** `sv_exempt`: **kein** KV/RV/AV/PV AN/AG, Umlagen 0, Warning „SV-frei laut Stamm“; ohne Flag `sv_exempt` ignorieren (L2b); ohne Flag aber Typ GF → Standard-SV |

Stundenlohn: Menge × Satz aus Assignment `WAGE_HOUR` / Stub-Stunden in Request — gleiche Pfade.

#### Strategy & Abgrenzung Code

| Thema | L4 |
|-------|-----|
| Interface + `StandardPayrollEngine` | L4c |
| Andere Engines | nicht in A |
| Monatslauf-UI / Status | L5 |
| PDF | L6 |
| FiBu | L7 |
| SEPA | L8 |
| ITSG / ELSTER / ELStAM | LB / L10 / Gates |
| Baulohn | LC |

#### Testvektoren (mind. 5) — nur **FIXTURE**, keine Amtswerte

Gemeinsames Rate-Set `FIXTURE-2026-A` (Beispieldaten für Unit-Tests; **nicht** produktiv übernehmen):

| param_key | value |
|-----------|-------|
| `minijob_month_limit_eur` | 560 |
| `midi_lower_eur` | 560.01 |
| `midi_upper_eur` | 2000 |
| `kv_an_rate` / `kv_ag_rate` | 0.073 / 0.073 |
| `kv_additional_an_rate` | 0.017 |
| `rv_an_rate` / `rv_ag_rate` | 0.093 / 0.093 |
| `av_an_rate` / `av_ag_rate` | 0.013 / 0.013 |
| `pv_an_rate` / `pv_ag_rate` | 0.017 / 0.017 |
| `pv_childless_surcharge_an` | 0 |
| `bbg_kv_month_eur` / `bbg_rv_month_eur` | 5000 / 7500 |
| `u1_rate` / `u2_rate` / `u3_rate` | 0.017 / 0.004 / 0.0006 |
| `minijob_flat_lst_rate` / `minijob_flat_kv_rate` | 0.02 / 0.13 |
| `solz_rate` | 0.055 |
| `kst_ek_rate` / `kst_rk_rate` | 0.09 / 0.09 |
| `lst_fixture_factor` | 0.14 |

Rundungstest: Endbeträge auf 2 Dezimalen; L4c legt Banker’s vs. kaufmännisch fest (Default **kaufmännisch** ROUND_HALF_UP).

| # | Name | Input (Kurz) | Erwartete Kern-Outputs (logisch) |
|---|------|--------------|----------------------------------|
| V1 | Festgehalt Standard | Typ fulltime, Assignment SALARY_MONTH 3000, StKl 1, religion none, Beitragsgruppe voll | `gross_total=3000`; `lst=420` (=0,14×3000); `solz=23.10`; SV-AN aus gross_sv=3000; `kst=0`; `net_pay` = 3000−420−23.10−Σ AN |
| V2 | Stundenlohn | Typ parttime, WAGE_HOUR 15 × 80 h Stub | `gross_total=1200`; LSt/SV analog V1-Faktoren auf 1200 |
| V3 | Minijob Pauschale | Typ minijob, Brutto 520, Pauschal-Pfad | `flat_lst`/`flat_other` aus 2 %/13 % auf 520; kein individueller `lst` (oder 0); SV-AN 0 im Default-Fixture; Brutto ≤ Limit |
| V4 | Midijob Band | Typ midijob, Brutto 1200, Schätzer gesetzt | `gross_sv` im Midi-Band; AN-SV **niedriger** als reines Standard auf 1200 (L4c: konkrete Midi-Formel mit zusätzlichen Fixture-Params `midi_an_factor=0.7` o. Ä. — in L4b-Seed dokumentieren); LSt wie Faktor×1200 |
| V5 | GF sv-frei | Typ managing_director, Flags controlling+sv_exempt+Begründung, Gehalt 5000 | `lst`/`solz`/`kst` wie Standard-Faktor; **alle** `*_an`/`*_ag` SV = 0; Umlagen 0; Warning gesetzt |
| V6 | Nettolohnabzug | wie V1 + Assignment NET_DEDUCTION 100 | `net_deductions=100`; `net_pay` um 100 niedriger als V1 |

L4c: goldene Zahlen für V1–V6 fest in Testfile hardcoden (aus FIXTURE berechnet); bei Formeländerung Spec+Vektoren zusammen anpassen.

#### Abnahme L4a

| Prüfung | Ergebnis |
|---------|----------|
| Ein-/Ausgaben-Katalog + Netto-Formel | ✅ |
| Tabellen `rate_sets`/`rate_params` + Allowlist | ✅ |
| Mini/Midi/GF-Pfade | ✅ |
| ≥5 Fixture-Testvektoren, keine Amtsgeheimnisse | ✅ |
| Kein PHP / kein Import-Code / kein Monatslauf | ✅ |

**Nicht:** Migration, Import-UI, Engine-PHP, ITSG-XML, Baulohn, ELSTER, PDF.

### L4b — Tabellen-Import + Versionierung (Code) ✅ 2026-10-30

| Lieferobjekt | Ort |
|--------------|-----|
| Migration | `database/migrations/127_payroll_rate_tables.sql` |
| Repository | `PayrollRateTableRepository` |
| Fixture-Seed | `FIXTURE-2026-A` inkl. `midi_an_factor=0.7` |
| CSV-Import | `importFromText` / Seite Upload+Text |
| Audit | `payroll_rate_import` · `payroll_rate_supersede` · `payroll_rate_param_patch` |
| UI | `/app?page=lohn-beitragstabellen` |
| Selftest | `bin/payroll-rate-tables-l4b-selftest.php` |

Verhalten: Allowlist-Keys; kein zweiter aktiver Satz mit gleichem `valid_from`; Ablösen statt Löschen; manuelle Param-Korrektur mit Alt/Neu-Audit. Periode-Auswahl `findForPeriod` für L4c.

#### Abnahme L4b

| Prüfung | Ergebnis |
|---------|----------|
| Migration + Fixture laut L4a | ✅ |
| Import-Pfad + Checksum | ✅ |
| Audit Import/Ablösen/Patch | ✅ |
| Kein Monatslauf / keine Engine | ✅ |

**Nicht:** Monatslauf-UI; keine Engine-Berechnung (L4c).

### L4c — `StandardPayrollEngine` + Unit-Tests ✅ 2026-10-30

| Lieferobjekt | Ort |
|--------------|-----|
| Interface | `PayrollEngineInterface` |
| DTO | `PayrollCalcRequest` / `PayrollCalcResult` |
| Engine | `StandardPayrollEngine` (pure, kein DB-Write) |
| Rate-Hydration | `StandardPayrollEngine::hydrateRates` → L4b |
| Selftest V1–V6 | `bin/payroll-engine-l4c-selftest.php` |

Verhalten: Rundung `ROUND_HALF_UP` 2 Dezimalen; LSt = `lst_fixture_factor` × steuerpflichtiges Brutto; Midi-AN × `midi_an_factor`; Minijob-Pauschalen AG-getragen (`flat_lst_employee_share=0`); GF+`sv_exempt` ohne SV/Umlagen.  
`employer_cost` = gross_total + AG-SV + Umlagen + flat_lst + flat_other.

#### Abnahme L4c

| Prüfung | Ergebnis |
|---------|----------|
| Nur `StandardPayrollEngine` | ✅ |
| V1–V6 goldene Fixture-Werte | ✅ |
| Kein PDF / FiBu / SEPA / Monatslauf-UI | ✅ |

**Nicht:** PDF, FiBu, SEPA, Persistenz (L5).

---

## Serie L5 — Monatslauf, Entwurf, Freigabe, Sperre

> Einstieg: `l5a` · L5a–c ✅ · weiter mit `l6c`

### L5a — Spec Lauf & GoBD-Workflow ✅ 2026-10-30

Nur Spezifikation — Schema/CRUD = **L5b**, UI = **L5c**. Kein PHP in L5a. Keine FiBu-Buchung (L7), kein PDF (L6), kein SEPA (L8).

#### Ziel

Pro Mandant/Instanz einen **Abrechnungsmonat** mit GoBD-fähigem Lebenszyklus: Entwurf → Berechnung (L4c) → Freigabe → Sperre. Freigegebene/gesperrte Ergebnisse werden **nicht still überschrieben** (L0b D8). Korrektur nur über neuen Lauf `storno`/`correction` mit Begründung und Benutzer. Zeiten aus Z6 fließen als **Eingabe** ein — Z6-Export bleibt parallel und unabhängig.

#### Lauf-Hierarchie (verbindlich für L5b)

| Ebene | Bedeutung |
|-------|-----------|
| **Run** (`dg_payroll_runs`) | Ein Abrechnungslauf für `(period_year, period_month)` + optional `kind` |
| **Employee result** (`dg_payroll_run_employees`) | Ein MA in diesem Lauf; hält Snapshot-Refs + `PayrollCalcResult`-Beträge |
| **Line** (`dg_payroll_run_lines`) | Positionen je MA (Lohnarten, Stub-Stunden, manuelle Korrekturen vor Freigabe) |
| **Status event** (`dg_payroll_run_events`) | Append-only Historie Statuswechsel / Storno-Link |

Ein **Kalendermonat** kann mehrere Runs haben: genau ein „aktueller“ produktiver Run (`kind=regular`, nicht storniert); plus `storno`/`correction`-Runs die auf `supersedes_run_id` zeigen.

#### Zustandsautomat

```text
draft ──calculate──► calculated ──approve──► approved ──lock──► locked
  │                     │                      │
  │◄──reopen*───────────┘                      │
  │                                            │
  └── (löschen nur draft, leer)                └── storno/correction → neuer Run (draft)
```

| Status | Bedeutung | Mutationen erlaubt |
|--------|-----------|-------------------|
| `draft` | Lauf angelegt; Positionen/MA-Auswahl editierbar | MA hinzu/entfernen, Lines edit, Zeiten neu laden, löschen wenn ohne Berechnung |
| `calculated` | Engine gelaufen; Beträge gespeichert | Lines nur mit Neuberechnung; zurück auf `draft` (`reopen`) mit Audit |
| `approved` | fachlich freigegeben | **keine** Betragsänderung; nur `lock` oder `storno`/`correction` |
| `locked` | revisionssicher für PDF/FiBu/SEPA-Nachfolge | nur `storno`/`correction` (neuer Run); kein Unlock |

\*`reopen` calculated→draft: nur `lohn`≥`partial`+`canEdit`; löscht gespeicherte Result-Beträge oder markiert `stale` — L5b wählt **löschen Result-Amounts + Events behalten**.

#### Transitionen & Rechte (Anschluss L0c)

| Von → Nach | Aktion | Recht |
|------------|--------|-------|
| — → `draft` | Lauf anlegen | `lohn` ≥ `partial` + `canEdit` |
| `draft` → `calculated` | `calculate` (Engine L4c je MA) | `partial` + `canEdit` |
| `calculated` → `draft` | `reopen` | `partial` + `canEdit` |
| `calculated` → `approved` | `approve` | **`full`** + `canEdit` |
| `approved` → `locked` | `lock` | **`full`** + `canEdit` |
| `approved`/`locked` → neuer Run | `storno` / `correction` | **`full`** + `canEdit` |
| `draft` löschen | nur ohne abhängige Events außer create | `partial` + `canEdit` |

Jeder Statuswechsel: Zeile in `dg_payroll_run_events` (`from_status`, `to_status`, `user_id`, `at`, `reason` — reason Pflicht ab `approve`/`storno`/`correction`/`lock`).

#### Run-Arten (`kind`)

| `kind` | Verwendung |
|--------|------------|
| `regular` | Normaler Monatslauf |
| `correction` | Korrekturabrechnung; `supersedes_run_id` = stornierter/zu ersetzender Run; `reason` Pflicht (min. 10 Zeichen) |
| `storno` | Storno-Kennzeichnung des Vorgänger-Runs; erzeugt Gegenlauf oder markiert Vorgänger `superseded` — L5b: Vorgänger-Status bleibt historisch `locked`/`approved`, Flag `is_superseded=1`; neuer `storno`-Run trägt Negativ-/Hinweislogik erst in L6/L7 aus |

Nach `storno`/`correction`: Vorgänger darf **nicht** erneut freigegeben werden; UI zeigt „ersetzt durch Run #…“.

#### Tabellenrahmen (für L5b — keine Migration hier)

##### `dg_payroll_runs`

| Spalte | Typ | Bedeutung |
|--------|-----|-----------|
| `id` | PK | |
| `period_year` / `period_month` | SMALLINT / TINYINT | Abrechnungsmonat |
| `kind` | ENUM/VARCHAR | `regular` / `correction` / `storno` |
| `status` | VARCHAR | `draft`/`calculated`/`approved`/`locked` |
| `supersedes_run_id` | NULL FK | Vorgänger bei correction/storno |
| `is_superseded` | TINYINT | 1 wenn durch neueren Run ersetzt |
| `rate_set_id` | NULL FK | verwendete Beitragstabelle (L4b) |
| `label` | VARCHAR(120) | optional |
| `created_at` / `created_by_user_id` | | |
| `approved_at` / `approved_by_user_id` | | |
| `locked_at` / `locked_by_user_id` | | |
| `notes` | TEXT | intern |

UNIQUE sinnvoll: kein zweiter **nicht-superseded** `regular`-Run für dieselbe Periode (L5b erzwingen).

##### `dg_payroll_run_employees`

| Spalte | Bedeutung |
|--------|-----------|
| `run_id`, `contact_id` | PK/UNIQUE Paar |
| `status` | `pending` / `ok` / `blocked` / `skipped` (MA-Ebene) |
| `engine` | z. B. `standard` |
| `path` | `standard`/`mini`/`midi`/`gf` |
| `result_json` | serialisierte Amounts aus `PayrollCalcResult` (prüfbar) |
| Snapshot-Felder oder JSON | MA-payroll + AG-lohn + Assignments zum Berechnungszeitpunkt |
| `warnings_json` | Engine-Warnungen |
| `net_pay` / `gross_total` | denormalisiert für Listen |

##### `dg_payroll_run_lines`

| Spalte | Bedeutung |
|--------|-----------|
| `run_id`, `contact_id` | |
| `source` | `assignment` / `time` / `manual` / `ot_payout` |
| `wage_type_id` / `code` | |
| `amount`, `hours`, `unit`, `direction`, Flags taxable/SV | Eingabe für Engine |
| `sort_order` | |

Vor `calculate`: Lines aus L3c-Assignments + Z6-Quellen aufbauen/aktualisieren (nur in `draft`). Nach `calculated`: Lines einfrieren bis `reopen`.

##### `dg_payroll_run_events`

Append-only: `run_id`, `from_status`, `to_status`, `user_id`, `created_at`, `reason`, optional `details_json`.

#### Berechnung (Anschluss L4c)

1. Nur aus `draft` (oder nach `reopen`).
2. Für jeden MA mit `status≠skipped`: `PayrollCalcRequest` aus Snapshots + Lines + `rate_set` (`findForPeriod` oder Run-`rate_set_id`).
3. `StandardPayrollEngine::calculate`; bei `ok=false` → MA `blocked`, Lauf darf trotzdem auf `calculated` — Freigabe blockt wenn offene `blocked` (konfigurierbar: **Default: Freigabe verboten solange blocked>0**).
4. Speichern Result + Snapshots; Run → `calculated`.
5. Keine Ledger-Buchung, kein PDF in L5.

#### Zeiten / Z6-Anschluss (soft)

| Quelle | Nutzung in L5 | Fehlt / Fehler |
|--------|---------------|----------------|
| L3c Assignments (Festgehalt/Satz) | Basis-Lines `source=assignment` | Warnung; Lauf möglich wenn andere Lines |
| Stub-/Zeiterfassung Stunden für `WAGE_HOUR` | Line `source=time` (Stunden × Satz) | Soft: MA `blocked` oder Warning — **Default Warning + blocked wenn Typ parttime/hourly ohne Stunden** |
| Z6e Überstunden-Auszahlung | Line `source=ot_payout` wenn markiert zur Auszahlung | Soft-degraden: fehlt → Warning „OT-Auszahlung nicht übernommen“, kein Hard-Fail des Monats |
| Z6 DATEV/Lex-**Export** | **nicht** ersetzen; bleibt eigene Seite | — |

Prinzip: Z6-Modulrechte unverändert; Monatslauf **liest** Daten, schreibt nicht zurück in Z6-Exporte. Keine harte Kopplung an erfolgreichen Z6-Download.

#### Plausibilität vor Freigabe

| Check | Wirkung |
|-------|---------|
| MA `blocked` vorhanden | Freigabe **nein** |
| `rate_set_id` fehlt / inaktiv | calculate **nein** |
| AG Betriebsnummer leer | Warning; Freigabe in Stufe A **noch erlaubt** mit Pflicht-`reason`-Zusatz „Betriebsnummer fehlt“ (oder block — **Default: block approve**) |
| Summe `net_pay` = 0 alle MA | Warning, Freigabe mit reason möglich |
| Superseded regular existiert parallel | UI verhindert zweiten regular |

#### UI-Ort (Rahmen L5c)

- Seite `lohn-monatslauf`: Monatswähler, Liste Runs, Detail mit MA-Tabelle, Aktionen laut Rechte.
- Kein SEPA-/ELSTER-Button in L5.

#### Abgrenzung

| Thema | Wohin |
|-------|--------|
| PDF Abrechnung | L6 |
| FiBu | L7 |
| SEPA | L8 |
| Portal/SV-Dateien | L9 |
| Engine-Formel | L4c (unverändert aufrufen) |
| Baulohn | LC |

#### Abnahme L5a

| Prüfung | Ergebnis |
|---------|----------|
| Zustände draft→…→locked + Rechte | ✅ |
| Storno/Correction ohne Soft-Overwrite (D8) | ✅ |
| Tabellenrahmen Run/Employee/Line/Event | ✅ |
| Z6 soft-degrade dokumentiert | ✅ |
| Kein PHP / keine FiBu | ✅ |

**Nicht:** Migration, UI-Code, PDF, Ledger-Buchung, SEPA, ELSTER.

### L5b — Migration Läufe + Positionen + Audit ✅ 2026-10-30

| Lieferobjekt | Ort |
|--------------|-----|
| Migration | `database/migrations/128_payroll_runs.sql` |
| Repository | `PayrollRunRepository` |
| Rechte | `PayrollRunAccess` (`canEditRun` / `canApprove`) |
| Selftest | `bin/payroll-runs-l5b-selftest.php` |

Verhalten: ein aktiver `regular`-Run je Periode; `calculate` → L4c je MA; `reopen` löscht Result-Amounts; `approve` blockt bei blocked-MA / fehlender Betriebsnummer; `lock`; `storno`/`correction` setzt `is_superseded` und legt neuen `draft` mit kopierten Lines; Events + `AuditLog` `payroll_run_status`.

#### Abnahme L5b

| Prüfung | Ergebnis |
|---------|----------|
| 4 Tabellen laut L5a | ✅ |
| Statusübergänge + Events | ✅ |
| Calculate-Hook L4c | ✅ |
| Kein PDF / keine Monatslauf-UI | ✅ |

**Nicht:** PDF; UI `lohn-monatslauf` = L5c.

### L5c — UI Monatslauf (Liste, berechnen, Freigabe) ✅ 2026-10-30

| Lieferobjekt | Ort |
|--------------|-----|
| Seite | `/app?page=lohn-monatslauf` · `views/modules/lohn-monatslauf.php` |
| Menü | `MenuRegistry::lohnSection` → Monatslauf |
| Aktionen | anlegen, MA+/−, Stamm-Lines, berechnen, reopen, freigeben, sperren, storno/correction, Entwurf löschen |

Rechte: sehen = `PayrollRunAccess::canView`; edit/calculate = `canEditRun`; freigeben/sperren/storno = `canApprove`.

#### Abnahme L5c

| Prüfung | Ergebnis |
|---------|----------|
| Liste + Detail + Statushistorie | ✅ |
| Rechte partial vs full | ✅ |
| Kein SEPA / kein ELSTER | ✅ |

**Nicht:** SEPA, ELSTER, PDF (L6), FiBu (L7).

---

## Serie L6 — PDF-Abrechnung & Lohnkonto

> Einstieg: `l6a` · L6a–c ✅ · weiter mit `l7a`

### L6a — Spec PDF + Lohnkonto ✅ 2026-10-30

Nur Spezifikation — Generator/Speicher = **L6b**, Lohnkonto-UI/Export = **L6c**. Kein PHP in L6a. Keine Behördenmeldung, kein SEPA, keine FiBu.

#### Ziel

Aus **gesperrten** Monatsläufen (`status=locked`, nicht superseded) je MA eine revisionssichere **Lohnabrechnungs-PDF** erzeugen und ablegen. Parallel bleibt die fremde Ablage `payroll_slip` an der Mitarbeiterkarte (L0/L2) — eigene PDF ersetzt sie **nicht** sofort (Übergang Lexware/Kanzlei, D5). **Lohnkonto**: chronologische Übersicht je MA/Jahr aus gespeicherten Run-Ergebnissen (+ optional Link zur PDF).

#### Trigger & Voraussetzungen

| Regel | Entscheidung |
|-------|--------------|
| Wann erzeugen? | Nach `lock` (automatisch Queue/Sync in L6b) **oder** manuell „PDF erzeugen“ nur wenn Run `locked` und MA `ok` |
| Entwurf-PDF | Optional aus `calculated`/`approved` mit Wasserzeichen „ENTWURF — nicht verbindlich“; **nicht** revisionssicher, überschreibbar |
| Verbindliche PDF | Nur aus `locked` + MA-Status `ok`; Datei unveränderlich |
| Storno/Correction | Neue PDF für neuen locked Run; Vorgänger-PDF bleibt; Lohnkonto zeigt beide mit Kennzeichnung |
| Fehlende Betriebsnummer | Lock ist in L5 bereits geblockt — PDF setzt voraus, dass AG-Snapshot vollständig genug war |

#### Mindestangaben Abrechnung (PDF-Inhalt)

**Kopf / Arbeitgeber**

| Feld | Quelle |
|------|--------|
| Arbeitgeber-Name (legal/display) | L1 Snapshot / `PayrollEmployerSettings::employerSnapshot` |
| Anschrift | Firmendaten |
| Betriebsnummer | Snapshot |
| Steuernummer (ESt) / Finanzamt-Kurz | Snapshot soweit vorhanden |
| Abrechnungsmonat | Run `period_year`/`period_month` |
| Dokumenttitel | „Lohnabrechnung“ + Periode |
| Run-ID / Art (`regular`/`correction`/`storno`) | Run |
| Erzeugt am / Benutzer | Lock- bzw. Generate-Zeitpunkt |

**Mitarbeiter**

| Feld | Quelle |
|------|--------|
| Name, Anschrift | Kontaktstamm (Snapshot zum Calculate) |
| Personal-/Steuer-ID (steuerliche IdNr.) | payroll `tax_id` (maskierbar in UI-Druckoption später) |
| Steuerklasse, Kinderfreibeträge, KiSt-Merkmal | Snapshot |
| SV-Nummer (wenn vorhanden) | employee_data |
| Beschäftigungsart / Pfad | `path` / `payroll_employment_type` |
| Eintritt | `entry_date` |

**Beträge (Körper)**

| Block | Inhalt |
|-------|--------|
| Brutto-Positionen | Run-Lines (code, Bezeichnung wenn vorhanden, Betrag, Stunden) |
| Steuerpflichtiges Brutto / SV-Brutto | Result `gross_taxable` / `gross_sv` |
| Abzüge AN | LSt, SolZ, KiSt, KV/RV/AV/PV AN, Nettolohnabzüge, ggf. `flat_lst_employee_share` |
| Netto / Auszahlungsbetrag | `net_pay` |
| AG-Anteile (Ausweis) | KV/RV/AV/PV AG, Umlagen U1/U2/U3, Minijob-Pauschalen `flat_lst`/`flat_other` |
| AG-Kosten gesamt | `employer_cost` (Hinweis „intern/Kontroll“) |
| Warnungen | optional klein gedruckt, wenn im Result |

**Fuß**

| Angabe | Inhalt |
|--------|--------|
| Hinweis | „Dies ist eine Abrechnung aus dem DG-CRM. Keine ELStAM-/Behördenübermittlung.“ |
| Parallel-Hinweis Stufe A | „Fremde Lohnbüro-PDFs können weiterhin unter Mitarbeiterdokumente liegen.“ |
| Seiten / Dateiname | `lohn-{contactId}-{YYYYMM}-run{runId}.pdf` |

Keine Bank-IBAN-Vollanzeige Pflicht in A (optional maskiert aus payout-Index); SEPA = L8.

#### Speicher-Entscheidung (verbindlich für L6b)

| Ansatz | Entscheidung |
|--------|--------------|
| Nur File ohne DB-Meta | **Nein** |
| Tabelle `dg_payroll_documents` | **Ja** |
| Dateipfad | Instanz-Storage analog Kontakt-Uploads / Belege (L6b konkretisiert Pfad-Helper) |
| Inhalt nach `locked` | **immutable** — kein Überschreiben; neue Version = neuer Datensatz nur bei neuem Run |

##### `dg_payroll_documents` (Rahmen)

| Spalte | Bedeutung |
|--------|-----------|
| `id` | PK |
| `run_id` | FK Run |
| `contact_id` | FK Kontakt |
| `kind` | `payslip` / `payslip_draft` / später `lohnkonto_export` |
| `period_year` / `period_month` | denormalisiert |
| `storage_path` | relativ Instanz |
| `checksum` | SHA-256 Datei |
| `byte_size` | |
| `is_final` | 1 = aus locked (unveränderlich) |
| `created_at` / `created_by_user_id` | |
| UNIQUE | sinnvoll `(run_id, contact_id, kind)` für `payslip` final — ein Final je MA/Run |

#### Lohnkonto (fachlich)

| Aspekt | Spec |
|--------|------|
| Scope | Je `contact_id` + Kalenderjahr |
| Zeilen | Jeder nicht-skipped MA-Eintrag aus Runs mit Status ≥ `calculated` (empfohlen: nur `approved`/`locked` für „verbindlich“; Entwurf ausblendbar) |
| Spalten mind. | Monat, Run#, Art, Status, Brutto, LSt, SV-AN-Summe, Netto, PDF-Link, superseded-Flag |
| Sortierung | Monat ASC, Run-ID ASC |
| Jahres-Summen | Brutto, Netto, LSt, SV-AN (aus denormalisierten Feldern / result_json) |
| Export | Kontroll-CSV (D5) in L6c; Lohnkonto-PDF optional Sammeldruck |
| Rechte | `lohn` ≥ `partial` sehen; Final-PDF Download wie sehen; Erzeugen final = `full` oder generate nach Lock durch System |

Seite: `lohn-lohnkonto` (L0c). Einstieg auch von Kontakt-Mitarbeiterkarte (Link „Lohnkonto“) in L6c.

#### Parallelität `payroll_slip`

| Regel | |
|-------|--|
| Fremde Uploads | bleiben; keine Auto-Löschung bei eigener PDF |
| UI | Kontakt zeigt beide Blöcke: „CRM-Abrechnungen“ (dg_payroll_documents) + „Externe Lohn-PDFs“ (`payroll_slip`) |
| Cutover | erst nach bewusster Betriebsentscheidung (nicht in L6a automatisieren) |

#### Rechte (Kurz)

| Aktion | Mindest |
|--------|---------|
| Entwurf-PDF sehen/erzeugen | `partial` + `canEdit` |
| Final-PDF erzeugen (falls manuell) | `full` + `canEdit` (oder auto bei Lock) |
| Final-PDF / Lohnkonto sehen | `partial` |
| Kontroll-CSV Lohnkonto | `partial` |

#### Abgrenzung

| Thema | Wohin |
|-------|--------|
| PDF-Binary schreiben | L6b |
| Lohnkonto-UI + CSV | L6c |
| FiBu | L7 |
| SEPA | L8 |
| ELSTER/SV-Meldung | L10 / LB / L9 |
| Layout-Pixel-Feinschliff | L6b (Briefpapier-Anbindung wenn vorhanden, sonst schlichtes CRM-PDF) |

#### Abnahme L6a

| Prüfung | Ergebnis |
|---------|----------|
| PDF-Mindestangaben + Dateiname | ✅ |
| Speicher `dg_payroll_documents` + Immutability | ✅ |
| Lohnkonto-Spalten/Jahr | ✅ |
| Parallel `payroll_slip` | ✅ |
| Kein PHP / keine Behördenmeldung | ✅ |

**Nicht:** Generator-Code, UI, FiBu, SEPA, ELSTER.

### L6b — PDF-Generator + Speicherung ✅ 2026-10-30

Migration `129_payroll_documents.sql` → `dg_payroll_documents`. Generator Dompdf (`PayrollPayslipPdfService`), Ablage `storage/payroll/{YYYY}/{MM}/lohn-{contactId}-{YYYYMM}-run{runId}[.draft].pdf`, SHA-256, Immutability Final. Auto nach `PayrollRunRepository::lock` für MA `ok`; Draft optional überschreibbar. Selftest: `bin/payroll-documents-l6b-selftest.php`.

#### Abnahme L6b

| Prüfung | Ergebnis |
|---------|----------|
| Migration 129 + MigrationRunner | ✅ |
| Storage-Pfad + Immutability Final | ✅ |
| HTML Mindestangaben + Draft-Wasserzeichen | ✅ |
| Dompdf Binary + Checksum (wenn vendor) | ✅ |
| Lock-Hook Finals (Event bei Fehler) | ✅ |
| Keine FiBu / keine Lohnkonto-UI | ✅ |

**Nicht:** FiBu, Lohnkonto-UI (L6c), SEPA, Behördenmeldung.

### L6c — Lohnkonto-UI + Export ✅ 2026-10-30

Seite `/app?page=lohn-lohnkonto` (`PayrollLohnkontoService`): MA/Jahr, Spalten Monat/Run/Art/Status/Brutto/LSt/SV-AN/Netto/PDF/ersetzt, Jahressummen (ohne superseded), Kontroll-CSV, Final-PDF-Download. Menü + Link von Kontaktkarte („CRM-Abrechnungen“ parallel zu `payroll_slip`). Selftest: `bin/payroll-lohnkonto-l6c-selftest.php`.

#### Abnahme L6c

| Prüfung | Ergebnis |
|---------|----------|
| Seite + Menü `lohn-lohnkonto` | ✅ |
| Spalten/Summen/Entwurf-Filter | ✅ |
| Kontroll-CSV (BOM, DE-Zahlen) | ✅ |
| Final-PDF-Download + Kontakt-Blöcke | ✅ |
| Kein SEPA / keine FiBu | ✅ |

**Nicht:** SEPA, FiBu (L7), Behördenmeldung.

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
| — | `l3b` Lohnarten Migration/CRUD | ✅ 2026-10-30 |
| — | `l3c` MA↔Lohnart Zuordnung | ✅ 2026-10-30 |
| — | `l4a` Brutto→Netto Spec | ✅ 2026-10-30 |
| — | `l4b` Tabellen-Import/Versionierung | ✅ 2026-10-30 |
| — | `l4c` StandardPayrollEngine + Tests | ✅ 2026-10-30 |
| — | `l5a` Monatslauf Spec | ✅ 2026-10-30 |
| — | `l5b` Migration Läufe/Positionen | ✅ 2026-10-30 |
| — | `l5c` UI Monatslauf | ✅ 2026-10-30 |
| — | `l6a` PDF/Lohnkonto Spec | ✅ 2026-10-30 |
| — | `l6b` PDF-Generator/Speicherung | ✅ 2026-10-30 |
| — | `l6c` Lohnkonto-UI + Export | ✅ 2026-10-30 |
| 1 | `l7a` Spec Buchungssätze | **als Nächstes** |

Danach L7b → … nicht überspringen.

Prompt für nächsten Chat:

```text
Lohn Implementierung: l7a laut docs/LOHNABRECHNUNG-IMPLEMENTIERUNG.md
Nur Abschnitt L7a. Nicht andere L-Pakete.
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
