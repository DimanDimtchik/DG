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

**Ist heute (2026-11-27):** Client-OCR + optional Kichel-Extrakt (BO3) für **FiBu-Kopf-Vorschläge**; Extrakt-Positionen im Panel sichtbar. Apply schreibt noch **keine** Form-Positionen (nur Kopf + 1. Split) — siehe §12. **Kein** Lager-Anschluss (→ BO5).

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
| **BO2** | Extrakt-Schema (Kopf + Positionen) | [x] BO2a–d ✅ | 🟢🟡 |
| **BO3** | Kichel-Strukturierung | [x] BO3a–e ✅ | 🟢🟡 |
| **BO4** | FiBu-UI Positionen übernehmen | [~] BO4a–b ✅ | 🟢🟡 |
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
| **BO2d** | Selftest oder Fixture: XML-Rechnung → Schema-Felder (PHP oder Node, eine Datei) | 🟡 | [x] 2026-11-27 · Fixtures `docs/testdata/beleg-ocr/` · `bin/beleg-ocr-bo2-fixture-selftest.mjs` |

**Prompt BO2b:**  
`Nur Mini-ID BO2b laut docs/BELEG-OCR-LAGER-REST-PLAN.md. E-Rechnung-Zeilen → BO2-Schema. Nur genannte JS/PHP-Dateien.`

---

### BO3 — Kichel strukturiert Rohtext

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| **BO3a** | Ist: wie Ollama/Kichel von CRM erreichbar (`llm…`, `kichel.local.php`) — nur lesen/Doc | 🟢 | [x] 2026-11-27 · §10 |
| **BO3b** | Spec API: `action=voucher_extract` (o. Ä.) — Input Rohtext (+ Meta), Output BO2-JSON; **kein** Chat-Ersatz | 🟢 | [x] 2026-11-27 · §11 |
| **BO3c** | PHP: Extrakt-Service — Prompt „nur strukturieren“, PHP-Abgleich Brutto/Netto/MwSt, Fehler → soft fail | 🟢 | [x] 2026-11-27 · `VoucherExtractService` + API |
| **BO3d** | Client: nach OCR/Textlayer optional Kichel-Extrakt aufrufen; UI zeigt „geordnet durch Assistenz“ ohne Plan-Codes | 🟢 | [x] 2026-11-27 · `enrichWithAssistenz` |
| **BO3e** | Smoke Dev + GoBD: keine Auto-Buchung; Selftest mit Fixture-Text | 🟢 | [x] 2026-11-27 · Fixture+GoBD-Selftest; UI-Smoke nach Deploy/BO1d |

**Regeln BO3:**  
- Beträge: LLM-Vorschlag vs. Regex-Hints vs. Summenzeile — PHP markiert Konflikte.  
- Keine Rechts-/Buchungsfreigabe durch Modell (KH3-Ton).  
- Intent-LLM (KI1–3) bleibt geparkt — dies ist **Extrakt**, nicht Chat-Intent.

**Prompt BO3c:**  
`Nur Mini-ID BO3c laut docs/BELEG-OCR-LAGER-REST-PLAN.md. Implementierung laut §11 Spec. Kein stilles Buchen. php -l.`

---

### BO4 — FiBu-UI Positionen

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| **BO4a** | Ist: wie Belegformular Split-/Positionszeilen speichert (`buchhaltung-belege.js` / Form-Felder) — nur lesen | 🟢 | [x] 2026-11-27 · §12 |
| **BO4b** | „Vorschläge übernehmen“ füllt **Belegpositionen** (Titel, Menge, Einzel/Gesamt, Steuersatz, Art Artikel/Leistung) | 🟢 | [x] 2026-11-27 · `applyOcrExtractLines` |
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
| `assets/js/buchhaltung-import.mjs` | Upload, Analyse-Kaskade, Vorschlagspanel, Apply **Kopf** (+ 1. Split-Brutto); BO3d Assistenz-Extrakt |
| `assets/js/buchhaltung-belege.js` | Form-Logik: Rechnungspositionen ↔ Buchungszeilen, Summen, Speichern |
| `assets/js/vendor/receipt-parse-core.mjs` | Text-/OCR-Heuristiken, u. a. `analyzeInvoiceText` |
| `assets/js/vendor/dg-einvoice-xml.mjs` | E-Rechnung XML → Felder |
| `views/modules/buchhaltung-beleg-form.php` | Dropzone + Extract-UI + `items[]` / `lines[]` |
| `src/Accounting/VoucherApi.php` | `file_upload` · `voucher_extract` (BO3c) |
| `src/Accounting/VoucherRepository.php` | `dg_voucher_items` / `dg_voucher_lines` replace on save |
| `src/Accounting/VoucherIncomePositions.php` | Items nur Erlös-Typen; leitet Buchungszeilen ab |
| `src/Install/InstallVoucherImporter.php` | Massen-Entwürfe ohne Analyse |
| Kichel Hilfe-API | Chat `POST /api/kichel` — getrennt von `voucher_extract` |
| `KichelOllamaClient` | `phrase`/`compose` + `generate` (Extrakt); Config `kichel.local.php` · Ist §10 |
| Lager / Artikelkatalog | **noch nicht** aus Extrakt angesteuert (→ BO5) |

