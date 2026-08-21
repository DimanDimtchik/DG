# Shop (shop.ganz-soft.de) – Todo-Liste

> Ziel: Öffentlicher Verkauf von CRM-Tarifen auf **shop.ganz-soft.de**, nach Kauf automatische Kundenanlage über KDV  
> API (bereits vorhanden): `POST /api/kdv/provision` auf dem Master (`dg.ganz-om.de`)  
> Verwandt: [KDV-TODO.md](./KDV-TODO.md)

---

## Entscheidungen (fest, 2026-08-20)

| Punkt | Entscheidung |
|--------|----------------|
| Scope | SaaS-Shop für CRM-Lizenzen (nicht Kunden-Webshop im CRM) |
| Stack | Eigene leichte **PHP-App** auf `shop.ganz-soft.de` |
| Zahlung | **Nur Stripe** (Checkout / Subscriptions) |
| Markt | **Nur Deutschland**, MwSt. 19 % (kein EU-Reverse-Charge im MVP) |
| Laufzeiten | Monatlich **oder** jährlich |
| Jahresabo | **1 Monat gratis** → Jahrespreis = **11 × Monatspreis** |
| Preise | Unverändert von [ganz-soft.de/preise](https://ganz-soft.de/preise) (netto zzgl. 19 % MwSt.) |
| Preisquelle | `/preise` (nicht `/preis`) |

### Preise (netto / Monat)

| Shop-ID | Anzeigename | KDV-Tarif | Netto/Monat | Jahr (×11) |
|---------|-------------|-----------|-------------|------------|
| `starter` | Starter | `basic` | 29 € | 319 € |
| `business` | Business | `business` | 49 € | 539 € |
| `premium` | Premium | `enterprise` | 89 € | 979 € |

Anzeige wie Marketing-Seite: **zzgl. 19 % MwSt.** Stripe später mit Brutto (`netto × 1,19`).

---

## Klärung (vor Phase 1)

- [x] Scope: **SaaS-Shop** (CRM-Lizenzen)
- [x] Domain: `shop.ganz-soft.de` – eigene PHP-App
- [x] Tech-Stack: leichtes PHP (wie CRM)
- [x] Zahlung: nur Stripe
- [x] Markt/MwSt.: nur DE, 19 % inkl.
- [x] Jahresabo: 1 Monat gratis (×11)
- [x] Preise: unverändert lassen

---

## Phase 0: Angebot & Recht

- [x] Feature-Vergleich Starter / Business / Premium aus [ganz-soft.de/preise](https://ganz-soft.de/preise) übernommen
- [x] Laufzeiten: monatlich / jährlich (1 Monat gratis = ×11)
- [x] AGB Shop + Widerrufsbelehrung (Fernabsatz) + Datenschutzerklärung Shop (`/agb`, `/widerruf`, `/datenschutz`)
- [x] Impressum / AVV-Hinweis für Hosting-Kunden (`/impressum`)
- [ ] Anschrift / USt-Id in `shop/config/legal.php` vollständig eintragen
- [x] Im Kauf enthalten (laut Preisseite): Domain, E-Mail, SSL, Hosting, Backups, Updates, Support

---

## Phase 1: Shop-Grundgerüst

- [x] Projektstruktur unter `shop/` (Deploy-Ziel: `shop.ganz-soft.de`)
- [x] Landing: Nutzen CRM, kurze Feature-Liste, CTA zu Tarifen
- [x] Tarif-Übersicht (Vergleichstabelle) inkl. Monatlich/Jährlich-Toggle
- [x] Checkout-Seite: Firmendaten, Domainwunsch, Ansprechpartner, E-Mail, Telefon
- [x] Validierung Domain (Syntax)
- [x] Responsive Design, Marke ganz soft
- [x] Auf `shop.ganz-soft.de` deployen (WordPress ersetzt, DB geleert und für Shop reserviert)
---

## Phase 2: Zahlung (Stripe only)

- [x] Stripe Checkout / Subscriptions (monatlich + jährlich ×11) — `ShopStripe`, `/checkout/pay`
- [x] Testmodus + Live-Keys in lokaler Config (nicht im Repo) — `shop/config/stripe.example.php` → `stripe.local.php`
- [x] MwSt. 19 % DE: Brutto an Stripe (`unit_amount` inkl. MwSt.)
- [x] Erfolgreiche Zahlung → Webhook `/webhook/stripe` + Success-Handler `/checkout/success`
- [x] Abbruch `/checkout/cancel` mit Retry
- [ ] Rechnung/Beleg an Käufer (Stripe Invoice / Receipt) — Stripe sendet Receipts automatisch bei aktivierter Option
- [ ] Abo-Verlängerung / Kundenportal-Kündigung (Stripe Customer Portal)

**Aktivierung:** `shop/config/stripe.local.php` aus Example anlegen (Keys + `kdv_api_key`), Webhook auf `https://shop.ganz-soft.de/webhook/stripe` (Event `checkout.session.completed`), dann `deploy-shop.bat`.

---

## Phase 3: Anbindung KDV (Provision)

- [ ] Nach bezahltem Kauf: `POST …/api/kdv/provision` mit Bearer-API-Key
- [ ] Payload abgleichen: `company_name`, `domain`, `contact_*`, `tariff`, `billing_cycle`, `monthly_price`
- [ ] Fehlerbehandlung: Zahlung ok, Provision fehlgeschlagen → Alert an Admin + Retry-Queue
- [ ] Erfolgsseite: Install-URL / „Sie erhalten eine E-Mail“
- [ ] Idempotenz: doppelte Webhooks nicht doppelt provisionieren (z. B. Stripe `payment_intent` speichern)
- [ ] Logging: Shop-Bestellung ↔ `customer_id` in KDV

---

## Phase 4: Kundenkommunikation + SaaS-Konto

- [ ] Bestellbestätigung per E-Mail
- [ ] Provisionierungs-Mail mit Install-Link (falls nicht schon aus KDV)
- [ ] Willkommens-/Onboarding-Text (Login, erste Schritte)
- [x] **SaaS-Konto** `/konto` – Login gegen KDV-API, nur eigene Akte / Status / Sperrhinweis
- [x] Entsperr-Bitte mit Auto-Ablehnung (z. B. unbezahlte Rechnung → kein Mail)
- [ ] Support-Kontakt / Eskalation bei Domain-Problemen

---

## Phase 5: Admin & Betrieb

- [ ] Bestellübersicht (lokal im Shop oder nur in KDV sichtbar?)
- [ ] Manueller Re-Provision-Button bei Fehlern
- [ ] Monitoring: Webhook-Fehler, API 401/5xx
- [ ] Staging-Umgebung / Test-Kauf ohne echte Domain-Anlage
- [ ] Deploy-Pfad dokumentieren (All-Inkl, SSL, Secrets)

---

## Phase 6: Feinschliff

- [ ] Analytics nur mit Consent (wie CRM-Website)
- [ ] SEO Landing / Tarife
- [ ] Add-ons (später; Jahresabo ist MVP)
- [x] Sprache: nur DE

---

## Architektur-Hinweis: SaaS-Shop vs. Kunden-Webshop

Zwei getrennte Produkte – nicht in eine Codebasis zwingen:

| | **shop.ganz-soft.de (jetzt)** | **Kunden-Webshop (später im CRM)** |
|--|------------------------------|-------------------------------------|
| Käufer | Interessenten kaufen DG CRM | Endkunden des CRM-Kunden |
| Katalog | feste Tarife in `config/plans.php` | Artikel & Leistungen aus dem CRM |
| Nach Kauf | KDV-Provision einer Instanz | Bestellung / Beleg / Lager |

**Jetzt richtig vorbereiten, ohne umzubauen:** klare Module (Katalog, Checkout, Zahlung, Recht). Später den Kunden-Webshop als CRM-Modul neu anbinden und Zahlung/Checkout-Muster wiederverwenden – nicht den SaaS-Shop „umbiegen“.

**Tarifbilder:** Bilder auf ganz-soft.de erscheinen **nicht automatisch** im Shop. Pro Tarif `image_url` in `shop/config/plans.php` setzen (öffentliche URL). Optional später: Sync/API.

- [ ] Produktkatalog (Artikel, Preise, MwSt., Lager?)
- [ ] Warenkorb + Checkout auf öffentlicher Website
- [ ] Zahlung (PayPal/Stripe) → Beleg in Buchhaltung
- [ ] Bestellverwaltung im CRM-Menü
- [ ] Rechtstexte (bereits teilweise im LegalPageGenerator vorbereitet)

---

## Technische Skizze (SaaS-Shop)

```
Kunde → shop.ganz-soft.de (Landing/Checkout)
         → Zahlung (Stripe/PayPal)
         → Webhook Success
         → POST dg.ganz-om.de/api/kdv/provision
         → KDV legt Domain/DB/CRM an
         → E-Mail mit Install-URL
```

### Bereits vorhanden

| Baustein | Status |
|----------|--------|
| KDV Kundenanlage | teilweise |
| `KdvProvisionApi` | vorhanden |
| API-Key im KDV-Dashboard | vorhanden |
| shop.ganz-soft.de Frontend | **fehlt** |
| Zahlungsflow | **fehlt** |

---

## Empfohlene Reihenfolge zum Starten

1. ~~Klärung~~ erledigt  
2. Phase 1: PHP-Gerüst + Landing/Tarife/Checkout-UI (Dummy-Preise aus bestehender Liste)  
3. Phase 0 parallel: Rechtstexte  
4. Phase 2 + 3: Stripe → Provision = MVP  
5. Phase 4–5: Produktion  
