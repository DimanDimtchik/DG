# Testliste – Stand nach 2026-08-20/21

> Zum Abhaken beim manuellen Test. Nach Deploy immer **Shift+Strg+R**.  
> CRM-Version erwartet: **1.0.12** · Shop: Phase 1 auf **shop.ganz-soft.de**

### Instanzen (CRM)

| Kurz | URL |
|------|-----|
| Master | https://dg.ganz-om.de |
| ganz-soft Live | https://ganz-soft.de |
| Kontur | https://kontur-cosmetics.de |
| Shop | https://shop.ganz-soft.de |

Empfehlung: **eine** CRM-Instanz gründlich (z. B. ganz-soft.de), die anderen nur Spot-Check (Login + Statistik-Menü + eine öffentliche Seite).

---

## A. CRM – Version & Navigation

- [ ] Login funktioniert
- [ ] Unter Website erscheint Menüpunkt **Statistik**
- [ ] Version irgendwo / Update-Hinweis zeigt **1.0.12** (falls sichtbar)
- [ ] Seiten, Formulare, Menü, Kopf & Fuß öffnen ohne Fehler

---

## B. Website → Statistik (lokal)

Voraussetzung: Cookie-Banner auf der **öffentlichen** Website.

### B1 Ohne Statistik-Consent

- [ ] Öffentliche Seite öffnen, Cookies **ablehnen** bzw. Statistik nicht erlauben
- [ ] Seite neu laden (normale Navigation)
- [ ] Im CRM unter **Website → Statistik**: Aufrufzahl steigt **nicht** (oder nur durch andere Besucher mit Consent)

### B2 Mit Statistik-Consent

- [ ] Öffentliche Seite, Cookie-Banner: **Statistik akzeptieren**
- [ ] 2–3 verschiedene Seiten/Pfade aufrufen
- [ ] CRM → **Website → Statistik**
- [ ] „Heute“ / Balken / Top-Pfade zeigen neue Aufrufe
- [ ] Umschalter **7 / 30 / 90 Tage** wechselt die Ansicht ohne Fehler
- [ ] Referrer: von einer **fremden** Domain kommen (z. B. Link aus Notepad) – optional Top-Referrer prüfen
- [ ] **Vorschau** einer Seite (`/vorschau/…`): zählt **nicht** als Aufruf

### B3 Google-Links

- [ ] Unter **Kopf & Fuß**: GA-Mess-ID und/oder GTM-Container leer → Statistik zeigt Hinweis + Link zu Kopf & Fuß
- [ ] IDs eintragen, speichern → Statistik zeigt Buttons **Google Analytics** / **Tag Manager** (öffnen Google-Login, kein CRM-Fehler)

---

## C. Analytics-Skripte (nur mit Consent)

- [ ] Consent **an**: im Browser DevTools → Netzwerk: Requests zu `googletagmanager.com` / `google-analytics.com` (wenn IDs gesetzt)
- [ ] Consent **aus** / abgelehnt: **keine** GA/GTM-Requests
- [ ] Freies Header-/Footer-JS (falls genutzt) lädt weiterhin unabhängig vom Statistik-Consent

---

## D. Formulare (falls noch nicht getestet)

- [ ] Website → Formulare: Liste öffnet
- [ ] Formular anlegen / bearbeiten, Felder speichern
- [ ] Formular in einer Seite einbinden (Block oder Shortcode)
- [ ] Öffentlich absenden mit **Mathe-Captcha** (richtig / falsch)
- [ ] Eingang erscheint unter Formular-Inbox
- [ ] Datei-Upload (wenn Feld vorhanden): Datei kommt an / Download im CRM

---

## E. Shop (shop.ganz-soft.de) – Phase 1

### E1 Erreichbarkeit

- [ ] https://shop.ganz-soft.de/ lädt (kein WordPress, kein 500)
- [ ] https://shop.ganz-soft.de/preise lädt
- [ ] Alte WP-URLs (z. B. `/wp-admin`) → 404 oder Shop-404, **kein** WP-Login

### E2 Preise

- [ ] Drei Pakete: **Starter 29 €**, **Business 49 €**, **Premium 89 €** / Monat netto
- [ ] Hinweis **zzgl. 19 % MwSt.**
- [ ] Toggle **Monatlich** / **Jährlich**
- [ ] Jährlich: Preis = 11× Monat (29→319, 49→539, 89→979), Badge „1 Monat gratis“
- [ ] „Jetzt starten“ führt zu `/checkout?plan=…`

