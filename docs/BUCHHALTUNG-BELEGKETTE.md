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

### Datenmodell (Migration `055_voucher_document_chain.sql` — vorbereitet)

- `document_kind` — z. B. `offer`, `order_confirmation`, `partial_invoice`, `invoice`, `final_invoice`
- `parent_voucher_id` — Verknüpfung zur Vorgänger-Stufe

**Noch nicht angebunden:** UI, API, PDF, Workflow-Buttons.

### UI / Workflow

- [ ] Dokumentart-Auswahl im Belegformular (getrennt von EÜR-Belegtyp `income`)
- [ ] „Aus Angebot erstellen“ / „Folgebeleg“ (AB, Abschlag, Schlussrechnung)
- [ ] Positionsübernahme vom Vorgängerbeleg
- [ ] Schlussrechnung: Summe bisheriger Abschläge anzeigen und abziehen
- [ ] Status je Stufe (entwurf, versendet, angenommen, abgerechnet, storniert)
- [ ] PDF-Vorlagen je Dokumentart (Angebot ohne Buchung, Rechnung mit Pflichtangaben)

### Buchhaltung

- [ ] Abschlagsrechnung: Umsatz buchen, OPOS Teilbetrag
- [ ] Schlussrechnung: Restumsatz, Verknüpfung Abschlagszahlungen
- [ ] Angebot/AB: **kein** Journal bis Rechnungsstellung
- [ ] Nummernkreis-Zuordnung: `partial_invoice`, `final_invoice` an `numberRangeTypeForVoucher()`

### Tests (nach UI)

1. Angebot anlegen → keine Buchung, keine UStVA
2. AB aus Angebot → noch keine Buchung
3. Abschlagsrechnung 30 % → Journal + OPOS
4. Schlussrechnung → Rest 70 %, Summe = Auftrag
5. Gutschrift auf Rechnung → Minderung korrekt

---

## Empfohlene Reihenfolge

1. **Dokumentart-Feld** + Anzeige in Belegliste (Filter)
2. **Folgebeleg-Button** mit Positionskopie
3. **Schlussrechnung-Logik** (Abzug Abschläge)
4. **PDF** je Dokumentart
5. **Status & Versand** (E-Mail aus CRM)

---

## Randfälle (parallel erledigt in diesem Branch)

- PRAP für Einnahmen (Vorausrechnung)
- Negative Rabattpositionen auf Rechnungen
- Trinkgeld als Zahlungsart (Durchlaufende Posten + Kassenbuch)
