# K0 — fiskaly Anbieterzugang klären

> Bezug: [`REGISTERKASSE-TSE-PLAN.md`](REGISTERKASSE-TSE-PLAN.md) · Stand **2026-11-07**  
> Token: meiste Punkte 🟢/🟡 — keine teuren Refactors hier.

Ziel: TEST-Integration starten, LIVE-/Vertragsmodell für DG CRM als Kassenanbieter klären, Limits und Abrechnung verstehen.

---

## Kurzfassung

| Thema | Ergebnis |
|-------|----------|
| Testzugang | Kostenlos über **fiskaly HUB** (TEST/Sandbox), keine Live-Belege |
| API | **SIGN DE v2** — nur V2+ für Neuprojekte |
| TEST-URL | `https://kassensichv-middleware.fiskaly.com/api/v2` |
| LIVE-URL | `https://kassensichv.fiskaly.com/api/v2` |
| Preise Endkunde | fiskaly veröffentlicht keine Endkunden-Listenpreise; Partner typ. **ca. 6–14 EUR netto/Monat je Kasse/TSS** |
| Preise DG (Integrator) | **Individueller Vertrag** mit fiskaly Sales nach „Go Live“ |
| Limits (Richtwerte) | Auth **10/min**, Signieren **200/min**, Management **60/min** — Token cachen |
| Multi-Filiale | **1 TSS pro Standort**, **1 Client pro Kasse/Terminal** |

---

## Abhackliste K0 (Mini-IDs)

### A — Sofort (Entwicklung, kostenlos) → weitgehend erledigt

- [x] **K0a** Account auf [hub.fiskaly.com](https://hub.fiskaly.com)
- [x] **K0a** Test-Organisation
- [x] **K0b** TEST-Key + Secret sichern (nicht Git)
- [x] **K0c** Cursor Cloud Secrets: `FISKALY_SIGN_DE_API_KEY`, `FISKALY_SIGN_DE_API_SECRET`
- [x] **K0d** `php bin/fiskaly-auth-smoke.php` → `OK: fiskaly TEST-Auth erfolgreich`
- [x] **K0e** Test-TSS → `INITIALIZED`
- [x] **K0f** Test-Client
- [x] **K0g** Signierte Test-Transaktion (bzw. Produktpfad Beleg-Barzahlung auf Branch `cursor/tse-kassenbeleg-1549`)

### B — Geschäftsmodell (ohne Code, 🟢)

#### K0h — Entscheidung (2026-11-07)

| Punkt | Festlegung |
|-------|------------|
| Rolle | **DG = POS-/Kassenanbieter** (fiskaly-Kunde/Integrator). Jede CRM-Instanz = Merchant/Mandant mit eigenen Filialen/Kassen. |
| Zielmodell | **Variante 2 — Integrator / Weiterlizenzierung** (SaaS). Passt zu Multi-Tenant, einheitlichem Onboarding und D9 (Secrets pro Instanz). |
| Übergangsoption | **Variante 1** bleibt technisch möglich: Instanz hinterlegt eigene fiskaly-Keys in Einstellungen→TSE (bereits so gebaut). Nutzen, bis LIVE-Vertrag steht oder für Sonderkunden. |
| fiskaly-Org | **Eine Org je Umgebung (TEST / später LIVE) bei DG**; darunter TSS/Clients je Filiale/Kasse der Instanz. Nicht: eine fiskaly-Org hart im Produktcode pro Kundendomäne. Später ggf. Management-API für viele Mandanten — kein Blocker für K1. |
| Go Live / Sales | **Noch offen.** Im HUB „Go Live“ anstoßen → Ansprechpartner + Vertrag hier eintragen: |
| Sales-Name | _noch offen_ |
| Sales-Kontakt | _noch offen (E-Mail/Termin)_ |
| Vertragsstand | TEST ok · LIVE-Vertrag **nicht** abgeschlossen |

- [x] **K0h** Rolle bestätigt (DG = Kassenanbieter / Integrator-Ziel)
- [x] **K0h** Abrechnungsmodell: **Variante 2 Ziel**, Variante 1 Übergang
- [~] **K0h** Sales-Ansprechpartner nach HUB „Go Live“ nachtragen (Zeile oben)
- [x] **K0i** Preislogik + Endkunden-Empfehlung (siehe unten; Einkauf nach Sales nachziehen)
- [ ] Optional mitdenken: **DSFinV-K DE**, **SUBMIT DE**, **SAFE** — mit K0i-Add-ons; Bestellung erst nach LIVE-Bedarf

#### K0i — Preise (2026-11-07, Arbeitshypothese)

Alle Beträge **netto**. Keine Bestellung / kein LIVE. Nach fiskaly-Sales-Angebot Zeile „Einkauf“ ersetzen.

**A) Einkauf DG bei fiskaly (noch unbekannt)**

| Position | Annahme bis Angebot | Nach Sales eintragen |
|----------|---------------------|----------------------|
| pro TSS / Monat | Markt-Richtwert Partner **ca. 6–10 €** | _€_ |
| pro Client (Kasse) / Monat | oft in TSS-Preis oder **+ ca. 0–4 €** | _€_ |
| Signaturen / Monat inkl. | typ. Kontingent im Paket; Überzug extra | _Stück / €_ |
| DSFinV-K / SUBMIT / SAFE | optional, extra | _€_ |

**B) Verkauf an Endkunde (DG-Produkt) — Empfehlung**

Abrechnungseinheit für den Kunden: **aktive Kasse (Client)**, nicht „TSS“ (Fachbegriff).  
1 Filiale = 1 TSS (technisch); Preis sichtbar = **je Kasse**.

