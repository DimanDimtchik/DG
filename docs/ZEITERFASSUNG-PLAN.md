# Zeiterfassung & Personal — Umsetzungsplan

> **Stand:** 2026-09-21  
> Status: **Phase 1–6 ✅** · Spec **Z3a–Z6a ✅** · **Z6b–Z6d Code ✅**  
> Verwandt: `EmployeeData`, `ContactFileStorage`, `CalendarWorkingHoursRepository`, Buchhaltung (Lohn-Export später)

---

## Ziel

Mitarbeiter können sich anmelden und unter **Zeiterfassung** ein-/ausstempeln. Führungskräfte und HR sehen Monats-/Jahresübersichten, Urlaub/Krankheit, Schichten und Zeitkonten. Daten werden für die **externe Lohnbuchhaltung** (DATEV, Lexoffice, …) exportiert — eigene Lohnabrechnung kommt später.

---

## Rechtliche / fachliche Leitplanken (Deutschland)

| Thema | Anforderung im System | Status |
|--------|----------------------|--------|
| **ArbZG §3 — Aufzeichnungspflicht** | Beginn, Ende, Dauer der täglichen Arbeitszeit; Pausen; Überstunden — **lückenlos, nachvollziehbar, mindestens 2 Jahre** | Stempel-Log ✅ · Auswertung/Export Phase 2 |
| **ArbZG §4 — Höchstarbeitszeit** | Regelmäßig max. **8 h/Tag**; verlängerbar auf **10 h**, wenn **8 h-Wochendurchschnitt** in 6 Monaten / 24 Wochen eingehalten wird | Warnung/Hinweis geplant Phase 2 |
| **ArbZG §5 — Ruhezeit** | Mindestens **11 h** ununterbrochene Ruhezeit nach Arbeitsende | Prüfung geplant Phase 2 |
| **ArbZG §7 — Nacht-/Sonntags-/Feiertagsarbeit** | Besondere Regeln, ggf. Zuschläge, Freizeitausgleich | Zuschläge später (Phase 6) |
| **Pausen (§4 ArbZG)** | Mind. **30 min** ab 6 h, **45 min** ab 9 h (Block ≥15 min) | ✅ Auto-Pause + Zwangspause |
| **Geringfügig beschäftigt (Minijob)** | Flag am Mitarbeiter → **kein Überstunden-Zeitkonto**, Warnung bei Stempel über Soll | ✅ |
| **ArbZG-Ausgleich (§3)** | 6 Kalendermonate: wöchentlicher Durchschnitt max. **48 h**; Erinnerung am **1. des Folgemonats** | ✅ Prüfung + E-Mail + Team-UI |
| **TzBfG** | Teilzeit ohne Benachteiligung; keine verdeckten Vollzeit-Anforderungen | Stammdaten / Soll-Zeiten |
| **BUrlG — Urlaub** | Anspruch, Rest, Genehmigung, Rückstellungen | Phase 4 |
| **EFZG — Entgeltfortzahlung** | Krankheit, AU-Fristen | Phase 4 (Attest-Verknüpfung vorbereitet) |
| **JArbSchG** | Bei Praktikanten/Minijobbern unter 18: kürzere Arbeitszeiten, keine Nachtarbeit | Flag `employment_type = intern` |
| **Nachweis / GoBD-Personal** | Unveränderliche Stempel-Historie; Korrekturen nur mit Begründung + Berechtigung | Audit-Log ✅ · Korrektur-UI Phase 2 |
| **DSGVO** | Zweckbindung, Löschfristen an `EmployeeRetentionService` | Anbindung vorhanden |

### ArbZG-Erinnerung (implementiert)

- Auswertung über **6 Kalendermonate** (einstellbar) anhand aggregierter Ist-Zeiten (`dg_time_work_days`).
- **Schwellenwert:** durchschnittlich **> 48 Stunden pro Woche** (§3 ArbZG, WD 6/097/19).
- **Erinnerung:** am **1. des Folgemonats** nach abgeschlossenem 6-Monats-Zeitraum (nicht nach 5 Monaten).
- **Text (Verantwortliche):** *„Durchschnittlich hat [Name] mehr als 48 Stunden pro Woche in den letzten 6 Monaten gearbeitet. Die Überstunden sind dringend abzubauen, um gesetzliche Bestimmungen nach Bundestag-WD 6/097/19 zu erfüllen.“*
- **E-Mail:** Sammelliste an Personal + Abteilungsleiter (Fallback GF → Admin); Mitarbeiter nur eigene Daten.
- **Team-UI:** rollierende 6-Monats-Anzeige bei aktuellem Verstoß.

> Vertragliche Überstunden (Soll/Ist laut Arbeitsvertrag) werden separat erfasst (`overtime_minutes` in Tagesaggregation), lösen aber **keine** ArbZG-Erinnerung aus.

---

## Was bereits im CRM existiert (Wiederverwendung)

| Baustein | Nutzen für Zeiterfassung |
|----------|---------------------------|
| `Contact` Rolle Mitarbeiter + `EmployeeData` | Stammdaten, Eintritt/Austritt, Beschäftigungsart, Minijob-Meldestelle |
| `EmployeeData::documentTypes()` | Arbeitsvertrag, Ausweise — erweiterbar um Lohnabrechnungen |
| `ContactFileStorage` | Dokumentenablage pro Mitarbeiter |
| `CalendarWorkingHoursRepository` | Öffnungszeiten / Buchungszeiten — Basis für **Soll-Arbeitszeit** (pro Standort/Abteilung) |
| `CalendarStaffRepository` | Mitarbeiter-Zuordnung Kalender |
| `DepartmentRepository` | Abteilungen, Schichtzuordnung später |
| Login / Rollen (`RoleResolver`) | Mitarbeiter sieht nur eigene Zeiten; HR/Admin alles |

---

## Modul-Struktur (geplant)

```
/app?page=zeiterfassung              → Einstempeln / Ausstempeln / Tagesübersicht (Mitarbeiter)
/app?page=zeiterfassung-team       → Teamübersicht (Vorgesetzte/HR)
/app?page=zeiterfassung-auswertung → Monat/Jahr, Export
/app?page=zeiterfassung-urlaub     → Urlaubsanträge & Kalender
/app?page=zeiterfassung-krank      → Krankmeldungen
Einstellungen → Personal → Zeiterfassung (Pausen, Schichten, Überstunden-Regeln)
```

Öffentlich **nicht** — nur eingeloggte Mitarbeiter (ggf. später Kiosk-Modus mit PIN).

---

## Datenmodell (Entwurf Migration 060+)

### `dg_time_clock_events`
Einzelne Stempelungen (immutable Log).

