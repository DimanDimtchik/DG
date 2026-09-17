# Plan: Lager-Reservierung, Beleg-Automatik & Einkaufsliste

Stand: **2026-09-17** · Status: **Phase 1–4 umgesetzt**  
Bezug: [`LAGER-WIRTSCHAFT.md`](LAGER-WIRTSCHAFT.md) · Belegkette [`BUCHHALTUNG-BELEGKETTE.md`](BUCHHALTUNG-BELEGKETTE.md)

---

## 1. Zielbild (Fach)

Bei **Angebot, Lieferschein, Rechnung, Gutschrift** soll der Lagerbestand nachvollziehbar und automatisch mitlaufen — ohne Doppelabzug in der Belegkette.

Anzeige je Artikel (Beispiel):

| Begriff | Beispiel | Bedeutung |
|---------|----------|-----------|
| **Bestand** (physisch / buchmäßig) | 100 | `stock_qty` wie heute |
| **Reserviert** | 12 | durch Angebote (ggf. AB) gebunden |
| **In Auslieferung** | 8 | verpackt / Lieferschein, noch nicht „versendet“ abgeschlossen |
| **Verfügbar** | 80 | Bestand − Reserviert − In Auslieferung |
| **Mindestmenge** | z. B. 20 | schon vorhanden (`min_stock`) |
| **Nachbestellen** | Link | Einkaufsliste / Lieferanten-URL |

Zusätzlich: **Einkaufsdaten am Artikel** (mehrere Quellen), **Admin-Regeln** (selbst bestellen vs. Einkaufsliste), **Einkaufsliste** + **Ignorierliste** unter Artikel & Leistungen.

---

## 2. Ist-Zustand (kurz)

| Thema | Heute |
|-------|--------|
| Bestand | `stock_qty`, `min_stock`, `track_stock` (Migration 065) |
| Beleg → Lager | `StockMovementService::syncForVoucher` — **Lieferschein** und buchbare Verkaufsbelege (−), Einkauf/Gutschrift (+); **Angebot wirkt nicht** |
| Doppelbuchung | Rechnung überspringt Abzug, wenn Kette schon Lieferschein hat |
| Reservierung | nur Platz-„fest/flexibel“, **keine Mengen-Reservierung** |
| Einkaufsstamm | fehlt (kein Lieferant/Preis/Shop am Artikel) |
| Einkaufsliste / Ignore | fehlt |

---

## 3. Fachregeln (vorgeschlagen)

### 3.1 Belegarten → Lagerwirkung

| Beleg | Wirkung auf `stock_qty` | Separater Pool | Hinweis |
|-------|-------------------------|----------------|---------|
| **Angebot** | keine | **+ Reserviert** | ab Status *Versendet* oder *Angenommen* (Admin-Option); Entwurf = keine Reserve |
| **Auftragsbestätigung** | keine | Reserve vom Angebot **übernehmen/ersetzen** (gleiche Positionen) | Kette: Angebot → AB |
| **Lieferschein** | **− Bestand** (wie heute) | Reserve **auflösen**; optional Status **In Auslieferung** bis „versendet“ | Kein zweites − bei Folge-Rechnung |
| **Rechnung / Teil- / Schlussrechnung** | − nur wenn **kein** LS in der Kette | sonst nur Info | bestehende Skip-Logik behalten |
| **Gutschrift** (Kunden) | **+ Bestand** (wie heute) | ggf. Reserve/Auslieferung korrigieren | |

**Wichtig (GoBD):** Physischer Abgang = Lieferschein (oder Rechnung ohne LS). Angebot darf den Buchbestand nicht „wegbuchen“, nur reservieren.

### 3.2 „In Auslieferung“

Zwei Varianten (Entscheidung vor Bau):

- **A (empfohlen, schlank):** Lieferschein mit Dokumentstatus ≠ final versendet/abgerechnet → Menge zählt als *In Auslieferung*; physischer `stock_qty`-Abzug erst bei Status „Versendet“ **oder** sofort bei LS-Erstellung (heutiges Verhalten) und „In Auslieferung“ nur Anzeige bis Versand-Status.
- **B:** Eigener Status „verpackt“ am LS + manuelles „Versendet“ löst endgültigen Abzug aus.

**Empfehlung A-Variante „Abzug bei LS wie heute“:**  
`stock_qty` sinkt bei LS-Speichern (nicht-Entwurf). Anzeige *In Auslieferung* = Summe offener LS-Positionen mit Status vor „Abgerechnet/Versendet“. Verfügbar = Bestand − Reserviert (bereits abgezogene LS-Mengen sind schon aus Bestand raus → *In Auslieferung* ist Teilmenge des bereits abgezogenen oder paralleler Pool — **klar definieren**).

**Klare Rechenformel (Empfehlung):**

