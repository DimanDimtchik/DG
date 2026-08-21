# Belege-Import — Stand

## Implementiert

Beim Installationsimport (Schritt „Datenimport“) werden Belegdateien (PDF, JPG, PNG, …) als **Beleg-Entwürfe** angelegt:

1. `VoucherRepository::createDraft()` — ohne Kontakt/Betrag/Konto
2. `VoucherFileStorage::attachFromPath()` — Datei am Entwurf
3. Fortschritt über `InstallImportRunner` / `install-import.js`
4. Hinweis + Filter „Nur Entwürfe“ unter **Buchhaltung → Belege**
5. API `POST /api/voucher?action=file_upload` für Sofort-Upload im Belegformular

OCR bleibt **clientseitig** (`assets/js/buchhaltung-import.mjs` / Tesseract) — beim Öffnen eines Entwurfs kann die angehängte Datei ausgewertet und das Formular vervollständigt werden.

## Status in Settings

`install_voucher_import_pending`: `status` = `processing` | `done` | `partial`, plus `voucher_ids`, `processed`, `file_count`.

## Referenzen

- `src/Install/InstallVoucherImporter.php`
- `src/Accounting/VoucherRepository.php` (`createDraft`)
- `src/Accounting/VoucherFileStorage.php` (`attachFromPath`)
- `assets/js/buchhaltung-import.mjs`
