# Super-Kichel / GEX45 — Bestandsaufnahme & Zielbild (tokenarm)

> **Eine Chat-Session = eine Mini-ID.**  
> **Zielbild ≠ Installationsfreigabe.** Erst Inventar (SKA0), dann Freigabe je Install-ID.  
> Produktlinie: **Kichel** im CRM · Dev-Zielinstanz **`ganz-om.de`** · KI-Backend auf **GEX45-1**.  
> Verwandt: [`KICHEL-PHASE2.md`](KICHEL-PHASE2.md) · [`GEX45-DISK-LAYOUT.md`](GEX45-DISK-LAYOUT.md) · [`PHASE-B-REST-PLAN.md`](PHASE-B-REST-PLAN.md) · [`SERVER-MIGRATION.md`](SERVER-MIGRATION.md)

Stand: **2026-11-12** · Server: **GEX45-1** · HEL1 · Ubuntu 26.04 LTS (PB1 ✅) · CRM-Stack PB2 teilweise

---

## 0. Was „Super-Kichel“ hier heißt

| Schicht | Wo | Rolle |
|---------|-----|--------|
| **Kichel (CRM)** | Instanz-DB + PHP (`src/Kichel/…`) | Regeln, Beträge, Navigation, Protokoll — **GoBD-/Fachlogik bleibt PHP** |
| **Ollama** | GEX45, ideal nur `127.0.0.1:11434` | Nur Formulierung (`KichelOllamaClient::phrase`) |
| **Dev-Oberfläche** | **`ganz-om.de`**, eingeloggt · Seite `/app?page=superkihel` | Skizze: [`SUPERKICHEL-PRODUKT-SKIZZE.md`](SUPERKICHEL-PRODUKT-SKIZZE.md) (SKC0a) |
| **Ops-Hilfen** | nach Freigabe je Mini-ID, oft Docker | SearXNG, Playwright, OpenHands, Comfy — Kriterien: kostenlos/legal/ohne Anmeldung/ohne Limits · Forgejo **nein** |

**Kein Tenant im Produktcode:** keine Kundenfirma als Default; Firmendaten nur aus Instanz-Settings/DB. Hosts nur in Ops-Docs.

**Abgrenzung Phase B:** `PB*` = CRM-Umzug All-Inkl → GEX. `SKA*`/`SKB*` = KI-/Assistenten-Stack auf demselben Server — parallel möglich, **ohne** Cutover und **ohne** Produktiv-DNS zu berühren.

---

## 1. Arbeitsweise (verbindlich)

Jeder Mini-Schritt separat:

1. Aufgabe anhand der **ID** identifizieren  
2. **Ist-Zustand** untersuchen — noch nichts ändern  
3. Betroffene Dateien / Serverpfade nennen  
4. Geplante Änderungen, Risiken, Prüfungen beschreiben  
5. Auf **ausdrückliche Freigabe** warten  
6. Nur die freigegebene ID umsetzen — keine Nebenarbeiten, keine Extra-Installationen  
7. Tests / Nachweis  
8. Ergebnis dokumentieren (Haken hier + ggf. Commit)  
9. Erst dann nächste ID  

**Standard-Chat-Prompt:**

```text
Nur Mini-ID SKA0-1 laut docs/SUPERKICHEL-GEX-PLAN.md.
Untersuche zuerst den Ist-Zustand. Ändere noch nichts.
Nenne betroffene Dateien/Pfade, geplante Änderungen, Risiken und Prüfungen.
Warte auf ausdrückliche Freigabe.
```

Nach Freigabe denselben Prompt mit „Freigabe erteilt — nur diese ID umsetzen.“

---

## 2. Token-Stufen

| Symbol | Wann |
|--------|------|
| 🟢 | Doc / Statusnotiz / Checkliste |
| 🟡 | kleines Script oder Config-Beispiel im Repo |
| 🔴 | SSH/Robot am GEX45 · Pakete · systemd · Firewall |