| Spalte | Typ | Beschreibung |
|--------|-----|--------------|
| id | INT | PK |
| contact_id | INT | Mitarbeiter |
| event_type | ENUM | `clock_in`, `clock_out`, `break_start`, `break_end` |
| occurred_at | DATETIME | Zeitpunkt |
| source | ENUM | `web`, `manual_correction`, `auto_break` |
| shift_id | INT NULL | optional Schicht |
| note | TEXT | bei Korrektur Pflicht |
| created_by | INT NULL | bei manueller Korrektur |

### `dg_time_work_days`
Aggregiert pro Tag (Performance + Auswertung).

| Spalte | Beschreibung |
|--------|--------------|
| contact_id, work_date | PK zusammen |
| scheduled_minutes | Soll laut Schicht/Arbeitszeitmodell |
| worked_minutes | Ist (netto ohne Pause) |
| break_minutes | Pausen gesamt |
| overtime_minutes | nur wenn erlaubt, sonst 0 |
| status | `open`, `closed`, `approved` |

### `dg_time_accounts`
Zeitkonto-Salden (Überstunden, Urlaub).

| Spalte | Beschreibung |
|--------|--------------|
| contact_id | Mitarbeiter |
| account_type | `overtime`, `vacation` |
| balance_minutes | Saldo |
| year | optional Jahresbezug Urlaub |

### `dg_time_shifts`
Schichtpläne (Woche/Monat).

### `dg_time_absences`
Urlaub, Krankheit, Sonderurlaub.

| Spalte | Beschreibung |
|--------|--------------|
| type | `vacation`, `sick`, `other` |
| date_from, date_to | |
| status | `requested`, `approved`, `rejected` |
| document_id | Link Attest |

### `dg_time_payroll_exports`
Protokoll Exporte an Lohnsoftware.

---

## Funktionen nach Phase

### Phase 1 — MVP Stempeluhr ✅

- [x] Menüpunkt **Zeiterfassung** für Rolle Mitarbeiter
- [x] **Einstempeln / Ausstempeln** mit Live-Anzeige „seit …“
- [x] **Manuelle Pause** starten/beenden
- [x] **Automatische Pause** nach Regel (Einstellungen → Termine → Zeiterfassung)
- [x] **Zwangspause** — Ausstempeln blockiert bis Mindestpause manuell genommen
- [x] **Autostart** — offene Tage (vergessen auszustempeln) schließen via `runIfDue()`
- [x] Tagesliste eigener Stempel
- [x] HR: Team heute (wer ist eingestempelt) — `/app?page=zeiterfassung-team`
- [x] Flag `overtime_allowed` + `employment_type` (Minijob) in `EmployeeData`

Migration: `061_time_clock.sql` · Module: `TimeClockService`, `TimeTrackingSettings`

### Phase 2 — Soll/Ist & Zeitkonto

- [ ] Reguläre Arbeitszeiten pro Mitarbeiter oder Abteilung (Wochentage, Stunden)
- [ ] Monatsansicht: Soll, Ist, Differenz, Überstunden
- [x] **ArbZG-Erinnerung** nach 6 Kalendermonaten bei Ø > 48 h/Woche (Team-UI + E-Mail)
- [ ] Vertragliches Überstundenkonto / Abbau buchen
- [ ] Warnung bei Minijob: „Keine Überstunden erlaubt“ (Tageswarnung ✅)
- [ ] Korrekturen mit Berechtigung + Audit
- [ ] ArbZG: Ruhezeit 11 h, max. 10 h/Tag, Wochendurchschnitt 8 h

### Phase 3 — Schichten

- [x] Schichtplan (Früh/Spät/Nacht oder frei definierbar) — Vorlagen Z3b + Zuordnung Z3c + Soll Z3d ✅
- [x] Zuordnung Mitarbeiter ↔ Schicht ↔ Datum
- [x] Abweichung Soll (Schicht) vs. Ist (Stempel)

### Phase 4 — Urlaub & Krankheit

- [x] Urlaubsantrag → Genehmigung Workflow — Z4c ✅
- [x] Urlaubskonto (Tage/Minuten, Jahresanspruch) — Entitlement Z4b + UI Z4c ✅
- [x] Krankmeldung, Verknüpfung Attest-Upload — Z4d (Ref auf Kontaktakte) ✅
- [x] Kalenderansicht Abwesenheiten (Team) — Z4d ✅

### Phase 5 — Rückstellungen & Buchhaltung

- [x] **Urlaubsrückstellung** — Preview Z5b + Buchung Z5c + Checkliste Z5d ✅
- [x] **Überstunden-Rückstellung** (optional) — Flag + Preview Z5b + Buchung Z5c ✅
- [x] Anbindung an Jahresabschluss-Checkliste — Z5d ✅

### Phase 6 — Lohn-Export (ohne eigene Abrechnung)

- [x] Exportformate: **DATEV Lohn**, **Lexoffice Lohn**, CSV-Standard — CSV Z6b + DATEV Z6c + Lexoffice Z6d ✅
- [~] Monatsdaten: Arbeitsstunden, Überstunden, Urlaub, Krankheit, Zuschläge (später) — CSV-Felder Z6b ✅; Zuschläge später
- [x] Dokumente: Lohnabrechnung PDF ablegen (`payroll_slip` Dokumenttyp) ✅ Z6d
- [ ] Später: eigene Lohnabrechnung (separates Großprojekt)

---

## UI-Skizze Mitarbeiter (Phase 1)

```
┌─────────────────────────────────────┐
│  Guten Tag, Max Mustermann          │
│  Status: ● Eingestempelt seit 08:02 │
│                                     │
│  [ Ausstempeln ]  [ Pause starten ] │
│                                     │
│  Heute: 4h 12min (Pause: 30min)     │
│  Soll heute: 8h 00min               │
└─────────────────────────────────────┘
```

---

## Einstellungen (neuer Tab „Personal“ oder unter Termine)

- Pausenregel automatisch (nach ArbZG-Vorgaben voreingestellt)
- Mindestpause manuell erzwingen
- Überstunden: Genehmigungspflicht ja/nein
- Schichtvorlagen
- Lohn-Export: Mandant/Beraternummer, Format, Zielordner

---

## Technische Hinweise

- **Zeitzone:** Europe/Berlin, UTC in DB speichern oder lokale Zeit konsistent
- **API:** `TimeClockApi` analog `VoucherApi` für AJAX Stempel
- **Cron:** ~~Täglich offene Tage schließen~~ → `TimeClockService::runIfDue()` in `App::boot` · Auto-Pause · Erinnerung „vergessen auszustempeln“ (UI-Warnung + Autoclose)
- **DSGVO:** Stempeldaten = personenbezogen, Aufbewahrung an `EmployeeRetentionService` anknüpfen

---

## Abhängigkeiten & Reihenfolge

