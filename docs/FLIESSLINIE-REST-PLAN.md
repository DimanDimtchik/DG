# Fließlinie / Puffer-Kette — tokenarm

> **Eine Chat-Session = eine Mini-ID.**  
> Ziel: Mehrere Maschinen als **Verarbeitungslinie** mit **Zwischenpuffern** abbilden — Blocking, Starving, Materialmangel, Engpass.  
> Aufbau auf Maschinenblock M0–M5 · **kein** Shopfloor-MES / keine SPS-Anbindung.

Stand: **2026-11-08** · Katalog: [`UI-KLASSENKATALOG.md`](UI-KLASSENKATALOG.md) · Anker: [`MASCHINENBLOCK-M0A-DATENMODELL.md`](MASCHINENBLOCK-M0A-DATENMODELL.md) · ERP: [`ERP-ERWEITERUNG-LASTENHEFT-V2.md`](ERP-ERWEITERUNG-LASTENHEFT-V2.md)

---

## Fachziel (verbindlich)

| Regel | Bedeutung |
|-------|-----------|
| **Linie** | Geordnete Kette von Work Centers (Maschinen) einer Instanz |
| **Puffer** | Endlicher Zwischenbestand zwischen Station *i* und *i+1* (Stück oder kg) |
| **Blocking** | Puffer **voll** → Vorgängermaschine darf nicht weiterlaufen |
| **Starving** | Puffer **leer** → Folgemaschine darf nicht starten/weiterlaufen |
| **Rohware** | Fehlendes Eingangsmaterial an Station 1 (oder BOM-Position) → **Linie stoppt** |
| **Engpass** | Station mit geringstem nachhaltigen Durchsatz begrenzt die Linie |
| **Störung** | Wartung/Ausfall einer Station wirkt wie dauerhaft blockierend/starving für Nachbarn |
| **Eigentum** | Stammdaten nur aus **Instanz-DB** — kein Tenant-Hardcode |

**Produkt-Stufe 1 (dieses Plan):** Stammdaten + Regeln + **Offline-Simulation / Planungsstatus** (was wäre, wenn …).  
**Nicht Stufe 1:** Echtzeit-PLC, Sensorik, automatischer Maschinenstopp vor Ort.

---

## Fachbegriffe (Kurz — Lernbuch-Anschluss)

| Dein Bild | Fachwort |
|-----------|----------|
| Puffer hinten voll → davor steht | **Blocking** |
| Puffer davor leer → Folge steht | **Starving** |
| Eine Maschine begrenzt alles | **Engpass** (Bottleneck / Theory of Constraints) |
| Zwischenmenge zwischen Stationen | **WIP-Puffer** (Work in Process) |
| Wer darf produzieren | **Push** vs. **Pull** (z. B. Kanban: nur wenn Puffer Platz hat) |
| Ungleiche Takte | **Linienausgleich** (Line Balancing) |

---

## Ist (Anker — bereits vorhanden)

| Baustein | Stand | Nutzen für Fließlinie |
|----------|--------|------------------------|
| `dg_work_centers` | ✅ | Stationen der Linie |
| `dg_recipe_routing` (`step_order`) | ✅ | Rezept-Arbeitsplan — **nicht** gleich Linien-Stammdatum |
| Kapazitätsprofil Input/Output/h | ✅ M0–M1 | Durchsatz-Schätzung je Station |
| Belegungsfenster / Kalender | ✅ M3/M4 | geplante Belegung; noch **ohne** Kettenlogik |
| Wartung Plan/Log | ✅ M2 | Störungs-/Ausfall-Hinweis |
| BOM / Lager | ✅ | Rohware / Hilfsstoffe — Bestand lesen später |
| Linien-Entity + Puffer + Blocking/Starving | ❌ | **dieser Plan** |

---

## Speichermodell (**festgelegt FL1a** — 2026-11-08)

