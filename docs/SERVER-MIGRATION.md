# Server-Umzug & Infrastruktur-Plan

> Stand: **2026-11-09**  
> Verwandt: [ELSTER-ERIC-TODO.md](./ELSTER-ERIC-TODO.md) · [CLOUD-AGENT-ACCESS.md](./CLOUD-AGENT-ACCESS.md) · Phase B: [`IQ-STROM-HETZNER-TODO.md`](./IQ-STROM-HETZNER-TODO.md) · tokenarm: [`PHASE-B-REST-PLAN.md`](./PHASE-B-REST-PLAN.md)

---

## Entscheidungen (festhalten)

| Punkt | Stand |
|--------|--------|
| **Derzeitiger Server** | Kasserver / All-Inkl Shared Webhosting (`dg.ganz-soft.de` / CRM-Instanzen) |
| **Deploy heute** | `scp`/`ssh`/`rsync`, PHP + MariaDB, **kein Root** auf All-Inkl, **kein Docker** |
| **ELSTER-Strategie** | **ERiC direkt** (keine Middleware wie Elmar) — **erst nach Umzug** |
| **Zielserver** | **Hetzner GEX45-1** — **bestellt** · Bestellnr. **B20261107-3544202** (2026-11-07) · Bereitstellung in **einigen Wochen** · Platten: [`GEX45-DISK-LAYOUT.md`](GEX45-DISK-LAYOUT.md) |
| **OS-Ziel** | **Ubuntu 26.04 LTS** (x64) · Zeitzone `Europe/Berlin` |
| **Standort** | **Finnland, HEL1** (Bestellung bestätigt) · Hetzner-Support für HEL1 **nur Englisch** |
| **Netz** | **1× Primäre IPv4** (bestellt) · IPv6 bei Freischaltung in Robot prüfen (PB1a) |
| **Skalierung** | **SX65** o. ä. erst bei Mandantenwachstum / voller Platte — nicht für ersten Cutover |
| **Bis Umzug** | CSV-Export + manuelle ELSTER-Eingabe (bereits implementiert) · CRM weiter All-Inkl |

---

## Derzeitiger Server (Kasserver)

| Eigenschaft | Wert |
|-------------|------|
| Typ | Shared Webhosting |
| Root | ❌ nein |
| Docker | ❌ nein |
| SSH | ✅ (ab Premium) |
| PHP / MariaDB | ✅ |
| Eigene Binaries / Daemon | ⚠️ eingeschränkt |
| ERiC nativ | ❌ nicht sinnvoll |
| Geeignet für | CRM, Shop-API, KDV-Provision, DATEV-CSV |

**Fazit:** Buchhaltung und DIY-UStVA (CSV) laufen hier. **ELSTER-Übermittlung via ERiC gehört auf einen Root-Server.**

---

## Zielserver: Hetzner GEX45-1 (bestellt)

