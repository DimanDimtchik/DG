# PB3c — SMTP / Mail nach Cutover (kurz)

> Stand: **2026-11-12** · Mini-ID **PB3c** · Plan: [`PHASE-B-REST-PLAN.md`](PHASE-B-REST-PLAN.md) · Cutover-DNS: [`PB3A-CUTOVER-CHECKLISTE.md`](PB3A-CUTOVER-CHECKLISTE.md)  
> **Nur Plan.** Kein Mailserver auf GEX einrichten, keine MX drehen in dieser ID. Live-Test = mit **PB4b**.

---

## Entscheidung

| Punkt | Soll nach Web-Cutover |
|-------|------------------------|
| **MX / Postfächer** | Bleiben vorerst bei **All-Inkl** (KAS) — Web-A auf GEX ≠ Mail umziehen |
| **CRM-Versand** | Weiter **SMTP der jeweiligen Mailbox** (All-Inkl oder Anbieter) aus `mail.local.php` / Postfach-Settings — siehe [`KOMMUNIKATION-POSTFAECHER-TODO.md`](KOMMUNIKATION-POSTFAECHER-TODO.md) |
| **Kein** | Eigenes Postfix-MTA auf GEX45 als Default · Roundcube o. Ä. · MX sofort mit auf GEX |
| **IMAP Sync** | CRM holt weiter per IMAP vom bestehenden Host; Firewall GEX: Outbound 993/465/587 ok |

Web und Mail dürfen getrennt sein: A-Record CRM → `2.29.242.28`, MX → `w….kasserver.com` (oder bestehender Mailhost).

---

## DNS (Mail vs. Web)

| Record | Bei CRM-Cutover |
|--------|------------------|
| **A/AAAA** Web-Host | → GEX (PB4) |
| **MX** | **unverändert** lassen, bis eigener Mail-Umzug beschlossen |
| **TXT SPF** | prüfen: `a`/`mx`/`include:spf.kasserver.com` weiter gültig, solange SMTP über All-Inkl geht |
| **DKIM / DMARC** | unverändert; nach SMTP-Wechsel neu setzen |
| **Wildcard `*`** | nur Web-Subdomains mit explizitem A; Mail-Subdomains nicht kaputt machen |

Wenn SPF `a` das Web-A meint und Versand weiter All-Inkl-SMTP nutzt: meist unkritisch (Auth über SMTP-User). Nach Umstellung auf fremdes Relay SPF **explizit** anpassen.

---

## CRM-Konfig (pro Instanz)

| Datei / Ort | Nach Cutover |
|-------------|--------------|
| `config/mail.local.php` | SMTP-Host weiter All-Inkl (oder gleicher Provider); Passwort/Secret nur Instanz |
| Einstellungen → E-Mail / Post | vorhandene Mailboxen testen (eine Testmail) |
| `home_url` | https auf neuem Host — Absender-Links in Mails |
| From-Adresse | Domain der Mailbox; nicht „von GEX IP senden“ ohne Auth |

Ports: SMTP **587 STARTTLS** oder **465** wie bisher im Example (`config/mail.local.php.example`).

---

## Reihenfolge mit PB4

| Phase | Mail-Aktion |
|-------|-------------|
| Vor DNS | SMTP-Zugänge notieren; eine Testmail von All-Inkl-CRM ok |
| PB4a Daten | `mail.local.php` + Postfach-Secrets mit Locals mitnehmen |
| PB4b nach A→GEX | Sofort: Login CRM · **eine** SMTP-Testmail · IMAP-Sync stichproben |
| Später (eigene Freigabe) | optional Mail ganz auf GEX/anderen Provider — nicht Blocker für Web-Cutover |

Rollback Web-A (PB3a): Mail oft unberührt, weil MX nie mitgedreht wurde.

---

## Bewusst später / nicht PB3c

- Mailserver oder Relay **auf** GEX betreiben  
- MX-Cutover All-Inkl → Hetzner  
- Spamhaus/IP-Reputation der GEX-IP für unauthentifiziertes Senden  
- Shop-/Kassen-Mail Sonderfälle (eigene Pläne)

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`KOMMUNIKATION-POSTFAECHER-TODO.md`](KOMMUNIKATION-POSTFAECHER-TODO.md) | IMAP/SMTP im CRM |
| [`PB3A-CUTOVER-CHECKLISTE.md`](PB3A-CUTOVER-CHECKLISTE.md) | MX beim Web-Cutover stehen lassen |
| [`config/mail.local.php.example`](../config/mail.local.php.example) | SMTP-Felder |
