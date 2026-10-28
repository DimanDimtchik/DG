# Maschinenblock M0a — Datenmodellplan

Stand: **2026-10-28** · Scope: Planung, keine Migration, keine UI  
Bezug: [`ERP-ERWEITERUNG-LASTENHEFT-V2.md`](ERP-ERWEITERUNG-LASTENHEFT-V2.md)

---

## Ziel

M0a beschreibt nur das Datenmodell fuer:

- Kapazitaet / Belegung einer Maschine
- Rohstoff-Input pro Stunde
- Produkt-Output pro Stunde
- Zusatz- und Verpackungsmaterial
- regelmaessige Wartung mit Turnus, Hilfsmitteln und Kosten

Nicht in M0a:

- keine SQL-Migration
- keine Repository-Klasse
- keine UI
- kein Belegungskalender
- keine FiBu-Buchung
- keine automatische Gemeinkostenumlage

---

## Bestehende Anker

| Bereich | Bestehend | Nutzung in M0b |
|---------|-----------|----------------|
| Maschine / Arbeitsplatz | `dg_work_centers` | Haupt-FK fuer Kapazitaet und Wartung |
| Rezeptur | `dg_recipes` | optionaler Bezug fuer rezepturspezifische Kapazitaet |
| Rezeptur-BOM | `dg_recipe_bom` | bleibt Rohstoff-/Materialbasis; Zusatzstoffe werden separat sichtbar |
| Routing | `dg_recipe_routing` | spaeter fuer Belegungs-/Laufzeitplanung |
| Snapshots | `dg_recipe_run_snapshots` | spaeter zur pruefbaren Kalkulation |
| Artikel / Leistungen | `dg_calendar_articles` | optionaler Bezug fuer Rohstoff, Output und Zusatzmaterial |

---

## Begriffe

| Begriff | Bedeutung |
|---------|-----------|
| Kapazitaetsprofil | technische Leistung einer Maschine: Input/h, Output/h, Ausbeute |
| Belegung | Zeitfenster, in dem Maschine fuer Produktion oder Wartung reserviert ist |
| Zusatzmaterial | Material, das nicht Kernrohstoff ist, aber verbraucht wird |
| Verpackungsmaterial | Sonderfall von Zusatzmaterial, z. B. Karton, Beutel, Etikett |
| Wartungsplan | Soll-Turnus mit erwarteten Hilfsmitteln, Kosten und Ausfallzeit |
| Wartungsprotokoll | tatsaechlich durchgefuehrte Wartung mit Ist-Kosten und Notiz |

---

## Tabellenentwurf M0b

### 1. `dg_machine_capacity_profiles`

Zweck: technische Kapazitaet einer Maschine dokumentieren.

Kernfelder:

- `id`
- `work_center_id`
- `recipe_id` nullable
- `input_article_id` nullable
- `input_material_label`
- `input_qty_per_hour`
- `output_article_id` nullable
- `output_product_label`
- `output_qty_per_hour`
- `unit`
- `yield_percent`
- `valid_from`
- `is_active`
- `notes`
- `created_at`, `updated_at`

Regeln:

- `work_center_id` ist Pflicht.
- `recipe_id` ist optional, falls eine Maschine je Rezept unterschiedliche Leistung hat.
- Artikelbezug ist optional; freier Text bleibt fuer einfache Betriebe erlaubt.
- Historisierung erfolgt zuerst ueber `valid_from` + `is_active`, keine Rueckwirkung.

### 2. `dg_recipe_auxiliary_materials`

Zweck: Zusatz-/Verpackungsmaterial separat von Kernrohstoffen sichtbar machen.

Kernfelder:

- `id`
- `recipe_id`
- `article_id` nullable
- `material_label`
- `role`: `packaging`, `additive`, `consumable`, `other`
- `qty_per_output_unit`
- `unit`
- `cost_per_unit`
- `is_active`

Regeln:

- Bleibt kalkulatorisch sichtbar neben der BOM.
- Kann spaeter in Rezeptur-Kalkulation und Snapshot einfließen.
- Keine Lagerbuchung in M0b/M1.

### 3. `dg_machine_maintenance_plans`

Zweck: wiederkehrende Wartung planen.

Kernfelder:

- `id`
- `work_center_id`
- `title`
- `interval_type`: `days`, `runtime_hours`, `cycles`
- `interval_value`
- `planned_downtime_minutes`
- `helper_materials_json`
- `estimated_material_cost`
- `estimated_service_cost`
- `next_due_date`
- `is_active`
- `created_at`, `updated_at`

Regeln:

- Ein Plan gehoert zu genau einer Maschine.
- Hilfsmittel bleiben in M0b als JSON, damit kein eigenes Ersatzteil-Modul noetig ist.
- Kosten sind Vorschlag/Kalkulation, keine FiBu-Buchung.

### 4. `dg_machine_maintenance_logs`

Zweck: erledigte Wartung dokumentieren.

Kernfelder:

- `id`
- `maintenance_plan_id` nullable
- `work_center_id`
- `performed_at`
- `runtime_hours_at_service`
- `actual_material_cost`
- `actual_service_cost`
- `notes`
- `created_at`

Regeln:

- Log kann mit oder ohne Plan erfasst werden.
- Belege bleiben in der normalen Belegkette / Voucher-Dateiablage.
- Ist-Kosten koennen spaeter die Kalkulation verbessern.

---

## Bewusst spaeter

| Spaeterer Block | Warum nicht M0a/M0b |
|-----------------|---------------------|
| Belegungskalender UI | braucht erst Kapazitaetsdaten |
| Konfliktpruefung Maschine ueberbucht | braucht Belegungsdaten und UI |
| Laufstunden automatisch aus Produktion | braucht Produktionslauf-Logik |
| Ersatzteil-Lager | eigenes Lager-/Wartungsmodul, nicht fuer Start noetig |
| FiBu-Buchung Wartungskosten | erst nach Freigabe-/Auditkonzept |
| Gemeinkostenumlage | erst nach Kapazitaet/Wartung als Grundlage |

---

## M0b Akzeptanzkriterien

M0b ist fertig, wenn:

- Migrationen fuer die vier Tabellen existieren.
- Foreign Keys zu `dg_work_centers` gesetzt sind.
- Repository-Selftest Einfuegen/Lesen/Aktiv-Inaktiv prueft.
- Keine UI und keine Buchung erzeugt wird.
- `git diff --check` sauber ist.

---

## Offene Entscheidung vor M1

**Belegungsplan** kann zwei Bedeutungen haben:

1. technische Kapazitaet/Leistung der Maschine
2. Kalender-/Zeitplanung fuer Produktions- und Wartungsfenster

M0a/M0b decken zuerst **Kapazitaet/Leistung und Wartung** ab. Der Kalender folgt als eigener kleiner Block, sobald die Stammdaten stehen.