```
physisch_buch = stock_qty                    // Bewegungen inkl. LS/Rechnung/WE
reserviert    = Summe offener Angebots-/AB-Reservierungen
in_auslief    = Summe LS-Positionen Status „verpackt / unterwegs“ (optionaler Pool)
verfuegbar    = physisch_buch - reserviert - in_auslief
```

Wenn LS **sofort** `stock_qty` mindert, dann `in_auslief` **nicht nochmals** von verfügbar abziehen (sonst Doppelzählung). Dann:

```
verfuegbar = stock_qty - reserviert
in_auslief = nur Anzeige „davon bereits entnommen, noch nicht zugestellt“
```

→ **Phase 1:** `verfuegbar = stock_qty − reserviert`; `in_auslief` = Info-Badge aus offenen LS.  
→ **Phase 2:** optional getrennter Pack-Status ohne sofortigen Abzug.

### 3.3 Unterbestand / Warnung beim Belegerstellen

Beim Speichern von Angebot/LS/Rechnung:

1. Prüfen: benötigte Menge vs. `verfügbar` (+ ggf. kommende WE).
2. Admin-Einstellung:
   - **Nur warnen** (Speichern erlaubt)
   - **Blockieren** unter Mindest-/Nullbestand
3. Fehlmenge → Eintrag **Einkaufsliste** (wenn nicht ignoriert).
4. Optional: Button/Link **Nachbestellen** (Shop-URL der bevorzugten Einkaufsquelle).

„Selbständig bestellen“ in Phase 1 = **Einkaufsliste + Öffnen der Bestell-URL** (kein Lieferanten-API-Zwang). API-Hooks später.

---

## 4. Datenmodell (neue Migrationen, Vorschlag ab **079**)

### 4.1 Mengen-Pools (kein zweites `stock_qty`)

**Option empfohlen:** Tabelle `dg_stock_reservations`

| Spalte | Zweck |
|--------|--------|
| `id`, `article_id`, `voucher_id`, `voucher_item_id` | Bezug |
| `quantity` | reservierte Menge |
| `status` | `active` / `released` / `consumed` |
| `created_at`, `updated_at` | |

Auslieferung: aus `dg_vouchers` + Items + `document_kind=delivery_note` + Status ableiten **oder** kleine Tabelle `dg_stock_allocations` — Phase 1 eher **ableiten**, keine Extra-Tabelle.

### 4.2 Einkaufsquellen (n:m am Artikel)

`dg_article_purchase_sources`

| Spalte | Zweck |
|--------|--------|
| `article_id` | Artikel |
| `supplier_contact_id` | optional Kontakt (Rolle Lieferant) |
| `supplier_name` | Freitext-Fallback |
| `purchase_price` | EK netto |
| `currency` | default EUR |
| `order_url` | Shop / Bestellquelle |
| `external_sku` | Lieferanten-Artikelnr. |
| `is_preferred` | 1 = Standard für „Nachbestellen“ |
| `sort_order`, `note` | |

### 4.3 Einkaufsliste & Ignore

`dg_purchase_list_items`

| Spalte | Zweck |
|--------|--------|
| `article_id` UNIQUE | |
| `reason` | `below_min` / `missing_for_voucher` / `manual` |
| `suggested_qty` | Vorschlag |
| `source_voucher_id` | optional Auslöser |
| `status` | `open` / `ignored` / `ordered` / `done` |
| `ignored_at`, `ignored_by` | |

Ignorierte = `status='ignored'` (gleiche Tabelle, Filter in UI) — **kein** zweites Stammdaten-System.

### 4.4 Admin-Einstellungen

In `dg_settings` / Settings-Registry Tab **Lager / Einkauf**:

| Key | Bedeutung |
|-----|-----------|
| `stock_reserve_on_offer` | ab welchem Dokumentstatus |
| `stock_shortage_policy` | `warn` / `block` |
| `stock_shortage_action` | `purchase_list` / `purchase_list_and_open_url` |
| `stock_show_in_transit` | Anzeige In-Auslieferung |

---

## 5. UI / UX

### 5.1 Artikel (erweitert)

- Bestandskarte: **Bestand | Reserviert | In Auslieferung | Verfügbar | Mindestmenge**
- Button/Link **Nachbestellen** (bevorzugte Quelle)
- Abschnitt **Einkaufsquellen** (CRUD Mehrfach)

### 5.2 Artikel & Leistungen — Unterbereich

Neue Subtabs (o. ä.):

1. Katalog (bestehend)
2. **Einkaufsliste** — `status=open` (unter Min / Fehlmenge)
3. **Ignoriert** — `status=ignored`  
   Aktionen: Ignorieren ↔ Wieder aktivieren; optional „Bestellt markieren“

### 5.3 Beleg-Editor

