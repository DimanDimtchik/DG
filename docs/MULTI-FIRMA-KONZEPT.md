# Multi-Firma / Umfirmierung — Produktkonzept

Stand: **2026-09-21** · Status: **MF0–MF5 erledigt · MF5a–c SSO ✅** · Offen: MF6, MF7  
Bezug: KDV (`docs/KDV-TODO.md`), Shop-Pakete (`shop/config/plans.php`), Buchhaltung, Lizenzserver

---

## 1. Problem

Kunden brauchen oft **mehr als eine rechtliche Firma** in einem Login:

| Fall | Beispiel |
|------|----------|
| **Umfirmierung unterjährig** | Einzelunternehmen → GmbH zum 15.07.; Alt und Neu laufen parallel bis Abschluss |
| **Umfirmierung zum WJ-Beginn** | Wechsel zum 01.01. |
| **Tochter / Schwester** | Holding + operative GmbH; zwei Gewerbe derselben Person |
| **Zweite Marke / Betrieb** | Separates Gewerbe mit eigener Steuernummer |

Heute: **1 CRM-Instanz = 1 DB = 1 Firma**. Firmendaten überschreiben reicht prüftechnisch nicht (GoBD, getrennte Bücher, getrennte Steuernummern).

---

## 2. Steuerrechtlicher Rahmen (kurz)

### Wann Bilanzierungspflicht entsteht

| Grundlage | Inhalt |
|-----------|--------|
| **§ 140 AO** | Abgeleitet aus anderen Gesetzen (v. a. HGB) — z. B. GmbH/UG/AG immer bilanzierungspflichtig |
| **§ 141 AO** | Originäre steuerliche Buchführungspflicht für **gewerbliche** Unternehmer (nicht Freiberufler), wenn FA feststellt: Umsatz **> 800.000 €**/Kalenderjahr **oder** Gewinn **> 80.000 €**/WJ — erst nach **Mitteilung** des FA |
| **§ 141 Abs. 2** | Pflicht beginnt mit dem **Wirtschaftsjahr nach Bekanntgabe** der Mitteilung (typisch nicht mitten im laufenden WJ) |
| **§ 141 Abs. 3** | Bei Betriebsübernahme im Ganzen geht die Buchführungspflicht **mit** (keine neue Mitteilung) |
| **Wahlrecht** | § 4 Abs. 1 vs. § 4 Abs. 3 EStG; freiwillige Bilanzierung bindet i. d. R. (oft 3 WJ); Umwandlung kann Wechselgrund sein |

### Unterjährige Umfirmierung (nicht nur 01.01.)

```
01.01 ──────── Stichtag (z. B. 15.07.) ──────── 31.12
   Alt: Rumpf-WJ bis Stichtag (EÜR oder Bilanz je nach Pflicht)
              Neu: ab Stichtag (oft GmbH → immer Bilanz)
```

Zusätzlich oft **Übergangsgewinn/-verlust** (Anpassung Zufluss/Abfluss ↔ Periodenabgrenzung).  
Software-Bedarf: **zwei Buchungskreise** (Rechtsträger), Stichtag, getrennte Abschlüsse — nicht „ein Jahr EÜR + ein Jahr Bilanz“ als einziges Modell.

---

## 3. Produktziel (UX)

Wie WordPress **Multisite**, aber steuerlich sauber:

- Oben links (Logo + Firmenname) → **Dropdown „Firma wechseln“**
- Ein Login (Organisations-Konto), mehrere Firmen-Slots
- Jede Firma: eigene Stammdaten, Bücher, Steuernummern, ggf. eigenes Paket

---

## 4. Architektur-Entscheidung

