# DG CRM — Registerkasse + TSE

> Token-armer, abhakbarer Plan. **Eine Chat-Session = eine Mini-ID.**
> Ziel: Ladenkasse, mobile Kasse und Barverkauf aus Auftrag/Rechnung mit TSE, GoBD, DSFinV-K und FiBu-Anbindung.

Stand: **2026-11-07** · Detail K0: [`REGISTERKASSE-TSE-K0-FISKALY.md`](REGISTERKASSE-TSE-K0-FISKALY.md)

---

## Token-Regel (verbindlich)

| Symbol | Bedeutung | Wann nutzen |
|--------|-----------|-------------|
| 🟢 | **Lokal / billig** — Spec, Migration, CRUD, Selftest, Docs; oft ohne teuren Chat möglich | Standard |
| 🟡 | **Kurz-Agent** — 1 Datei / 1 Hook / 1 Guard; Prompt = nur Mini-ID + Dateipfad | Sparsam |
| 🔴 | **Teuer** — Fiskaly-Live, Storno/GoBD-Rand, DSFinV, Review über viele Dateien | Nur wenn 🟢/🟡 vorbereitet |

**Chat-Prompt-Vorlage:**  
`Nur Mini-ID K1a laut docs/REGISTERKASSE-TSE-PLAN.md. Keine anderen Dateien. Am Ende Selftest/Guard nennen.`

**Nicht im teuren Chat:** gesamtes Modul erkunden, Plan neu erfinden, UI-Polishing ohne ID, parallele Mini-IDs.

**Bereits vorhanden (nicht neu bauen):** Branch `cursor/tse-kassenbeleg-1549` — Barzahlung auf Rechnung → Fiskaly SIGN DE + QR (`TseSignService`, `dg_tse_transactions`, Einstellungen → TSE). Das ist **Vorläufer zu K9**, keine volle Registerkasse.

---

## Entscheidungen (fest)

| # | Entscheidung |
|---|--------------|
| D1 | Kassenarten: **Ladenkasse**, **mobile Kasse**, **Barverkauf aus Auftrag/Rechnung** |
| D2 | Zahlungsarten: **Bar**, **EC**, **Karte**, **Gutschein**, **gemischt** |
| D3 | Multi-Filiale und Multi-Kasse sind Kernstandard |
| D4 | Jede Kasse: eigene Kassen-ID, eigener Nummernkreis, eigene Tagesabschlüsse |
| D5 | Cloud-TSE Standard; **fiskaly SIGN DE** Startanbieter |
| D6 | Deutsche Fiskal später als zweiter Cloud-Adapter möglich |
| D7 | Vorhandene USB-/Karten-TSE: zuerst **Swissbit** via lokalem Connector |
| D8 | Keine eigene TSE-Krypto, keine Schein-Zertifizierung |
| D9 | Secrets nur Instanz/DB/Config, nie im Code |
| D10 | DSFinV-K, Ausfallprotokoll, Kassen-Nachschau von Anfang an mitdenken |

---

## Zielmodell

```text
Instanz
  -> Betriebsstätte / Filiale
      -> Kasse / Kassenterminal
          -> TSE-Zuordnung
          -> Bonnummernkreis
          -> Kassensitzung
          -> Tagesabschluss
```

---

## Abhackplan (Mini-IDs)

Status: `[ ]` offen · `[~]` angefangen · `[x]` erledigt

### K0 — Anbieterzugang fiskaly

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K0a | HUB-Account + Test-Organisation | 🟢 | [x] |
| K0b | TEST API-Key/Secret sichern (nicht Git) | 🟢 | [x] |
| K0c | Cloud-Agent Secrets `FISKALY_SIGN_DE_API_KEY` / `_SECRET` | 🟢 | [x] |
| K0d | `bin/fiskaly-auth-smoke.php` → TEST-Auth OK | 🟡 | [x] |
| K0e | Test-TSS anlegen + `INITIALIZED` | 🟡 | [x] |
| K0f | Test-Client (1 Kasse) anlegen | 🟡 | [x] |
| K0g | 1 Test-Transaktion ACTIVE→FINISHED (Signatur+QR-Daten) | 🟡 | [x] |
| K0h | Notiz Abrechnungsmodell + Sales-Ansprechpartner | 🟢 | [~] Variante 2 Ziel; Sales nach Go Live |
| K0i | Grobe Endkunden-Preisempfehlung (getrennt von Einkauf) | 🟢 | [x] 14,90 €/Kasse (Hypothese) |
| K0j | Rate-Limits / Token-Cache in K0-Doc festhalten | 🟢 | [x] |

→ Details/Checkboxen: [`REGISTERKASSE-TSE-K0-FISKALY.md`](REGISTERKASSE-TSE-K0-FISKALY.md)

---

### K1 — Datenmodell

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K1a | Spec-Tabelle: Spalten `cash_locations` (Doc-Abschnitt unten) | 🟢 | [x] |
| K1b | Migration `cash_locations` + MigrationRunner | 🟡 | [x] `143_cash_locations.sql` |
| K1c | Spec + Migration `cash_registers` (FK location, Nummernkreis) | 🟡 | [x] `144_cash_registers.sql` |
| K1d | Spec + Migration `cash_tse_assignments` | 🟡 | [x] `145_cash_tse_assignments.sql` |
| K1e | Spec + Migration `cash_sessions` | 🟡 | [x] `146_cash_sessions.sql` |
| K1f | Spec + Migration `cash_receipts` + `cash_receipt_lines` | 🟡 | [x] `147_cash_receipts.sql` |
| K1g | Spec + Migration `cash_payments` | 🟡 | [x] `148_cash_payments.sql` |
| K1h | Spec + Migration Tagesabschluss Register (`dg_cash_register_day_closings`) | 🟡 | [x] `149_…` |
| K1i | Spec + Migration `cash_audit_log` + `cash_exports` | 🟡 | [x] `150_cash_audit_exports.sql` |
| K1j | Repository-Stubs (nur CRUD-Gerüst, keine Business-Logik) | 🟡 | [x] `src/CashRegister/*` |
| K1k | Schema-Selftest (`php bin/…` zeigt Tabellen/Spalten) | 🟡 | [x] `bin/cash-register-schema-selftest.php` |

