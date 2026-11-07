# Registerkasse — Smoke-Checkliste (1 Seite)

> **Instanz:** Dev `ganz-om.de` (oder freigegebene Test-Instanz) · nach Deploy Hard-Reload  
> **Rolle:** Buchhaltung/Admin · **Umgebung TSE:** nur **test** / Mock — kein Live-SUBMIT  
> **Selbsttests (CLI):** `php bin/cash-*-selftest.php` — ergänzen, ersetzen diese Liste nicht.

Datum: ________  Tester: ________  Build/Branch: ________

---

## A · Stammdaten

| # | Schritt | OK |
|---|---------|----|
| A1 | Filiale anlegen (Adresse) · Menü Registerkasse → Filialen | ☐ |
| A2 | Zwei Kassen an derselben Filiale (unterschiedliche Kassen-ID + Nummernkreis-Start) | ☐ |
| A3 | TSE-Zuordnung je Kasse: Provider **mock**, Umgebung **test** · Speichern | ☐ |
| A4 | Optional: „Verbindung prüfen“ (nur test) zeigt Status | ☐ |

## B · Sitzung & Bon

| # | Schritt | OK |
|---|---------|----|
| B1 | Sitzung öffnen (Anfangsbestand) · ohne Sitzung kein neuer Bon | ☐ |
| B2 | Bon starten · Position erfassen · Status Entwurf | ☐ |
| B3 | Zahlung Bar (Teil) → Status zahlend · weitere Position blockiert | ☐ |
| B4 | Restzahlung EC/Karte/Gutschein gemischt · Summe = Brutto | ☐ |
| B5 | Bon festschreiben (ohne TSE-Live ok) · erscheint im Bon-Journal | ☐ |
| B6 | Zweite Kasse: eigene Bonnummer (darf bei 1 starten, Kreis getrennt) | ☐ |

## C · Storno & Ausfall

| # | Schritt | OK |
|---|---------|----|
| C1 | Festgeschriebenen Verkauf stornieren · Original unverändert · neuer Storno-Bon mit Referenz | ☐ |
| C2 | Doppel-Storno / Storno eines Stornos abgewiesen | ☐ |
| C3 | TSE-Ausfall melden (falls UI) oder Flag prüfen · Banner/Warnung sichtbar | ☐ |
| C4 | Nach Beheben: Ausfall an Kasse weg · **Bon behält** historisches Ausfall-Kennzeichen | ☐ |

## D · Abschluss & Export

| # | Schritt | OK |
|---|---------|----|
| D1 | Tagesabschluss je Kasse · danach kein neuer Bon für diesen Tag | ☐ |
| D2 | Bon-Journal: Filter Tag/Kasse · CSV-Zeitraum herunterladen | ☐ |
| D3 | DSFinV-K Stub-ZIP öffnen (Köpfe vorhanden) | ☐ |
| D4 | Prüferpaket-ZIP: Ordner `dsfinvk/`, `tse/EXPORT_STATUS.txt`, Journal, Manifest | ☐ |
| D5 | Hinweis FiBu-Kassenbuch vs. Register-Journal verständlich | ☐ |

## E · Kassenmeldung (ohne Senden)

| # | Schritt | OK |
|---|---------|----|
| E1 | Kassenmeldung: Steuernummer speichern · kein Versand | ☐ |
| E2 | Meldefelder einer Kasse speichern · Stand „Kernfelder“/unvollständig plausibel | ☐ |

## F · Negativ / Guard

| # | Schritt | OK |
|---|---------|----|
| F1 | Überzahlung abgewiesen | ☐ |
| F2 | Finish bei offenem Rest abgewiesen | ☐ |
| F3 | Inaktive Kasse / geschlossener Tag: kein neuer Bon | ☐ |

---

**Ergebnis:** ☐ bestanden · ☐ mit Mängeln (Notiz unten) · ☐ blockiert  

Notiz: _________________________________________________________________

Selftests Kurz: `cash-multi-register-numbers` · `cash-mixed-payment` · `cash-storno-reference` · `cash-tse-outage-flag` · `cash-prueferpaket` · `cash-ers-melde`