| Variante | Beschreibung | Bewertung |
|----------|--------------|-----------|
| **A — Verbundene Instanzen + Switcher** | Pro Firma eigene Instanz/DB (wie heutiges KDV); Org-Login wechselt Kontext | **Empfohlen** — GoBD-klar, Lizenz/Backup/Prüferpfad je Rechtsträger |
| **B — Multisite in einer DB** | `company_id` an allen Beleg-/Journal-Tabellen | Sehr invasiv, hohes Fehlerrisiko |
| **C — Hybrid** | Shared Users + getrennte Buchungs-DBs | Mehr Sonderlogik ohne klaren Gewinn |

**Festlegung Konzept:** Variante **A**.

Bestehende Bausteine nutzen:

- KDV-Provision / Kundeninstanzen
- Lizenzserver (`dg-user.ganz-soft.de`) — Key **pro Domain/Firma**
- Shop-Tarife Starter / Business / Premium

---

## 5. Datenmodell (Org + Firma)

### Organisation (Rechnungsempfänger / Login-Träger)

- `org_id`, Name, Rechnungsadresse, Stripe-Kunde
- Benutzer der Org (einmal einladen, Rechte **pro Firma**)

### Firma (Rechtsträger / Slot)

| Feld | Bedeutung |
|------|-----------|
| `company_id` / Instanz-Domain | Technische Identität |
| `legal_name`, `company_type` | Rechtsform (GmbH, Einzelunternehmen, …) |
| `tax_id` / Steuernummer / USt-IdNr. | Pro Firma |
| `gewinnermittlung` | `euer` \| `bilanz` (Steuerung Hinweise/Export/Jahresabschluss) |
| `relation` | `standalone` \| `tochter` \| `schwester` \| `nachfolger` \| `vorgaenger` |
| `related_company_id` | Verknüpfung (z. B. Vorgänger bei Umfirmierung) |
| `effective_from` / `effective_to` | Stichtage (Rumpf-WJ / Archiv) |
| `tariff` | `basic` / `business` / `enterprise` (Shop: starter/business/premium) |
| `status` | `active` \| `archive_readonly` \| `closed` |

### Firmendaten-Historie (später / prüfrelevant)

Stichtagsgültige Änderungen: Name, Rechtsform, Steuernummer — für Impressum und Betriebsprüfung.

---

## 6. Funktionen

### MVP

1. **Firmen-Switcher** im CRM-Header (Logo/Name)
2. Pro Firma: eigene DB-Instanz, Stammdaten, WJ, Journal, Belege, Bank
3. Beziehungstypen inkl. Umfirmierung (`vorgaenger`/`nachfolger`)
4. **Umfirmierungs-Assistent:** Stichtag setzen, Alt → Archiv/Read-only ab Stichtag, Neu anlegen, Checkliste Übergangsgewinn (Hinweis; Buchung manuell oder halbautomatisch)
5. Getrennte DATEV-Mandantennummern
6. Benutzer: Org-weit einladen, ACL pro Firma
7. Lizenz/Paket **pro Firma** (nicht nur pro Login)

### Später

- Optional geteilte Kontakte (Herkunftskennzeichen)
- Intercompany-Belege
- Konzern-Übersicht (ohne steuerliche Konsolidierung vorzutäuschen)
- Bericht „Rumpfwirtschaftsjahr / Umfirmierung“
- Automatische Überleitungsbuchungen (Übergangsgewinn)

---

## 7. Preis & Pakete

Aktuelle Listenpreise (netto/Monat, zzgl. USt.): **Starter 29 € · Business 49 € · Premium 89 €** (`shop/config/plans.php`).

### Regeln (Konzept)

