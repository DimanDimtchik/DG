# DG CRM — ToDos & aktueller Stand

> **Regeln (nicht hier):** [`AGENTS.md`](../AGENTS.md) · SSH: [`CLOUD-AGENT-ACCESS.md`](CLOUD-AGENT-ACCESS.md)

Stand: **2026-09-17** — Belegdarstellung/AB-Druck deployed + synced; Vor-Hetzner-Checkliste `VOR-HETZNER-CHECK.md`.

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
- [x] **Master erneut deployen + sync** (nach Merge 16.09. / laufende Deploys Sep 2026)
- [x] Migrationen **062–081** / **064–078 fertig** (Master + ganz-soft.de + kontur, 2026-09-17) — runPending + Schema OK
- [x] **LDAP Phase 1 (Kasserver):** `ldap-readiness.php` gelaufen — php-ldap OK, Modus `local`, `ldap.local.php` fehlt (erwartet bis Hetzner)
- [x] Smoke: Akademie, Lager, Kichel, Terminkalender, Rechtstexte, Kontakt-Notiz, LDAP-UI
- [x] Fix: „Finanzamt ermitteln“ — lokale ESt-Nr. (z. B. 127/219/40770) nicht mehr als Steuer-ID verwerfen
- [x] Firmen-E-Mail in ganz-soft.de CRM eintragen (Einstellungen → Firma) — `info@ganz-om.de`
- [~] Manuelle Testliste **ganz-soft.de** — Block A erledigt (Lager/Akademie/Termin/Recht/Wartung); offen: Belegkette, Bank, Kichel
- [x] Multi-Firma Phase 0 — KDV Org↔Firma Registry (`082`, Formular/Liste) — Switcher = Phase 1
- [x] **ELSTER Phase 2:** Hersteller-ID **34573** in CRM-Einstellungen (ganz-soft.de) hinterlegt; danach ERiC Linux laden, Test-Zertifikat (nach Server-Umzug)
- [x] **Lager Phase 3+4:** Einkaufsliste/Ignore (081), In-Auslieferung, Beleg-Live-Hinweis
- [x] **Belegkette Kundendarstellung** — Deploy 2026-09-17 (Druck, Summen, Belegdarstellung, AB→Druck+Unterschrift)
- [x] **AB aus Angebots-Mail-Antwort** — Migration 083 · Auto-AB + Annahme-Vermerk (Inbound In-Reply-To)
- [x] **Anzahlung materialbasiert** — EK aus `dg_article_purchase_sources` (Preferred)
- [ ] **Vor Hetzner:** `VOR-HETZNER-CHECK.md` (Shared Core, Asset-Minify, GoBD/§14/§19-Stichprobe)
- [x] **Multi-Firma Betrieb MB1–MB4** — SSO-Secret, Switcher-Smoke, Contact-Export/Import, Provision-Gates (`MULTI-FIRMA-KONZEPT.md` §15); **MB1–MB4 ✅** (KAS-Voll-Lauf nur auf expliziten Befehl)
- [ ] **LDAP Phase 2 (nach Hetzner):** `ldap.local.php` + Hybrid-Login testen · Plugin-Code aus No-Repo-Chat
- [ ] **Amazon Business Stufe 2** (halbautomatisch aus Einkaufsliste) — Plan: `AMAZON-BUSINESS-STUFE2.md` · Onboarding Business-API parallel
- [x] **Zeiterfassung Phase 2+** — token-sparend Z2a–Z2e (`ZEITERFASSUNG-PLAN.md` § Betrieb); **Z2a–Z2e ✅** · weiter: `z3a` (Schichten)

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
| **Multi-Firma Phase 0** | **082** · `dg_kdv_orgs` / Org-Felder an KDV-Kunden |
| Wartungsmodus einheitlicher Code | `WebsiteMaintenanceRenderer` |
| Website-Menü Icons (Lucide) | `src/Website/` |

Doku: `BUCHHALTUNG-BELEGKETTE.md`, `BELEGKETTE-DARSTELLUNG.md`, `ARBEITSVERTRAG-VORLAGEN-HINWEIS.md`, `LDAP-INTEGRATION.md`, `LAGER-WIRTSCHAFT.md`, `ZEITERFASSUNG-PLAN.md`

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

**Erledigt 2026-09-17 (Nutzer):**

- [x] **Lager / Einkauf** komplett
- [x] **Akademie** öffnet; Kurs/Video erreichbar
- [x] **Terminkalender / Online-Buchung** (Vorschau)
- [x] **Rechtstexte** (Impressum o. Ä.) ohne Fehler
- [x] Öffentlich `/` = Wartungsseite
- [x] Kontakt-Link aus CRM (Firma/Wartung)

**Noch offen:**

- [ ] Belegkette, Workflow, Klauseln, Skonto, Teilzahlungen, Zeiterfassung
- [ ] **Bankabgleich:** Geisterumsätze erkennen, manuell ausblenden (Migration 064)
- [ ] **Kichel**

Basis: [`TESTLISTE-2026-08-21.md`](TESTLISTE-2026-08-21.md) Abschnitt K

---

## Geplant (Konzept, noch nicht gebaut)

| Thema | Doku |
|-------|------|
| Multi-Firma Code + Betrieb MB1–MB4 | erledigt 2026-09-21 |
| **Zeiterfassung Z2a–Z2e** (Soll/Ist, ArbZG-Warnung, Korrektur) | erledigt 2026-09-21 |
| Zeiterfassung Z3+ (Schichten …) | [`ZEITERFASSUNG-PLAN.md`](ZEITERFASSUNG-PLAN.md) § Betrieb |

---

## Bewusst zurückgestellt

| Thema | Doku |
|-------|------|
| Stripe Live | `SHOP-TODO.md` |
| ELSTER/ERiC live | `ELSTER-ERIC-TODO.md` |
| LDAP / dg-user live | `LDAP-INTEGRATION.md` |
| PHP 8.5 KAS-Umstellung | `PHP85-TEST-HANDOFF.md` |
| Nextcloud cloud.ganz-om.de | `CLOUD-NEXTCLOUD-RESTORE.md` |
| Zeiterfassung Ph.3–6 (nach Z2) | `ZEITERFASSUNG-PLAN.md` |

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
