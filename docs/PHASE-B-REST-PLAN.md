# Phase B — Hetzner-Umzug (tokenarm)

> **Eine Chat-Session = eine Mini-ID.**  
> Fach: [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) §B · Hardware: [`SERVER-MIGRATION.md`](SERVER-MIGRATION.md)

Stand: **2026-11-08** · Zielserver: **GEX45** (bestellt) · OS: **Ubuntu 26** · Lieferung: **einige Wochen**

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
| PB1 | Server da / Basis-OS | [ ] wartet auf Lieferung | 🔴 |
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

### PB1 — Lieferung & Basis (erst wenn Server live)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| PB1a | Robot: IPv4/IPv6, Standort HEL1 bestätigen · Notiz in `SERVER-MIGRATION.md` (ohne Secrets) | 🔴 | [ ] |
| PB1b | Ubuntu 26 installiert · `timedatectl` Berlin · Updates | 🔴 | [ ] |
| PB1c | Firewall 22/80/443 · SSH-Key User `deploy` · Passwort-Login aus | 🔴 | [ ] |
| PB1d | Statusnotiz „Basis grün“ in IQ-STROM §B | 🟢 | [ ] |

**Prompt:** `Nur Mini-ID PB1a laut docs/PHASE-B-REST-PLAN.md. Nur Docs-Notiz IP/Standort. Kein Cutover.`

### PB2 — Stack & Probe-Deploy

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| PB2a | nginx + PHP 8.2+ FPM + MariaDB 10.11+ + certbot (Test-Domain oder IP) | 🔴 | [ ] |
| PB2b | Deploy-Pipeline Probe laut [`DEPLOY-PIPELINE-PREP.md`](DEPLOY-PIPELINE-PREP.md) (ohne Produktiv-DNS) | 🔴 | [ ] |
| PB2c | Leere/Test-Instanz erreichbar HTTPS · Smoke `/login` | 🔴 | [ ] |
| PB2d | Doc-Haken PB2 in IQ-STROM | 🟢 | [ ] |

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

1. Warten auf Lieferung GEX45  
2. **PB1a–d** → **PB2a–d** → **PB3a–c**  
3. Freigabe → **PB4** · danach **PB5** · dann Phase C (ERiC)

**Nächster Chat (erst nach Lieferung):**  
`Nur Mini-ID PB1a laut docs/PHASE-B-REST-PLAN.md. IPv4/Standort in SERVER-MIGRATION.md. Kein Cutover.`

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`SERVER-MIGRATION.md`](SERVER-MIGRATION.md) | Hardware GEX45 |
| [`DEPLOY-PIPELINE-PREP.md`](DEPLOY-PIPELINE-PREP.md) | rsync/SSH Prep |
| [`HETZNER-AVV-TOM-CHECKLISTE.md`](HETZNER-AVV-TOM-CHECKLISTE.md) | AVV ✅ |
| [`ELSTER-ERIC-TODO.md`](ELSTER-ERIC-TODO.md) | nach Cutover |