1. Phase 1 (Stempeluhr) — schneller Nutzen  
2. Teilzahlungen Buchhaltung (parallel möglich, siehe `BUCHHALTUNG-BELEGKETTE.md`)  
3. Phase 2–4 Personal  
4. Lohn-Export Phase 6 vor eigener Lohnabrechnung  

---

## Offene Fragen an Product Owner

| # | Frage | Default für Z2+ (token-sparend) |
|---|--------|----------------------------------|
| 1 | Kiosk-Tablet (PIN)? | **Später** — nicht in Z2–Z4 |
| 2 | GPS beim Stempeln? | **Nein** in Z2 |
| 3 | Lohnsoftware zuerst? | **DATEV Lohn** in Z6a; Lexoffice optional Z6b |
| 4 | Soll-Quelle? | **Zuerst** `CalendarWorkingHoursRepository` / bestehende Arbeitszeiten; nur bei Lücke eigene MA-Soll-Felder |
| 5 | Zuschläge Nacht/So/Feiertag? | **Z6+**, nicht Z2 |

Abweichung nur per explizitem Chat-Befehl.

---

## Betrieb Phase 2+ (token-sparend)

> **Agent-Regel:** Pro Chat **ein** Unterpunkt (`z2a` …). Spec nur dieser Abschnitt + genannte Dateien.  
> **Kein** paralleles Einlesen Phase 3–6. **Kein** Deploy außer „deploy“.  
> Rhythmus wie Multi-Firma MB: Spec/Checkliste → Code → Smoke → `commit z2a`.

### Entscheid-Checkliste (ohne Agent abhaken)

| # | Entscheidung | Default |
|---|--------------|---------|
| T1 | Erste Umsetzung | ✅ Phase **2** vor Schichten/Urlaub |
| T2 | Soll-Quelle | ✅ Kalender-Arbeitszeiten wiederverwenden |
| T3 | Korrektur-Recht | ✅ HR/Admin; Mitarbeiter nur eigene Anträge (wenn gebaut) |
| T4 | Export in Z2 | ✅ CSV Monatsblatt (ArbZG-Nachweis); DATEV = Z6 |
| T5 | Buchungs-Rückstellung | ✅ erst Z5 (Steuerberater) |

### Reihenfolge

**Serie Z2:** ✅ abgeschlossen  

**Serie Z3 (Schichten — jetzt):**

1. **Z3a** Spec Schicht-Vorlagen + Zuordnung + Soll-Prio ✅  
2. **Z3b** Migration + CRUD Vorlagen (Früh/Spät/Nacht / frei) ✅  
3. **Z3c** Zuordnung MA ↔ Schicht ↔ Datum + Wochen-UI ✅  
4. **Z3d** Soll aus Schicht in `TimeScheduleService` + Abweichung Ist ✅  

**Spätere Serien (eigene Chat-Ketten, nicht mischen):**

| Serie | Inhalt | Einstieg |
|-------|--------|----------|
| **Z3** | Schichten | `z3a`–`z3d` ✅ |
| **Z4** | Urlaub & Krankheit | `z4a`–`z4e` ✅ |
| **Z5** | Rückstellungen Buchhaltung | `z5a`–`z5d` ✅ |
| **Z6** | Lohn-Export DATEV/CSV | `z6a`–`z6d` ✅ |

### Z2a — Spec/Checkliste ✅ 2026-09-21

Nur Spezifikation — Umsetzung Code = **Z2b+**. Keine Migration, keine UI in Z2a.

#### Ist-Stand (Ph.1, relevant für Soll)

| Baustein | Verhalten heute |
|----------|-----------------|
| `TimeClockService::daySummary` | Soll = `EmployeeData::dailyTargetMinutes` |
| `dailyTargetMinutes` | 1) `daily_work_minutes` · 2) erste Zahl in `working_hours` × 60 · 3) **sonst 480** (8 h) |
| `CalendarWorkingHoursRepository` | Firma-Öffnungszeiten (`start_time`/`end_time`/`weekdays`/`start_date`) — **noch nicht** an Stempel-Soll gekoppelt |
| Aggregation | `dg_time_work_days.scheduled_minutes` aus daySummary |
| Team-Recht | `TimeClockService::canViewTeam` = Admin **oder** HR-Abteilung **oder** Modul `zeiterfassung` Level `full` |

#### Soll-Mapping (verbindlich für Z2b)

Funktion Ziel: `scheduledMinutesFor(contactId, date): int` (Name in Z2b festlegen).

| Prio | Quelle | Regel |
|------|--------|--------|
| 1 | MA-Stammdaten `daily_work_minutes` | Wenn > 0 → Minuten (cap 960 wie heute) |
| 2 | MA-Stammdaten `working_hours` | Erste Ganzzahl × 60 (wie heute), nur wenn Prio 1 leer |
| 3 | Kalender `dg_calendar_working_hours` | Zeile mit größtem `start_date ≤ date`; Wochentag in `weekdays`; Soll = Differenz `end_time − start_time` (Pause **nicht** abziehen — Pause ist Ist-Thema) |
| 4 | Kein Treffer | **0** + UI-Hinweis „Soll nicht hinterlegt“ |

**Breaking vs. Ph.1:** Stiller Fallback **480 entfällt** in Z2b (TzBfG/Minijob: kein Fake-Vollzeit-Soll). Prüfbarkeit: Differenz/Überstunden nur bei hinterlegtem Soll.

**Wochentag ohne Kalender-Match:** Wenn Prio 1/2 gesetzt, gilt Soll auch am Wochenende (Vertragstage später Z3); Kalender (Prio 3) liefert 0 außerhalb `weekdays`.

**Minijob / `overtime_allowed=0`:** Soll-Mapping unverändert; Überstunden-Gutschrift bleibt 0 (Ph.1). Warnung bei Ist > Soll bleibt.

**Nicht in Z2b:** pro-Abteilung-Soll-Tabelle, Schicht-Soll (Z3), Feiertags-Nullung (kann Soft-Hinweis Z2d).

#### Rollen & Rechte (verbindlich für Z2c–Z2e)

| Aktion | Wer |
|--------|-----|
| Eigenes Stempeln / eigene Tagesliste | Modul `zeiterfassung` + `canEdit` (wie Ph.1) |
| Eigenes Monatsblatt (Z2c) | derselbe Kreis; nur **eigene** `contact_id` |
| Team-Monatsblatt / CSV-Export fremder MA | `canViewTeam` (Admin \| HR \| zeiterfassung `full`) |
| Stempel-Korrektur buchen (Z2e) | **nur** `canViewTeam` — mit Pflicht-Begründung |
| Überstunden-Abbau buchen (Z2e) | **nur** `canViewTeam` |
| Mitarbeiter-Selbstkorrektur / Antrag | **nicht** in Z2 — frühestens Z4-Workflow-Stil |