| Situation | Preislogik |
|-----------|------------|
| **Erste aktive Firma** | Voller Tarif |
| **Weitere aktive Firma** (Tochter, zweites Gewerbe) | **−20 %** auf den Listenpreis des gewählten Pakets der Zusatzfirma |
| **Paketwahl** | Pro Firma frei (z. B. Premium + Starter) — Funktionsumfang folgt dem **jeweiligen** Tarif |
| **Umfirmierung** | **Kein** zweites Vollabo für denselben wirtschaftlichen Betrieb in der Übergangsphase: 1 aktives Paket für die Nachfolger-Firma; Vorgänger als **Archiv/Read-only** zeitlich begrenzt (Vorschlag: 3–12 Monate) **gratis** oder Pauschale **9–15 €**/Monat |
| **3.+ Firma** | Weiter −20 % oder Staffel −25 % / −30 % (noch festzulegen) |
| **Jahresabo** | Bestehender Jahresrabatt + Zweitfirma-Rabatt kombinierbar |
| **Voraussetzung Rabatt** | Gleicher Rechnungsempfänger / verknüpfte Org in KDV |

### Warum nicht pauschal 20 % auch bei Umfirmierung?

Unterjährig existieren zwei Rechtsträger, aber oft nur **ein** laufender Betrieb. Doppeltes Vollabo wirkt ungerecht und treibt Kunden aus dem System. Archiv-Slot + ein aktives Paket ist prüf- und verkaufsseitig sauberer; 20 %-Zweitpaket bleibt für **dauerhaft parallele** Firmen.

### AGB / DSGVO (Hinweis)

Jede Firma = eigener Datenkreis; AV-Vertrag / Auftragsverarbeitung klar der Org zuordnen; Löschfristen pro Firma.

---

## 8. Umfirmierungs-Assistent (Fachablauf)

1. Aktive Firma wählen → „Umfirmierung starten“
2. Stichtag, neue Rechtsform, neuer Name, neue Steuernummer (sobald bekannt)
3. System legt **Nachfolger-Slot** an (neue Instanz oder vorbereitete DB)
4. Optional: Stammdaten/Nummernkreise/Kontenrahmen übernehmen (keine stillen Buchungsübernahmen ohne Beleg)
5. Checkliste: Eröffnungsbilanz Neu, Abschluss Alt, Übergangsgewinn, Bankkonten, Verträge, USt, Impressum/Website
6. Ab Stichtag: Buchungen nur noch in der jeweils gültigen Firma; Alt read-only nach Frist
7. Switcher zeigt beide (Alt als „Archiv“) bis `effective_to`

---

## 9. Abgrenzung / Nicht-Ziele (MVP)

- Keine steuerliche Konzernkonsolidierung
- Kein automatischer Rechtsformwechsel ohne Nutzerentscheidung
- Kein Vermischen von Journalen zweier Rechtsträger
- ELSTER/ERiC bleibt pro Firma (Zertifikat/Steuernummer)

---

## 10. Offene Entscheidungen

- [ ] Archiv-Dauer und Archiv-Preis (gratis vs. 9–15 €) final → **MF2: gratis, 12 Monate Standard**
- [ ] Staffel ab 3. Firma → **MF2: vorerst weiter −20 %**
- [ ] Shared Contacts: ja/nein im MVP → **MF4: Kennzeichnung ja, Sync nein**
- [ ] Ob Website/Domain fest an eine Firma gebunden ist oder Org-weit umschaltbar
- [ ] Technische Provision: neue Subdomain vs. Pfad vs. bestehendes KDV-Domain-Modell

---

## 11. Umsetzungsphasen (grob)

