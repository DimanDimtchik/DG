# DG CRM — ToDos & aktueller Stand

> **Regeln (nicht hier):** [`AGENTS.md`](../AGENTS.md) · SSH: [`CLOUD-AGENT-ACCESS.md`](CLOUD-AGENT-ACCESS.md)

Stand: **2026-08-28** — bei jeder Session zuerst aktualisieren, wenn sich Branch/Deploy/Tests ändern.

---

## Git & Branch

| Branch | Status |
|--------|--------|
| `master` | Produktionsbasis (Deploy-Quelle auf Server) |
| `cursor/buchhaltung-randfaelle-1c3a` | **Aktive Arbeit** — Belegkette, Mahnwesen, Zeiterfassung Ph.1, Wartungsmodus-Vereinheitlichung, ganz-om Platzhalter |

Nach Tests: Merge → `master` → Master deployen → `sync-crm-from-master.sh`

---

## Prioritäten (jetzt)

- [ ] Branch `cursor/buchhaltung-randfaelle-1c3a` auf Master deployen + testen (ganz-soft.de)
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
| dg.ganz-om.de | AN | Master, Login ok |
| ganz-soft.de | AN | Haupt-Testinstanz |
| kontur-cosmetics.de | AN | Adresse ggf. prüfen |
| ganz-om.de | Platzhalter | Sync vom Master, keine DB |
| shop.ganz-soft.de | — | Stripe nicht live |

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