---

## 12. BO4a — Ist Split- / Positionszeilen im Belegformular (2026-11-27, nur lesen)

> Kein Code in BO4a. Grundlage für BO4b (Apply Positionen). Lieferanten-OCR zielt auf **Ausgabe**-Belege.

### Zwei Zeilenwelten

| Welt | UI | POST-Felder | DB | Wann sichtbar |
|------|-----|-------------|-----|----------------|
| **Rechnungspositionen** | `#dg-voucher-invoice-items-section` · `.dg-voucher-items__row` | `items[i][…]` | `dg_voucher_items` | nur `income` / `income_reduction` / `credit` (`VoucherIncomePositions::usesInvoiceItems`) |
| **Buchungszeilen (Split)** | `#dg-voucher-booking-section` · `.dg-voucher-split__row` | `lines[i][…]` | `dg_voucher_lines` | **immer** (bei Items: abgeleitet / `dg-voucher-booking--derived`) |

**Ausgabe / Aufwand (`expense`, `expense_reduction`):** Section Rechnungspositionen **hidden**. Nutzer erfasst FiBu nur über **Konto + Brutto + USt %** in den Split-Zeilen. Kein `article_id` / `catalog_kind` / Menge in diesem UI.

**Erlös:** Positionen = Katalogartikel/Leistung (Suche); JS `syncBookingFromItems()` gruppiert nach Steuersatz → Erlöskonten in Split; Server `bookingLinesFromItems` beim Speichern.

### Form-Felder (Ist)

**`items[i]` (Erlös):** `article_id`, `catalog_kind`, `article_number`, `title`, `area_id`/`area_name`, `quantity`, `unit`, `unit_price_gross`, `tax_rate`, `tax_type`, `gross_amount`  
Klassen: `.dg-voucher-items-*` · JS: `bindInvoiceItemRow`, `collectInvoiceItems`, `addInvoiceItemRow`, `reindexInvoiceItems`.

**`lines[i]` (Buchung):** `account_number`, `gross_amount`, `tax_rate`  
Klassen: `.dg-voucher-split-*` · JS: `bindBookingRow`, `addBookingRow`, `syncTotalsFromLines`.  
Bei Items-Modus: Split oft read-derived; Hinweis „Wird aus den Rechnungspositionen berechnet“.

### Speichern (Server)

`VoucherRepository` (Save-Pfad):

1. Wenn `usesInvoiceItems` und Items vorhanden → `parseItemRows` → `replaceItems` → Buchungszeilen aus Items.  
2. Sonst → `parseLineRows` aus POST `lines[]` → `replaceLines` (`dg_voucher_lines`).  
3. Journal/Lager erst über bestehende Posting-/Stock-Services nach gültigem Speichern — **nicht** aus OCR-Apply.

### OCR-Apply (`buchhaltung-import.mjs` → `applySuggestion`, Stand BO4b)

| Feld | Verhalten |
|------|-----------|
| Kopf | Rechnungsnr., Datum, Lieferant, Kontakt-Suche, Notizen (IBAN/USt …) |
| Extrakt-`lines[]` mit Betrag | `dgVoucherFormApi.applyOcrExtractLines` |
| Ausgabe | eine Split-Zeile je Position (Brutto/USt, Konto leer); Summary in Notizen |
| Erlös | `items[]` je Position (Titel/`catalog_kind`/Menge/…); `article_id` leer |
| Ohne Positionsbeträge | Fallback: erste Split-Brutto + USt wie früher |

**Entscheidung BO4b (umgesetzt):**  
- **Ausgabe:** Extrakt-`lines` → **eine Split-Zeile je Position** (Brutto/USt; **Konto leer**); Titel/Art/Menge in Notizen + Buchungstext.  
- **Erlös:** Extrakt → **`items[]`** (Titel, `catalog_kind`, Menge, Einheit, Einzel/Summe brutto, USt; `article_id` leer → BO5).  
- Ohne Positionsbeträge: Fallback Kopf + 1. Split wie bisher. Kein Auto-Konto, kein Katalog-Write.

