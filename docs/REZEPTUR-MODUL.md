# Rezeptur & Produktionsplanung — Spec (Source of Truth)

Stand: **2026-09-21** · Status: **R7 erledigt (Modul komplett)** · Zielsystem: DG CRM (Buchhaltung + Lager)  
Quellen: `Systemspezifikation_Rezeptur_Modul.pdf` · Markt-/UX-Notiz (SAP / Odoo / Lexware) · Session 2026-09-21

> Agent-Regel: Bei Arbeit an diesem Modul **nur diese Spec** und die in der jeweiligen Phase genannten Dateien lesen. Kein erneutes Einlesen der PDF. Kein Scope außerhalb der Phase.

---

## 1. Ziel

KMU/Handwerk: **Stückliste (BOM) + Arbeitsplan (Routing) + Maschinen-/Personalkosten** → transparente Vorkalkulation, Wunsch-Marge → Verkaufspreis, später Soll/Ist-Nachkalkulation.

Differenzierung vs. Platzhirsche:

| System | Problem (kurz) | DG-Antwort |
|--------|----------------|------------|
| SAP | Over-engineered, starre Pfade, undurchsichtige Zahlen | Ein Cockpit, Wizard-Sprache, tarifäre Nachvollziehbarkeit |
| Odoo | Lücken bei dynamischer Kostenanpassung; DE-Fibu oft aufwendig | Anbindung an bestehende SKR/GoBD-Belegkette |
| Lexware | Keine echten Arbeitspläne / Maschinenkosten | Routing + Maschinenstundensatz |

**Nicht-Ziele (vorerst):** vollständige MES-Plantafel, SAP-Parität, React-Flow in Phase 1–3.

---

## 2. Fachliche Leitplanken (Steuer / GoBD)

1. Preisänderungen (Rohstoff, Strom, Lohn) **ändern keine** bereits gebuchten Läufe rückwirkend.
2. Bei Fertigungsausführung: **Kalkulations-Snapshot** speichern (Materialpreise, Stundensätze, Formelstand).
3. Rezept-Stammdaten **versionierbar** (mindestens: neuer Stand speichert Historie oder `valid_from`).
4. Anbindung Lager: Preferred-EK / Bestände aus bestehendem Lager **lesen**, nicht parallel neu erfinden.
5. Buchungen erst, wenn explizit freigegeben — Nachkalkulation zuerst **informationspflichtig**, nicht still buchungswirksam.

---

## 3. Kostenmodell (Norm)

```
K_mat   = Σ ( Menge_i × EK_i × (1 + Verschnitt_i%) )
K_masch = (Anschaffung / Nutzungsdauer_h) + (kW × €/kWh) + (m² × Raum€/h)
K_fert  = (Rüst_min + Lauf_min)/60 × (K_masch + Bediener_Anz × Lohn€/h)
Selbstkosten = K_mat + K_fert
VK       = Selbstkosten × (1 + Marge%)
```

Einheiten und Rundung: im UI Euro mit 2–4 Nachkommastellen je nach Kontext; Snapshot speichert die verwendeten Inputs.

---

## 4. Datenmodell (Minimal)

| Tabelle | Zweck | Kernfelder |
|---------|--------|------------|
| `dg_recipes` | Rezept / Fertigprodukt-Rezeptur | id, title, target_qty, margin_pct, status, version |
| `dg_recipe_bom` | Stückliste | recipe_id, material_ref, qty, scrap_pct |
| `dg_work_centers` | Maschine / Arbeitsplatz | name, purchase_price, life_hours, kw, space_m2, operators |
| `dg_recipe_routing` | Arbeitsplan | recipe_id, work_center_id, step_order, setup_min, run_min |
| `dg_recipe_run_snapshots` | Historie je Lauf | recipe_id, inputs_json, result_json, created_at |
| `dg_recipe_run_actuals` | Soll/Ist je Lauf | snapshot_id, planned_*, actual_*, note |

Exakte Migration-Nummern und Spaltentypen: in der jeweiligen Phase festlegen (anschließend an letzte DG-Migration).

---

## 5. UX-Bausteine (Priorität)

1. **Wizard** statt Fachjargon („Maschine hinzufügen“ → Preis, Lebensdauer, Watt).
2. **Was-wäre-wenn-Slider** (Strom, Charge, Rüstzeit) — ändert Anzeige, nicht Stammdaten, bis „Übernehmen“.
3. **Soll/Ist-Button** „Produktionsdurchlauf protokollieren“.
4. **Single-Pane-Cockpit** (BOM + Routing + Kalkulation auf einer Maske) — **ohne** Graph zuerst.
5. **Ablaufgraph** — SVG/Vanilla-JS unter dem Cockpit (R7); kein React-Build im CRM-Sync.