### E3 Checkout (ohne Stripe)

- [ ] Tarif + Laufzeit vorausgefüllt / umschaltbar
- [ ] Leeres Absenden → Fehlermeldungen (Firma, Domain, Name, E-Mail)
- [ ] Ungültige Domain (z. B. `firma` ohne TLD) → Fehler
- [ ] Gültige Testdaten → „Angaben geprüft“ / Hinweis Phase 2 (noch **keine** echte Zahlung, keine KDV-Anlage)
- [ ] Handy/schmale Breite: Layout nutzbar

### E4 Sicherheit kurz

- [ ] https://shop.ganz-soft.de/config/database.local.php → **403/gesperrt**, kein Klartext-Passwort

---

## F. Spot-Check andere CRM-Instanzen

Je Instanz:

- [ ] Login
- [ ] Website → Statistik öffnet
- [ ] Eine öffentliche Seite lädt
- [ ] Cookie-Banner erscheint (falls noch nicht entschieden)

---

## G. Bekannte Grenzen (kein Bug, wenn so)

| Thema | Erwartung |
|--------|-----------|
| Shop-Zahlung | Noch **kein** Stripe |
| Shop → KDV | Noch **keine** automatische Provisionierung |
| Statistik ohne Consent | Bleibt leer / steigt nicht |
| Preise Shop vs. Marketing | Gleich wie [ganz-soft.de/preise](https://ganz-soft.de/preise); Shop-Jahresabo zusätzlich mit ×11 |
| Rechtstexte Bootstrap | Generator-Entwurf – juristisch prüfen |
| Wartungsmodus nach Install | Standard **an** – unter Website → Seiten abschaltbar |

---

## G. Website-Bootstrap (Pflichtseiten, Stand 2026-08-21)

Nach Deploy von `seed-website-defaults.php` / neuer Installation:

- [ ] CRM → **Website → Seiten** → Panel „Pflichtseiten & Startseite“ sichtbar
- [ ] Seiten vorhanden: **Startseite**, **Kontakt**, **Impressum**, **Datenschutz**, **AGB**
- [ ] **Wartungsmodus** ist eingeschaltet (Badge „Aktiv“ unter Website → Seiten)
- [ ] Öffentlich `/` zeigt Wartungsseite (nicht eingeloggt)
- [ ] Vorschau `/vorschau/startseite` zeigt Startseite (eingeloggt im CRM)
- [ ] `/kontakt` (Vorschau): Kontaktformular mit **Datenschutz-Checkbox** und Captcha
- [ ] Menü: Start, Kontakt, Rechtliches (Impressum, Datenschutz, AGB)
- [ ] Button „Pflichtseiten jetzt anlegen“ auf bestehender Instanz (mit `--overwrite` testen)

**Server (SSH):** nach Code-Deploy  
`bash bin/run-website-bootstrap-on-instances.sh`  
oder pro Instanz: `php bin/seed-website-defaults.php --overwrite`

---

## H. Shop – Unternehmenstyp (volle Domain)

- [ ] Checkout mit `meine-firma.de` → Feld „Ihr Unternehmen“ erscheint (Pflicht)
- [ ] Checkout mit `crm.firma.de` oder leerer Domain → Feld **nicht** sichtbar
- [ ] KDV-Payload enthält `business_profile` / `business_kind` (wenn Website-Intent)

---

## I. Installations-Datenimport (Branch `cursor/install-data-import-6a0c`)

Frische Installation oder Test-VM (`storage/.installed` entfernen):

- [ ] Install-Schritte 1–4 unverändert durchlaufbar
- [ ] **Schritt 5** „Datenimport“ sichtbar (6 Schritte insgesamt)
- [ ] Quellsystem-Dropdown zeigt Hinweis-Text (z. B. DATEV, Excel)
- [ ] Beispiel-Vorlage herunterladbar (`?action=import-template&type=contacts`)
- [ ] Kontakte: Excel-Datei hochladen → Schritt 6 → Installation
- [ ] Fortschrittsbalken + Schritte während Import
- [ ] Nach Import: Login möglich, Kontakte in CRM sichtbar
- [ ] Installation **ohne** Import: direkt Erfolgsseite, kein Import-Panel
- [ ] Belege: PDF hochladen → Hinweis „zwischengespeichert“, keine Verarbeitung

**Ohne Import (Skip):** Schritt 5 leer lassen → weiter zu Benutzer.

---

## J. Buchhaltung Phasen A–C (Branch `cursor/buchhaltung-phase-abc-6a0c`)

> Deploy: `.\deploy.bat` · Migration automatisch beim CRM-Login · Details: `docs/BUCHHALTUNG-PC-HANDOFF.md`

### J1 Zeitraum-Filter

- [ ] **Belege:** Monat wählen → nur Belege dieses Monats
- [ ] **Belege:** Von/Bis-Datum → korrekter Zeitraum
- [ ] **Kontenübersicht:** Monatsfilter → Salden nur für Zeitraum
- [ ] **Kassenbuch:** Zeitraumfilter → Ein/Aus/Saldo passt

### J2 Skonto + USt

- [ ] Beleg mit Skonto (discount_amount oder paid < gross) speichern
- [ ] Journal im Belegformular: Skonto-Zeilen **mit** USt-Aufspaltung (3 Buchungen)
- [ ] UStVA: Skonto reduziert KZ 62/63 bzw. 83/35

### J3 Druck / PDF

- [ ] Bilanz & GuV → „Drucken / PDF“ → HTML öffnet, Druckdialog OK
- [ ] UStVA → Druck
- [ ] Kassenbuch → Druck
- [ ] Kontoauszug (Konto anklicken) → Druck

### J4 UStVA Sonderfälle

- [ ] Leerer Monat → Hinweis **Nullmeldung möglich**
- [ ] Checkbox **Berichtigung** → gelber Hinweis
- [ ] ELSTER-CSV Download funktioniert

### J5 Kassenbuch Tagesabschluss

- [ ] Datum wählen, gezählter Bestand eingeben → Abschluss speichern
- [ ] Eintrag erscheint in Historie-Tabelle
- [ ] Zweiter Abschluss am selben Tag → Fehlermeldung

### J6 BWA & SuSa

- [ ] Menü **BWA** sichtbar, Report mit Monatsfilter
- [ ] Menü **SuSa** sichtbar, Konten mit Anfang/Soll/Haben/Saldo

### J7 Bank & Export

- [ ] CAMT.053 Import (wie bisher)
- [ ] MT940 Import (`.sta` / `.940`)
- [ ] Auto-Match: Beleg mit passender IBAN am Kontakt wird zugeordnet
- [ ] Steuerberater-Export → **Komplett-Paket (ZIP)** enthält EXTF, UStVA, EÜR, SuSa, Beleg-ZIP

---

## K. Buchhaltung Randfälle (Branch `cursor/buchhaltung-randfaelle-1c3a`)

> **Druckbare Checkliste:** [`TESTLISTE-RANDFAELLE-2026-08-23.html`](TESTLISTE-RANDFAELLE-2026-08-23.html) (Browser → Drucken / PDF)  
> Deploy + Migrationen **055–061** · Hard-Reload · Details: `BUCHHALTUNG-BELEGKETTE.md`, `ZEITERFASSUNG-PLAN.md`

- [ ] Belegkette: Angebot → AB → (Lieferschein) → Abschlag → Schlussrechnung + Panel-Links
- [ ] Dokumentstatus-Workflow + Belegliste-Filter
- [ ] Freitext + gesetzliche Klauseln auf Druck/PDF/E-Mail
- [ ] Skonto-Stufen + manuelle/automatische Mahnung
- [ ] Teilzahlungen (`partial`, OPOS Bezahlt/Offen)
- [ ] Zeiterfassung: Stempel, Zwangspause, Team, Autoclose
- [ ] Randfälle: PRAP Einnahmen, negative Rabattzeile, Trinkgeld
- [ ] Terminkalender-Landing (frisch / Bootstrap)

---

## Schnellpfad (ca. 15 Minuten)

1. Shop: Start + Preise Monat/Jahr + ein Checkout mit Testdaten  
2. CRM (eine Instanz): Statistik-Menü  
3. Öffentlich: Consent an → 2 Seiten klicken → Statistik prüfen  
4. Consent aus / neu (anderes Profil/Inkognito): keine GA-Requests  
5. Optional: ein Formular absenden  

Wenn etwas rot wird: URL, was du geklickt hast, und Screenshot/Fehlertext notieren.
