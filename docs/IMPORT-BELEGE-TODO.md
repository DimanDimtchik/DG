# Belege-Import — Stand

> **Ausbau:** FiBu-Positionen + Lager + Kichel → [`BELEG-OCR-LAGER-REST-PLAN.md`](BELEG-OCR-LAGER-REST-PLAN.md) (**BO0–BO6 ✅** 2026-11-27).  
> Stand-Notiz: **2026-11-27** — Pipeline Kopf + Positionen + Katalog/EK/WE-Vorschlag; Mensch bestätigt; kein stilles Buchen.

## Implementiert

Beim Installationsimport (Schritt „Datenimport“) werden Belegdateien (PDF, JPG, PNG, …) als **Beleg-Entwürfe** angelegt:

1. `VoucherRepository::createDraft()` — ohne Kontakt/Betrag/Konto
2. `VoucherFileStorage::attachFromPath()` — Datei am Entwurf
3. Fortschritt über `InstallImportRunner` / `install-import.js`
4. Hinweis + Filter „Nur Entwürfe“ unter **Buchhaltung → Belege**
5. API `POST /api/voucher?action=file_upload` für Sofort-Upload im Belegformular

## Pipeline Beleg-OCR → FiBu + Lager (Kurz)

```
Datei (PDF/Bild/XML)
  → Client: speichern + Rohtext (E-Rechnung XML · Textlayer · Tesseract, max. 5 Seiten)
  → optional Server: Kichel voucher_extract → BO2-JSON (Kopf + lines[])
  → UI: Vorschläge prüfen
       → Übernehmen: Kopf + Positionen (Ausgabe=Splits, Erlös=items)
       → optional: Katalog Zuordnen / Neuanlage / EK speichern / WE buchen
```

| Stufe | Was | Grenze |
|-------|-----|--------|
| Roh / Erneut auslesen | Client Tesseract/pdf.js/XML | Kein Server-Tesseract |
| Extrakt | BO2-Schema; Kichel ordnet Text | Soft-Fail ohne Ollama; Beträge PHP-Abgleich |
| Apply | User-Klick „Vorschläge übernehmen“ | Keine Auto-Buchung ins Journal |
| Katalog | Match / Neuanlage nach Klick | Neuanlage: `track_stock` aus |
| EK | Purchase-Source optional | Preferred nur per Checkbox |
| Wareneingang | Vorschlag + Klick nach gespeichertem Beleg | Nur `product`+`track_stock`; kein `syncForVoucher` aus OCR |

Details und Mini-IDs: [`BELEG-OCR-LAGER-REST-PLAN.md`](BELEG-OCR-LAGER-REST-PLAN.md) · Lager-Seite: [`LAGER-WIRTSCHAFT.md`](LAGER-WIRTSCHAFT.md) § Beleg-OCR.

**Rest (nicht blocker):** BO1d Dev-Smoke PDF/Scan · UI-Smoke nach Deploy.

## Status in Settings

`install_voucher_import_pending`: `status` = `processing` | `done` | `partial`, plus `voucher_ids`, `processed`, `file_count`.

## Referenzen

- `src/Install/InstallVoucherImporter.php`
- `src/Accounting/VoucherRepository.php` (`createDraft`)
- `src/Accounting/VoucherFileStorage.php` (`attachFromPath`)
- `src/Accounting/VoucherExtractService.php` / `VoucherApi` (`voucher_extract`, `article_*`, `stock_receipt*`)
- `assets/js/buchhaltung-import.mjs`
- [`BELEG-OCR-LAGER-REST-PLAN.md`](BELEG-OCR-LAGER-REST-PLAN.md)
- Testdaten: `docs/testdata/beleg-ocr/` · `php bin/beleg-ocr-bo5f-lager-smoke-selftest.php`
