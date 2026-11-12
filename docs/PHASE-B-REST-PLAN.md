# Phase B — Hetzner-Umzug (tokenarm)

> **Eine Chat-Session = eine Mini-ID.**  
> Fach: [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) §B · Hardware: [`SERVER-MIGRATION.md`](SERVER-MIGRATION.md)

Stand: **2026-11-12** · Zielserver: **GEX45-1** (Bestellnr. **B20261107-3544202**) · Standort: **Finnland, HEL1** · OS: **Ubuntu 26.04 LTS** · IPv4 **`2.29.242.28`** · IPv6 **`2a01:4f9:3120:23ae::2`** · **PB1 ✅** · **PB2c ✅** (Probe HTTPS `crm-gex.ganz-soft.de`)

---

## Warum tokenarm

| Regel | Bedeutung |
|-------|-----------|
| **Kein Explorer** | Nur die Mini-ID |
| **Kein Cutover ohne Freigabe** | PB1–PB3 = Prep; Cutover = eigene IDs + Termin |
| **Kein ERiC live** | C1 nach stabilem CRM |
| **1 ID = 1 Commit oder 1 Ops-Nachweis** | Doc-Haken / Config-Notiz / Checkliste |
| **Kein Tenant im Produktcode** | Hosts nur Ops-Docs |

**Chat-Prompt:**  
`Nur Mini-ID PB2a laut docs/PHASE-B-REST-PLAN.md. Kein Cutover. Kein Tenant-Hardcode.`

---

## Token-Stufen

| Symbol | Wann |
|--------|------|
| 🟢 | Doc, Checkliste, Statusnotiz |
| 🟡 | kleines Script/Config-Beispiel im Repo |
| 🔴 | Mensch am Server / DNS / Robot |

---

## Parent

| ID | Thema | Status | Token |
|----|--------|--------|-------|
| PB0 | Inventar / dieser Plan | [x] 2026-11-08 | 🟢 |
| PB1 | Server da / Basis-OS | [x] 2026-11-11 | 🟢 |
| PB2 | Stack + Deploy-Probe | [ ] | 🔴🟢 |
| PB3 | Cutover-Vorbereitung | [ ] | 🟢🔴 |
| PB4 | Cutover + 48 h | [ ] | 🔴 |
| PB5 | Nachzug Docs/Haken | [ ] | 🟢 |

---

## Mini-IDs

### PB0 — Plan

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| PB0a | Dieser Plan + Serverdaten GEX45/Ubuntu 26 in `SERVER-MIGRATION.md` | 🟢 | [x] 2026-11-08 |
| PB0b | Bestellung abgeglichen: GEX45-1 · B20261107-3544202 · Finnland HEL1 · Ubuntu 26.04 LTS · 1× IPv4 | 🟢 | [x] 2026-11-09 |

### PB1 — Lieferung & Basis (erst wenn Server live)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| PB1a | Robot: freigeschaltete **Primäre IPv4** (+ ggf. IPv6) notieren · Standort HEL1 bereits per Bestellung bestätigt · Notiz in `SERVER-MIGRATION.md` (ohne Secrets) | 🔴 | [x] 2026-11-11 · `2.29.242.28` / `2a01:4f9:3120:23ae::2` |
| PB1b | Ubuntu **26.04 LTS** installiert · `timedatectl` Berlin · Updates | 🔴 | [x] 2026-11-11 |
| PB1c | Firewall 22/80/443 · SSH-Key User `deploy` · Passwort-Login aus | 🔴 | [x] 2026-11-11 |
| PB1d | Statusnotiz „Basis grün“ in IQ-STROM §B | 🟢 | [x] 2026-11-11 |

**Prompt:** `Nur Mini-ID PB1a laut docs/PHASE-B-REST-PLAN.md. Nur Docs-Notiz IP/Standort. Kein Cutover.`

