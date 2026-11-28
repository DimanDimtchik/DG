# DG CRM — Artikel & Leistungen: dynamische Eigenschaften (Zukunft)

> **Status:** Plan / Backlog — **noch nicht implementieren**, außer explizit vorgezogen.  
> **Issue:** [#1 Artikel Eigenschaften](https://github.com/DimanDimtchik/DG/issues/1)  
> **Anker heute:** `dg_calendar_articles` · Lager [`LAGER-WIRTSCHAFT.md`](LAGER-WIRTSCHAFT.md) · FiBu-Anlagenbuch später ([`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) § Jahresblock)  
> **Agent-Regel:** Pro Chat **eine** Mini-ID (AP…). Spec nur dieser Abschnitt + genannte Dateien.

Stand: **2026-11-28** · Branch-Empfehlung: `cursor/artikel-eigenschaften-…`

---

## Zielbild (in einem Satz)

Ein **universeller Artikel- und Leistungsstamm** mit **maximalem Eigenschaftskatalog**, den die Instanz **gruppenweise aktiviert** — dieselben Stammdaten speisen **Verkauf, Einkauf, Lager/Inventar und (später) Anlagenbuch**, ohne Branchen-Sondercode im Kern.

---

## Fachliche Idee (Nutzerentscheidung)

| Wunsch | Umsetzung im CRM |
|--------|------------------|
| Artikel **und** Dienstleistungen maximal erweitern | Katalog-Kern + dynamische Merkmale; Typen Ware / Leistung / Set / Anlage / … |
| Sachen **gruppieren** | **Eigenschaftsgruppen** (Themen-/Branchenpakete), nicht eine starre Spaltenliste |
| Gruppe **aktivieren und benutzen** | Pro Instanz: Pakete an/aus; UI und Pflichtfelder nur aus **aktiven** Gruppen |
| Café sieht kein KFZ-Handel, KFZ kein Pharma | Inaktive Gruppen erscheinen weder in Formularen noch in Filtern/Import-Vorschlägen |
| Dieselben Daten im **Inventar** | Lager/Bewegungen bleiben an `article_id`; Merkmale (Maße, Gefahrstoff, MHD-Fähigkeit …) werden mitgenutzt |
| Café kann ein **Fahrzeug als Anlage oder Inventar** führen | Ein Stamm-Datensatz, **Nutzungsrollen** (lagergeführt / Anlagevermögen / verkaufbar …) — kein zweites „Auto-Modul“ |

**Beispiel:** Café aktiviert Gruppen *Gastronomie*, *Allgemein*, optional *Fahrzeuge*. Ein Firmenwagen ist derselbe Artikelstamm mit Rolle **Anlage** (AfA) oder — falls Ersatzteile/Zubehör — **lagergeführt**. Die Gruppe *Pharma* bleibt aus → keine Pharma-Felder in der UI.

---

## Entscheidungen (verbindlich, bis geändert)

| # | Wert |
|---|------|
| **D1** | **Erweitern, nicht ersetzen:** Anker bleibt `dg_calendar_articles` (+ bestehende Lager-/Preis-/Garantie-Spalten). Kein paralleles `articles`-Produkt. |
| **D2** | **Hybrid-Speicher:** feste Kernfelder (Identität, Steuer, Preis, Lager-Flags) + **dynamische** Eigenschaftswerte. |
| **D3** | **Gruppen = aktivierbare Pakete** (`property_group` / Branchenkatalog). Produkt liefert viele Gruppen; Instanz schaltet nur benötigte ein. |
| **D4** | **Ein Stamm, viele Rollen:** Flags/Rollen z. B. verkaufbar, einkaufbar, lagergeführt (`track_stock`), Dienstleistung, **Anlagevermögen** — kombiniert möglich. |
| **D5** | **Lager & Anlage teilen den Stamm:** Inventur/Bewegungen nur bei lagergeführt; AfA/Anlagenbuch (später) bei Anlage — gemeinsame Identität und Merkmale. |
| **D6** | **Gleicher Code überall · eigene DB:** keine Firmen-/Branchen-Hardcodes in `src/`/`views/`; Katalog-Seeds sind Produktinhalt, Aktivierung = Instanz-Setting. |
| **D7** | **Standards später importierbar:** GS1 GPC, ETIM, eCl@ss, UNSPSC, Schema.org — Architektur vorbereiten; kein Zwang in Phase AP1. |
| **D8** | **Typisierte Wertespalten** (Text/Zahl/Bool/Datum/Auswahl/…), keine einzige Universal-Textspalte für alles. |
| **D9** | **GoBD:** Änderungen an steuer-/bewertungsrelevanten Merkmalen und Rollen nachvollziehbar (Protokoll/Historie ab AP-Härtung). |
| **D10** | **Kein Tenant im Kern:** Beispieltexte nur generisch („Café“, „KFZ“, „Pharma“ als **Gruppen-IDs**, nicht als Kundennamen). |

### Bewusst nicht (in diesem Plan / ersten Wellen)

- Sofortiges volles PIM / Shop-Sync (`shop.ganz-soft.de`)
- Live-Anbindung ETIM/eCl@ss-Lizenzen
- Automatische FiBu-Bestandskonten-Buchung (bleibt Lager-Backlog)
- MES/PLC, Amazon-Katalog (eigene Pläne)
- Anlagenbuch-Vollausbau vor Hetzner/FiBu-Jahresblock — hier nur **Schnittstelle am Stamm**

---

## Ist-Stand (Inventar 2026-11-28)

| Baustein | Stand | Hinweis |
|----------|-------|---------|
| Katalog `dg_calendar_articles` | ✅ | `catalog_kind` Ware/Leistung, Nummer, GTIN, Steuer, Preis, Garantie, Online-Preisliste |
| Lager Stufe A–C Teil | ✅ | `track_stock`, Bewegungen, Inventur, Plätze, Reservierung, Einkaufsquellen — [`LAGER-WIRTSCHAFT.md`](LAGER-WIRTSCHAFT.md) |
| Dynamische Eigenschaften / Gruppen | ❌ | Ziel dieses Plans |
| Chargen / Serien / MHD | ❌ | Lager-„bewusst später“ — hängt an Merkmalgruppen |
| Anlagenbuch / AfA am Abschluss | ❌ | Roadmap; Anschaffungsrechner leicht vorhanden |
| Varianten / Verpackungshierarchie | ❌ | spätere AP-Phasen |
| Rezeptur-Material → Artikel | ✅ Teil | optional `article_id` — profitiert von reicherem Stamm |

---

## Architektur (Skizze)

```
dg_calendar_articles          ← Kern (Identität, Steuer, Preis, Rollen-Flags)
        │
        ├── dg_article_property_groups     (Paket: allgemein, gastronomie, kfz, pharma, …)
        ├── dg_article_properties          (Definition: Name, Datentyp, Einheit, Gruppe)
        ├── dg_article_property_values     (Wert je Artikel, typisierte Spalten)
        ├── dg_instance_property_groups    (Instanz: Gruppe aktiv ja/nein, Sortierung)
        │
        ├── Lager: dg_stock_*              (nur wenn lagergeführt)
        └── später: Anlagenbuch            (nur wenn Anlagevermögen)
```

**UI-Regel:** Formular „Artikel & Leistungen“ zeigt Kernfelder immer; Merkmal-Blöcke nur für **aktive** Gruppen. Suche/Filter respektieren Aktivierung.

**Cross-Use:** Modul Lager liest Maße/Gewicht/Gefahrstoff aus aktiven Merkmalen, wenn vorhanden — Fallback auf heutige Kernfelder. Modul Anlagenbuch (später) liest Anschaffung, Nutzungsdauer, Kennzeichen o. Ä. aus Gruppe *Fahrzeuge* / *Anlagen*, ohne dass Gastronomie-Felder Pflicht werden.

---

## Datenbereiche (logisch, nicht alles Pflicht)

Orientierung an Issue #1, **gekürzt auf CRM-Nutzung**:

| Bereich | Beispiele | Typische Gruppe |
|---------|-----------|-----------------|
| A Identität | Nummer, Name, Typ, GTIN, Hersteller | Kern (fest) |
| B Merkmale | Material, Farbe, Allergene, Gewinde … | Branchenpakete |
| C Maße/Gewicht | L×B×H, kg | allgemein + logistik |
| D Verpackung | VE, Karton, Palette | logistik |
| E Lagerung | Temp, Gefahrstoff, Platzbedarf | lager |
| F Einkauf | Lieferanten (heute: `dg_article_purchase_sources`) | kern/einkauf |
| G Verkauf | Preise, Staffeln | kern |
| H Buchhaltung | Erlöskonto, Vorsteuer, **AfA-Klasse** | fibu / anlage |
| I–O | Logistik, Qualität, Stückliste, Shop, Recht, Dienstleistung | spätere Pakete |

Nicht jeder Artikel braucht alle Bereiche. **Aktivierung der Gruppe** steuert Sichtbarkeit; Rollen-Flags steuern Modul-Verhalten.

---

## Parent-Übersicht (Mini-IDs)

| ID | Thema | Priorität | Status | Token |
|----|--------|-----------|--------|-------|
| **AP0** | Dieser Plan + Ist-Inventar + Entscheidungen | — | [x] 2026-11-28 | 🟢 |
| **AP1** | Datenmodell: Gruppen, Eigenschaften, Werte, Instanz-Aktivierung | 1 | [ ] | 🟡 |
| **AP2** | UI: Artikel maske — aktive Gruppen, Validierung | 2 | [ ] | 🟡 |
| **AP3** | Seed: Basisgruppen (Allgemein, Maße, Lager, Dienstleistung) | 3 | [ ] | 🟢 |
| **AP4** | Branchenpakete (Gastronomie, KFZ, …) als **optionale** Seeds | 4 | [ ] | 🟢 |
| **AP5** | Lager-Anbindung: Merkmale → Platz/Scan/Warnungen (read) | 5 | [ ] | 🟡 |
| **AP6** | Rolle Anlagevermögen am Stamm (Flag + FiBu-Felder-Hooks) | 6 | [ ] | 🟡 |
| **AP7** | Historie / GoBD-Protokoll Merkmaländerungen | 7 | [ ] | 🟡 |
| **AP8** | Varianten / Einheiten-Umrechnung | später | [ ] | 🔴 |
| **AP9** | Chargen / Serien / MHD (mit Lager) | später | [ ] | 🔴 |
| **AP10** | Standards-Import (GS1/ETIM/…) | später | [ ] | 🔴 |
| **AP11** | Automatik: Verpackung / Lagerplatz / Disposition (Services) | Vision | [ ] | 🔴 |

Token: 🟢 Doc/Seed/Guard · 🟡 Service+UI · 🔴 Gate / Lizenz / großer Querschnitt.

**Chat-Prompt-Vorlage:**  
`Artikel-Eigenschaften AP0 ✅. Nächster Block APn: laut docs/ARTIKEL-EIGENSCHAFTEN-PLAN.md (nur diese Mini-ID).`

---

## Abhackplan

Status: `[ ]` offen · `[~]` angefangen · `[x]` erledigt

### AP0 — Plan / Inventar (🟢) — **dieser Stand**

- [x] Zielbild, Entscheidungen D1–D10
- [x] Ist-Anker `dg_calendar_articles` + Lager + Anlagenbuch-Hinweis
- [x] Gruppierung + Instanz-Aktivierung + Cross-Use Inventar/Anlage
- [x] Verweis Issue #1 · Eintrag Backlog `TODOS.md`

### AP1 — Datenmodell (🟡)

- [ ] Migration: `dg_article_property_groups`, `dg_article_properties`, `dg_article_property_values`, `dg_instance_property_groups` (Namen final bei Umsetzung)
- [ ] Typisierte Wertespalten; Unique (article_id, property_id)
- [ ] Repository + Selftest (CRUD, Aktivierung filtert Definitionen)
- [ ] **Kein** Löschen aktiver Gruppen mit Bestands-Werten ohne Migration/Warnung

### AP2 — UI Artikel & Leistungen (🟡)

- [ ] Einstellungen (oder Katalog-Nebenreiter): Gruppen aktivieren/deaktivieren
- [ ] Artikel-Formular: Blöcke nur aktiver Gruppen; Katalog-Klassen laut [`UI-KLASSENKATALOG.md`](UI-KLASSENKATALOG.md)
- [ ] Endnutzer-Texte ohne Plan-Codes (`AP1` …)

### AP3 — Basis-Seeds (🟢)

- [ ] Gruppen: `allgemein`, `masse_gewicht`, `lagerung`, `dienstleistung` (IDs stabil, Labels DE)
- [ ] Wenige Kern-Merkmale (Gewicht, L/B/H, lagerfähig-Hinweis) — doppelte Spalten zu bestehenden Kernfeldern vermeiden oder klar als „bevorzugt Merkmal“ dokumentieren

### AP4 — Branchenpakete optional (🟢)

- [ ] Seeds z. B. `gastronomie`, `kfz`, `pharma`, `elektronik`, `textil` — **Default: inaktiv**
- [ ] Dokumentation: welche Gruppe wofür; kein Zwang zur Aktivierung

### AP5 — Lager mitnutzen (🟡)

- [ ] Read-Pfad: Maße/Gewicht/Gefahr aus Merkmalen für Platz-Check / Etiketten-Hinweis (ohne Pflicht)
- [ ] Inventur unverändert über `article_id`; Merkmale nur Anzeige/Hilfen
- [ ] Guard: lagergeführt ohne aktive Lager-Gruppe bleibt erlaubt (heutiges Verhalten)

### AP6 — Anlage / Inventar-Rollen (🟡)

- [ ] Rolle `is_fixed_asset` (o. Ä.) am Kern + UI-Checkbox verständlich („Als Anlagevermögen führen“)
- [ ] Gleicher Datensatz darf lagergeführt **oder** Anlage sein (Café-Firmenwagen = Anlage; Ersatzrad = lagergeführt — zwei Artikel oder eine klare Regel im UI-Text)
- [ ] Hook-Punkte für späteres Anlagenbuch/AfA (keine Vollbuchung in AP6)
- [ ] Abgleich Roadmap „Anlagenbuch / AfA an Abschluss“

### AP7 — Historie (🟡)

- [ ] Protokoll wer/wann/altes/neues bei Merkmal- und Rollenänderung (steuerrelevant)
- [ ] Selftest + kurzer Prüfer-Hinweis in Docs

### AP8–AP11 — später

Siehe Parent-Tabelle; erst nach stabilem AP1–AP7 und Kapazität (Hetzner/Lohn/FiBu haben Vorrang, sofern nicht vorgezogen).

---

## Leitplanken (Steuer / Prüfung / Praxis)

- **USt / Erlöse:** Steuerart und Preise bleiben Kernfelder; Merkmale ersetzen keine Beleglogik.
- **Bestand:** Bewegungen weiter GoBD-fähig über `dg_stock_movements`; Merkmale sind Stammdaten, keine stillen Bestandsbuchungen.
- **AfA:** Anlage-Rolle allein erzeugt keine Abschreibung — erst Anlagenbuch-Modul; bis dahin nur Stammdaten + Rechner-Hooks.
- **Branchenrecht** (Allergene, Medizinprodukte, Gefahrgut): Gruppen können Felder vorsehen; Haftung/Freigabe bleibt Instanz/Org — CRM speichert strukturiert.
- **Mehrinstanz:** Seed-Kataloge im Code/Migration; Aktivierung und Werte nur Instanz-DB.

---

## Bezug Issue #1 (Was übernommen / was bewusst anders)

| Issue-Idee | Hier |
|------------|------|
| Universeller Stamm, nicht nur Spalten erweitern | ✅ D1+D2 |
| Dynamischer Eigenschaftskatalog | ✅ AP1 |
| 15 SAP-artige Anwendungsbereiche | ✅ als logische Bereiche; Umsetzung über Gruppen, nicht 15 Pflicht-Tabellen sofort |
| International Standards | ✅ D7 / AP10 — vorbereitet, nicht Blocker |
| P01–P19 Phasenliste | ↪️ verdichtet zu **AP0–AP11**, CRM-Anker, tokenarm |
| „Weltweit jedes Produkt“ sofort | ↪️ Maximum als **Katalog-Angebot**; Sichtbarkeit über Aktivierung (Café ≠ Pharma-UI) |
| Eigenes PIM-Produkt | ↪️ zuerst CRM-Modul am bestehenden Katalog |

---

## Nächster Schritt

Wenn Umsetzung startet: **AP1** (Datenmodell + Selftest) in eigenem Chat/Branch. Bis dahin: Plan liegt im Backlog; Priorität hinter Phase A / Hetzner / Lohn-Gates, außer Nutzer zieht vor.