**Gate SSH:** Cloud-Agent braucht gültiges Secret `Hetzner_Private_Key` (**Private** Key zu Comment `hetzner-gex45-cursor-agent`) + Host/User. Ohne SSH: SKA0 nur Doc-/Portprobe-Teile, klar als unvollständig markieren.

---

## 3. Parent-IDs

| ID | Thema | Status | Token |
|----|--------|--------|-------|
| SK0 | Dieser Plan + Regeln | [x] 2026-11-11 | 🟢 |
| **SK0b** | Hetzner-Cursor-Secrets in [`CLOUD-AGENT-ACCESS.md`](CLOUD-AGENT-ACCESS.md) §1b | [x] 2026-11-11 | 🟢 |
| **SKC0a** | Produkt-Skizze Widget vs. Super-Kichel | [x] 2026-11-11 · [`SUPERKICHEL-PRODUKT-SKIZZE.md`](SUPERKICHEL-PRODUKT-SKIZZE.md) | 🟢 |
| **SKA0-12v** | Inventar-Fazit **vorläufig** (ohne SSH) | [x] 2026-11-11 · [`SUPERKICHEL-INVENTAR-VORLAEUFIG.md`](SUPERKICHEL-INVENTAR-VORLAEUFIG.md) | 🟢 |
| SKA0 | Bestandsaufnahme GEX45 (SKA0-1 … SKA0-12) | [ ] | 🔴🟢 |
| **SKB0** | Entscheidung: was wird wirklich installiert | [x] 2026-11-11 · Nachtrag 2026-11-12 · [`SUPERKICHEL-SKB0-ENTSCHEIDUNG.md`](SUPERKICHEL-SKB0-ENTSCHEIDUNG.md) | 🟢 |
| SKB1 | Härten Ollama — Kind-IDs SKB1a–d | [x] 2026-11-12 | 🔴 |
| **SKB1a** | Ist: Ollama-Bindung, systemd/Env, UFW/11434 — **nur lesen** | [x] 2026-11-12 · siehe §8 | 🔴 |
| **SKB1b** | Ollama auf `127.0.0.1:11434` binden | [x] 2026-11-12 · siehe §8 | 🔴 |
| **SKB1c** | UFW: 11434 von außen zu | [x] 2026-11-12 · siehe §8 | 🔴 |
| **SKB1d** | Nachweis + Cursor-Zugang (Tunnel o. Ä.) | [x] 2026-11-12 · siehe §8 | 🔴 |
| SKB2 | Installationen — **einzeln** Kind-IDs unten | [x] 2026-11-12 (a–e) | 🔴 |
| **SKB2a** | Docker (Engine/Compose) | [x] 2026-11-12 · siehe §8 | 🔴 |
| **SKB2b** | SearXNG (nach SKB2a) | [x] 2026-11-12 · siehe §8 | 🔴 |
| **SKB2c** | Playwright | [x] 2026-11-12 · siehe §8 | 🔴 |
| **SKB2d** | OpenHands (nach SKB2a) | [x] 2026-11-12 · siehe §8 | 🔴 |
| **SKB2e** | ComfyUI | [x] 2026-11-12 · siehe §8 | 🔴 |
| SKC0 | CRM↔Ollama Dev — Kind-IDs SKC0b–e (SKC0a ✅ Skizze) | [x] 2026-11-12 | 🔴🟡 |
| **SKC0b** | Ist Dev: `kichel.local.php` / Ollama-URL — **nur lesen** | [x] 2026-11-12 · siehe §8 | 🔴 |
| **SKC0c** | Weg All-Inkl→GEX-Ollama klären + Config setzen | [x] 2026-11-12 · siehe §8 | 🔴 |
| **SKC0d** | Smoke Kichel/Super-Kichel mit Ollama auf Dev | [x] 2026-11-12 · siehe §8 | 🔴 |
| **SKC0e** | Docs/Haken nachziehen | [x] 2026-11-12 | 🟢 |
| SKD0 | Docs/Haken nachziehen | [ ] | 🟢 |

