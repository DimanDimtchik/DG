# DG CRM — ToDos & aktueller Stand

> **Regeln (nicht hier):** [`AGENTS.md`](../AGENTS.md) · SSH: [`CLOUD-AGENT-ACCESS.md`](CLOUD-AGENT-ACCESS.md)

Stand: **2026-09-03** — bei jeder Session zuerst aktualisieren, wenn sich Branch/Deploy/Tests ändern.

---

## Git & Branch

| Branch | Status |
|--------|--------|
| `master` | **Einzige Produktionslinie** — Buchhaltung, Zeiterfassung, Bank-Geisterumsätze (064), Website-Menü-Icons |

**Hygiene:** Feature-Branches (`cursor/…`) nach Merge in `master` lokal + remote löschen. **Keine offenen `cursor/`-Branches mehr.**

Deploy: `bash bin/deploy-via-rsync.sh` (Cloud) oder `deploy.bat` (PC) → `bash bin/sync-crm-from-master.sh` (auf Server).

---

## Prioritäten (jetzt)

- [x] Master auf Server deployen + Instanzen syncen (2026-09-03)
- [x] Lizenzserver repariert; kontur/Master `/login` OK
- [x] Feature-Branches gemergt: Bank-Geisterumsätze, Arbeitsvertrag-Doku
- [ ] Migration **064** (Bank-Fingerabdruck) auf Live prüfen nach Login
- [ ] Migrationen 062–063 (Zeiterfassung/ArbZG) prüfen
- [ ] Firmen-E-Mail in ganz-soft.de CRM eintragen (Einstellungen → Firma)
- [ ] Manuelle Testliste Randfälle auf **ganz-soft.de**

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
| **Bank Geisterumsätze** | **064** · `BankGhostDetectionService` |
| Wartungsmodus einheitlicher Code | `WebsiteMaintenanceRenderer` |
| Website-Menü Icons (Lucide) | `src/Website/` |

Doku: `BUCHHALTUNG-BELEGKETTE.md`, `ARBEITSVERTRAG-VORLAGEN-HINWEIS.md`, `ZEITERFASSUNG-PLAN.md`

---

## Live-Instanzen (Kurz)

| Instanz | Wartung | Bemerkung |
|---------|---------|-----------|
| dg.ganz-om.de | AN | Master, `/login` OK |
| ganz-soft.de | AN | Haupt-Testinstanz |
| kontur-cosmetics.de | AN | `/login` OK |
| ganz-om.de | Platzhalter | Sync vom Master, keine DB |
| shop.ganz-soft.de | — | Stripe nicht live |

---

## Test-Checkliste (Randfälle — `master` auf ganz-soft.de)

- [ ] Belegkette, Workflow, Klauseln, Skonto, Teilzahlungen, Zeiterfassung
- [ ] **Bankabgleich:** Geisterumsätze erkennen, manuell ausblenden (Migration 064)
- [ ] Wartungsmodus: Layout + Kontakt aus CRM

Basis: [`TESTLISTE-2026-08-21.md`](TESTLISTE-2026-08-21.md) Abschnitt K

---

## Bewusst zurückgestellt

| Thema | Doku |
|-------|------|
| Stripe Live | `SHOP-TODO.md` |
| ELSTER/ERiC live | `ELSTER-ERIC-TODO.md` |
| PHP 8.5 KAS-Umstellung | `PHP85-TEST-HANDOFF.md` |
| Nextcloud cloud.ganz-om.de | `CLOUD-NEXTCLOUD-RESTORE.md` |
| Zeiterfassung Ph.2+ | `ZEITERFASSUNG-PLAN.md` |

---

## Chats aufräumen

Nur **1 Cloud-Chat** + **1 Lokal-Chat** behalten.

---

## Detail-Handoffs (Referenz)

| Datei | Inhalt |
|-------|--------|
| `PC-HANDOFF.md` | PC-Deploy, Import |
| `BUCHHALTUNG-PC-HANDOFF.md` | Buchhaltung Deploy |
| `CLOUD-AGENT-ACCESS.md` | Secrets, rsync-Deploy |
| `ARBEITSVERTRAG-VORLAGEN-HINWEIS.md` | Vertragsvorlagen (Rechtliches) |
