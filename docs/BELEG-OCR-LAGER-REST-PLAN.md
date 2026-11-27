# Beleg-OCR → FiBu + Lager (BO) — Rest-Plan (tokenarm)

> **Eine Chat-Session = eine Mini-ID.**  
> Fach: [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) §D2 · Ist-OCR: [`IMPORT-BELEGE-TODO.md`](IMPORT-BELEGE-TODO.md) · Lager: [`LAGER-WIRTSCHAFT.md`](LAGER-WIRTSCHAFT.md) · Kichel: [`KICHEL-PHASE2.md`](KICHEL-PHASE2.md)  
> Stand: **2026-11-27** · Branch-Empfehlung: `cursor/beleg-ocr-…`

---

## 0. Zielbild (verbindlich)

| Punkt | Festlegung |
|-------|------------|
| **Eingang** | Eingescannte **und** heruntergeladene Lieferantenrechnungen (PDF/Bild/E-Rechnung) |
| **Nicht Lexware-Office-Niveau** | Nicht nur Kopf + Betrag/Steuer für die FiBu-Buchung |
| **Alles von der Rechnung** | Kopfdaten **und** Positionen: **Artikel** *und* **Dienstleistungen** |
| **Lager** | Erkannte Artikel/Leistungen für Lagerwirtschaft nutzbar (Match/Neuanlage, EK-Quelle, Wareneingang-Vorschlag) |
| **Kichel** | Prüft den OCR-/Textrohstoff und **ordnet** ihn (strukturierter Extrakt) — kein stilles Buchen |
| **Mensch** | Übernimmt Vorschläge bewusst; GoBD: keine Auto-Buchung, keine Auto-Lagerbewegung ohne Bestätigung |
| **Beträge** | Summen/Steuer **PHP** (Abgleich); LLM darf vorschlagen, nicht als alleinige Wahrheit buchen |

**Abgrenzung Lexware:** Lexware Office ignoriert oft Rechnungspositionen beim Beleg-Einlesen. DG soll Positionen **mitnehmen** und an Katalog/Lager anbinden.

**Ist heute (2026-11-27):** Client-OCR/E-Rechnung/Textlayer im Belegformular für **FiBu-Kopf-Vorschläge**. Positionen höchstens Zähler. **Kein** Kichel in der Pipeline. **Kein** Lager-Anschluss. Docs teilweise noch „OCR zurückgestellt“ → veraltet.

---

## 1. Warum tokenarm

| Regel | Bedeutung |
|-------|-----------|
| **Kein Explorer** | Nur Datei(en) / Vertrag der Mini-ID |
| **1 ID = 1 Commit** | Doc, Schema, 1 Service, 1 UI-Schritt oder 1 Selftest |
| **Kein Tenant im Kern** | Firmendaten nur Instanz; keine Kundenfirmen in Spec-Beispielen hardcoden |
| **UI-Katalog** | Views: [`UI-KLASSENKATALOG.md`](UI-KLASSENKATALOG.md) · Guard `php bin/ui-form-spacing-selftest.php` |
| **Kein Server-OCR-Zwang** | Roh-OCR bleibt Browser (Tesseract/pdf.js); Kichel strukturiert den **Text** serverseitig |
| **Kein stilles Buchen** | Apply = User-Aktion; Lagerbewegung erst nach bestätigtem Pfad |

**Chat-Prompt-Vorlage:**  
`Nur Mini-ID BO2a laut docs/BELEG-OCR-LAGER-REST-PLAN.md. Nur genannte Datei(en). Kein Refactor. Kein stilles Buchen.`

**Nicht in diesem Plan:** Factur-X-Ausgang-Hybrid (eigenes D2-Ticket) · PSD2 · Auto-AB-Mail · Server-Tesseract · Peppol.

---

## 2. Token-Stufen

| Symbol | Wann |
|--------|------|
| 🟢 | Doc, Schema, Haken, kleiner Guard |
| 🟡 | 1 Service / 1 API-Zweig / 1 View-Block |
| 🔴 | Manuelle Stichprobe auf Dev, Qualität OCR/LLM |

---

## 3. Architektur (Soll)

```
Datei (PDF/Bild/XML)
  → Client: speichern + Rohtext
       E-Rechnung XML → strukturierte Zeilen (bevorzugt, kein OCR)
       Textlayer PDF → Rohtext
       Scan/Foto → Tesseract → Rohtext
  → Server: Kichel-Extrakt (Rohtext + optionale Regex-Hints)
       → JSON-Schema BO2 (Kopf + Positionen Artikel/Leistung)
  → UI: Vorschläge prüfen
       → Übernehmen FiBu-Belegzeilen
       → optional Lager: Match / Neuanlage / EK / Wareneingang-Vorschlag
```

