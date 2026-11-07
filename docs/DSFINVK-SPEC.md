# DSFinV-K — Spec Pflicht-Dateien und Kernfelder (K17a)

> Verbindlich für DG CRM Registerkasse.  
> Basis: **DSFinV-K Version 2.3** (BMF / DFKA-Taxonomie).  
> Umsetzung: Stub **K17b** · Befüllung **K17c** · Selftest Dateinamen **K17d**.  
> TSE-TAR / Prüferpaket gesamt: **K18** (getrennt).

---

## 1. Zweck

Die Digitale Schnittstelle der Finanzverwaltung für Kassensysteme (DSFinV-K) ist der standardisierte Prüfungseinstieg (§ 4 KassenSichV, GoBD-Datenträgerüberlassung).  
DG exportiert **kein eigenes Format**, sondern den vorgeschriebenen Datenkranz.

**Nicht verwechseln**

| Thema | Hier | Anderswo |
|-------|------|----------|
| DSFinV-K CSV + `index.xml` | K17 | — |
| TSE-TAR / Rohsignaturen | — | K18 |
| Bon-Journal CSV (intern) | — | K16b |
| FiBu-Kassenbuch | — | Buchhaltung / K15b |

---

## 2. Lieferform

| Bestandteil | Pflicht | Bemerkung |
|-------------|---------|-----------|
| ZIP oder TAR mit allen CSV | ja | Prüfer üblich: ZIP; manche Checker erwarten TAR — K17b entscheidet Liefercontainer, Inhalt gleich |
| `index.xml` | ja | GDPdU-Index: Dateinamen, Spalten, Typen, Beziehungen |
| `gdpdu-01-09-2004.dtd` (o. ä.) | ja | mit `index.xml` mitliefern |
| CSV-Dateien (englische Dateinamen) | ja | UTF-8, **Semikolon**, Text in Anführungszeichen, Dezimal **Punkt** laut Spec-Konvention; Datum/Zeit ISO 8601 mit Zeitzone |

Zusätzliche systemspezifische Spalten nur am **Zeilenende** und nur mit Eintrag in `index.xml`.

---

## 3. Pflicht-Dateien (20 CSV + Index)

Logischer Name (deutsch) · Dateiname (englisch). Leere Dateien mit nur Kopfzeile sind erlaubt, wenn das Modul fachlich nicht vorkommt (z. B. keine Agenturen) — die Datei muss trotzdem im Paket und in `index.xml` stehen.

### 3.1 Stammdatenmodul

| # | Logisch | Datei | Inhalt kurz | DG-Quelle (Soll K17c) |
|---|---------|-------|-------------|------------------------|
| 1 | Stamm_Abschluss | `cashpointclosing.csv` | Tagesabschluss je Kasse (Z-Nr., Von–Bis-Bon) | `dg_cash_register_day_closings` + Sessions |
| 2 | Stamm_Orte | `location.csv` | Betriebsstätte / Filiale | `dg_cash_locations` |
| 3 | Stamm_Kassen | `cashregister.csv` | Kassen-Stammdaten | `dg_cash_registers` |
| 4 | Stamm_Terminals | `slaves.csv` | Untergeräte / Terminals | oft leer |
| 5 | Stamm_Agenturen | `pa.csv` | Agentur / Durchlauf | oft leer |
| 6 | Stamm_USt | `vat.csv` | verwendete USt-IDs und Sätze | aus Bon-Positionen / festen Keys 1=19 %, 2=7 %, … |
| 7 | Stamm_TSE | `tse.csv` | TSE-Stammdaten | `dg_cash_tse_assignments` (+ Provider-Metadaten) |

### 3.2 Kassenabschlussmodul

| # | Logisch | Datei | Inhalt kurz | DG-Quelle |
|---|---------|-------|-------------|-----------|
| 8 | Z_GV_TYP | `businesscases.csv` | Summen je Geschäftsvorfalltyp im Abschluss | Aggregation finalized Bons |
| 9 | Z_Zahlart | `payment.csv` | Summen je Zahlart im Abschluss | `dg_cash_payments` aggregiert |
| 10 | Z_Waehrungen | `cash_per_currency.csv` | Bargeld je Währung | i. d. R. nur EUR |

### 3.3 Einzelaufzeichnungsmodul