**Audit (Z2e):** Originale `dg_time_clock_events` unverändert; Korrektur als zusätzlicher Event-Typ oder Korrektur-Tabelle + Verweis; wer/wann/warum speichern (GoBD-Personal).

**Seite:** Monatsblatt unter neuem Slug z. B. `zeiterfassung-monat` (oder Auswertung wie Plan-Skizze) — Menü nur bei `zeiterfassung`; Team-Filter nur bei `canViewTeam`.

#### Abnahme Z2a

| Prüfung | Ergebnis |
|---------|----------|
| Soll-Priorität 1→4 dokumentiert | ✅ |
| Fake-8h-Fallback abgeschafft (Spec) | ✅ |
| Rollen Stempel / Monat / Korrektur klar | ✅ |
| Kein Code in diesem Chat | ✅ |

**Nicht:** Code, Migration, UI.

### Z2b — Soll anbinden ✅ 2026-09-21

| Lieferobjekt | Ergebnis |
|--------------|----------|
| `TimeScheduleService::scheduledMinutesFor` | Prio MA → Kalender → 0 |
| Fake-8h | entfernt (`EmployeeData::dailyTargetMinutes` → personal only / 0) |
| Anbindung | `TimeClockService::daySummary` + Team-Heute |
| UI-Hinweis | Warnung „Soll nicht hinterlegt“ wenn Soll = 0 |

**Nicht:** Schichtplan, Urlaub, Monatsblatt (Z2c).

### Z2c — Monatsansicht + CSV ✅ 2026-09-21

| Lieferobjekt | Ergebnis |
|--------------|----------|
| UI | `/app?page=zeiterfassung-monat` — Tagesspalten Soll/Ist/Pause/Diff/ÜStd + Monatssumme |
| Rechte | Eigenes Blatt für Stempel-Nutzer; Team-Auswahl nur `canViewTeam` |
| CSV | UTF-8 BOM, `;`, Minuten + Anzeige, Summenzeile; kein DATEV |
| Service | `TimeMonthReportService` (aggregiert `dg_time_work_days`, sonst Live) |

**Nicht:** DATEV-Lohn, Zuschläge, Korrektur (Z2e).

### Z2d — ArbZG-Warnungen ✅ 2026-09-21

| Lieferobjekt | Ergebnis |
|--------------|----------|
| >10 h/Tag | Soft-Warnung in `daySummary` + Team + Monats-Flag |
| Ruhezeit <11 h | Soft-Warnung (letzter `clock_out` → erster `clock_in`) |
| Ø-Woche >8 h | Soft-Hinweis Kalenderwoche (Tage mit Ist > 0) |
| Hard-Block | **nein** (kein Spec-Flag) |
| 48h/6 Monate | unverändert (bestehende Erinnerung) |

**Nicht:** Zuschläge, neue Erinnerungs-E-Mail.

### Z2e — Korrektur + Zeitkonto ✅ 2026-09-21

| Lieferobjekt | Ergebnis |
|--------------|----------|
| UI | `/app?page=zeiterfassung-konto` (nur `canViewTeam`) |
| Korrektur | `dg_time_corrections` + Audit-Event `correction_audit`; Originale unverändert |
| Ist-Anrechnung | `daySummary` addiert Delta |
| Abbau | FIFO `minutes_remaining`; Audit `dg_time_overtime_reductions`; Minijob gesperrt |
| Migration | `090_time_corrections.sql` |

**Nicht:** Urlaubsantrag, Lohn-Export, Mitarbeiter-Selbstkorrektur.

### Chat-Vorlage

```text
Scope: Zeiterfassung Z2a laut docs/ZEITERFASSUNG-PLAN.md § Betrieb Phase 2+
Nur: Spec/Checkliste Soll-Quelle + Korrektur-Rechte
Kein Code, keine Migration, kein Deploy.
Nicht Phase 3–6 neu einlesen.
```

Weitere: `Z2b` / `Z2c` / `Z2d` / `Z2e` / `Z3a` analog.

### Token-Sparregeln

1. **Ein Unterpunkt pro Chat**  
2. T1–T5 vorher in der Checkliste lassen (Defaults oben)  
3. Composer/lokal für Spec; Cloud nur bei SSH/Deploy  
4. Kein erneutes Gesamtkonzept-Einlesen  
5. Nach jedem Unterpunkt: **ein Commit** wenn Doku/Code geändert  
6. Max. 1 Lokal- + 1 Cloud-Chat

### Bewusst nicht in Z2

- Kiosk/PIN, GPS  
- Schichten, Urlaub, Krankheit  
- Rückstellungen, DATEV-Lohn  
- Nacht-/Sonntagszuschläge  
- Eigene Lohnabrechnung

---

## Betrieb Phase 3 — Schichten (token-sparend)

> **Agent-Regel:** Pro Chat **ein** Unterpunkt (`z3a` …). Spec nur dieser Abschnitt + genannte Dateien.  
> **Kein** paralleles Einlesen Z4–Z6. **Kein** Deploy außer „deploy“.

### Entscheid-Checkliste Z3

| # | Entscheidung | Default |
|---|--------------|---------|
| S1 | Vorlagen | ✅ frei benennbar + Seed Früh/Spät/Nacht |
| S2 | Mitternacht | ✅ `end < start` = über Mitternacht (Dauer = Rest + Ende) |
| S3 | Soll-Prio | ✅ **Schicht-Zuordnung schlägt** MA-Stammdaten und Kalender |
| S4 | Mehrfach/Tag | ✅ höchstens **eine** Zuordnung pro Kontakt+Datum |
| S5 | Zuschläge Nacht | ✅ **nicht** in Z3 (erst Z6+) |

### Z3a — Spec Schicht-Modell ✅ 2026-09-21

Nur Spezifikation — Code/Migration = **Z3b+**.

#### Datenmodell (Ziel)

| Tabelle | Zweck |
|---------|--------|
| `dg_time_shift_templates` | Vorlagen: `name`, `start_time`, `end_time`, `sort_order`, `active` |
| `dg_time_shift_assignments` | `contact_id`, `work_date`, `template_id` (UNIQUE contact+date) |

**Dauer-Minuten:** wenn `end > start` → Differenz; wenn `end ≤ start` → `(24:00−start) + end` (Nacht). Cap 960 wie Z2. Pause **nicht** von Soll abziehen (wie Kalender-Soll).

**Seed (Z3b):** drei Vorlagen z. B. Früh 06:00–14:00, Spät 14:00–22:00, Nacht 22:00–06:00 — editierbar/löschbar wenn unbenutzt.

#### Soll-Prio (ersetzt Z2a-Liste ab Z3d)

