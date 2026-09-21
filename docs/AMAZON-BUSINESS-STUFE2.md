# Amazon Business — Stufe 2 (Halbautomatisch)

Stand: **2026-09-17** · Master: `dg.ganz-om.de`  
Bezug: Einkaufsliste (`PurchaseListService`, Migration `081`), Einkaufsquellen (`dg_article_purchase_sources`).

## Ziel

Aus der **Einkaufsliste** per Klick bei **Amazon Business** bestellen:

1. Admin bestätigt Menge am offenen Listeneintrag.
2. CRM ruft Amazon Ordering API auf (ASIN + Menge).
3. Bei Erfolg: Eintrag → Status `ordered`, Amazon-Bestellreferenz speichern.
4. **Kein** stiller Cron / kein Auto-Kauf ohne Klick (Stufe 3 später).

Kein Ersatz für die Lieferantenrechnung — Belegpflicht bleibt (Rechnung von Amazon Business manuell oder später Import).

---

## Developer-Zugang beantragen (Anleitung)

Amazon vergibt API-Zugang erst nach Freigabe — **nicht** selbst freischalten.

### 1. Zugang anfragen

1. Onboarding-Übersicht lesen: [docs.business.amazon.com — Onboarding overview](https://docs.business.amazon.com/docs/onboarding-overview)
2. **Amazon Business API Onboarding Questionnaire** ausfüllen (ca. 10–15 Min.).  
   Link steht in der Onboarding-Seite („Request access“ / Questionnaire).  
   Alternativ (laut Amazon-Release-Notes 2026): E-Mail an **ab-api-access-approvals@amazon.com** mit Firma, Use-Case und Region.
3. Im Fragebogen / der Mail beschreiben, z. B.:

> Wir betreiben ein eigenes CRM (private App) für unsere Firma.  
> Use-Case: Aus der internen Einkaufsliste (Artikel unter Mindestbestand) sollen Mitarbeiter per Klick Bestellungen bei Amazon Business auslösen (Ordering API).  
> Region: EU / Deutschland.  
> Kein Marketplace für Dritte — nur unser eigener Einkauf.

Rollen braucht ihr nicht auswendig nennen — Amazon mappt auf **Product Catalog** + **Order Placement**.

### 2. Nach Freigabe (E-Mail von Amazon)

1. **Amazon-Business-Konto** fertigstellen (falls noch nicht).  
2. **Solution Provider Portal (SPP)**-Konto anlegen / verifizieren (Identität).  
3. **Developer Profile** einreichen.  
4. **App-Client** im SPP anlegen → **Client-ID** und **Client-Secret**.  
5. App autorisieren → **Refresh-Token** erzeugen  
   (Anleitung: [Generate refresh token](https://docs.business.amazon.com/docs/generate-refresh-token)).  
6. Im Amazon-Business-Konto: Gruppe + Purchasing System „Ordering API“, Safeguards, Zahlungsmethode, Test→Active  
   (siehe [Ordering API — Customer onboarding](https://docs.business.amazon.com/docs/ordering-api)).

### 3. Im DG-CRM hinterlegen

Einstellungen → Lagerstruktur → **Einkauf** → Abschnitt **Amazon Business**:

- Integration aktiv  
- Region EU  
- Client-ID, Secret, Refresh-Token  
- Nutzer-E-Mail (Amazon-Business-User der Gruppe)  
- „Verbindung testen“ (holt Access-Token)

Credentials liegen nur in der Instanz-DB (`SettingsStore`), nicht im Git.

### Dauer

Freigabe und SPP-Prüfung oft **Tage bis Wochen** — länger als die CRM-Umsetzung.

---

## Bestehendes CRM (Anknüpfungspunkte)

| Baustein | Ort |
|----------|-----|
| Einkaufsliste UI | `views/settings/tab-calendar-articles.php` (`list=purchase`) |
| Status `open` → `ordered` | `PurchaseListService::markOrdered` / POST `purchase_list_ordered` |
| Lieferant + URL + SKU | `dg_article_purchase_sources` (`order_url`, `external_sku`, `supplier_name`) |
| Lager-Policy | `StockPurchaseSettings` (`stock_purchase`) |

**Konvention Stufe 2:** Bevorzugte Einkaufsquelle mit Lieferant/Label „Amazon Business“ (oder Flag `channel=amazon_business`) und **ASIN** in `external_sku`.

---

## Umsetzungsblöcke

### A — Einstellungen — **angelegt 2026-09-17**

Unter Einstellungen → Lagerstruktur → Einkauf:

- aktiv / Region / Max. Bestellwert  
- Client-ID, Client-Secret, Refresh-Token, Nutzer-E-Mail  
- „Verbindung testen“ (Token-Refresh)

Klassen: `AmazonBusinessSettings`, `AmazonBusinessAuth`, `AmazonBusinessClient` (placeOrder / orderDetails).

### B — API-Client — **Skelett 2026-09-17**

Token + Ordering-Aufrufe vorhanden. Bestell-Button an der Einkaufsliste folgt (Block D).

### C — Artikelstamm

In Einkaufsquellen-UI:

- Feldhinweis: „Bei Amazon: ASIN in Artikelnummer Lieferant / external_sku“  
- optional Checkbox „Amazon Business“ (oder Erkennung am Lieferantennamen)  
- Shop-URL weiter optional (Fallback Stufe 1: Deep Link)

### D — Einkaufsliste UI

Neben „Bestellen“ / Shop-Link:

- Button **„Bei Amazon Business bestellen“** nur wenn:
  - Amazon-Integration aktiv und Credentials ok  
  - bevorzugte Quelle hat ASIN  
- Klick: POST `purchase_list_amazon_order` mit `purchase_list_id` + Menge  
- Erfolg: wie bisher `ordered` + Anzeige Bestell-ID  
- Misserfolg: Liste bleibt `open`, Fehlertext

### E — Persistenz Bestellreferenz

Migration z. B. `082_purchase_list_amazon.sql`:

- `dg_purchase_list_items.external_order_id` VARCHAR (Amazon externalId / Order-Ref)  
- `external_order_channel` VARCHAR (`amazon_business`)  
- optional `external_order_payload` TEXT (gekürzt, ohne Secrets) für Prüfung  

GoBD: Nachvollziehbarkeit „wer / wann / welche ASIN / Menge / Antwortstatus“.

### F — Rechte & Deploy

- nur Rollen mit Lager/Einkauf-Bearbeitung  
- CSRF wie bestehende Purchase-List-POSTs  
- Entwickeln nur Master → `sync-crm-from-master.sh`  
- Credentials **pro Instanz** in lokaler Settings-DB, nicht im Sync-Code

---

## Ablauf (Sequenz)

```
Einkaufsliste (open)
  → Nutzer: Menge prüfen → „Bei Amazon Business bestellen“
  → AmazonBusinessOrderService
       · ASIN aus preferred purchase source
       · placeOrder(externalId=CRM-PL-{id}-{ts}, lineItems, expectations)
  → OK: markOrdered + external_order_id speichern
  → NOK: Flash, Status unverändert
  → Später: Wareneingang / „Erledigt“ wie heute
```

---

## Explizit nicht in Stufe 2

- Automatische Bestellung ohne Klick (Cron)  
- Multi-Lieferant-Split in einer API-Order  
- Rechnungs-PDF-Import von Amazon  
- Privatkunden-Amazon (nur Business)

---

## Testplan (nach Implementierung)

1. Credentials Sandbox/Prod in Einstellungen speichern, Status „verbunden“ prüfen  
2. Testartikel mit ASIN, auf Einkaufsliste (unter Min)  
3. Bestellen → Amazon-Konto zeigt Order; CRM → `ordered` + ID  
4. Safeguard-Abweichung → Ablehnung, Liste bleibt offen  
5. Artikel ohne ASIN → Button ausgeblendet / klare Meldung  
6. Instanz ohne Credentials → kein Button / Hinweis

---

## Aufwand (Grobrichtwert)

| Block | geschätzt |
|-------|-----------|
| A Einstellungen | 0,5–1 Tag |
| B API-Client + Auth | 1,5–2 Tage |
| C + D UI | 0,5–1 Tag |
| E Migration + Logging | 0,5 Tag |
| Test mit echtem Business-Konto | 0,5–1 Tag |
| **Summe** | **ca. 3,5–5,5 Tage** |

Abhängig von Amazon-Onboarding-Dauer (oft länger als der Code).

---

## Nächster Schritt (Umsetzung)

1. Amazon-Business-Developer-Zugang beantragen (parallel).  
2. Im Repo: Migration + `AmazonBusinessSettings` + Client-Skelett.  
3. Button auf Einkaufsliste verdrahten.  
4. Mit einer Test-ASIN End-to-End gegen Sandbox/Prod.

Wenn die Umsetzung starten soll: zuerst Block A+B (Credentials + Client), dann UI.