| Phase | Inhalt | Stand |
|-------|--------|-------|
| **0** | Konzept · KDV-Datenmodell Org↔Firma | ✅ Migration `082`, `KdvOrgRepository`, Formular/Liste |
| **1** | Switcher + verknüpfte Instanzen (manuell) | ✅ **MF1 2026-09-21:** Header-Dropdown, KDV-Org-Mapping, Redirect `https://{domain}/login`; Kundeninstanz optional `config/firm-switcher.local.php`. ✅ **MF5 SSO 2026-09-21:** Handoff-Token wenn `firm-sso.local.php` gesetzt. |
| **2** | Shop/KDV: Zweitfirma −20 %, Umfirmierungs-Archiv-Slot | ✅ **MF2 2026-09-21:** `MultiFirmaPricingService`; KDV Preis-übernehmen + Archiv-Checkbox; Shop-Checkout Zusatzfirma −20 %; Provision-API Org/Preis. Archiv = 0 € / 12 Monate. 3.+ Firma weiter −20 %. |
| **3** | Umfirmierungs-Assistent + Gewinnermittlungsart-Stammdatum | ✅ **MF3 2026-09-21:** Migration `088`, `UmfirmierungService`, KDV-Assistent, Gewinnermittlung in Firma-Einstellungen + KDV-Slot |
| **4** | Historie Firmendaten, Berichte Rumpf-WJ, optionale Shared Contacts | ✅ **MF4 2026-09-21:** `dg_company_master_history`, Rumpf-WJ-Bericht, Org-Flag Shared Contacts, Kontakt-Herkunftshinweis (kein Cross-DB-Sync) |

### Phase 0 — technische Artefakte

- `database/migrations/082_kdv_orgs_multi_firma.sql`
- `src/Kdv/KdvOrgRepository.php`
- `KdvCustomerRepository`: `org_id`, Beziehung, Slot-Status, Gültigkeit; `listByOrgId()`
- UI: `views/modules/kdv-kunde-form.php` (Panel Organisation), Liste mit Org-Spalte
- Nur Master-DB (KDV-Register); Kundeninstanzen unverändert getrennt

### Phase 1 (MF1) — technische Artefakte

- `src/MultiFirma/FirmSwitcherService.php` — Sibling-Liste aus KDV (`findByDomain` + `listByOrgId`) oder `config/firm-switcher.local.php`
- `config/firm-switcher.local.example.php` — Vorlage für Kundeninstanzen (Sync-Exclude `*.local.php`)
- `POST /firm-switch` — CSRF + Domain-Allowlist → Redirect HTTPS Login
- UI: `views/layout/app.php` Adminbar-Dropdown „Firma wechseln“
- **Nicht in MF1:** SSO/Org-Single-Login, Shop −20 %, Umfirmierungs-Assistent, `company_id` an Belegen

### Phase 2 (MF2) — technische Artefakte

- `src/MultiFirma/MultiFirmaPricingService.php` — Listenpreise, −20 % Zusatzfirma, Archiv 0 € / 12 Monate
- KDV-Kunde-Form: MF2-Vorschlag, Checkbox „Preis übernehmen“, „Als Archiv-Slot setzen“
- `KdvProvisionApi`: Org aus Kontakt-E-Mail, `additional_firm` / `apply_mf_price`
- Shop: Checkout-Checkbox Zusatzfirma; `ShopPlans::priceWithAdditionalFirmDiscount`
- **Festlegung MF2:** Archiv gratis; 3.+ Firma weiter −20 % (Staffel später)
- **Nicht in MF2:** Umfirmierungs-Assistent (MF3), SSO, Stripe-Coupon-Automatik

### Phase 3 (MF3) — technische Artefakte

- Migration `088_multi_firma_gewinnermittlung.sql` — `gewinnermittlung`, `company_type`, `tax_number_note` an `dg_kdv_customers`
- `UmfirmierungService` — Nachfolger anlegen, Vorgänger Archiv, Checkliste in Notizen
- UI: `/app?page=kdv-umfirmierung&from_id=` · Link am KDV-Kunden
- Stammdatum Instanz: `CompanyExtendedSettings.gewinnermittlung` (EÜR/Bilanz) in Einstellungen → Firma
- **Nicht in MF3:** Auto-Provision der neuen Instanz, Buchungsübernahme, Firmendaten-Historie (MF4)

### Phase 4 (MF4) — technische Artefakte