**Hinweis:** `dg_tse_transactions` (Beleg-Barzahlung) bleibt; neues Modell ist parallel und wird später verknüpft (K9).

---

### K2 — Mock-TSE

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K2a | Interface-Skizze in Doc (Methoden, Return-Felder) | 🟢 | [x] siehe § K2 Specs |
| K2b | `MockTseProvider` start/update/finish/cancel (feste Testwerte) | 🟡 | [x] `MockTseProvider.php` |
| K2c | Selftest Mock ohne Netz | 🟡 | [x] `bin/mock-tse-selftest.php` |

---

### K3 — Provider-Schicht

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K3a | `TseProviderInterface` PHP (leer + Signaturen) | 🟡 | [x] `TseProviderInterface.php` |
| K3b | `FiskalyTseProvider` aus bestehendem `TseSignDeClient` ausdünnen/wickeln | 🔴 | [ ] |
| K3c | Provider-Factory: Auswahl mock|fiskaly (Setting/Kasse) | 🟡 | [x] `TseProviderFactory` |
| K3d | Selftest Factory + Mock-Pfad | 🟡 | [x] `bin/tse-provider-factory-selftest.php` |

---

### K4 — Admin Filialen

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K4a | Menüpunkt + leere Listen-Seite (UI-Katalog) | 🟡 | [x] `registerkasse-filialen` |
| K4b | Formular anlegen/bearbeiten (Name, Adresse, aktiv) | 🟡 | [x] `registerkasse-filiale-form` |
| K4c | Speichern POST + Flash + CSRF | 🟡 | [x] |
| K4d | Liste mit aktiv/inaktiv-Filter | 🟡 | [x] |

---

### K5 — Admin Kassen

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K5a | Listen-Seite je Filiale | 🟡 | [x] `registerkasse-kassen` |
| K5b | Formular: Kassen-ID, Nummernkreis-Start, Standort, aktiv | 🟡 | [x] `registerkasse-kasse-form` |
| K5c | Speichern + eindeutige Kassen-ID je Instanz prüfen | 🟡 | [x] |
| K5d | Selftest Nummernkreis-Vergabe (nächste Bonnummer) | 🟡 | [x] `allocateNextReceiptNumber` |

---

### K6 — Admin TSE-Zuordnung

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K6a | UI: Kasse ↔ Provider/TSS/Client (ohne Live-Call) | 🟡 | [x] `registerkasse-tse` |
| K6b | Status-Felder anzeigen (last_ok, Zertifikat-Ablauf wenn bekannt) | 🟡 | [x] |
| K6c | Button „Verbindung prüfen“ (nur TEST, kurz) | 🔴 | [x] `CashTseConnectionCheck` |
| K6d | Bestehenden Tab Einstellungen→TSE als Übergang belassen oder verlinken | 🟢 | [x] beidseitig verlinkt |

---

### K7 — Bonfluss (Kern)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K7a | Bon starten (Draft, register_id, operator) | 🟡 | [x] `CashReceiptService::startDraft` |
| K7b | Position hinzufügen/ändern nur solange Draft | 🟡 | [x] add/update/removeLine |
| K7c | Zahlung erfassen → Bon „zahlend“ | 🟡 | [x] status=`paying` |
| K7d | TSE finish + Bon festschreiben (immutable) | 🔴 | [ ] |
| K7e | Bon-Druck/PDF-Stub (Pflichtfelder + Platz QR) | 🟡 | [ ] |
| K7f | Selftest: Draft→Position→Mock-TSE→fest | 🟡 | [ ] |

---

### K8 — Zahlungsarten

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K8a | Bar | 🟡 | [ ] |
| K8b | EC / Karte (ohne Terminal-Integration zuerst: Betrag+Art) | 🟡 | [ ] |
| K8c | Gutschein (Anbindung vorhandenes Gift-Voucher wenn möglich) | 🟡 | [ ] |
| K8d | Gemischt: Summe = Bon brutto, Rest offen blockiert Finish | 🟡 | [ ] |
| K8e | Selftest gemischte Zahlung | 🟡 | [ ] |

---

### K9 — Barverkauf aus Auftrag/Rechnung

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K9a | Ist-Stand dokumentieren (Beleg-Barzahlung + `dg_tse_transactions`) | 🟢 | [~] |
| K9b | Beleg-Barzahlung an `cash_register` koppeln (welche Kasse) | 🟡 | [ ] |
| K9c | Optional: aus Auftrag Bon erzeugen (gleiche Provider-Pipeline) | 🔴 | [ ] |
| K9d | Doppelte Signatur vermeiden (ein Zahlungsweg = eine Tx) | 🔴 | [ ] |

---

### K10 — Mobile Kasse

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K10a | Kasse-Typ `mobile` im Modell/UI | 🟡 | [ ] |
| K10b | Eigene Kassen-ID + Nummernkreis erzwingen (Selftest) | 🟡 | [ ] |
| K10c | Responsive Bon-UI (nur Layout, Katalog) | 🟡 | [ ] |

---

### K11 — Kassensitzung

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K11a | Kasse öffnen: Bediener, Anfangsbestand | 🟡 | [ ] |
| K11b | Ohne offene Sitzung kein Bon (Guard) | 🟡 | [ ] |
| K11c | Sitzung schließen (Zählung optional später) | 🟡 | [ ] |

---

### K12 — Tagesabschluss

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K12a | Abschluss pro Kasse (Summen Bar/EC/Karte/Gutschein) | 🟡 | [ ] |
| K12b | Nach Abschluss keine neuen Bons auf dem Tag (Regel) | 🟡 | [ ] |
| K12c | Optional Filialabschluss = Summe Kassen | 🟡 | [ ] |
| K12d | Abgrenzung FiBu-`dg_cash_day_closings` dokumentieren | 🟢 | [x] siehe K1h |