| # | Logisch | Datei | Inhalt kurz | DG-Quelle |
|---|---------|-------|-------------|-----------|
| 11 | Bonkopf | `transactions.csv` | Bon-Kopf (elektronisches Rechnungsdoppel) | `dg_cash_receipts` finalized |
| 12 | Bonkopf_AbrKreis | `allocation_groups.csv` | Abrechnungskreise (Tisch o. ä.) | oft leer / optional |
| 13 | Bonkopf_USt | `transactions_vat.csv` | USt je Bon | `vat_summary_json` / Positionen |
| 14 | Bonkopf_Zahlarten | `datapayment.csv` | Zahlungen je Bon | `dg_cash_payments` |
| 15 | Bonpos | `lines.csv` | Positionen | `dg_cash_receipt_lines` |
| 16 | Bonpos_USt | `lines_vat.csv` | USt je Position | aus `tax_rate` + Beträgen |
| 17 | Bonpos_Preisfindung | `itemamounts.csv` | Preisfindung / Rabattzeilen | oft leer bis Rabattmodell |
| 18 | Bonpos_Zusatzinfo | `subitems.csv` | Unterpositionen / Menüs | oft leer |
| 19 | Bon_Referenzen | `references.csv` | Storno-/Referenzbezüge | `storno_of_receipt_id` |
| 20 | TSE_Transaktionen | `transactions_tse.csv` | TSE-Signatur je Bon | TSE-Felder am Receipt + Assignment |

**Index**

| Datei | Pflicht |
|-------|---------|
| `index.xml` | ja |
| DTD (z. B. `gdpdu-01-09-2004.dtd`) | ja |

---

## 4. Schlüssel und Kernfelder (verbindlich für Mapping)

Gemeinsame Schlüsselzüge (vereinfacht; exakte Spaltenliste = offizielle Spec + `index.xml`):

| Schlüssel | Bedeutung | DG-Abbildung |
|-----------|-----------|--------------|
| `Z_KASSE_ID` | Kassen-ID im Abschlusskontext | `dg_cash_registers.code` (stabil, K10b) |
| `Z_ERSTELLUNG` | Zeitpunkt Abschluss | `dg_cash_register_day_closings.closed_at` / Erstellung |
| `Z_NR` | Abschlussnummer | laufende Nr. je Kasse (ggf. aus Closing-`id` oder eigener Zähler — K17c festlegen) |
| `BON_ID` | Vorgangs-ID | `receipt_number_display` oder stabile ID je Kasse+Bon |
| `POS_ZEILE` | Positionszeile | `line_no` |
| `TSE_ID` | TSE-Referenz innerhalb Abschluss | Zuordnung aus `tse.csv` / Assignment |

### 4.1 `cashregister.csv` (Stamm_Kassen) — Kern

| Feld (Spec) | Pflicht-Kern | Quelle |
|-------------|--------------|--------|
| `Z_KASSE_ID` | ja | `registers.code` |
| Kassenname / Typ | ja | `name`, `register_type` |
| Seriennummer / Software | ja | Produktkennung DG + Instanz |
| Standort-Bezug | ja | `location_id` → `location.csv` |

### 4.2 `location.csv` (Stamm_Orte) — Kern

| Feld | Quelle |
|------|--------|
| Ort-ID / Name | `locations.code`, `name` |
| Adresse | `street`, `zip`, `city`, `country` |

### 4.3 `cashpointclosing.csv` (Stamm_Abschluss) — Kern

| Feld | Quelle |
|------|--------|
| `Z_KASSE_ID`, `Z_NR`, `Z_ERSTELLUNG` | Closing |
| `Z_START_ID` / `Z_ENDE_ID` | erste/letzte `BON_ID` des Tags |
| Summen Brutto / Bar | Closing `total_gross`, `total_cash`, … |

### 4.4 `transactions.csv` (Bonkopf) — Kern

| Feld | Quelle |
|------|--------|
| `Z_KASSE_ID`, `BON_ID` | Register + Bonnummer |
| Datum/Zeit Start–Ende | `started_at` / `finished_at` (mit TZ) |
| `BON_TYP` | `sale` → Beleg; `storno` → Storno-Beleg (Spec-Werte laut Anhang) |
| Brutto / Netto / USt | Receipt-Summen + VAT-Detail in `transactions_vat.csv` |
| Bediener | `operator_id` |
| Notiz | `notes` |

