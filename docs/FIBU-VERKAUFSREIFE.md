# FiBu-Verkaufsreife — Checkliste

> Stand: 2026-11-01  
> **Klarstellung:** Es gibt **keine** allgemeine Finanzamt-„Softwarezulassung“. Verkaufsfähig = GoBD-taugliche Buchführung, Meldewege, Verfahrensdoku, AVV, korrekte Steuerlogik.

Verwandt: [`UST-IST-SOLL-VERSTEUERUNG.md`](UST-IST-SOLL-VERSTEUERUNG.md) · [`FIRMEN-NACHWEIS-MATRIX.md`](FIRMEN-NACHWEIS-MATRIX.md) · [`VERFAHRENSDOKUMENTATION-VORLAGE.md`](VERFAHRENSDOKUMENTATION-VORLAGE.md) · [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) · [`ELSTER-ERIC-TODO.md`](ELSTER-ERIC-TODO.md) · [`KDV-TODO.md`](KDV-TODO.md)

---

## Schon tragfähig (Basis)

- [x] Doppelte Buchführung, SKR03/04, Belegkette
- [x] OPOS, Bank CAMT/MT940, BWA/SuSa
- [x] UStVA-CSV, DATEV EXTF + Beleg-ZIP
- [x] Jahresabschluss / Jahr sperren
- [x] §19 / §13b-Ansatz, DIY ohne Kanzlei
- [x] Soll-/Ist-Versteuerung inkl. FA-Bescheid ([`UST-IST-SOLL-VERSTEUERUNG.md`](UST-IST-SOLL-VERSTEUERUNG.md))
- [x] GoBD: Ausgang Revision/Storno; Eingang nach Buchung gesperrt; manuelle Buchung per Gegenbuchung stornieren
- [x] GoBD-Prüferexport (ZIP) unter Steuerberater-Export
- [x] Verfahrensdokumentation-Vorlage im Repo

---

## Blocker vor seriösem FiBu-Verkauf

| Thema | Status | Nächster Schritt |
|-------|--------|------------------|
| ELSTER live UStVA | CSV + Stub; Live blockiert | Hetzner + ERiC — [`ELSTER-ERIC-TODO.md`](ELSTER-ERIC-TODO.md) |
| AVV / AGB Hosting | organisatorisch offen | [`KDV-TODO.md`](KDV-TODO.md) AVV/AGB; Hosting ≠ Steuerberatung |
| Backup / Aufbewahrung | Storage pro Instanz | dokumentiertes Backup/Restore + Fristen (Hetzner) |
| §14 / gemischte Sätze / E-Rechnung | weitgehend | Stichproben A1; §13b XRechnung; ZUGFeRD-Entscheidung |
| Steuer-Update-Pfad | implizit | Changelog-Prozess bei UStG-Änderungen kommunizieren |
| Smoke FiBu auf Instanz | offen | [`TESTLISTE-2026-09-22.md`](TESTLISTE-2026-09-22.md) |

---

## Je Zielkunde später

- EÜR: CSV vorhanden — Smoke
- GmbH/Bilanz: E-Bilanz/KSt/GewSt = Roadmap D1 — ohne Vollversprechen verkaufen
- Lohn in Suite: ITSG erst für Direktmeldung; sonst „FiBu + Zeiten/Export“
- IDW PS 880: optional Marketing, keine gesetzliche Zulassung

---

## Haftungsrahmen (Produkttext)

- Formulierung: Buchhaltungssoftware zur **GoBD-unterstützten** Führung — **keine Steuerberatung**
- Support-SLA für gesetzliche Änderungen
- Mandantenfähigkeit: gleicher Code, eigene DB (bereits Regel)
