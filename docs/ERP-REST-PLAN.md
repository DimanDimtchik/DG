# DG CRM — ERP-Rest (tokenarm)

> **Eine Chat-Session = eine Mini-ID** (nach Zerlegung).  
> Fachquelle: [`ERP-ERWEITERUNG-LASTENHEFT-V2.md`](ERP-ERWEITERUNG-LASTENHEFT-V2.md) · Roadmap: [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) · Lohn: [`LOHNABRECHNUNG-IMPLEMENTIERUNG.md`](LOHNABRECHNUNG-IMPLEMENTIERUNG.md)

Stand: **2026-11-08** · Branch-Empfehlung: `cursor/erp-…`

---

## Warum tokenarm (Free-/Hobby-Plan)

| Regel | Bedeutung |
|-------|-----------|
| **Kein Explorer-Chat** | Kein „lies ganz Rezeptur/Lohn/FiBu“ — nur Datei(en) der Mini-ID |
| **Kein Monolith** | Kein neues paralleles ERP-Modul; Anker: Rezeptur / FiBu / Akademie / Lohn |
| **Kein Live-Versand** | ITSG/ELSTER/Baulohn-Live = **Mensch + Gates** (Hetzner, G3, Zertifikate) |
| **1 ID = 1 Commit-fähig** | Nach Schritt: Lint/Selftest der genannten Datei |
| **Credentials nie committen** | Zertifikate / Portal-IDs nur Instanz |

**Chat-Prompt-Vorlage (kopieren):**  
`Nur Mini-ID E2d laut docs/ERP-REST-PLAN.md. Nur views/modules/rezeptur-maschine-form.php (Tab Wissen). Katalog-Klassen. Kein Refactor.`

**Nicht im Agent-Chat:** Baulohn vor Standardlohn, ITSG-Versand ohne Gate, FiBu-Buchungsroboter, PostgreSQL/UUID-Schema, Amazon/UI-CSS (eigene Pläne).

---

## Token-Stufen

| Symbol | Wann | Typisch |
|--------|------|---------|
| 🟢 | Doc, Migration-Skizze, 1 View-Hinweis, Guard | Free |
| 🟡 | 1 Service + 1 POST/View, klarer Diff | Kurz-Agent |
| 🔴 | Live-Meldung, Pilotnachweis, Zertifikatspfad | Mensch / nach Gate |

---

## Ist-Stand (Inventar 2026-11-08)

| Block aus Lastenheft V2 | Stand | Hinweis |
|-------------------------|-------|---------|
| Zeiterfassung / Lohn-Export / fremde Lohnabrechnungen | ✅ | nicht neu bauen |
| Maschinen / Work Centers / Rezeptur-Routing / Snapshots | ✅ | nicht neu bauen |
| **Maschinenblock M0–M5** (Kapazität, Zusatz, Wartung, Belegung, Kalk) | ✅ | gemergt 2026-10-29 · [`MASCHINENBLOCK-M0A-DATENMODELL.md`](MASCHINENBLOCK-M0A-DATENMODELL.md) |
| Akademie-Videos (Kurse/Module/VTT) | ✅ | Anker für Wissensbrücke |
| **Lohn D0-A Code** L0–L10c | ✅ | G4 ✅ · LB0a ✅ · **G3 Pilot offen** |
| Maschinen-Gemeinkosten aus FiBu (nicht buchungswirksam) | ❌ | nächster baubarer Code-Block |
| Maschinenwissen ↔ Handbuch/Akademie (Fehlercodes) | ❌ | klein, parallel möglich |
| Lohn D0-B ITSG / ELStAM / euBP live | 🔴 | nach **G3** + Hetzner/ERiC |
| Baulohn / SOKA / Saison-KUG (D0-C) | 🔴 | nach Standardlohn (+ ideal B) |
| Zertifizierungen (ITSG, ELSTER Org, …) | 🔴 | Meldewege, keine „allgemeine Finanzamt-Freigabe“ |

---

## Parent-Übersicht

