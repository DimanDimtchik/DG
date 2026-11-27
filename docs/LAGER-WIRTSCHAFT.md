# Lager- und Warenwirtschaft (Stufe A + B + C Teil)

Stand: **2026-11-27** · Migrationen **065–069**, **079–081** · Beleg-OCR→Lager: [`BELEG-OCR-LAGER-REST-PLAN.md`](BELEG-OCR-LAGER-REST-PLAN.md) (BO5)

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
- [x] **Phase 3 Einkaufsliste** — Auto-Einträge unter Min / Fehlmenge, Subtabs Einkaufsliste + Ignoriert, Ignore/Bestellt/Erledigt (Migration **081**)
- [x] **Phase 4 Feinschliff** — In-Auslieferung aus LS-Status `sent`; Live-Hinweis Verfügbarkeit im Beleg-Editor; Einstellungen Unterbestand (Lagerstruktur → Einkauf)
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

## Beleg-OCR → Lager (Pipeline + Grenzen)

Eingangsbeleg im Formular (OCR/E-Rechnung/Kichel): Positionen können dem Katalog zugeordnet oder neu angelegt werden; optional EK-Quelle; Wareneingang nur als **Vorschlag nach Klick**. Microplan: [`BELEG-OCR-LAGER-REST-PLAN.md`](BELEG-OCR-LAGER-REST-PLAN.md) · Import-Ist: [`IMPORT-BELEGE-TODO.md`](IMPORT-BELEGE-TODO.md).

| Pfad | Mechanik | Bestätigung |
|------|----------|-------------|
| **A) Beleg-Sync** | Speichern → `StockMovementService::syncForVoucher` | Beleg speichern (Items mit `article_id`, product+`track_stock`, kein Draft) |
| **B) Manuell / OCR** | `StockReceiptIssueService` (`processReceipt`) · UI Lager oder Beleg-Panel „Wareneingang buchen“ | **Expliziter Klick**; OCR nutzt **nur B**, nicht still A |

**Grenzen (GoBD):**

- Keine Auto-Lagerbewegung aus OCR/Apply/Kichel
- Neuanlage aus Beleg: `track_stock` aus → Zeile erscheint im WE-Vorschlag unter „Übersprungen“, bis Lagerführung im Stamm aktiv
- Ausgabe-Belege schreiben FiBu oft nur als Splits (kein `items[]`) — Path A greift dann nicht; Path B braucht Zuordnung + gespeicherten Beleg
- Doppelter WE am selben Beleg: geblockt, wenn bereits `receipt`/`purchase`-Bewegung existiert

## Technik

| Komponente | Pfad |
|------------|------|
| Migration | `065`–`069_stock_*.sql`, `079_stock_reservations.sql`, `080_article_purchase_sources.sql`, `081_purchase_list_items.sql` |
| Stammdaten | `src/Inventory/StockStructureRepository.php` |
| Reservierung | `StockReservationService`, `StockAvailabilityService` |
| Einkaufsliste | `PurchaseListService`, `PurchaseListRepository`, `StockPurchaseSettings` |
| Strichcode | `src/Inventory/StockBarcodeService.php` |
| Kartons | `src/Inventory/StockPackageRepository.php` |
| Ein-/Ausgang | `src/Inventory/StockReceiptIssueService.php` |
| Scan-API | `src/Inventory/StockScanApi.php` → `/api/stock-scan` |
| Etiketten | `src/Inventory/StockLabelService.php`, `StockLabelPrintService.php` |
| UI | `views/modules/lager.php`, `views/settings/tab-lager-struktur.php` |
| Beleg-Hook | `VoucherRepository::save()` / `delete()` → Path A |
| OCR-WE (Path B) | `StockReceiptIssueService::proposalFromExtractLines` / `bookExtractProposal` · `VoucherApi` `stock_receipt*` |