- Migration `089_multi_firma_history_shared.sql` — `dg_company_master_history`, `dg_kdv_orgs.share_contacts`, `dg_contacts.origin_firm_note`
- `CompanyMasterHistoryRepository` — Snapshot bei Firmenstammdaten-Änderung (Fingerprint)
- UI Historie: Einstellungen → Firma (Akkordeon)
- `RumpfWjReportService` + `/app?page=kdv-rumpf-wj` — Stichtage/Slots je Org
- Shared Contacts: Org-Kennzeichnung + Herkunftsfeld am Kontakt — **kein** Sync zwischen Instanz-DBs (Variante A)
- **Festlegung MF4:** Shared Contacts = Kennzeichnung/Hinweis, kein Cross-DB-Import

---

## 13. Ausbau nach MF4 (SSO · Sync · Auto-Provision) — token-sparend

> **Agent-Regel:** Pro Chat nur **einen** Unterpunkt (z. B. MF5a). Spec-Absatz §13 + genannte Dateien — **nicht** §1–12 / §14 neu einlesen. Kein Deploy außer Nutzer sagt es. Ein Chat = ein Commit.

### Reihenfolge (Absicht)

1. **MF5 SSO** — ohne SSO bleibt Switcher unkomfortabel; Sync/Provision brauchen Auth-Kontext  
2. **MF6 Contact-Sync** — erst nach SSO (Zielinstanz muss Nutzer/Org kennen)  
3. **MF7 Auto-Provision** — teuer/riskant; zuletzt, baut auf KDV + vorhandenem `KdvDeployService`

**Nie parallel** SSO+Sync+Provision in einem Chat.

### Entscheid-Checkliste (5 Min. ohne Agent, vor MF5a)

| # | Entscheidung | Vorschlag (Default) |
|---|--------------|---------------------|
| E1 | SSO-Mechanismus | ✅ **MF5a:** Signiertes **Handoff-Token** (HMAC-SHA256, TTL 60 s, einmalig) → Ziel-`/login?firm_sso=…` — kein Shared-Session-Cookie |
| E2 | Shared Secret | ✅ **MF5a:** `config/firm-sso.local.php` (Sync-Exclude `*.local.php`); gleicher `shared_secret` auf allen Org-Instanzen (manuell) |
| E3 | Contact-Sync | **Einbahn** Export-Paket (JSON) + manueller/halbauto Import; **kein** Live-2-Wege (GoBD/Konflikt) |
| E4 | Was wird synchronisiert | Stammfelder Kontakt + Adresse; **keine** Mitarbeiterakten, **keine** Belege |
| E5 | Auto-Provision | Ruft bestehenden `KdvDeployService` nur für **Nachfolger** nach Umfirmierung; Domain muss DNS-ready sein |
| E6 | Rollback | Provision-Fehler → KDV-Slot bleibt `neu`, kein stilles Löschen des Vorgänger-Archivs |

### MF5a — SSO Spec (Token · TTL · Allowlist) ✅ 2026-09-21

Nur Spezifikation — Umsetzung Code = **MF5b/MF5c**.

#### Ziel-URL

```
https://{target_domain}/login?firm_sso={token}
```

- Nur **HTTPS**. HTTP ablehnen.
- Token ausschließlich als Query-Parameter `firm_sso` (kein Cookie, kein POST-Body in MF5).
- Nach erfolgreicher Verifikation: Session auf Zielinstanz anlegen, Token **verbrauchmarkieren**, Redirect auf Home (`RoleResolver::homePath`).
- Bei Fehler: Login-Formular mit Flash „Firmenwechsel abgelaufen oder ungültig“ — kein Account-Leak in der Meldung.

#### Token-Inhalt (Payload, vor Signatur)

| Feld | Typ | Bedeutung |
|------|-----|-----------|
| `v` | int | Formatversion, fest `1` |
| `iss` | string | Quell-Domain normalisiert (ohne Schema/Port/`www.`) |
| `aud` | string | Ziel-Domain normalisiert (Allowlist-Pflicht) |
| `sub` | string | Login-Identität: bevorzugt **E-Mail** des Users (lowercase); Fallback Username |
| `iat` | int | Unix-Zeit Ausstellung |
| `exp` | int | Unix-Zeit Ablauf = `iat + 60` |
| `jti` | string | 16+ Bytes hex Zufall (Einmaligkeit) |
| `uid` | int\|null | optionale Quell-User-ID (nur Info, Login auf Ziel über `sub`) |