**Verweise:** `views/modules/buchhaltung-beleg-form.php` · `assets/js/buchhaltung-belege.js` (`applyOcrExtractLines`) · `assets/js/buchhaltung-import.mjs` · `VoucherIncomePositions` · `VoucherRepository::replaceItems` / `replaceLines`

---

## 10. BO3a — Ist Ollama / Kichel vom CRM (2026-11-27, nur lesen)

> Keine Secrets in Git. Path-Token / `kichel.local.php` nur Instanz-Server.

| Baustein | Ist |
|----------|-----|
| **Config** | `config/kichel.local.php` (nicht committen) · Vorlage `config/kichel.local.php.example` |
| **Laden** | `KichelOllamaClient::config()` liest nur diese Datei; fehlt sie → Ollama aus |
| **Schalter** | `ollama_enabled` · URL `ollama_url` · Modell `ollama_model` · `timeout_seconds` |
| **Beispiel-Defaults** | enabled `false` · URL `http://127.0.0.1:11434` · Model `llama3.1:8b` |
| **Dev (ganz-om.de)** | SKC1 ✅: `ollama_enabled=true` · **HTTPS** `https://llm.ganz-soft.de/…` (Path-Secret) · Modell typ. `qwen2.5-coder:14b` |
| **GEX Ollama** | Bind `127.0.0.1:11434` (SKB1) · UFW 11434 zu · nginx-Proxy nur Host `llm.ganz-soft.de` |
| **Aufruf** | PHP `file_get_contents` → `{ollama_url}/api/generate` · `stream:false` |
| **Hilfe-Pipeline** | Widget/Super-Kichel → `POST /api/kichel` → `KichelApi` → Assistant → ggf. `KichelOllamaClient::compose`/`phrase` |
| **Regel heute** | Ollama **nur Formulierung**; Beträge/Normen PHP/RAG — **kein** freier Beleg-JSON-Extrakt |
| **Beleg-OCR** | Client (Browser) spricht **nicht** Ollama an; BO3b+ = **neuer** Server-Pfad `voucher_extract` (nicht Chat) |

**Folge für BO3b/c:** Extrakt-Service soll denselben `kichel.local.php`-/Proxy-Weg nutzen (wenn enabled), eigenes Prompt „nur strukturieren → BO2-JSON“, Soft-Fail wenn Ollama aus/Fehler → Regex-Hints (BO2c) bleiben Fallback. Kein Secret in Responses/Logs.

**Verweise:** [`KICHEL-PHASE2.md`](KICHEL-PHASE2.md) · [`SUPERKICHEL-GEX-PLAN.md`](SUPERKICHEL-GEX-PLAN.md) § SKC0/SKC1 · `config/kichel.local.php.example`

---

## 11. BO3b — Spec API `voucher_extract` (verbindlich für BO3c+)

> **Kein Code in BO3b.** Umsetzung = **BO3c**. Kein Chat, kein `/api/kichel`, kein stilles Buchen/Lager.

### Endpoint

| Punkt | Festlegung |
|-------|------------|
| **URL** | `POST /api/voucher?action=voucher_extract` |
| **Handler** | neuer Zweig in `VoucherApi` (wie `file_upload`) — **nicht** `KichelApi` |
| **Auth** | eingeloggt + `MenuRegistry::canAccess(…, 'buchhaltung-belege')` + `RoleResolver::canEdit` (wie Upload) |
| **CSRF** | Pflicht: `_csrf` (Form-POST oder Header-äquivalent wie bestehende Beleg-API) |
| **Content-Type** | `application/x-www-form-urlencoded` oder `multipart/form-data` (kein JSON-Body Pflicht; Felder wie Upload) |
| **Antwort** | `Content-Type: application/json` |

### Request-Felder

| Feld | Pflicht | Bedeutung |
|------|---------|-----------|
| `_csrf` | ja | CSRF |
| `raw_text` | ja | OCR-/Textlayer-/Hinweis-Rohtext (UTF-8) |
| `source` | nein | `ocr` \| `pdf_text` \| `einvoice_xml` \| `mixed` — Default `pdf_text` |
| `voucher_id` | nein | >0 nur Meta/Audit; Extrakt speichert **nicht** still in den Beleg |
| `hint_extract` | nein | optionales JSON-String des Client-BO2-Extrakts (BO2c Hints) — Server darf mergen/prüfen |
| `file_name` | nein | Originalname nur für Prompt-Kontext |

