# Cloud-Agent / Cursor Browser — Zugänge (DG CRM)

Stand: 2026-11-11

> **Konsolidierter Stand:** [`docs/HANDOFF.md`](HANDOFF.md) · Agent-Rolle: [`AGENTS.md`](../AGENTS.md) · Super-Kichel/GEX: [`SUPERKICHEL-GEX-PLAN.md`](SUPERKICHEL-GEX-PLAN.md)

> **Kein Passwort-SSH.** All-Inkl akzeptiert den Key `id_ed25519_ganzom`. Ein „veraltetes Passwort“ ist normal — der Browser-Agent braucht den **Private Key** als Cursor-Secret. Hetzner GEX45: ebenfalls nur Key (`deploy`), siehe **§1b**.

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
| **SSH-Benutzer** | beginnt immer mit `ssh-` | `DG_ALLINKL_SSH_USER` |
| **KAS-Login** | `w0217246` | `DG_KAS_LOGIN` (optional, nur KAS-API / FTP-Fallback) |
| **KAS-Passwort** | Weblogin-Passwort | `DG_KAS_AUTH_DATA` (optional, nur FTP-Fallback) |

Häufiger Fehler: KAS-Login in `DG_ALLINKL_SSH_USER` → am PC funktioniert SSH, im Cloud Agent `Permission denied (publickey)`, obwohl der Private Key korrekt ist.

### Deploy vom Cloud-Agent (wie KlarWin — **kein KAS-Passwort**)

All-Inkl/WinSCP: **SFTP + Public Key**, Passwort leer — dieselben Secrets wie SSH (`DG_ALLINKL_SSH_*`). Nutzer sprechen oft von „FTP“, meinen damit **SFTP** (Port 22).

```bash
bash bin/cloud-agent-ssh-setup.sh      # muss SSH_OK ausgeben
bash bin/cloud-agent-sftp-setup.sh     # optional: SFTP-Verzeichnis testen
bash bin/deploy-via-sftp.sh            # Master dg.ganz-om.de
bash bin/deploy-via-sftp.sh --all        # + ganz-soft.de, kontur, ganz-om
```

**Kein manueller KAS-Datei-Upload nötig** — KlarWin nach `ganz-soft.de/klarwin/` wurde genauso übertragen (Daten liegen auf dem Server).

Wenn Port 22 **Connection timed out** (All-Inkl-Ticket): weder SSH noch SFTP vom Cloud-Agent — dann PC `deploy.bat` sobald SSH wieder geht, oder optional FTP-Fallback (extra Secrets, siehe unten).

### Optional: FTP-Fallback (Port 21, **nur mit KAS-Passwort**)

Nur wenn du **zusätzlich** Secrets setzen willst — **nicht** für KlarWin nötig gewesen:

| Name | Wert |
|------|------|
| `DG_KAS_LOGIN` | KAS-Weblogin (`w0217246`) |
| `DG_KAS_AUTH_DATA` | KAS-Hauptaccount-Passwort (= FTP-Passwort) |

```bash
bash bin/cloud-agent-ftp-setup.sh
bash bin/deploy-via-ftp.sh --all
```

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

**Häufige Fehler (2026-08-23 behoben):**

| Symptom | Ursache | Lösung |
|---------|---------|--------|
| `error in libcrypto` | Key im Secret als **eine Zeile** | `cloud-agent-ssh-setup.sh` auf `master` reformatiert automatisch |
| `Permission denied` trotz Key | `DG_ALLINKL_SSH_USER` = KAS-Login statt `ssh-…` | Secret auf SSH-User setzen (nicht `w0217246`) |

**Key vom PC holen** (einmalig, wenn du am Rechner bist):

```powershell
Get-Content $env:USERPROFILE\.ssh\id_ed25519_ganzom -Raw
```

**SSH-User vom PC prüfen** (Wert für `DG_ALLINKL_SSH_USER`):

```powershell
ssh -G allinkl-ganzom | findstr /i "^user "
```