Matching auf Ziel: `UserRepository::findByEmailOrUsername(sub)` — **kein** automatisches Anlegen fehlender User (MF5). Fehlt User → Fehler, Passwort-Login.

#### Kodierung

1. Payload JSON (UTF-8, Schlüssel sortiert oder feste Feldreihenfolge laut Implementierung MF5b — dokumentieren im Code).
2. `payload_b64` = Base64URL (ohne Padding) des JSON.
3. `sig` = Base64URL( HMAC-SHA256( `payload_b64`, `shared_secret` ) ).
4. Token-String: `{payload_b64}.{sig}` (ein Punkt).

#### TTL & Replay

| Regel | Wert |
|-------|------|
| TTL | **60 Sekunden** (`exp - iat = 60`) |
| Uhr | Serverzeit; Toleranz Clock-Skew **±10 s** bei Prüfung (`now` in `[iat-10, exp+10]` unzulässig erweitern — nur `now ≤ exp+10` und `now ≥ iat-10`) |
| Einmaligkeit | `jti` nach Erfolg in Zielinstanz speichern (SettingsStore-Key oder kleine Tabelle `dg_firm_sso_jti`, TTL-Cleanup > 24 h) — Wiederverwendung → reject |
| CSRF am Quell-`/firm-switch` | bleibt POST+CSRF wie MF1; Token wird **server-seitig** nach CSRF erzeugt, nicht vom Browser gebaut |

#### Allowlist Domains

Ziel (`aud`) und Quelle (`iss`) müssen **beide** in der für die aktuelle Instanz bekannten Sibling-Liste liegen:

1. Primär: KDV Org-Geschwister (`FirmSwitcherService` / `listByOrgId`) wenn KDV-DB vorhanden  
2. Sonst: `config/firm-switcher.local.php` (MF1)  
3. Zusätzlich optional in `firm-sso.local.php`: `allowed_domains => string[]` als **Schnittmenge**-Verschärfung (wenn gesetzt: Domain muss in Sibling-Liste **und** in `allowed_domains` sein)

Normalisierung wie MF1: lowercase, ohne Port, ohne führendes `www.`, ohne Schema.

Reject wenn:

- `aud` ≠ HTTP_HOST der prüfenden Instanz (normalisiert)  
- `iss` === `aud` (Selbst-Switch)  
- `aud` / `iss` nicht allowlisted  
- Signatur falsch, `v !== 1`, `exp` abgelaufen, `jti` schon gesehen, `sub` leer  

#### Secret-Datei (`config/firm-sso.local.example.php` in MF5b anlegen)

```php
<?php
return [
    'shared_secret' => '', // min. 32 Bytes Zufall, identisch auf allen Org-Instanzen
    'ttl_seconds' => 60,
    'allowed_domains' => [
        // optional leer = nur Sibling-Liste aus KDV / firm-switcher.local.php
        // 'firma-a.example',
        // 'firma-b.example',
    ],
];
```

Ohne `shared_secret` oder Secret &lt; 32 Zeichen: SSO **aus** — Switcher fällt auf MF1-Verhalten zurück (Redirect `/login` ohne Token).

#### Security-Randbedingungen (MF5)

- Kein Token in Referrer loggen (Login-Seite: `Referrer-Policy` beachten falls nötig).  
- Rate-Limit: fehlgeschlagene `firm_sso`-Versuche zählen wie LoginThrottle (MF5b anbinden, wenn trivial).  
- Support-Session / Impersonation: **kein** SSO-Handoff aus Support-Session (MF5b: hart ablehnen).  
- Archiv-Slots: Switch erlaubt (nur lesen auf Ziel ist Instanz-Sache); SSO ändert daran nichts.

