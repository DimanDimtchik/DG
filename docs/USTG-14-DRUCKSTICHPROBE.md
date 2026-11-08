# §14 UStG Druckstichprobe — Ausgangsbelege

> Zweck: kurze manuelle Stichprobe fuer DG CRM Druck/PDF vor Produktiv-FiBu.
> Token-sparsam: auf Laptop/Testinstanz ausfuehren, nur Auffaelligkeiten mit Screenshot/PDF an den Agenten geben.

## Rahmen

- Instanz: `ganz-soft.de` oder Master-Testinstanz nach Deploy.
- Browser nach Deploy: `Shift+Strg+R`.
- Vorab Firmendaten pruefen: Name, Anschrift, Steuernummer oder USt-IdNr., Registerangaben falls vorhanden, Bank.
- Kontakt fuer Testbelege: gespeicherter CRM-Kontakt mit Anschrift; bei B2B optional USt-IdNr.
- Belege als Kunden-PDF/Druckansicht pruefen, nicht nur Formular.

## Allgemeine Pflicht-/Prueffelder

| Feld | Erwartung |
| --- | --- |
| Aussteller | Firmenname und Anschrift sichtbar |
| Empfaenger | Kontaktname und Anschrift sichtbar |
| Dokumentart | Angebot, Auftragsbestaetigung, Rechnung, Schlussrechnung oder Gutschrift eindeutig |
| Nummer | Nummer sichtbar; bei Rechnungen/Gutschriften eindeutig und fortlaufend |
| Datum | Ausstellungsdatum sichtbar |
| Leistungs-/Lieferdatum | sichtbar, wenn vom Belegdatum abweichend; sonst fachlich plausibel |
| Positionen | Menge, Einheit, Beschreibung, Einzel-/Gesamtbetrag plausibel |
| Steuer | Netto/USt/Brutto je Steuersatz nachvollziehbar |
| Pflichtnummern | Steuernummer oder USt-IdNr. im Fuss/Kopf sichtbar |
| Zahlungsziel | bei buchbaren Rechnungen/Schlussrechnungen vorhanden |
| Klauseln | §19, §13b, PV §12 Abs. 3 oder §4 nur, wenn fachlich aktiviert |
| PDF-Kunde | interne Hinweise/Status nicht kundenseitig sichtbar |

## Testbelege

### 1. Angebot

- [x] Titel: `Angebot`
- [x] Angebotsnummer und Datum sichtbar
- [x] Gueltig-bis-Datum sichtbar
- [x] Hinweis: unverbindlich / keine Rechnung / keine UStVA-Wirkung
- [x] Positionen und Summen plausibel

Ergebnis: `OK`
Notiz: **PA1a 2026-11-08** · Instanz **`ganz-om.de` (Dev)** · Kunden-Druck-HTML · Firma Ganz-OM mit Steuernummer + USt-IdNr. · Gueltig-bis im Fuss · kein Zahlungsinfo-Block (nicht buchbar). Expliziter Satz „stellt keine Rechnung dar“ nur intern (`no-print`); kundenseitig reicht Titel Angebot + fehlende Zahlungsinfos.

### 2. Auftragsbestaetigung

- [x] Titel: `Auftragsbestaetigung`
- [x] Nummer und Datum sichtbar
- [x] Kunde und Aussteller korrekt
- [x] Keine Rechnungs-/UStVA-Wirkung behauptet
- [x] Optional Unterschriftsblock plausibel

Ergebnis: `OK`
Notiz: PA1a 2026-11-08 Dev · Titel/Meta/Positionen OK · optionaler Unterschriftsblock vorhanden.

### 3. Rechnung

- [x] Titel: `Rechnung`
- [x] Rechnungsnummer eindeutig sichtbar
- [x] Rechnungsdatum sichtbar
- [x] Leistungs-/Lieferdatum sichtbar oder plausibel aus Belegdatum
- [x] Steuernummer oder USt-IdNr. sichtbar
- [x] Netto, USt und Brutto je Steuersatz korrekt
- [x] Zahlungsinformationen und Zahlungsziel sichtbar

Ergebnis: `OK`
Notiz: PA1a 2026-11-08 Dev · Lieferdatum abweichend vom Belegdatum sichtbar · Steuerblock + Faelligkeit OK.

### 4. Schlussrechnung

- [x] Titel: `Schlussrechnung`
- [x] Eigene Rechnungsnummer und Datum sichtbar
- [x] Auftrags-/Kettenbezug plausibel
- [x] Anzahlungen/Abschlagsrechnungen werden abgezogen
- [x] Restbetrag, Steuer und Brutto nachvollziehbar
- [x] Zahlungsziel sichtbar

Ergebnis: `OK`
Notiz: PA1a 2026-11-08 Dev · Abschlag muss **nicht Entwurf** sein (`document_status` ≠ draft), sonst kein Abzug in der Kundenansicht (Produktlogik `partialInvoicesForFinal`). Mit versendetem Abschlag: Anzahlung + Restbetrag OK.

### 5. Kundengutschrift

- [x] Titel: `Gutschrift` oder eindeutig als Kundengutschrift erkennbar
- [x] Gutschriftsnummer und Datum sichtbar
- [x] Bezug zur korrigierten Rechnung/Leistung in Beschreibung oder Notiz
- [x] Steuerliche Behandlung entspricht der Ursprungskorrektur
- [x] Summen und Vorzeichen fachlich plausibel

Ergebnis: `OK`
Notiz: PA1a 2026-11-08 Dev · **Fix:** Drucktitel war zuvor generisch „Beleg“ (kein `document_kind` bei `voucher_type=credit`) → jetzt Belegart-Label **Kundengutschrift** (`VoucherDocumentPrintService`). Selftest: `bin/ustg14-print-title-selftest.php`.

## Sonderstichprobe gemischte Steuersaetze

Ein buchbarer Ausgangsbeleg mit mindestens:

- [ ] Position 19 %
- [ ] Position 7 %
- [ ] Position 0 %
- [ ] Steuerblock zeigt getrennte Summen je Steuersatz
- [ ] 0-%-Klausel passt zum Firmenflag: §19, §13b, PV §12 Abs. 3 oder §4

Ergebnis: `OK / Fehler:` — **offen → Mini-ID PA1b**
Notiz:

## Ablage der Nachweise

Je Beleg ein PDF/Screenshot sichern:

- `ustg14-angebot.pdf`
- `ustg14-auftragsbestaetigung.pdf`
- `ustg14-rechnung.pdf`
- `ustg14-schlussrechnung.pdf`
- `ustg14-gutschrift.pdf`
- `ustg14-gemischte-steuer.pdf`

**PA1a 2026-11-08:** Kunden-Druck-HTML unter Agent-Artifacts `pa1a-ustg14/ustg14-*.html` (+ `pa1a-report.json`). Dev-Belege mit Beschreibung „PA1a …“ auf `ganz-om.de` belassen (Stichproben-Nachweis).

Nur wenn ein Punkt fehlschlaegt: betreffendes PDF/Screenshot und kurze Fehlernotiz an den Agenten geben. Dann gezielt den Druckrenderer oder die Beleglogik korrigieren.