---

## 4. SKA0 — Bestandsaufnahme (Start: SKA0-1)

> Nur lesen/prüfen. **Keine** Installation in SKA0.

| ID | Aufgabe | Abnahme | Token | Status |
|----|---------|---------|-------|--------|
| **SKA0-1** | OS, Version, Architektur (`uname`, `/etc/os-release`, Zeitzone) | Nachweis Ubuntu/Arch/Berlin | 🔴 | [ ] |
| **SKA0-2** | CPU/RAM/Swap, Last-Snapshot | Kernzahlen dokumentiert | 🔴 | [ ] |
| **SKA0-3** | NVMe / Mounts / `df -h` vs. [`GEX45-DISK-LAYOUT.md`](GEX45-DISK-LAYOUT.md) | Soll/Ist Mounts | 🔴 | [ ] |
| **SKA0-4** | User `deploy`, sudo, SSH-Keys, Passwort-SSH aus | PB1c bestätigt oder Diff | 🔴 | [ ] |
| **SKA0-5** | UFW / offene Ports (außen + `ss` lokal) | Portliste; Ollama-Bindung | 🔴🟢 | [ ] |
| **SKA0-6** | CRM-Stack: nginx, PHP-FPM, MariaDB, certbot (PB2a) | Versionen + Dienststatus | 🔴 | [ ] |
| **SKA0-7** | Docker / Compose / NVIDIA-Container vorhanden? | ja/nein + Version | 🔴 | [ ] |
| **SKA0-8** | GPU-Treiber / `nvidia-smi` | Treiber ja/nein | 🔴 | [ ] |
| **SKA0-9** | **Ollama**: Binary/Service, Version, Bind-Adresse, Modelle | Ist (bekannt: läuft öffentlich — verifizieren) | 🔴🟢 | [ ] |
| **SKA0-10** | Zielbild-Kandidaten: OpenHands, SearXNG, Forgejo, Playwright, Comfy — installiert/laufend? | je Komponente: fehlt / da / nur Zielbild | 🔴 | [ ] |
| **SKA0-11** | Pfade `/var/www/dg-crm`, `/mnt/data` (shared, instances, ollama) | Baum-Kurznotiz | 🔴 | [ ] |
| **SKA0-12** | **Inventar-Fazit** live (nach SKA0-1…11): vorhanden / fehlt / unsicher / später + SKB0 | ersetzt bzw. bestätigt 12v | 🟢 | [ ] |
| **SKA0-12v** | Gleiches Fazit **nur** Außenprobe+Docs | [`SUPERKICHEL-INVENTAR-VORLAEUFIG.md`](SUPERKICHEL-INVENTAR-VORLAEUFIG.md) | 🟢 | [x] 2026-11-11 |

**Kurz Außenprobe** (Detail → 12v-Doc; **nicht** SKA0-12-Abnahme): nginx `1.28.3` · Ollama `0.40.16` + 3 Modelle **öffentlich** `:11434` · Docker-API/OpenHands/SearXNG/Forgejo-typische Ports zu.

---

## 5. Zielbild-Komponenten — **SKB0 ✅** (Nachtrag 2026-11-12)

Kanonsich: [`SUPERKICHEL-SKB0-ENTSCHEIDUNG.md`](SUPERKICHEL-SKB0-ENTSCHEIDUNG.md).

| Komponente | SKB0-Entscheidung |
|------------|-------------------|
| **Ollama** | **Nutzen/härten** (SKB1), nicht neu installieren |
| **Docker** | **Ja** → **SKB2a** |
| **SearXNG** | **Ja** → **SKB2b** |
| **Playwright** | **Ja** → **SKB2c** |
| **OpenHands** | **Ja** → **SKB2d** |
| **ComfyUI** | **Ja** → **SKB2e** |
| **Forgejo** | **Nein** |
| **GPU-Treiber** | nur prüfen (SKA0-8), Install nur mit Freigabe |