| Prio | Quelle |
|------|--------|
| **0** | Schicht-Zuordnung an diesem Datum → Vorlagen-Dauer |
| 1 | MA `daily_work_minutes` |
| 2 | MA `working_hours` |
| 3 | Kalender-Öffnungsdauer |
| 4 | 0 + Hinweis |

Ohne Zuordnung: Verhalten unverändert Z2b.

#### Rechte

| Aktion | Wer |
|--------|-----|
| Vorlagen CRUD | `canViewTeam` |
| Zuordnung planen (Woche) | `canViewTeam` |
| Eigene Schicht sehen (Stempel/Monat) | Stempel-Nutzer (read-only) |
| Mitarbeiter ändert eigene Schicht | **nein** in Z3 |

**Seite (Z3c):** z. B. `/app?page=zeiterfassung-schichten` — Wochenraster Kontakt × Tag; Link von Team/Konto.

#### Abweichung Ist vs. Schicht-Soll (Z3d)

- Monatsblatt/Stempel: Soll = Schicht wenn gesetzt  
- Soft-Hinweis wenn Ist stark abweicht optional; **kein** Hard-Block  
- ArbZG Soft-Warnungen (Z2d) bleiben

#### Abnahme Z3a

| Prüfung | Ergebnis |
|---------|----------|
| Tabellen + Mitternacht-Regel | ✅ |
| Soll-Prio 0 = Schicht | ✅ |
| Rechte canViewTeam | ✅ |
| Kein Code in diesem Chat | ✅ |

**Nicht:** Migration, UI, Zuschläge, Urlaub.

### Z3b — Vorlagen CRUD ✅ 2026-09-21

| Lieferobjekt | Ergebnis |
|--------------|----------|
| Migration | `091_time_shift_templates.sql` + Seed Früh/Spät/Nacht |
| Service | `TimeShiftTemplateRepository` (Dauer inkl. Mitternacht) |
| UI | `/app?page=zeiterfassung-schicht-vorlagen` (`canViewTeam`) |
| Löschen | nur ohne Zuordnung; sonst deaktivieren |

**Nicht:** Zuordnung (Z3c), Soll-Anbindung (Z3d).

### Z3c — Zuordnung Wochen-UI ✅ 2026-09-21

| Lieferobjekt | Ergebnis |
|--------------|----------|
| Migration | `092_time_shift_assignments.sql` (UNIQUE contact+date) |
| Repo | `TimeShiftAssignmentRepository` |
| UI | `/app?page=zeiterfassung-schichten` — Woche MA × Tag → Vorlage |
| Rechte | `canViewTeam` |

**Nicht:** Soll-Anbindung (Z3d), Lohn, Zuschläge.

### Z3d — Soll-Anbindung ✅ 2026-09-21

| Lieferobjekt | Ergebnis |
|--------------|----------|
| `TimeScheduleService` | Prio 0 = Schicht-Zuordnung → Vorlagen-Dauer |
| Stempel/Team/Monat | Schicht-Soll + Name sichtbar |
| Soft-Hinweis | Ist weicht ≥60 min vom Schicht-Soll (kein Block) |

**Nicht:** Z4 Urlaub, Zuschläge.

### Chat-Vorlage Z3

```text
Scope: Zeiterfassung Z3a laut docs/ZEITERFASSUNG-PLAN.md § Betrieb Phase 3
Nur: Spec Schicht-Vorlagen + Zuordnung + Soll-Prio
Kein Code, keine Migration, kein Deploy.
Nicht Z4–Z6 neu einlesen.
```

Weitere: `Z3b` / `Z3c` / `Z3d` analog.

---

## Betrieb Phase 4 — Urlaub & Krankheit (token-sparend)

> **Agent-Regel:** Pro Chat **ein** Unterpunkt (`z4a` …). Spec nur dieser Abschnitt.  
> **Kein** paralleles Einlesen Z5–Z6 / Z3-Code. **Kein** Deploy außer „deploy“.  
> Hinweis: Z3b–d (Schicht-Code) bleibt parallel offen — Serien nicht in einem Chat mischen.

### Entscheid-Checkliste Z4

| # | Entscheidung | Default |
|---|--------------|---------|
| U1 | Datenhaltung | ✅ eigene `dg_time_absences` (+ Urlaubskonto) — **nicht** 1:1 die Kalender-Absences ersetzen |
| U2 | Kalender-Absences | ✅ `dg_calendar_employee_absences` bleibt Terminkalender-Team; optional später Spiegel (nicht Z4) |
| U3 | Urlaubseinheit | ✅ **Tage** (halbe Tage erlaubt als 0,5); Minuten nur intern optional |
| U4 | Genehmigung | ✅ Antrag MA → Freigabe `canViewTeam` |
| U5 | Stempel an Abwesenheit | ✅ Soft-Warnung; **kein** Hard-Block in Z4 |
| U6 | Rückstellung | ✅ erst **Z5** (Steuerberater) |

### Z4a — Spec Urlaub/Krankheit ✅ 2026-09-21

Nur Spezifikation — Code/Migration = **Z4b+**.

#### Ist-Stand (Wiederverwendung)

| Baustein | Relevanz |
|----------|----------|
| `dg_calendar_employee_absences` | Terminkalender-Team (vacation/sick/other) — **Planung**, kein Genehmigungs-Workflow |
| `EmployeeDocuments` / Kontaktakte | Attest-Upload-Anbindung vorbereitet |
| `canViewTeam` | HR/Admin/full — Freigabe & Krank-Erfassung für andere |
| Plan-Skizze `dg_time_absences` | Status `requested`/`approved`/`rejected`, `document_id` |

#### Datenmodell (Ziel)

**`dg_time_vacation_entitlements`** (Jahresanspruch)

| Feld | Regel |
|------|--------|
| contact_id, year | UNIQUE |
| days_entitled | Jahresanspruch (Dezimal ok, z. B. 30 / 27,5) |
| days_carried | Übertrag Vorjahr (Default 0) |
| note | optional |

**Rest:** `entitled + carried − genehmigte Urlaubstage (approved, type=vacation) im Jahr`.

**`dg_time_absences`**

| Feld | Regel |
|------|--------|
| contact_id | Mitarbeiter |
| type | `vacation` \| `sick` \| `other` |
| date_from, date_to | inklusiv; `from ≤ to` |
| days_count | berechnete Werktage oder Kalendertage — **Z4b entscheidet Default: Werktage Mo–Fr ohne Feiertagslogik zuerst** (Feiertage Soft später) |
| status | `requested` → `approved` \| `rejected` \| `cancelled` |
| reason | Pflicht bei Antrag/Ablehnung |
| document_ref | optional Attest (Pfad/ID Kontaktakte — keine neue Dokument-Engine) |
| decided_by, decided_at | bei Freigabe/Ablehnung |
| created_by, created_at | Audit |