---

### K13 — Storno / Korrektur

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K13a | Storno-Bon mit Referenz auf Original (kein Edit) | 🔴 | [ ] |
| K13b | TSE cancel/finish Storno laut Provider | 🔴 | [ ] |
| K13c | Selftest: Original unverändert, Storno verknüpft | 🟡 | [ ] |

---

### K14 — TSE-Ausfall

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K14a | Ausfall-Flag + Audit-Log-Eintrag | 🟡 | [ ] |
| K14b | UI-Warnung sichtbar | 🟡 | [ ] |
| K14c | Nachsignatur-Pfad nur wenn Anbieter erlaubt (Spec zuerst) | 🟢 | [ ] |
| K14d | Implementierung Nachsignatur | 🔴 | [ ] |

---

### K15 — FiBu-Buchungen

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K15a | Mapping Zahlart → Konten (Settings/Doc) | 🟢 | [ ] |
| K15b | Buchung Bar bei Bon-Finish | 🔴 | [ ] |
| K15c | Buchung EC/Karte | 🟡 | [ ] |
| K15d | Buchung Gutschein | 🟡 | [ ] |
| K15e | Storno-Buchung | 🔴 | [ ] |

---

### K16 — Journale

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K16a | Unveränderbare Bon-Liste (Filter Kasse/Tag) | 🟡 | [ ] |
| K16b | Export CSV Zeitraum | 🟡 | [ ] |
| K16c | Abgleich/Anzeige vs. FiBu-Kassenbuch (nur Link/Hinweis) | 🟢 | [ ] |

---

### K17 — DSFinV-K

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K17a | Pflicht-Dateien/Felder als Spec-Doc | 🟢 | [ ] |
| K17b | Export-Stub (leere/Minimal-CSV-Struktur) | 🟡 | [ ] |
| K17c | Befüllung aus cash_*-Tabellen | 🔴 | [ ] |
| K17d | Selftest: ZIP enthält erwartete Dateinamen | 🟡 | [ ] |

---

### K18 — TSE-/TAR-Export + Prüferpaket

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K18a | Spec Inhalt Prüferpaket | 🟢 | [ ] |
| K18b | TAR/Export über Provider-API | 🔴 | [ ] |
| K18c | Paket-Download in Admin-UI | 🟡 | [ ] |

---

### K19 — Kassenmeldung / ERS

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K19a | Felder für §146a-Meldung speichern (ohne Senden) | 🟡 | [ ] |
| K19b | Spec SUBMIT DE / Meldeweg | 🟢 | [ ] |
| K19c | Senden erst nach Hetzner/Freigabe (wie ELSTER) | 🔴 | [ ] |

---

### K20 — Tests

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K20a | Nummernkreise Mehrkassen | 🟡 | [ ] |
| K20b | Gemischte Zahlung | 🟡 | [ ] |
| K20c | Storno-Referenz | 🟡 | [ ] |
| K20d | TSE-Ausfall-Flag | 🟡 | [ ] |
| K20e | Manuelle Smoke-Checkliste (1 Seite) | 🟢 | [ ] |

---

### K21 — Verfahrensdoku

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| K21a | Baustein-Text Registerkasse/TSE (Vorlage) | 🟢 | [ ] |
| K21b | Einbindung in Verfahrensdoku-Modul/Export | 🟡 | [ ] |

---

## Empfohlene Reihenfolge (nächste 10 Mini-Schritte)

1. **K7d** TSE finish + festschreiben (🔴) oder **K9a** Ist-Stand Beleg-TSE
2. *(nebenher, ohne Agent)* K0h Sales-Zeile nach HUB Go Live
3. **K3b** FiskalyTseProvider (🔴) — für echte Ping-Prüfung gegen fiskaly
4. **K4a–K4c** — Filial-Admin (je einzeln)  
5. **K9a** — Ist-Stand Beleg-TSE festhalten  
6. **K5** — Admin Kassen  
7. **K6** — Admin TSE-Zuordnung  
8. **K1** Deploy: Migrationen 143–150 auf Dev  
9. **K3b** — Fiskaly-Wrapper (🔴)  
10. **K7a** — Bon starten (Draft)

Erst danach **K3b** (🔴 Fiskaly-Wrapper) und Bonfluss **K7**.

---

## Datenmodell-Skizze (Ziel)

```text
dg_cash_locations
dg_cash_registers
dg_cash_tse_assignments
dg_cash_sessions
dg_cash_receipts
dg_cash_receipt_lines
dg_cash_payments
dg_cash_register_day_closings  -- Registerkasse (K1h); FiBu-Kassenbuch bleibt dg_cash_day_closings (054)
dg_cash_audit_log
dg_cash_exports
```

**Abgrenzung:** `dg_stock_locations` = Lagerorte. `dg_cash_locations` = Betriebsstätten/Filialen der Registerkasse. Nicht zusammenlegen.

Pflichtbezug pro Bon:

```text
location_id, register_id, tse_assignment_id,
receipt_number, transaction_number,
tse_serial, signature_counter, signature_value,
started_at, finished_at, operator_id,
payment_summary, vat_summary
```

---

## K1 Specs (Tabellen)

### K1a — `dg_cash_locations` (2026-11-07)

Betriebsstätte / Filiale. **1 TSS pro Standort** (Zuordnung in `dg_cash_tse_assignments`, nicht hier). Keine Kundendaten als Seed-Defaults.

