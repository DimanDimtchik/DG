# Lohnabrechnungs-Erweiterung — Konzept aus Acrobat-Dokument

> Quelle: Acrobat-Dokument `entwicklungsplan_lohnsoftware.pdf` aus <https://acrobat.adobe.com/id/urn:aaid:sc:EU:8bb9b7d3-555b-430f-84c7-502247d7e749>, abgerufen am 2026-10-27.  
> Status: fachliches Produktkonzept fuer DG CRM; keine Implementierung und keine Rechts-/Steuerfreigabe.

## Zielbild

DG CRM soll die bestehende Zeiterfassung und den Lohn-Export schrittweise zu einem eigenen Lohn- und Gehaltsmodul erweitern. Der Ausbau bleibt mandantenfaehig: gleicher Code fuer alle CRM-Instanzen, eigene Stammdaten, eigene Zertifikate und eigene Betriebsdaten je Instanz.

Die Reihenfolge bleibt konservativ:

1. **Normaler Lohn / Gehalt** auf Lexware-Office-Niveau.
2. **Automatischer SV-Versand** mit ITSG-Systemuntersuchung.
3. **Baulohn** als eigenes Zusatzmodul.
4. Weitere Sonderlohnformen nur als spaetere Produktoptionen.

## Architekturprinzip

Das PDF empfiehlt ein Strategy-Pattern fuer Berechnungs-Engines. Fuer DG CRM bedeutet das:

- **Kernsystem:** Mandant, Mitarbeiter, Abrechnungsmonat, Lohnarten, Freigabe, AuditLog, Fibu-Buchung.
- **Berechnungsstrategien:** Standardlohn, Minijob/Midijob, Geschaeftsfuehrer, spaeter Baulohn und Sonderbranchen.
- **Melde-Adapter:** ELSTER/ERiC fuer Lohnsteuer, ITSG/GKV fuer SV, ELStAM fuer Steuermerkmale, SEPA fuer Zahlungen.
- **Keine Instanz-Sonderlogik:** Bau, Gastronomie oder TVoeD werden ueber Mandanten-/Mitarbeiter-Flags und Lohnarten gesteuert.

## Stufe A — MVP Normal-Lohn

Ziel: DG CRM kann einfache deutsche Lohn- und Gehaltsabrechnungen erstellen, buchen und dokumentieren. SV-Meldungen duerfen in dieser Stufe noch ueber das SV-Meldeportal laufen.

### Stammdaten

- Arbeitgeber: Betriebsnummer, Steuernummer, Finanzamt, Bundesland, Berufsgenossenschaft, Umlagen U1/U2/U3, Bank.
- Mitarbeiter: Steuer-ID, Steuerklasse, Kinderfreibetraege, Konfession, Sozialversicherungsnummer, Krankenkasse, Beitragsgruppenschluessel, Personengruppe.
- Beschaeftigung: Vollzeit, Teilzeit, Minijob, Midijob, Geschaeftsfuehrer, Eintritt/Austritt, Arbeitszeitmodell.
- Lohnarten: Festgehalt, Stundenlohn, Sachbezug, geldwerter Vorteil, steuerfreie Erstattung, Zuschuss, Abzug.

### Abrechnung

- Monatslauf mit Entwurf, Plausibilitaetspruefung, Freigabe und revisionssicherer Sperre.
- Brutto-Netto-Berechnung fuer Lohnsteuer, Solidaritaetszuschlag, Kirchensteuer, KV, PV, RV, AV.
- Arbeitgeberanteile, Umlagen U1/U2/U3 und Pauschalabgaben fuer Minijob/Midijob.
- Uebernahme aus Zeiterfassung: Arbeitsstunden, Zuschlaege, Urlaub/Krankheit, Zeitkonto-Auszahlung.
- PDF-Abrechnung und Lohnkonto je Mitarbeiter.

### Ausgaben

- Fibu-Buchungssaetze ins Hauptbuch: Lohnaufwand, Arbeitgeberanteile, Verbindlichkeiten Finanzamt/SV, Auszahlung.
- SEPA-XML fuer Nettoauszahlungen.
- Lohnsteuer-Anmeldung per ELSTER/ERiC nach Abschluss der ERiC-Produktivstufe.
- Export-/Kontrolllisten fuer SV-Meldeportal als Uebergang.

## Stufe B — ITSG und direkter SV-Versand

Direkte SV-Meldungen sind erst nach ITSG-Systemuntersuchung produktiv. Ohne Zertifizierung duerfen Annahmestellen Meldungen ablehnen.

Notwendige Schritte:

