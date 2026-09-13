# Lager- und Warenwirtschaft (Stufe A + B + C Teil)

Stand: 2026-09-13 · Migrationen **065–069**

## Umgesetzt

- [x] **Stufe A** — Ein Lager, Bestand pro Artikel (`track_stock`, `stock_qty`, `min_stock`)
- [x] **Positionscode** Ort-Halle-Regal-Platz (Migration 066)
- [x] **Lagerstruktur-Stammdaten** — Ort, Halle, Regal/Stellplätze, Platz fest/flexibel (Migration 067–068)
- [x] Einstellungen → **Lagerstruktur** (CRUD Lagerorte, Hallen, Regale)
- [x] Bewegungslog `dg_stock_movements` (GoBD-Nachvollziehbarkeit)
- [x] Automatik aus Belegen: Einkauf (+), Verkauf (−), Lieferschein (−), Kundengutschrift (+)
- [x] Rechnung ohne Doppelbuchung, wenn Lieferschein in Belegkette (Migration 069)
- [x] Manuelle Korrektur mit Pflicht-Grund
- [x] Menü **Lager** (Bestand, Wareneingang, Warenausgang, Bewegungen, Inventur, CSV)
- [x] **Stufe B** — Inventur-Assistent (Stichtag, Zählung, Differenz buchen, CSV)
- [x] **Stufe C (Teil)** — Strichcodes: Artikel (EAN/GTIN), Palette/Platz, Karton (`dg_stock_packages`)
- [x] **Wareneingang / Warenausgang** — Scan-UI, Karton anlegen, Belegbezug Lieferschein/Auftrag

## Strichcode-Ebenen

| Ebene | Speicherort | Auflösung |
|-------|-------------|-----------|
| Artikel | `dg_calendar_articles.gtin` (oder Artikelnummer) | Einzelstück |
| Palette/Platz | `dg_stock_places.barcode` (auto aus Positionscode) | Lagerplatz |
| Karton | `dg_stock_packages.barcode` | Menge + optional Platz |

## Bewusst nicht (später)

- [ ] Chargen, Seriennummern, MHD
- [ ] Mehrere Mandanten-Lager / Filial-Sync
- [ ] Automatische SKR-Bestandskonten-Buchung
- [ ] Shop-Bestandsabzug (`shop.ganz-soft.de`)

## Test

```bash
php bin/stock-selftest.php
```

Manuell: Lager → Wareneingang/Warenausgang, Strichcode scannen, Lieferschein verknüpfen.

## Technik

| Komponente | Pfad |
|------------|------|
| Migration | `065`–`069_stock_*.sql` |
| Stammdaten | `src/Inventory/StockStructureRepository.php` |
| Strichcode | `src/Inventory/StockBarcodeService.php` |
| Kartons | `src/Inventory/StockPackageRepository.php` |
| Ein-/Ausgang | `src/Inventory/StockReceiptIssueService.php` |
| Scan-API | `src/Inventory/StockScanApi.php` → `/api/stock-scan` |
| UI | `views/modules/lager.php`, `assets/js/lager-scan.js` |
| Beleg-Hook | `VoucherRepository::save()` / `delete()` |