### PB2 — Stack & Probe-Deploy

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| PB2a | nginx + PHP 8.2+ FPM + MariaDB 10.11+ + certbot (Test-Domain oder IP) | 🔴 | [~] 2026-11-11 Pakete+Ordner+sudo ✅ (PHP 8.5.4 · nginx · MariaDB · certbot Paket) · vHost/TLS/Probe offen |
| PB2b | Deploy-Pipeline Probe laut [`DEPLOY-PIPELINE-PREP.md`](DEPLOY-PIPELINE-PREP.md) (ohne Produktiv-DNS) | 🔴 | [x] 2026-11-11 tar→`/var/www/dg-crm/shared` (~3697 Dateien; kein rsync lokal; ohne DNS) |
| PB2c | Leere/Test-Instanz erreichbar HTTPS · Smoke `/login` | 🔴 | [x] 2026-11-12 · PB2c1–c4 ✅ · Probe `https://crm-gex.ganz-soft.de/login` |
| **PB2c4a** | Ist: CRM-vHost nur :80 · DNS `ganz-om.de`→All-Inkl · kein CRM-Cert — **nur lesen** | 🟢 | [x] 2026-11-12 · siehe § PB2c4 |
| **PB2c4b** | Hostname `crm-gex.ganz-soft.de` · KAS A → GEX `2.29.242.28` | 🔴 | [x] 2026-11-12 · Auth-NS A=GEX |
| **PB2c4c** | nginx `server_name` + certbot LE für Probe-Host | 🔴 | [x] 2026-11-12 · LE bis 2027-02-10 |
| **PB2c4d** | Smoke HTTPS `/login` · Docs/Haken · PB2c abschließen | 🔴🟡 | [x] 2026-11-12 |
| PB2d | Doc-Haken PB2 in IQ-STROM | 🟢 | [ ] |

> **Abgrenzung:** Ollama-Proxy-TLS = **SKC1** (`llm.ganz-soft.de`) — fertig. **PB2c4** = HTTPS für **CRM-Probe**-vHost — kein Cutover `ganz-om.de` (**PB4**).

### PB2c4 — Zerlegung CRM-Probe HTTPS (2026-11-12)

**Warum eigener Hostname:** Öffentliches DNS `ganz-om.de` / `www` → All-Inkl. LE HTTP-01 auf GEX für diesen Namen trifft All-Inkl. Probe-Host analog SKC1: **`crm-gex.ganz-soft.de`**.

#### PB2c4a — Ist (2026-11-12, nur lesen) ✅

| Punkt | Befund |
|-------|--------|
| **Ports GEX** | `:80` + `:443` (443 nur durch **`llm.ganz-soft.de`**, SKC1) |
| **CRM-vHost** | `sites-enabled/ganz-om.de` · `listen 80` only · `server_name ganz-om.de www.ganz-om.de` · root `instances/ganz-om.de/public` |
| **CRM-Cert** | **kein** `/etc/letsencrypt/live/ganz-om.de` · live nur `llm.ganz-soft.de` |
| **Smoke** | `Host: ganz-om.de` HTTP `/login` → **200** · HTTPS gleicher Host → **404** (kein CRM-SSL-Server) |
| **DNS öffentlich** | `ganz-om.de` / `www` → **85.13.168.110** (All-Inkl) · `crm-gex.ganz-soft.de` noch Catch-all/CNAME All-Inkl (kein A→GEX) |
| **Instanz** | `instances/ganz-om.de` da · `config/database.local.php` · **kein** `app.local.php` in Instanz/shared (SC2-Wiring weiter offen) |
| **Nicht** | Certbot · DNS ändern · Cutover (**PB4**) · Ollama-vHost |

**Folge:** **PB2c4b** — KAS A `crm-gex` → `2.29.242.28` (Apex/`*` unverändert).

#### PB2c4b — DNS (2026-11-12) ✅

