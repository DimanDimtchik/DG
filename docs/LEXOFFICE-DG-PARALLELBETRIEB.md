# Lexoffice + DG — Parallelbetrieb (PA4g)

> Stand: **2026-11-08** · Mini-ID **PA4g** · Fach: [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) A4  
> **Nur Regeln / Checkliste.** Kein Cutover-Code, kein Produktiv-Import.  
> Voraussetzung: Migrationscheckliste [`LEXOFFICE-DG-MIGRATION.md`](LEXOFFICE-DG-MIGRATION.md) (PA4f).

---

## Entscheidung (Standardvorschlag)

| Punkt | Festlegung |
|-------|------------|
| **Dauer** | **1–2 Monate** ab Stichtag „DG startet parallel“ |
| **Neue Ausgangsbelege** (Angebot → Rechnung) | ab Stichtag **nur DG** = Original |
| **Neue Eingangsbelege** | ab Stichtag **nur DG** (Scan/PDF + Buchung) |
| **Lexoffice** | Lesen / Archiv / offene Alt-Posten abarbeiten — **keine** neuen Umsätze |
| **Bank** | Auszüge (CAMT/MT940) in **DG** importieren und zuordnen |
| **Ende Parallel** | Cutover-Datum setzen → Lexoffice nur noch Archiv/Leserecht (siehe A4/C in IQ-STROM) |

Instanz-Team trägt die konkreten Daten unten ein (nicht im Produktcode).

---

## Auszufüllen je Instanz

| Feld | Wert (Instanz notieren) |
|------|-------------------------|
| Stichtag Parallelstart (DG führend für **neue** Belege) | ________ (YYYY-MM-DD) |
| Geplantes Parallel-Ende / Cutover | ________ (YYYY-MM-DD) · Ziel: +1–2 Monate |
| SKR / Gewinnermittlung in DG geprüft | [ ] |
| Wer entscheidet Ausnahmen? | Name/Rolle: ________ |

---

## Originalbeleg-Regel (GoBD / Nachvollziehbarkeit)

| Vorgang | Original während Parallel | Kopie / Hinweis |
|---------|---------------------------|-----------------|
| Vor Stichtag ausgestellt (Lexoffice) | **Lexoffice** / dortiges PDF | Optional PDF in DG-Anhang; **nicht** neu als Ausgang buchen |
| Ab Stichtag Ausgang (Angebot, AB, Rechnung, …) | **DG** (Druck/PDF/E-Rechnung) | Lexoffice nicht parallel ausstellen |
| Ab Stichtag Eingang | **DG**-Belegakte (`storage` / Beleganhang) | Lieferanten-PDF behalten |
| Zahlung / Bankumsatz | Bankbeleg + DG-Bankabgleich | Lexoffice-Bankmodul nicht parallel pflegen |
| Korrektur Alt-Beleg (vor Stichtag) | Im **Ursprungssystem** (meist Lexoffice) stornieren/korrigieren | In DG nur wenn bewusst nacherfasst und gekennzeichnet |
| Offener Posten aus Lexoffice | Bis Zahlung: Lexoffice **oder** bewusst in DG nacherfasst — **nie beides buchungswirksam** | Liste aus PA4f abhaken |

**Kurzformel:** Ein wirtschaftlicher Vorgang → **ein** führendes System. Doppelte Rechnung = Fail.

---

## Was in Lexoffice noch erlaubt ist

- [ ] Offene Posten **vor** Stichtag zu Ende führen (Zahlungseingang verbuchen, falls noch dort gestartet)
- [ ] Auswertungen / DATEV-Export Historie
- [ ] Nur-Lese-Zugriff für Prüfung
- [ ] **Nicht:** neue Angebote/Rechnungen an Kunden
- [ ] **Nicht:** neue Lieferantenrechnungen als Hauptbuchung, wenn DG schon führt

---

## Was in DG Pflicht ist (ab Stichtag)

- [ ] Alle neuen Kundenbelege in der Belegkette
- [ ] Eingangsbelege mit Anhang
- [ ] Bankabgleich laufend
- [ ] UStVA-Vorbereitung aus DG (CSV; ERiC erst nach Hetzner/C1)
- [ ] Benutzer kennen die Regel „neu nur DG“ (kurze Einweisung)

---

## Abbruch / Verlängerung

| Situation | Aktion |
|-----------|--------|
| Nach 4 Wochen viele Doppelbuchungen | Parallel **pausieren**, Ursache klären, Stichtag neu setzen |
| Fachlich noch nicht stabil (USt, §13b) | Parallel um bis zu **+4 Wochen** verlängern, Cutover verschieben |
| Cutover erreicht, Lex noch nötig für Altjahr | Lex = Archiv; neues Jahr nur DG |

---

## Abnahme vor Cutover (Ende Parallel)

- [ ] Keine neuen Belege mehr in Lexoffice seit ≥2 Wochen
- [ ] Offene AR/AP nur noch in **einem** System
- [ ] Bankzuordnung in DG aktuell
- [ ] Stichprobe 5 Ausgang + 5 Eingang: Original eindeutig DG
- [ ] Cutover-Datum kommuniziert; Lex-Zugang auf Lesen/Archiv

---

## Nicht-Ziele (PA4g)

- Kein automatischer Sync Lexoffice ↔ DG
- Kein Löschen von Lexoffice-Daten durch DG
- Keine Firmendaten / Mandantennamen im Produktcode
- Kein Hetzner-Cutover (Phase B separat)

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`LEXOFFICE-DG-MIGRATION.md`](LEXOFFICE-DG-MIGRATION.md) | PA4f Import-/Übernahme-Checkliste |
| [`PHASE-A-REST-PLAN.md`](PHASE-A-REST-PLAN.md) | PA4g |
| [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) | A4 Parallel · später Cutover Lex Archiv |
| [`FIBU-VERKAUFSREIFE.md`](FIBU-VERKAUFSREIFE.md) | GoBD / Verkaufsreife |
