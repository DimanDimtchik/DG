# Super-Kichel — Inventar vorläufig (SKA0-12v)

> **Ohne SSH.** Quellen: Außenprobe GEX45-1 + Phase-B-Docs.  
> **Kein** Ersatz für **SKA0-1…12** / **SKA0-12**. Keine Installationsfreigabe.  
> Plan: [`SUPERKICHEL-GEX-PLAN.md`](SUPERKICHEL-GEX-PLAN.md)

Stand Probe: **2026-11-11** (erneuert abends)

---

## 1. Methodik / Grenzen

| Quelle | Was sie kann | Was sie nicht kann |
|--------|--------------|-------------------|
| TCP/HTTP von außen | Offene Ports, nginx-Banner, Ollama-API | Localhost-only Dienste, Pakete, GPU, Pfade |
| Docs PB1/PB2 | Geplanter/ dokumentierter Stack | Live-`systemctl` / Mounts |
| DNS `ganz-om.de` | Host der Dev-CRM-Seite | Cutover-Status (PB4 offen → All-Inkl) |

---

## 2. Außenprobe (GEX IPv4)

| Port / Dienst | Ergebnis |
|---------------|----------|
| 22 SSH | offen |
| 80 HTTP | offen · `Server: nginx/1.28.3 (Ubuntu)` · Default-Welcome |
| 443 HTTPS | Port offen; TLS/App noch nicht produktiv abgenommen (PB2c) |
| 11434 Ollama | offen · Antwort „Ollama is running“ · Version **0.40.16** |
| 2375/2376 Docker-API | zu |
| 3000 / 8080 / 8088 / 2222 (OpenHands/SearXNG/Forgejo-typisch) | zu |

**Ollama-Modelle (API `/api/tags`):** `qwen2.5-coder:32b`, `qwen2.5-coder:14b`, `llama3.1:8b`.

**Risiko:** Ollama ist von außen erreichbar — widerspricht Zielbild `127.0.0.1:11434` ([`GEX45-DISK-LAYOUT.md`](GEX45-DISK-LAYOUT.md)). Härten = **SKB1** (nach SSH + Freigabe).

---

## 3. Fazit-Tabelle (vorläufig)

| Komponente | Einstufung | Beleg |
|------------|------------|--------|
| Ubuntu 26.04 / Berlin / `deploy` / UFW 22·80·443 / Passwort-SSH aus | **vorhanden (Docs)** | PB1 ✅ — live erst SKA0-1/4/5 |
| nginx | **vorhanden** | Außenprobe + PB2a |
| PHP-FPM / MariaDB / certbot | **vorhanden (Docs)** | PB2a Teil — Versionen live erst SKA0-6 |
| CRM-Hülle `instances/ganz-om.de` + shared Code-Probe | **vorhanden (Docs)** | PB2b/c1 — DNS weiter All-Inkl |
| **Ollama** + 3 Modelle | **vorhanden / unsicher Bindung** | API live; Bind = öffentlich (Härte offen) |
| **Docker** | **unsicher** | API-Ports zu; Binary unbekannt ohne SSH (SKA0-7) |
| GPU-Treiber | **unsicher** | Hardware ja (GEX45); `nvidia-smi` ohne SSH (SKA0-8) |
| OpenHands | **fehlt (außen)** / ggf. nur Zielbild | keine typischen Ports |
| SearXNG | **fehlt (außen)** / ggf. nur Zielbild | keine typischen Ports |
| Forgejo | **fehlt (außen)** / ggf. nur Zielbild | keine typischen Ports |
| Playwright | **unsicher** | kein Netzdienst; oft nur lokal/CI |
| ComfyUI | **unsicher / Zielbild** | Disk-Layout-Plan; nicht geprobt |
| NVMe-B `/mnt/data` Layout | **unsicher** | nur Soll-Doku — SKA0-3/11 |

---

## 4. Vorschlag für spätere SKB0 (noch keine Freigabe)

| Priorität | Maßnahme | Warum |
|-----------|----------|--------|
| 1 | **SKB1** Ollama auf localhost + UFW | öffentlich erreichbar |
| 2 | Ollama für Super-Kichel **nutzen** (nicht neu installieren) | schon da + passende Modelle |
| 3 | Docker nur wenn SearXNG/OpenHands wirklich gewollt | SKA0-7 zuerst |
| 4 | OpenHands / Forgejo | **bewusst später / oft entbehrlich** (Cloud-Agent + GitHub) |
| 5 | SearXNG / Playwright | nur nach klarem Super-Kichel-Bedarf |
| 6 | CRM↔Ollama Dev | **SKC0** nach Härten; Seite laut [`SUPERKICHEL-PRODUKT-SKIZZE.md`](SUPERKICHEL-PRODUKT-SKIZZE.md) |

---

## 5. Nächste Live-IDs (SSH)

`SKA0-1` → … → `SKA0-11` → echtes **`SKA0-12`** (diese Datei ersetzen oder als Anhang „v“ behalten).

---

## Änderungshistorie

| Datum | Inhalt |
|-------|--------|
| 2026-11-11 | **SKA0-12v** Erstfassung aus Außenprobe + PB-Docs |