| Spalte | Typ | Null | Default | Bedeutung |
|--------|-----|------|---------|-----------|
| `id` | INT UNSIGNED AI | PK | — | Intern |
| `code` | VARCHAR(32) | NOT NULL | — | Kurzkürzel je Instanz, eindeutig (z. B. `HAUPT`, `FIL-02`) |
| `name` | VARCHAR(255) | NOT NULL | `''` | Anzeigename (Filiale / Betriebsstätte) |
| `street` | VARCHAR(255) | NOT NULL | `''` | Straße + Nr. (DSFinV/ERS später) |
| `zip` | VARCHAR(16) | NOT NULL | `''` | PLZ |
| `city` | VARCHAR(128) | NOT NULL | `''` | Ort |
| `country` | CHAR(2) | NOT NULL | `'DE'` | ISO-3166-1 alpha-2 |
| `notes` | VARCHAR(500) | NOT NULL | `''` | Interne Notiz |
| `is_active` | TINYINT(1) | NOT NULL | `1` | 1 = nutzbar für neue Kassen/Bons |
| `sort_order` | INT | NOT NULL | `0` | UI-Reihenfolge |
| `created_at` | DATETIME | NOT NULL | CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | NOT NULL | CURRENT_TIMESTAMP ON UPDATE | |

**Keys**

| Key | Spalten | Art |
|-----|---------|-----|
| PRIMARY | `id` | |
| `uq_cash_location_code` | `code` | UNIQUE |
| `idx_cash_location_active` | `is_active`, `sort_order` | |

**Nicht in dieser Tabelle**

- TSE-/TSS-IDs → `dg_cash_tse_assignments` (K1d)
- Kassen / Nummernkreise → `dg_cash_registers` (K1c)
- Soft-Delete: kein `deleted_at`; Deaktivieren über `is_active=0` (GoBD: Stammdaten nachvollziehbar behalten)

**K1b** setzt genau diese Spec als Migration `dg_cash_locations` + MigrationRunner-Eintrag um — nichts erweitern ohne neue Mini-ID.

### K1c — `dg_cash_registers` (2026-11-07)

Eine Kasse / ein Terminal an einer Betriebsstätte. Eigene **Kassen-ID** (`code`) und eigener **Bon-Nummernkreis**. TSE-Client-Zuordnung → K1d.

| Spalte | Typ | Null | Default | Bedeutung |
|--------|-----|------|---------|-----------|
| `id` | INT UNSIGNED AI | PK | — | Intern |
| `location_id` | INT UNSIGNED | NOT NULL | — | FK → `dg_cash_locations.id` |
| `code` | VARCHAR(32) | NOT NULL | — | Kassen-ID je Instanz, eindeutig (auch fiskaly `serial_number`-Kandidat) |
| `name` | VARCHAR(255) | NOT NULL | `''` | Anzeigename |
| `register_type` | VARCHAR(16) | NOT NULL | `'store'` | `store` = Ladenkasse · `mobile` = mobile Kasse · `counter` = Schalter/Barverkauf-Arbeitsplatz |
| `next_receipt_number` | INT UNSIGNED | NOT NULL | `1` | Nächste zu vergebende Bonnummer (Vergabe atomar in K5d/K7) |
| `receipt_number_prefix` | VARCHAR(16) | NOT NULL | `''` | Optionaler Präfix vor der Nummer (leer = nur Zahl) |
| `notes` | VARCHAR(500) | NOT NULL | `''` | Interne Notiz |
| `is_active` | TINYINT(1) | NOT NULL | `1` | 1 = nutzbar |
| `sort_order` | INT | NOT NULL | `0` | UI-Reihenfolge |
| `created_at` | DATETIME | NOT NULL | CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | NOT NULL | CURRENT_TIMESTAMP ON UPDATE | |

**Keys**

| Key | Spalten | Art |
|-----|---------|-----|
| PRIMARY | `id` | |
| `uq_cash_register_code` | `code` | UNIQUE (Instanz-weit) |
| `idx_cash_register_location` | `location_id` | |
| `idx_cash_register_active` | `is_active`, `sort_order` | |
| `fk_cash_register_location` | `location_id` → `dg_cash_locations(id)` | ON DELETE **RESTRICT** |

**Nicht in dieser Tabelle**

- TSS/Client-UUIDs → `dg_cash_tse_assignments` (K1d)
- Offene Sitzung / Anfangsbestand → `dg_cash_sessions` (K1e)
- Soft-Delete: nur `is_active=0`

**Migration:** `144_cash_registers.sql` (nach `143_cash_locations.sql`).

### K1d — `dg_cash_tse_assignments` (2026-11-07)

TSE-Anbindung **je Kasse**. Max. **eine Zeile pro** `register_id`. API-Key/Secret **nicht** hier (Instanz: `TseSettings` / Env, D9).

| Spalte | Typ | Null | Default | Bedeutung |
|--------|-----|------|---------|-----------|
| `id` | INT UNSIGNED AI | PK | — | Intern; Bon speichert `tse_assignment_id` |
| `register_id` | INT UNSIGNED | NOT NULL | — | FK → `dg_cash_registers.id` (UNIQUE) |
| `provider` | VARCHAR(32) | NOT NULL | `'fiskaly'` | `mock` · `fiskaly` · später `deutsche_fiskal` · `swissbit` |
| `environment` | VARCHAR(8) | NOT NULL | `'test'` | `test` · `live` |
| `tss_id` | CHAR(36) | NOT NULL | `''` | fiskaly TSS-UUID (gleiche ID für alle Kassen einer Filiale) |
| `client_id` | CHAR(36) | NOT NULL | `''` | fiskaly Client-UUID (1 pro Kasse) |
| `tss_serial_number` | VARCHAR(128) | NOT NULL | `''` | Aus Provider-Status, optional |
| `client_serial_number` | VARCHAR(70) | NOT NULL | `''` | Oft = Kassen-`code`; aus Provider |
| `certificate_valid_until` | DATE | NULL | NULL | Ablauf, wenn bekannt |
| `last_state` | VARCHAR(64) | NOT NULL | `''` | z. B. TSS `INITIALIZED` |
| `last_checked_at` | DATETIME | NULL | NULL | Letzter Verbindungscheck (K6c) |
| `last_error` | VARCHAR(500) | NOT NULL | `''` | Letzte Fehlermeldung |
| `notes` | VARCHAR(500) | NOT NULL | `''` | Intern |
| `is_active` | TINYINT(1) | NOT NULL | `1` | 0 = Kasse ohne TSE-Betrieb |
| `created_at` | DATETIME | NOT NULL | CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | NOT NULL | CURRENT_TIMESTAMP ON UPDATE | |