**PHP-Regel:** Kichel **ordnet** Text; Konten, Steuerarithmetik, Lagerbuchung = bestehende PHP-Services nach User-Bestätigung.

---

## 4. Parent-Übersicht

| ID | Thema | Status | Token |
|----|--------|--------|-------|
| **BO0** | Inventar / Zielbild / Doc-Sync | [x] BO0a–c ✅ | 🟢 |
| **BO1** | Rohpipeline Client härten | [~] BO1b–c ✅ · BO1d offen | 🟡🔴 |
| **BO2** | Extrakt-Schema (Kopf + Positionen) | [~] BO2a–c ✅ · BO2d offen | 🟢🟡 |
| **BO3** | Kichel-Strukturierung | [ ] | 🟡🔴 |
| **BO4** | FiBu-UI Positionen übernehmen | [ ] | 🟡 |
| **BO5** | Lager-Anbindung | [ ] | 🟡🔴 |
| **BO6** | Docs/Haken Abschluss | [ ] | 🟢 |

---

## 5. Abhackplan (Mini-IDs)

Status: `[ ]` offen · `[~]` angefangen · `[x]` erledigt

### BO0 — Inventar / Docs

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| **BO0a** | Dieser Plan + Verweis in `IQ-STROM` §D2 · `TODOS.md` · `IMPORT-BELEGE-TODO.md` | 🟢 | [x] 2026-11-27 |
| **BO0b** | `buchhaltung-todo.html` §6: „zurückgestellt“ → Ist/Soll laut diesem Plan (kein Plan-Jargon in Nutzer-UI) | 🟢 | [x] 2026-11-27 |
| **BO0c** | Kurz-Ist Code: `buchhaltung-import.mjs` · `receipt-parse-core.mjs` · `dg-einvoice-xml.mjs` · Upload-API — Tabelle in diesem Plan §6 | 🟢 | [x] 2026-11-27 §6 |

**Prompt BO0a:**  
`Nur Mini-ID BO0a laut docs/BELEG-OCR-LAGER-REST-PLAN.md. Nur Doc-Verweise. Kein Code.`

---

### BO1 — Rohpipeline Client (ohne Kichel, ohne Lager)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| **BO1a** | Ist lesen: Upload, Analyse-Kaskade, Apply-Felder (nur Doc-Notiz hier §6) | 🟢 | [x] 2026-11-27 §6 |
| **BO1b** | Button **„Erneut auslesen“** für bereits gespeicherte Anhänge (Install-Entwurf) | 🟡 | [x] 2026-11-27 · Toolbar + je Datei · `bin/beleg-ocr-reread-selftest.php` |
| **BO1c** | Scan-PDF: OCR über **weitere Seiten** (sinnvolles Limit, z. B. max. N Seiten, Statuszeile) | 🟡 | [x] 2026-11-27 · max. 5 Seiten · Status „Seite x von y“ |
| **BO1d** | Smoke Dev: 1 digitales PDF + 1 Scan/Foto — Kurznotiz im Plan | 🔴 | [ ] |

**Nicht in BO1:** Kichel-API · Lager · Bild-Editor (Zuschneiden = optional später eigene ID, wenn nötig).

**Prompt BO1b:**  
`Nur Mini-ID BO1b laut docs/BELEG-OCR-LAGER-REST-PLAN.md. Erneut-auslesen für gespeicherte Anhänge. Katalog-Buttons. Kein Kichel.`

---

### BO2 — Extrakt-Schema

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| **BO2a** | JSON-Vertrag dokumentieren (unten §7) — Kopf + `lines[]` mit `catalog_kind` product\|service | 🟢 | [x] 2026-11-27 in diesem Plan §7 |
| **BO2b** | Client: aus E-Rechnung-XML **Zeilen** ins Schema mappen (bevorzugter Pfad) | 🟡 | [x] 2026-11-27 · `mapEInvoiceToBo2Extract` · Selftest `bin/beleg-ocr-bo2-einvoice-lines-selftest.mjs` |
| **BO2c** | Client: Regex-/receipt-parse **Zeilen-Hints** optional an Schema anreichern (ohne LLM) | 🟡 | [x] 2026-11-27 · `mapReceiptHintsToBo2Extract` · E-Rechnung-Zeilen haben Vorrang · Selftest `bin/beleg-ocr-bo2-receipt-hints-selftest.mjs` |
| **BO2d** | Selftest oder Fixture: XML-Rechnung → Schema-Felder (PHP oder Node, eine Datei) | 🟡 | [ ] |

