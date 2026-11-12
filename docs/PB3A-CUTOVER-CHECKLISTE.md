# PB3a — Cutover-Checkliste (Docs)

> Stand: **2026-11-12** · Mini-ID **PB3a** · Plan: [`PHASE-B-REST-PLAN.md`](PHASE-B-REST-PLAN.md) · Domains: [`SERVER-DOMAIN-DATENBANK.md`](SERVER-DOMAIN-DATENBANK.md)  
> **Kein Cutover hier.** Ausführung = **PB4** (eigene Freigabe). Support HEL1 nur Englisch.

---

## 0. Abgrenzung

| Schon auf GEX (kein Cutover) | Cutover später (PB4) |
|------------------------------|----------------------|
| Probe CRM HTTPS `crm-gex.ganz-soft.de` | Produktiv-DNS der CRM-Hosts (All-Inkl → `2.29.242.28`) |
| Ollama-Proxy `llm.ganz-soft.de` (SKC1) | Shop/Cloud/Kasse nur wenn explizit im Fenster |
| Shared Code-Probe `/var/www/dg-crm/shared` | Volle Instanzdaten + `storage/` + Locals |

---

## 1. Domains (Reihenfolge vorschlagen)

Staffelung = weniger Blast-Radius. **Ein** Host pro Freigabe-Fenster, wenn unsicher.

| # | Host | Rolle | Account | Soll nach Cutover |
|---|------|-------|---------|-------------------|
| 1 | `ganz-om.de` (+ `www` falls genutzt) | Dev-CRM | w0217246 | A → GEX · LE auf GEX · `home_url` https |
| 2 | `dg.ganz-soft.de` | Code-Kern | w0217246 | A → GEX · Sync-Quelle |
| 3 | `dg.ganz-om.de` | Firma Ganz OM | w0217246 | A → GEX · eigene DB |
| 4 | `ganz-soft.de` | CRM (wie Kunde) | w0217246 | A → GEX · **nicht** mit Apex-Mail/SPF verwechseln |
| 5 | `kontur-cosmetics.de` | Kunde | w0217246 | A → GEX |
| 6 | `office.iqstrom.com` (+ `www`) | Kunde IQ | w01f1176 | A → GEX · eigene Freigabe |

**Außerhalb CRM-Cutover-Fenster (eigene Entscheidung):** `shop.ganz-soft.de`, `cloud.ganz-om.de`, `kasse.*`, `license-server.*`, Nextcloud.

**Bereits GEX (stehen lassen):** `llm.ganz-soft.de`, `crm-gex.ganz-soft.de` — nicht löschen; Apex/`*`-CNAME All-Inkl nur für nicht umgezogene Namen.

Vor jedem Host: Liste A/AAAA/CNAME/MX/TXT notieren (Screenshot KAS). MX/TXT/SPF/DKIM oft **auf All-Inkl lassen**, nur **A (und ggf. AAAA)** der Web-Hosts auf GEX drehen — sonst Mailbruch.

---

## 2. TTL

| Schritt | Wann | Aktion |
|---------|------|--------|
| TTL senken | **≥ 24–48 h vor** Cutover | A-Records der Cutover-Hosts auf **300 s** (oder KAS-Minimum) |
| Cutover | Fenster | A → `2.29.242.28` (ggf. AAAA → GEX-IPv6 `2a01:4f9:3120:23ae::2` nur wenn Stack IPv6 bedient) |
| Prüfen | sofort + 1 h | `dig` Auth-NS + 8.8.8.8 / 1.1.1.1 · HTTPS `/login` 200 |
| TTL wieder hoch | nach **48 h** stabil | z. B. 3600–86400 |

Wildcard-`*` CNAME auf All-Inkl: umgezogene Subdomains brauchen **expliziten A** (wie `llm` / `crm-gex`), sonst Catch-all.

---

## 3. Dump- / Sync-Reihenfolge (pro Instanz, PB4a)

