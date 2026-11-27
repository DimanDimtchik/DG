# Belege-Import — Stand

> **Ausbau:** FiBu-Positionen + Lager + Kichel-Strukturierung → tokenarm [`BELEG-OCR-LAGER-REST-PLAN.md`](BELEG-OCR-LAGER-REST-PLAN.md) (BO0–BO6).  
> Stand-Notiz: **2026-11-27** — Client-OCR für Kopf-Vorschläge ist da; Positionen/Lager/Kichel noch offen.

## Implementiert

Beim Installationsimport (Schritt „Datenimport“) werden Belegdateien (PDF, JPG, PNG, …) als **Beleg-Entwürfe** angelegt:

1. `VoucherRepository::createDraft()` — ohne Kontakt/Betrag/Konto
2. `VoucherFileStorage::attachFromPath()` — Datei am Entwurf
3. Fortschritt über `InstallImportRunner` / `install-import.js`
4. Hinweis + Filter „Nur Entwürfe“ unter **Buchhaltung → Belege**
5. API `POST /api/voucher?action=file_upload` für Sofort-Upload im Belegformular

OCR/Auslesen ist **clientseitig** (`assets/js/buchhaltung-import.mjs` / Tesseract / pdf.js / E-Rechnung-XML): Upload speichert die Datei, Analyse schlägt **FiBu-Kopfdaten** vor (Lieferant, Nr., Datum, Betrag/Steuer). **BO1b:** Button **„Erneut auslesen“** für bereits gespeicherte Anhänge (Install-Entwurf). Positionen werden nicht zuverlässig übernommen; **kein** Kichel und **kein** Lager-Anschluss (siehe BO-Plan).

## Status in Settings

`install_voucher_import_pending`: `status` = `processing` | `done` | `partial`, plus `voucher_ids`, `processed`, `file_count`.

## Referenzen

- `src/Install/InstallVoucherImporter.php`
- `src/Accounting/VoucherRepository.php` (`createDraft`)
- `src/Accounting/VoucherFileStorage.php` (`attachFromPath`)
- `assets/js/buchhaltung-import.mjs`
- [`BELEG-OCR-LAGER-REST-PLAN.md`](BELEG-OCR-LAGER-REST-PLAN.md)