| Punkt | Stand |
|-------|--------|
| **KAS** | A `crm-gex` → `2.29.242.28` (neben `llm`; Apex All-Inkl) |
| **Auth-NS** | `ns5`/`ns6.kasserver.com` → **2.29.242.28** |
| **Nicht** | Cutover `ganz-om.de` |

#### PB2c4c — Cert (2026-11-12) ✅

| Punkt | Stand |
|-------|--------|
| **nginx** | `server_name` … `crm-gex.ganz-soft.de` am CRM-vHost |
| **Cert** | LE `crm-gex.ganz-soft.de` · bis **2027-02-10** · Auto-Renew · getrennt von `llm…` |
| **Vorab-Smoke** | `https://crm-gex.ganz-soft.de/login` → **200** + Anmeldeformular (formal **PB2c4d**) |

#### PB2c4d — Smoke + Haken (2026-11-12) ✅

| Check | Ergebnis |
|-------|----------|
| DNS Auth | `crm-gex.ganz-soft.de` → `2.29.242.28` |
| HTTPS `/login` | **200** · TLS verify OK · Formular (Passwort + „Anmelden“) |
| HTTPS `/` | **302** → `/login` |
| HTTP `/login` | **301** → HTTPS |
| Certs live | `crm-gex.ganz-soft.de` · `llm.ganz-soft.de` |
| **Nicht** | Cutover `ganz-om.de` (**PB4**) · SC2 Locals · Login-User-Inhalt |

**PB2c ✅.** Offen in PB2: **PB2d** (IQ-STROM Doc-Haken) · parallel SC2.

### PB3 — Cutover-Vorbereitung (Docs + Termine)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| PB3a | Cutover-Checkliste: Domains, TTL, Dump-Reihenfolge, Rollback | 🟢 | [ ] |
| PB3b | Backup/off-site Ziel auf GEX45 skizzieren (kein HDD wie SX65) | 🟢 | [ ] |
| PB3c | SMTP/Mail-Plan nach Cutover (kurz) | 🟢 | [ ] |

### PB4 — Cutover (nur mit ausdrücklicher Freigabe)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| PB4a | DB-Dump + `storage/` Sync Produktiv → GEX45 | 🔴 | [ ] |
| PB4b | DNS Cutover · 48 h Monitoring | 🔴 | [ ] |
| PB4c | All-Inkl read-only / Abschaltplan | 🔴 | [ ] |

### PB5 — Nachzug

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| PB5a | IQ-STROM §B + SERVER-MIGRATION Haken setzen | 🟢 | [ ] |

---

## Empfohlene Reihenfolge

1. Warten auf Lieferung **GEX45-1** (B20261107-3544202, HEL1)  
2. **PB1a–d** → **PB2a–d** → **PB3a–c**  
3. Freigabe → **PB4** · danach **PB5** · dann Phase C (ERiC)  
4. Parallel/nach PB2: Shared Core [`SHARED-CORE-REST-PLAN.md`](SHARED-CORE-REST-PLAN.md) (SC3 Pilot Dev)

**Hinweis Ops:** Hetzner-Support für Finnland HEL1 nur **Englisch**.

**Nächste Freigabe:** **PB2d** (IQ-STROM Doc-Haken) oder Shared-Core **SC2** · kein Cutover (**PB4**).

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`SERVER-MIGRATION.md`](SERVER-MIGRATION.md) | Hardware GEX45-1 · Bestellung B20261107-3544202 |
| [`DEPLOY-PIPELINE-PREP.md`](DEPLOY-PIPELINE-PREP.md) | rsync/SSH Prep |
| [`HETZNER-AVV-TOM-CHECKLISTE.md`](HETZNER-AVV-TOM-CHECKLISTE.md) | AVV ✅ |
| [`ELSTER-ERIC-TODO.md`](ELSTER-ERIC-TODO.md) | nach Cutover |
| [`SHARED-CORE-REST-PLAN.md`](SHARED-CORE-REST-PLAN.md) | nach PB2: ein Code-Baum `/var/www/dg-crm/shared` (SC3+) |
