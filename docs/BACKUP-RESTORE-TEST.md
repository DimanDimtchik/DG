# Backup/Restore-Test — Notiz (PA3b)

> Stand: **2026-11-08** · Mini-ID **PA3b** · Bezug: [`VOR-HETZNER-CHECK.md`](VOR-HETZNER-CHECK.md) §4 · UI: Einstellungen → Backup  
> Getestet auf **Dev** `ganz-om.de` (Account `w0217246`). Kein Cutover, keine Produktiv-DB überschrieben.

---

## Ergebnis

| Check | Ergebnis |
|-------|----------|
| Selftest Dateien/Excludes | ✅ `php bin/backup-instance-data-selftest.php` |
| Selftest UI (kein Restore-Button) | ✅ `php bin/backup-ui-render-selftest.php` |
| Manuelles Backup Dev | ✅ `BackupService::createBackup()` → `storage/backups/2026-11-08_161732` |
| Inhalt Dump | ✅ `database.sql` ~2,1 MB, MariaDB-Dump, 169× `CREATE TABLE` |
| Inhalt Dateien | ✅ `files/config/*.local.php`, `files/storage/…` |
| Excludes | ✅ kein verschachteltes `storage/backups`, kein `storage/cache` |
| Restore-Smoke | ✅ Staging unter `/tmp/…`: SQL kopiert + `files/` entpackt, `SQL_SMOKE_OK`, Staging gelöscht |
| Live-DB-Restore | ❌ bewusst **nicht** gegen Live ausgeführt |

**Fazit:** Instanzdaten-Backup ist betriebsfähig; Wiederherstellung der **Dateien** in Staging nachgewiesen. Kompletter DB-Restore bleibt Server-Protokoll (siehe unten) — vor Hetzner einmal gegen leere Test-DB üben.

---

## Was gesichert wird (Soll = Ist)

| Bestandteil | Ort im Backup |
|-------------|----------------|
| DB-Dump | `database.sql` |
| Instanz-`storage/` | `files/storage/` (ohne `backups/`, `cache/`) |
| Locals | `files/config/*.local.php` |
| CRM-Code | **nicht** (kommt aus Deploy/Git) |

Tageslauf: `BackupService::runIfDue()` · manuell: Einstellungen → Backup · Retention: max. 14 Ordner.

---

## Restore-Protokoll (Server, manuell)

1. Wartungsfenster; CRM nicht schreiben lassen.
2. Neuesten Ordner unter `storage/backups/YYYY-MM-DD_HHMMSS/` wählen.
3. **Dateien:** `files/config/` und `files/storage/` gezielt zurückkopieren (nicht blind ganzes `storage/backups` überschreiben).
4. **DB:** `mysql … < database.sql` nur auf **leere/Test-DB** oder nach dokumentiertem Dump der Ist-DB; nie „aus Neugier“ auf Live ohne Rollback-Plan.
5. Kurz prüfen: Login, ein Beleg-PDF aus `storage/vouchers`, `config/database.local.php` zeigt auf erwartete DB.
6. Ergebnis + Uhrzeit in Betriebsnotiz festhalten.

---

## Hinweise / Aufräumen

- Unter `ganz-om.de/storage/backups/` lag zusätzlich eine alte `wordpress-*.sql` (nicht vom CRM-BackupService) — bei Gelegenheit entfernen oder archivieren, damit die Liste klar bleibt.
- IQ-/Kunden-Instanzen: denselben Smoke **vor Cutover** wiederholen (PA3b hier = Dev-Nachweis).

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`CRON-AUTOSTART.md`](CRON-AUTOSTART.md) | Tages-Backup-Konzept |
| [`DEPLOY-PIPELINE-PREP.md`](DEPLOY-PIPELINE-PREP.md) | Deploy ohne Cutover |
| [`VOR-HETZNER-CHECK.md`](VOR-HETZNER-CHECK.md) | Vor-Umzug-Reihenfolge |
| [`PHASE-A-REST-PLAN.md`](PHASE-A-REST-PLAN.md) | PA3b |
