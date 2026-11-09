# Hetzner AVV / TOMs — Checkliste & Status (PA3d)

> Stand: **2026-11-08** · Mini-ID **PA3d** · **Org-AVV abgeschlossen** (Nachweis PDFs vom Nutzer, nicht in Git)  
> Fach: [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) A3  
> **Keine** unterschriebenen PDFs / keine Auftraggeber-PII in diesem Repo.

Verwandt: [`SERVER-MIGRATION.md`](SERVER-MIGRATION.md) · [`FIBU-VERKAUFSREIFE.md`](FIBU-VERKAUFSREIFE.md) · [`KDV-TODO.md`](KDV-TODO.md) (Kunden-AVV/AGB separat)

---

## Status (Kurz)

| Thema | Status | Wer |
|-------|--------|-----|
| Checkliste dokumentiert (dieses Doc) | ✅ 2026-11-08 PA3d | Agent/Doc |
| Org-Laufzettel (Schreibtisch) | ✅ 2026-11-08 | Agent/Doc |
| Hetzner-Kundenkonto / Robot | ✅ Nachweis DPA-PDF 2026-11-07 | Mensch |
| AV-Vertrag (AVV) mit Hetzner Online GmbH abgeschlossen | ✅ **07.11.2026** · Version **1.2** (16.02.2026) | Mensch |
| TOMs gelesen / abgelegt | ✅ Anlage 2 im DPA-PDF · öffentliche TOM-URL referenziert | Mensch |
| TÜV-/Prüfprotokoll im Hetzner-Account abgerufen | ✅ TÜV Rheinland i-sec · Bericht **63017991-01** · Version 1.0 · Köln **19.02.2026** | Mensch |
| Kunden-AVV/AGB Hosting (DG als Anbieter) | ⬜ parallel FiBu-Verkauf | siehe KDV-TODO — **nicht** Hetzner-AVV |

**Phase-B-Cutover:** Hetzner-AVV-Blocker für Produktivdaten auf neuem Host damit **entschärft** (Empfehlung erfüllt). Serverkauf/DNS weiter Phase B.

---

## Nachweis (ohne PII / ohne PDF im Git)

Vom Nutzer bereitgestellt (Chat-Upload 2026-11-08; **intern archivieren**, nicht committen):

| Datei (Upload-Name) | Inhalt (Metadaten) |
|---------------------|--------------------|
| `dpa-2026-11-07.pdf` | AV-Vertrag Art. 28 DSGVO · **Version und Stand: 1.2, 16. Februar 2026** · Dokumentdatum **07.11.2026** · 27 Seiten inkl. Anlage 2 TOMs · Auftragnehmer Hetzner Online GmbH |
| `dpa-tuev-audit-de.pdf` | TÜV Rheinland i-sec GmbH · Prüfung TOMs Rechenzentren · **Version 1.0** · Bericht Nr. **63017991-01** · Köln **19. Februar 2026** · Bezug `hetzner.com/AV/TOM.pdf` |

Agent-Prüfung: Text/Metadaten aus den PDFs gelesen; Auftraggeber-Adresse/Name **nicht** in Docs übernommen.

---

## Org-Laufzettel (erledigt)

1. ~~Konto~~ ✅  
2. ~~AVV im Account~~ ✅ (PDF vom 07.11.2026)  
3. ~~Annehmen/unterzeichnen~~ ✅  
4. ~~PDF sichern~~ ✅ Upload an Agent + **bitte zusätzlich Firmenablage** (nicht Git)  
5. ~~TOM-/Audit-Nachweis~~ ✅ Anlage 2 + TÜV-Bericht  
6. ~~Rückmeldung Abschnitt D~~ ✅ siehe unten  

