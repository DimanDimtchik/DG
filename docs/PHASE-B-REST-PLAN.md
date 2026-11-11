# Phase B — Hetzner-Umzug (tokenarm)

> **Eine Chat-Session = eine Mini-ID.**  
> Fach: [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) §B · Hardware: [`SERVER-MIGRATION.md`](SERVER-MIGRATION.md)

Stand: **2026-11-11** · Zielserver: **GEX45-1** (Bestellnr. **B20261107-3544202**) · Standort: **Finnland, HEL1** · OS: **Ubuntu 26.04 LTS** · IPv4 **`[REDACTED]`** · IPv6 **`2a01:4f9:3120:23ae::2`** · **PB1 ✅** · **PB3a–c ✅**

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
| PB3 | Cutover-Vorbereitung | [x] PB3a–c ✅ 2026-11-11 | 🟢 |
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
| PB1a | Robot: freigeschaltete **Primäre IPv4** (+ ggf. IPv6) notieren · Standort HEL1 bereits per Bestellung bestätigt · Notiz in `SERVER-MIGRATION.md` (ohne Secrets) | 🔴 | [x] 2026-11-11 · `[REDACTED]` / `2a01:4f9:3120:23ae::2` |
| PB1b | Ubuntu **26.04 LTS** installiert · `timedatectl` Berlin · Updates | 🔴 | [x] 2026-11-11 |
| PB1c | Firewall 22/80/443 · SSH-Key User `deploy` · Passwort-Login aus | 🔴 | [x] 2026-11-11 |
| PB1d | Statusnotiz „Basis grün“ in IQ-STROM §B | 🟢 | [x] 2026-11-11 |

**Prompt:** `Nur Mini-ID PB1a laut docs/PHASE-B-REST-PLAN.md. Nur Docs-Notiz IP/Standort. Kein Cutover.`

### PB2 — Stack & Probe-Deploy

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| PB2a | nginx + PHP 8.2+ FPM + MariaDB 10.11+ + certbot (Test-Domain oder IP) | 🔴 | [~] 2026-11-11 Pakete+Ordner+sudo ✅ (PHP 8.5.4 · nginx · MariaDB · certbot Paket) · vHost/TLS/Probe offen |
| PB2b | Deploy-Pipeline Probe laut [`DEPLOY-PIPELINE-PREP.md`](DEPLOY-PIPELINE-PREP.md) (ohne Produktiv-DNS) | 🔴 | [x] 2026-11-11 tar→`/var/www/dg-crm/shared` (~3697 Dateien; kein rsync lokal; ohne DNS) |
| PB2c | Leere/Test-Instanz erreichbar HTTPS · Smoke `/login` | 🔴 | [~] PB2c1 ✅ 2026-11-11 Hülle `instances/ganz-om.de`+Stub · PB2c2 vHost · PB2c3 DB/login · PB2c4 TLS |
| PB2d | Doc-Haken PB2 in IQ-STROM | 🟢 | [ ] |

### PB3 — Cutover-Vorbereitung (Docs + Termine)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| PB3a | Cutover-Checkliste: Domains, TTL, Dump-Reihenfolge, Rollback | 🟢 | [x] 2026-11-11 · § unten |
| PB3b | Backup/off-site Ziel auf GEX45 skizzieren (kein HDD wie SX65) | 🟢 | [x] 2026-11-11 · § unten |
| PB3c | SMTP/Mail-Plan nach Cutover (kurz) | 🟢 | [x] 2026-11-11 · § unten |

#### PB3a — Cutover-Checkliste (nur Docs, kein Cutover)

**Voraussetzungen:** PB2 Stack grün (vHost/TLS/`/login`) · Shared-Core Pilot ideal (SC3) · Freigabe für **PB4** ausdrücklich.

| # | Thema | Soll vor PB4 |
|---|--------|----------------|
| 1 | **Domain-Liste** | Pro Cutover-Welle festlegen welche FQDNs umziehen (CRM: z. B. `ganz-om.de` zuerst Dev; dann `dg.ganz-soft.de` Kern nur Code bzw. Shared; Firmen/Kunden einzeln). Quelle: [`SERVER-DOMAIN-DATENBANK.md`](SERVER-DOMAIN-DATENBANK.md). Shop/Nextcloud/Kasse **nicht** stillschweigend mitziehen. |
| 2 | **TTL** | DNS-TTL der betroffenen Records **mind. 24–48 h vorher** auf niedrig (z. B. 300–600 s) setzen · nach stabilem Cutover wieder anheben |
| 3 | **Dump-Reihenfolge** | Pro Instanz: (a) App kurz wartungs-/schreibarm halten · (b) **DB-Dump** · (c) `storage/` / `crm-storage/<fqdn>` rsync · (d) Locals `*.local.php` · (e) Smoke auf GEX mit **Hosts-Datei oder Staging-Name** vor öffentlichem DNS |
| 4 | **Shared vs. Instanz** | Code nur nach `/var/www/dg-crm/shared/` · Daten nur Instanz/DB/Storage ([`SHARED-CORE-REST-PLAN.md`](SHARED-CORE-REST-PLAN.md)) |
| 5 | **Rollback** | All-Inkl DNS/Origin **nicht** löschen bis 48 h grün · bei Fehler: DNS zurück auf All-Inkl · GEX-Instanz read-only lassen · letzter Dump+Storage vom Cutover-Fenster aufbewahren |
| 6 | **Monitoring 48 h** | `/login`, eine Beleg-/Kontaktseite, Mail-Abruf wenn IMAP, Cron/Autostart, Platte `df`, Fehlerlogs nginx/PHP |
| 7 | **IQ separat** | `office.iqstrom.com` (Account `w01f1176`) eigene Welle / eigene Freigabe — nicht im gleichen Schritt wie `w0217246` erzwingen |