PHP-Regel aus Phase 2 bleibt: **Ollama rechnet keine Beträge.** CRM bleibt nativ.

---

## 6. Empfohlene Reihenfolge

1. **SKB0 ✅** · **SKB1 ✅** · **SKB2a–e ✅** · **SKC0 ✅** (2026-11-12)  
2. optional **SKA0-1…12** Live-Inventar (Bestätigung)  
3. **SKD0** Docs-Feinschliff / oder Super-Kichel-**UI**-Mini-IDs (Seite `superkihel`)  
4. TLS für Ollama-Proxy mit **PB2c4** (statt HTTP:80)  

**Nächste Freigabe (UI):** [`SUPERKICHEL-UI-PLAN.md`](SUPERKICHEL-UI-PLAN.md) — **SKU3** (SKU0–2 ✅).

**Nicht** parallel zu einer offenen `PB*`-Serverarbeit dieselben Dienste umbauen, ohne das in der Freigabe zu sagen.

---

## 7. Secrets / Zugang (Ops)

Kanonsich: [`CLOUD-AGENT-ACCESS.md`](CLOUD-AGENT-ACCESS.md) **§1b** (SK0b).

| Secret / Zugang | Rolle |
|-----------------|--------|
| `Hetzner_user` | `deploy` |
| `Hetrzner_Host` | GEX-IPv4 (Name historisch Tippfehler) |
| `Hetzner_Private_Key` | **muss** OpenSSH-**Private**-Key sein (nicht Public) |
| Robot | Rescue / vKVM bei Schlüsselverlust |

Public-Key-Comment: `hetzner-gex45-cursor-agent` · Fingerprint `SHA256:v1atqLF/jX329gWUORfjlsR1yX0srjhnU5i5z4R2Sh8`.

---

## 8. SKB1a — Ist Ollama/UFW (2026-11-12, nur lesen)

SSH: `deploy` + Key `id_ed25519_hetzner_agent` (Fingerprint Docs §7). Host-Alias `hetzner-gex45` nutzt aktuell **anderen** Key → `Permission denied`; Agent-Key OK.

| Punkt | Ist |
|-------|-----|
| Version | Ollama **0.40.16** · `/usr/local/bin/ollama` |
| systemd | `active/running` · Unit `/etc/systemd/system/ollama.service` |
| Bind | Drop-in `ollama.service.d/override.conf`: **`OLLAMA_HOST=0.0.0.0:11434`** |
| Listen | `*:11434` (alle Interfaces) |
| UFW | **aktiv** · **11434/tcp ALLOW Anywhere** (+ v6) · daneben 22/80/443 |
| API lokal | `127.0.0.1:11434` OK |
| API außen | `http://2.29.242.28:11434` OK (öffentlich) |
| Modelle | `qwen2.5-coder:32b`, `qwen2.5-coder:14b`, `llama3.1:8b` |

**Risiko (vor SKB1b):** Ollama weltweit erreichbar (kein Auth).  

### SKB1b — umgesetzt 2026-11-12

| Punkt | Nachher |
|-------|---------|
| Drop-in | `OLLAMA_HOST=127.0.0.1:11434` in `override.conf` |
| Listen | nur `127.0.0.1:11434` |
| Lokal | API OK (`0.40.16`) |
| Außen `2.29.242.28:11434` | **Verbindung fehlgeschlagen** (Bind) |
| UFW 11434 ALLOW | **entfernt** (v4+v6) — SKB1c ✅ |
| Cursor-Override öffentlich | **tot** bis **SKB1d** (Tunnel) |

### SKB1c — umgesetzt 2026-11-12

| Punkt | Nachher |
|-------|---------|
| UFW | nur noch 22 / 80 / 443 (inkl. v6) |
| 11434 ALLOW | gelöscht |
| Ollama lokal | weiterhin OK auf `127.0.0.1:11434` |