| ID | Thema | Priorität | Status | Token |
|----|--------|-----------|--------|-------|
| E0 | Inventar / dieser Plan | — | [x] 2026-11-08 | 🟢 |
| E1 | **Maschinen-Gemeinkosten** (Kontengruppen → €/Maschinenstunde → Snapshot) | 1 | [x] E1a–E1f | 🟡 |
| E2 | **Maschinenwissen** (Tabelle + Tab Wissen/Störungen + Fehlercode-Suche) | 2 | [~] E2a–E2c ✅; ab E2d | 🟡 |
| E3 | **Lohn-Gates** (G3-Nachweis, dann LB1+) | Mensch/Pilot | [~] G3 offen | 🔴 |
| E4 | **Zertifizierungen / Meldewege** | nach Hetzner | [ ] nicht zerlegt | 🔴 |
| E5 | **Baulohn D0-C** | nach G3 (+ ideal B) | [ ] nicht zerlegt | 🔴 |

### Leitplanken (aus Lastenheft)

- Gemeinkosten zuerst **nur Kalkulationsvorschlag** — keine automatischen FiBu-Buchungen.  
- Keine rückwirkende Änderung gebuchter Produktionsläufe / Snapshots.  
- Maschinenwissen: MariaDB (`dg_machine_knowledge_links`-Skizze im Lastenheft), keine UUID-Arrays.  
- Endnutzer-Texte: keine internen Gate-/Plan-Codes in der UI.  
- Anker: `ChartAccountRepository` / Hauptbuch lesen · `RecipeCostService::snapshotPayload` · Views `rezeptur-maschine*` · Akademie-Module.

---

## Abhackplan (Mini-IDs)

Status: `[ ]` offen · `[~]` angefangen · `[x]` erledigt  
Nächste freie Migration bei Start prüfen (`ls database/migrations/` — Stand Inventar: **142** vergeben → **143+**).

### E0 — Doc / Inventar (🟢)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| E0a | Dieser Rest-Plan + Verweis in Lastenheft / `TODOS.md` | 🟢 | [x] 2026-11-08 |
| E0b | Mini-ID-Zerlegung E1/E2 (+ Prompt-Zeilen) | 🟢 | [x] 2026-11-08 |

---

### E1 — Maschinen-Gemeinkosten (🟡) — **zuerst Code**

Nicht buchungswirksam. Liest FiBu/Konten + Maschinenlaufzeit; schreibt Settings + Snapshot-Felder.

#### Spec-Kurz (E1a) — verbindlich für E1b–E1f

**Migrationsentscheidung:** **keine** SQL-Migration für E1.  
Konfiguration analog `RecipeCostSettings` / `stock_purchase` nur in **`SettingsStore`**.  
Nächste freie Migrationsnummer **143+** bleibt für **E2a** (`machine_knowledge_links`) — nicht für Overhead blockieren.

| Feld | Wert |
|------|------|
| `SettingsStore`-Key | `machine_overhead` |
| Klasse (E1c/E1e) | `MachineOverheadSettings` (+ `MachineOverheadService` für Berechnung) unter `src/Recipe/` |
| Buchungswirkung | **keine** — nur lesen aus `dg_ledger_postings` / Work-Center-Belegung |

**Settings-Payload (`machine_overhead`):**

| Key | Typ | Default | Bedeutung |
|-----|-----|---------|-----------|
| `enabled` | bool | `false` | Aufschlag in Kalkulation nutzen |
| `period_mode` | string | `fiscal_year` | `fiscal_year` \| `date_range` |
| `fiscal_year` | int | aktuelles GJ | bei `fiscal_year` |
| `date_from` / `date_to` | `Y-m-d` \| `''` | leer | bei `date_range` (beide Pflicht) |
| `account_numbers` | `list<string>` | `[]` | SKR-Kontonummern der Gemeinkostengruppe (z. B. Raum, IT, Verwaltung) |
| `manual_machine_hours` | float \| `null` | `null` | Fallback, wenn keine Laufzeit aus Belegung; **Pflicht-Hinweis** wenn Divisor 0 |
| `label` | string | `''` | optionale Bezeichnung der Gruppe (UI) |

**Zeitraum:**  
- `fiscal_year` → Bewegungen des GJ (wie `LedgerRepository::accountOverview` ohne engere Periode).  
- `date_range` → `posting_date BETWEEN date_from AND date_to` im gewählten GJ (`fiscal_year` = Jahr von `date_from`).  
Saldenvortrag (`opening_balance`) **nicht** in die Gemeinkosten-Summe.

**Konten / Kostensumme:**  
Für jedes Konto in `account_numbers`: Perioden-Bewegung **Soll − Haben** (Aufwand üblich positiv). Summe = `total_overhead_cost` (EUR, 2 Nachkommastellen). Unbekannte Kontonummern → Warning, nicht hart abbrechen.

