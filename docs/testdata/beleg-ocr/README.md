# Beleg-OCR Testdaten (BO2 / BO3e)

Demo-Dateien ohne echte Firmendaten. Nutzung:

```bash
node bin/beleg-ocr-bo2-fixture-selftest.mjs
php bin/beleg-ocr-bo3e-gobd-selftest.php
node bin/beleg-ocr-bo4d-einvoice-apply-selftest.mjs
php bin/beleg-ocr-bo5f-lager-smoke-selftest.php
```

| Datei | Inhalt |
|-------|--------|
| `cii-invoice.xml` | CII mit Artikel + Dienstleistung |
| `ubl-invoice.xml` | UBL mit einer Artikelzeile |
| `invoice-ocr-sample.txt` | OCR-/Textlayer-Rohtext (Kopf + 2 Positionen) für Extrakt/GoBD |
