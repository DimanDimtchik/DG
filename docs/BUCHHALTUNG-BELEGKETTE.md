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
- Migration `057_voucher_document_texts.sql` — `document_intro_text`, `document_footer_text` ✅
- Migration `058_voucher_document_legal_clauses.sql` — `document_legal_clauses` (JSON) ✅

### UI / Workflow

- [x] Dokumentart-Auswahl im Belegformular (getrennt von EÜR-Belegtyp `income`)
- [x] „Folgebeleg“ (AB, Abschlag, Schlussrechnung) mit Positionsübernahme
- [x] Belegkette-Panel mit Links auf allen verknüpften Belegen
- [x] Schlussrechnung: Summe bisheriger Abschläge anzeigen und abziehen
- [x] Dokumentstatus (Entwurf, Versendet, Angenommen, Abgerechnet, Storniert) + Schnellaktionen
- [x] Belegliste: Filter und Spalten Dokument + Dokumentstatus
- [x] Druck/PDF inkl. Kette (HTML-Druck, Layout je Dokumentart)
- [x] E-Mail-Versand aus dem Beleg (HTML-Anhang + Vorschau im Body)
- [x] Freitext vor/nach Rechnungspositionen (Migration 057)
- [x] Gesetzliche Hinweis-Vorlagen (§ 19, § 13b, Photovoltaik, …) neben Freitext (Migration 058)
- [x] Logo und Pflichtangaben im Druck/PDF-Layout (Firmenlogo, Steuernummer, USt-IdNr., Handelsregister, GF/Inhaber)

### Buchhaltung

- [x] Abschlagsrechnung: Umsatz buchen, OPOS Teilbetrag (über bestehende Einnahmen-Logik)
- [x] Schlussrechnung: Restumsatz, Verknüpfung Abschlagszahlungen (Anzeige + Positions-Skalierung)
- [x] Angebot/AB/Lieferschein: kein Journal bis Rechnungsstellung
- [x] Nummernkreis-Zuordnung je `document_kind`

### Tests (nach Deploy)

**Druckbare Checkliste:** [`TESTLISTE-RANDFAELLE-2026-08-23.html`](TESTLISTE-RANDFAELLE-2026-08-23.html)

1. Angebot anlegen → keine Buchung, keine UStVA
2. AB aus Angebot → noch keine Buchung
3. Abschlagsrechnung 30 % → Journal + OPOS
4. Schlussrechnung → Rest 70 %, Summe = Auftrag
5. Gutschrift auf Rechnung → Minderung korrekt
6. Status-Workflow: versendet → angenommen → abgerechnet
7. Belegliste filtern nach Dokumentart und Status
8. Rechnung: Freitext + gesetzliche Vorlagen (§ 19, Reverse Charge) auf Druck/PDF/E-Mail prüfen
9. Skonto-Stufen: 7 Tage 3 %, 30 Tage netto, 90 Tage Verzug — Zahlung buchen, Skonto automatisch
10. Mahnung: Fälligkeit überschritten → manuell/automatisch (Cron), Mahngebühren auf Beleg
11. Teilzahlungen: zwei Zahlungen auf eine Rechnung → OPOS „Bezahlt/Offen“, Status `partial`, Mahnung nur auf Rest

### Teilzahlungen (Migration 060)

| Aspekt | Status |
|--------|--------|
| Tabelle `dg_voucher_payments` (Datum, Betrag, Art, Bankumsatz) | ✅ |
| Mehrere Zahlungen pro Rechnung (Historie im Belegformular) | ✅ |
| Zahlungsstatus `partial` (teilweise bezahlt) | ✅ |
| OPOS: Spalten „Bezahlt“ und „Offen“ (Brutto − Summe Zahlungen) | ✅ |
| Bankabgleich: Match auf Restbetrag, Teilzahlung buchen | ✅ |
| Mahnung: nur auf Restbetrag (`openAmount`) | ✅ |
| Legacy `paid_amount` → erste Zahlung in Historie (Migration beim Laden) | ✅ |

Manuell im Beleg: „Neue Zahlung erfassen“ (Betrag, Datum, Art). Bei voller Bezahlung über Zahlungsstatus ohne Historie legt `finalizePayments()` eine Zahlung an.

---

## Zahlungsbedingungen & Mahnwesen

### Skonto-Stufen (Einstellungen → Buchhaltung → Zahlungsbedingungen & Mahnung)

Standard-Stufen (anpassbar):

| Tage ab Rechnungsdatum | Änderung | Bedeutung |
|------------------------|----------|-----------|
| 7 | −3 % | Skonto |
| 30 | 0 % | Netto ohne Abzug |
| 90 | +1,5 % | Verzugszinsen bei späterer Zahlung |

Pro Rechnung editierbar im Belegformular. Text erscheint auf Druck/PDF/E-Mail. Bei Zahlungsdatum wird Skonto/Zuschlag vorgeschlagen.

### Automatischer Mahnversand

**Standard (ohne KAS-Cron):** Wenn „Automatischer Mahnversand“ aktiv ist, läuft `DunningService::runIfDue()` beim ersten Request des Tages (`App::boot` — wie Tages-Backup). Status: `storage/dunning-auto-state.json`, Log: `storage/logs/dunning-auto.log`.

**Optional KAS:** `cron.php?job=dunning-auto&token=…` (Token in `config/cron.local.php`) — für feste Uhrzeit oder erzwungenen Lauf.

---

## Gesetzliche Hinweis-Vorlagen (Rechnung / Abschlag / Schlussrechnung)

Unter den Rechnungspositionen:

1. **Einleitungstext** (Freitext, z. B. „Wir berechnen Ihnen …“)
2. **Zusätzlicher Freitext** nach den Positionen (Zahlungsziel, Skonto, persönliche Hinweise)
3. **Gesetzliche Hinweise (Vorlagen)** — Checkboxen mit Standardformulierungen:
   - Kleinunternehmer § 19 UStG
   - Photovoltaik § 12 Abs. 3 UStG
   - Steuerfreie Leistung § 4 UStG
   - Innergemeinschaftliche Lieferung / Ausfuhrlieferung
   - Reverse Charge § 13b (allgemein, EU, Bauleistung)
   - Differenzbesteuerung § 25a UStG

Bei gesetztem Reverse-Charge-Typ werden passende Vorlagen als **Vorschlag** markiert. Ausgabe: Freitext zuerst, dann die gewählten Vorlagen auf Druck, PDF und E-Mail.

---

## Empfohlene Reihenfolge (Rest)

1. **Manuelle Tests** nach Migration 055 + 056

---

## Randfälle (Branch cursor/buchhaltung-randfaelle-1c3a)

- PRAP für Einnahmen (Vorausrechnung)
- Negative Rabattpositionen auf Rechnungen
- Trinkgeld als Zahlungsart (Durchlaufende Posten + Kassenbuch)
