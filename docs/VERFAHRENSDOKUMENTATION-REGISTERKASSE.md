# Verfahrensdokumentation — Baustein Registerkasse / TSE

> **Zweck:** Ergänzungsbaustein zur GoBD-/KassenSichV-Verfahrensdokumentation für Mandanten mit DG-CRM-Registerkasse.  
> Einfügen in bzw. anhängen an [`VERFAHRENSDOKUMENTATION-VORLAGE.md`](VERFAHRENSDOKUMENTATION-VORLAGE.md) (FiBu).  
> Hersteller liefert den Text; der Nutzer ergänzt betriebliche Besonderheiten (wer bedient, Filialen, Backup).  
> Stand Baustein: 2026-11-07 · Produkt: DG CRM Registerkasse  
> Download im CRM: Buchhaltung → Steuerberater-Export → Verfahrensdokumentation (auch im GoBD-Prüfer-ZIP).

---

## R1. Geltungsbereich

Dieser Baustein beschreibt das **elektronische Aufzeichnungssystem (Registerkasse)** inkl. Anbindung einer **Technischen Sicherheitseinrichtung (TSE)** und der damit verbundenen Aufzeichnungs-, Abschluss- und Exportprozesse.

| Feld | Inhalt (anpassen) |
|------|-------------------|
| Unternehmen | _[Firma — aus Instanz]_ |
| Software-Modul | DG CRM Registerkasse |
| Filialen / Betriebsstätten | _[Liste oder Verweis]_ |
| Anzahl Kassen | _[…]_ |
| TSE-Anbieter | _[z. B. Cloud-TSE Mock/Test · produktiv: Anbieter laut Vertrag]_ |
| Verantwortliche Kasse / Filiale | _[Name, Rolle]_ |
| IT-/Admin | _[Name]_ |

---

## R2. Systembeschreibung (Hersteller)

- **Architektur:** Teil der DG-CRM-Instanz (gleiche PHP-Anwendung, eigene Tabellen `dg_cash_*`); Daten nur in der jeweiligen Instanz-DB.
- **Struktur:** Filiale (Betriebsstätte) → Kasse (Terminal) → Kassensitzung → Bon (mit Positionen und Zahlungen) → optional Tagesabschluss.
- **Jede Kasse:** eigene Kassen-ID, eigener Bon-Nummernkreis, eigene Sitzungen und Tagesabschlüsse.
- **Zahlungsarten:** Bar, EC, Karte, Gutschein; gemischte Zahlung = mehrere Zahlungszeilen (keine Fantasie-Zahlart „gemischt“).
- **TSE:** Zuordnung Provider / TSE-ID / Client je Kasse. Signatur und QR laut Anbieter; bei Ausfall: erkennbares Ausfall-Kennzeichen und Protokoll — kein „heimlich signiert“.
- **FiBu-Anbindung:** festgeschriebene Bons können in die Finanzbuchhaltung gebucht werden (Kontenmapping in den Einstellungen). Das **Register-Bon-Journal** und das **FiBu-Kassenbuch** sind getrennte Nachweise.

---

## R3. Prozessablauf Kasse

1. Filiale und Kasse anlegen; TSE zuordnen (Test-Umgebung vor Produktivfreigabe).
2. Kassensitzung öffnen (Bediener, Anfangsbestand) — ohne offene Sitzung kein neuer Bon.
3. Bon starten → Positionen → Zahlungen → Bon festschreiben (mit TSE-Signatur, sobald Produktivpfad freigeschaltet; bis dahin dokumentierter Zwischenpfad ohne Live-TSE).
4. Korrektur nur als **Storno-Bon** mit Verweis auf das Original; Original bleibt unverändert.
5. Tagesabschluss je Kasse; danach keine neuen Bons für diesen Geschäftstag auf dieser Kasse.
6. Auswertungen / Export: Bon-Journal (CSV), Prüferpaket (DSFinV-K-Struktur + TSE-Status), ggf. FiBu-Exporte getrennt.

---

## R4. Unveränderbarkeit & Nachvollziehbarkeit

| Vorgang | Verhalten |
|---------|-----------|
| Festgeschriebenen Bon ändern | nicht möglich — nur Storno-Bon |
| Storno | neuer Bon, Typ Storno, Referenz auf Original; eigene Bonnummer |
| TSE-Ausfall | Kennzeichen am Bon bleibt historisch; aktueller Ausfallstatus an der Kassen-Zuordnung |
| Nummernkreis | lückenlos je Kasse (Vergabe atomar); mehrere Kassen = getrennte Kreise |
| Protokoll | Kassen-Audit (u. a. Sitzung, Finalize, Ausfall, Exporte) |

---

## R5. Datenträgerüberlassung / Prüfung

| Liefergegenstand | Inhalt (Soll) |
|------------------|---------------|
| DSFinV-K | vorgeschriebene CSV + Index laut Spec; Befüllung aus Kassendaten schrittweise |
| Prüferpaket | ZIP mit DSFinV-K, TSE-Export/Status, Manifest, optional Bon-Journal |
| Bon-Journal CSV | festgeschriebene Bons, inkl. Ausfall- und Storno-Kennzeichen |
| FiBu-GoBD-Export | getrennt (Buchhaltung), nicht Ersatz für Kassen-DSFinV |

Nutzer: Exporte bei Betriebsprüfung / Kassen-Nachschau bereithalten; Zeiträume und Kassenfilter dokumentieren.

---

## R6. Mitteilungspflicht § 146a Abs. 4 AO

Stammdaten für die Kassenmeldung (Steuerpflichtiger, Kassen-/ERS-Angaben) können im CRM erfasst werden.  
**Elektronische Übermittlung** an das Finanzamt (SUBMIT DE / ELSTER) erfolgt erst nach Freigabe des Meldewegs — bis dahin gilt die vom Steuerberater/Finanzamt vorgegebene Übergabe (z. B. ELSTER-Portal).

| Feld | Ist (anpassen) |
|------|----------------|
| Steuernummer (Bundesschema) | _[…]_ |
| Meldestatus | _[noch nicht gesendet / gesendet am …]_ |
| Verantwortliche Freigabe | _[…]_ |

---

## R7. Organisatorische Regelungen (Nutzer)

- Wer darf Sitzungen öffnen/schließen und Bons stornieren?
- Umgang mit TSE-Ausfall (Verkauf fortsetzen / stoppen laut Betriebsanweisung)?
- Vertretung und Schulung der Bediener?
- Abstimmung Register-Tagesabschluss mit FiBu-Kassenbuch?

---

## R8. Versions- und Änderungsnachweis

Kassensoftware und TSE-Anbindung werden über Produkt-Updates ausgeliefert. Nach Update: Stichprobe Bon → Journal → Export. Changelog beachten.

---

## R9. Unterschriften (Baustein)

| Rolle | Name | Datum | Unterschrift |
|-------|------|-------|--------------|
| Geschäftsleitung | | | |
| Kassenverantwortlich | | | |
| Buchhaltung | | | |
| IT | | | |

---

*Ende Baustein Registerkasse/TSE · Verweis Gesamt-Vorlage: [`VERFAHRENSDOKUMENTATION-VORLAGE.md`](VERFAHRENSDOKUMENTATION-VORLAGE.md)*