**Prompt BO2b:**  
`Nur Mini-ID BO2b laut docs/BELEG-OCR-LAGER-REST-PLAN.md. E-Rechnung-Zeilen → BO2-Schema. Nur genannte JS/PHP-Dateien.`

---

### BO3 — Kichel strukturiert Rohtext

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| **BO3a** | Ist: wie Ollama/Kichel von CRM erreichbar (`llm…`, `kichel.local.php`) — nur lesen/Doc | 🟢 | [ ] |
| **BO3b** | Spec API: `action=voucher_extract` (o. Ä.) — Input Rohtext (+ Meta), Output BO2-JSON; **kein** Chat-Ersatz | 🟢 | [ ] |
| **BO3c** | PHP: Extrakt-Service — Prompt „nur strukturieren“, PHP-Abgleich Brutto/Netto/MwSt, Fehler → soft fail | 🟡 | [ ] |
| **BO3d** | Client: nach OCR/Textlayer optional Kichel-Extrakt aufrufen; UI zeigt „geordnet durch Assistenz“ ohne Plan-Codes | 🟡 | [ ] |
| **BO3e** | Smoke Dev + GoBD: keine Auto-Buchung; Selftest mit Fixture-Text | 🔴🟡 | [ ] |

**Regeln BO3:**  
- Beträge: LLM-Vorschlag vs. Regex-Hints vs. Summenzeile — PHP markiert Konflikte.  
- Keine Rechts-/Buchungsfreigabe durch Modell (KH3-Ton).  
- Intent-LLM (KI1–3) bleibt geparkt — dies ist **Extrakt**, nicht Chat-Intent.

**Prompt BO3c:**  
`Nur Mini-ID BO3c laut docs/BELEG-OCR-LAGER-REST-PLAN.md. Kichel voucher_extract Service laut §7. Kein stilles Buchen. php -l.`

---

### BO4 — FiBu-UI Positionen

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| **BO4a** | Ist: wie Belegformular Split-/Positionszeilen speichert (`buchhaltung-belege.js` / Form-Felder) — nur lesen | 🟢 | [ ] |
| **BO4b** | „Vorschläge übernehmen“ füllt **Belegpositionen** (Titel, Menge, Einzel/Gesamt, Steuersatz, Art Artikel/Leistung) | 🟡 | [ ] |
| **BO4c** | Kopfdaten-Apply unverändert nutzbar; Konflikte/leere Zeilen klar in Status | 🟡 | [ ] |
| **BO4d** | Guard + Smoke Dev (1 E-Rechnung mit Zeilen) | 🔴 | [ ] |

**Nicht in BO4:** Artikelstamm anlegen (→ BO5).

---

### BO5 — Lagerwirtschaft

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| **BO5a** | Ist: `CalendarArticleRepository` / `catalog_kind` product\|service · `ArticlePurchaseSourceRepository` · Wareneingang — nur Doc | 🟢 | [ ] |
| **BO5b** | Match-Vorschläge: bestehende Artikel/Leistungen (Nummer/Titel); UI-Liste zur Bestätigung | 🟡 | [ ] |
| **BO5c** | Neuanlage-Vorschlag: product vs. service; **nur nach User-Klick** speichern | 🟡 | [ ] |
| **BO5d** | EK-Quelle am Artikel aus Beleg (Lieferant-Kontakt + Preis), Preferred optional | 🟡 | [ ] |
| **BO5e** | Wareneingang-**Vorschlag** (bestehender Lager-Service) nach gespeichertem Eingangsbeleg — kein Auto-Post ohne Klick | 🟡🔴 | [ ] |
| **BO5f** | Smoke: 1 Artikel-Match + 1 Neuanlage + EK sichtbar; Kurznotiz | 🔴 | [ ] |

**Prompt BO5c:**  
`Nur Mini-ID BO5c laut docs/BELEG-OCR-LAGER-REST-PLAN.md. Neuanlage Artikel/Leistung nur nach Bestätigung. Katalog-UI. Kein Auto-Wareneingang.`

---

### BO6 — Abschluss

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| **BO6a** | `IQ-STROM` D2 OCR-Haken / Standzeile · `TODOS.md` · dieser Plan Parent-Haken | 🟢 | [ ] |
| **BO6b** | Kurz in `LAGER-WIRTSCHAFT.md` / `IMPORT-BELEGE-TODO.md`: Pipeline + Grenzen | 🟢 | [ ] |

---

## 6. Ist-Code (Referenz, BO0c/BO1a)