### SKB1d — umgesetzt 2026-11-12

Zugang vom PC (ohne öffentliche 11434):

```powershell
ssh -o IdentitiesOnly=yes -i $env:USERPROFILE\.ssh\id_ed25519_hetzner_agent -N -L 11434:127.0.0.1:11434 deploy@2.29.242.28
```

Fenster offen lassen. Cursor Override Base URL: **`http://127.0.0.1:11434/v1`** · Modell z. B. `qwen2.5-coder:14b` · Key beliebig (`ollama`).

**Nachweis:** Tunnel-Smoke → `http://127.0.0.1:11434/api/version` = `0.40.16`.  
SSH-Config-Alias `hetzner-gex45` zeigt noch auf anderen Key — für Tunnel den **Agent-Key** nutzen (oder Alias anpassen, eigene Mini-ID).

**SKB1 gesamt ✅** (a–d).

### SKB2a — umgesetzt 2026-11-12

| Punkt | Ist |
|-------|-----|
| Pakete | `docker.io` **29.1.3** · `docker-compose-v2` **2.40.3** (Ubuntu repos) |
| Dienst | `docker` **active/enabled** · Socket nur lokal (`/var/run/docker.sock`) |
| Smoke | `sudo docker run --rm hello-world` OK |
| Gruppe | `deploy` in `docker` (neue SSH-Session → ggf. ohne sudo) |
| UFW | unverändert (22/80/443) · **kein** Docker-TCP-API außen |
| CRM | unberührt (nativ) |

### SKB2b — umgesetzt 2026-11-12

| Punkt | Ist |
|-------|-----|
| Pfad | `/opt/searxng/stack` (offizielles Compose aus searxng/searxng `container/`) |
| Container | `searxng-core` + `searxng-valkey` · `restart: always` |
| Bind | **`127.0.0.1:8080`** (`SEARXNG_HOST`) · außen zu |
| Smoke | UI/Suche lokal HTTP 200 · Suche liefert Treffer |
| JSON-API | in `settings.yml` `formats: html, json` gesetzt — Abnahme JSON ggf. nachziehen |
| Browser vom PC | Tunnel z. B. `-L 8080:127.0.0.1:8080` zusätzlich zum Ollama-Tunnel |

### SKB2c — umgesetzt 2026-11-12

| Punkt | Ist |
|-------|-----|
| Pfad | `/opt/playwright/venv` (Python 3.14 venv) |
| Paket | Playwright **1.65.1** |
| Browser | Chromium (Playwright cache unter `~deploy/.cache/ms-playwright/`) |
| Smoke | headless → `example.com` Titel **Example Domain** |
| Nutzung | `/opt/playwright/venv/bin/python` · CLI `/opt/playwright/venv/bin/playwright` |

### SKB2d — umgesetzt 2026-11-12

| Punkt | Ist |
|-------|-----|
| Image | `docker.openhands.dev/openhands/openhands:1.8` · Container `openhands-app` · `restart unless-stopped` |
| Netz | **`--network host`** (erreicht Ollama `127.0.0.1:11434` ohne Öffnen von 11434) |
| UI | Host Port **3000** · UFW default-deny → von außen Timeout · lokal 200 |
| Daten | `/home/deploy/.openhands` · Helper `/opt/openhands/run-openhands.sh` |
| PC-Zugang | Tunnel `-L 3000:127.0.0.1:3000` → Browser `http://127.0.0.1:3000` |
| LLM in UI | Advanced: Base URL `http://127.0.0.1:11434/v1` · Model `openai/qwen2.5-coder:14b` (oder `:32b`) · Key beliebig |

Hinweis: OpenHands will großen Context; ggf. später `OLLAMA_CONTEXT_LENGTH` (eigene Freigabe). Tool-Qualität lokal ≠ Cloud.

