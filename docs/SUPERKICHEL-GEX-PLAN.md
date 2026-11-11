# Super-Kichel / GEX45 — Bestandsaufnahme & Zielbild (tokenarm)

> **Eine Chat-Session = eine Mini-ID.**  
> **Zielbild ≠ Installationsfreigabe.** Erst Inventar (SKA0), dann Freigabe je Install-ID.  
> Produktlinie: **Kichel** im CRM · Dev-Zielinstanz **`ganz-om.de`** · KI-Backend auf **GEX45-1**.  
> Verwandt: [`KICHEL-PHASE2.md`](KICHEL-PHASE2.md) · [`GEX45-DISK-LAYOUT.md`](GEX45-DISK-LAYOUT.md) · [`PHASE-B-REST-PLAN.md`](PHASE-B-REST-PLAN.md) · [`SERVER-MIGRATION.md`](SERVER-MIGRATION.md)

Stand: **2026-11-11** · Server: **GEX45-1** · HEL1 · Ubuntu 26.04 LTS (PB1 ✅) · CRM-Stack PB2 teilweise

---

## 0. Was „Super-Kichel“ hier heißt

| Schicht | Wo | Rolle |
|---------|-----|--------|
| **Kichel (CRM)** | Instanz-DB + PHP (`src/Kichel/…`) | Regeln, Beträge, Navigation, Protokoll — **GoBD-/Fachlogik bleibt PHP** |
| **Ollama** | GEX45, ideal nur `127.0.0.1:11434` | Nur Formulierung (`KichelOllamaClient::phrase`) |
| **Dev-Oberfläche** | **`ganz-om.de`** (später ggf. Route/Feature „Super-Kichel“) | Testen & Freigabe vor anderen Instanzen |
| **Ops-Hilfen (Zielbild)** | nur nach Freigabe, oft Docker | z. B. SearXNG (Recherche), Playwright (UI-Checks/Akademie), OpenHands/Forgejo nur wenn wirklich nötig |

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
| SKA0 | Bestandsaufnahme GEX45 (SKA0-1 … SKA0-12) | [ ] | 🔴🟢 |
| SKB0 | Entscheidung: was wird wirklich installiert | [ ] | 🟢 |
| SKB1 | Härten / binden (Ollama localhost, UFW) — nur nach Freigabe | [ ] | 🔴 |
| SKB2 | Fehlende Komponenten installieren — **einzeln** je Kind-ID | [ ] | 🔴 |
| SKC0 | CRM-Anbindung Dev `ganz-om.de` (`kichel.local.php`, Smoke) | [ ] | 🔴🟡 |
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
| **SKA0-12** | **Inventar-Fazit**: Tabelle „vorhanden / fehlt / unsicher / bewusst später“ + Vorschlag SKB0 | Freigabe-Vorlage für Install | 🟢 | [ ] |

**Vorläufige Außenprobe (2026-11-11, ohne SSH — nicht Abnahme):**

| Komponente | Probe |
|------------|--------|
| nginx :80 | Default-Seite Ubuntu |
| Ollama :11434 | `0.40.16`, Modelle `qwen2.5-coder:32b`, `qwen2.5-coder:14b`, `llama3.1:8b` — **öffentlich erreichbar** |
| OpenHands / SearXNG / Forgejo / Docker-API | typische Ports zu |
| Playwright | nicht als Netzdienst prüfbar |

---

## 5. Zielbild-Komponenten (nur nach SKB0-Freigabe)

| Komponente | Nutzen für Super-Kichel / Ops | Default nach Inventar |
|------------|-------------------------------|------------------------|
| **Ollama** | Formulierung Kichel | **Nutzen/härten**, nicht neu erfinden |
| **Docker** | saubere Isolation SearXNG/OpenHands/… | nur wenn SKA0-7 fehlt und SKB0 es fordert |
| **SearXNG** | optionale Web-Recherche für Assistent | nur bei Bedarf; nie öffentlich ohne Auth |
| **Playwright** | Akademie/UI-Smoke, keine Fachlogik | oft Dev/CI; auf GEX nur wenn begründet |
| **OpenHands** | Coding-Agent auf dem Server | **bewusst optional** — oft Konflikt mit Cloud-Agent-Workflow |
| **Forgejo** | eigener Git auf dem Server | **bewusst optional** — GitHub bleibt Produkt-Repo |
| **ComfyUI** | Bild/Video-Nebenlinie | laut Disk-Layout; **nicht** Blocker für Kichel |

PHP-Regel aus Phase 2 bleibt: **Ollama rechnet keine Beträge.**

---

## 6. Empfohlene Reihenfolge

1. **SKA0-1** … **SKA0-12** (eine ID pro Freigabe)  
2. **SKB0** Entscheidungstabelle abhaken  
3. **SKB1** Härten (mind. Ollama → localhost + UFW), bevor CRM darauf zeigt  
4. **SKB2-*** nur freigegebene Installs  
5. **SKC0** Dev `ganz-om.de`: `config/kichel.local.php` → GEX-Ollama (SSH-Tunnel oder localhost-Proxy laut Freigabe) + Smoke  
6. **SKD0** Docs (`KICHEL-PHASE2`, TODOS, Disk-Layout)

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

## 8. Verweise

| Datei | Rolle |
|-------|--------|
| [`KICHEL-PHASE2.md`](KICHEL-PHASE2.md) | PHP vs. Ollama |
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