1. Pflichtenheft und technische Systemdokumentation erstellen.
2. ITSG-Testaufgaben mathematisch korrekt loesen und XML-Meldestrukturen erzeugen.
3. Pilotbetrieb mit mindestens zwei Anwendern.
4. Prod-MOD-ID und kryptografische ITSG-Schluessel in Betrieb nehmen.
5. Jaehrliche Rezertifizierung bei Gesetzesaenderungen einplanen.

Meldungen in Stufe B:

- Beitragsnachweise.
- DEUEV-Meldungen.
- ELStAM-Abruf, soweit fuer Standardlohn produktiv noetig.
- Sofortmeldungen nur dort, wo Beschaeftigungsart/Branche das fordert.

## Stufe C — Baulohn / SOKA

Baulohn wird nicht vor Normal-Lohn begonnen. Das Modul muss fachlich getrennt bleiben, weil Bauhauptgewerbe und SOKA eigene Verfahren und Pruefpfade verlangen.

Kernanforderungen:

- SOKA-BAU-Beitraege, Urlaubsverfahren, Berufsbildung und Zusatzversorgung.
- Schlechtwetterlogik: Saison-KUG, Mehraufwands-Wintergeld, Zuschuss-Wintergeld.
- Bau-Urlaubskonto ueber Betriebsgrenzen hinweg.
- Mandanten-Flag `Baulohn aktiv` und getrennte Baulohn-Lohnarten.
- Klaerung, welche Baulohnmeldungen ueber ITSG, SOKA oder separate Schnittstellen laufen.

## Archiv spaeterer Sonderlohnformen

| Modul / Branche | Technische Kernanforderung | Komplexitaet |
| --- | --- | --- |
| Gastronomie / Pflege | SFN-Zuschlags-Engine | Steuer- und SV-Freiheitsgrenzen, minutengenaue Zeiten |
| Maler / Dachdecker | UK Malerkasse / Lohnausgleichskasse | SOKA-aehnlich, aber andere Umlagen und Verfahren |
| Oeffentlicher Dienst | TVoeD-Tarifmatrix, VBL | Stufenaufstiege, starre Tabellen, hoher Pflegeaufwand |
| Seefahrt / Heuer | Heuer-Steuer und Pauschalierung | Eigenstaendige Nischenformulare |
| Allgemeines KUG | Soll-/Ist-Entgelt nach BA-Tabellen | Pauschalierter Nettoausfall |

## Behoerden und Registrierungen

| Stelle | Zweck | Nachweis / Zugang |
| --- | --- | --- |
| ITSG GmbH / Trust Center | Lohnsoftware-Zertifizierung und SV-Datenaustausch | GKV-Zertifikat, Prod-MOD-ID, ITSG-Schluessel |
| Bundesagentur fuer Arbeit | Betriebsnummer, KUG-Tabellen | Betriebsnummer als Absender-/Arbeitgeberkennung |
| Finanzamt / ELSTER | Lohnsteuer-Anmeldung und Lohnsteuerbescheinigung | Organisationszertifikat fuer Serverzugriff |
| BZSt | ELStAM-Abruf | Zulassung als Datenuebermittler / Softwarehersteller |
| DRV | euBP | Elektronisch unterstuetzte Betriebspruefung |
| Berufsgenossenschaften / DGUV | Digitaler Lohnnachweis | Mitgliedsnummer, PIN, UV-Meldeverfahren |

## Pruef- und GoBD-Punkte

- Jede Abrechnung braucht Entwurf, Berechnungsergebnis, Freigabe, Sperre und Korrekturpfad.
- Nachtraegliche Aenderungen laufen ueber Storno/Korrekturabrechnung, nicht durch stilles Ueberschreiben.
- Beitragssaetze, Grenzwerte und Tabellen muessen versioniert werden.
- Jede Meldung an Finanzamt, GKV, BZSt, DRV oder BG wird mit Payload, Antwort, Zeitstempel und Benutzer protokolliert.
- Exportdateien und PDF-Abrechnungen muessen eindeutig an Mandant, Mitarbeiter und Abrechnungsperiode haengen.

## Offene Entscheidungen vor Implementierung

- Eigene Tabellenpflege fuer Steuer-/SV-Werte oder initial importierte Jahreswerte?
- Wie wird ein beherrschender Geschaeftsfuehrer sv-rechtlich geprueft und dokumentiert?
- Soll SEPA schon in Stufe A produktiv sein oder erst nach mehreren Testmonaten?
- Wie werden ELStAM-Aenderungen gegen manuell gepflegte Steuerdaten abgegrenzt?
- Welche Kanzlei-/Beraterexporte sind fuer Lohnkonto, SV-Pruefung und Jahreslohnkonto notwendig?
