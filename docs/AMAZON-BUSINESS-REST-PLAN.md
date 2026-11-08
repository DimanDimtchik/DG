# Amazon Business Stufe 2 — Rest (tokenarm)

> **Eine Chat-Session = eine Mini-ID.** Ziel: offene Blöcke C–F ohne teuren Agent.  
> Fachplan: [`AMAZON-BUSINESS-STUFE2.md`](AMAZON-BUSINESS-STUFE2.md) · Klassen schon da: `AmazonBusinessSettings` / `Auth` / `Client`

Stand: **2026-11-08** · Branch-Empfehlung: `cursor/amazon-…`

---

## Warum tokenarm (Free-/Hobby-Plan)

| Regel | Bedeutung |
|-------|-----------|
| **Kein Explorer-Chat** | Kein „lies ganz Lager/Index“ — nur Datei(en) der Mini-ID |
| **Kein API-Rewrite** | Client (`placeOrder` / `orderDetails`) **nicht** neu erfinden |
| **Kein Onboarding im Agent** | Amazon-Freigabe / SPP / Refresh-Token = **Mensch** (🟢 lokal Doc) |
| **1 ID = 1 Commit-fähig** | Nach Schritt: kurzer Selftest / PHP-Lint der genannten Datei |
| **Credentials nie committen** | Nur Settings-UI / Instanz-DB |

**Chat-Prompt-Vorlage (kopieren):**  
`Amazon Stufe-2 Rest A0–A5 Code ✅. Nächster Schritt Mensch: Onboarding / Live-Checkliste M1–M5 in docs/AMAZON-BUSINESS-REST-PLAN.md; danach TODOS.md Amazon auf [x].`

**Nicht im Agent-Chat:** ganzen `index.php` umbauen, Sandbox-E2E gegen Amazon live, Rechnung-Import, Stufe-3-Cron.

---

## Token-Stufen

| Symbol | Wann | Typisch |
|--------|------|---------|
| 🟢 | **Lokal / Free** — Doc, 1 View-Hinweis, 1 Migration, Guard/Lint | Standard |
| 🟡 | **Kurz-Agent** — 1 Service + 1 POST-Zweig, klarer Diff | Sparsam |
| 🔴 | **Teuer** — Live-Amazon-E2E, großes UI-Review, Onboarding-Portal | Auf Free vermeiden / Mensch |

---

## Ist-Stand (2026-11-08)

| Block | Stand |
|-------|--------|
| A Einstellungen UI + Store | ✅ `AmazonBusinessSettings`, Lagerstruktur → Einkauf |
| B API-Client + Auth | ✅ Skelett `AmazonBusinessAuth` / `AmazonBusinessClient` |
| C Artikelstamm-Hinweise | ✅ ASIN-Hinweis + Amazon-Lieferant Soft-Hinweis |
| D Einkaufsliste-Button | ✅ POST + Button `canOrderItem` + Order-ID |
| E Persistenz Order-Ref | ✅ Migration 142 + `markAmazonOrdered` |
| F Rechte / CSRF / Deploy-Hinweise | ✅ gleicher Gate `userCanManageArticleCatalog` + CSRF |
| Amazon Developer-Zugang | 🔴 Mensch parallel (Tage–Wochen) |

Migration: **`142_purchase_list_amazon.sql`** (082 war schon KDV; Stand geprüft 2026-11-08).

---

## Abhackplan (Mini-IDs)

Status: `[ ]` offen · `[~]` angefangen · `[x]` erledigt

### A0 — Doc / Inventar (🟢)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| A0a | Dieser Rest-Plan + Verweis in `AMAZON-BUSINESS-STUFE2.md` / `TODOS.md` | 🟢 | [x] 2026-11-08 |
| A0b | Nächste freie Migrationsnummer lokal prüfen (`ls database/migrations/`) | 🟢 | [x] → 142 |

---

### A1 — Persistenz E (🟢/🟡) — **zuerst**, UI braucht Spalten

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| A1a | Migration `NNN_purchase_list_amazon.sql`: `external_order_id`, `external_order_channel`, optional `external_order_payload` / `external_ordered_at` | 🟢 | [x] 2026-11-08 `142_purchase_list_amazon.sql` |
| A1b | `PurchaseListRepository`: Felder lesen/schreiben (nur diese Methoden) | 🟡 | [x] 2026-11-08 normalize + setExternalOrder |
| A1c | `PurchaseListService::markOrdered` erweitern **oder** `markAmazonOrdered(...)` — Order-ID + Channel setzen | 🟡 | [x] 2026-11-08 markAmazonOrdered |

**Prompt A1a:**  
`Nur Mini-ID A1a laut docs/AMAZON-BUSINESS-REST-PLAN.md. Nur neue Migration unter database/migrations/. Kein PHP. Nummer vorher prüfen.`

---

### A2 — Order-Service (🟡) — **eine Klasse**

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| A2a | Neu: `AmazonBusinessOrderService` — ASIN aus preferred source, `placeOrder`, Fehler als klare Message | 🟡 | [x] 2026-11-08 |
| A2b | Autoload-Eintrag + kurzer PHP-Lint | 🟢 | [x] 2026-11-08 |

