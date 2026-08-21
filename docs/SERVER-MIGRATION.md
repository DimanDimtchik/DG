# Server-Umzug & Infrastruktur-Plan

> Stand: 2026-08-21  
> Verwandt: [ELSTER-ERIC-TODO.md](./ELSTER-ERIC-TODO.md) · [CLOUD-AGENT-ACCESS.md](./CLOUD-AGENT-ACCESS.md)

---

## Entscheidungen (festhalten)

| Punkt | Stand |
|--------|--------|
| **Derzeitiger Server** | Kasserver / All-Inkl Shared Webhosting (`dg.ganz-om.de`) |
| **Deploy** | `scp`/`ssh`, PHP + MariaDB, **kein Root**, **kein Docker** |
| **ELSTER-Strategie** | **ERiC direkt** (keine Middleware wie Elmar) — **erst nach Umzug** |
| **Server-Favorit** | **Hetzner SX65-2** (Dedicated Storage, Auction/Konfiguration der SX65-Linie) |
| **Bis Umzug** | CSV-Export + manuelle ELSTER-Eingabe (bereits implementiert) |

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

## Server-Favorit: Hetzner SX65-2

Produktlinie: [Hetzner SX65 Storage Server](https://www.hetzner.com/dedicated-rootserver/sx65)  
Doku: [Hetzner SX Server Konfigurationen](https://docs.hetzner.com/robot/dedicated-server/server-lines/sx-server/)

> **Hinweis:** „SX65-2“ ist eine konkrete Robot/Auction-Konfiguration (Variante der SX65-Linie). Technische Basis entspricht der SX65-Familie; exakte Festplattenbelegung beim Bestellen prüfen.

### Typische SX65-Specs (Referenz)

| Komponente | SX65 (Basis) |
|------------|----------------|
| CPU | AMD Ryzen™ 7 3700X, 8 Kerne / 16 Threads |
| RAM | 64 GB DDR4 ECC (Upgrade bis 128 GB, Modellabhängig) |
| NVMe | 2× 1 TB (System / schnelle DB) |
| SATA | bis 4× 22 TB Enterprise HDD (Backups, Belege, Archive) |
| Netz | 1 Gbit/s, Traffic unbegrenzt (Standard-Uplink) |
| Standorte | FSN1, HEL1 |
| Root | ✅ voller Root-Zugriff |
| Docker | ✅ möglich |
| ERiC Linux x64 | ✅ geeignet |
| Preis grob | ab ca. 104 €/Monat + Setup (Stand 2025/26, je nach Auktion günstiger) |

### Warum SX65-2 als Favorit sinnvoll ist

- **Viel NVMe + HDD:** CRM-Datenbank auf NVMe, Beleg-PDFs und Backups auf HDD
- **64+ GB RAM:** Master-CRM, KDV, mehrere Kunden-Instanzen (später), ERiC, MariaDB parallel
- **8 Kerne:** ERiC ist single-threaded pro Vorgang — Rest für PHP-FPM, Cron, Backups
- **Dedicated:** Kein Shared-Hosting-Limit für `exec`, Daemon, ausgehende ELSTER-Verbindungen
- **Langfristig:** Ein Server für dg.ganz-om.de + KDV + ERiC statt Kasserver + Extra-VPS

---

## Mindestanforderungen (Merken)

### Absolutes Minimum — nur ERiC-Worker (Sidecar neben Kasserver)

Falls ihr **vor** dem Full-Umzug nur ELSTER ergänzen wolltet (nicht empfohlen, aber technisches Minimum):

| Ressource | Minimum |
|-----------|---------|
| vCPU | 2 |
| RAM | 2 GB |
| Disk | 20 GB SSD |
| OS | Debian 12 / Ubuntu 22.04 LTS x64 |
| Netz | Ausgehend HTTPS zu ELSTER |

### Empfohlen — DG + ERiC auf einem Root-Server (Zielbild)

| Ressource | Empfohlen | SX65-2 |
|-----------|-----------|--------|
| vCPU / Kerne | 4+ | 8 ✅ |
| RAM | 8–16 GB | 64 GB ✅ |
| Disk System | 40 GB NVMe | 2× 1 TB NVMe ✅ |
| Disk Daten | 100 GB+ | TB HDD ✅ |
| OS | Debian 12 / Ubuntu 22.04 LTS | ✅ |
| Docker | optional (nicht nötig für ERiC direkt) | ✅ |
| Backup | täglich, off-site | HDD + Hetzner Snapshot |

### Software-Stack auf Zielserver (Plan)

| Dienst | Zweck |
|--------|--------|
| nginx oder Apache | CRM, Shop-Reverse-Proxy |
| PHP 8.2+ FPM | DG-Anwendung |
| MariaDB 10.11+ | CRM-Datenbank |
| ERiC (Linux x64) | ELSTER-Übermittlung |
| `dg-eric-worker` (CLI/HTTP) | Wrapper um ERiC, von PHP aufgerufen |
| certbot | TLS |
| restic/rsync | Backups auf HDD |

---

## Migrationsphasen (Überblick)

| Phase | Inhalt | Wann |
|-------|--------|------|
| **0 — Jetzt** | CSV-UStVA, Docs, Code-Vorbereitung (`ElsterSettings`, Stubs) | ✅ läuft auf Kasserver |
| **1 — Server** | Hetzner SX65-2 bestellen, OS installieren, Hardening | Nach Bestellung |
| **2 — Parallel** | DG auf neuem Server deployen, DNS TTL senken, Test-Domain | Vor Cutover |
| **3 — Daten** | DB-Dump, `storage/` rsync, Cron, Mail testen | Cutover-Wochenende |
| **4 — ERiC** | Hersteller-ID, ERiC installieren, Test-Zertifikat, UStVA Testmerker | Nach stabilem CRM |
| **5 — Produktion ELSTER** | Echtes Zertifikat auf Server, Modus `eric`, Abgabe aus UStVA-UI | Nach erfolgreichen Tests |
| **6 — Kasserver** | Alte Instanz read-only, dann abschalten | Wenn neu stabil |

Details: [ELSTER-ERIC-TODO.md](./ELSTER-ERIC-TODO.md)

---

## DNS & Domains (bei Umzug)

| Domain | Heute | Ziel |
|--------|-------|------|
| dg.ganz-om.de | Kasserver | Hetzner SX65-2 |
| shop.ganz-soft.de | Kasserver | gleicher Server oder separat |
| Kunden-CRMs (KDV) | Kasserver Subdirs | später: gleicher Server oder Subdomains |

---

## Konfiguration im Repo (Vorbereitung)

| Datei | Zweck |
|-------|--------|
| `config/elster.local.php.example` | Worker-URL, Pfade, Hersteller-ID (nicht committen) |
| `src/Settings/ElsterSettings.php` | Modus `csv` / `eric`, Vorbereitungsfelder |
| `src/Accounting/ElsterEricClient.php` | Readiness-Check, später ERiC-Aufruf |
| `bin/elster-readiness.php` | Diagnose auf Server |

Nach Umzug: `config/elster.local.php` aus Example anlegen, Tab **Einstellungen → ELSTER** ausfüllen.

---

## Checkliste „Server bestellt?“

- [ ] Hetzner Robot: SX65-2 (oder vergleichbare Config) bestellt
- [ ] IPv4 notiert, Firewall (nur 22, 80, 443)
- [ ] OS: Debian 12 oder Ubuntu 22.04 LTS
- [ ] SSH-Key, kein Passwort-Login
- [ ] MariaDB + PHP-FPM + nginx
- [ ] `docs/ELSTER-ERIC-TODO.md` Phase 1 abarbeiten