| Entscheidung | Festlegung |
|--------------|------------|
| Linie vs. Routing | **Getrennt:** `dg_recipe_routing` = Kalkulations-Arbeitsplan; Linie = physische Betriebs-Kette |
| Tabellen-Schnitt | **FL1b:** Linie + Stationen · **FL2a:** Puffer (eigene Migration) |
| Löschen | Linie: Soft (`is_active=0`) · Stationen: Ersatz durch `saveStations` (Rewrite) · **kein** Hard-DELETE der Linie ohne Flag · WC: `ON DELETE RESTRICT` |
| Gleiche Maschine 2× | **Nein** — Unique `(line_id, work_center_id)` |
| Reihenfolge | Unique `(line_id, station_order)`, lückenlos 1…n im Store erzwingen |
| WIP-Mengen | `current_qty` nur Simulation/manuell — **keine** FiBu-/Lagerbuchung in FL1/FL2 |
| Migration-Nummer | **FL1b:** `156_production_lines.sql` · **FL2a:** `157_production_line_buffers.sql` (Stand 2026-11-08; Nummern nach Kasse 155+). |

### 1. `dg_production_lines` (FL1b)

| Feld | Typ / Regel |
|------|-------------|
| `id` | PK INT UNSIGNED AI |
| `name` | VARCHAR(191) NOT NULL |
| `code` | VARCHAR(64) NOT NULL DEFAULT '' (optional Kurzcode, kein Unique-Zwang Instanz-weit zuerst) |
| `recipe_id` | NULL → `dg_recipes(id)` **ON DELETE SET NULL** (optional Default-Rezept) |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 |
| `notes` | VARCHAR(1000) NOT NULL DEFAULT '' |
| `created_at` / `updated_at` | DATETIME |

Index: `idx_production_lines_active (is_active)`.

### 2. `dg_production_line_stations` (FL1b)

| Feld | Typ / Regel |
|------|-------------|
| `id` | PK |
| `line_id` | FK → `dg_production_lines(id)` **ON DELETE CASCADE** |
| `work_center_id` | FK → `dg_work_centers(id)` **ON DELETE RESTRICT** |
| `station_order` | INT UNSIGNED NOT NULL (≥ 1) |
| `label` | VARCHAR(191) NOT NULL DEFAULT '' (Anzeige; leer → WC-Name in UI) |
| `created_at` / `updated_at` | DATETIME |

Constraints: **UNIQUE** `(line_id, station_order)` · **UNIQUE** `(line_id, work_center_id)`.

### 3. `dg_production_line_buffers` (**FL2a Migration** `157_production_line_buffers.sql`)

Puffer **nach** Station *i* (zwischen *i* und *i+1*). Letzte Station: **kein** Puffer-Datensatz.

| Feld | Typ / Regel |
|------|-------------|
| `id` | PK |
| `line_id` | FK → lines CASCADE (Redundanz für Queries) |
| `after_station_id` | FK → `dg_production_line_stations(id)` **ON DELETE CASCADE** · **UNIQUE** |
| `capacity_qty` | DECIMAL(14,4) NOT NULL · **> 0** (App-Check) |
| `min_qty` | DECIMAL(14,4) NOT NULL DEFAULT 0 · Starving unter/gleich diesem Wert |
| `unit` | VARCHAR(32) NOT NULL DEFAULT 'Stk' |
| `current_qty` | DECIMAL(14,4) NOT NULL DEFAULT 0 · nur Sim/manuell |
| `updated_at` | DATETIME |

### Stopp-Logik (verbindlich FL3a → [`FLIESSLINIE-REGELN.md`](FLIESSLINIE-REGELN.md))

- Station *i* **blocked**, wenn Puffer nach *i*: `current_qty >= capacity_qty`  
- Station *i+1* **starved**, wenn Puffer davor: `current_qty <= min_qty`  
- Priorität Station: `down` > `blocked` > `starved` > `running`  
- Linie: `stopped_material` (FL5) / `stopped` / `degraded` / `constrained` / `running` — Details im Regeln-Doc

---

## Recht / Compliance (Kurz)

| Thema | Für dieses Feature |
|-------|---------------------|
| GoBD / Bestand | WIP-Puffer-Mengen sind betrieblicher Zwischenbestand — bei bewertungsrelevanten Mengen später Lager-/Inventur-Anschluss, **nicht** in FL1 buchen |
| Prüfbarkeit | Simulationsläufe / Statuswechsel protokollieren (wer, wann, Annahme) |
| Kein Ersatz für SPS | CRM plant und erklärt — Maschinensteuerung bleibt vor Ort |

