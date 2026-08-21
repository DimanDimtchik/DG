# Kundenverwaltung (KDV) – Todo-Liste

> Erreichbar unter: `dg.ganz-om.de` → Menü **SaaS-Kunden (Ganz Soft)** (Admin)
> Zweck: Zentrale Verwaltung **Ihrer** CRM-Hosting-/Lizenzkunden (Domains)
> Abgrenzung: **nicht** CRM-Kontakte der Endkunden; Endkunden sehen nur das **Shop-Konto** auf shop.ganz-soft.de

---

## Abgrenzung (fest)

| Begriff | Bedeutung |
|---------|-----------|
| KDV / SaaS-Kunden | Ihre Mandanten (Instanzen) – nur Admin |
| CRM-Kontakte | Endkunden-Adressbuch in *deren* CRM |
| Lizenz-Shop | shop.ganz-soft.de – Verkauf + SaaS-Konto |
| Kunden-Webshop | später im CRM des Kunden – anderes Produkt |

---

## Phase 1: Grundstruktur

- [x] KDV als Admin-Bereich im Master-CRM (Menü „SaaS-Kunden (Ganz Soft)“)
- [x] Datenbank-Tabelle `dg_kdv_customers` (Firma, Domain, Vertrag, Status)
- [ ] Datenbank-Tabelle `dg_kdv_contracts` (Tarif-Historie fein)
- [x] Zugang: nur Admin-Rolle
- [x] Dashboard: Übersicht Instanzen

## Phase 2: Kundenanlage + Lizenz

- [x] Formular: Neuen SaaS-Kunden anlegen (Firmenname, Domain, Ansprechpartner, E-Mail)
- [x] Automatisch via KAS-API: Domain anlegen, Webspace einrichten, DB erstellen
- [x] CRM-Dateien per SCP/API auf neuen Webspace deployen
- [x] Install-URL generieren und an Kunden-E-Mail senden
- [x] Status-Tracking: „Domain bestellt" → „DNS aktiv" → „CRM installiert" → „Aktiv"
- [x] **Lizenz:** Key erzeugen / bestehenden Key zuweisen; sperren / entsperren am Lizenzserver
- [x] **Sperrgrund** (`block_reason`) inkl. Auto-Ablehnung von Entsperr-Bitten (z. B. `unpaid_invoice`)
- [x] **Shop-Konto-API:** `/api/kdv/account/login|me|unlock-request|logout`
- [ ] Stripe → automatischer Sperrgrund `unpaid_invoice` (Zahlung folgt)

## Phase 3: Vertragsverwaltung

- [ ] Tarif-Modell definieren (Basic, Business, Enterprise oder individuelle Pakete)
- [ ] Vertragslaufzeiten (monatlich, jährlich, individuell)
- [ ] Rechnungserstellung (monatlich/jährlich) – Integration mit Buchhaltungsmodul
- [ ] Mahnwesen bei unbezahlten Rechnungen
- [ ] Kündigung: Vertrag beenden, Daten exportieren, Instanz deaktivieren

## Phase 4: Update-Steuerung

- [ ] Übersicht: Welcher Kunde hat welche CRM-Version?
- [ ] Massen-Update: Alle Kunden auf einmal auf neue Version updaten
- [ ] Kritisches Zwangsupdate: Button „Sofortiges Update für alle erzwingen"
  - Setzt `force_pending = true` in jeder Kundeninstanz
  - Optional: Wartungshinweis per E-Mail an alle Kunden
- [ ] Einzelupdate: Bestimmten Kunden gezielt updaten
- [ ] Update-Protokoll: Welcher Kunde wann welches Update bekommen hat
- [ ] Rollback-Möglichkeit bei fehlgeschlagenem Update

## Phase 5: Monitoring

- [ ] Heartbeat-Check: Ist die Kundeninstanz erreichbar? (periodisch pingen)
- [ ] Versions-Check: Stimmt installierte Version mit erwarteter überein?
- [ ] Speicherplatz-Überwachung (Webspace, Datenbank)
- [ ] Fehler-Log: Kundeninstanz meldet kritische PHP-Fehler an KDV
- [ ] E-Mail-Benachrichtigung bei Ausfällen

## Phase 6: Kommunikation

- [ ] Nachrichten an einzelne oder alle Kunden senden (In-App-Benachrichtigung)
- [ ] Wartungsankündigungen: „Am [Datum] wird ein Update eingespielt"
- [ ] Changelog pro Version: Was ist neu?
- [ ] Support-Ticket-System (oder Verlinkung zu externem System)

## Phase 7: Recht & Compliance

- [ ] AGB für Hosting-Kunden (Auftragsverarbeitungsvertrag / AVV)
- [ ] Datenschutz: Wo liegen Kundendaten? (Dokumentation für DSGVO)
- [ ] Backup-Strategie pro Kunde (automatische DB-Backups, Aufbewahrungsfrist)
- [ ] Löschkonzept: Daten löschen nach Vertragsende (Frist definieren)

## Phase 8: Finanzen

- [ ] Umsatzübersicht pro Kunde / pro Monat
- [ ] Kosten-Tracking: Was kostet der Webspace pro Kunde bei All-Inkl?
- [ ] Marge pro Kunde berechnen
- [ ] Integration mit CRM-Buchhaltung für automatische Rechnungsstellung

---

## Technische Architektur

```
dg.ganz-om.de/KDV/
├── index.php          ← Routing
├── config/            ← KDV-Config (DB, Auth)
├── src/               ← PHP-Klassen
│   ├── KdvAuth.php
│   ├── CustomerRepository.php
│   ├── ContractRepository.php
│   ├── DeployService.php
│   ├── UpdateService.php
│   └── MonitorService.php
├── views/             ← Templates
│   ├── dashboard.php
│   ├── customer-list.php
│   ├── customer-form.php
│   ├── contracts.php
│   └── updates.php
└── assets/            ← CSS/JS
```

---

## Merkliste CRM-Produkt (Zukunft)

Notiert 21.08.2026 – noch nicht priorisiert:

- [ ] **Caching** (Performance / Zwischenspeicher)
- [ ] **HaltStatus** (Bedeutung beim Umsetzen klären)
- [ ] **Zeiterfassung**
- [ ] **Lagerwirtschaft**