Pro Host **eigene** DB + `storage/` + `config/*.local.php` — nie fremde Instanz-DB.

| Schritt | Inhalt | Hinweis |
|---------|--------|---------|
| A | Wartung / Schreibstopp (kurz) | Nutzer raus; Cron pausieren |
| B | DB-Dump All-Inkl | wie [`BACKUP-RESTORE-TEST.md`](BACKUP-RESTORE-TEST.md); Größe/Zeit notieren |
| C | `storage/` rsync/tar → GEX NVMe | ohne `storage/backups`/`cache` wenn Pipeline so vorsieht |
| D | Locals auf GEX | `database.local.php`, `app.local.php` (`home_url` https+neuer Host), Mail/ELSTER nur Instanz |
| E | Import DB auf GEX | leere Ziel-DB; Collation/User prüfen |
| F | nginx vHost + LE | `server_name` = Produktiv-Host; **nicht** nur `crm-gex` lassen |
| G | Smoke | `/login` · eine Beleg-PDF · Nummernkreis-Stichprobe |
| H | DNS A drehen | erst wenn G–Smoke grün |
| I | 48 h Monitoring | PB4b |

**Reihenfolge der Instanzen:** Dev (`ganz-om.de`) zuerst üben → Kern → Firmen/Kunden. IQ (`office.iqstrom.com`) = **eigene** Freigabe (echte Daten).

Code: weiter Deploy/Sync vom Kern — Cutover kopiert **Daten**, nicht den Git-Arbeitsbaum als Wahrheit.

---

## 4. Rollback (wenn etwas hapert)

| Situation | Aktion |
|-----------|--------|
| DNS schon gedreht, GEX kaputt | A-Record **zurück** auf All-Inkl-IP (`85.13.168.110` bzw. IQ-Host-IP); TTL war kurz → schnell wirksam |
| Dump/Import fehlerhaft | GEX-Instanz nicht als produktiv nutzen; All-Inkl bleibt Quelle bis neuer Dump |
| Nur TLS/nginx falsch | DNS lassen wenn A schon GEX und HTTP-App ok; Cert/vHost reparieren |
| Mail betroffen | MX/TXT prüfen — Web-A allein zurückdrehen reicht oft |

All-Inkl **nicht** löschen/read-only setzen, bevor PB4c und 48 h grün (**PB4c** erst nach stabil).

Rollback-Notiz festhalten: Host, Uhrzeit UTC/Berlin, wer, alter/neuer A, Symptom.

---

## 5. Kurz-Check vor PB4-Freigabe

- [ ] SC2/Locals-Plan klar (Instanz vs. shared) — sonst Login/DB-Überraschungen
- [ ] TTL der ersten Cutover-Hosts gesenkt
- [ ] Dump-Übung an Dev/`crm-gex` verstanden (Größe, Dauer)
- [ ] LE/Certbot-Weg pro Host klar (HTTP-01 nur wenn A bereits GEX — Huhn-Ei: kurz HTTP oder DNS-01)
- [x] PB3b Backup-Ziel · PB3c SMTP-Plan gelesen ([`PB3B-BACKUP-OFFSITE.md`](PB3B-BACKUP-OFFSITE.md) · [`PB3C-SMTP-MAIL-PLAN.md`](PB3C-SMTP-MAIL-PLAN.md))
- [ ] Explizite Freigabe-Nachricht für **PB4a** (welcher Host zuerst)

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`PHASE-B-REST-PLAN.md`](PHASE-B-REST-PLAN.md) | Mini-IDs PB3/PB4 |
| [`SERVER-MIGRATION.md`](SERVER-MIGRATION.md) | Hardware GEX45 |
| [`BACKUP-RESTORE-TEST.md`](BACKUP-RESTORE-TEST.md) | Dump/Restore-Übung |
| [`SHARED-CORE-REST-PLAN.md`](SHARED-CORE-REST-PLAN.md) | shared + Instanz-Hüllen |
