# DG CRM — ToDos & aktueller Stand

> **Regeln (nicht hier):** [`AGENTS.md`](../AGENTS.md) · SSH: [`CLOUD-AGENT-ACCESS.md`](CLOUD-AGENT-ACCESS.md)

Stand: **2026-09-16** — Cloud-Feature-Branches in `master` gemergt; bei Session zuerst prüfen.

---

## Git & Branch

| Branch | Status |
|--------|--------|
| `master` | **Produktionslinie** — Akademie, Lager, Kichel, Terminkalender, Rechtstexte, LDAP-Prep, Kontakt-Notiz |

**Merge 2026-09-16:**

1. `cursor/arbeitsvertrag-artifacts-ignore-2ec8`
2. `cursor/akademie-lager-konten-kichel-1dc6` (Lager / Akademie / Kichel / Termin / Recht)
3. `cursor/kontakt-bemerkung-ip-1dc6` (LDAP + Kontakt-Notiz + IQ-SSH)

Zwischenschritte nicht einzeln gemergt — Inhalt steckte in 2/3. Remote-`cursor/*`-Branches danach gelöscht.

Deploy: `bash bin/deploy-via-rsync.sh` (Cloud) oder `deploy.bat` (PC) → `bash bin/sync-crm-from-master.sh` (auf Server).

---

## Prioritäten (jetzt)

- [x] Master auf Server deployen + Instanzen syncen (2026-09-03)
- [x] Lizenzserver repariert; kontur/Master `/login` OK
- [x] Feature-Branches gemergt: Bank-Geisterumsätze, Arbeitsvertrag-Doku
- [x] Cloud-Sammelbranches in `master` (2026-09-16)
- [x] **Multi-Firma / Umfirmierung** — Konzept dokumentiert (`MULTI-FIRMA-KONZEPT.md`)
- [ ] **Master erneut deployen + sync** (nach Merge 16.09.)
- [ ] Migrationen **064–077** (+ LDAP **078**) auf Live prüfen
- [ ] Migrationen 062–063 (Zeiterfassung/ArbZG) prüfen
- [x] Smoke: Akademie, Lager, Kichel, Terminkalender, Rechtstexte, Kontakt-Notiz, LDAP-UI
- [x] Fix: „Finanzamt ermitteln“ — lokale ESt-Nr. (z. B. 127/219/40770) nicht mehr als Steuer-ID verwerfen
- [ ] Firmen-E-Mail in ganz-soft.de CRM eintragen (Einstellungen → Firma)
- [ ] Manuelle Testliste Randfälle auf **ganz-soft.de**
- [ ] Multi-Firma Phase 0/1 planen (Org-Switcher, KDV Org↔Firma) — siehe Konzept
- [ ] **ELSTER Phase 2:** Hersteller-ID per E-Mail abwarten → ERiC Linux laden, Test-Zertifikat
- [ ] **LDAP Phase 1:** `ldap-readiness.php` auf Kasserver · Plugin-Code aus No-Repo-Chat

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
| Lagerwirtschaft | **065–069** · `views/modules/lager.php` |
| Akademie | **070–072** |
| Terminkalender Website | **073–075** |
| **Rechtstexte Mehrprodukt** | **076** · `LegalProductSettings` |
| Kichel-Protokoll | **077** |
| **LDAP-Vorbereitung** | **078** · `LdapAuthenticator` / Einstellungen |
| Kontakt-Notiz (`contact_note`) | Kontakte |
| Wartungsmodus einheitlicher Code | `WebsiteMaintenanceRenderer` |
| Website-Menü Icons (Lucide) | `src/Website/` |

Doku: `BUCHHALTUNG-BELEGKETTE.md`, `ARBEITSVERTRAG-VORLAGEN-HINWEIS.md`, `LDAP-INTEGRATION.md`, `LAGER-WIRTSCHAFT.md`, `ZEITERFASSUNG-PLAN.md`

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
- [ ] Akademie / Lager / Kichel / Terminkalender / Rechtstexte
- [ ] Wartungsmodus: Layout + Kontakt aus CRM

Basis: [`TESTLISTE-2026-08-21.md`](TESTLISTE-2026-08-21.md) Abschnitt K

---

## Geplant (Konzept, noch nicht gebaut)

| Thema | Doku |
|-------|------|
| **Multi-Firma** (Switcher, Tochter, Umfirmierung unterjährig) | [`MULTI-FIRMA-KONZEPT.md`](MULTI-FIRMA-KONZEPT.md) |
| Zweitfirma −20 %, Umfirmierung = 1 Paket + Archiv-Slot | Abschnitt 7 im Konzept |

---

## Bewusst zurückgestellt

| Thema | Doku |
|-------|------|
| Stripe Live | `SHOP-TODO.md` |
| ELSTER/ERiC live | `ELSTER-ERIC-TODO.md` |
| LDAP / dg-user live | `LDAP-INTEGRATION.md` |
| PHP 8.5 KAS-Umstellung | `PHP85-TEST-HANDOFF.md` |
| Nextcloud cloud.ganz-om.de | `CLOUD-NEXTCLOUD-RESTORE.md` |
| Zeiterfassung Ph.2+ | `ZEITERFASSUNG-PLAN.md` |

---

## Chats aufräumen

Nur **1 Cloud-Chat** + **1 Lokal-Chat** behalten. Agent kann nicht archivieren — du: Rechtsklick → Archive.

---

## Detail-Handoffs (Referenz)

| Datei | Inhalt |
|-------|--------|
| `PC-HANDOFF.md` | PC-Deploy, Import |
| `BUCHHALTUNG-PC-HANDOFF.md` | Buchhaltung Deploy |
| `CLOUD-AGENT-ACCESS.md` | Secrets, rsync-Deploy |
| `ARBEITSVERTRAG-VORLAGEN-HINWEIS.md` | Vertragsvorlagen (Rechtliches) |
| `MULTI-FIRMA-KONZEPT.md` | Multi-Firma, Umfirmierung, Pakete/Rabatt |
| `HANDOFF-WEBSITE-MENU-ICONS.md` | Menü-Icons Phase 2 |
| `LDAP-INTEGRATION.md` | LDAP-Vorbereitung |
| `LAGER-WIRTSCHAFT.md` | Lager |
