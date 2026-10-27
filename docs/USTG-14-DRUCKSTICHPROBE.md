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

- [ ] Titel: `Angebot`
- [ ] Angebotsnummer und Datum sichtbar
- [ ] Gueltig-bis-Datum sichtbar
- [ ] Hinweis: unverbindlich / keine Rechnung / keine UStVA-Wirkung
- [ ] Positionen und Summen plausibel

Ergebnis: `OK / Fehler:`  
Notiz:

### 2. Auftragsbestaetigung

- [ ] Titel: `Auftragsbestaetigung`
- [ ] Nummer und Datum sichtbar
- [ ] Kunde und Aussteller korrekt
- [ ] Keine Rechnungs-/UStVA-Wirkung behauptet
- [ ] Optional Unterschriftsblock plausibel

Ergebnis: `OK / Fehler:`  
Notiz:

### 3. Rechnung

- [ ] Titel: `Rechnung`
- [ ] Rechnungsnummer eindeutig sichtbar
- [ ] Rechnungsdatum sichtbar
- [ ] Leistungs-/Lieferdatum sichtbar oder plausibel aus Belegdatum
- [ ] Steuernummer oder USt-IdNr. sichtbar
- [ ] Netto, USt und Brutto je Steuersatz korrekt
- [ ] Zahlungsinformationen und Zahlungsziel sichtbar

Ergebnis: `OK / Fehler:`  
Notiz:

### 4. Schlussrechnung

- [ ] Titel: `Schlussrechnung`
- [ ] Eigene Rechnungsnummer und Datum sichtbar
- [ ] Auftrags-/Kettenbezug plausibel
- [ ] Anzahlungen/Abschlagsrechnungen werden abgezogen
- [ ] Restbetrag, Steuer und Brutto nachvollziehbar
- [ ] Zahlungsziel sichtbar

Ergebnis: `OK / Fehler:`  
Notiz:

### 5. Kundengutschrift

- [ ] Titel: `Gutschrift` oder eindeutig als Kundengutschrift erkennbar
- [ ] Gutschriftsnummer und Datum sichtbar
- [ ] Bezug zur korrigierten Rechnung/Leistung in Beschreibung oder Notiz
- [ ] Steuerliche Behandlung entspricht der Ursprungskorrektur
- [ ] Summen und Vorzeichen fachlich plausibel

Ergebnis: `OK / Fehler:`  
Notiz:

## Sonderstichprobe gemischte Steuersaetze

Ein buchbarer Ausgangsbeleg mit mindestens:

- [ ] Position 19 %
- [ ] Position 7 %
- [ ] Position 0 %
- [ ] Steuerblock zeigt getrennte Summen je Steuersatz
- [ ] 0-%-Klausel passt zum Firmenflag: §19, §13b, PV §12 Abs. 3 oder §4

Ergebnis: `OK / Fehler:`  
Notiz:

## Ablage der Nachweise

Je Beleg ein PDF/Screenshot sichern:

- `ustg14-angebot.pdf`
- `ustg14-auftragsbestaetigung.pdf`
- `ustg14-rechnung.pdf`
- `ustg14-schlussrechnung.pdf`
- `ustg14-gutschrift.pdf`
- `ustg14-gemischte-steuer.pdf`

Nur wenn ein Punkt fehlschlaegt: betreffendes PDF/Screenshot und kurze Fehlernotiz an den Agenten geben. Dann gezielt den Druckrenderer oder die Beleglogik korrigieren.
