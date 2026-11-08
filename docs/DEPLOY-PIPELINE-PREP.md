# Deploy-Pipeline Prep (PA3e) — rsync/SSH, ohne Cutover

> Stand: **2026-11-08** · Mini-ID **PA3e** · Fach: [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) A3  
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
IQ-Instanz: `IQ_ALLINKL_*` + `bin/cloud-agent-iq-ssh-setup.sh` (eigener Account).

**Excludes (Kern↔Instanz):** Locals, `storage/`, `logs/`, Nutzerordner (`klarwin/` …) — siehe `bin/sync-crm-from-master.sh` / `deploy-via-rsync.sh`.  
**Verboten:** Sync mit `--delete` gegen den **Account-Root**.

**Minify:** vor Upload `bin/minify-dg-css.sh` (PA3c ✅).

---

## 2 · Hetzner-Pipeline vorbereiten (ohne Cutover)

Ziel: sobald der Server steht (Phase B Hardware), kann Code **probeweise** per rsync landen — DNS bleibt auf All-Inkl.

### 2.1 Checkliste Prep

- [ ] Hetzner-Server bestellt/erreichbar (Phase B) — **oder** Staging-VPS nur zum Pipeline-Test
- [ ] OS Debian 12 / Ubuntu 22.04 · `Europe/Berlin` · User `deploy` (SSH-Key, kein Passwort-Login nötig)
- [ ] Verzeichnis laut Shared-Core-Skizze angelegt, z. B. `/var/www/dg-crm/shared/` ([`VOR-HETZNER-CHECK.md`](VOR-HETZNER-CHECK.md) §1.1)
- [ ] SSH vom PC **und** Cloud-Agent zum Hetzner-`deploy`-User getestet (`ssh -i … deploy@…`)
- [ ] Firewall: 22 (Admin-IP), 80/443 vorbereitet — **DNS noch nicht umstellen**
- [ ] rsync-Dry-Run skizziert (Befehl unten) — einmal gegen leeres `shared/`
- [ ] DocumentRoot/nginx **noch nicht** produktiv auf Kunden-Domains zeigen (optional: interne Staging-URL / Hosts-Eintrag)
- [ ] Locals/Secrets **nicht** aus All-Inkl ungefragt kopieren; auf Hetzner eigene `*.local.php`-Vorlagen bereithalten
- [ ] AVV/TOMs organisatorisch (PA3d) — parallel, nicht Blocker für Dry-Run

### 2.2 Geplanter Deploy-Befehl (Skizze — noch nicht Cutover)

Lokal/Agent nach SSH-Setup zum Hetzner-Host:

```bash
# Beispiel — Host/Pfad erst nach Server-Anlage setzen; DNS unverändert
rsync -az --delete --dry-run \
  --exclude 'config/*.local.php' \
  --exclude 'storage/' \
  --exclude 'logs/' \
  --exclude '.git/' \
  -e "ssh -i ~/.ssh/id_ed25519_ganzom -o BatchMode=yes" \
  ./ deploy@HETZNER_HOST:/var/www/dg-crm/shared/
```

Nach erfolgreichem Dry-Run: gleicher Befehl ohne `--dry-run` nur auf **Staging-/shared**-Pfad.  
Instanz-Hüllen (`instances/…/config`, `storage`) leer anlegen — **keine** Produktiv-DB importieren in PA3e.

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
