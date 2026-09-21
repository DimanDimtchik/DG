# Rezeptur & Produktionsplanung — Spec (Source of Truth)

Stand: **2026-09-21** · Status: Entwurf · Zielsystem: DG CRM (Buchhaltung + Lager)  
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
| `dg_recipe_run_actuals` | Soll/Ist (später) | snapshot_id, planned_*, actual_*, note |

Exakte Migration-Nummern und Spaltentypen: in der jeweiligen Phase festlegen (anschließend an letzte DG-Migration).

---

## 5. UX-Bausteine (Priorität)

1. **Wizard** statt Fachjargon („Maschine hinzufügen“ → Preis, Lebensdauer, Watt).
2. **Was-wäre-wenn-Slider** (Strom, Charge, Rüstzeit) — ändert Anzeige, nicht Stammdaten, bis „Übernehmen“.
3. **Soll/Ist-Button** „Produktionsdurchlauf protokollieren“.
4. **Single-Pane-Cockpit** (BOM + Routing + Kalkulation auf einer Maske) — **ohne** Graph zuerst.
5. **Ablaufgraph (React Flow)** — erst nach stabilem Cockpit (Phase R7).

---

## 6. Roadmap (eine Phase = ein Chat = ein Commit)

| Phase | Lieferobjekt | Erlaubt zu lesen/ändern |
|-------|----------------|-------------------------|
| **R0** | Diese Spec + ggf. Menü-Platzhalter/Feature-Flag | docs, MenuRegistry (1 Eintrag), kein Schema |
| **R1** | `recipes` + `recipe_bom` + Liste/Form (Material + Zeit, keine Maschine) | Migration, Repository, 1–2 Views, index-Routing minimal |
| **R2** | `work_centers` + Stundensatz-Anzeige | Migration, Service `RecipeCostService`, Settings/UI |
| **R3** | `recipe_routing` + Vorkalkulation + Snapshot beim Speichern/Lauf | Routing-UI, CostService, Snapshot-Tabelle |
| **R4** | Wizard Maschine anlegen | nur Work-Center-UI |
| **R5** | Was-wäre-wenn-Slider | Frontend + CostService (keine neuen Tabellen) |
| **R6** | Soll/Ist-Protokoll | Actuals-Tabelle + Button |
| **R7** | Graph-Visualisierung | neues Frontend-Paket, Optional |

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

- [ ] Material-Referenz: `dg_articles.id` vs. freier Text + optionaler Artikel-Link
- [ ] Menü-Ort: unter Lager, Buchhaltung oder eigener Punkt „Produktion“
- [ ] Mehrere Firmen (Multi-Firma Phase 0): recipe pro Firma/Tenant wie üblich über Instanz-DB
