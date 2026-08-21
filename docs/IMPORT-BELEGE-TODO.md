# Belege-Import — TODO

Die Verarbeitung von Belegen und Rechnungen beim Installationsimport ist **noch nicht implementiert**.

## Aktueller Stand

- Im Installationsassistenten (Schritt „Datenimport“) können Belegdateien (PDF, JPG, PNG) hochgeladen werden.
- Dateien werden nach `storage/vouchers/import-pending/` kopiert.
- In den Einstellungen wird `install_voucher_import_pending` gesetzt (Status: `todo`).

## Geplante Verarbeitung

1. OCR / Texterkennung (analog `assets/js/buchhaltung-import.mjs`)
2. Zuordnung zu Kontakten und Buchungskonten
3. Anlage als Belege in der Buchhaltung (`VoucherRepository`)
4. Fortschrittsanzeige im CRM (nicht nur Installation)

## Referenzen

- `src/Install/InstallVoucherImporter.php` — Staging-Logik
- `assets/js/buchhaltung-import.mjs` — Einzelbeleg-OCR im CRM
