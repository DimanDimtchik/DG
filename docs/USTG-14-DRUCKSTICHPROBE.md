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

- [x] Position 19 %
- [x] Position 7 %
- [x] Position 0 %
- [x] Steuerblock zeigt getrennte Summen je Steuersatz
- [x] 0-%-Klausel passt zum Firmenflag: §19, §13b, PV §12 Abs. 3 oder §4

Ergebnis: `OK`
Notiz: **PA1b 2026-11-08** · Dev `ganz-om.de` · Rechnung Nr. 4 (Beleg-ID 22) · Positionen 119 €/19 %, 107 €/7 %, 50 €/0 % · Steuerblock: `Umsatzsteuer 0 % —` · `zzgl. 7 % USt. 7,00 €` · `zzgl. 19 % USt. 19,00 €` · Gesamt 276,00 €. Firmenflags KU/PV **aus** → keine §19-/PV-Klausel (korrekt zum Flag). Artifact: `pa1b-ustg14/ustg14-gemischte-steuer.html`.

## Klauseln gegen Firma-Flags (PA1c)

Kurzmatrix Kunden-Druck (Dev `ganz-om.de`, Flags danach wieder **aus**):

| Fall | Erwartung | Ergebnis |
| --- | --- | --- |
| KU/PV aus, auch mit 0 %-Zeile | kein §19 / kein PV | OK |
| KU an | §19-Hinweis sichtbar | OK |
| PV an + 0 %-Zeile | PV §12 Abs. 3 sichtbar | OK |
| PV an, nur 19 % | kein PV-Hinweis | OK |
| KU+PV an + 0 % | §19, kein PV (Vorrang KU) | OK |
| §13b Bauleistung (Beleg Reverse Charge + Klausel) | §13b-Text im Druck; Suggest `reverse_charge_13b_construction` | OK |

Ergebnis: `OK`
Notiz: **PA1c 2026-11-08** · §19 und PV sind **Firmen-Auto-Hints** (`CompanyExtendedSettings`). §13b ist **Beleg-Klausel** (Vorschlag aus `reverse_charge_type`); Firmenhaken „§13b“ schaltet den Sonderfall in den Einstellungen, steuert den Drucktext nicht allein. Artifacts: `pa1c-klauseln/*.html` · `pa1c-report.json`. Beobachtung (kein Fix in PA1c): Firmen-PV-Standardtext enthält noch „umsatzsteuerbefreit“; Katalogtext (PA1d) spricht von 0 % — später redaktionell angleichen.

## Ablage der Nachweise

Je Beleg ein PDF/Screenshot sichern:

- `ustg14-angebot.pdf`
- `ustg14-auftragsbestaetigung.pdf`
- `ustg14-rechnung.pdf`
- `ustg14-schlussrechnung.pdf`
- `ustg14-gutschrift.pdf`
- `ustg14-gemischte-steuer.pdf`

**PA1a 2026-11-08:** Kunden-Druck-HTML unter Agent-Artifacts `pa1a-ustg14/ustg14-*.html` (+ `pa1a-report.json`). Dev-Belege mit Beschreibung „PA1a …“ auf `ganz-om.de` belassen (Stichproben-Nachweis).

**PA1b 2026-11-08:** `pa1b-ustg14/ustg14-gemischte-steuer.html` (+ `pa1b-report.json`). Dev-Beleg Beschreibung „PA1b gemischte Steuersätze 19/7/0“.

**PA1c 2026-11-08:** `pa1c-klauseln/` (Flags-off / KU / PV / §13b HTML + Report). Firmenflags nach Test wieder aus.

Nur wenn ein Punkt fehlschlaegt: betreffendes PDF/Screenshot und kurze Fehlernotiz an den Agenten geben. Dann gezielt den Druckrenderer oder die Beleglogik korrigieren.
