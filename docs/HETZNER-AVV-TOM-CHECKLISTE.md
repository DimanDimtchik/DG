# Hetzner AVV / TOMs — Checkliste & Status (PA3d)

> Stand: **2026-11-08** · Mini-ID **PA3d** · Org-Laufzettel nachgezogen (CTI vor Ort entfällt → **Org-AVV**)  
> Fach: [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) A3  
> **Nur Organisation / Status.** Kein Cutover, kein Server-Kauf. Agent unterschreibt **nicht**.

Verwandt: [`SERVER-MIGRATION.md`](SERVER-MIGRATION.md) · [`FIBU-VERKAUFSREIFE.md`](FIBU-VERKAUFSREIFE.md) · [`KDV-TODO.md`](KDV-TODO.md) (Kunden-AVV/AGB separat)

---

## Org-Laufzettel (jetzt, Schreibtisch)

Reihenfolge für den Menschen mit Hetzner-Login. Kein Vor-Ort nötig.

1. **Konto:** Einloggen unter [accounts.hetzner.com](https://accounts.hetzner.com/) (Robot/Cloud — gleiches Kundenkonto wie für den späteren Server).  
2. **AVV öffnen:** Account → **AV-Vertrag / DPA** → [accounts.hetzner.com/account/dpa](https://accounts.hetzner.com/account/dpa).  
3. **Lesen & annehmen:** aktuelle Fassung AV + TOMs im Account bestätigen/unterzeichnen (nicht die veraltete PDF-Kopie aus dem Web allein).  
4. **PDF sichern:** AVV + TOMs (+ ggf. Prüfbericht) **außerhalb Git** ablegen (Firmenablage / verschlüsselter Ordner).  
5. **Stichprobe TOMs:** Anlage 2 überfliegen (Zutritt, Zugang, Weitergabe, Verfügbarkeit, Trennung) — Lücken zur eigenen Verfahrensdoku notieren.  
6. **Rückmeldung an Agent/Doc:** Datum, AV/TOM-Version, Ablageort (ohne PII) → Abschnitt **D** unten ausfüllen lassen.

Öffentliche Referenz-PDFs (Stand-Check 2026-11-08: HTTP 200):  
[DPA DE](https://www.hetzner.com/AV/DPA_de.pdf) · [TOM](https://www.hetzner.com/AV/TOM.pdf) · [Datenschutz-Übersicht](https://docs.hetzner.com/de/general/company-and-policy/data-protection-at-hetzner/)

**Nicht verwechseln:** Kunden-AVV/AGB von DG als Hosting-Anbieter = [`KDV-TODO.md`](KDV-TODO.md) / FiBu-Verkauf — **anderer** Vertrag.

---

## Status (Kurz)

| Thema | Status | Wer |
|-------|--------|-----|
| Checkliste dokumentiert (dieses Doc) | ✅ 2026-11-08 PA3d | Agent/Doc |
| Org-Laufzettel (Schreibtisch) | ✅ 2026-11-08 nachgezogen | Agent/Doc |
| Hetzner-Kundenkonto / Robot | ⬜ offen | Mensch |
| AV-Vertrag (AVV) mit Hetzner Online GmbH abgeschlossen | ⬜ offen | Mensch |
| TOMs gelesen / abgelegt | ⬜ offen | Mensch |
| TÜV-/Prüfprotokoll im Hetzner-Account abgerufen (falls AV aktiv) | ⬜ offen | Mensch |
| Kunden-AVV/AGB Hosting (DG als Anbieter) | ⬜ parallel FiBu-Verkauf | siehe KDV-TODO — **nicht** Hetzner-AVV |

**Blocker für Phase-B-Cutover:** Hetzner-AVV sollte **vor** Produktivdaten auf dem neuen Host stehen (Empfehlung). Dry-Run-Deploy ohne personenbezogene Produktivdaten (PA3e) kann technisch vorher laufen.

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

Hinweis: Versionen ändern sich (Stand Recherche 2026: AV/TOM **1.2**, 16.02.2026) — bei Abschluss immer die **aktuelle** Fassung aus dem Hetzner-Account verwenden.

---

## Checkliste vor Cutover (Mensch)

### A · Vertrag Hetzner

- [ ] Hetzner-Kundenkonto vorhanden (Robot / Cloud Console)
- [ ] AV-Vertrag im Account angenommen / unterzeichnet (Art. 28 DSGVO)
- [ ] PDF AVV + TOMs lokal/archiviert (nicht ins Git mit Unterschriften/PII)
- [ ] Standort der Verarbeitung (EU/EWR) zur Planung passend (z. B. FSN1/HEL1 laut Serverwahl)

### B · TOMs inhaltlich (Stichprobe, kein Rechtsgutachten)

- [ ] TOMs gelesen (Zutritt, Zugang, Weitergabe, Eingabe, Auftrag, Verfügbarkeit, Trennung — Hetzner-Gliederung)
- [ ] Abgleich mit eigener Verfahrensdokumentation / TOMs der CRM-Instanz (Lücken notieren)
- [ ] Backup/Restore-Konzept (PA3b) + Offsite-Plan Hetzner (Snapshot/HDD) erwähnt
- [ ] Support-/Root-Zugriffe: wer hat SSH-Keys (Deploy-User), Passwort-Login aus

### C · Gegenüber CRM-Kunden (separat, nicht PA3d-Abschluss)

- [ ] Eigener AVV/AGB Hosting für Mandanten ([`KDV-TODO.md`](KDV-TODO.md), FiBu-Verkaufsblocker)
- [ ] Klarstellung: Hosting ≠ Steuerberatung ([`FIBU-VERKAUFSREIFE.md`](FIBU-VERKAUFSREIFE.md))

### D · Nach AVV-Abschluss (Status hier nachtragen)

| Feld | Eintrag |
|------|---------|
| Datum AVV aktiv | ________ |
| Version AV/TOM | ________ |
| Ablageort PDF (intern) | ________ |
| Nächste Review | ________ |

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
