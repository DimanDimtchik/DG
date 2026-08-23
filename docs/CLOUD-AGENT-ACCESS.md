# Cloud-Agent / Cursor Browser — Zugänge (DG CRM)

Stand: 2026-08-23

> **Konsolidierter Stand:** [`docs/HANDOFF.md`](HANDOFF.md) · Agent-Rolle: [`AGENTS.md`](../AGENTS.md)

> **Kein Passwort-SSH.** All-Inkl akzeptiert den Key `id_ed25519_ganzom`. Ein „veraltetes Passwort“ ist normal — der Browser-Agent braucht den **Private Key** als Cursor-Secret.

---

## 1. Sofort am Handy: Cursor Secrets setzen

1. Öffnen: [cursor.com/dashboard/cloud-agents](https://cursor.com/dashboard/cloud-agents) → **Secrets**
2. Als **Runtime Secrets** anlegen (Werte nicht committen):

| Name | Wert |
|------|------|
| `DG_ALLINKL_SSH_PRIVATE_KEY` | Inhalt von `~/.ssh/id_ed25519_ganzom` (kompletter Key inkl. `BEGIN`/`END`) |
| `DG_ALLINKL_SSH_USER` | `ssh-w0217246` |
| `DG_ALLINKL_SSH_HOST` | `w0217246.kasserver.com` |
| `DG_CRM_SSH_HOST` | `dg.ganz-om.de` |

Optional (nur wenn Agent lokal DB/KAS braucht; sonst reichen SSH + Server-Configs):

| Name | Bedeutung |
|------|-----------|
| `DG_KAS_LOGIN` | `w0217246` |
| `DG_KAS_AUTH_DATA` | KAS-API-Passwort (liegt nur in GitHub Secrets / Server `kas.local.php`) |
| `DG_MASTER_DB_PASSWORD` | DB-Passwort Master-CRM |
| `DG_LIVE_DB_PASSWORD` | DB-Passwort Live-Root |
| `DG_LIVE_DB_NAME` / `DG_LIVE_DB_USER` | `d046f637` |

3. Neuen Cloud-Agent starten (alte Runs haben keine neuen Secrets).
4. Agent soll zuerst ausführen: `bash bin/cloud-agent-ssh-setup.sh`

**Häufige Fehler (2026-08-23 behoben):**

| Symptom | Ursache | Lösung |
|---------|---------|--------|
| `error in libcrypto` | Key im Secret als **eine Zeile** | `cloud-agent-ssh-setup.sh` auf `master` reformatiert automatisch |
| `Permission denied` trotz Key | `DG_ALLINKL_SSH_USER` = KAS-Login statt `ssh-…` | Secret auf SSH-User setzen (nicht `w0217246`) |

**Key vom PC holen** (einmalig, wenn du am Rechner bist):

```powershell
Get-Content $env:USERPROFILE\.ssh\id_ed25519_ganzom
```

Öffentlicher Fingerprint (Kontrolle): `ssh-ed25519 … ganz-om.de (w0217246)` — Datei `id_ed25519_ganzom.pub`.

---

## 2. GitHub Secrets (Backup, CI)

Im Repo `DimanDimtchik/DG` sind gesetzt:

- `DG_ALLINKL_SSH_PRIVATE_KEY`
- `DG_ALLINKL_SSH_PUBLIC_KEY`
- `DG_ALLINKL_SSH_USER` / `DG_ALLINKL_SSH_HOST` / `DG_CRM_SSH_HOST`
- `DG_KAS_LOGIN` / `DG_KAS_AUTH_DATA`
- `DG_MASTER_DB_PASSWORD` / `DG_LIVE_DB_PASSWORD` / `DG_LIVE_DB_NAME` / `DG_LIVE_DB_USER`

Hinweis: GitHub-Secrets sind **nicht** automatisch in Cursor Cloud Agents. Cursor-Dashboard ist maßgeblich.

---

## 3. Verbindungen (nicht geheim)

| Zweck | Host / Pfad | URL |
|-------|-------------|-----|
| SSH | `ssh-w0217246@w0217246.kasserver.com` (Alias `allinkl-ganzom`) | — |
| Master-CRM | `/www/htdocs/w0217246/dg.ganz-om.de` | https://dg.ganz-om.de |
| Live ganz-soft | `/www/htdocs/w0217246` | https://ganz-soft.de |
| Kontur | `/www/htdocs/w0217246/kontur-cosmetics.de` | (Kundeninstanz) |
| Shop | `/www/htdocs/w0217246/shop.ganz-soft.de` | https://shop.ganz-soft.de |
| Git | `https://github.com/DimanDimtchik/DG.git` | Branch `master` |
| KAS-Login | `w0217246` | KAS-API / Webmail |
| Live-DB | Name/User `d046f637` @ `localhost` | nur auf Server |
| Falscher Key | `id_ed25519_allinkl` → anderes Konto (`w01f1176`) — **nicht** für DG | |

Deploy vom Agent nach SSH-Setup:

```bash
bash bin/sync-crm-from-master.sh
# Shop: lokal deploy-shop / rsync analog
```

---

## 4. Was der Agent nicht braucht / nicht anfassen soll

- Stripe Live-Keys: bewusst noch nicht aktiv (`shop/config/stripe.local.php`)
- Passwort-Login SSH: deaktiviert bzw. veraltet — nur Key
- `bin/sync-crm-from-master.sh` mit `--delete` gegen Account-Root: **verboten** (Geschwister-Domains)
