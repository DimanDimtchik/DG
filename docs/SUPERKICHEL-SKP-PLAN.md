# Super-Kichel — Programmieren (SKP)

> Stand: **2026-11-12** · Ziel: Super-Kichel soll **agentisch programmieren** (Dateien/Tools), nicht nur plaudern — näher an Cursor, self-host auf GEX.  
> Stack-Basis: [`SUPERKICHEL-GEX-PLAN.md`](SUPERKICHEL-GEX-PLAN.md) · UI: [`SUPERKICHEL-UI-PLAN.md`](SUPERKICHEL-UI-PLAN.md) · Ollama-Regel: [`KICHEL-PHASE2.md`](KICHEL-PHASE2.md)  
> **Eine Chat-Session = eine Mini-ID.** Phase-B Cutover / SC2 / Storage-Box = **geparkt** (nicht Blocker für SKP).

---

## 0. Warum SKP

| Heute | Lücke |
|-------|--------|
| `/superkihel` + Ollama | Text/Hilfe — **keine** Repo-Tools |
| OpenHands auf GEX (SKB2d) | Cursor-ähnlicher Agent — **nicht** in der CRM-Seite verdrahtet |

**SKP** = Brücke: OpenHands (oder gleichwertig) nutzbar → dann in Super-Kichel anbinden.

PHP-Regel bleibt: **Beträge/Steuer = PHP**; der Agent schreibt Code/Docs, rechnet keine Buchungen.

---

## 1. Mini-IDs

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| **SKP0a** | Ist OpenHands: läuft, lokal/Tunnel HTTP 200, Zugangsweg PC | 🔴 | [x] 2026-11-12 · §2 |
| **SKP0b** | Entscheidung: Super-Kichel → OpenHands (Link / Proxy / API) — nur Doc | 🟢 | [x] 2026-11-12 · §4 |
| **SKP0c** | Erste Anbindung in `/superkihel` (Staff) laut SKP0b | 🔴🟡 | [x] 2026-11-12 · §5 |
| **SKP0d** | Smoke: aus Super-Kichel (oder dokumentiertem Weg) kleine Repo-Änderung | 🔴 | [x] 2026-11-12 · §6 |
| **SKP0e** | Docs/Haken TODOS + Produkt-Skizze | 🟢 | [x] 2026-11-12 · §7 |
| **SKP1** | HTTPS-Zugang OpenHands ohne PC-Tunnel — Kind-IDs a–e | 🔴 | a–c ✅ · d–e offen |
| **SKP1a** | Ist: `:3000` host-netz · UFW ohne 3000 · kein Agent-Host — **nur lesen** | 🟢 | [x] 2026-11-12 · §8 |
| **SKP1b** | Hostname `agent.ganz-soft.de` + DNS A → GEX | 🔴 | [x] 2026-11-12 · Auth-NS A=GEX |
| **SKP1c** | nginx Proxy → `127.0.0.1:3000` + certbot (+ Basic Auth Minimum) | 🔴 | [x] 2026-11-12 · §8 |
| **SKP1d** | Zugangsschutz Feinschliff · Smoke HTTPS formal | 🔴 | [ ] Basic Auth schon in c |
| **SKP1e** | `/superkihel` Button-URL + Docs | 🔴🟡 | [ ] |
| **SKP2** | Optional: Agent-API/Tools **in** der Chat-UI | 🔴 | [ ] geparkt |

**SKP0 ✅.** **SKP1a–c ✅.** Nächste Freigabe: **SKP1d** (Smoke/Feinschliff) oder **SKP1e** (CRM-Button).

---

## 2. SKP0a — Ist (2026-11-12) ✅

| Punkt | Befund |
|-------|--------|
| Container | `openhands-app` · Image `openhands:1.8` · **Up** · `restart unless-stopped` · `--network host` |
| Port | `0.0.0.0:3000` listen · UFW default-deny → **außen zu** |
| HTTP lokal GEX | `http://127.0.0.1:3000/` → **200** · UI-Hinweis OpenHands |
| HTTP vom PC | SSH-Tunnel `-L 3000:127.0.0.1:3000` → `http://127.0.0.1:3000/` → **200** |
| Ollama | `127.0.0.1:11434/api/tags` → **200** (für LLM in OpenHands-UI) |
| Daten | `/home/deploy/.openhands` · Docker-Sock gemountet |
| Helper | `/opt/openhands/run-openhands.sh` (SKB2d) |
| LLM in UI (Erinnerung) | Base `http://127.0.0.1:11434/v1` · Model z. B. `openai/qwen2.5-coder:14b` · Key beliebig |

**PC-Tunnel (PowerShell):**

```powershell
ssh -o IdentitiesOnly=yes -i $env:USERPROFILE\.ssh\id_ed25519_hetzner_agent -N -L 3000:127.0.0.1:3000 deploy@2.29.242.28
```

Browser: `http://127.0.0.1:3000` — parallel zu Cursor nutzbar, **noch ohne** CRM-Einbindung.