Fingerprint Private Key (Kontrolle): `SHA256:RoWIYpvE7HH1cQbVS7YUmSouM4pGvYv33i3AEiFriPw` — Kommentar in `id_ed25519_ganzom.pub`: `ganz-om.de (s000e3d3)`.

**Zwei All-Inkl-Accounts** — jeweils die passenden Secrets verwenden:

| Prefix | KAS-Account | Domains (Auszug) | Setup-Skript |
|--------|-------------|------------------|--------------|
| `DG_ALLINKL_*` | `w0217246` | dg.ganz-om.de, ganz-soft.de, kontur-cosmetics.de | `bin/cloud-agent-ssh-setup.sh` |
| `IQ_ALLINKL_*` | `w01f1176` | ma.iqstrom.com, iqstrom.com, iq-strom.de, cloud.iqstrom.com, **office.iqstrom.com** | `bin/cloud-agent-iq-ssh-setup.sh` |

Für **CRM-Deploy** (Kern/Dev) `DG_ALLINKL_*`. Für **IQ-Strom** (CRM `office.iqstrom.com`, Telefonbuch, ma…) `IQ_ALLINKL_*` — nicht verwechseln.  
IQ-CRM-Sync vom Kern: `bash bin/sync-crm-to-iqstrom.sh` (nach beiden SSH-Setups).

---

## 1b. Hetzner GEX45 — Cursor SSH (SK0b)

Zielserver **GEX45-1** (Phase B / Super-Kichel). SSH-User **`deploy`**, Passwort-Login aus (PB1c). Secrets getrennt von `DG_ALLINKL_*` / `IQ_ALLINKL_*`.

### Runtime Secrets (Cursor Dashboard)

| Name | Wert | Hinweis |
|------|------|---------|
| `Hetzner_user` | `deploy` | SSH-Login auf dem GEX |
| `Hetrzner_Host` | Primäre IPv4 des GEX | **Tippfehler im Secret-Namen** (`Hetrzner` statt `Hetzner`) — so belassen, bis umbenannt und alle Agents angepasst |
| `Hetzner_Private_Key` | Inhalt der **Private**-Key-Datei (inkl. `BEGIN OPENSSH PRIVATE KEY` … `END`) | **Nicht** die `.pub`-Zeile |

Optional später: Secret in `Hetzner_Host` umbenennen und Agent-Skripte nachziehen — nicht Teil von SK0b.

### Welcher Key?

Am Server autorisiert (Comment): **`hetzner-gex45-cursor-agent`**.

Public-Key-Fingerprint zur Kontrolle (zugehörige **`.pub`**, nicht den Private Key posten):

`SHA256:v1atqLF/jX329gWUORfjlsR1yX0srjhnU5i5z4R2Sh8` · ED25519 · Comment `hetzner-gex45-cursor-agent`

**Passenden Private Key auf dem PC finden** (PowerShell):

```powershell
Get-ChildItem $env:USERPROFILE\.ssh\*.pub | ForEach-Object { ssh-keygen -l -f $_.FullName }
```

Treffer mit obigem Fingerprint/Comment → Datei **ohne** `.pub` ist der Private Key. Inhalt als Secret `Hetzner_Private_Key` setzen (kompletter PEM-Block). Danach **neuen** Cloud-Agent starten (alte Runs behalten alte Secret-Werte).

### Häufiger Fehler (2026-11-11)

| Symptom | Ursache | Lösung |
|---------|---------|--------|
| `Permission denied (publickey)` trotz gesetztem `Hetzner_Private_Key` | Im Secret liegt die **Public**-Key-Zeile (`ssh-ed25519 …`) | Secret durch echten **Private** Key ersetzen |
| Key startet mit `ssh-ed25519` | Das ist immer Public | Private beginnt mit `-----BEGIN OPENSSH PRIVATE KEY-----` |
| SSH vom PC ok, Cloud nicht | Anderer Key / Secret noch Public | Fingerprint der `.pub` mit Tabelle oben abgleichen |

### Ohne Private Key (Notfall)