**Maschinenlaufzeit (Divisor):**  
1. Primär: Summe Belegungs-/Laufstunden aktiver Work Centers im gleichen Zeitraum (Maschinenblock-Buchungen; Details in E1c).  
2. Wenn Summe &lt; 0,0005 h und `manual_machine_hours` &gt; 0 → Manualwert.  
3. Sonst: `overhead_per_machine_hour = null`, Warning „Keine Maschinenstunden — Aufschlag nicht berechenbar“.

**Formel:**  
`overhead_per_machine_hour = total_overhead_cost / machine_hours` (auf 4 Nachkommastellen intern, Anzeige 2–4).

**Snapshot (E1d — nur speichern bei neuem Snapshot, nie rückwirkend):**  
- `inputs.overhead`: Kopie der wirksamen Settings + `total_overhead_cost`, `machine_hours`, `overhead_per_machine_hour`, `computed_at`  
- `result.overhead_per_machine_hour` + optional `result.k_overhead_suggested` (Aufschlag × Maschinenzeit der Kalkulation)  
UI-Hinweis: „Vorschlag, nicht gebucht“.

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| E1a | Spec-Kurz: Settings-Keys + Zeitraum + Kontenliste + Migrationsentscheidung | 🟢 | [x] 2026-11-08 SettingsStore `machine_overhead`, **keine** Migration |
| E1b | Plan-Abschluss E1b: bestätigen „nur SettingsStore“ (kein SQL); Stub-Klasse erst E1c — **kein** `NNN_machine_overhead_settings.sql` | 🟢 | [x] 2026-11-08 bestätigt: kein SQL; 143+ frei für E2a |
| E1c | Neu: `MachineOverheadService` (+ Settings-Klasse) unter `src/Recipe/` — Formel laut Spec; Return u. a. `overhead_per_machine_hour` | 🟡 | [x] 2026-11-08 MachineOverheadSettings + Service + Autoload |
| E1d | Anbindung `RecipeCostService` / `snapshotPayload`: Felder laut Spec; **alte Snapshots unverändert** | 🟡 | [x] 2026-11-08 calculate + snapshotPayload (Vorschlag, self_cost unverändert) |
| E1e | UI: Einstellungen Konten/Zeitraum (bei Rezeptur-Kostensätzen oder klarer Nachbar) + Anzeige €/h; Katalog-Klassen; kein FiBu-Buchen-Button | 🟡 | [x] 2026-11-08 rezeptur-maschinen + Form-Hinweis + API |
| E1f | Kurz-Selftest `bin/machine-overhead-selftest.php` + Plan-Haken | 🟢 | [x] 2026-11-08 `bin/machine-overhead-selftest.php` |

**E1b bestätigt (2026-11-08):** kein `*machine_overhead*.sql` unter `database/migrations/`; letzte Nummer weiterhin **142**; **143+** für E2a. Persistenz nur `SettingsStore` Key `machine_overhead` (E1a). Stub/Klassen kamen mit E1c.

**Prompt E1d:**  
`Nur Mini-ID E1d laut docs/ERP-REST-PLAN.md. Nur RecipeCostService / Snapshot-Pfad: overhead_per_machine_hour in Payload. Alte Snapshots nicht ändern. Kein UI.`

**Prompt E1e:**  
`Nur Mini-ID E1e laut docs/ERP-REST-PLAN.md. Nur genannte View(s) + POST-Zweig wenn nötig. Katalog-Klassen. Kein Refactor. Kein automatisches Buchen.`

**Prompt E1f:**  
`Nur Mini-ID E1f laut docs/ERP-REST-PLAN.md. Nur bin/machine-overhead-selftest.php + Plan-Haken. Kein Live-FiBu-Rewrite.`

---

### E2 — Maschinenwissen / Akademie (🟡)