**Nicht in SKP0a:** CRM-UI ändern · OpenHands öffentlich öffnen · Phase-B.

---

## 3. Abgrenzung

| Thema | Wo |
|-------|-----|
| Chat-Hilfe / Skonto-Text | Kichel / Super-Kichel + Ollama (SKU/SKC) |
| Agentisches Programmieren | **SKP** (+ OpenHands) |
| Desktop Cline | Weg A [`KI-AGENT-WEG-A-PLAN.md`](KI-AGENT-WEG-A-PLAN.md) — parallel, nicht Pflicht für SKP |
| CRM-Cutover | PB4 — geparkt |

---

## 4. SKP0b — Entscheidung Anbindung (2026-11-12) ✅

### Gewählt für SKP0c: **Staff-Link + Kurzhilfe (Tunnel)**

| Punkt | Festlegung |
|-------|------------|
| **Muster** | Auf `/superkihel`: sichtbarer Bereich „Programmieren“ · **Button-Link** (Katalog `.dg-button`) öffnet OpenHands in neuem Tab |
| **Ziel-URL** | `http://127.0.0.1:3000/` — gilt nur mit SSH-Tunnel (SKP0a) |
| **Wer** | nur Staff (wie Super-Kichel); kein öffentlicher Link |
| **Text** | kurze Endnutzer-Hilfe: Tunnel starten · LLM-URL/Modell — **keine** Plan-Codes (`SKP0…`) in der UI |
| **Nicht** | iframe (CSP/Cookies/Login von OpenHands ungeeignet) · OpenHands-Port in UFW öffnen · API-Chat in SKP0c |

**Warum zuerst Link:** OpenHands liefert die Cursor-ähnlichen Tools schon; Dev-CRM liegt auf All-Inkl, Agent auf GEX — Proxy/API bräuchten Host/Auth/Session extra. Link + Tunnel = schnellster Weg, **jetzt** zu programmieren.

### Später (nicht SKP0c)

| ID | Was |
|----|-----|
| **SKP1** | Eigener HTTPS-Host (analog `llm…`/`crm-gex…`) oder Auth-Proxy → OpenHands ohne lokalen Tunnel (Tablet/andere PCs) |
| **SKP2** | Werkzeuge/API **in** der Super-Kichel-Chat-Oberfläche (enger an „alles in einer Seite“) |

### Verworfen für SKP0c

- Soft-iframe von `:3000` in CRM  
- PHP ruft OpenHands-API für jeden Chat-Turn (Scope-Sprung)  
- OpenHands öffentlich ohne Auth  

---

## 5. SKP0c — umgesetzt (2026-11-12) ✅

| Punkt | Ist |
|-------|-----|
| View | `views/modules/superkihel.php` · Panel „Programmieren“ + Button „Agent öffnen“ → `http://127.0.0.1:3000/` · Hilfe-Chat darunter |
| UI | `.dg-stack` · `.dg-panel` · `.dg-section-title` · `.dg-button--primary` · `.dg-field-hint` · Guard ✅ |
| Deploy | Dev `ganz-om.de` View ausgerollt |
| Endnutzer | keine Plan-Codes in der UI |

**Abnahme:** eingeloggt `/superkihel` → Panel + Button; mit Tunnel Tab auf OpenHands.

**Nächste ID:** **SKP0d**.

---

## 6. SKP0d — Smoke (2026-11-12) ✅

| Check | Ergebnis |
|-------|----------|
| Dev UI | `superkihel.php` enthält „Agent öffnen“ (Deploy SKP0c) |
| PC-Tunnel → OpenHands | `http://127.0.0.1:3000/` → **200** |
| LLM-Pfad (wie OpenHands) | `qwen2.5-coder:14b` via `127.0.0.1:11434` → Antwort **SKP0D_OK** |
| Workspace-Schreibtest | `/opt/openhands/smoke-workspace/skp0d-ok.txt` + `skp0d-stamp.txt` angelegt |
| OpenHands REST „eine Conversation POST“ | SPA fängt `/api/settings` als HTML · Conversation-POST **405** — UI-Agent-Turn bleibt manuell im Browser |

**Manuell (empfohlen einmal):** Tunnel · `/superkihel` → Agent öffnen · Workspace auf `smoke-workspace` bzw. Repo zeigen · „Create/update skp0d-ok.txt“ — Stack dafür ist grün.

**Nächste ID:** **SKP0e**.

---

## 7. SKP0e — Docs/Haken (2026-11-12) ✅

Nachgezogen: dieser Plan · [`TODOS.md`](TODOS.md) · [`SUPERKICHEL-GEX-PLAN.md`](SUPERKICHEL-GEX-PLAN.md) · [`SUPERKICHEL-PRODUKT-SKIZZE.md`](SUPERKICHEL-PRODUKT-SKIZZE.md) · [`SUPERKICHEL-UI-PLAN.md`](SUPERKICHEL-UI-PLAN.md) · [`CLOUD-AGENT-ACCESS.md`](CLOUD-AGENT-ACCESS.md) (Tunnel :3000).