**Limits (BO3c):** `raw_text` max. **100 000** Zeichen; bei Überschreitung `400` + klare Message. Leerer Text nach Trim → `400`.

### Response (Erfolg)

```json
{
  "success": true,
  "data": {
    "extract": { /* BO2-Schema §7, source oft \"kichel\" oder soft \"pdf_text\"/\"ocr\" */ },
    "meta": {
      "ollama_used": false,
      "soft_fail": false,
      "soft_fail_reason": "",
      "model": ""
    }
  }
}
```

| Punkt | Regel |
|-------|--------|
| `extract` | Immer BO2-`schema_version: 1`; `lines[]` wie §7 |
| `ollama_used` | `true` nur wenn Modellantwort geparst und übernommen |
| Soft-Fail | Ollama aus / Timeout / ungültiges JSON / GoBD-Filter → **HTTP 200**, `soft_fail=true`, Extrakt = validierte Hints oder leere `lines` + `warnings` — **kein** 500 für „Modell sagt nichts“ |
| Hard-Fail | CSRF/Rechte/leerer Text/Limit → 4xx wie andere Voucher-API |

### Server-Verhalten (Soll für BO3c)

1. Hints parsen/normalisieren (`hint_extract` oder Regex aus `raw_text` — wiederverwendbar aus BO2-Logik wo möglich).  
2. Wenn `KichelOllamaClient::isEnabled()`: Prompt „nur strukturieren → **nur** BO2-JSON“, Input = Rohtext + optionale Hints; **keine** Buchungsratschläge.  
3. PHP: JSON extrahieren → gegen §7 validieren → `amount_check` (Zeilensummen vs. Kopf); Konflikte → `warnings`, Beträge nicht „schönrechnen“ ohne Hinweis.  
4. Zahlen: Modellvorschlag vs. Hints — bei Widerspruch Hint/PHP-Abgleich bevorzugen oder beides in `warnings`.  
5. **Nicht:** Beleg speichern, Journal, Lager, Kontakt patchen, Chat-Protokoll als Hilfe-Turn.

### Abgrenzung

| Thema | Hier? |
|-------|--------|
| `POST /api/kichel` Hilfe-Chat | **nein** |
| Intent-LLM (KI1–3) | **nein** (geparkt) |
| Client Apply Positionen | **BO4** |
| Lager Match/Neuanlage | **BO5** |
| UI-Aufruf nach OCR | **BO3d** |

### Client-Erwartung (BO3d, nicht BO3b)

Nach Textlayer/OCR (und wenn keine vollständigen E-Rechnung-Zeilen): optional `fetch` dieses Endpoints; Status ohne Plan-Codes („Assistenz ordnet den Text…“ / bei Soft-Fail Hints behalten).

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
| [`KICHEL-PHASE2.md`](KICHEL-PHASE2.md) | Ollama-Regel Formulierung / Dev-Proxy |
| [`SUPERKICHEL-GEX-PLAN.md`](SUPERKICHEL-GEX-PLAN.md) | SKC1 `llm.ganz-soft.de` |

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
| 2026-11-27 | **BO2d ✅** Fixture-XML CII/UBL unter testdata · Schema-Selftest · BO2 Block fertig |
| 2026-11-27 | **BO3a ✅** Ist Ollama/Kichel §10 (`kichel.local.php` · `llm.ganz-soft.de` · kein Beleg-Extrakt noch) |
| 2026-11-27 | **BO3b ✅** Spec `POST /api/voucher?action=voucher_extract` §11 (kein Chat, Soft-Fail, BO2-JSON) |
| 2026-11-27 | **BO3c ✅** `VoucherExtractService` + `VoucherApi` Zweig + `KichelOllamaClient::generate` · Soft-Fail Selftest |
| 2026-11-27 | **BO3d ✅** Client `enrichWithAssistenz` → `voucher_extract` nach OCR/Text; E-Rechnung-Zeilen überspringen |
| 2026-11-27 | **BO3e ✅** Fixture `invoice-ocr-sample.txt` + GoBD-Scan (kein stilles Buchen/Lager/Auto-Apply) · `beleg-ocr-bo3e-gobd-selftest.php` · BO3-Block fertig |
| 2026-11-27 | **BO4a ✅** Ist Split/`items` vs. OCR-Apply §12 — Ausgabe nur `lines[]`; Items nur Erlös; Apply füllt keine Positionen |
| 2026-11-27 | **BO4b ✅** Apply → `applyOcrExtractLines` (Ausgabe=Splits, Erlös=items, Konto/Artikel leer) |