Öffentliche Referenz: [DPA DE](https://www.hetzner.com/AV/DPA_de.pdf) · [TOM](https://www.hetzner.com/AV/TOM.pdf) · [Datenschutz-Übersicht](https://docs.hetzner.com/de/general/company-and-policy/data-protection-at-hetzner/)

**Nicht verwechseln:** Kunden-AVV/AGB von DG als Hosting-Anbieter = [`KDV-TODO.md`](KDV-TODO.md).

---

## Rollen klären (wichtig)

| Rolle | Typisch |
|-------|---------|
| **Verantwortlicher** (Art. 4 Nr. 7 DSGVO) | Betreiber der CRM-Instanz / Firma (z. B. Kundenmandant) |
| **Auftragsverarbeiter Hetzner** | Hetzner Online GmbH — Hosting/Server |
| **DG CRM / Ganz Soft** | Softwareanbieter; gegenüber **Endkunden** ggf. eigener AVV (Hosting-Paket) — anderer Vertrag als Hetzner |

Dieses Doc betrifft nur **Hetzner als Unterauftragsverarbeiter / Hosting-AVV** für den Serverumzug.

---

## Offizielle Hetzner-Unterlagen (Links)

| Dokument | URL |
|----------|-----|
| AV-Vertrag (DPA) DE | https://www.hetzner.com/AV/DPA_de.pdf |
| TOMs (Anlage 2) | https://www.hetzner.com/AV/TOM.pdf |
| Datenschutz-Übersicht | https://docs.hetzner.com/de/general/company-and-policy/data-protection-at-hetzner/ |
| AV / Prüfbericht im Account | https://accounts.hetzner.com/account/dpa |

---

## Checkliste vor Cutover (Mensch)

### A · Vertrag Hetzner

- [x] Hetzner-Kundenkonto vorhanden (Robot / Cloud Console) — ✅ DPA-PDF 07.11.2026
- [x] AV-Vertrag im Account angenommen / unterzeichnet (Art. 28 DSGVO) — ✅ **07.11.2026**
- [x] PDF AVV + TOMs lokal/archiviert (nicht ins Git mit Unterschriften/PII) — ✅ Upload 2026-11-08; intern nacharchivieren
- [x] Standort der Verarbeitung (EU/EWR): **Finnland, HEL1** — per Bestellung **B20261107-3544202** (GEX45-1) bestätigt · bei Freischaltung IPv4 in Robot notieren (PB1a)

### B · TOMs inhaltlich (Stichprobe, kein Rechtsgutachten)

- [x] TOMs liegen vor (Anlage 2 im DPA + TÜV-Bezug auf aktuelle TOM-URL) — ✅ 2026-11-08
- [ ] Abgleich mit eigener Verfahrensdokumentation / TOMs der CRM-Instanz (Lücken notieren) — operativ vor Cutover
- [x] Backup/Restore-Konzept (PA3b) existiert als Doc — Offsite-Plan Hetzner bei Serverwahl vertiefen
- [ ] Support-/Root-Zugriffe: wer hat SSH-Keys (Deploy-User), Passwort-Login aus — bei Hetzner-Server Setup

### C · Gegenüber CRM-Kunden (separat, nicht PA3d-Abschluss)

- [ ] Eigener AVV/AGB Hosting für Mandanten ([`KDV-TODO.md`](KDV-TODO.md), FiBu-Verkaufsblocker)
- [ ] Klarstellung: Hosting ≠ Steuerberatung ([`FIBU-VERKAUFSREIFE.md`](FIBU-VERKAUFSREIFE.md))

### D · Nach AVV-Abschluss

| Feld | Eintrag |
|------|---------|
| Datum AVV aktiv | **2026-11-07** |
| Version AV/TOM | **1.2** (Stand 16.02.2026); TÜV-Audit-Bericht 1.0 / 19.02.2026 (Nr. 63017991-01) |
| Ablageort PDF (intern) | Chat-Upload Agent 2026-11-08 (**nicht Git**) · Nutzer: Firmenablage ergänzen |
| Nächste Review | **2027-11** oder bei neuer Hetzner-AV/TOM-Version (was früher eintritt) |

---

## Nicht-Ziele (PA3d)

- Kein Hetzner-Server bestellen / DNS-Cutover
- Keine Rechtsberatung / keine Vertragsunterzeichnung durch den Agenten
- Kein Kunden-AVV-Text im Produktcode
- Keine Secrets oder unterschriebenen PDFs ins Git

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`PHASE-A-REST-PLAN.md`](PHASE-A-REST-PLAN.md) | PA3d |
| [`DEPLOY-PIPELINE-PREP.md`](DEPLOY-PIPELINE-PREP.md) | Deploy-Prep ohne Cutover |
| [`BACKUP-RESTORE-TEST.md`](BACKUP-RESTORE-TEST.md) | Backup-Nachweis |
| [`SERVER-MIGRATION.md`](SERVER-MIGRATION.md) | Hetzner-Hardware |
| [`KDV-TODO.md`](KDV-TODO.md) | Kunden-AVV/AGB |
