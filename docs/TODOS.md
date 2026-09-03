# DG CRM — ToDos & aktueller Stand

> **Regeln (nicht hier):** [`AGENTS.md`](../AGENTS.md) · SSH: [`CLOUD-AGENT-ACCESS.md`](CLOUD-AGENT-ACCESS.md)

Stand: **2026-09-03** — bei jeder Session zuerst aktualisieren, wenn sich Branch/Deploy/Tests ändern.

---

## Git & Branch

| Branch | Status |
|--------|--------|
| `master` | **Einzige Produktionslinie** @ `1.0.29` — Buchhaltung Randfälle, Zeiterfassung Ph.1, Website-Menü-Icons, Deploy-Skripte |

**Hygiene:** Feature-Branches (`cursor/…`) nach Merge in `master` lokal + remote löschen.

**Offene Remote-Branches** (noch nicht in `master` — behalten bis Merge):

| Branch | Inhalt |
|--------|--------|
| `cursor/bank-geisterumsaetze-e031` | Bankabgleich Geisterumsätze |
| `cursor/arbeitsvertrag-vorlagen-hinweis-caef` | Doku Arbeitsvertrag-Vorlagen |

**Aufgeräumt 2026-09-03:** u. a. `buchhaltung-randfaelle`, `buchhaltung-phase-abc`, `install-data-import`, `website-bootstrap`, `datev-doppelbuchung`, `server-elster-prep`, `fix-ssh-key-format`, drei SSH-Deploy-Branches (Fixes in `master` + `deploy-via-rsync.sh`).

Deploy: `bash bin/deploy-via-rsync.sh` (Cloud) oder `deploy.bat` (PC) → `bash bin/sync-crm-from-master.sh` (auf Server).

---

## Prioritäten (jetzt)

- [x] Master auf Server deployen + Instanzen syncen (2026-09-03)
- [x] Lizenzserver `dg-user.ganz-soft.de` repariert; kontur/Master `/login` OK
- [ ] Migrationen 062–063 (Zeiterfassung/ArbZG) prüfen
- [ ] Firmen-E-Mail in ganz-soft.de CRM eintragen (Einstellungen → Firma) — Wartungs-Kontakt
- [ ] Manuelle Testliste Randfälle auf **ganz-soft.de** (siehe unten)

---

## Inhalt auf `master` (Deploy-Quelle)

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
| Website-Menü Icons (Lucide, Untermenü-Styles) | `src/Website/` |

Doku: `BUCHHALTUNG-BELEGKETTE.md`, `CRON-AUTOSTART.md`, `ZEITERFASSUNG-PLAN.md`, `HANDOFF-WEBSITE-MENU-ICONS.md`

---

## Live-Instanzen (Kurz)

| Instanz | Wartung | Bemerkung |
|---------|---------|-----------|
| dg.ganz-om.de | AN | Master, `/login` OK |
| ganz-soft.de | AN | Haupt-Testinstanz, `/login` OK |
| kontur-cosmetics.de | AN | `/login` OK (Lizenz 03.09. repariert) |
| ganz-om.de | Platzhalter | Sync vom Master, keine DB |
| shop.ganz-soft.de | — | Stripe nicht live |

**Erledigt 2026-09-03:** CRM v1.0.29 deployt; Lizenzserver wiederhergestellt (`license-server/` + `config.php`); Grace-State zurückgesetzt.

---

## Test-Checkliste (Randfälle — `master` auf ganz-soft.de)

Instanz: **ganz-soft.de** (Wartung bleibt an bis Freigabe)

- [ ] Belegkette: Angebot → AB → Lieferschein → Rechnung
- [ ] Workflow-Status Entwurf → … → Abgerechnet
- [ ] Gesetzliche Klauseln in PDF
- [ ] Skonto-Stufen + Mahnlauf
- [ ] Teilzahlungen
- [ ] Zeiterfassung: Stempeln, Team, Autoclose
- [ ] Wartungsmodus: gleiches Layout auf ganz-soft.de und ganz-om.de; Kontakt aus CRM

Basis: [`TESTLISTE-2026-08-21.md`](TESTLISTE-2026-08-21.md) Abschnitt K

---

## Bewusst zurückgestellt

| Thema | Doku |
|-------|------|
| Stripe Live | `SHOP-TODO.md` |
| ELSTER/ERiC live | `ELSTER-ERIC-TODO.md` (Entwicklerregistrierung läuft) |
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
| `CLOUD-AGENT-ACCESS.md` | Secrets, SSH, rsync-Deploy |
