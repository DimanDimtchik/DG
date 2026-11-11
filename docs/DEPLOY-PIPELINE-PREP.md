# Deploy-Pipeline Prep (PA3e) — rsync/SSH, ohne Cutover

> Stand: **2026-11-11** · Mini-ID **PA3e** · Nachzug **SC4a** (Shared-Ziel GEX)  
> Fach: [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) A3 · Shared: [`SHARED-CORE-REST-PLAN.md`](SHARED-CORE-REST-PLAN.md)  
> **Nur Dokumentation / Checkliste.** Kein DNS-Cutover, kein DB-Umzug, kein Produktiv-Traffic auf Hetzner.

Verwandt: [`CLOUD-AGENT-ACCESS.md`](CLOUD-AGENT-ACCESS.md) · [`VOR-HETZNER-CHECK.md`](VOR-HETZNER-CHECK.md) §1.1 Shared-Core · [`SERVER-MIGRATION.md`](SERVER-MIGRATION.md) · [`PC-HANDOFF.md`](PC-HANDOFF.md)

---

## 1 · Ist-Pipeline (All-Inkl — weiter nutzen)

```text
PC / Cloud-Agent
  │  minify (dg.app.min.css)
  │  rsync/scp über SSH-Key
  ▼
Code-Kern  dg.ganz-soft.de   ← Deploy-Ziel
  │  bash bin/sync-crm-from-master.sh
  ▼
Instanzen  ganz-om.de · dg.ganz-om.de · ganz-soft.de · kontur-…
           (eigene DB + config/*.local.php + storage/)
```

| Weg | Befehl / Skript | Ziel |
|-----|-----------------|------|
| Cloud SSH | `bash bin/cloud-agent-ssh-setup.sh` → `SSH_OK` | Key/Alias |
| Cloud Deploy | `bash bin/deploy-via-rsync.sh` | Code-Kern (`dg.ganz-soft.de`) |
| Cloud optional | `bash bin/deploy-via-rsync.sh --all` | Kern + einige Instanzen direkt |
| Alternative | `bash bin/deploy-via-sftp.sh` | Upload Kern |
| PC | `deploy.bat` / `deploy.ps1` | Kern (intern minify) |
| Nach Kern | auf Server: `bash bin/sync-crm-from-master.sh` | Instanzen vom Kern |

**Secrets (Cursor / Umgebung):** `DG_ALLINKL_SSH_USER` (= `ssh-…`, nicht KAS-Login), `DG_ALLINKL_SSH_HOST`, `DG_ALLINKL_SSH_PRIVATE_KEY`.  
IQ-Instanz: `IQ_ALLINKL_*` + `bin/cloud-agent-iq-ssh-setup.sh` (eigener Account) · Sync Kern→Instanz: `bin/sync-crm-to-iqstrom.sh`.

**Excludes (Kern↔Instanz):** Locals, `storage/`, `logs/`, Nutzerordner (`klarwin/` …) — siehe `bin/sync-crm-from-master.sh` / `deploy-via-rsync.sh`.  
**Verboten:** Sync mit `--delete` gegen den **Account-Root**.

**Minify:** vor Upload `bin/minify-dg-css.sh` (PA3c ✅).

---

## 2 · Hetzner-Pipeline vorbereiten (ohne Cutover)

Ziel: sobald der Server steht (Phase B Hardware), kann Code **probeweise** per rsync landen — DNS bleibt auf All-Inkl.

### 2.1 Checkliste Prep

- [x] Hetzner-Server **GEX45** bestellt (2026-11) — erreichbar nach Lieferung (Phase B / [`PHASE-B-REST-PLAN.md`](PHASE-B-REST-PLAN.md))
- [x] OS **Ubuntu 26** · `Europe/Berlin` · User `deploy` (SSH-Key, kein Passwort-Login)
- [x] Verzeichnis laut Shared-Core-Skizze angelegt, z. B. `/var/www/dg-crm/shared/` ([`VOR-HETZNER-CHECK.md`](VOR-HETZNER-CHECK.md) §1.1)
- [x] SSH vom PC **und** Cloud-Agent zum Hetzner-`deploy`-User getestet (`ssh -i … deploy@…`)
- [x] Firewall: 22/80/443 — **DNS noch nicht umstellen**
- [x] Probe 2026-11-11: `tar`→`deploy@2.29.242.28:/var/www/dg-crm/shared/` (~3697 Dateien; lokal kein rsync)
- [ ] DocumentRoot/nginx **noch nicht** produktiv auf Kunden-Domains zeigen (optional: interne Staging-URL / Hosts-Eintrag)
- [ ] Locals/Secrets **nicht** aus All-Inkl ungefragt kopieren; auf Hetzner eigene `*.local.php`-Vorlagen bereithalten
- [ ] AVV/TOMs organisatorisch (PA3d) — parallel, nicht Blocker für Dry-Run

