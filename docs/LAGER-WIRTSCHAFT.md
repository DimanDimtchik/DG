# Lager- und Warenwirtschaft (Stufe A + B)

Stand: 2026-09-13 · Migrationen **065–067**

## Umgesetzt

- [x] **Stufe A** — Ein Lager, Bestand pro Artikel (`track_stock`, `stock_qty`, `min_stock`)
- [x] **Positionscode** Ort-Halle-Regal-Platz (Migration 066)
- [x] **Lagerstruktur-Stammdaten** — Ort, Halle, Regal/Stellplätze, Platz fest/flexibel (Migration 067)
- [x] Einstellungen → **Lagerstruktur** (CRUD Lagerorte, Hallen, Regale)
- [x] Bewegungslog `dg_stock_movements` (GoBD-Nachvollziehbarkeit)
- [x] Automatik aus Belegen: Einkauf (+), Verkauf (−), Kundengutschrift (+), Ausgabenminderung (−)
- [x] Nur gebuchte Einnahmen-Dokumentarten (Rechnung, Abschlag, Schluss) — kein Angebot/Lieferschein
- [x] Manuelle Korrektur mit Pflicht-Grund
- [x] Menü **Lager** (Bestand, Bewegungen, CSV-Export)
- [x] **Stufe B** — Inventur-Assistent (Stichtag, Zählung, Differenz buchen, CSV)

## Bewusst nicht (Stufe C / später)

- [ ] Automatische Platz-Zuweisung bei Beleg-Eingang (UI-Vorschlag vorbereitet)
- [ ] Mehrere Mandanten-Lager / Filial-Sync
- [ ] Chargen, Seriennummern, MHD
- [ ] Automatische SKR-Bestandskonten-Buchung
- [ ] Shop-Bestandsabzug (`shop.ganz-soft.de`)

## Test

```bash
php bin/stock-selftest.php
```

Manuell: `docs/TESTLISTE-2026-08-21.md` — Abschnitt Lager (neu).

## Technik

| Komponente | Pfad |
|------------|------|
| Migration | `065_stock_management.sql`, `066_stock_position.sql`, `067_stock_structure.sql` |
| Stammdaten | `src/Inventory/StockStructureRepository.php` |
| Platz-Logik | `src/Inventory/StockPlaceService.php` |
| Bewegungen | `src/Inventory/StockMovementService.php` |
| Inventur | `src/Inventory/StockInventoryService.php` |
| UI Lager | `views/modules/lager.php` |
| UI Struktur | `views/settings/tab-lager-struktur.php` |
| Beleg-Hook | `VoucherRepository::save()` / `delete()` |
