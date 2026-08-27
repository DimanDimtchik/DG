# Cloud-Agent / Cursor Browser — Zugänge (DG CRM)

Stand: 2026-08-27

> **Kein Passwort-SSH.** All-Inkl akzeptiert den Key `id_ed25519_ganzom`. Ein „veraltetes Passwort“ ist normal — der Browser-Agent braucht den **Private Key** als Cursor-Secret.

---

## 1. Sofort am Handy: Cursor Secrets setzen

1. Öffnen: [cursor.com/dashboard/cloud-agents](https://cursor.com/dashboard/cloud-agents) → **Secrets**
2. Als **Runtime Secrets** anlegen (Werte nicht committen):

| Name | Wert |
|------|------|
| `DG_ALLINKL_SSH_PRIVATE_KEY` | Inhalt von `~/.ssh/id_ed25519_ganzom` (kompletter Key inkl. `BEGIN`/`END`) |
| `DG_ALLINKL_SSH_USER` | **SSH-Benutzer** aus KAS → Tools → SSH-Zugänge: `ssh-XXXXXXX` (**nicht** der KAS-Weblogin!) |
| `DG_ALLINKL_SSH_HOST` | `[login].kasserver.com` |
| `DG_CRM_SSH_HOST` | `dg.ganz-om.de` (optional) |

### Wichtig: SSH-User ≠ KAS-Login

| Feld | Beispielformat | Secret |
|------|----------------|--------|
| **KAS-Login** (Web/API) | kurzer Name, ~8 Zeichen | `DG_KAS_LOGIN` — **nicht** für SSH |
| **SSH-Benutzer** | beginnt immer mit `ssh-` | `DG_ALLINKL_SSH_USER` |

Häufiger Fehler: KAS-Login in `DG_ALLINKL_SSH_USER` → am PC funktioniert SSH, im Cloud Agent `Permission denied (publickey)`, obwohl der Private Key korrekt ist.


Optional (nur wenn Agent lokal DB/KAS braucht; sonst reichen SSH + Server-Configs):

| Name | Bedeutung |
|------|-----------|
| `DG_KAS_LOGIN` | KAS-Weblogin (nicht der SSH-User!) |
| `DG_KAS_AUTH_DATA` | KAS-API-Passwort (liegt nur in GitHub Secrets / Server `kas.local.php`) |
| `DG_MASTER_DB_PASSWORD` | DB-Passwort Master-CRM |
| `DG_LIVE_DB_PASSWORD` | DB-Passwort Live-Root |
| `DG_LIVE_DB_NAME` / `DG_LIVE_DB_USER` | `d046f637` |

3. Neuen Cloud-Agent starten (alte Runs haben keine neuen Secrets).
4. Agent soll zuerst ausführen: `bash bin/cloud-agent-ssh-setup.sh`

**Key vom PC holen** (einmalig, wenn du am Rechner bist):

```powershell
Get-Content $env:USERPROFILE\.ssh\id_ed25519_ganzom -Raw
```

**SSH-User vom PC prüfen** (Wert für `DG_ALLINKL_SSH_USER`):

```powershell
ssh -G allinkl-ganzom | findstr /i "^user "
```

Fingerprint Private Key (Kontrolle): `SHA256:RoWIYpvE7HH1cQbVS7YUmSouM4pGvYv33i3AEiFriPw` — Kommentar in `id_ed25519_ganzom.pub`: `ganz-om.de (s000e3d3)`.

**Nicht verwenden:** Secrets mit Prefix `IQ_ALLINKL_*` — anderer All-Inkl-Account (`w01f1176`), nicht DG (`s000e3d3`).


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
| Live ganz-soft | `/www/htdocs/w0217246/ganz-soft.de` | https://ganz-soft.de |
| Kontur | `/www/htdocs/w0217246/kontur-cosmetics.de` | (Kundeninstanz) |
| Shop | `/www/htdocs/w0217246/shop.ganz-soft.de` | https://shop.ganz-soft.de |
| Git | `https://github.com/DimanDimtchik/DG.git` | Branch `master` |
| KAS-Login | `w0217246` | KAS-API / Webmail |
| Live-DB | Name/User `d046f637` @ `localhost` | nur auf Server |
| Falscher Key | `id_ed25519_allinkl` → anderes Konto (`w01f1176`) — **nicht** für DG | |

Deploy vom Agent nach SSH-Setup:

```bash
bash bin/cloud-agent-ssh-setup.sh   # muss SSH_OK ausgeben
bash bin/sync-crm-from-master.sh    # Master → ganz-soft.de/ + kontur-cosmetics.de
# Shop: Dateien nach shop.ganz-soft.de/ (eigenes Projekt, nicht im CRM-Sync)
# Wartungsmodus: https://shop.ganz-soft.de/admin/login (Passwort in config/admin.local.php auf Server)
```

Erst Master per `scp`/`deploy.bat` auf **dg.ganz-om.de** hochladen, dann `sync-crm-from-master.sh`.

**PHP 8.5:** Erst alle Domains im KAS auf 8.5 stellen, dann testen — [`docs/PHP85-TEST-HANDOFF.md`](PHP85-TEST-HANDOFF.md).

---

## 4. Fehlerbehebung SSH im Cloud Agent

### Symptom: `Permission denied (publickey)` — PC geht, Agent nicht

**Diagnose (August 2026):** Der Private Key im Secret war **korrekt** (gleicher Fingerprint wie am PC). Ursache war **`DG_ALLINKL_SSH_USER`**: KAS-Login (~8 Zeichen) statt SSH-User (`ssh-…`).

| Prüfung | Erwartung |
|---------|-----------|
| `DG_ALLINKL_SSH_PRIVATE_KEY` | Fingerprint `SHA256:RoWIYpvE7HH1cQbVS7YUmSouM4pGvYv33i3AEiFriPw` |
| `DG_ALLINKL_SSH_USER` | beginnt mit `ssh-`, Länge typisch 11–12 Zeichen |
| `DG_ALLINKL_SSH_HOST` | `[login].kasserver.com` |
| Verbindung mit `IQ_ALLINKL_*` | Falsches Konto — ignorieren |

**Fix:**

1. KAS (Hauptaccount) → **Tools → SSH-Zugänge** → Spalte **SSH-Login** kopieren (`ssh-…`)
2. In Cursor Secrets `DG_ALLINKL_SSH_USER` setzen (nicht den KAS-Weblogin!)
3. Neuen Cloud-Agent starten
4. `bash bin/cloud-agent-ssh-setup.sh` → Ausgabe `SSH_OK`

**Agent-seitig prüfen** (ohne Key-Inhalt anzuzeigen):

```bash
ssh-keygen -l -f ~/.ssh/id_ed25519_ganzom
python3 -c "import os; u=os.environ.get('DG_ALLINKL_SSH_USER',''); print('user ok:', u.startswith('ssh-'), 'len', len(u))"
```

### Symptom: Verbindung klappt, falscher Account (`w01f1176`)

Falscher Key — `id_ed25519_allinkl` / `IQ_ALLINKL_*` statt `id_ed25519_ganzom` / `DG_ALLINKL_*`.

---

## 5. Was der Agent nicht braucht / nicht anfassen soll

- Stripe Live-Keys: bewusst noch nicht aktiv (`shop/config/stripe.local.php`)
- Passwort-Login SSH: deaktiviert bzw. veraltet — nur Key
- `bin/sync-crm-from-master.sh` mit `--delete` gegen Account-Root: **verboten** (Geschwister-Domains)
