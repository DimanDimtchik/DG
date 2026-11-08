# DG CRM — ERP-Rest (tokenarm)

> **Eine Chat-Session = eine Mini-ID** (nach Zerlegung).  
> Fachquelle: [`ERP-ERWEITERUNG-LASTENHEFT-V2.md`](ERP-ERWEITERUNG-LASTENHEFT-V2.md) · Roadmap: [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) · Lohn: [`LOHNABRECHNUNG-IMPLEMENTIERUNG.md`](LOHNABRECHNUNG-IMPLEMENTIERUNG.md)

Stand: **2026-11-08** · Branch-Empfehlung: `cursor/erp-…`

---

## Warum tokenarm (Free-/Hobby-Plan)

| Regel | Bedeutung |
|-------|-----------|
| **Kein Explorer-Chat** | Kein „lies ganz Rezeptur/Lohn/FiBu“ — nur Datei(en) der Mini-ID |
| **Kein Monolith** | Kein neues paralleles ERP-Modul; Anker: Rezeptur / FiBu / Akademie / Lohn |
| **Kein Live-Versand** | ITSG/ELSTER/Baulohn-Live = **Mensch + Gates** (Hetzner, G3, Zertifikate) |
| **1 ID = 1 Commit-fähig** | Nach Schritt: Lint/Selftest der genannten Datei |
| **Credentials nie committen** | Zertifikate / Portal-IDs nur Instanz |

**Chat-Prompt-Vorlage (nach Zerlegung kopieren):**  
`Nur Mini-ID Exx laut docs/ERP-REST-PLAN.md. Nur genannte Datei(en). Kein Refactor.`

**Nicht im Agent-Chat:** Baulohn vor Standardlohn, ITSG-Versand ohne Gate, FiBu-Buchungsroboter, PostgreSQL/UUID-Schema, Amazon/UI-CSS (eigene Pläne).

---

## Token-Stufen

| Symbol | Wann | Typisch |
|--------|------|---------|
| 🟢 | Doc, Migration-Skizze, 1 View-Hinweis, Guard | Free |
| 🟡 | 1 Service + 1 POST/View, klarer Diff | Kurz-Agent |
| 🔴 | Live-Meldung, Pilotnachweis, Zertifikatspfad | Mensch / nach Gate |

---

## Ist-Stand (Inventar 2026-11-08)

| Block aus Lastenheft V2 | Stand | Hinweis |
|-------------------------|-------|---------|
| Zeiterfassung / Lohn-Export / fremde Lohnabrechnungen | ✅ | nicht neu bauen |
| Maschinen / Work Centers / Rezeptur-Routing / Snapshots | ✅ | nicht neu bauen |
| **Maschinenblock M0–M5** (Kapazität, Zusatz, Wartung, Belegung, Kalk) | ✅ | gemergt 2026-10-29 · [`MASCHINENBLOCK-M0A-DATENMODELL.md`](MASCHINENBLOCK-M0A-DATENMODELL.md) |
| Akademie-Videos (Kurse/Module/VTT) | ✅ | Anker für Wissensbrücke |
| **Lohn D0-A Code** L0–L10c | ✅ | G4 ✅ · LB0a ✅ · **G3 Pilot offen** |
| Maschinen-Gemeinkosten aus FiBu (nicht buchungswirksam) | ❌ | nächster baubarer Code-Block |
| Maschinenwissen ↔ Handbuch/Akademie (Fehlercodes) | ❌ | klein, parallel möglich |
| Lohn D0-B ITSG / ELStAM / euBP live | 🔴 | nach **G3** + Hetzner/ERiC |
| Baulohn / SOKA / Saison-KUG (D0-C) | 🔴 | nach Standardlohn (+ ideal B) |
| Zertifizierungen (ITSG, ELSTER Org, …) | 🔴 | Meldewege, keine „allgemeine Finanzamt-Freigabe“ |

---

## Parent-Übersicht (noch ohne Mini-IDs)

| ID | Thema | Priorität | Status | Token |
|----|--------|-----------|--------|-------|
| E0 | Inventar / dieser Plan | — | [x] 2026-11-08 | 🟢 |
| E1 | **Maschinen-Gemeinkosten** (Kontengruppen → €/Maschinenstunde → Snapshot) | 1 unter offenen Code-Blöcken | [ ] | 🟡 |
| E2 | **Maschinenwissen** (Tabelle + Tab Wissen/Störungen + Fehlercode-Suche) | 2 · parallel zu E1 möglich | [ ] | 🟡 |
| E3 | **Lohn-Gates** (G3-Nachweis, dann LB1+ Spec) | Mensch/Pilot · blockiert Versand | [~] G3 offen | 🔴 |
| E4 | **Zertifizierungen / Meldewege** (nach Hetzner) | nach C1/ERiC | [ ] | 🔴 |
| E5 | **Baulohn D0-C** | nach G3 (+ ideal B) | [ ] | 🔴 |

### Leitplanken (aus Lastenheft)

- Gemeinkosten zuerst **nur Kalkulationsvorschlag** — keine automatischen FiBu-Buchungen.  
- Keine rückwirkende Änderung gebuchter Produktionsläufe / Snapshots.  
- Maschinenwissen: MariaDB (`dg_machine_knowledge_links`-Skizze im Lastenheft), keine UUID-Arrays.  
- Endnutzer-Texte: keine internen Gate-/Plan-Codes in der UI.

---

## Nächster Schritt (eigene Session)

**Plan in Mini-IDs zerlegen** — vorgeschlagen zuerst **E1** (Gemeinkosten), dann **E2** (Wissen), analog Amazon/UI:

1. E1a Migration / Settings-Keys  
2. E1b Service: Summe Kontengruppe ÷ Maschinenlaufzeit  
3. E1c UI Einstellungen + Anzeige in Kalkulation/Snapshot  
4. E2a Migration knowledge_links  
5. E2b CRUD/Repo  
6. E2c Tab Maschine + Fehlercode-Suche  

Zerlegung = eigene Chat-Session: nur Doc `ERP-REST-PLAN.md` Abhackplan-Tabelle mit Prompt-Zeilen füllen — **noch kein Code**.

---

## Empfohlene Free-Plan-Reihenfolge (nach Zerlegung)

1. **E1** Mini-IDs (Gemeinkosten, nicht buchungswirksam)  
2. **E2** Mini-IDs (Maschinenwissen)  
3. Parallel Mensch: **E3** G3-Pilotnachweis auf Dev-Instanz  
4. Nach Hetzner: **E4** / **E5** nur mit explizitem Gate  

**Parallel Mensch (🔴):** Amazon Onboarding (anderer Plan) · Hetzner/ERiC · G3 zwei Testmonate.

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`ERP-ERWEITERUNG-LASTENHEFT-V2.md`](ERP-ERWEITERUNG-LASTENHEFT-V2.md) | Fach / Lastenheft-Einordnung |
| [`MASCHINENBLOCK-M0A-DATENMODELL.md`](MASCHINENBLOCK-M0A-DATENMODELL.md) | M0–M5 erledigt |
| [`LOHNABRECHNUNG-IMPLEMENTIERUNG.md`](LOHNABRECHNUNG-IMPLEMENTIERUNG.md) | Lohn L* / Gates G* |
| [`AMAZON-BUSINESS-REST-PLAN.md`](AMAZON-BUSINESS-REST-PLAN.md) | Muster tokenarmer Mini-IDs |
| [`UI-CSS-REST-PLAN.md`](UI-CSS-REST-PLAN.md) | Muster tokenarmer Mini-IDs |