Produkt: [Hetzner GEX45](https://www.hetzner.com/dedicated-rootserver/gex45/) · Doku GPU-Linie: [GPU Server](https://docs.hetzner.com/robot/dedicated-server/server-lines/gpu-server/)

### Bestellung (Robot / Auftragsbestätigung)

| Feld | Wert |
|------|------|
| Bestellnummer | **B20261107-3544202** |
| Produkt | **1× Dedicated GPU-Server GEX45-1** |
| Standort | **Finnland, HEL1** |
| OS (Base) | **Ubuntu 26.04 LTS** |
| Netz | **1× Primäre IPv4** |
| Support-Hinweis | Für Finnland HEL1 nur **englischer** Hetzner-Support |
| Status | bestellt 2026-11-07 · noch nicht freigeschaltet (PB1) |

| Komponente | GEX45-1 (laut Produktlinie / Bestellung) |
|------------|----------------------------------|
| CPU | Intel Core i5-13500 (14 Kerne / 20 Threads) |
| GPU | NVIDIA RTX PRO 4000 Blackwell SFF · 24 GB GDDR7 ECC (Bonus; CRM/ERiC brauchen die GPU nicht zwingend) |
| RAM | 64 GB DDR4 |
| Disk | 2× 512 GB NVMe |
| Netz | 1 Gbit/s, Traffic unbegrenzt (Standard) · **1× Primäre IPv4** bestellt |
| Standort | Finnland, **HEL1** (EU/EWR) |
| Root | ✅ |
| Docker | ✅ möglich |
| ERiC Linux x64 | ✅ geeignet |
| Preis grob | ca. 214 €/Monat + Setup (Stand Produktseite; Vertrag im Robot maßgeblich) |

### Warum GEX45 für DG passt

- **Root + 64 GB RAM:** CRM-Instanzen, MariaDB, PHP-FPM, später ERiC-Worker parallel
- **NVMe:** DB und Belege schnell; Backup-Strategie (Snapshot/off-site) separat planen — kein großes HDD-Array wie SX65
- **GPU:** optional für AI/Rendering-Nebenprojekte; **kein** Blocker und keine Pflicht für FiBu/ELSTER
- Früher Favorit **SX65-2** (Storage-HDD) bleibt als Referenz verworfen zugunsten der bestellten GEX45

---

## Mindestanforderungen (Merken)

### Absolutes Minimum — nur ERiC-Worker (Sidecar)

| Ressource | Minimum |
|-----------|---------|
| vCPU | 2 |
| RAM | 2 GB |
| Disk | 20 GB SSD |
| OS | Debian 12 / Ubuntu 24.04+ LTS x64 |
| Netz | Ausgehend HTTPS zu ELSTER |

### Empfohlen — DG + ERiC (Zielbild = GEX45)

| Ressource | Empfohlen | GEX45 |
|-----------|-----------|--------|
| vCPU / Kerne | 4+ | 14/20 ✅ |
| RAM | 8–16 GB+ | 64 GB ✅ |
| Disk System | 40 GB+ NVMe | 2× 512 GB ✅ |
| OS | Ubuntu 26.04 LTS (bestellt) / Debian 12 | ✅ |
| Backup | täglich, off-site | Snapshot + off-site (kein großes HDD) |

### Software-Stack auf Zielserver (Plan)

| Dienst | Zweck |
|--------|--------|
| nginx oder Apache | CRM, Shop-Reverse-Proxy |
| PHP 8.2+ FPM | DG-Anwendung |
| MariaDB 10.11+ | CRM-Datenbank(en) |
| ERiC (Linux x64) | ELSTER-Übermittlung (nach Cutover) |
| `dg-eric-worker` (CLI/HTTP) | Wrapper um ERiC |
| certbot | TLS |
| restic/rsync | Backups / off-site |

---

## Migrationsphasen (Überblick)

| Phase | Inhalt | Wann |
|-------|--------|------|
| **0 — Jetzt** | CSV-UStVA, Docs, Code-Vorbereitung | ✅ Kasserver |
| **1 — Server** | GEX45-1 geliefert · Ubuntu 26.04 LTS · Hardening · User `deploy` | Nach Bereitstellung (Wochen) |
| **2 — Parallel** | DG probeweise deployen, Test-Domain/TTL | Vor Cutover |
| **3 — Daten** | DB-Dump, `storage/` rsync, Cron, Mail | Cutover-Fenster |
| **4 — ERiC** | Hersteller-ID, ERiC, Test-Zertifikat | Nach stabilem CRM |
| **5 — Produktion ELSTER** | Echtes Zertifikat, Modus `eric` | Nach Tests |
| **6 — Kasserver** | Alt read-only, dann abschalten | Wenn neu stabil |

Tokenarme Mini-IDs: [`PHASE-B-REST-PLAN.md`](./PHASE-B-REST-PLAN.md) · Details ERiC: [`ELSTER-ERIC-TODO.md`](./ELSTER-ERIC-TODO.md)

---

## DNS & Domains (bei Umzug)

| Domain | Heute | Ziel |
|--------|-------|------|
| dg.ganz-soft.de / CRM-Kern | All-Inkl | Hetzner GEX45 |
| Dev / Kunden-Instanzen | All-Inkl | gleicher Server oder gestaffelt |
| shop.ganz-soft.de | All-Inkl | gleicher Server oder separat |

---

## Konfiguration im Repo (Vorbereitung)

| Datei | Zweck |
|-------|--------|
| `config/elster.local.php.example` | Worker-URL, Pfade, Hersteller-ID (nicht committen) |
| `src/Settings/ElsterSettings.php` | Modus `csv` / `eric` |
| `src/Accounting/ElsterEricClient.php` | Readiness / später ERiC |
| `bin/elster-readiness.php` | Diagnose |

Nach Umzug: `config/elster.local.php` anlegen, Tab **Einstellungen → ELSTER** ausfüllen.

---

## Checkliste „Server bestellt / bereit?“

- [x] Hetzner Robot: **GEX45-1** bestellt — **B20261107-3544202** (2026-11-07) · Standort Finnland HEL1 · Ubuntu 26.04 LTS · 1× IPv4
- [ ] Server von Hetzner freigeschaltet / Primäre IPv4 notiert (PB1a)
- [ ] Firewall (nur 22, 80, 443; SSH-Key)
- [ ] OS: **Ubuntu 26.04 LTS** installiert · `Europe/Berlin`
- [ ] User `deploy`, kein Passwort-Login
- [ ] MariaDB + PHP-FPM + nginx + Let’s Encrypt
- [ ] Mini-IDs in [`PHASE-B-REST-PLAN.md`](./PHASE-B-REST-PLAN.md) abarbeiten