### SKB2e — umgesetzt 2026-11-12

| Punkt | Ist |
|-------|-----|
| Pfad | `/opt/comfy/ComfyUI` (+ venv) · später Modelle ggf. nach `/mnt/data/comfy` (Disk-Layout noch ohne Mount) |
| Torch | **2.15.0+cu132** · CUDA **True** · GPU RTX PRO 4000 Blackwell |
| Dienst | `comfyui.service` · `127.0.0.1:8188` · enable+active |
| Smoke | lokal HTTP 200 · außen nicht erreichbar |
| PC | Tunnel `-L 8188:127.0.0.1:8188` → `http://127.0.0.1:8188` |

**SKB2 gesamt ✅** (a–e).

### SKC0b — Ist Dev Ollama-Config (2026-11-12, nur lesen)

Pfad: `/www/htdocs/w0217246/ganz-om.de`

| Punkt | Ist |
|-------|-----|
| `config/kichel.local.php` | **fehlt** |
| `kichel.local.php.example` | da · `ollama_enabled=false` · URL `http://127.0.0.1:11434` · Model `llama3.1:8b` |
| Code | `src/Kichel/KichelOllamaClient.php` vorhanden |
| Ollama vom All-Inkl-Host | `127.0.0.1:11434` nein · `2.29.242.28:11434` Timeout (SKB1 — erwartet) |
| Folge | **SKC0c** muss Weg All-Inkl→GEX-Ollama lösen (nicht nur Config-Datei kopieren) |

### SKC0c — umgesetzt 2026-11-12

**Entscheidung Weg:** All-Inkl PHP → **HTTP nginx Reverse-Proxy auf GEX:80** → `127.0.0.1:11434`.  
11434 bleibt zu. SSH outbound All-Inkl→GEX: kein Key. HTTPS:443 auf GEX noch nicht (PB2c4).

| Punkt | Ist |
|-------|-----|
| Proxy | nginx `snippets/ollama-proxy.conf` · Secret-**Pfad** (Token in `/opt/ollama-proxy/path-token`, Base-URL `/opt/ollama-proxy/crm-base-url`) |
| Dev-Config | `/www/htdocs/w0217246/ganz-om.de/config/kichel.local.php` · `ollama_enabled=true` · Model `qwen2.5-coder:14b` · timeout 120 |
| Smoke | von All-Inkl PHP: `…/api/version` → **200** `0.40.16` |
| Git | **kein** Token/URL-Secret committen |

### SKC0d — umgesetzt 2026-11-12

CLI-Smoke auf Dev `/www/htdocs/w0217246/ganz-om.de`:

| Check | Ergebnis |
|-------|----------|
| `KichelOllamaClient::isEnabled()` | true |
| `phrase()` über Proxy→GEX | OK (~4 s) · Text zurück |
| `KichelAssistant::answer` Skonto-Frage | OK (~1,6 s) · Antwort mit 2,00 / 98,00 |
| Seite `superkihel` | **noch kein Code** auf Dev (nur Skizze SKC0a) — Smoke = Widget/Kichel-Pfad |

### SKC0e — umgesetzt 2026-11-12

Docs nachgezogen: Plan-Haken SKC0b–e · `TODOS.md` · `KICHEL-PHASE2.md` (Ops-Hinweis Proxy, ohne Secret).

**SKC0 gesamt ✅** (a Skizze · b–e Anbindung Dev). Offen später: Super-Kichel-**Seite** UI · TLS für Proxy (PB2c4) · SKD0.

---

## 9. Verweise