- Bei Positionswahl Live-Hinweis Verfügbarkeit / Mindestmenge
- Speichern: Sync Reserve bzw. Bewegungen + ggf. Einkaufslisten-Eintrag

### 5.4 Lager-Übersicht

Spalten erweitern analog Artikel-Bestandskarte.

---

## 6. Technik / Code-Schnittstellen

| Neu / Anpassung | Ort |
|-----------------|-----|
| `StockReservationService` | anlegen, freigeben, verbrauchen bei LS |
| `StockAvailabilityService` | `availableQty()`, Anzeige-DTO |
| `StockMovementService` | Angebot **nicht** in `stock_qty`; Reserve separat; LS/Rechnung/Gutschrift wie heute + Consume |
| `ArticlePurchaseSourceRepository` | CRUD Quellen |
| `PurchaseListService` | rebuild from min_stock + voucher shortage; ignore/activate |
| MigrationRunner | 079+ Heuristiken |
| Settings-Tab | `tab-lager-einkauf.php` o. ä. |
| Selftest | `bin/stock-selftest.php` erweitern |

**Idempotenz:** Reservierungen wie Bewegungen bei Beleg-Speichern neu aufbauen (`delete+insert` pro Beleg) oder diff — analog `syncForVoucher`.

---

## 7. Phasen (Durchführung)

### Phase 0 — Festlegungen (kurz, vor Code)

1. Reserve ab welchem Angebots-Status?
2. `in_auslief` nur Anzeige vs. eigener Pool?
3. Unterbestand: warnen vs. blockieren (Default)?
4. „Selbst bestellen“ = URL+Liste (Phase 1) bestätigt?

### Phase 1 — Kern Bestandssicht & Reserve (MVP) — **umgesetzt 2026-09-16**

1. [x] Migration `dg_stock_reservations` (079)  
2. [x] `StockAvailabilityService` + Anzeige Artikel + Lager-Übersicht  
3. [x] Angebot/AB → Reserve sync ab Versendet/Angenommen; Storno/Löschen → freigeben  
4. [x] LS / Rechnung ohne LS → Reserve consume; Warnung bei Unterbestand (nicht blockierend)  
5. [x] Selftest erweitert  

### Phase 2 — Einkaufsstamm & Nachbestellen — **umgesetzt 2026-09-16**

1. [x] Migration `dg_article_purchase_sources` (080)  
2. [x] UI am Artikel (Mehrfach-Quellen)  
3. [x] Link Nachbestellen (bevorzugte URL) in Lager + Artikelliste  

### Phase 3 — Einkaufsliste & Ignore — **umgesetzt 2026-09-17**

1. [x] Migration `dg_purchase_list_items` (081)  
2. [x] Auto-Einträge: `stock_qty`/`available` unter `min_stock` (bei CRM-Zugriff Artikel/Lager) + Fehlmenge aus Beleg  
3. [x] UI Subtabs Einkaufsliste / Ignoriert unter Artikel & Leistungen  
4. [x] Admin: shortage_policy + shortage_action (Einstellungen → Lagerstruktur → Einkauf)  

### Phase 4 — Feinschliff — **umgesetzt 2026-09-17**

1. [x] In-Auslieferung-Anzeige aus LS-Status (`sent`, nicht Entwurf/Abgerechnet)  
2. [x] Beleg-Editor Live-Hinweis Verfügbarkeit / Min bei Artikelwahl (`article_search` + data-Attribute)  
3. [x] Doku `LAGER-WIRTSCHAFT.md` + Plan aktualisiert  
4. [ ] Optional: Lieferanten-API-Hooks (bewusst später)  

---

## 8. Abgrenzung (bewusst später)

- Echte Lieferanten-API / Auto-PO an Amazon etc.
- Chargen / MHD / Filial-Lager
- Shop-Bestand (`shop.ganz-soft.de`) Sync
- Automatische SKR-Warenbestandsbuchung

---

## 9. Risiken & Prüfbarkeit

| Risiko | Gegenmaßnahme |
|--------|----------------|
| Doppelabzug LS+Rechnung | bestehende `shouldSkipIncomeStockSync` behalten + Tests |
| Reserve + LS doppelt zählen | Verfügbarkeitsformel dokumentieren + Selftest |
| Alt-Artikel fluten Einkaufsliste | Ignore-Liste + manuelles Ignorieren |
| GoBD | Jede Mengenänderung über Reservation/Movement mit Beleg-ID |

---

## 10. Nächster Schritt

Phase 1–4 sind umgesetzt. Optional später: Lieferanten-API-Hooks, harte Speichern-Sperre bei `shortage_policy=block` (Transaktion/Preflight).  
Akademie-Clip Lager/Einkauf nachziehen, wenn UI stabil ist.