| Baustein | Rolle |
|----------|--------|
| `assets/js/buchhaltung-import.mjs` | Upload, Analyse-Kaskade, Vorschlagspanel, Apply Kopf |
| `assets/js/vendor/receipt-parse-core.mjs` | Text-/OCR-Heuristiken, u. a. `analyzeInvoiceText` |
| `assets/js/vendor/dg-einvoice-xml.mjs` | E-Rechnung XML → Felder |
| `views/modules/buchhaltung-beleg-form.php` | Dropzone + Extract-UI |
| `src/Accounting/VoucherApi.php` `file_upload` | Sofort speichern am Entwurf |
| `src/Install/InstallVoucherImporter.php` | Massen-Entwürfe ohne Analyse |
| Kichel Hilfe-API | **noch nicht** in Beleg-Extrakt verdrahtet |
| Lager / Artikelkatalog | **noch nicht** aus Extrakt angesteuert |

---

## 7. Extrakt-Schema (BO2a) — Vertrag

Mindestfelder (JSON). Namen stabil halten; Erweiterungen nur mit neuer Mini-ID.

```json
{
  "schema_version": 1,
  "source": "einvoice_xml|pdf_text|ocr|kichel",
  "supplier_name": "",
  "invoice_number": "",
  "invoice_date": "YYYY-MM-DD",
  "currency": "EUR",
  "gross_total": "0.00",
  "net_total": "0.00",
  "vat_total": "0.00",
  "tax_rates": [{ "percent": 19, "net": "0.00", "vat": "0.00" }],
  "iban": "",
  "bic": "",
  "vat_id": "",
  "tax_number": "",
  "lines": [
    {
      "line_no": 1,
      "catalog_kind": "product|service",
      "sku": "",
      "title": "",
      "quantity": "1",
      "unit": "Stück",
      "unit_price_net": "",
      "line_net": "",
      "line_gross": "",
      "tax_percent": 19,
      "confidence": 0.0,
      "match_article_id": null
    }
  ],
  "warnings": [],
  "amount_check": { "ok": true, "detail": "" }
}
```

| Regel | Inhalt |
|-------|--------|
| `catalog_kind` | `product` = Artikel (lagerfähig möglich) · `service` = Dienstleistung |
| `amount_check` | PHP: Zeilensummen vs. Kopf; bei Abweichung `ok=false` + `warnings` |
| Leere Zeilen | lieber weglassen als raten |
| E-Rechnung | XML-Zeilen haben Vorrang vor OCR/Kichel |

---

## 8. Empfohlene Reihenfolge

1. **BO0a–c** (Docs/Ist)  
2. **BO1b–d** (Erneut auslesen + Mehrseiten)  
3. **BO2b–d** (Schema befüllen aus XML/Hints)  
4. **BO3a–e** (Kichel ordnen)  
5. **BO4b–d** (FiBu-Positionen)  
6. **BO5b–f** (Lager)  
7. **BO6** Abschluss  

Parallel ok: BO1 und BO2a (Schema schon hier). **BO5 nicht vor BO4.**

---

## 9. Verweise

| Datei | Rolle |
|-------|--------|
| [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) | D2 Bullet OCR |
| [`IMPORT-BELEGE-TODO.md`](IMPORT-BELEGE-TODO.md) | Install-Entwürfe + Client-OCR |
| [`LAGER-WIRTSCHAFT.md`](LAGER-WIRTSCHAFT.md) | Bestand, EK, Wareneingang |
| [`ZUGFERD-AUSGANG-ENTSCHEIDUNG.md`](ZUGFERD-AUSGANG-ENTSCHEIDUNG.md) | Ausgangs-Hybrid = anderes D2-Ticket |
| [`UI-KLASSENKATALOG.md`](UI-KLASSENKATALOG.md) | Buttons/Form |

---

## Änderungshistorie

| Datum | Inhalt |
|-------|--------|
| 2026-11-27 | Plan angelegt · Zielbild Lexware-plus + Kichel + Lager · Mini-IDs BO0–BO6 · Schema §7 |
| 2026-11-27 | **BO0a–c ✅** Verweise TODOS/IQ-STROM/Import · buchhaltung-todo §6 · Ist-Tabelle §6 |
| 2026-11-27 | **BO1b ✅** „Erneut auslesen“ Toolbar + je Anhang · Selftest `beleg-ocr-reread-selftest.php` |
| 2026-11-27 | **BO1c ✅** Scan-PDF OCR bis 5 Seiten · Fortschritt in Statuszeile · Selftest erweitert |
| 2026-11-27 | **BO2b ✅** E-Rechnung-Zeilen → BO2-Schema (Artikel/Leistung, Menge, Netto) · Node-Selftest |
| 2026-11-27 | **BO2c ✅** Text/OCR-Hints → BO2-Schema ohne LLM · XML-Vorrang · Selftest |
