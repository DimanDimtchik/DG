# Lager- und Warenwirtschaft (Stufe A + B + C Teil)

Stand: **2026-09-16** · Migrationen **065–069**, **079–080**

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
- [x] **Inventur-Zähllisten PDF** — druckbares HTML (`download=inventur-print`, leer / mit Soll, gruppiert Ort·Halle·Regal)
- [x] **Stufe C (Teil)** — Strichcodes: Artikel (EAN/GTIN), Palette/Platz, Karton (`dg_stock_packages`)
- [x] **Wareneingang / Warenausgang** — Scan-UI, Karton anlegen, Belegbezug Lieferschein/Auftrag
- [x] **Etiketten-Druck** — Lagerstruktur → Etiketten, Avery L7163/L7160/… + Rollenformate
- [x] **Kamera-Scan** — Smartphone/Tablet (html5-qrcode, CODE128/EAN)
- [x] **Platz-Check** — Mini-Audit: Belegung, Reservierung, letzte Bewegungen
- [x] **Phase 1 Reservierung** — Angebot/AB ab Versendet/Angenommen; Anzeige Bestand/Reserviert/In Auslief./Verfügbar; Warnung bei Unterbestand (Migration **079**)
- [x] **Phase 2 Einkaufsquellen** — Lieferant/EK/Shop-URL je Artikel (mehrfach), Link **Nachbestellen** (Migration **080**)

## Strichcode-Ebenen

| Ebene | Speicherort | Auflösung |
|-------|-------------|-----------|
| Artikel | `dg_calendar_articles.gtin` (oder Artikelnummer) | Einzelstück |
| Palette/Platz | `dg_stock_places.barcode` (auto aus Positionscode) | Lagerplatz |
| Karton | `dg_stock_packages.barcode` | Menge + optional Platz |

## Etiketten (Aufkleber)

Einstellungen → **Lagerstruktur → Etiketten** — lesbarer Code + CODE128-Strichcode:

| Ebene | Text auf Etikett |
|-------|------------------|
| Lagerort | nur Ortkode (z. B. `WH1`) |
| Halle | Ort-Halle (z. B. `WH1-H1`) |
| Regal | Ort-Halle-Regal (z. B. `WH1-H1-R1`) |
| Stellplatz | vollständig inkl. PAL/KRT/EIN (z. B. `WH1-H1-R1-PAL01`) |

Formate u. a. **Avery L7163, L7160, L7159, L7161, L7162, L4776, L4778** sowie 62×29 mm, 100×50 mm. Vorschau im Browser; Feinabstimmung in der Druckersoftware.

## Platz-Check (Mini-Audit)

Lager → **Platz-Check**: Etikett scannen (Scanner, Kamera oder Eingabe). Anzeige:

- **Reservierung** — fest (Artikel) oder flexibel
- **Belegung** — `dg_stock_place_occupancy`, Kartons, Artikel-Stammplatz
- **Letzte Bewegungen** — aus `dg_stock_movements` am Platz

API: `/api/stock-scan?action=audit&code=…` oder manuell `/api/stock-scan?action=audit&level=place&entity_id=…` (`level`: `location`, `hall`, `shelf`, `place`)

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
| Migration | `065`–`069_stock_*.sql`, `079_stock_reservations.sql` |
| Stammdaten | `src/Inventory/StockStructureRepository.php` |
| Reservierung | `StockReservationService`, `StockAvailabilityService` |
| Strichcode | `src/Inventory/StockBarcodeService.php` |
| Kartons | `src/Inventory/StockPackageRepository.php` |
| Ein-/Ausgang | `src/Inventory/StockReceiptIssueService.php` |
| Scan-API | `src/Inventory/StockScanApi.php` → `/api/stock-scan` |
| Etiketten | `src/Inventory/StockLabelService.php`, `StockLabelPrintService.php` |
| UI | `views/modules/lager.php`, `views/settings/tab-lager-struktur.php` |
| Beleg-Hook | `VoucherRepository::save()` / `delete()` |