Werte im Bonkopf müssen dem **gedruckten Beleg** entsprechen (nicht „neu aufsummieren“ gegen Spec-Hinweis).

### 4.5 `lines.csv` / `lines_vat.csv` — Kern

| Feld | Quelle |
|------|--------|
| `BON_ID`, `POS_ZEILE` | Receipt + `line_no` |
| Bezeichnung, Menge, Preis | `title`, `quantity`, `unit_price_gross`, `gross_amount` |
| USt-Satz | `tax_rate` → `vat.csv`-ID |

### 4.6 `datapayment.csv` — Kern

| Feld | Quelle |
|------|--------|
| Zahlart | Mapping: `cash`→Bar, `ec`→EC, `card`→Kreditkarte, `voucher`→Gutschein (Z_ZAHLART_NAME / Typ laut Spec) |
| Betrag | `dg_cash_payments.amount` |
| Währung | `currency` (EUR) |

### 4.7 `references.csv` — Kern (Storno)

| Feld | Quelle |
|------|--------|
| `BON_ID` (Storno) | Storno-Bon |
| `REF_BON_ID` / `REF_Z_KASSE_ID` | Original über `storno_of_receipt_id` |

### 4.8 `transactions_tse.csv` + `tse.csv` — Kern

| Feld | Quelle |
|------|--------|
| TSE-Seriennummer, Signaturalgorithmus | Assignment / Receipt `tse_serial`, `signature_algorithm` |
| Transaktionsnummer, Signaturzähler, Signaturwert | `transaction_number`, `signature_counter`, `signature_value` |
| Start/Ende Unix | `time_start`, `time_end` |
| Ausfall | `tse_outage` / Assignment `outage_active` — historisch am Bon; Nachschau erkennbar (K14) |

Ohne TSE-Finish (nur `finalizeWithoutTse`) sind TSE-Felder leer/unvollständig — Export darf fehlende Signatur **nicht** erfinden (K14c).

### 4.9 `vat.csv` — Kern

Mindestens die im Zeitraum vorkommenden Sätze (typisch 19 %, 7 %, 0 %) mit stabiler USt-ID.

---

## 5. Inhaltliche Regeln (Prüfer)

1. **Unveränderlichkeit:** nur `status=finalized` (und zugehörige Storno-Bons); keine Drafts.
2. **Lückenlose Bonnummern** je Kasse im Abschluss-Intervall (App-Regel K5/K10; Export prüft/meldet).
3. **Jeder Tag mit Vorgängen** braucht passende `cashpointclosing.csv`-Zeile (Register-Tagesabschluss K12).
4. **Storno** nur als neuer Bon + `references.csv`, Original unverändert (K13).
5. **Gemischte Zahlung** = mehrere Zeilen in `datapayment.csv`, kein Fantasie-`mixed`.
6. **index.xml** muss jede gelieferte Datei und jedes Extra-Feld beschreiben.

---

## 6. Abgrenzung Umsetzung

| Mini-ID | Was |
|---------|-----|
| **K17a** | dieses Spec-Doc |
| **K17b** | ✅ Stub: ZIP mit Minimal-CSV + Kopfzeilen + `index.xml` + DTD (`CashDsfinvkStubExport`) |
| **K17c** | Befüllung aus `dg_cash_*` |
| **K17d** | ✅ Selftest Dateinamen: `bin/cash-dsfinvk-zip-names-selftest.php` |
| **K18** | TSE-TAR / Prüferpaket (kann DSFinV-K + TAR bündeln) |

**Nicht tun**

- Eigenes „DSFinV-ähnlich“-CSV erfinden
- Signaturen oder QR-Payloads faken
- FiBu-Ledger als Ersatz für DSFinV-K ausgeben
- Kunden-/Personenstammdaten unnötig in den Pflichtkranz schreiben

---

## 7. Referenz

- Offizielle Spec: DSFinV-K Vers. 2.3 (BMF), Dateinamen laut DFKA-Taxonomie (englische CSV-Namen oben).  
- GoBD: ergänzende Informationen zur Datenträgerüberlassung (`index.xml`).  
- Interne Tabellen: [`REGISTERKASSE-TSE-PLAN.md`](REGISTERKASSE-TSE-PLAN.md) § K1.