[robot.hetzner.com](https://robot.hetzner.com) → Server GEX45-1 → **Rescue** (neues Root-Passwort nur im Rescue) oder **vKVM**. Details: Hetzner Docs Rescue / vKVM. Danach `authorized_keys` für `deploy` prüfen bzw. Secret korrigieren. **Kein** Passwort/Passphrase in Chats posten.

### Smoke nach Fix

```bash
# erwartet: SSH_OK / whoami=deploy (Host aus Secret, Key aus Secret)
ssh -o BatchMode=yes -o IdentitiesOnly=yes -i ~/.ssh/id_ed25519_hetzner_gex \
  "${Hetzner_user}@${Hetrzner_Host}" 'echo SSH_OK; whoami; hostname'
```

(Key-Datei lokal aus dem Secret materialisieren — Inhalt nie committen.) Plan-Kontext: [`SUPERKICHEL-GEX-PLAN.md`](SUPERKICHEL-GEX-PLAN.md) Gate SSH.

### Ollama vom PC (SKB1d, nach Härten)

Ollama lauscht nur auf **`127.0.0.1:11434`** am GEX; Port 11434 ist in der UFW **zu**. Vom PC:

```powershell
ssh -o IdentitiesOnly=yes -i $env:USERPROFILE\.ssh\id_ed25519_hetzner_agent -N -L 11434:127.0.0.1:11434 deploy@2.29.242.28
```

Cursor Override: Base URL **`http://127.0.0.1:11434/v1`**. Lokal am PC: Datei oft `id_ed25519_hetzner_agent` (Fingerprint `SHA256:v1atqLF/…`, Comment `hetzner-gex45-cursor-agent`).

**CRM → Ollama (SKC1 ✅):** Dev nutzt HTTPS-Hostname **`llm.ganz-soft.de`** (path-secret Proxy, LE-Cert). Hostname/IP öffentlich ok; **Path-Token und volle Base-URL nie** in Git/Chat. Cursor-Tunnel oben bleibt der PC-Zugang zu `:11434/v1`.

### OpenHands vom PC (SKP0 / SKB2d)

OpenHands-UI nur lokal auf GEX **`:3000`** (UFW zu). Vom PC parallel zum Ollama-Tunnel:

```powershell
ssh -o IdentitiesOnly=yes -i $env:USERPROFILE\.ssh\id_ed25519_hetzner_agent -N -L 3000:127.0.0.1:3000 -L 11434:127.0.0.1:11434 deploy@2.29.242.28
```

Browser: `http://127.0.0.1:3000` · Einstieg auch über Dev **`/superkihel`** → „Agent öffnen“. LLM in OpenHands: Base `http://127.0.0.1:11434/v1`, Modell z. B. `openai/qwen2.5-coder:14b`. Plan: [`SUPERKICHEL-SKP-PLAN.md`](SUPERKICHEL-SKP-PLAN.md).

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
bash bin/deploy-via-sftp.sh           # Master hochladen (oder deploy.bat am PC)
bash bin/sync-crm-from-master.sh      # Master → ganz-soft.de + kontur-cosmetics.de + ganz-om.de
# Shop: Dateien nach shop.ganz-soft.de/ (eigenes Projekt, nicht im CRM-Sync)
# Wartungsmodus: https://shop.ganz-soft.de/admin/login (Passwort in config/admin.local.php auf Server)
```

Erst Master per `scp`/`deploy.bat` auf **dg.ganz-om.de** hochladen, dann `sync-crm-from-master.sh`.

**Kichel-Protokoll (Cloud-Agent):** Wenn der Nutzer Kichel-Antworten besprechen will:

```bash
bash bin/cloud-agent-kichel-protocol.sh 30
```

JSON mit Frage/Antwort von **ganz-soft.de** (Live Account-Root). Details: [`KICHEL-PHASE2.md`](KICHEL-PHASE2.md).

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
| IQ-Strom / ma.iqstrom.com | `IQ_ALLINKL_*` + `bash bin/cloud-agent-iq-ssh-setup.sh` → `ssh allinkl-iqstrom` |

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
