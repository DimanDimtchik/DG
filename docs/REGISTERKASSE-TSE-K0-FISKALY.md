# K0 — fiskaly Anbieterzugang klären

> Bezug: [`REGISTERKASSE-TSE-PLAN.md`](REGISTERKASSE-TSE-PLAN.md) · Stand **2026-11-05**

Ziel: TEST-Integration starten, LIVE-/Vertragsmodell für DG CRM als Kassenanbieter klären, Limits und Abrechnung verstehen.

---

## Kurzfassung

| Thema | Ergebnis |
|-------|----------|
| Testzugang | Kostenlos über **fiskaly HUB** (TEST/Sandbox), keine Live-Belege |
| API | **SIGN DE v2** — nur V2+ für Neuprojekte |
| TEST-URL | `https://kassensichv-middleware.fiskaly.com/api/v2` |
| LIVE-URL | `https://kassensichv.fiskaly.com/api/v2` |
| Preise Endkunde | fiskaly veröffentlicht keine Endkunden-Listenpreise; Partner nennen typ. **ca. 6–14 EUR netto/Monat je Kasse/TSS** |
| Preise DG (Integrator) | **Individueller Vertrag** mit fiskaly Sales nach „Go Live“ |
| Limits (Richtwerte) | Auth **10/min**, Signieren **200/min**, Management **60/min** — Token cachen |
| Multi-Filiale | **1 TSS pro Standort**, **1 Client pro Kasse/Terminal** |

---

## Abhackliste K0

### A — Sofort (Entwicklung, kostenlos)

- [ ] Account auf [hub.fiskaly.com](https://hub.fiskaly.com) anlegen (E-Mail oder SSO)
- [ ] **Test-Organisation** anlegen (Adresse ausfüllen; Rechnungsadresse kann leer bleiben für TEST)
- [ ] Unter **Settings → API Keys** TEST-Key + Secret erzeugen — **Secret sofort sichern** (nur einmal sichtbar)
- [ ] Quickstart durchgehen: Auth → TSS → Client → Transaktion signieren  
  Quelle: [Quick Start SIGN DE](https://workspace.fiskaly.com/getting-started/quickstart/)
- [ ] Credentials **nicht** ins Git — nur Instanz-Config / Secrets (z. B. Cloud-Agent Secrets, `config/*.local.php`)

### B — Geschäftsmodell DG CRM klären (mit fiskaly Sales)

- [ ] Rolle bestätigen: DG als **POS-/Kassenanbieter (CUSTOMER)** mit **Endkunden (Merchants)** pro Instanz
- [ ] Abrechnungsmodell wählen:
  - **Variante 1:** Endkunde schließt TSE-Vertrag selbst (einfacher rechtlich, mehr Aufwand pro Mandant)
  - **Variante 2:** DG als Integrator mit **Weiterlizenzierung** an Endkunden (SaaS-Modell, Vertrag mit fiskaly nötig)
- [ ] Im HUB **„Go Live“** anstoßen → Sales kontaktiert euch für **LIVE-Vertrag** und Konditionen  
  Quelle: [Switch to LIVE](https://workspace.fiskaly.com/hub/switch-to-live/)
- [ ] Schriftlich klären: Preis **pro TSS**, **pro Client (Kasse)**, enthaltene Signaturen/Monat, Mehrpreis bei Volumen
- [ ] Optional mitbestellen: **DSFinV-K DE**, **SUBMIT DE** (§146a Meldung), **SAFE** (Archivierung) — siehe [Workspace Germany](https://workspace.fiskaly.com/de/countries/germany/)

### C — Technische Grenzen & Betrieb

- [ ] Rate Limits dokumentieren (Header `X-RateLimit-*`, bei 429 backoff)
- [ ] Access Token **24 h** / Refresh **48 h** — **nicht** pro Bon neu authentifizieren
- [ ] TEST und LIVE sind **getrennt** — TSS/Clients/Keys in LIVE neu anlegen
- [ ] Pro Filiale TSS-Lifecycle: anlegen → Admin-PIN → `INITIALIZED` → Client pro Kasse
- [ ] Entscheidung: fiskaly-Org-Struktur pro **CRM-Instanz** vs. zentral über Management API (bei vielen Mandanten)

### D — Abschluss K0 (Definition of Done)

- [ ] TEST-Auth erfolgreich (curl oder später DG Mock-Provider)
- [ ] Mindestens **1 Test-TSS + 1 Test-Client + 1 signierte Test-Transaktion**
- [ ] Notiz: gewähltes Abrechnungsmodell + Ansprechpartner fiskaly Sales
- [ ] Grobe Endkunden-Preisempfehlung für DG-Produkt (z. B. „TSE-Paket X EUR/Monat/Kasse“) — getrennt von Einkaufspreis fiskaly

---

## Mapping auf DG CRM

```text
CRM-Instanz (Mandant)
  -> cash_location (Filiale / Betriebsstätte)
      -> fiskaly TSS (1 pro Standort)
      -> cash_register (Kasse)
          -> fiskaly Client (1 pro Kasse, serial_number = Kassen-ID)
          -> cash_receipt -> fiskaly Transaction (start/finish)
```

---

## Preise (Einordnung, keine Bestellung)

| Quelle | Modell | Richtwert netto |
|--------|--------|-----------------|
| fiskaly direkt (LIVE) | Vertrag mit Sales | individuell |
| Partner-Beispiele (Reseller) | pro Kasse/TSS/Monat | ca. 6–14 EUR |
| Swissbit Hardware (Vergleich) | einmalig | ca. 200–220 EUR / ~5 Jahre |

**Wichtig:** Partnerpreise sind **Wiederverkauf**, nicht automatisch euer Einkaufspreis als Integrator. Für DG CRM braucht ihr ein **Integrator-Angebot** von fiskaly.

---

## TEST-Smoke (curl)

Ersetzt `YOUR_*` durch TEST-Credentials aus dem HUB.

```bash
curl -sS -X POST 'https://kassensichv-middleware.fiskaly.com/api/v2/auth' \
  -H 'Content-Type: application/json' \
  -d '{"api_key":"YOUR_API_KEY","api_secret":"YOUR_API_SECRET"}'
```

Erfolg: JSON mit `access_token`. Danach TSS/Client/Tx laut Quickstart.

---

## Nächster Schritt nach K0

**K1** Datenmodell + **K2** Mock-TSE / **K3** `FiskalyTseProvider` — parallel möglich, sobald TEST-Credentials in Dev-Secrets liegen.