Krankheit: Default-Status bei HR-Erfassung `approved`; MA-Selbstmeldung `requested` bis HR bestätigt (oder direkt `approved` wenn Spec-Flag — **Default: HR bestätigt**).

#### Rechte

| Aktion | Wer |
|--------|-----|
| Eigenen Urlaub beantragen | Stempel-Nutzer (`zeiterfassung` + canEdit) |
| Eigenen Antrag zurückziehen (`cancelled`) solange `requested` | Antragsteller |
| Freigeben / Ablehnen | `canViewTeam` |
| Krankmeldung für anderen + Attest zuordnen | `canViewTeam` |
| Anspruchstage pflegen | `canViewTeam` |
| Team-Abwesenheitskalender lesen | `canViewTeam`; eigene Einträge: Stempel-Nutzer |

#### Wirkung auf Soll/Ist (Z4e)

- Genehmigte Abwesenheit an Tag T: **Soll = 0** (schlägt Schicht/Stammdaten/Kalender für diesen Tag)
- Ist bleibt Stempel; Soft-Warnung „Abwesenheit genehmigt“ beim Stempeln
- Monatsblatt: Spalte/Hinweis Abwesenheitstyp
- **Nicht** in Z4: Entgeltfortzahlungs-Buchung, BUrlG-Rückstellung (Z5)

#### UI-Skizze (Seiten)

| Slug | Inhalt |
|------|--------|
| `zeiterfassung-urlaub` | Antrag + eigene Anträge + Restanspruch |
| `zeiterfassung-abwesenheit` (Team) | Freigabe-Liste + Krank erfassen + Monatskalender |

#### Serie Z4

1. **Z4a** Spec ✅  
2. **Z4b** Migration Entitlement + Absences + Repository ✅  
3. **Z4c** Urlaubsantrag + Freigabe-UI ✅  
4. **Z4d** Krankheit + Attest-Link + Team-Kalender ✅  
5. **Z4e** Soll=0 an genehmigten Tagen + Soft-Warnung Stempel ✅  

#### Abnahme Z4a

| Prüfung | Ergebnis |
|---------|----------|
| Eigenes Modell vs. Kalender-Absences | ✅ |
| Workflow + Rechte | ✅ |
| Anspruch in Tagen / Restformel | ✅ |
| Kein Code in diesem Chat | ✅ |

**Nicht:** Migration, UI, Rückstellung, Feiertagskalender DE.

### Z4b — Migration + Repository ✅ 2026-09-21

| Lieferobjekt | Ergebnis |
|--------------|----------|
| Migration | `093_time_absences.sql` — entitlements + absences |
| Repos | `TimeVacationEntitlementRepository`, `TimeAbsenceRepository` |
| Werktage | Mo–Fr, ohne Feiertage; Halbtage 0,5 |
| Rest | `entitled + carried − approved vacation days_count` (Jahr vollständig) |

**Nicht:** UI-Workflow (Z4c), Krankheit-Team-UI (Z4d), Soll=0 (Z4e).

### Z4c — Urlaub Antrag/Freigabe ✅ 2026-09-21

| Lieferobjekt | Ergebnis |
|--------------|----------|
| UI | `/app?page=zeiterfassung-urlaub` |
| Service | `TimeVacationService` (Antrag, Cancel, Approve/Reject, Anspruch) |
| MA | Restanspruch + Antrag + eigene Liste |
| HR | Offene Freigaben + Jahresanspruch pflegen |

**Nicht:** Krankheit (Z4d), Soll=0 (Z4e), Rückstellung.

### Z4d — Krankheit + Team-Kalender ✅ 2026-09-21

| Lieferobjekt | Ergebnis |
|--------------|----------|
| UI | `/app?page=zeiterfassung-abwesenheit` |
| Service | `TimeAbsenceService` |
| MA | Krankmeldung `requested` + Attest-Ref |
| HR | Erfassung `approved`, Bestätigung, Monatskalender U/K/S |

**Nicht:** Soll=0 (Z4e), Rückstellung (Z5).

### Z4e — Soll-Anbindung Abwesenheit ✅ 2026-09-21

| Lieferobjekt | Ergebnis |
|--------------|----------|
| `TimeScheduleService` | genehmigte Abwesenheit → Soll 0 (vor Schicht) |
| Stempel/Team/Monat | Soft-Hinweis + Typ-Label |
| Hard-Block | nein |

**Nicht:** Entgeltfortzahlung, Rückstellung (Z5).

### Chat-Vorlage Z4

```text
Scope: Zeiterfassung Z4a laut docs/ZEITERFASSUNG-PLAN.md § Betrieb Phase 4
Nur: Spec Urlaub/Krankheit (Modell, Rechte, Soll-Wirkung)
Kein Code, keine Migration, kein Deploy.
Nicht Z3-Code / Z5–Z6 neu einlesen.
```

Weitere: `Z4b` / `Z4c` / `Z4d` / `Z4e` analog.

---

## Betrieb Phase 5 — Rückstellungen (token-sparend)

> **Agent-Regel:** Pro Chat **ein** Unterpunkt (`z5a` …). Spec nur dieser Abschnitt.  
> **Kein** stilles Auto-Buchen ohne Nutzerbestätigung. **Kein** Deploy außer „deploy“.  
> Fachlich: **Steuerberater stimmt Konten/Methode ab**, bevor Live-Buchung (E5-ähnliche Vorsicht).  
> Abhängigkeiten: Resturlaub idealerweise aus **Z4**; bis dahin manuelle Tage-Eingabe erlaubt.

### Entscheid-Checkliste Z5

| # | Entscheidung | Default |
|---|--------------|---------|
| R1 | Urlaubsrückstellung | ✅ Pflicht-Gegenstand Z5 (HGB § 249 analog / handelsüblich) |
| R2 | Überstunden-Rückstellung | ✅ **optional** (Schalter); oft vertraglich anders gelöst |
| R3 | Buchung | ✅ Entwurf → Nutzer bestätigt → `ManualLedgerService` (keine stillen Posts) |
| R4 | Konten | ✅ **konfigurierbar** (SKR-Vorschlag, kein Hardcode als Wahrheit) |
| R5 | Stichtag | ✅ 31.12. des Geschäftsjahres (bzw. abweichendes WJ später) |
| R6 | Jahresabschluss | ✅ Checklisten-Punkt in `FiscalCloseService` (warn bis gebucht/als n.a. markiert) |

### Z5a — Spec Rückstellungen ✅ 2026-09-21

Nur Spezifikation — Code = **Z5b+**. Keine Buchungssätze erzeugen in Z5a.

#### Recht / Prüfbarkeit (Kurz)

