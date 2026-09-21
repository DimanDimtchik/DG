# Demo-Kontoauszüge Bankabgleich

- **q1-2026** — 1. Quartal 2026
- **q2a-2026** — 2. Quartal 2026, Teil 1 (Apr–Mai)
- **q2b-2026** — 2. Quartal 2026, Teil 2 (Juni)

Formate: CAMT.053 (`.camt053.xml`) und MT940 (`.mt940.sta`).
Import in die DB: `php bin/academy-bankabgleich-demo-statements.php` (ohne `--write-only`).
CAMT wird importiert (Umsätze bleiben). MT940-Dateien dienen als alternatives Format — gleicher Inhalt, bei Zweitimport als Duplikat erkannt.