| Datei | Rolle |
|-------|--------|
| [`KICHEL-PHASE2.md`](KICHEL-PHASE2.md) | PHP vs. Ollama |
| [`SUPERKICHEL-PRODUKT-SKIZZE.md`](SUPERKICHEL-PRODUKT-SKIZZE.md) | **SKC0a** Abgrenzung Widget vs. Seite |
| [`SUPERKICHEL-INVENTAR-VORLAEUFIG.md`](SUPERKICHEL-INVENTAR-VORLAEUFIG.md) | **SKA0-12v** Inventar ohne SSH |
| [`SUPERKICHEL-SKB0-ENTSCHEIDUNG.md`](SUPERKICHEL-SKB0-ENTSCHEIDUNG.md) | **SKB0** Install ja/nein |
| [`GEX45-DISK-LAYOUT.md`](GEX45-DISK-LAYOUT.md) | NVMe, `OLLAMA_MODELS` |
| [`PHASE-B-REST-PLAN.md`](PHASE-B-REST-PLAN.md) | CRM-Umzug PB* |
| [`CLOUD-AGENT-ACCESS.md`](CLOUD-AGENT-ACCESS.md) | All-Inkl §1 · **Hetzner GEX §1b** |
| [`TODOS.md`](TODOS.md) | Einstiegsliste |

---

## Änderungshistorie

| Datum | Inhalt |
|-------|--------|
| 2026-11-11 | Erstfassung: Workflow + SKA0-1…12 + Zielbild Super-Kichel / `ganz-om.de` / GEX |
| 2026-11-11 | **SK0b** ✅ — Hetzner-Cursor-Secrets in `CLOUD-AGENT-ACCESS.md` §1b |
| 2026-11-11 | **SKC0a** ✅ — Produkt-Skizze `SUPERKICHEL-PRODUKT-SKIZZE.md` |
| 2026-11-11 | **SKA0-12v** ✅ — vorläufiges Inventar `SUPERKICHEL-INVENTAR-VORLAEUFIG.md` |
| 2026-11-11 | **SKB0** ✅ — Entscheidung `SUPERKICHEL-SKB0-ENTSCHEIDUNG.md` |
| 2026-11-12 | **SKB0-Nachtrag** — Docker/SearXNG/Playwright/OpenHands/Comfy = ja (SKB2a–e); Forgejo nein; Cloud-Docs auf master |
| 2026-11-12 | **SKB1a–d** Mini-IDs · **SKB1a ✅** Ist Ollama öffentlich `0.0.0.0` + UFW 11434 open (§8) |
| 2026-11-12 | **SKB1b ✅** Bind `127.0.0.1:11434` — außen tot; UFW-Regel noch da (SKB1c) |
| 2026-11-12 | **SKB1c ✅** UFW 11434 ALLOW entfernt (v4+v6) |
| 2026-11-12 | **SKB1d ✅** SSH-Tunnel + Cursor `127.0.0.1:11434/v1` · SKB1 abgeschlossen |
| 2026-11-12 | **SKB2a ✅** docker.io + compose-v2 · hello-world OK · kein API-Port außen |
| 2026-11-12 | **SKB2b ✅** SearXNG Compose unter `/opt/searxng/stack` · nur `127.0.0.1:8080` |
| 2026-11-12 | **SKB2c ✅** Playwright 1.65.1 + Chromium unter `/opt/playwright` · Smoke OK |
| 2026-11-12 | **SKB2d ✅** OpenHands 1.8 host-network · UI :3000 lokal · LLM→Ollama localhost |
| 2026-11-12 | **SKB2e ✅** ComfyUI 0.45 · Torch CUDA · systemd `127.0.0.1:8188` · SKB2 fertig |
| 2026-11-12 | **SKC0b–e** Mini-IDs · **SKC0b ✅** Dev ohne `kichel.local.php` · GEX-Ollama von All-Inkl unerreichbar |
| 2026-11-12 | **SKC0c ✅** nginx path-secret Proxy :80 → Ollama · `kichel.local.php` auf Dev · Smoke 200 |
| 2026-11-12 | **SKC0d ✅** phrase+Assistant Skonto auf Dev OK · Super-Kichel-Seite noch nicht im Code |
| 2026-11-12 | **SKC0e ✅** Docs/Haken · SKC0 Block abgeschlossen |
