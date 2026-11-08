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

## Speichermodell (Entscheidungsskizze — FL1 festlegen)

**Empfehlung (vor FL1b bestätigen):**

1. `dg_production_lines` — Linie (Name, aktiv, optional Rezept-Bezug)  
2. `dg_production_line_stations` — `line_id`, `work_center_id`, `station_order` (1…n)  
3. `dg_production_line_buffers` — Puffer **nach** Station *i* (vor *i+1*): `capacity_qty`, `unit`, optional `current_qty` nur für Simulation/manuelle Ist-Eingabe  

**Wechsel / Stopp-Logik (Berechnung, kein Hard-DELETE):**

- Station *i* **blocked**, wenn Puffer nach *i* ≥ Kapazität  
- Station *i+1* **starved**, wenn Puffer vor ihr ≤ 0 (bzw. unter Mindestmenge)  
- Linie **gestoppt**, wenn Eingangs-Rohware fehlt **oder** alle produktiven Stationen blocked/starved/in Störung  

Routing (`dg_recipe_routing`) bleibt Kalkulations-Arbeitsplan; Linie ist **Betriebs-Stammdatum** (eine physische Kette kann mehrere Rezepte fahren).

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
`Nur Mini-ID FL1a laut docs/FLIESSLINIE-REST-PLAN.md. Nur genannte Dateien. Katalog. Kein MES/PLC. Kein Refactor.`

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
| FL1 | Linie + Stationen (Stammdaten) | [ ] | 🟡 |
| FL2 | Puffer zwischen Stationen | [ ] | 🟡 |
| FL3 | Blocking / Starving-Engine (Offline) | [ ] | 🟡 |
| FL4 | Engpass / Takt-Hinweis | [ ] | 🟢🟡 |
| FL5 | Materialmangel (Rohware + Hilfsstoff) | [ ] | 🟡 |
| FL6 | Störung / Wartung propagiert | [ ] | 🟡 |
| FL7 | UI Linie + Status | [ ] | 🟡🔴 |
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
| FL1a | Speichermodell final (Tabellen/FKs) in diesem Plan bestätigen · Migration-Nummer prüfen | 🟢 | [ ] |
| FL1b | Migration: `dg_production_lines` + `dg_production_line_stations` · Unique `(line_id, station_order)` · WC RESTRICT | 🟡 | [ ] |
| FL1c | Store: `listLines`, `getLineWithStations`, `saveStations` (Reihenfolge) · **kein** Hard-DELETE Historie ohne Flag | 🟡 | [ ] |
| FL1d | Selftest: 3 Stationen speichern → Reihenfolge stabil | 🟡 | [ ] |

### FL2 — Puffer

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| FL2a | Migration: `dg_production_line_buffers` nach Station (capacity, unit, optional current) | 🟡 | [ ] |
| FL2b | Store: Puffer je Lücke Station→Station; Kapazität > 0 Pflicht | 🟡 | [ ] |
| FL2c | Selftest: Puffer voll/leer-Grenzen setzen | 🟡 | [ ] |

### FL3 — Blocking / Starving (Offline-Engine)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| FL3a | Doc: Zustandsregeln Blocking/Starving/Linienstopp ( Pseudocode in Plan oder `docs/FLIESSLINIE-REGELN.md` ) | 🟢 | [ ] |
| FL3b | Service: aus Stationen + Pufferständen Status je Station berechnen | 🟡 | [ ] |
| FL3c | Selftest: Puffer voll → Vorgänger blocked; Puffer leer → Nachfolger starved | 🟡 | [ ] |

**Prompt FL3b:**  
`Nur Mini-ID FL3b laut docs/FLIESSLINIE-REST-PLAN.md. Nur Status-Service. Kein UI. Kein PLC.`

### FL4 — Engpass / Takt

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| FL4a | Engpass aus Kapazitätsprofilen (Output/h) der Linien-Stationen ableiten · Hinweistext | 🟢🟡 | [ ] |
| FL4b | Optional: grober Linien-Durchsatz = min(Station-Durchsätze) unter Puffer-Annahmen | 🟡 | [ ] |

### FL5 — Materialmangel

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| FL5a | Regel: fehlende Rohware (Eingang Station 1 / BOM) → Linie `stopped_material` | 🟢 | [ ] |
| FL5b | Optional Hilfsstoff mitten in der Linie (Zusatzmaterial an Station *k*) → ab *k* starved/stop | 🟡 | [ ] |
| FL5c | Anbindung Lagerbestand **nur lesen** (kein Auto-Buchen) · Selftest mit Fixture | 🟡 | [ ] |

### FL6 — Störung / Wartung

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| FL6a | Offene Wartung / manuelles Stör-Flag an Station → Station `down` | 🟡 | [ ] |
| FL6b | Propagation: down bei *i* → Puffer davor füllt sich (Blocking-Risiko), danach Starving | 🟡 | [ ] |
| FL6c | Selftest: eine Station down → Nachbarn laut Regel | 🟡 | [ ] |

### FL7 — UI (Katalog)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| FL7a | Liste Linien + Detail Stationen/Puffer (`.dg-table` / `.dg-form`) unter Rezeptur/Maschinen | 🟡 | [ ] |
| FL7b | Statusanzeige je Station (läuft / blocked / starved / down / material) — **kurze** Endnutzer-Texte, keine Plan-Codes | 🟡 | [ ] |
| FL7c | Smoke Dev: 2-Maschinen-Linie, Puffer voll → Vorgänger „steht (Puffer voll)“ | 🔴 | [ ] |

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
`Nur Mini-ID FL1a laut docs/FLIESSLINIE-REST-PLAN.md. Speichermodell final bestätigen. Keine Migration ohne Freigabe. Kein Code außer Doc.`

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`MASCHINENBLOCK-M0A-DATENMODELL.md`](MASCHINENBLOCK-M0A-DATENMODELL.md) | Kapazität, Wartung, Belegung |
| [`ERP-ERWEITERUNG-LASTENHEFT-V2.md`](ERP-ERWEITERUNG-LASTENHEFT-V2.md) | ERP-Rahmen · Punkt 1b Fließlinie |
| [`REZEPTUR-MODUL.md`](REZEPTUR-MODUL.md) | Nicht-Ziele MES |
| [`UI-KLASSENKATALOG.md`](UI-KLASSENKATALOG.md) | UI |
| `dg_work_centers` / `dg_recipe_routing` | Stationen · Rezept-Routing |
| `MachinePlanningRepository` | Belegung/Konflikt **pro** Maschine |
