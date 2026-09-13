# Lager- und Warenwirtschaft (Stufe A + B)

Stand: 2026-09-13 · Migration **065**

## Umgesetzt

- [x] **Stufe A** — Ein Lager, Bestand pro Artikel (`track_stock`, `stock_qty`, `min_stock`)
- [x] **Positionscode** Ort-Halle-Regal-Platz (Migration 066)
- [x] Bewegungslog `dg_stock_movements` (GoBD-Nachvollziehbarkeit)
- [x] Automatik aus Belegen: Einkauf (+), Verkauf (−), Kundengutschrift (+), Ausgabenminderung (−)
- [x] Nur gebuchte Einnahmen-Dokumentarten (Rechnung, Abschlag, Schluss) — kein Angebot/Lieferschein
- [x] Manuelle Korrektur mit Pflicht-Grund
- [x] Menü **Lager** (Bestand, Bewegungen, CSV-Export)
- [x] **Stufe B** — Inventur-Assistent (Stichtag, Zählung, Differenz buchen, CSV)

## Bewusst nicht (Stufe C / später)

- [ ] Mehrere Lagerorte / Filialen
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
| Migration | `database/migrations/065_stock_management.sql` |
| Bewegungen | `src/Inventory/StockMovementService.php` |
| Inventur | `src/Inventory/StockInventoryService.php` |
| UI | `views/modules/lager.php` |
| Beleg-Hook | `VoucherRepository::save()` / `delete()` |
