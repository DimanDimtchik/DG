# Lexoffice → DG CRM — Migrationscheckliste (PA4f)

> Stand: **2026-11-08** · Mini-ID **PA4f** · Fach: [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) A4  
> **Nur Checkliste / Konzept.** Kein Produktiv-Import in diesem Ticket. Keine Firmendaten im Code.

Verwandt: Parallelbetrieb **PA4g** → [`LEXOFFICE-DG-PARALLELBETRIEB.md`](LEXOFFICE-DG-PARALLELBETRIEB.md) · Import-Fixtures [`testdata/import-fixtures/README.md`](testdata/import-fixtures/README.md) · Bank-Demo [`demo/bankabgleich/README.md`](demo/bankabgleich/README.md)

---

## Ziel

Stammdaten und offene Vorgänge aus **Lexoffice** (Export CSV/Excel) in eine **DG-CRM-Instanz** überführen, ohne Sondercode pro Kunde.

| Prinzip | Bedeutung |
|---------|-----------|
| Gleicher Code | Nur Instanz-DB + `config/*.local.php` + `storage/` |
| Kein Tenant-Hardcode | Firmendaten nur in der Ziel-Instanz setzen |
| Kein Big-Bang-Zwang | Parallelbetrieb möglich (PA4g) — Originalbeleg-Regel festlegen |
| GoBD | Gebuchte Lexoffice-Historie nicht „neu erfinden“; offene Posten bewusst übernehmen |

---

## Reihenfolge (empfohlen)

| # | Block | Warum zuerst |
|---|--------|----------------|
| 0 | Instanz bereit (PA4a–e) | Firma, SKR, Nummernkreise, Bank, Benutzer |
| 1 | **Kontakte** | Belege/OPOS brauchen Personenkonten |
| 2 | **Artikel** | Positionen auf offenen Ausgangsbelegen |
| 3 | **Bank** (CAMT/MT940) | Offene Umsätze zuordnen |
| 4 | **Offene Belege / OPOS** | Nur was noch läuft — Historie oft nur Archiv |
| 5 | Stichprobe + Parallelstart | PA4g |

---

## 0 · Vor dem Import (Instanz)

- [ ] CRM-Instanz mit eigener DB, Locals, Sync vom Code-Kern
- [ ] Einstellungen → Firma: Rechtsform, Gewinnermittlung, Steuernr., USt-IdNr., Finanzamt, SKR
- [ ] Steuerliche Sonderfälle nur wenn fachlich nötig (§13b, PV, …)
- [x] Nummernkreise so setzen, dass **keine Kollision** mit Lexoffice-Nummern entsteht (Lücke oder Prefix) — ✅ PA4e 2026-11-08: `RE-/AN-…-{JJJJ}{MM}-{NR}` ab **5000** (Lexoffice-Stichprobe ≤0338)
- [ ] ≥1 Admin + Buchhaltungs-Rolle zum Testen
- [ ] Lexoffice-Export **frisch** ziehen und lokal sichern (nicht im Git)

---

## 1 · Kontakte (Kunden / Lieferanten / Mitarbeiter)

### Lexoffice / sevDesk-Export

- Einstellungen → Export (Kontakte) → Excel oder CSV  
- Im DG-Import-Preset: **„sevDesk / Lexoffice“** (`InstallImportSourcePresets::SOURCE_SEVDESK`)

### DG — wohin

| Weg | Wann |
|-----|------|
| Installationsassistent / Massenimport | Erstbefüllung |
| Laufender CRM-Kontaktimport (Install-Engine) | Nach Go-Live Nachzüge |
| Testfixtures | `docs/testdata/import-fixtures/kontakte/kontakte-sevdesk.csv` (Demo, keine Echtpersonen) |

### Checkliste Kontakte

- [ ] Export enthält Name, Firma, E-Mail, Telefon, Straße, PLZ, Ort, USt-IdNr. (soweit vorhanden)
- [ ] Rollen trennen: Kunde / Lieferant / Mitarbeiter (nicht alles als Kunde)
- [ ] Duplikat-Strategie: zuerst klein importieren → `on_duplicate = skip` prüfen
- [ ] Stichprobe: 5 Kontakte manuell gegen Lexoffice vergleichen (Adresse, USt-IdNr.)
- [ ] Keine Produktiv-CSV ins Repo committen

---

## 2 · Artikel / Leistungen

### Lexoffice

- Produkte/Leistungen exportieren (Excel/CSV)

### DG