**Keys**

| Key | Spalten | Art |
|-----|---------|-----|
| PRIMARY | `id` | |
| `uq_cash_tse_register` | `register_id` | UNIQUE |
| `idx_cash_tse_provider` | `provider`, `environment` | |
| `idx_cash_tse_active` | `is_active` | |
| `fk_cash_tse_register` | → `dg_cash_registers(id)` | ON DELETE **RESTRICT** |

**Regeln**

- 1 TSS pro Filiale, 1 Client pro Kasse — App-Logik (K6); Schema erzwingt nur 1 Assignment/Kasse.
- Übergang: solange keine Zeile existiert, darf Beleg-Barzahlung weiter Instanz-`TseSettings` nutzen (K9).
- `provider=mock` braucht keine echten UUIDs (K2).

**Migration:** `145_cash_tse_assignments.sql` (nach `144`).

### K1e — `dg_cash_sessions` (2026-11-07)

Kassensitzung: Kasse öffnen/schließen mit Bediener und Anfangsbestand (Wechselgeld). Bons (K1f) hängen an `session_id`.

| Spalte | Typ | Null | Default | Bedeutung |
|--------|-----|------|---------|-----------|
| `id` | INT UNSIGNED AI | PK | — | Intern |
| `register_id` | INT UNSIGNED | NOT NULL | — | FK → `dg_cash_registers.id` |
| `business_date` | DATE | NOT NULL | — | Geschäftstag der Sitzung |
| `status` | VARCHAR(16) | NOT NULL | `'open'` | `open` · `closed` |
| `opening_balance` | DECIMAL(12,2) | NOT NULL | `0.00` | Anfangsbestand / Wechselgeld bei Öffnung |
| `closing_expected_balance` | DECIMAL(12,2) | NULL | NULL | Soll-Bestand beim Schließen (K11/K12) |
| `closing_counted_balance` | DECIMAL(12,2) | NULL | NULL | Gezählter Bestand |
| `closing_difference` | DECIMAL(12,2) | NULL | NULL | gezählt − erwartet |
| `opened_by` | INT UNSIGNED | NULL | NULL | User-ID (`dg_users`), wer geöffnet hat |
| `closed_by` | INT UNSIGNED | NULL | NULL | User-ID, wer geschlossen hat |
| `opened_at` | DATETIME | NOT NULL | CURRENT_TIMESTAMP | |
| `closed_at` | DATETIME | NULL | NULL | gesetzt wenn `closed` |
| `notes` | VARCHAR(500) | NOT NULL | `''` | Intern |
| `created_at` | DATETIME | NOT NULL | CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | NOT NULL | CURRENT_TIMESTAMP ON UPDATE | |

**Keys**

| Key | Spalten | Art |
|-----|---------|-----|
| PRIMARY | `id` | |
| `idx_cash_session_register_status` | `register_id`, `status` | |
| `idx_cash_session_business_date` | `business_date` | |
| `idx_cash_session_opened` | `opened_at` | |
| `fk_cash_session_register` | → `dg_cash_registers(id)` | ON DELETE **RESTRICT** |

**Regeln (App, K11 — nicht DB-UNIQUE partial)**

- Pro `register_id` höchstens **eine** Zeile mit `status='open'`.
- Ohne offene Sitzung kein neuer Bon (K11b).
- Schließen setzt `status=closed`, `closed_at`, optional Zählfelder; danach neue Sitzung möglich (auch gleicher `business_date`, wenn fachlich erlaubt — Tagesabschluss K12 separat).
- Nicht verwechseln mit FiBu `dg_cash_day_closings` (054) — das ist Kassenbuch, nicht Registerkasse.

**Migration:** `146_cash_sessions.sql` (nach `145`).

### K1f — `dg_cash_receipts` + `dg_cash_receipt_lines` (2026-11-07)

Bon inkl. Positionen. Zahlungen separat (K1g). `status=finalized` → keine Änderung, nur Storno-Bon.

#### `dg_cash_receipts`

| Spalte | Typ | Null | Default | Bedeutung |
|--------|-----|------|---------|-----------|
| `id` | INT UNSIGNED AI | PK | — | Intern |
| `location_id` | INT UNSIGNED | NOT NULL | — | FK Filiale (denormalisiert für Export/Filter) |
| `register_id` | INT UNSIGNED | NOT NULL | — | FK Kasse |
| `session_id` | INT UNSIGNED | NOT NULL | — | FK Sitzung |
| `tse_assignment_id` | INT UNSIGNED | NULL | NULL | FK TSE-Zuordnung; gesetzt spätestens bei Finish |
| `voucher_id` | INT UNSIGNED | NULL | NULL | Optional: Beleg/Rechnung (K9), ohne FK-Zwang |
| `receipt_number` | INT UNSIGNED | NOT NULL | — | Nummernkreis der Kasse; UNIQUE je `register_id` |
| `receipt_number_display` | VARCHAR(48) | NOT NULL | `''` | Anzeige inkl. Prefix |
| `status` | VARCHAR(16) | NOT NULL | `'draft'` | `draft` · `paying` · `finalized` · `cancelled` (Abbruch vor Finish) |
| `receipt_type` | VARCHAR(16) | NOT NULL | `'sale'` | `sale` · `storno` |
| `storno_of_receipt_id` | INT UNSIGNED | NULL | NULL | Referenz Originalbon (K13); kein FK (Historie) |
| `operator_id` | INT UNSIGNED | NULL | NULL | User-ID Bediener |
| `total_gross` | DECIMAL(12,2) | NOT NULL | `0.00` | Brutto |
| `total_net` | DECIMAL(12,2) | NOT NULL | `0.00` | Netto |
| `vat_summary_json` | TEXT | NULL | NULL | z. B. `[{"rate":19,"gross":…,"net":…,"tax":…}]` |
| `payment_summary_json` | TEXT | NULL | NULL | Kurzfassung Zahlarten (Detail = K1g) |
| `tse_tx_id` | CHAR(36) | NOT NULL | `''` | Provider-Transaktions-ID |
| `transaction_number` | INT UNSIGNED | NULL | NULL | TSE-Transaktionsnummer |
| `tse_serial` | VARCHAR(128) | NOT NULL | `''` | TSE-Seriennummer |
| `signature_counter` | VARCHAR(32) | NOT NULL | `''` | |
| `signature_value` | TEXT | NULL | NULL | |
| `signature_algorithm` | VARCHAR(64) | NOT NULL | `''` | |
| `qr_code_data` | TEXT | NULL | NULL | KassenSichV-QR-Payload |
| `time_start` / `time_end` | INT UNSIGNED | NULL | NULL | Unix aus Provider |
| `started_at` | DATETIME | NOT NULL | CURRENT_TIMESTAMP | Bon start |
| `finished_at` | DATETIME | NULL | NULL | festgeschrieben |
| `notes` | VARCHAR(500) | NOT NULL | `''` | |
| `created_at` / `updated_at` | DATETIME | NOT NULL | TS | |

