# Zeiterfassung & Personal — Umsetzungsplan

> **Stand:** 22.08.2026  
> Status: **Phase 1 implementiert** (Stempeluhr MVP) · Phase 2+ siehe unten  
> Verwandt: `EmployeeData`, `ContactFileStorage`, `CalendarWorkingHoursRepository`, Buchhaltung (Lohn-Export später)

---

## Ziel

Mitarbeiter können sich anmelden und unter **Zeiterfassung** ein-/ausstempeln. Führungskräfte und HR sehen Monats-/Jahresübersichten, Urlaub/Krankheit, Schichten und Zeitkonten. Daten werden für die **externe Lohnbuchhaltung** (DATEV, Lexoffice, …) exportiert — eigene Lohnabrechnung kommt später.

---

## Rechtliche / fachliche Leitplanken (Deutschland)

| Thema | Anforderung im System |
|--------|----------------------|
| **Arbeitszeitgesetz** | Pausenregeln (mind. 30 min ab 6 h, 45 min ab 9 h), max. Arbeitszeit, Ruhezeiten |
| **Geringfügig beschäftigt (Minijob)** | Flag am Mitarbeiter → **kein Überstunden-Zeitkonto**, Warnung bei Stempel über Soll |
| **Überstunden** | Nur wenn `overtime_allowed = 1`; sonst nur Anzeige/Warnung, keine Gutschrift auf Zeitkonto |
| **Urlaub** | Anspruch, Rest, Genehmigung, Rückstellungen (Buchhaltung Phase 2) |
| **Krankheit** | AU-Erfassung, Verknüpfung zu Attest-Dokumenten (bestehend: `medical_certificates`) |
| **Nachweis** | Unveränderliche Stempel-Historie (Audit), Korrekturen nur mit Begründung + Berechtigung |

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
- [ ] **Zeitkonto Überstunden** nur bei `overtime_allowed`
- [ ] Warnung bei Minijob: „Keine Überstunden erlaubt“
- [ ] Korrekturen mit Berechtigung + Audit

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

1. Kiosk-Tablet in der Werkstatt (PIN statt Login)?
2. GPS/Standort beim Stempeln nötig?
3. Welche Lohnsoftware zuerst (DATEV Lohn & Gehalt, Lexware, …)?
4. Soll-Arbeitszeit aus Terminkalender-Arbeitszeiten oder separat pflegen?
5. Zuschläge (Nacht/Sonntag/Feiertag) in Phase 1 oder später?
