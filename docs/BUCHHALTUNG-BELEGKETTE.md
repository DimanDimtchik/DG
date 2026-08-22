# Buchhaltung — Belegkette (Angebot → Schlussrechnung)

> **Stand:** 22.08.2026  
> Verwandt: [`buchhaltung-todo.html`](buchhaltung-todo.html) · Nummernkreise in Einstellungen

---

## Zielkette (Ausgangsbelege)

| Stufe | Dokument | Buchung? | Nummernkreis (Settings) |
|-------|----------|----------|-------------------------|
| 1 | **Angebot** | Nein (unverbindlich) | `offer` ✅ |
| 2 | **Auftragsbestätigung** | Nein | `order_confirmation` ✅ (neu) |
| 3 | **Abschlagsrechnung** | Ja (Teilumsatz) | `partial_invoice` ✅ (neu) |
| 4 | **Rechnung** | Ja | `invoice` ✅ (Belegart Einnahmen) |
| 5 | **Schlussrechnung** | Ja (Restbetrag) | `final_invoice` ✅ |
| — | **Kundengutschrift** | Ja (Minderung) | `credit_note` ✅ |

---

## Was bereits existiert

| Baustein | Status |
|----------|--------|
| Nummernkreise Angebot / Rechnung / Schlussrechnung / Gutschrift | ✅ `NumberRangeSettings` |
| Belegart **Einnahmen** mit Auto-Rechnungsnummer (`invoice`) | ✅ |
| Belegart **Kundengutschrift** mit Auto-Nummer (`credit_note`) | ✅ |
| Rechnungspositionen (Artikel/Leistungen) | ✅ |
| Journal / OPOS / UStVA aus Einnahmenbelegen | ✅ |

---

## Was noch fehlt (Implementierung)

### Datenmodell

- Migration `055_voucher_document_chain.sql` — `document_kind`, `parent_voucher_id` ✅
- Migration `056_voucher_document_status.sql` — `document_status` ✅

### UI / Workflow

- [x] Dokumentart-Auswahl im Belegformular (getrennt von EÜR-Belegtyp `income`)
- [x] „Folgebeleg“ (AB, Abschlag, Schlussrechnung) mit Positionsübernahme
- [x] Belegkette-Panel mit Links auf allen verknüpften Belegen
- [x] Schlussrechnung: Summe bisheriger Abschläge anzeigen und abziehen
- [x] Dokumentstatus (Entwurf, Versendet, Angenommen, Abgerechnet, Storniert) + Schnellaktionen
- [x] Belegliste: Filter und Spalten Dokument + Dokumentstatus
- [x] Druck/PDF inkl. Kette (HTML-Druck)
- [ ] PDF-Vorlagen je Dokumentart (Layout/Pflichtangaben Feinschliff)
- [ ] E-Mail-Versand aus dem Beleg

### Buchhaltung

- [x] Abschlagsrechnung: Umsatz buchen, OPOS Teilbetrag (über bestehende Einnahmen-Logik)
- [x] Schlussrechnung: Restumsatz, Verknüpfung Abschlagszahlungen (Anzeige + Positions-Skalierung)
- [x] Angebot/AB/Lieferschein: kein Journal bis Rechnungsstellung
- [x] Nummernkreis-Zuordnung je `document_kind`

### Tests (nach Deploy)

1. Angebot anlegen → keine Buchung, keine UStVA
2. AB aus Angebot → noch keine Buchung
3. Abschlagsrechnung 30 % → Journal + OPOS
4. Schlussrechnung → Rest 70 %, Summe = Auftrag
5. Gutschrift auf Rechnung → Minderung korrekt
6. Status-Workflow: versendet → angenommen → abgerechnet
7. Belegliste filtern nach Dokumentart und Status

---

## Empfohlene Reihenfolge (Rest)

1. **PDF-Layout** je Dokumentart
2. **E-Mail-Versand** (Angebot/Rechnung an Kunden)
3. **Manuelle Tests** nach Migration 055 + 056

---

## Randfälle (Branch cursor/buchhaltung-randfaelle-1c3a)

- PRAP für Einnahmen (Vorausrechnung)
- Negative Rabattpositionen auf Rechnungen
- Trinkgeld als Zahlungsart (Durchlaufende Posten + Kassenbuch)
