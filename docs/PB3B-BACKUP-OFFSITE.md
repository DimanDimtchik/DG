# PB3b — Backup / Off-site Ziel GEX45 (Skizze)

> Stand: **2026-11-12** · Mini-ID **PB3b** · Plan: [`PHASE-B-REST-PLAN.md`](PHASE-B-REST-PLAN.md) · Platten: [`GEX45-DISK-LAYOUT.md`](GEX45-DISK-LAYOUT.md)  
> **Nur Skizze.** Keine Storage-Box-Bestellung, kein restic-Install in dieser ID. Cutover-Daten = **PB4**.

---

## Entscheidung

| Punkt | Soll |
|-------|------|
| **Lokal NVMe** | Kurzlebige Staging-Dumps unter `/mnt/data/backup-staging/` — **kein** monatelanges Backup-Archiv auf den 2×512‑GB-NVMe |
| **Off-site** | **Hetzner Storage Box** (oder Robot Backup Space) als primäres externes Ziel — **nicht** SX65-HDD für den ersten Betrieb |
| **Werkzeug** | **restic** (verschlüsselt, inkrementell) von GEX → Box; optional ergänzend Robot-Snapshots falls gebucht |
| **Inhalt** | Pro Instanz: DB-Dump + `crm-storage/<host>/` + `config/*.local.php` — **kein** CRM-Code-Baum (kommt aus Deploy/Git) |
| **Nicht** | Ollama-/Comfy-Modelle als Pflicht-Backup (groß, neu ladbar); Secrets nur verschlüsselt in restic, nie in Git |

Begründung: GEX45 hat wenig freie NVMe-B-Reserve (~100 GB bei vollem Startbudget). Off-site entkoppelt Plattenausfall und Füllstand von der Wiederherstellung.

---

## Datenfluss (Soll)

```text
MariaDB (/var/lib/mysql)
  → mysqldump | mariadb-backup
  → /mnt/data/backup-staging/<datum>/

/mnt/data/crm-storage/<instanz>/
  + Instanz-Locals
  → restic backup → Storage Box (SFTP/REST)

Retention (Vorschlag, später justieren):
  staging lokal: 1–3 Tage
  restic: täglich · 14 täglich · 8 wöchentlich · 6 monatlich
```

CRM-UI-Backup ([`BACKUP-RESTORE-TEST.md`](BACKUP-RESTORE-TEST.md)) bleibt **Instanz-Hilfe**; Server-restic ist die **Maschinen- und Off-site-Schicht**.

---

## Was wohin

| Was | Staging (NVMe B) | Off-site (Box) |
|-----|------------------|----------------|
| DB alle Instanzen | ja (Dump) | ja (restic) |
| `crm-storage/*` | nein (Quelle ist schon NVMe B) | ja (direkt restic) |
| Locals | mit Instanz-Backup / staging | ja |
| `/var/www/dg-crm/shared` | nein | optional selten; Deploy reicht meist |
| ELSTER-Zertifikate | getrennt, verschlüsselt | ja, eigener restic-Pfad oder starkes Include |
| Ollama/Comfy | nein Pflicht | nein Pflicht |

Bei **NVMe B > 85 %**: ältere Belegjahre zuerst auf Box archivieren (Disk-Layout § Größenbudget), nicht „mehr Backup auf dieselbe Platte“.

---

## Off-site-Produkt (noch nicht bestellt)

| Option | Rolle |
|--------|--------|
| **Hetzner Storage Box** | Empfohlenes PB3b-Ziel: SFTP, Quota wählbar, restic-kompatibel |
| Robot Backup | Zusatz/Snapshot-ähnlich; ersetzt restic-App-Backup nicht allein |
| SX65 | bewusst **später** / Wachstum — nicht Voraussetzung für PB4 |

Bestellung + Zugangsdaten + erster `restic init` = **eigene Freigabe** (Ops), nicht PB3b.

---

## Vor PB4 / Betrieb

- [ ] Storage Box (o. ä.) gebucht · Zugang nur auf Server/Secret-Store
- [ ] `restic` auf GEX · Repo init · Verschlüsselungspassphrase offline notiert
- [ ] Erster Lauf: eine Probe-Instanz (z. B. Dev-Daten auf GEX) · Restore-Stichprobe Datei + Dump-Header
- [ ] Cron/systemd-Timer täglich nach Berlin-Zeit
- [ ] Monitoring: Job-Exit + freie Box-Quota

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`GEX45-DISK-LAYOUT.md`](GEX45-DISK-LAYOUT.md) | NVMe A/B · staging-Pfad |
| [`BACKUP-RESTORE-TEST.md`](BACKUP-RESTORE-TEST.md) | Instanz-Backup-Übung All-Inkl |
| [`PB3A-CUTOVER-CHECKLISTE.md`](PB3A-CUTOVER-CHECKLISTE.md) | Dump-Reihenfolge Cutover |
| [`CRON-AUTOSTART.md`](CRON-AUTOSTART.md) | bisherige Kasserver-Backup-Idee |