---

## Warum tokenarm

| Regel | Bedeutung |
|-------|-----------|
| **1 ID = 1 kleiner Diff** | Doc **oder** Migration **oder** Store **oder** eine UI |
| **Kein MES-Monolith** | Keine Plantafel, keine PLC, kein zweites ERP |
| **Kein Tenant-Hardcode** | Instanz-DB only |
| **Katalog-UI** | [`UI-KLASSENKATALOG.md`](UI-KLASSENKATALOG.md) |
| **Guard** | Views: `php bin/ui-form-spacing-selftest.php` |

**Chat-Prompt:**  
`Nur Mini-ID FL8a laut docs/FLIESSLINIE-REST-PLAN.md. Backlog-Notiz Scrap optional. Kein MES.`

---

## Token-Stufen

| Symbol | Wann |
|--------|------|
| 🟢 | Doc, Entscheidung, Hinweistext |
| 🟡 | Migration / kleines PHP + 1 View |
| 🔴 | Manueller Smoke Dev / Fachabnahme |

---

## Parent

| ID | Thema | Status | Token |
|----|--------|--------|-------|
| FL0 | Plan / Zielbild / Begriffe | [x] FL0a–b ✅ | 🟢 |
| FL1 | Linie + Stationen (Stammdaten) | [x] FL1a–d ✅ | 🟡 |
| FL2 | Puffer zwischen Stationen | [x] FL2a–c ✅ | 🟡 |
| FL3 | Blocking / Starving-Engine (Offline) | [x] FL3a–c ✅ | 🟡 |
| FL4 | Engpass / Takt-Hinweis | [x] FL4a–b ✅ | 🟢🟡 |
| FL5 | Materialmangel (Rohware + Hilfsstoff) | [x] FL5a–c ✅ | 🟡 |
| FL6 | Störung / Wartung propagiert | [x] FL6a–c ✅ | 🟡 |
| FL7 | UI Linie + Status | [x] FL7a–c ✅ | 🟡🔴 |
| FL8 | Verfeinerungen (optional) | [ ] | 🟢 |
| FL9 | Hinweise + Plan-Haken | [ ] | 🟢 |

---

## Mini-IDs

### FL0 — Plan

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| FL0a | Dieser Plan: Zielbild Blocking/Starving/Rohware/Engpass | 🟢 | [x] 2026-11-08 |
| FL0b | Verweis in `TODOS.md` + Querverweis ERP/Maschinenblock | 🟢 | [x] 2026-11-08 |

### FL1 — Linie & Stationen

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| FL1a | Speichermodell final (Tabellen/FKs) in diesem Plan bestätigen · Migration-Nummer prüfen | 🟢 | [x] 2026-11-08 Modell festgelegt · nächste frei ≈ **145** (nach 142–144) |
| FL1b | Migration: `dg_production_lines` + `dg_production_line_stations` · Unique `(line_id, station_order)` · WC RESTRICT | 🟡 | [x] 2026-11-08 `156_production_lines.sql` · Selftest |
| FL1c | Store: `listLines`, `getLineWithStations`, `saveStations` (Reihenfolge) · **kein** Hard-DELETE Historie ohne Flag | 🟡 | [x] 2026-11-08 `ProductionLineStore` + Autoload · Soft `deactivateLine` |
| FL1d | Selftest: 3 Stationen speichern → Reihenfolge stabil | 🟡 | [x] 2026-11-08 `bin/production-line-store-selftest.php` SQLite |

### FL2 — Puffer

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| FL2a | Migration: `dg_production_line_buffers` nach Station (capacity, unit, optional current) | 🟡 | [x] 2026-11-08 `157_production_line_buffers.sql` · Selftest |
| FL2b | Store: Puffer je Lücke Station→Station; Kapazität > 0 Pflicht | 🟡 | [x] 2026-11-08 `listBuffers`/`saveBuffers`/`setBufferCurrentQty` · Snapshot bei Station-Rewrite |
| FL2c | Selftest: Puffer voll/leer-Grenzen setzen | 🟡 | [x] 2026-11-08 `bin/production-line-buffers-store-selftest.php` SQLite |