**Keys (Kopf):** UNIQUE (`register_id`,`receipt_number`); FKs location/register/session RESTRICT; `tse_assignment_id` RESTRICT; Indexes session/status/voucher/storno/tse_tx.

#### `dg_cash_receipt_lines`

| Spalte | Typ | Null | Default | Bedeutung |
|--------|-----|------|---------|-----------|
| `id` | INT UNSIGNED AI | PK | — | |
| `receipt_id` | INT UNSIGNED | NOT NULL | — | FK → receipts, ON DELETE **CASCADE** |
| `line_no` | SMALLINT UNSIGNED | NOT NULL | `1` | UNIQUE je Bon |
| `article_id` | INT UNSIGNED | NOT NULL | `0` | 0 = Freitext |
| `article_number` | VARCHAR(64) | NOT NULL | `''` | |
| `title` | VARCHAR(255) | NOT NULL | `''` | |
| `unit` | VARCHAR(64) | NOT NULL | `'Stück'` | |
| `quantity` | DECIMAL(12,3) | NOT NULL | `1.000` | |
| `unit_price_gross` | DECIMAL(12,2) | NOT NULL | `0.00` | |
| `gross_amount` | DECIMAL(12,2) | NOT NULL | `0.00` | |
| `tax_rate` | TINYINT UNSIGNED | NOT NULL | `19` | 19 / 7 / 0 … |
| `created_at` / `updated_at` | DATETIME | NOT NULL | TS | |

**Regeln**

- Bonnummer bei Anlegen aus `dg_cash_registers.next_receipt_number` (atomar, K5d/K7a).
- `draft`: Positionen änderbar; erste Zahlung → `paying` (Positionen gesperrt, Zahlungen änderbar); `finalized`: unveränderbar; Storno = neuer Bon `receipt_type=storno`.
- Parallel zu `dg_tse_transactions` (Beleg-Barzahlung); Verknüpfung über `voucher_id` / K9.

**Migration:** `147_cash_receipts.sql` (nach `146`).

### K1g — `dg_cash_payments` (2026-11-07)

Zahlungszeilen je Bon. **Gemischt** = mehrere Zeilen; kein eigener `payment_method=mixed`.

| Spalte | Typ | Null | Default | Bedeutung |
|--------|-----|------|---------|-----------|
| `id` | INT UNSIGNED AI | PK | — | |
| `receipt_id` | INT UNSIGNED | NOT NULL | — | FK → `dg_cash_receipts`, ON DELETE CASCADE |
| `line_no` | SMALLINT UNSIGNED | NOT NULL | `1` | UNIQUE je Bon |
| `payment_method` | VARCHAR(16) | NOT NULL | — | `cash` · `ec` · `card` · `voucher` |
| `amount` | DECIMAL(12,2) | NOT NULL | `0.00` | Brutto-Anteil |
| `currency` | CHAR(3) | NOT NULL | `'EUR'` | |
| `gift_voucher_id` | INT UNSIGNED | NULL | NULL | bei `voucher`; optional Link CRM-Gutschein, kein FK-Zwang |
| `external_ref` | VARCHAR(128) | NOT NULL | `''` | Terminal-Beleg / Trace-ID |
| `notes` | VARCHAR(500) | NOT NULL | `''` | Intern |
| `created_at` / `updated_at` | DATETIME | NOT NULL | TS | |

**Keys:** UNIQUE (`receipt_id`,`line_no`); Index method / gift_voucher; FK receipt CASCADE.

**Regeln (K8)**

- Summe `amount` = `dg_cash_receipts.total_gross` vor `finalized` (K8d).
- Nach Finalize unveränderbar (wie Bon; Storno über Storno-Bon + gegenläufige Zahlungen).
- `payment_summary_json` am Bon wird aus diesen Zeilen befüllt (K7).

**Migration:** `148_cash_payments.sql` (nach `147`).

### K1h — `dg_cash_register_day_closings` (2026-11-07)

Tagesabschluss **pro Registerkasse**.  
**Name bewusst anders** als FiBu `dg_cash_day_closings` (Migration 054, Kassenbuch ohne Filiale/Kasse).