- Artikelstamm / Kalender-Artikel je nach Nutzung  
- Install-Import: gleiches Quell-Preset **sevDesk / Lexoffice** wo Produkte unterstützt  
- Steuersätze (19 / 7 / 0 / §13b-Kennzeichnung) **vor** offenen Belegen prüfen

### Checkliste Artikel

- [ ] Bezeichnung, Einheit, Netto-/Bruttopreis, Steuersatz
- [ ] Lagerartikel vs. Dienstleistung klar
- [ ] 0-%-Fälle (PV, Reverse Charge) nicht stillschweigend auf 19 % mappen
- [ ] Stichprobe: 5 Artikel Preise/Steuer gegen Lexoffice

---

## 3 · Bank

### Lexoffice / Bank

- Kein Ersatz für GoBD-Kontoauszüge: **CAMT.053** oder **MT940** von der Bank holen (nicht nur Lexoffice-UI-Export)

### DG

- Buchhaltung → Bankabgleich → Datei importieren  
- Demo/Übung: `docs/demo/bankabgleich/`

### Checkliste Bank

- [ ] Primärkonto in Firmeneinstellungen hinterlegt (IBAN)
- [ ] Letzte 30–90 Tage CAMT/MT940 importiert (Testinstanz zuerst)
- [ ] Duplikaterkennung verstanden (Zweitimport)
- [x] Geisterumsätze: ausblenden nur bewusst (Migration 064 / PA2c) — ✅ PA2c 2026-11-08 (classify ohne Auto-Hide)- [ ] Keine Zuordnung „auf Verdacht“ ohne Beleg

---

## 4 · Belege / offene Posten

### Was **nicht** pauschal übernehmen

| Inhalt | Empfehlung |
|--------|------------|
| Alte, bezahlte Ausgangsrechnungen | Lexoffice/DATEV-Archiv belassen; in DG nicht neu buchen |
| Entwurf / Angebot ohne Folge | Optional neu anlegen oder weglassen |
| Gebuchte Journalhistorie komplett | Kein 1:1-Import-Zwang; Saldenübernahme nur mit Konzept |

### Was **sinnvoll** ist

- [ ] Offene Forderungen / Verbindlichkeiten (OPOS) als Liste aus Lexoffice exportieren
- [ ] Pro offenem Posten: Kontakt existiert in DG → Beleg in DG neu erfassen **oder** Startsaldo/OPOS-Buchung laut Steuerberater-Abstimmung
- [ ] PDF-Originale der offenen Belege in `storage/` / Beleganhang ablegen (Install-Belegimport nur als Datei-Anhang-Hilfe, Beträge manuell prüfen)
- [ ] Ausgangskette ab Stichtag nur noch in DG (siehe PA4g)

### Checkliste Belege

- [ ] Stichtag „DG ist führend“ notiert
- [ ] Offene AR/AP-Liste abgehakt (Anzahl, Summe brutto)
- [ ] Keine Doppelbuchung Lexoffice + DG für denselben Umsatz
- [ ] §13b / PV-Klauseln an übernommenen offenen Ausgangsbelegen gesetzt

---

## 5 · Abnahme vor Parallelstart

- [ ] Kontakt- und Artikel-Stichproben ok
- [ ] Bank-Import läuft; 3 Umsätze probeweise zugeordnet
- [ ] Offene-Posten-Saldo grob gleich Lexoffice (Toleranz dokumentieren)
- [ ] Benutzer können Angebot → Rechnung in DG erzeugen (Smoke)
- [ ] Parallelregeln schriftlich: [`LEXOFFICE-DG-PARALLELBETRIEB.md`](LEXOFFICE-DG-PARALLELBETRIEB.md) (PA4g)

---

## Nicht-Ziele (PA4f)

- Kein API-Live-Pull aus Lexoffice
- Kein automatischer Vollimport aller Historienjahre
- Kein Produktiv-Lauf durch den Agenten
- Keine kundenspezifischen Hardcodes in `src/` / `views/`

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`PHASE-A-REST-PLAN.md`](PHASE-A-REST-PLAN.md) | PA4f / PA4g |
| [`LEXOFFICE-DG-PARALLELBETRIEB.md`](LEXOFFICE-DG-PARALLELBETRIEB.md) | PA4g Zeitraum + Originalbeleg-Regel |
| [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) | A4 Migrationskonzept |
| [`testdata/import-fixtures/README.md`](testdata/import-fixtures/README.md) | Demo-CSV inkl. sevDesk/Lexoffice-Preset |
| [`ZUGFERD-AUSGANG-ENTSCHEIDUNG.md`](ZUGFERD-AUSGANG-ENTSCHEIDUNG.md) | E-Rechnung Ausgang (nach Umstieg) |