### FL3 — Blocking / Starving (Offline-Engine)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| FL3a | Doc: Zustandsregeln Blocking/Starving/Linienstopp ( Pseudocode in Plan oder `docs/FLIESSLINIE-REGELN.md` ) | 🟢 | [x] 2026-11-08 [`FLIESSLINIE-REGELN.md`](FLIESSLINIE-REGELN.md) |
| FL3b | Service: aus Stationen + Pufferständen Status je Station berechnen | 🟡 | [x] 2026-11-08 `ProductionLineStatusService` · Autoload |
| FL3c | Selftest: Puffer voll → Vorgänger blocked; Puffer leer → Nachfolger starved | 🟡 | [x] 2026-11-08 `bin/production-line-status-selftest.php` |

**Prompt FL3b:**  
`Nur Mini-ID FL3b laut docs/FLIESSLINIE-REST-PLAN.md. Nur Status-Service. Kein UI. Kein PLC.`

### FL4 — Engpass / Takt

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| FL4a | Engpass aus Kapazitätsprofilen (Output/h) der Linien-Stationen ableiten · Hinweistext | 🟢🟡 | [x] 2026-11-08 `resolveBottleneck` + Hint · Selftest |
| FL4b | Optional: grober Linien-Durchsatz = min(Station-Durchsätze) unter Puffer-Annahmen | 🟡 | [x] 2026-11-08 `line_throughput` · Selftest |

### FL5 — Materialmangel

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| FL5a | Regel: fehlende Rohware (Eingang Station 1 / BOM) → Linie `stopped_material` | 🟢 | [x] 2026-11-08 Station1 starved + `raw_material` · Selftest |
| FL5b | Optional Hilfsstoff mitten in der Linie (Zusatzmaterial an Station *k*) → ab *k* starved/stop | 🟡 | [x] 2026-11-08 `missing_aux_from_station_order` · Selftest |
| FL5c | Anbindung Lagerbestand **nur lesen** (kein Auto-Buchen) · Selftest mit Fixture | 🟡 | [x] 2026-11-08 `ProductionLineMaterialStockGate` · Fixture-Selftest |

### FL6 — Störung / Wartung

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| FL6a | Offene Wartung / manuelles Stör-Flag an Station → Station `down` | 🟡 | [x] 2026-11-08 `ProductionLineStationDownGate` · Selftest |
| FL6b | Propagation: down bei *i* → Puffer davor füllt sich (Blocking-Risiko), danach Starving | 🟡 | [x] 2026-11-08 `applyDownPropagation` · Selftest |
| FL6c | Selftest: eine Station down → Nachbarn laut Regel | 🟡 | [x] 2026-11-08 `bin/production-line-down-neighbors-selftest.php` |

### FL7 — UI (Katalog)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| FL7a | Liste Linien + Detail Stationen/Puffer (`.dg-table` / `.dg-form`) unter Rezeptur/Maschinen | 🟡 | [x] 2026-11-08 `rezeptur-fliesslinien` · Guard |
| FL7b | Statusanzeige je Station (läuft / blocked / starved / down / material) — **kurze** Endnutzer-Texte, keine Plan-Codes | 🟡 | [x] 2026-11-08 Labels + Panel Detail |
| FL7c | Smoke Dev: 2-Maschinen-Linie, Puffer voll → Vorgänger „steht (Puffer voll)“ | 🔴 | [x] 2026-11-08 `bin/production-line-fl7c-smoke.php --live` auf ganz-om.de · Migrationen 156/157 |

### FL8 — Verfeinerungen (Backlog im Plan, nicht vor FL7)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| FL8a | Ausschuss/Nacharbeit-Schleife (Doc only zuerst) | 🟢 | [ ] |
| FL8b | Pull/Kanban-Modus (nur produzieren wenn Puffer Platz) vs. Push | 🟢 | [ ] |
| FL8c | Parallele Stationen / Umgehungsweg (optional, später) | 🟢 | [ ] |
| FL8d | WIP-Mengen ↔ Lagerplatz (GoBD) — eigenes Gate, nicht still mischen | 🟢 | [ ] |

