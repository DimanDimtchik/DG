# Zeiterfassung & Personal — Umsetzungsplan

> **Stand:** 2026-09-21  
> Status: **Phase 1 ✅** · **Z2a Spec ✅** · offen Z2b–Z2e · Phase 3+ später  
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

- [ ] Schichtplan (Früh/Spät/Nacht oder frei definierbar)
- [ ] Zuordnung Mitarbeiter ↔ Schicht ↔ Datum
- [ ] Abweichung Soll (Schicht) vs. Ist (Stempel)

### Phase 4 — Urlaub & Krankheit

- [ ] Urlaubsantrag → Genehmigung Workflow
- [ ] Urlaubskonto (Tage/Minuten, Jahresanspruch)
- [ ] Krankmeldung, Verknüpfung Attest-Upload
- [ ] Kalenderansicht Abwesenheiten (Team)

### Phase 5 — Rückstellungen & Buchhaltung

- [ ] **Urlaubsrückstellung** (Buchungssätze, SKR-Konten — mit Steuerberater abstimmen)
- [ ] **Überstunden-Rückstellung** (optional)
- [ ] Anbindung an Jahresabschluss-Checkliste

### Phase 6 — Lohn-Export (ohne eigene Abrechnung)

- [ ] Exportformate: **DATEV Lohn**, **Lexoffice Lohn**, CSV-Standard
- [ ] Monatsdaten: Arbeitsstunden, Überstunden, Urlaub, Krankheit, Zuschläge (später)
- [ ] Dokumente: Lohnabrechnung PDF ablegen (`payroll_slip` Dokumenttyp)
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

**Serie Z2 (jetzt):**

1. **Z2a** Spec/Checkliste Soll-Quelle + Korrektur-Rechte ✅  
2. **Z2b** Soll-Arbeitszeiten anbinden (lesen aus Kalender/MA)  
3. **Z2c** Monatsansicht Soll/Ist/Diff + CSV-Export  
4. **Z2d** ArbZG-Warnungen: Ruhezeit 11 h, max. 10 h/Tag, Hinweis Wochendurchschnitt  
5. **Z2e** Korrektur-UI + Audit; Überstundenkonto Abbau buchen  

**Spätere Serien (eigene Chat-Ketten, nicht mischen):**

| Serie | Inhalt | Einstieg |
|-------|--------|----------|
| **Z3** | Schichten | `z3a` nach Z2e ✅ |
| **Z4** | Urlaub & Krankheit | `z4a` |
| **Z5** | Rückstellungen Buchhaltung | `z5a` (+ Steuerberater) |
| **Z6** | Lohn-Export DATEV/CSV | `z6a` |

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

### Z2b — Soll anbinden

| Lieferobjekt | Erwartung |
|--------------|-----------|
| Service | `scheduledMinutesFor(contactId, date)` aus bestehender Quelle |
| Fallback | fehlendes Soll → 0 + Hinweis, kein Fake-8h |
| Nicht | Schichtplan, Urlaub |

### Z2c — Monatsansicht + CSV

| Lieferobjekt | Erwartung |
|--------------|-----------|
| UI | Monat: Soll, Ist, Diff, Überstunden (exist. `overtime_minutes`) |
| CSV | eine Datei/Monat, GoBD-tauglich nachvollziehbar |
| Nicht | DATEV-Lohn, Zuschläge |

### Z2d — ArbZG-Warnungen

| Lieferobjekt | Erwartung |
|--------------|-----------|
| Prüfungen | <11 h Ruhe; >10 h Tag; Soft-Hinweis Ø-Woche |
| UI | Warnung Stempel/Team; **kein** hard block ohne Spec-Flag |
| Nicht | Zuschläge, Erinnerungs-E-Mail neu (48h-Ø schon ✅) |

### Z2e — Korrektur + Zeitkonto

| Lieferobjekt | Erwartung |
|--------------|-----------|
| Korrektur | Begründung + Berechtigung; Stempel-Historie unverändert + Korrektur-Satz |
| Konto | Überstunden abbuchen (Minuten), Minijob gesperrt |
| Nicht | Urlaubsantrag, Lohn-Export |

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
