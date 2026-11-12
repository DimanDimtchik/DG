# GEX45 — Plattenlayout (2× 512 GB NVMe)

> Stand: **2026-11-09**  
> Hardware: **GEX45-1** (Bestellnr. **B20261107-3544202**) · Standort **Finnland, HEL1** · OS **Ubuntu 26.04 LTS**  
> Laut Hetzner: **keine** NVMe-/RAM-Upgrades am GEX45 → [GPU-Server-Doku](https://docs.hetzner.com/robot/dedicated-server/server-lines/gpu-server/)  
> Speicher-Wachstum: **Hetzner-Backup/Storage Box** · später optional **SX65**  
> Verwandt: [`SERVER-MIGRATION.md`](SERVER-MIGRATION.md) · [`GEX45` Produktseite](https://www.hetzner.com/dedicated-rootserver/gex45/)

---

## Prinzipien

| Regel | Begründung |
|--------|------------|
| **Zwei NVMe getrennt** (kein RAID0) | Ein Plattenausfall zerstört nicht Code **und** Belege gleichzeitig |
| **NVMe0 = System + DB** | MariaDB und OS auf schneller, kleiner Platte; Backup-Ziel extern |
| **NVMe1 = Dateien + KI** | `storage/`, Ollama, Comfy — wächst mit Belegen/Modellen |
| **Externes Backup** | Täglich DB + `storage/` (restic/rsync) — Plattenersatz, kein Ersatz für Offsite |

**Startgröße All-Inkl:** ~250 GB — Budget unten; Reserve ≥ 15 % frei pro Platte anstreben.

---

## Physische Zuordnung (Install / Rescue / Erstformat)

Hetzner liefert **2× 512 GB** — in Robot/BIOS **NVMe0** vs **NVMe1** notieren (Slot kann tauschen).

**Empfehlung:**

| Physische SSD | Rolle | Dateisystem | Mount |
|---------------|--------|-------------|--------|
| **NVMe A** (z. B. `nvme0n1`) | System + Datenbank | ext4 (+ optional 1 GB EFI) | `/`, `/var/lib/mysql` |
| **NVMe B** (z. B. `nvme1n1`) | Daten + KI | ext4 | `/mnt/data` |

### NVMe A — Partitionen (Beispiel)

| Partition | Größe | Mount | Inhalt |
|-----------|-------|--------|--------|
| `p1` (optional) | 512 MB | `/boot/efi` | UEFI |
| `p2` | **Rest (~511 GB)** | `/` | Ubuntu, nginx, PHP, `/opt/eric`, Logs, CRM-**Code** |

MariaDB **explizit** auf derselben Platte, Datenverzeichnis Standard:

```text
/var/lib/mysql
```

Optional später: eigenes LV/Partition nur für MySQL — beim GEX45 **nicht nötig**, solange NVMe B die großen Dateien hält.

### NVMe B — eine Partition

| Partition | Größe | Mount |
|-----------|-------|--------|
| `p1` | **100 %** | `/mnt/data` |

**fstab** (Beispiel, UUID aus `blkid`):

```text
UUID=…  /              ext4  noatime,errors=remount-ro  0  1
UUID=…  /mnt/data      ext4  noatime                     0  2
```

`noatime` reduziert Schreiblast auf NVMe.

---

## Verzeichnisbaum unter `/mnt/data`

```text
/mnt/data/
├── crm-storage/          # Belege, Uploads, Mail-Anhänge (pro Instanz)
│   ├── ganz-om.de/
│   ├── dg.ganz-om.de/
│   ├── dg.ganz-soft.de/  # Master: nur storage falls nötig
│   ├── kontur-cosmetics.de/
│   └── …
├── ollama/               # OLLAMA_MODELS
├── comfy/                # ComfyUI repo + models/checkpoints (schlank halten)
├── backup-staging/       # kurzlebige Dumps vor restic (optional)
└── archive/              # ältere PDFs von Box zurückkopiert (optional)
```

**CRM-Code** bleibt auf NVMe A (klein, häufig deployt). Shared-Core-Plan: [`SHARED-CORE-REST-PLAN.md`](SHARED-CORE-REST-PLAN.md).

```text
/var/www/dg-crm/shared/             # Code-Kern (eine Version) — Deploy-Ziel
/var/www/dg-crm/instances/<host>/   # config/*.local.php, public/, symlink storage
```

Pro Instanz:

```text
/var/www/instances/ganz-om.de/storage  →  /mnt/data/crm-storage/ganz-om.de
```

Gleiches Muster wie auf Kasserver (eigene `storage/`, eigene DB), nur physisch auf NVMe B.

---

## Größenbudget (~250 GB Migration, Start)

| Bereich | Platte | Zielbelegung (Start) | Reserve |
|---------|--------|----------------------|---------|
| OS, nginx, PHP, ERiC, Logs | NVMe A | ~40 GB | |
| MariaDB (alle Instanzen) | NVMe A | ~80–120 GB | Wachstum mit Buchungen |
| CRM-PHP-Code | NVMe A | ~2–5 GB | |
| **Summe NVMe A** | | **~130–170 GB** | **~340 GB frei** |
| `crm-storage/` (Belege) | NVMe B | **~250 GB** | |
| Ollama (4× quant. Modelle) | NVMe B | ~80–120 GB | nicht alle gleichzeitig nötig |
| Comfy (2–3 Checkpoints) | NVMe B | ~20–40 GB | Rest auf Backup/Box |
| **Summe NVMe B** | | **~350–410 GB** | **~100 GB frei** knapp → ältere Belege früh **archivieren** |

Wenn **NVMe B > 85 %**: ältere Jahre nach **Hetzner-Backup/Box**, in CRM nur Referenz/Archivpfad — oder SX65 planen.

---

## Ollama & Comfy (Pfade / systemd)

```bash
# /etc/environment oder ollama.service drop-in
OLLAMA_MODELS=/mnt/data/ollama
OLLAMA_MAX_LOADED_MODELS=1
```

Ollama nur `127.0.0.1:11434`. CRM/Kichel: `config/kichel.local.php` → `ollama_url`.

ComfyUI z. B. `/mnt/data/comfy/ComfyUI`, Modelle:

```text
/mnt/data/comfy/ComfyUI/models/checkpoints/
```

Große Checkpoint-Sammlungen: primär **Backup/Box**, bei Bedarf syncen.

---

## MariaDB & Backups

| Was | Wo | Backup |
|-----|-----|--------|
| DB-Daten | `/var/lib/mysql` (NVMe A) | täglich `mariadb-backup` oder `mysqldump` → `/mnt/data/backup-staging` → **restic zur Box** |
| `storage/` | `/mnt/data/crm-storage` | restic/rsync (inkrementell) |
| ELSTER-Zertifikate | `storage/...` oder `config/` | **getrennt**, verschlüsselt, nicht in Git |

**Off-site-Skizze (PB3b ✅):** [`PB3B-BACKUP-OFFSITE.md`](PB3B-BACKUP-OFFSITE.md) — Storage Box + restic; Box-Bestellung/Install eigene Freigabe.

Vor DNS-Cutover: **Restore-Probe** (DB + eine Instanz `storage/`) dokumentieren — [`VOR-HETZNER-CHECK.md`](VOR-HETZNER-CHECK.md).

---

## Swap & RAM

64 GB RAM: **Swap optional** (z. B. 8 GB **Datei** auf NVMe A `/swapfile`), nur als Notfall bei großem `mysqldump` + Ollama gleichzeitig — im Alltag **kein** 32B-Modell während vollem DB-Import.

---

## Install-Checkliste (kurz)

1. Beide NVMe formatieren, Mounts wie oben, `fstab` testen (`mount -a`)
2. MariaDB installieren, `datadir` = `/var/lib/mysql`
3. `/mnt/data/...` anlegen, Rechte `deploy`/`www-data`
4. Erste Instanz: Code nach `/var/www/…`, `storage` symlink
5. `OLLAMA_MODELS`, Ollama pull (nach GPU-Treiber)
6. restic/Backup-Job auf Box — **bevor** großer rsync von All-Inkl
7. Nach Migration: `df -h` beide Platten + freie Inodes (`df -i`)

---

## IQ Strom (`office.iqstrom.com`)

Eigener Account auf All-Inkl — eigener Ordner unter `crm-storage/office.iqstrom.com/` und **eigene DB**; gleiche Symlink-Logik.

---

## Änderungshistorie

| Datum | Inhalt |
|-------|--------|
| 2026-11-08 | Erstfassung GEX45-only, 2×512 GB, Start ~250 GB |