### FL9 — Abschluss

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| FL9a | UI-Texte final (Blocking/Starving verständlich, kein MES-Jargon in Labels) | 🟢 | [ ] |
| FL9b | `TODOS.md` / Parent-Haken · Plan FL0–FL7 Status | 🟢 | [ ] |

---

## Nicht-Ziele (Produkt)

- SPS / OPC-UA / Sensor-Echtzeit  
- Vollständige MES-Plantafel / SAP-ME-Parität  
- Automatische FiBu-Buchung von WIP  
- Fremde Instanz-Daten / Tenant-Hardcode  
- Ersetzen von `dg_recipe_routing` (Kalkulation bleibt)  

---

## Empfohlene Reihenfolge (wenig Tokens)

1. **FL0b** (Verweise)  
2. **FL1a → FL1b → FL1c → FL1d**  
3. **FL2a–c**  
4. **FL3a → FL3b → FL3c**  
5. **FL4a** (Engpass-Hinweis)  
6. **FL5a** dann **FL5c** (Material)  
7. **FL6a–c**  
8. **FL7a–c** Smoke  
9. FL8 nur nach Bedarf · **FL9** Abschluss  

**Nächster Chat:**  
`Nur Mini-ID FL8a laut docs/FLIESSLINIE-REST-PLAN.md` (oder FL9 wenn Backlog übersprungen).

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`FLIESSLINIE-REGELN.md`](FLIESSLINIE-REGELN.md) | FL3a Blocking/Starving Pseudocode |
| [`MASCHINENBLOCK-M0A-DATENMODELL.md`](MASCHINENBLOCK-M0A-DATENMODELL.md) | Kapazität, Wartung, Belegung |
| [`ERP-ERWEITERUNG-LASTENHEFT-V2.md`](ERP-ERWEITERUNG-LASTENHEFT-V2.md) | ERP-Rahmen · Punkt 1b Fließlinie |
| [`REZEPTUR-MODUL.md`](REZEPTUR-MODUL.md) | Nicht-Ziele MES |
| [`UI-KLASSENKATALOG.md`](UI-KLASSENKATALOG.md) | UI |
| `dg_work_centers` / `dg_recipe_routing` | Stationen · Rezept-Routing |
| `database/migrations/156_production_lines.sql` | FL1b Linie + Stationen |
| `bin/production-lines-migration-selftest.php` | FL1b Vertrags-Selftest |
| `src/Recipe/ProductionLineStore.php` | FL1c Store (kein Hard-DELETE) |
| `bin/production-line-store-selftest.php` | FL1d SQLite Selftest |
| `database/migrations/157_production_line_buffers.sql` | FL2a Puffer |
| `bin/production-line-buffers-migration-selftest.php` | FL2a Vertrags-Selftest |
| `bin/production-line-buffers-store-selftest.php` | FL2c Puffer voll/leer Selftest |
| `src/Recipe/ProductionLineStatusService.php` | FL3b Offline-Status |
| `bin/production-line-status-selftest.php` | FL3c blocked/starved Selftest |
| `bin/production-line-bottleneck-selftest.php` | FL4a Engpass-Hinweis Selftest |
| `bin/production-line-throughput-selftest.php` | FL4b Linien-Durchsatz Selftest |
| `bin/production-line-raw-material-selftest.php` | FL5a Rohware Selftest |
| `bin/production-line-aux-material-selftest.php` | FL5b Hilfsstoff Selftest |
| `src/Recipe/ProductionLineMaterialStockGate.php` | FL5c Lager nur lesen |
| `bin/production-line-material-stock-selftest.php` | FL5c Fixture-Selftest |
| `src/Recipe/ProductionLineStationDownGate.php` | FL6a Wartung/Stör → down |
| `bin/production-line-station-down-selftest.php` | FL6a Selftest |
| `bin/production-line-down-propagation-selftest.php` | FL6b Propagation Selftest |
| `bin/production-line-down-neighbors-selftest.php` | FL6c Nachbarn Selftest |
| `views/modules/rezeptur-fliesslinien.php` | FL7a/b Liste/Detail + Status-UI |
| `bin/production-line-fl7c-smoke.php` | FL7c Offline + Dev `--live` |
| `MachinePlanningRepository` | Belegung/Konflikt **pro** Maschine · FL4a Output/h |
