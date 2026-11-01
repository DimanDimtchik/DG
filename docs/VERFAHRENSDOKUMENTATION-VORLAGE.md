# Verfahrensdokumentation — Vorlage (DG CRM FiBu)

> **Zweck:** GoBD-Verfahrensdokumentation für den Mandanten/Nutzer.  
> Hersteller liefert diese Vorlage; der Nutzer ergänzt betriebliche Besonderheiten (wer bucht, Backup-Verantwortung, Scanner-Pfad).  
> Stand Vorlage: 2026-11-01 · Produkt: DG CRM

---

## 1. Allgemeines

| Feld | Inhalt (anpassen) |
|------|-------------------|
| Unternehmen | _[Firma]_ |
| Software | DG CRM (Buchhaltung / Belege / Journal) |
| Betreiber / Hosting | _[z. B. Ganz Soft SaaS / Eigenbetrieb]_ |
| Verantwortliche Buchhaltung | _[Name, Rolle]_ |
| IT-/Admin-Verantwortlich | _[Name]_ |
| Kontenrahmen | SKR03 / SKR04 (in Einstellungen) |
| Gewinnermittlung | EÜR / Bilanz |
| USt-Versteuerung | Soll / Ist — siehe Firmeneinstellung + FA-Bescheid |

---

## 2. Systembeschreibung (Hersteller)

- **Architektur:** PHP-Webanwendung, MariaDB/MySQL je Instanz, Dateiablage unter `storage/` (Belege, Bescheide).
- **Mandanten:** Eine Domain/Instanz = ein Rechtsträger, eigene Datenbank; kein Sondercode pro Domain.
- **Module FiBu:** Belege (Ein-/Ausgang), Journal, OPOS, Bankabgleich, Kasse, UStVA, Auswertungen (BWA/SuSa), DATEV-/Kanzlei-Export, GoBD-Prüferexport, Jahresabschluss.
- **Rechte:** rollenbasiert (Admin / Buchhaltung / Lesen); Änderungen an Firmendaten und Versteuerung nur Admin.
- **Protokoll:** `AuditLog` für Statuswechsel, Revisionen, Stornos, Versteuerungswechsel, Prüferexporte.

---

## 3. Beleg- und Buchungsprozess

1. Beleg erfassen (Entwurf möglich) → speichern → Journalbuchung.
2. Ausgangsbelege nach Versand/Druck: Inhalt nicht überschreibbar; Korrektur = Storno + neuer Entwurf (`VoucherDocumentRevisionService`).
3. Eingangsbelege nach Buchung (kein Entwurf): ebenfalls GoBD-gesperrt; Korrektur = Storno (`voided_at`) + neuer Beleg.
4. Manuelle Journalbuchungen: Korrektur nur per **Storno mit Gegenbuchung**, kein stilles Löschen.
5. Zahlungen: Bank/Kasse/OPOS; bei Ist-Versteuerung USt-Realisation über Verrechnungskonto.
6. UStVA: Kennziffern aus Belegen/Zahlungen; Export CSV / später ERiC.
7. Jahresabschluss: Checkliste → Jahr sperren → Saldenvortrag.

---

## 4. Unveränderbarkeit & Nachvollziehbarkeit

| Vorgang | Verhalten |
|---------|-----------|
| Versendete Ausgangsrechnung ändern | Storno + neuer Beleg, Audit |
| Gebuchten Eingangsbeleg ändern | Storno + neuer Beleg, Audit |
| Manuelle Buchung rückgängig | Gegenbuchung, Original markiert storniert |
| FA-Bescheid Ist-Versteuerung | Ablage unter `storage/vat-taxation/`, Historie in DB |
| Prüferexport | ZIP mit Manifest-SHA256 |

---

## 5. Datensicherung & Aufbewahrung (Nutzer ausfüllen)

| Thema | Vorgabe / Ist |
|-------|----------------|
| Backup-Zyklus DB | _[täglich/…]_ |
| Backup Belegdateien | _[…]_ |
| Aufbewahrungsfrist | steuerlich i. d. R. 10 Jahre (AO) — _[interne Regelung]_ |
| Wiederherstellungstest | _[Datum letzter Test]_ |
| Löschkonzept | _[Verweis interne Richtlinie]_ |

Hosting-Kunden: AVV mit Anbieter abschließen; TOMs dokumentieren.

---

## 6. Zugriffs- und Änderungskontrolle

- Benutzerkonten persönlich, keine Sammel-Logins für Buchung.
- Passwort-/Session-Regeln laut CRM.
- Admin-Rechte nur an benannte Personen.

---

## 7. Schnittstellen

| Schnittstelle | Nutzung |
|---------------|---------|
| DATEV EXTF / Beleg-ZIP | Steuerberater-Übergabe |
| Agenda / Addison | CSV/DATEV-kompatibel |
| ELSTER | CSV manuell; ERiC nach Freigabe/Server |
| Bank | CAMT.053 / MT940 |
| GoBD-Prüferexport | Betriebsprüfung |

---

## 8. Organisatorische Regelungen (Nutzer)

- Wer darf Belege freigeben / stornieren?
- Scanner-/E-Mail-Eingang Belege?
- Abstimmung Soll-/Ist und FA-Bescheid?
- Vertretung bei Abwesenheit?

---

## 9. Versions- und Änderungsnachweis Software

Gesetzliche Änderungen (USt, Konten) werden über Produkt-Updates ausgeliefert. Nutzer: Changelog beachten und Stichproben nach Update.

---

## 10. Unterschriften

| Rolle | Name | Datum | Unterschrift |
|-------|------|-------|--------------|
| Geschäftsleitung | | | |
| Buchhaltungsverantwortlich | | | |
| IT | | | |