**SKP0 Block ✅** — Nutzen jetzt: Tunnel + `/superkihel` → Agent öffnen.

---

## 8. SKP1 — HTTPS ohne Tunnel

### SKP1a — Ist (2026-11-12, nur lesen) ✅

| Punkt | Befund |
|-------|--------|
| OpenHands | `openhands-app` Up · Image 1.8 · **`--network host`** · listen **`0.0.0.0:3000`** |
| UFW | erlaubt nur **22/80/443** — **kein** 3000 |
| Erreichbarkeit | lokal `127.0.0.1:3000` → **200** · vom **PC öffentlich** `2.29.242.28:3000` → **blockiert** (UFW) |
| Hinweis | Curl von GEX zur eigenen Public-IP:3000 kann **200** zeigen (Hairpin) — zählt **nicht** als „Internet offen“ |
| nginx | kein vHost für OpenHands · LE nur `llm…` / `crm-gex…` |
| DNS | `agent.ganz-soft.de` / `openhands.ganz-soft.de` → noch All-Inkl-Catch-all |
| CRM-Button | weiter `http://127.0.0.1:3000/` (SKP0c) |

**Folge SKP1b:** KAS A `agent` → `2.29.242.28`. Apex/`*` lassen. **Nicht** Port 3000 in UFW öffnen — Zugang nur über 443+Proxy+Schutz (SKP1c/d).

### SKP1b — Hostname + DNS (2026-11-12) ✅

| Punkt | Stand |
|-------|--------|
| **Hostname** | `agent.ganz-soft.de` |
| **KAS** | A `agent` → `2.29.242.28` (neben `llm` / `crm-gex`; Apex All-Inkl) |
| **Verify** | Auth-NS `ns5` → **2.29.242.28** · 1.1.1.1 ok · 8.8.8.8 kann kurz Catch-all cachen |
| **Nicht** | Certbot · UFW 3000 · CRM-Button (SKP1c–e) |

### SKP1c — nginx + LE (2026-11-12) ✅

| Punkt | Stand |
|-------|--------|
| **vHost** | `agent.ganz-soft.de` · Proxy → `127.0.0.1:3000` · WebSocket-Header · Timeout lang |
| **Cert** | LE bis **2027-02-10** · Auto-Renew |
| **Schutz** | **Basic Auth** Minimum (`/etc/nginx/.htpasswd-agent`) — Passwort **nicht** in Git; User `agent` |
| **Smoke** | ohne Auth **401** · mit Auth **200** + OpenHands-HTML |
| **UFW** | 3000 weiter **zu** |
| **Nicht** | CRM-Button-URL (SKP1e) · Passwort in Docs |

### Sandbox-Fix (2026-11-12)

| Symptom | Sandbox-500 · UI „Connecting…“ / Nachrichten unsichtbar |
|---------|----------------------------------------------------------|
| Ursachen | Bridge≠Host (UFW) · `host.docker.internal` · Browser bekam `http://localhost:8000` (unerreichbar) · MCP hinter Basic Auth |
| Fix | Host-Netz · max 1 Sandbox (alte Agent-Container werden entfernt) · nginx **8443→Agent / 8444→VSCode / 8445–46→Worker** · öffentliche URLs gemappt · `/mcp/` ohne Basic Auth |
| Smoke | Start **READY** · VSCode-URL `:8444` · zweiter Start ersetzt ersten (kein 401) |
| LLM | Qwen via Ollama: `native_tool_calling=false` (sonst Tool-Calls als Roh-JSON im Chat) |

**Nächste ID:** **SKP1d** (formal Smoke/Feinschliff) oder **SKP1e** (Button auf `https://agent.ganz-soft.de/`).

---

## Änderungshistorie

| Datum | Inhalt |
|-------|--------|
| 2026-11-12 | **SKP** angelegt · **SKP0a ✅** OpenHands Ist/Tunnel-Smoke |
| 2026-11-12 | **SKP0b ✅** Anbindung = Staff-Link + Tunnel-Hilfe; Proxy/API = SKP1/2 |
| 2026-11-12 | **SKP0c ✅** Panel Programmieren + Agent-Button auf `/superkihel` · Dev deploy |
| 2026-11-12 | **SKP0d ✅** Tunnel+LLM+Workspace-Smoke · UI-Conversation API nicht nutzbar |
| 2026-11-12 | **SKP0e ✅** Docs/Haken · SKP0 Block abgeschlossen |
| 2026-11-12 | **SKP1** in a–e zerlegt · **SKP1a ✅** Ist :3000/UFW/DNS |
| 2026-11-12 | **SKP1b ✅** KAS A `agent` → GEX · Auth-NS OK |
| 2026-11-12 | **SKP1c ✅** HTTPS Proxy + LE + Basic Auth · UFW 3000 zu |
| 2026-11-12 | Sandbox-Fix: Host-Netz max1 + HTTPS 8443/8444/… · VSCode-URL korrekt · 2. Start READY |