**Prompt A2a:**  
`Nur Mini-ID A2a laut docs/AMAZON-BUSINESS-REST-PLAN.md. Nur neue Datei src/Inventory/AmazonBusinessOrderService.php (+ Autoload-Zeile). Nutzt vorhandenen AmazonBusinessClient. Kein View.`

---

### A3 — Einkaufsliste UI D (🟡, splitten)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| A3a | POST-Handler `purchase_list_amazon_order` in bestehendem Purchase-List-POST-Block (`index.php` — **nur der Block**, kein Refactor) | 🟡 | [x] 2026-11-08 |
| A3b | View Einkaufsliste: Button „Bei Amazon Business bestellen“ nur wenn ready + ASIN (`tab-calendar-articles.php` list=purchase) | 🟡 | [x] 2026-11-08 |
| A3c | Flash Erfolg/Fehler + Anzeige Order-ID in Liste | 🟢 | [x] 2026-11-08 (Flash=A3a; Order-ID unter Nachbestellt) |

**Prompt A3b:**  
`Nur Mini-ID A3b laut docs/AMAZON-BUSINESS-REST-PLAN.md. Nur views/settings/tab-calendar-articles.php (Purchase-List-Abschnitt). Katalog-Buttons. Kein Refactor.`

---

### A4 — Artikelstamm C (🟢/🟡)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| A4a | Feldhinweis ASIN an Einkaufsquellen-UI (wo `external_sku` gepflegt wird) | 🟢 | [x] 2026-11-08 |
| A4b | Optional: Erkennung Lieferant enthält „Amazon“ → Hinweis (kein Pflicht-Flag) | 🟢 | [x] 2026-11-08 |

---

### A5 — Rechte / Abschluss F (🟢)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| A5a | Rechte: gleicher Gate wie bestehende Purchase-List-Aktionen | 🟢 | [x] 2026-11-08 `DepartmentAccess::userCanManageArticleCatalog` (kein Extra-Gate) |
| A5b | Kurz-Selftest oder manueller Checkliste-Haken in diesem Plan | 🟢 | [x] 2026-11-08 `bin/amazon-business-stufe2-selftest.php` |
| A5c | `TODOS.md` Amazon-Zeile auf `[~]`/`[x]` je nach Live-Test | 🟢 | [x] 2026-11-08 `[~]` (kein Live-E2E; [x] nach Onboarding) |

---

## Empfohlene Free-Plan-Reihenfolge

1. **A0b + A1a** lokal (Migration)  
2. **A1b → A1c** ein Chat  
3. **A2a + A2b** ein Chat  
4. **A3a** allein (index-POST — teurer Kontext → Dateiabschnitte nennen)  
5. **A3b → A3c**  
6. **A4a** lokal  
7. **A5**  

**Parallel Mensch (🔴):** Amazon Questionnaire / SPP / Refresh-Token — ohne das kein echter E2E; Code kann mit „nicht ready“-UI trotzdem landen.

**Vermeiden auf Free:** Live-Bestellung debuggen, Client neu schreiben, Beleg-Import, Cron-Stufe-3.

---

## Parent-Übersicht

| ID | Thema | Status |
|----|--------|--------|
| A0 | Doc / Inventar | [x] |
| A1 | Persistenz | [x] |
| A2 | Order-Service | [x] |
| A3 | Einkaufsliste UI | [x] A3a–A3c |
| A4 | Artikelstamm | [x] A4a–A4b |
| A5 | Rechte / Abschluss | [x] A5a–A5c |

---

## Checkliste / Selftest (A5b)

**Automatisch (ohne Amazon-Live):**

```bash
php bin/amazon-business-stufe2-selftest.php
```

Prüft: Migration 142 · Order-Service · Autoload · `markAmazonOrdered` · POST `purchase_list_amazon_order` im Gate `DepartmentAccess::userCanManageArticleCatalog` · Button/`canOrderItem` · Order-ID-Anzeige · ASIN-/Amazon-Hinweise.

| Haken | Manuell (Mensch / Dev) | Status |
|-------|------------------------|--------|
| M1 | Migration 142 auf Dev gelaufen | [ ] |
| M2 | Amazon Settings ready (Credentials) | [ ] 🔴 Onboarding |
| M3 | Testartikel: bevorzugte Quelle + ASIN | [ ] |
| M4 | Button nur bei ready+ASIN sichtbar | [ ] |
| M5 | Sandbox-/Live-Bestellung ok, Order-ID unter Nachbestellt | [ ] 🔴 |
| M6 | Rolle ohne Artikelkatalog: POST wirkungslos / kein Menü | [~] Gate = `userCanManageArticleCatalog` (A5a; Selftest) |

---

## Nicht vergessen

- Keine Secrets in Git  
- Endnutzer-Texte: keine Plan-Codes (`A3a`) in der UI  
- Belegpflicht bleibt — Amazon-Rechnung separat  
- Nach UI: Katalog-Klassen / Guard wenn Views geändert  