| Thema | Anforderung im CRM |
|--------|-------------------|
| Urlaub | Offener Urlaubsanspruch zum Stichtag → Rückstellung; Berechnung nachvollziehbar (Tage × Tageskostensatz) |
| Überstunden | Optional; nur wenn betrieblich Rückstellung gebildet wird (nicht = ArbZG-Ausgleichskonto) |
| GoBD | Beleg/Protokoll: wer, wann, Parameter, Ergebnisbetrag; Storno nur mit Begründung |
| Steuerberater | Kontenrahmen (SKR 03/04), GuV vs. Bilanz, Sozialkosten-Zuschlag — **nicht** vom Agenten „final“ festnageln |

#### Berechnungsmodell (verbindlich für Z5b)

**Urlaubsrückstellung (pro MA, Jahr Y):**

1. `rest_days` = Anspruch+Übertrag − genehmigte Urlaubstage (Z4) **oder** manuell gepflegt  
2. `daily_cost` = konfigurierbar je MA oder Firmen-Default (Brutto-Äquivalent / 365 oder / Arbeitstage — **Methode wählbar**, Default: `/ 260` Arbeitstage-Näherung)  
3. optional `social_factor` (z. B. 1,20) — Default **1,00** bis Steuerberater setzt  
4. `amount = rest_days × daily_cost × social_factor`  
5. Summe über aktive Mitarbeiter = Buchungsbetrag

**Überstunden (optional):**

1. `ot_hours` = Saldo Überstundenkonto (Z2e Lots) / 60  
2. `hourly_cost` = daily_cost / 8 (oder eigener Satz)  
3. `amount = ot_hours × hourly_cost × social_factor`

#### Buchungsvorschlag (nur Vorlage — Konten editierbar)

| Seite | SKR-03-Vorschlag (Beispiel) | SKR-04-Vorschlag (Beispiel) |
|-------|----------------------------|----------------------------|
| Aufwand Urlaub | 6140 o. Ä. | 6300-Bereich Personal |
| Rückstellung Urlaub | 0970 / Verbindlichkeiten | entspr. Passiva |
| Aufwand ÜStd (opt.) | analog Personalaufwand | analog |
| Rückstellung ÜStd | analog | analog |

Exact numbers: **Settings**, Label „Vorschlag — mit Steuerberater prüfen“.

Buchungstext: `Urlaubsrückstellung {Y} / Stichtag {date} / Berechnung CRM`.

#### Daten / UI (Ziel)

| Baustein | Zweck |
|----------|--------|
| Settings Zeiterfassung/Personal | Konten, social_factor, Methode Tageskostensatz, ÜStd an/aus |
| `TimeProvisionService` (Name Z5b) | Preview-Tabelle MA × Betrag; Export CSV Prüfnachweis |
| Manual-Ledger-Batch | nach Bestätigung; source-Tag z. B. `time_provision` |
| `FiscalCloseService::checklist` | Item „Urlaubsrückstellung {Y}“ → ok/warn/offen |

#### Rechte

| Aktion | Wer |
|--------|-----|
| Preview / CSV | Buchhaltung-Zugang **oder** `canViewTeam` + Buchhaltungsrecht (Z5b: an `MenuRegistry::canAccessBuchhaltung` koppeln) |
| Buchung bestätigen | wie manuelle Buchungen heute |
| Konten ändern | Settings (Admin) |

#### Serie Z5

1. **Z5a** Spec ✅  
2. **Z5b** Settings + Berechnungs-Preview (+ CSV) ✅  
3. **Z5c** Buchungsentwurf → ManualLedger nach Bestätigung ✅  
4. **Z5d** Jahresabschluss-Checklisten-Punkt ✅  

#### Abnahme Z5a

| Prüfung | Ergebnis |
|---------|----------|
| Formel Urlaub + optional ÜStd | ✅ |
| Kein Auto-Buch ohne Confirm | ✅ |
| Konten nur Vorschlag/konfigurierbar | ✅ |
| Checkliste JA angebunden (Spec) | ✅ |
| Kein Code in diesem Chat | ✅ |

**Nicht:** Migration, echte Buchung, Lohn-Export (Z6), Z4-UI nachbauen.

### Z5b — Settings + Preview ✅ 2026-09-21

| Lieferobjekt | Ergebnis |
|--------------|----------|
| Settings | Konten, Tageskostensatz, Methode /260|/365, Sozialfaktor, ÜStd-Flag |
| Service | `TimeProvisionService` Preview + CSV |
| UI | `/app?page=zeiterfassung-rueckstellung` (Buchhaltung) |

**Nicht:** Ledger-Schreiben (Z5c ✅), Checkliste JA (Z5d).

### Z5c — Buchung ✅ 2026-09-21

| Lieferobjekt | Ergebnis |
|--------------|----------|
| Confirm-UI | Entwurf Soll/Haben auf Rückstellungs-Seite |
| Ledger | `ManualLedgerService::createBatch` source=`time_provision` |
| Schutz | Checkbox-Bestätigung · kein Doppel-Batch/Jahr · kein Cron |

**Nicht:** Checkliste JA (Z5d ✅).

### Z5d — Jahresabschluss-Checkliste ✅ 2026-09-21

| Lieferobjekt | Ergebnis |
|--------------|----------|
| Checklist-Item | `Urlaubsrückstellung {Y}` in `FiscalCloseService` |
| ok | Batch `time_provision` **oder** n. a. markiert |
| Default | **warn** (blockiert Abschluss nicht) |
| UI | n. a. setzen/aufheben auf Jahresabschluss-Seite |

**Nicht:** Hard-Block ohne Spec-Flag.

### Chat-Vorlage Z5

```text
Scope: Zeiterfassung Z5a laut docs/ZEITERFASSUNG-PLAN.md § Betrieb Phase 5
Nur: Spec Rückstellungen (Formel, Konten-Vorschlag, ManualLedger, Checkliste)
Kein Code, keine Buchung, kein Deploy.
Nicht Z6 / Z3–Z4-Code neu einlesen.
```

Weitere: `Z5b` / `Z5c` / `Z5d` analog.

---

## Betrieb Phase 6 — Lohn-Export (token-sparend)

> **Agent-Regel:** Pro Chat **ein** Unterpunkt (`z6a` …). Spec nur dieser Abschnitt.  
> **Keine eigene Lohnabrechnung** in Z6 — nur Export an externe Software.  
> **Kein** Deploy außer „deploy“. Datenquellen soft-degraden (fehlen Z4-Abwesenheiten → Spalten leer/0).

### Entscheid-Checkliste Z6

