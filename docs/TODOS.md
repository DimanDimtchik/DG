# DG CRM — ToDos & aktueller Stand

> **Regeln (nicht hier):** [`AGENTS.md`](../AGENTS.md) · SSH: [`CLOUD-AGENT-ACCESS.md`](CLOUD-AGENT-ACCESS.md)

Stand: **2026-08-28** — bei jeder Session zuerst aktualisieren, wenn sich Branch/Deploy/Tests ändern.

---

## Git & Branch

| Branch | Status |
|--------|--------|
| `master` | **Aktuell** — Buchhaltung Randfälle, Zeiterfassung Ph.1, Wartungsmodus, AGENTS/TODOS |

Nach Tests: Master deployen → `sync-crm-from-master.sh`

---

## Prioritäten (jetzt)

- [ ] Master auf Server deployen + testen (ganz-soft.de)
- [ ] Migrationen 062–063 (Zeiterfassung/ArbZG) prüfen
- [ ] Firmen-E-Mail in ganz-soft.de CRM eintragen (Einstellungen → Firma) — dann erscheint Wartungs-Kontakt automatisch
- [ ] Manuelle Tests Randfälle-Branch (siehe unten)
- [ ] Merge → `master` wenn Testliste grün

---

## Offene Arbeit (Branch-Inhalt)

| Block | Migration / Ort |
|-------|-----------------|
| Belegkette | 055 |
| Dokument-Workflow / Statusfilter | 056 |
| Freitext vor/nach Positionen | 057 |
| Gesetzliche Klauseln | 058 |
| Skonto-Stufen, Mahnwesen + Autostart | 059 |
| Teilzahlungen | 060 |
| Zeiterfassung Ph.1 (Stempeluhr) | 061 |
| Überstunden / ArbZG-Erinnerung | 062–063 |
| Wartungsmodus einheitlicher Code | `WebsiteMaintenanceRenderer`, Sync inkl. ganz-om.de |

Doku: `BUCHHALTUNG-BELEGKETTE.md`, `CRON-AUTOSTART.md`, `ZEITERFASSUNG-PLAN.md`

---

## Live-Instanzen (Kurz)

| Instanz | Wartung | Bemerkung |
|---------|---------|-----------|
| dg.ganz-om.de | AN | Master — **Lizenzfehler 30.08.** (SSH reparieren) |
| ganz-soft.de | AN | Öffentlich 503 Aufbau, **/login OK** |
| kontur-cosmetics.de | AN | **Lizenzfehler 30.08.** |
| ganz-om.de | Platzhalter | Öffentlich 503 Aufbau, **/login OK** |
| shop.ganz-soft.de | — | Stripe nicht live |

**Incident 2026-08-30:** Domains existieren — ganz-soft/ganz-om zeigen absichtlich Wartungsseite. kontur + Master blockiert durch fehlende/ungültige Lizenz; Lizenzserver `dg-user.ganz-soft.de/check` liefert HTTP 500. Diagnose/Reparatur: `bash bin/repair-crm-instances.sh` (auf Server per SSH). Cloud-Restore betraf nur `cloud.ganz-om.de`, nicht automatisch alle CRM-Configs.

---

## Test-Checkliste (Randfälle-Branch)

Instanz: **ganz-soft.de** (Wartung bleibt an bis Freigabe)

- [ ] Belegkette: Angebot → AB → Lieferschein → Rechnung
- [ ] Workflow-Status Entwurf → … → Abgerechnet
- [ ] Gesetzliche Klauseln in PDF
- [ ] Skonto-Stufen + Mahnlauf
- [ ] Teilzahlungen
- [ ] Zeiterfassung: Stempeln, Team, Autoclose
- [ ] Wartungsmodus: gleiches Layout auf ganz-soft.de und ganz-om.de; Kontakt aus CRM

Basis: [`TESTLISTE-2026-08-21.md`](TESTLISTE-2026-08-21.md)

---

## Bewusst zurückgestellt

| Thema | Doku |
|-------|------|
| Stripe Live | `SHOP-TODO.md` |
| ELSTER/ERiC live | `ELSTER-ERIC-TODO.md` |
| PHP 8.5 KAS-Umstellung | `PHP85-TEST-HANDOFF.md` |
| Nextcloud cloud.ganz-om.de | `CLOUD-NEXTCLOUD-RESTORE.md` |
| ganz-om.de vollständiges CRM | `CLOUD-NEXTCLOUD-RESTORE.md` |
| Zeiterfassung Ph.2+ | `ZEITERFASSUNG-PLAN.md` |

---

## Chats aufräumen

Nur **1 Cloud-Chat** + **1 Lokal-Chat** behalten. Agent kann nicht archivieren — du: Rechtsklick → Archive in Cursor.

---

## Detail-Handoffs (Referenz)

| Datei | Inhalt |
|-------|--------|
| `PC-HANDOFF.md` | PC-Deploy, Import |
| `HANDY-HANDOFF.md` | Kurz Mobile |
| `BUCHHALTUNG-PC-HANDOFF.md` | Buchhaltung A–C |
| `CLOUD-AGENT-ACCESS.md` | Secrets, SSH |