### 2.2 Deploy-Ziel GEX Shared (**SC4a** ✅)

| | All-Inkl | GEX45 |
|--|----------|--------|
| **Pfad** | Code-Kern Full-Tree | **`/var/www/dg-crm/shared/`** |
| **User** | `DG_ALLINKL_SSH_USER` | `deploy` (`Hetzner_user`) |
| **Skript heute** | `deploy-via-rsync.sh` / `deploy.bat` | noch Skizze — **SC4b** |
| **Nach Deploy** | `sync-crm-from-master.sh` | Instanz-Hüllen unverändert (Locals/Storage eigenen Pfad) |

Kanonsiche Tabelle + Excludes + PC/Cloud-Beispiele: [`SHARED-CORE-REST-PLAN.md`](SHARED-CORE-REST-PLAN.md) § SC4a.  
Secrets Hetzner: `Hetzner_user` / `Hetrzner_Host` / `Hetzner_Private_Key` — siehe [`CLOUD-AGENT-ACCESS.md`](CLOUD-AGENT-ACCESS.md) (nicht den All-Inkl-Key für GEX annehmen).

```bash
# Skizze Cloud — Dry-Run; Live-Skript = SC4b
rsync -az --delete --dry-run \
  --exclude 'config/*.local.php' \
  --exclude 'storage/' \
  --exclude 'logs/' \
  --exclude '.git/' \
  --exclude 'klarwin/' \
  -e "ssh -i ~/.ssh/id_ed25519_hetzner_gex -o BatchMode=yes -o IdentitiesOnly=yes" \
  ./ "${Hetzner_user}@${Hetrzner_Host}:/var/www/dg-crm/shared/"
```

Nach erfolgreichem Dry-Run: gleicher Befehl ohne `--dry-run` nur auf **`…/shared/`**.  
Instanz-Hüllen (`instances/…/config`, `storage`) **nicht** mit Shared-rsync — **keine** Produktiv-DB in PA3e/SC4a.

### 2.3 Was absichtlich **nicht** in PA3e liegt

| Thema | Wann |
|-------|------|
| DNS Cutover / Kunden-Traffic | Phase **B** |
| DB-Dump All-Inkl → Hetzner | Phase **B** |
| `storage/`-Migration | Phase **B** |
| Live-ELSTER/ERiC | Phase **C** |
| Shared-Core-Symlinks produktiv scharf | nach erfolgreichem B-Cutover |

---

## 3 · Abnahme PA3e

- [x] Ist-Pipeline (rsync/SSH/Sync) in einem Doc nachvollziehbar
- [x] Hetzner-Prep-Checkliste + Dry-Run-Skizze ohne Cutover
- [x] Verweise auf Shared-Core, Secrets, Verbote

**Erledigt:** 2026-11-08 (dieser Stand).

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`CLOUD-AGENT-ACCESS.md`](CLOUD-AGENT-ACCESS.md) | SSH-Setup, Secrets, Verbote |
| [`VOR-HETZNER-CHECK.md`](VOR-HETZNER-CHECK.md) | Shared-Core-Ordnerplan §1.1 |
| [`SERVER-MIGRATION.md`](SERVER-MIGRATION.md) | Hetzner-Hardware / ERiC-Kontext |
| [`SERVER-DOMAIN-DATENBANK.md`](SERVER-DOMAIN-DATENBANK.md) | Domain-Rollen All-Inkl |
| [`PHASE-A-REST-PLAN.md`](PHASE-A-REST-PLAN.md) | PA3e |