| Spalte | Typ | Null | Default | Bedeutung |
|--------|-----|------|---------|-----------|
| `id` | INT UNSIGNED AI | PK | — | |
| `location_id` | INT UNSIGNED | NOT NULL | — | FK Filiale |
| `register_id` | INT UNSIGNED | NOT NULL | — | FK Kasse |
| `business_date` | DATE | NOT NULL | — | Abschlusstag; UNIQUE je Kasse |
| `opening_balance` | DECIMAL(12,2) | NOT NULL | `0.00` | Anfang (aus Sitzungen) |
| `total_cash` | DECIMAL(12,2) | NOT NULL | `0.00` | Summe Zahlart `cash` |
| `total_ec` | DECIMAL(12,2) | NOT NULL | `0.00` | Summe `ec` |
| `total_card` | DECIMAL(12,2) | NOT NULL | `0.00` | Summe `card` |
| `total_voucher` | DECIMAL(12,2) | NOT NULL | `0.00` | Summe `voucher` |
| `total_gross` | DECIMAL(12,2) | NOT NULL | `0.00` | Bon-Brutto finalized |
| `receipt_count` | INT UNSIGNED | NOT NULL | `0` | Anzahl finalized Bons |
| `expected_balance` | DECIMAL(12,2) | NOT NULL | `0.00` | Soll-Bargeld |
| `counted_balance` | DECIMAL(12,2) | NOT NULL | `0.00` | Ist-Zählung |
| `difference` | DECIMAL(12,2) | NOT NULL | `0.00` | gezählt − erwartet |
| `summary_json` | TEXT | NULL | NULL | optionale Details |
| `notes` | VARCHAR(500) | NOT NULL | `''` | |
| `closed_by` | INT UNSIGNED | NULL | NULL | User-ID |
| `closed_at` | DATETIME | NOT NULL | CURRENT_TIMESTAMP | |
| `created_at` / `updated_at` | DATETIME | NOT NULL | TS | |

**Keys:** UNIQUE (`register_id`,`business_date`); FK location/register RESTRICT.

**Abgrenzung**

| Tabelle | Modul | Granularität |
|---------|--------|--------------|
| `dg_cash_day_closings` | FiBu-Kassenbuch | 1× je Kalendertag (Instanz) |
| `dg_cash_register_day_closings` | Registerkasse | 1× je Kasse + Geschäftstag |

Filialabschluss (K12c) = Summe der Kassen-Abschlüsse, keine Extra-Tabelle in K1h.

**Migration:** `149_cash_register_day_closings.sql` (nach `148`).

### K1i — `dg_cash_audit_log` + `dg_cash_exports` (2026-11-07)

Append-only Protokoll und Export-Jobs für Registerkasse. Keine FKs auf Stammdaten (Historie bleibt, auch wenn Stammdaten später inaktiv).

#### `dg_cash_audit_log`

| Spalte | Typ | Null | Default | Bedeutung |
|--------|-----|------|---------|-----------|
| `id` | INT UNSIGNED AI | PK | — | |
| `location_id` | INT UNSIGNED | NULL | NULL | optional Kontext |
| `register_id` | INT UNSIGNED | NULL | NULL | |
| `session_id` | INT UNSIGNED | NULL | NULL | |
| `receipt_id` | INT UNSIGNED | NULL | NULL | |
| `user_id` | INT UNSIGNED | NULL | NULL | |
| `event_type` | VARCHAR(64) | NOT NULL | — | z. B. `session.open`, `receipt.finalize`, `tse.outage`, `export.dsfinvk` |
| `severity` | VARCHAR(16) | NOT NULL | `'info'` | `info` · `warning` · `error` |
| `message` | VARCHAR(500) | NOT NULL | `''` | Kurztext |
| `details_json` | TEXT | NULL | NULL | Zusatzdaten |
| `ip` | VARCHAR(45) | NOT NULL | `''` | |
| `created_at` | DATETIME | NOT NULL | CURRENT_TIMESTAMP | unveränderlich (kein updated_at) |

**Regeln:** nur INSERT; kein UPDATE/DELETE in App-Code (GoBD). Getrennt von allgemeinem `dg_audit_log`.

#### `dg_cash_exports`

| Spalte | Typ | Null | Default | Bedeutung |
|--------|-----|------|---------|-----------|
| `id` | INT UNSIGNED AI | PK | — | |
| `location_id` | INT UNSIGNED | NULL | NULL | Filter optional |
| `register_id` | INT UNSIGNED | NULL | NULL | Filter optional; NULL = mehrere/alle |
| `export_type` | VARCHAR(32) | NOT NULL | — | `dsfinvk` · `tse_tar` · `pruefer` · `csv_journal` |
| `date_from` / `date_to` | DATE | NULL | NULL | Zeitraum |
| `status` | VARCHAR(16) | NOT NULL | `'pending'` | `pending` · `running` · `done` · `failed` |
| `file_path` | VARCHAR(500) | NOT NULL | `''` | relativ unter `storage/` |
| `file_checksum` | VARCHAR(128) | NOT NULL | `''` | z. B. SHA-256 |
| `created_by` | INT UNSIGNED | NULL | NULL | User |
| `error_message` | VARCHAR(500) | NOT NULL | `''` | bei `failed` |
| `meta_json` | TEXT | NULL | NULL | Dateiliste, Provider-Refs |
| `created_at` | DATETIME | NOT NULL | CURRENT_TIMESTAMP | |
| `finished_at` | DATETIME | NULL | NULL | |

**Befüllung** der eigentlichen DSFinV-/TAR-Dateien = K17/K18; K1i nur Metadaten-Tabelle.

**Migration:** `150_cash_audit_exports.sql` (nach `149`).

---

## Architektur (Ziel)

```text
TseProviderInterface
  startTransaction / updateTransaction / finishTransaction
  cancelTransaction / exportData / getStatus

Provider: MockTseProvider | FiskalyTseProvider
  (+ später DeutscheFiskalProvider | SwissbitLocalProvider)
```

Bestehender Code (`TseSignDeClient`, `TseSignService`) wird in **K3b** hinter das Interface gezogen — nicht parallel zweite Fiskaly-Welt.

---

## K2 Specs

### K2a — `TseProviderInterface` Skizze (2026-11-07)

Nur Spec für **K3a** (PHP) und **K2b** (Mock). Keine Exceptions-Typen erfinden: bei Fehler `RuntimeException` mit klarer Meldung (wie heute `TseSignDeClient`).

**Kontext je Aufruf** (aus `dg_cash_tse_assignments` + Instanz-Secrets):