| # | Entscheidung | Default |
|---|--------------|---------|
| L1 | Erstes Format | ✅ **CSV-Standard** (Z6b) — robust, prüfbar |
| L2 | DATEV | ✅ **DATEV Lohn & Gehalt** Anschluss (Z6c) — EXTF/bekannte Felder, mit Berater prüfen |
| L3 | Lexoffice | ✅ optional **Z6d** nach DATEV |
| L4 | Zuschläge | ✅ **nicht** in Z6 (Nacht/So/Feiertag später) |
| L5 | PDF Lohnzettel | ✅ Ablegen `payroll_slip` in Kontaktakte (Z6d/e) — **kein** Generieren der Abrechnung |
| L6 | Protokoll | ✅ `dg_time_payroll_exports` (wer/wann/Monat/Format/Dateiname) |

### Z6a — Spec Lohn-Export ✅ 2026-09-21

Nur Spezifikation — Code = **Z6b+**.

#### Abgrenzung

| Ja in Z6 | Nein in Z6 |
|----------|------------|
| Monats-Export Ist/Soll/ÜStd/Urlaub/Krank (soweit Daten) | Eigene Netto-Lohnberechnung |
| CSV + DATEV-Lohn-Anschluss + optional Lexoffice | ELStAM, SV-Meldungen, Auszahlung |
| Mandant/Berater-Nr. in Settings | Stripe/Shop |
| Upload/Ablage fremder Lohn-PDF | PDF selbst erzeugen |

#### Datenquellen (pro MA, Monat YYYY-MM)

| Feld | Quelle (Priorität) |
|------|-------------------|
| Personalnummer / Login | Kontakt / EmployeeData |
| Name | Kontakt |
| Soll_Minuten | Summe `scheduled` (TimeMonth / WorkDays) |
| Ist_Minuten | Summe `worked` |
| Pause_Minuten | Summe `break` |
| Ueberstunden_Minuten | Summe `overtime` bzw. Lots-Bewegung |
| Urlaub_Tage | Z4 approved vacation overlapping month (0 bis Z4 live) |
| Krank_Tage | Z4 approved sick (0 bis Z4 live) |
| Korrektur_Minuten | Summe Z2e Deltas im Monat |

#### CSV-Standard (Z6b — verbindliches Minimal-Schema)

- UTF-8 BOM, Trenner `;`
- Kopfzeile fest (deutsche Bezeichner + `_Minuten` / `_Tage`)
- Eine Zeile pro Mitarbeiter; Summenzeile optional
- Dateiname: `lohn-zeiten-{YYYY-MM}-{domain}.csv`
- Inhalt prüfbar gegen Monatsblatt (Z2c)

#### DATEV Lohn (Z6c)

- Settings: Beraternummer, Mandantennummer, ggf. Personalnummer-Mapping
- Export erzeugt Datei im mit Steuerberater abgestimmten DATEV-Lohn-Importformat (Dokumentation/Feldliste in Code-Kommentar + Spec-Anhang bei Implementierung)
- **Kein** Raten undokumentierter Binärformate; im Zweifel CSV-Übergabe + DATEV-Import-Assistent laut Berater
- Wiederverwendung Muster: bestehende DATEV-Exporter (Buchhaltung) nur als Vorbild für Encoding/BOM/Protokoll — **nicht** Kontenblatt als Lohn missbrauchen

#### Lexoffice (Z6d, optional)

- Nur wenn Nutzer/Berater Format vorgibt; sonst zurückstellen
- Gleicher Monatsdatensatz wie CSV, anderes Mapping

#### Dokument `payroll_slip` (Z6d/e)

- Typ in EmployeeDocuments / Kontaktakte
- Nutzer lädt vom Steuerberater erhaltene PDF hoch (Monat + MA)
- Kein Auto-Download von DATEV-Cloud in Z6

#### Rechte & UI

| Aktion | Wer |
|--------|-----|
| Export starten | `canViewTeam` **und** Buchhaltungs- oder HR-Kontext (Z6b: `canViewTeam`; optional zusätzlich Buchhaltung) |
| Settings Mandant/Berater | Admin / Settings |
| PDF ablegen | wie Kontaktakte heute |

Seite: z. B. `/app?page=zeiterfassung-lohnexport` — Monat wählen, Vorschau, Download, Protokoll.

#### Serie Z6

1. **Z6a** Spec ✅  
2. **Z6b** CSV-Monats-Export + Protokoll-Tabelle ✅  
3. **Z6c** DATEV-Lohn-Anschluss (Settings + Datei) ✅  
4. **Z6d** optional Lexoffice + `payroll_slip`-Ablage ✅  

#### Abnahme Z6a

| Prüfung | Ergebnis |
|---------|----------|
| CSV zuerst, DATEV danach, Lexoffice optional | ✅ |
| Keine eigene Abrechnung | ✅ |
| Soft-Degrade ohne Z4 | ✅ |
| Kein Code in diesem Chat | ✅ |

**Nicht:** Migration, Export-Code, Zuschläge, ELSTER/Lohnsteuer.

### Z6b — CSV + Protokoll ✅ 2026-09-21

| Lieferobjekt | Ergebnis |
|--------------|----------|
| Migration | `094_time_payroll_exports.sql` |
| Service | `TimePayrollExportService` + Repository |
| UI | `/app?page=zeiterfassung-lohnexport` — Vorschau, CSV, Protokoll |
| Rechte | `canViewTeam` |

**Nicht:** DATEV-Felder (Z6c ✅), Lexoffice/PDF (Z6d ✅).

### Z6c — DATEV Lohn ✅ 2026-09-21

| Lieferobjekt | Ergebnis |
|--------------|----------|
| Settings | Berater/Mandant via `DatevExportSettings` (Kontenrahmen) |
| Datei | `TimePayrollDatevExporter` — CSV-Übergabe mit Metakopf (kein LODAS-Binär) |
| UI | Download „DATEV Lohn-Zeiten“ auf Lohn-Export-Seite |
| Mapping | Login bzw. `datev_personnel_number` |

**Nicht:** Lexoffice (Z6d ✅), PDF generieren (weiterhin nicht).

### Z6d — Lexoffice + PDF-Ablage ✅ 2026-09-21

| Lieferobjekt | Ergebnis |
|--------------|----------|
| Optional Mapping | `TimePayrollLexofficeExporter` — CSV-Übergabe (Stunden dezimal), Protokoll `lexoffice_lohn` |
| payroll_slip | Multi-Upload in Mitarbeiterdaten / Kontaktakte |
| UI | Download „Lexoffice Lohn-Zeiten“ + Hinweis PDF-Ablage |

**Nicht:** PDF generieren, Lexoffice-API, Netto-Lohn.

### Chat-Vorlage Z6

```text
Scope: Zeiterfassung Z6a laut docs/ZEITERFASSUNG-PLAN.md § Betrieb Phase 6
Nur: Spec Lohn-Export (CSV/DATEV/Lexoffice, Rechte, Abgrenzung)
Kein Code, keine Migration, kein Deploy.
Keine eigene Lohnabrechnung.
```

Weitere: `Z6b` / `Z6c` / `Z6d` analog.