#### Abgrenzung MF5a

- **Nicht** in MF5a: PHP-Code, Migration JTI-Tabelle, Beispiel-Config-Datei (→ MF5b).  
- **Nicht** Cookie-SSO, OAuth, SAML, Lizenzserver als IdP.

### Phasen

| Phase | Lieferobjekt | Erlaubt zu lesen/ändern | Nicht |
|-------|----------------|-------------------------|--------|
| **MF5a** ✅ | Spec-Nachtrag SSO (Token-Format, TTL, CSRF, Allowlist Domains) | nur `MULTI-FIRMA-KONZEPT.md` §13 — **erledigt 2026-09-21** | Code |
| **MF5b** ✅ | `FirmSsoService` ausstellen + verifizieren | `src/MultiFirma/*`, `config/firm-sso.local.example.php`, `index.php` `/firm-switch` + `/login` — **erledigt 2026-09-21** | Sync, Provision, Shop |
| **MF5c** ✅ | Switcher-UX/Polish (Flash, Referrer-Policy, Feinschliff) | Login-View, `app.php`, `SecurityHeaders` — **erledigt 2026-09-21** | neues UI-Framework |
| **MF6a** | Spec Contact-Export-Schema + Herkunft | Spec §13 | Code |
| **MF6b** | Export API/Button „Kontakte für Org-Schwester“ (JSON-Datei) | Contact-Repo read-only Export, 1 View | Import, Live-Sync |
| **MF6c** | Import auf Zielinstanz + `origin_firm_note` setzen | Contact save, MF4-Feld | 2-Wege, Merge-UI groß |
| **MF7a** | Spec: wann Provision erlaubt (DNS, KAS, Slot `neu`) | Spec | Code |
| **MF7b** | Hook Umfirmierung → optional `KdvDeployService::provision` | `UmfirmierungService`, DeployService | Shop-Stripe |
| **MF7c** | Status/Fehler in KDV-UI (Install-URL, Steps) | kdv-kunde-form / umfirmierung View | neue Infrastruktur |

### Chat-Vorlage (kopieren)

```text
Scope: Multi-Firma MF5b laut docs/MULTI-FIRMA-KONZEPT.md §13
Nur: FirmSsoService Token ausstellen/prüfen + Anbindung /firm-switch und /login
Kein Contact-Sync, keine Auto-Provision, kein Shop, kein Deploy außer ich sage es.
Nicht §1–12/§14 der Spec neu einlesen — nur §13 + genannte Dateien.
```

Weitere: `MF5a` / `MF5c` / `MF6a` … analog ersetzen.

### Token-Sparregeln (Cursor)

1. **Ein Unterpunkt pro Chat** — nie „baue SSO und Sync“  
2. **Entscheidungen E1–E6 vorher** in dieser Tabelle abhaken (ohne Agent)  
3. **Composer/lokal** für Code; Cloud nur bei Deploy/SSH  
4. **Kein erneutes Konzept-Einlesen** — Agent-Regel oben  
5. **Kein Deploy** in Ausbau-Chats, bis Sie „deploy“ sagen  
6. Nach jedem Unterpunkt: **ein Commit** (`commit mf5b`) — Diff bleibt klein  
7. Chats archivieren — max. 1 Lokal + 1 Cloud aktiv lassen  

### Bewusst später / nicht in MF5–7

- Intercompany-Belege, Konzern-Konsolidierung  
- Shared Session über alle Domains (Cookie)  
- Automatische Buchungsübernahme bei Umfirmierung  
- Stripe-Coupon-API für −20 %  

---

## 14. Referenzen

- § 140, § 141 AO  
- § 4 Abs. 1 / Abs. 3 EStG  
- `docs/KDV-TODO.md`, `docs/SHOP-TODO.md`, `docs/TODOS.md`  
- `shop/config/plans.php`