| Feld | Bedeutung |
|------|-----------|
| `provider` | `mock` · `fiskaly` · … |
| `environment` | `test` · `live` |
| `tss_id` / `client_id` | UUIDs (Mock: feste Test-UUIDs erlaubt) |
| Secrets | **nicht** im Interface; Factory lädt aus Settings/Env |

#### Einheitliches Tx-Ergebnis `TseTxResult` (assoziatives Array)

Alle start/update/finish/cancel liefern dieselben Keys (fehlende = `null` / `''`):

| Key | Typ | Bedeutung |
|-----|-----|-----------|
| `tse_tx_id` | string | Transaktions-UUID |
| `state` | string | `ACTIVE` · `FINISHED` · `CANCELLED` |
| `tx_number` | int\|null | TSE-Transaktionsnummer |
| `revision` | int\|null | Provider-Revision |
| `signature_value` | string\|null | |
| `signature_algorithm` | string\|null | |
| `signature_counter` | string\|null | |
| `qr_code_data` | string\|null | KassenSichV-QR-Payload |
| `time_start` | int\|null | Unix |
| `time_end` | int\|null | Unix |
| `tss_serial_number` | string | |
| `client_serial_number` | string | oft = Kassen-`code` |
| `raw` | array\|null | optional Provider-Rohantwort (Debug; nicht auf Bon drucken) |

Passt auf Spalten in `dg_cash_receipts` / `dg_tse_transactions`.

#### Methoden

```text
interface TseProviderInterface
{
  /** Provider-Kurzname: mock | fiskaly | … */
  getName(): string

  /** TSS/Client erreichbar? Kein Bon. */
  getStatus(): TseStatusResult

  /**
   * Bon starten (ACTIVE).
   * $txId = vorgegebene UUID (Client generiert, wie Fiskaly).
   * $input = Beträge/USt für erste Buchung (kann leer/0 sein).
   */
  startTransaction(string $txId, TseTxInput $input): TseTxResult

  /** Positionen/Betrag nachziehen solange ACTIVE. */
  updateTransaction(string $txId, int $revision, TseTxInput $input): TseTxResult

  /** Abschließen → FINISHED + Signatur/QR. */
  finishTransaction(string $txId, int $revision, TseTxInput $input): TseTxResult

  /** Abbruch vor Finish → CANCELLED. */
  cancelTransaction(string $txId, int $revision): TseTxResult

  /**
   * Rohdaten/Export-Hinweis für K18 (TAR o. Ä.).
   * Darf Stub werfen „not implemented“ bis K18.
   */
  exportData(TseExportRequest $request): TseExportResult
}
```

#### `TseTxInput` (Eingabe Signatur)

| Key | Typ | Bedeutung |
|-----|-----|-----------|
| `receipt_type` | string | z. B. `RECEIPT` · `CANCELLATION` (Provider-Mapping in Adapter) |
| `amount_gross` | float | Bon-Brutto |
| `vat_amounts` | list of `{vat_rate_code, amount}` | wie `TseSignService::allocateVatAmounts` / Fiskaly |
| `client_serial_number` | string | optional Override |
| `process_type` / `process_data` | string | optional, Default leer/Kassen-Beleg |

#### `TseStatusResult`

| Key | Typ | Bedeutung |
|-----|-----|-----------|
| `ok` | bool | Verbindung + TSS nutzbar |
| `state` | string | z. B. TSS `INITIALIZED` |
| `tss_id` | string | |
| `client_id` | string | |
| `tss_serial_number` | string | |
| `certificate_valid_until` | string\|null | ISO-Datum wenn bekannt |
| `message` | string | Hinweis / letzter Fehler |

#### `TseExportRequest` / `TseExportResult` (für später K18)

| Request | Result |
|---------|--------|
| `date_from`, `date_to` (optional) | `ok` bool |
| `format` (`tar`\|`json`\|…) | `file_bytes` oder `download_ref` |
| | `checksum`, `message` |

**Mock (K2b):** feste UUIDs/Serien, `signature_value=MOCK-…`, `qr_code_data` nichtleer, kein HTTP.  
**K3a:** erledigt — `TseProviderInterface` mit Arrays; `MockTseProvider implements TseProviderInterface`.  
**K3b:** `FiskalyTseProvider` wrappt `TseSignDeClient` + VAT-Logik aus `TseSignService` (🔴).

---

## Nicht vergessen

- Kein Sondercode pro Kundeninstanz; keine Kundendaten als Kern-Defaults.
- Fertige Bons nie bearbeiten — nur stornieren/korrigieren.
- Offline/Netzausfall sichtbar protokollieren.
- Beleg: Druck, PDF, E-Mail, QR/Signaturdaten.
- Prüferansicht ohne Technikkenntnisse bedienbar.
- UI immer [`UI-KLASSENKATALOG.md`](UI-KLASSENKATALOG.md); Guard `php bin/ui-form-spacing-selftest.php`.
- Endnutzer-Texte: keine internen Plan-Codes (`K7d`) in der UI.

---

## Grobe Abhack-Übersicht (Parent)

| ID | Thema | Status |
|----|--------|--------|
| K0 | Anbieterzugang | [~] K0i Preis-Hypothese da; nur noch Sales-Zeile (Go Live) |
| K1 | Datenmodell | [x] K1a–K1k |
| K2 | Mock-TSE | [x] K2a–K2c |
| K3 | Provider-Interface | [~] K3a+K3c+K3d; offen nur K3b 🔴 |
| K4 | Admin Filialen | [x] K4a–K4d |
| K5 | Admin Kassen | [x] K5a–K5d |
| K6 | Admin TSE-Zuordnung | [x] K6a–K6d |
| K7 | Bonfluss | [~] K7a–K7c; offen K7d–K7f |
| K8 | Zahlungsarten | [ ] |
| K9 | Barverkauf Auftrag/Rechnung | [~] Beleg-Barzahlung TSE da |
| K10–K21 | Rest | [ ] |