| Paket | Inhalt | Empf. Endkundenpreis | Hinweis |
|-------|--------|----------------------|---------|
| **TSE-Kasse** | 1 Kasse inkl. Cloud-TSE-Signatur (SIGN DE), Test→Live nach Freigabe | **14,90 € / Monat / Kasse** | Untergrenze Marktrahmen 6–14 € Partner + DG-Marge/Support |
| **TSE-Kasse Jahr** | wie oben, Jahresabo | **11 × Monat** (= 1 Monat gratis, wie CRM-Shop) | konsistent zu `SHOP-TODO` |
| **Filial-Start** | erste Kasse am Standort | gleicher Kassenpreis | kein Aufpreis „TSS“ an Endkunde |
| **Weitere Kasse** gleicher Standort | weiterer Client | gleicher Kassenpreis | |
| Variante 1 (Kunde eigener fiskaly-Vertrag) | nur Anbindung im CRM | **0 € TSE-Umlage** oder optional **4,90 € / Kasse** „TSE-Anbindung“ | bis Integrator-Vertrag / Sonderfälle |

**C) Spätere Add-ons (nicht jetzt verkaufen)**

| Add-on | Empf. grob | Wann |
|--------|------------|------|
| DSFinV-K Export (Produktfeature) | in TSE-Kasse **enthalten**, sobald K17 steht | kein Extra, Differenzierung |
| §146a-Meldung (SUBMIT) | **+ 2,90 € / Kasse / Monat** oder einmalig Setup | erst K19 + LIVE |
| Archiv SAFE | nur wenn fiskaly berechnet → 1:1 + kleiner Aufschlag | nach Bedarf |

**D) Kalkulationsregel (nach Sales)**

```text
Mindest-VK je Kasse = Einkauf (anteilig TSS+Client+Signaturen) + 4 € Support/Marge
Ziel-VK = max(14,90 €; Mindest-VK)
```

Wenn Einkauf z. B. 9 €/Kasse → VK 14,90 € bleibt ok.  
Wenn Einkauf ≥ 12 €/Kasse → VK auf **19,90 €** anheben (Entscheidung dann, nicht jetzt).

**E) Was K0i nicht ist**

- Kein verbindliches Angebot an Kunden vor LIVE-Vertrag.
- Kein Softcode von Preisen im CRM-Kern (Preise gehören Shop/KDV/Vertrag, nicht `src/`).

### C — Technische Grenzen (Doc, 🟢)

- [x] **K0j** Rate Limits / Token-Cache / 429 — Betriebshinweis unten
- [x] Access Token ~24 h / Refresh ~48 h — nicht pro Bon neu auth
- [x] TEST und LIVE getrennt
- [x] Pro Filiale: TSS → Admin-PIN → `INITIALIZED` → Client pro Kasse
- [x] Org-Struktur: eine DG-fiskaly-Org je Umgebung; TSS/Client je Filiale/Kasse der CRM-Instanz (siehe K0h)

#### K0j — Betriebshinweis Rate-Limits & Auth (2026-11-07)

| Thema | Regel |
|-------|--------|
| Auth | **max. ca. 10/min** — Access-Token cachen, **nicht** vor jedem Bon neu einloggen |
| Signieren | **max. ca. 200/min** — reicht für normale Ladenkasse; Bursts (Massenimport) entkoppeln/drosseln |
| Management | **max. ca. 60/min** — TSS/Client anlegen, Status; nicht in Hot-Path Bonfluss |
| Header | Antwort-Header `X-RateLimit-*` beachten (Remaining/Reset), wenn Anbieter sie liefert |
| HTTP **429** | Kurz warten (Backoff, z. B. 1s → 2s → 4s, max. wenige Versuche), Vorgang **sichtbar** loggen; Bon nicht still verwerfen |
| Token-Laufzeit | Access ~**24 h**, Refresh ~**48 h** — vor Ablauf refreshen; bei 401 einmal neu auth, dann Request wiederholen |
| Ist-Code | `TseSignDeClient` hält Token nur **in der laufenden PHP-Anfrage**. Dauerhafter Cache (Datei/APCu/DB) = spätere Mini-ID unter K3, nicht K0 |

TEST und LIVE haben getrennte Keys/TSS — Limits gelten je Umgebung.

### D — Definition of Done K0

| Kriterium | Status |
|-----------|--------|
| TEST-Auth erfolgreich | [x] |
| ≥1 Test-TSS + Client + signierte Tx | [x] |
| Notiz Abrechnungsmodell + Sales | [~] K0h Modell fest · Sales-Zeile nach Go Live |
| Endkunden-Preisempfehlung | [x] K0i (Arbeitshypothese 14,90 €/Kasse; Einkauf nach Sales) |

**K0 Parent:** fachlich für Entwicklung weiter mit **K1a**. Formal „K0 zu“ erst wenn K0h Sales-Zeile steht (Go Live) — blockiert K1 nicht.

---

## Mapping auf DG CRM

```text
CRM-Instanz (Mandant)
  -> cash_location (Filiale)
      -> fiskaly TSS (1 pro Standort)
      -> cash_register (Kasse)
          -> fiskaly Client (1 pro Kasse)
          -> cash_receipt -> fiskaly Transaction
```

Übergang jetzt: Einstellungen → TSE + `dg_tse_transactions` an Beleg-Barzahlung (ohne `cash_*`-Tabellen) — siehe Plan **K9a/K9b**.

---

## TEST-Smoke

```bash
php bin/fiskaly-auth-smoke.php
```

Oder curl Auth gegen TEST-URL mit Key/Secret aus HUB (nie committen).

---

## Nächster Schritt nach K0h/K0i

**K1a** Spec `cash_locations` — siehe Plan, Token 🟢/🟡.