---

## 6. Roadmap (eine Phase = ein Chat = ein Commit)

| Phase | Lieferobjekt | Erlaubt zu lesen/ändern |
|-------|----------------|-------------------------|
| **R0** ✅ | Spec + Menü-Platzhalter `rezeptur` + Flag `features.rezeptur` | docs, `MenuRegistry` — **erledigt 2026-09-21** |
| **R1** ✅ | `dg_recipes` + `dg_recipe_bom` + Liste/Form | Migration `084`, `RecipeRepository`, Views, index-Hook — **erledigt 2026-09-21** |
| **R2** ✅ | `dg_work_centers` + Stundensatz K<sub>masch</sub> | Migration `085`, `WorkCenterRepository`, `RecipeCostService`/`RecipeCostSettings`, UI — **erledigt 2026-09-21** |
| **R3** ✅ | Routing + Vorkalkulation + Snapshots | Migration `086`, `RecipeSnapshotRepository`, CostService erweitern, Form-UI — **erledigt 2026-09-21** |
| **R4** ✅ | Wizard Maschine anlegen | nur `rezeptur-maschine-form` (+ Listentext) — **erledigt 2026-09-21** |
| **R5** ✅ | Was-wäre-wenn-Slider | Frontend + CostService (keine neuen Tabellen) — **erledigt 2026-09-21** |
| **R6** ✅ | Soll/Ist-Protokoll | Migration `087`, `RecipeActualRepository`, Button + Ist-Anpassung — **erledigt 2026-09-21** |
| **R7** ✅ | Graph-Visualisierung | `assets/css/recipe-flow.css` + `assets/js/recipe-flow.js` (SVG, kein npm) — **erledigt 2026-09-21** |

**Chat-Vorlage (Token-sparend):**

```text
Scope: Rezeptur R1 laut docs/REZEPTUR-MODUL.md
Nur: Migration + Repository + eine Listen-/Form-View + minimaler index.php-Hook
Kein Routing, kein Graph, kein Deploy außer ich sage es.
```

---

## 7. Abgrenzung zu bestehenden DG-Modulen

- **Lager / Einkauf:** Material-IDs aus Artikel/Lager; EK aus Preferred-Quelle wenn vorhanden.
- **Belege:** Fertigprodukt kann später Artikel+Verkaufspreis speisen — nicht in R1.
- **Akademie:** Schulungsvideo erst nach R3+.

---

## 8. Offene Entscheidungen (vor R1 klären)

- [x] Material-Referenz: **freier Text** (`material_label`) **+ optional** `article_id` → `dg_calendar_articles`
- [x] Menü-Ort: **neben Lager** (Slug `rezeptur`), gleiche Rechte wie Artikelkatalog; Feature-Flag `features.rezeptur` (Default an, abschaltbar)
- [x] Mehrere Firmen (Multi-Firma Phase 0): recipe pro Firma/Tenant wie üblich über Instanz-DB

**R1:** Fertigungszeit als Feld `labor_minutes` am Rezept (ohne Maschine).  
**R2:** Maschinen unter Rezeptur → „Maschinen & Stundensatz“; globale Kostensätze in `SettingsStore` (`recipe_cost_rates`).  
**R3:** `dg_recipe_routing` + Vorkalkulation (K_mat/K_fert/SK/VK); Snapshot bei Speichern (`kind=save`) und Button „Produktionsdurchlauf“ (`kind=run`). EK aus Preferred-Einkauf oder manuellem `unit_cost`.  
**R4:** Maschinen-Anlage als 5-Schritt-Wizard (Name → Preis/Jahre→Stunden → Watt → Fläche/Personal → Zusammenfassung + Live-Stundensatz).
**R5:** Was-wäre-wenn-Slider (Strom/Charge/Rüst) über `/api/recipe-cost`; Übernehmen schreibt Formular + Stromsatz.
**R6:** `dg_recipe_run_actuals` — Button „Produktionsdurchlauf“ speichert Lauf-Snapshot (Soll) + Ist-Werte; nachträglich editierbar; keine Buchung.
**R7:** Ablaufgraph als SVG-Frontend-Paket (`recipe-flow.css`/`recipe-flow.js`), live aus BOM+Routing; bewusst ohne React/npm (gleicher Code-Sync).
