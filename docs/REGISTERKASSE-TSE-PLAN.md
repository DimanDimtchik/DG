# DG CRM — Registerkasse + TSE

> Token-armer, abhakbarer Plan.
> Ziel: Ladenkasse, mobile Kasse und Barverkauf aus Auftrag/Rechnung mit TSE, GoBD, DSFinV-K und FiBu-Anbindung.

Stand: **2026-11-05**

---

## Entscheidungen

| # | Entscheidung |
|---|--------------|
| D1 | Kassenarten: **Ladenkasse**, **mobile Kasse**, **Barverkauf aus Auftrag/Rechnung** |
| D2 | Zahlungsarten: **Bar**, **EC**, **Karte**, **Gutschein**, **gemischt** |
| D3 | Multi-Filiale und Multi-Kasse sind Kernstandard |
| D4 | Jede Kasse hat eigene Kassen-ID, eigenen Nummernkreis und eigene Tagesabschluesse |
| D5 | Cloud-TSE ist Standard; **fiskaly SIGN DE** ist bevorzugter Startanbieter |
| D6 | **Deutsche Fiskal / Fiskal Cloud** bleibt als spaeterer Cloud-Adapter moeglich |
| D7 | Bereits gekaufte gueltige USB-/Karten-TSEs bleiben moeglich, zuerst **Swissbit** via lokalen Connector |
| D8 | Keine eigene TSE-Krypto, keine Schein-Zertifizierung |
| D9 | Anbieter-Secrets nur pro Instanz/DB/Config, nie hart im Code |
| D10 | DSFinV-K, TSE-Ausfallprotokoll und Kassen-Nachschau von Anfang an mitdenken |

---

## Zielmodell

```text
Instanz
  -> Betriebsstaette / Filiale
      -> Kasse / Kassenterminal
          -> TSE-Zuordnung
          -> Bonnummernkreis
          -> Kassensitzung
          -> Tagesabschluss
```

---

## Anbieter-Bild

| Anbieter | Rolle | Preisbild netto | Bewertung |
|----------|-------|-----------------|-----------|
| fiskaly SIGN DE | Standard Cloud-TSE | ca. 9-14 EUR/Monat je TSE/Kasse ueber Partner | API-first, beste Startwahl |
| Deutsche Fiskal | Alternative Cloud-TSE | ca. 14,99 EUR/Monat je Kasse + teils Aktivierung | gut fuer Bestandswelt/JTL/Fiskal Cloud |
| Swissbit USB/microSD | Hardware-TSE | ca. 200-220 EUR einmalig, meist ca. 5 Jahre | fuer vorhandene gueltige Hardware |

Preise sind Richtwerte und vor Bestellung beim Anbieter/Partner zu bestaetigen.

---

## Architektur

```text
TseProviderInterface
  - startTransaction()
  - updateTransaction()
  - finishTransaction()
  - cancelTransaction()
  - exportData()
  - getStatus()

Provider:
  - MockTseProvider
  - FiskalyTseProvider
  - DeutscheFiskalProvider
  - SwissbitLocalProvider
```

Hardware-TSE braucht einen lokalen Dienst auf dem Kassen-PC/Mini-PC. Das CRM spricht diesen Dienst per API an; der Dienst spricht mit USB-/Karten-TSE und Treiber/SDK.

---

## Abhackplan

| ID | Aufgabe | Status |
|----|---------|--------|
| K0 | Anbieterzugang klaeren: fiskaly Testzugang, Preise, Vertrag, Limits — Detail: [`REGISTERKASSE-TSE-K0-FISKALY.md`](REGISTERKASSE-TSE-K0-FISKALY.md) | [ ] |
| K1 | Datenmodell fuer Filialen, Kassen, TSE-Zuordnungen, Bons, Zahlungen, Tagesabschluesse | [ ] |
| K2 | Mock-TSE bauen, damit Entwicklung ohne echten Anbieter laeuft | [ ] |
| K3 | `TseProviderInterface` und Provider-Auswahl pro Kasse | [ ] |
| K4 | Admin-UI: Filialen/Betriebsstaetten verwalten | [ ] |
| K5 | Admin-UI: Kassen verwalten, Nummernkreis, Standort, Aktivstatus | [ ] |
| K6 | Admin-UI: TSE zu Kasse zuordnen, Zertifikatsablauf und Status anzeigen | [ ] |
| K7 | Bonfluss: starten, Positionen, Zahlung, TSE beenden, Bon festschreiben | [ ] |
| K8 | Zahlungsarten: Bar, EC, Karte, Gutschein, gemischt | [ ] |
| K9 | Barverkauf aus Auftrag/Rechnung mit definierter Kasse | [ ] |
| K10 | Mobile Kasse mit eigener Kassen-ID und eigenem Nummernkreis | [ ] |
| K11 | Kassensitzung: Kasse oeffnen, Anfangsbestand, Bediener, Wechselgeld | [ ] |
| K12 | Tagesabschluss pro Kasse, optional Filialabschluss | [ ] |
| K13 | Storno/Korrektur nur mit Referenzbeleg, keine stille Bon-Aenderung | [ ] |
| K14 | TSE-Ausfall: Protokoll, Nachsignatur soweit Anbieter erlaubt, Warnung | [ ] |
| K15 | FiBu-Buchungen: Bar, EC/Karte, Gutschein, USt, Storno | [ ] |
| K16 | Kassenbuch/Journale: unveraenderbar, filterbar, exportierbar | [ ] |
| K17 | DSFinV-K Export pro Zeitraum, Filiale, Kasse | [ ] |
| K18 | TSE-/TAR-Export und Prueferpaket | [ ] |
| K19 | Kassenmeldung/ERS-Daten fuer steuerliche Meldung vorbereiten | [ ] |
| K20 | Tests: Nummernkreise, Mehrkassen, gemischte Zahlung, Storno, TSE-Ausfall | [ ] |
| K21 | Verfahrensdoku-Baustein fuer Registerkasse/TSE | [ ] |

---

## Datenmodell-Skizze

```text
cash_locations
cash_registers
cash_tse_assignments
cash_sessions
cash_receipts
cash_receipt_lines
cash_payments
cash_day_closings
cash_audit_log
cash_exports
```

Pflichtbezug pro Bon:

```text
location_id
register_id
tse_assignment_id
receipt_number
transaction_number
tse_serial
signature_counter
signature_value
started_at
finished_at
operator_id
payment_summary
vat_summary
```

---

## Nicht vergessen

- Kein Sondercode pro Kundeninstanz.
- Keine Kundendaten als Defaults im Kern.
- Fertige Bons nie bearbeiten, nur stornieren/korrigieren.
- Offline/Netzausfall sichtbar protokollieren.
- Belegausgabe: Druck, PDF, E-Mail, QR/Signaturdaten.
- Pruefansicht muss ohne Technikkenntnisse bedienbar sein.