**Abnahme PB3a:** Checkliste im Plan · **kein** DNS- und **kein** Dump-Lauf.

#### PB3b — Backup / off-site auf GEX45 (Skizze)

Kein SX65-HDD für den ersten Cutover. Zielbild:

| Schicht | Lokal GEX | Off-site |
|---------|-----------|----------|
| DB | Dump/`mariadb-backup` → `/mnt/data/backup-staging` | **restic** (oder gleichwertig) → **Hetzner Storage Box** / Backup-Space |
| `crm-storage/<fqdn>` | NVMe B | restic/rsync inkrementell dieselbe Box |
| Locals | mit Instanz-Backup | in Off-site-Snapshot enthalten |
| Shared-Code | Git + Deploy | optional selten; nicht Tagespflicht |
| Zertifikate ELSTER | getrennt verschlüsselt | nie ungeschützt in Git |

Details Matrix: [`SHARED-CORE-REST-PLAN.md`](SHARED-CORE-REST-PLAN.md) SC6 / [`GEX45-DISK-LAYOUT.md`](GEX45-DISK-LAYOUT.md).  
**Vor PB4:** mind. eine Restore-Probe (DB + eine `storage/`) dokumentieren ([`BACKUP-RESTORE-TEST.md`](BACKUP-RESTORE-TEST.md) / PA3b-Muster).

**Abnahme PB3b:** Off-site = Box/restic skizziert · SX65 explizit **nicht** nötig für Cutover · **kein** Box-Vertragspflicht-Nachweis in dieser ID.

#### PB3c — SMTP / Mail nach Cutover (kurz)

| Thema | Plan |
|-------|------|
| **SMTP-Versand** | Weiter **Hoster-SMTP der jeweiligen Mailbox** (kein eigenes Relay auf GEX als Muss) — CRM `MailService` / Postfächer wie bisher ([`KOMMUNIKATION-POSTFAECHER-TODO.md`](KOMMUNIKATION-POSTFAECHER-TODO.md)) |
| **IMAP** | Unverändert gegen Mail-Provider; nach DNS prüfen Auth/Firewall GEX → Provider |
| **DNS Mail** | MX/SPF/DKIM der **Maildomain** nur ändern wenn Mail wirklich mitzieht — CRM-A-Record-Cutover ≠ MX-Cutover |
| **Locals** | `mail.local.php` / Postfach-Secrets **pro Instanz** neu prüfen · nicht von fremder Instanz kopieren |
| **Smoke nach Cutover** | Eine Testmail senden + IMAP-Sync einer Shared/Privat-Box |

**Abnahme PB3c:** Kurzplan im Doc · **kein** Server-SMTP installieren.

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

1. Lieferung **GEX45-1** · **PB1 ✅** · **PB2** Stack zu Ende (PB2c2–c4)  
2. **PB3a–c ✅** (Cutover-Prep Docs)  
3. Freigabe → **PB4** · danach **PB5** · dann Phase C (ERiC)  
4. Parallel: Shared Core [`SHARED-CORE-REST-PLAN.md`](SHARED-CORE-REST-PLAN.md) (SC3b+)

**Hinweis Ops:** Hetzner-Support für Finnland HEL1 nur **Englisch**.

**Nächster Chat (Server):**  
`Nur Mini-ID PB2c2 laut docs/PHASE-B-REST-PLAN.md.`  
**Cutover erst mit:** `Nur Mini-ID PB4a … Freigabe erteilt.`

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`SERVER-MIGRATION.md`](SERVER-MIGRATION.md) | Hardware GEX45-1 · Bestellung B20261107-3544202 |
| [`DEPLOY-PIPELINE-PREP.md`](DEPLOY-PIPELINE-PREP.md) | rsync/SSH Prep |
| [`HETZNER-AVV-TOM-CHECKLISTE.md`](HETZNER-AVV-TOM-CHECKLISTE.md) | AVV ✅ |
| [`ELSTER-ERIC-TODO.md`](ELSTER-ERIC-TODO.md) | nach Cutover |
| [`SHARED-CORE-REST-PLAN.md`](SHARED-CORE-REST-PLAN.md) | nach PB2: ein Code-Baum `/var/www/dg-crm/shared` (SC3+) |