Schema-Skizze: Lastenheft §6 `dg_machine_knowledge_links`.

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| E2a | Migration `NNN_machine_knowledge_links.sql` (MariaDB, FKs work_center / academy_module laut Skizze) | 🟢 | [x] 2026-11-08 `143_machine_knowledge_links.sql` |
| E2b | `MachineKnowledgeRepository` (+ Autoload): CRUD, Suche nach Fehlercode in `error_codes_json` | 🟡 | [x] 2026-11-08 |
| E2c | POST/Handler im bestehenden Rezeptur-Maschinen-Block (`index.php` nur Zweig) — gleicher Gate wie Maschinen-CRUD | 🟡 | [x] 2026-11-08 machine_knowledge_save/deactivate |
| E2d | UI Tab „Wissen & Störungen“ in `views/modules/rezeptur-maschine-form.php` (+ Liste/Suche); Katalog-Buttons; PDF-Pfad nur `storage/` | 🟡 | [ ] |
| E2e | Fehlercode-Schnellsuche (Maschine oder zentrale kleine Suche) + Hinweis Akademie-Clip-Regeln (Doc/UI-Lead, keine Plan-Codes) | 🟢 | [ ] |
| E2f | Kurz-Selftest oder Plan-Checkliste M-Wissen + `TODOS` ERP-Zeile anpassen | 🟢 | [ ] |

**Prompt E2a:**  
`Nur Mini-ID E2a laut docs/ERP-REST-PLAN.md. Nur neue Migration database/migrations/ (Nummer prüfen). Schema wie Lastenheft §6 MariaDB. Kein PHP.`

**Prompt E2b:**  
`Nur Mini-ID E2b laut docs/ERP-REST-PLAN.md. Nur neue Datei src/Recipe/MachineKnowledgeRepository.php (+ Autoload). Kein View.`

**Prompt E2c:**  
`Nur Mini-ID E2c laut docs/ERP-REST-PLAN.md. Nur POST-Zweig im bestehenden Rezeptur-Maschinen-Block in index.php. CSRF + gleicher Gate. Kein Refactor.`

**Prompt E2d:**  
`Nur Mini-ID E2d laut docs/ERP-REST-PLAN.md. Nur views/modules/rezeptur-maschine-form.php (Tab Wissen). Katalog-Klassen. Kein Refactor.`

**Prompt E2e:**  
`Nur Mini-ID E2e laut docs/ERP-REST-PLAN.md. Fehlercode-Suche UI laut Plan. Nur genannte View. Kein Refactor.`

**Prompt E2f:**  
`Nur Mini-ID E2f laut docs/ERP-REST-PLAN.md. Selftest und/oder TODOS/Plan-Haken. Kein Live-Upload-E2E nötig.`

---

### E3 — Lohn-Gates (🔴 Mensch — keine Code-Mini-IDs hier)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| E3a | G3-Nachweis: 2 Pilotmonate L5–L9 auf Dev-Instanz · Tabelle in `LOHNABRECHNUNG-IMPLEMENTIERUNG.md` | 🔴 | [ ] |
| E3b | Nach G3: `lb1a` Spec-Chat (nicht dieser Plan) | 🔴 | blockiert bis E3a |

---

### E4 / E5 — nicht zerlegt

Erst nach Hetzner (E4) bzw. G3+ (E5) eigene Mini-Pläne anlegen — **kein** Free-Chat „Baulohn anfangen“.

---

## Empfohlene Free-Plan-Reihenfolge

1. **E1a → E1b → E1c → E1d → E1e → E1f** (Gemeinkosten)  
2. **E2a → E2b → E2c → E2d → E2e → E2f** (Wissen; E2a kann parallel nach E1b starten)  
3. Parallel Mensch: **E3a** G3-Pilot  
4. **E4 / E5** erst nach Gates  

**Parallel Mensch (🔴):** Amazon Onboarding · Hetzner/ERiC · G3 zwei Testmonate.

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`ERP-ERWEITERUNG-LASTENHEFT-V2.md`](ERP-ERWEITERUNG-LASTENHEFT-V2.md) | Fach / Lastenheft-Einordnung |
| [`MASCHINENBLOCK-M0A-DATENMODELL.md`](MASCHINENBLOCK-M0A-DATENMODELL.md) | M0–M5 erledigt |
| [`LOHNABRECHNUNG-IMPLEMENTIERUNG.md`](LOHNABRECHNUNG-IMPLEMENTIERUNG.md) | Lohn L* / Gates G* |
| [`AMAZON-BUSINESS-REST-PLAN.md`](AMAZON-BUSINESS-REST-PLAN.md) | Muster tokenarmer Mini-IDs |
| [`UI-CSS-REST-PLAN.md`](UI-CSS-REST-PLAN.md) | Muster tokenarmer Mini-IDs |
