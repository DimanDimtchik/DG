# Mobile Apps (Flutter) — Rest-Plan (tokenarm)

> **Eine Chat-Session = eine Mini-ID.**  
> Spec-Basis: [`MOBILE-APPS-PLAN.md`](MOBILE-APPS-PLAN.md) · Fach: [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) §D3  
> Stand: **2026-11-27** · Branch-Empfehlung: `cursor/mobile-apps-…`

---

## 0. Zielbild (verbindlich)

| Punkt | Festlegung |
|-------|------------|
| **Zwei Store-Apps** | **Kalender** (Kunden) · **Mitarbeiter** (Stempel/HR-Selbstservice) |
| **Technik** | Flutter iOS + Android · API `/api/mobile/*` · eigene DB pro CRM-Instanz |
| **CRM-Adresse** | App speichert Basis-URL (manuell oder QR aus Einstellungen) |
| **Kunden-Auth** | E-Mail + Passwort → Kontakt, **kein** CRM-`dg_users`-Login |
| **MA-Auth** | Kennung + Stempeluhr-PIN → Bearer |
| **Phase-1-Grenze** | Kein GPS, keine Gesichtserkennung, keine In-App-Zahlung, kein Push-Zwang, kein CRM-Admin in der App |

**Ist (2026-11-27):** PHP-API **M0–M2 weitgehend ✅**. Flutter unter `apps/kalender/` und `apps/mitarbeiter/` schon mit Login/Flows (kein leeres Hello-World) — aber **noch nicht** als „fertig nutzbar / Store-reif“ abgeschlossen. Dieser Plan macht die **Rest-Arbeit in Microschritten**.

---

## 1. Warum tokenarm

| Regel | Bedeutung |
|-------|-----------|
| **1 ID = 1 Commit** | Doc, 1 Screen-Block, 1 API-Härte oder 1 Guard |
| **Apps nicht mischen** | Kalender-IDs und Mitarbeiter-IDs getrennt — nie beide Apps in einem Chat umbauen |
| **Kein Tenant im Kern** | Keine Kundenfirmen/Personen in Spec-Beispielen hardcoden |
| **Apps nicht im CRM-Sync** | `apps/` bleiben außerhalb `sync-crm-from-master.sh` / rsync-Excludes |
| **Kein Deploy außer „deploy“** | PHP-API nur bei Bedarf; Flutter-Builds separat |

**Chat-Prompt-Vorlage:**  
`Nur Mini-ID MA2b laut docs/MOBILE-APPS-REST-PLAN.md. Nur Kalender-App. Kein Mitarbeiter. Kein Refactor.`

---

## 2. Token-Stufen

| Symbol | Wann |
|--------|------|
| 🟢 | Doc, Haken, kleiner Guard / Selftest |
| 🟡 | 1 Flutter-Screen / Auth-Härtung / API-Feinschliff |
| 🔴 | Gerät/Emulator-Smoke, Store-Checkliste |

---

## 3. Architektur (Soll)

```
CRM-Instanz (eigene DB)
  └── /api/mobile/*
        ├── auth (customer | staff)
        ├── customer/*  → App Kalender
        └── staff/*     → App Mitarbeiter

App speichert: baseUrl + bearerToken (+ refresh-Flow)
```

**PHP:** `src/Mobile/*` · **Flutter:** `apps/kalender/` · `apps/mitarbeiter/`

---

## 4. Parent-Übersicht

| ID | Thema | Status | Token |
|----|--------|--------|-------|
| **MA0** | Inventar / Ist / Docs | [x] MA0a–d ✅ | 🟢 |
| **MA1** | Shared (beide Apps, gemeinsame Regeln) | [x] MA1a–d ✅ | 🟢🟡 |
| **MA2** | Kalender nutzbar | [~] MA2a ✅ | 🟡🔴 |
| **MA3** | Mitarbeiter nutzbar | [ ] | 🟡🔴 |
| **MA4** | Härte / Guards / API-Smoke | [ ] | 🟢🟡 |
| **MA5** | Store-Vorbereitung | [ ] | 🟡🔴 |
| **MA6** | Abschluss-Haken | [ ] | 🟢 |

**Reihenfolge:** MA0 → MA1 → **MA2 komplett** (oder **MA3 komplett**) → die andere App → MA4 → MA5 → MA6.  
Push (**M1d** alt) bleibt bewusst später (nach MA5 oder eigene Serie).

---

## 5. Abhackplan (Mini-IDs)

Status: `[ ]` offen · `[~]` angefangen · `[x]` erledigt

### MA0 — Inventar / Docs

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| **MA0a** | Dieser Plan + Verweis in `MOBILE-APPS-PLAN.md` · `TODOS.md` · IQ-STROM §D3 | 🟢 | [x] 2026-11-27 |
| **MA0b** | Ist-Tabelle: API-Endpunkte vs. Flutter-Screens (was schon da, was fehlt) — nur Doc hier §7 | 🟢 | [x] 2026-11-27 |
| **MA0c** | QR/Basis-URL: wo im CRM erzeugt? Kurznotiz (Einstellungen) | 🟢 | [x] 2026-11-27 · §7.1 |
| **MA0d** | Konflikt: CRM-Download-Slug `/apps` → `/app-download` (Website-`/apps` frei) | 🟡 | [x] 2026-11-27 · Migration 162 |

**Prompt MA0a:**  
`Nur Mini-ID MA0a laut docs/MOBILE-APPS-REST-PLAN.md. Nur Doc-Verweise. Kein Flutter-Code.`

**Prompt MA0d:**  
`Nur Mini-ID MA0d laut docs/MOBILE-APPS-REST-PLAN.md. CRM-Download-Slug von apps auf app-download. Kein Flutter.`

---

### MA1 — Shared (gilt für beide Apps)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| **MA1a** | Fehlerformat vereinheitlichen: `ok`/`error`/`code` in beiden Apps anzeigen (menschliche Meldung) | 🟡 | [x] 2026-11-27 · `api_message.dart` + Selftest |
| **MA1b** | Token: Speichern, Logout, Refresh bei 401 — gemeinsames Muster (je App-Datei ok, Logik gleich) | 🟡 | [x] 2026-11-27 · `api_auth.dart` + Selftest |
| **MA1c** | Basis-URL: Validierung + Speichern + optional QR-Scan (wenn Plugin schon da) | 🟡 | [x] 2026-11-27 · `api_base_url.dart` (kein QR-Plugin → Link einfügen) |
| **MA1d** | Kurz: Netzwerk-Fehler / Offline-Hinweis (kein Offline-Modus) | 🟢 | [x] 2026-11-27 · `network_error` in api_message/api_auth |

**Nicht in MA1:** neue API-Endpunkte · Store-Assets · Push.

---

### MA2 — Kalender (Kunden) nutzbar

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| **MA2a** | Login + Register Ende-zu-Ende gegen Dev-API (Fehlermeldungen klar) | 🟡 | [x] 2026-11-27 · LoginPage Härte + Dev-E2E + Selftest |
| **MA2b** | Katalog → Slot wählen → Buchen (Happy Path) | 🟡 | [ ] |
| **MA2c** | Meine Termine: Liste + Storno + Umbuchen | 🟡 | [ ] |
| **MA2d** | Randfälle: Buchung aus, Slot weg, Session abgelaufen | 🟡 | [ ] |
| **MA2e** | Smoke Emulator/Gerät + Kurznotiz §8 | 🔴 | [ ] |

**Prompt MA2b:**  
`Nur Mini-ID MA2b laut docs/MOBILE-APPS-REST-PLAN.md. Nur apps/kalender. Buchen Happy Path. Kein Mitarbeiter.`

---

### MA3 — Mitarbeiter nutzbar

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| **MA3a** | Login Kennung+PIN Ende-zu-Ende gegen Dev-API | 🟡 | [ ] |
| **MA3b** | Stempel: Ein/Aus/Pause + Tagesstatus | 🟡 | [ ] |
| **MA3c** | Abwesenheit beantragen + Liste; Schichten Woche | 🟡 | [ ] |
| **MA3d** | Profil + Konto-Saldo; Akte-Liste/Download wenn API da | 🟡 | [ ] |
| **MA3e** | Unterschrift (bestehende `signature_page`) — nur Härte/Fehlerpfade | 🟡 | [ ] |
| **MA3f** | Smoke Emulator/Gerät + Kurznotiz §8 | 🔴 | [ ] |

**Prompt MA3b:**  
`Nur Mini-ID MA3b laut docs/MOBILE-APPS-REST-PLAN.md. Nur apps/mitarbeiter. Stempel-UI. Kein Kalender.`

---

### MA4 — Härte / Guards

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| **MA4a** | PHP: Selftest Auth + ein Customer- + ein Staff-Happy-Path (ohne echte Tenant-Daten hardcoden) | 🟢🟡 | [ ] |
| **MA4b** | Flutter: `flutter analyze` / Widget-Tests Mindeststand je App | 🟡 | [ ] |
| **MA4c** | Rate-Limit / ungültiges Token: UI zeigt verständliche Meldung | 🟢 | [ ] |

---

### MA5 — Store-Vorbereitung (noch kein Upload)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| **MA5a** | App-ID / Anzeigename / Icons je App prüfen (kein `com.example`) | 🟡 | [ ] |
| **MA5b** | Privacy-Text / Datenfluss kurz dokumentieren (Login, Token, keine GPS) | 🟢 | [ ] |
| **MA5c** | Release-Build-Checkliste (Android AAB / iOS Archive) — Doc only | 🟢 | [ ] |
| **MA5d** | Optional: TestFlight / internes Track — nur nach explizitem Auftrag | 🔴 | [ ] |

---

### MA6 — Abschluss

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| **MA6a** | `TODOS.md` · IQ-STROM §D3 Haken · Parent hier | 🟢 | [ ] |
| **MA6b** | `MOBILE-APPS-PLAN.md` Statuszeile auf „Rest MA0–MA6“ aktualisieren | 🟢 | [ ] |

---

## 6. Empfohlene Reihenfolge (pragmatisch)

1. **MA0a–c** (Ist klar)  
2. **MA1a–d** (Shared, spart Doppelarbeit)  
3. **Entweder MA2** (Kunden-Kalender) **oder MA3** (Mitarbeiter) — produktseitig wählen  
4. Die andere App-Serie  
5. **MA4** Guards  
6. **MA5** Store-Prep  
7. **MA6** Haken  

**Tipp:** Mitarbeiter-App oft interner Nutzen früher; Kalender braucht Online-Buchung in der Instanz aktiv.

---

## 7. Ist-Matrix (MA0b, 2026-11-27)

Legende: **✅** verdrahtet · **🟡** teilweise · **❌** fehlt · **—** nicht vorgesehen

Quellen: `src/Mobile/*` · `apps/kalender/lib/main.dart` · `apps/mitarbeiter/lib/{main,signature_page}.dart` · `views/app-connect.php`

### Auth & Verbindung

| Fähigkeit | API | Kalender | Mitarbeiter |
|-----------|-----|----------|-------------|
| Customer Register/Login | ✅ `POST …/auth/customer/*` | ✅ LoginPage (Toggle Register) | — |
| Staff Login (Kennung+PIN) | ✅ `POST …/auth/staff/login` | — | ✅ LoginPage |
| Logout | ✅ `POST …/auth/logout` | ✅ | ✅ |
| Token Refresh | ✅ `POST …/auth/refresh` | ✅ MA1b bei 401 (+ Retry) | ✅ MA1b bei 401 (+ Retry, inkl. Binary) |
| Session `me` | ✅ `GET …/auth/me` | ❌ | ❌ |
| Basis-URL manuell speichern | — (App) | ✅ MA1c `resolveCrmBaseUrl` + Prefs | ✅ MA1c + Deep-Link |
| Deep-Link / Connect | HTML `/app-connect` + Scheme | 🟡 Link/`base=` einfügen (kein Scheme-Listener) | ✅ `dg-mitarbeiter://…?base=` (app_links) |
| QR-Scan in der App | — | ❌ kein Plugin · Link von Connect-Seite einfügen | ❌ kein Plugin · Link/Deep-Link |

### Kalender (Kunden)

| Fähigkeit | API | App |
|-----------|-----|-----|
| Katalog | ✅ `GET …/customer/catalog` | ✅ HomePage Tab „Buchen“ |
| Slots | ✅ `GET …/customer/slots` | ✅ BookSlotPage (Datum + Liste) |
| Buchen | ✅ `POST …/customer/bookings` | ✅ Bestätigungsdialog → POST |
| Meine Termine | ✅ `GET …/customer/bookings` | ✅ HomePage Tab „Termine“ |
| Storno | ✅ `POST …/bookings/{id}/cancel` | ✅ |
| Umbuchen | ✅ `POST …/bookings/{id}/reschedule` | ❌ keine UI |
| Mitarbeiter-Filter bei Slots | API: `employee_id` optional | 🟡 nicht gesetzt (nur article + date) |

### Mitarbeiter

| Fähigkeit | API | App |
|-----------|-----|-----|
| Stempel Status / Events | ✅ `GET/POST …/staff/clock` | ✅ ClockTab |
| Zeitkonto | ✅ `GET …/staff/konto` | ✅ KontoTab |
| Abwesenheiten Liste/Antrag | ✅ `GET/POST …/staff/absences` | ✅ AbsencesTab (+ Multipart Nachweis) |
| Schichten | ✅ `GET …/staff/shifts` | ✅ ShiftsTab |
| Akte Liste/Download | ✅ `…/documents` (+ download) | ✅ DocsTab |
| Profil | ✅ `GET …/staff/profile` | ✅ ProfileTab |
| Unterschrift GET/POST | ✅ `…/staff/signature` | ✅ `signature_page.dart` |

### Sonst

| Fähigkeit | API | Apps |
|-----------|-----|------|
| Push | ❌ (M1d später) | ❌ |
| HTTP 401 → Refresh → Retry | API da | ✅ MA1b `api_auth.dart` (identisch) |
| Fehlerformat `ok`/`error`/`code` | ✅ `MobileApi::fail` | ✅ MA1a `api_message.dart` (identisch beide Apps) |
| Offline-Hinweis | — | ✅ MA1d `network_error` / „Keine Verbindung…“ (kein Offline-Modus) |

### Folge für Mini-IDs

| Lücke | → |
|-------|---|
| Refresh / 401 / `me` | **MA1b** |
| Fehlerformat / Offline-Hinweis vereinheitlichen | **MA1a** / **MA1d** |
| Basis-URL QR / Deep-Link Kalender angleichen | **MA1c** (+ Kurz **MA0c** CRM-Seite) |
| Umbuchen-UI Kalender | **MA2c** |
| Slot `employee_id` optional | **MA2b** / **MA2d** |
| Stempel/Abwesenheit Härte | **MA3b–e** |
| Smoke Geräte | **MA2e** / **MA3f** |

### 7.1 MA0c — Wo kommt die CRM-Basis-URL her? (2026-11-27)

**Quelle der Adresse:** immer `App::publicBaseUrl()` der jeweiligen Instanz (eigene Domain/DB). Kein Tenant-Hardcode.

| Ort | Was der Nutzer sieht | Technik |
|-----|----------------------|---------|
| Öffentlich **`/app-connect`** | Seite „App verbinden“: APK-Links + Button „App öffnen / verbinden“ + Fallback-Text mit Basis-URL | `index.php` → `views/app-connect.php` · Scheme **`dg-mitarbeiter://setup?base=<publicBaseUrl>`** · Query `?base=` optional (sonst `publicBaseUrl`) |
| Öffentlich **`/app-download`** | CRM-Seite „Apps herunterladen“ (Slug `app-download`, Menü „App-Download“) | `MobileAppDownloadService` · APKs unter `downloads/dg-*.apk` · Text: CRM-Adresse **manuell** in der App tippen |
| Öffentlich **`/apps`** | **Frei für Instanz-Website** (Produkt-/Marketing-Seiten) — **nicht** CRM-Download | Seit **MA0d**; Legacy CRM-Seiten mit `page_kind=mobile_app_download` werden nach `/app-download` umgezogen |
| CRM **Kontakt → Mitarbeiter-Zugang** | Button „App-Einladung senden“ | `StaffAccessInviteService::sendAppInvite` · Partial `views/partials/staff-access-invite.php` · Mail-Link: `{base}/app-connect?base={base}` |
| Einstellungen → **Kalender / Online-Buchung → QR** | QR/Flyer für **Terminbuchung** | `views/settings/tab-calendar-embed.php` · URL = öffentlicher **Buchungs-/Embed-Link** (`/terminkalender` o. Ä.) — **nicht** die Flutter-App-Basis-URL |

**Wichtig (Abgrenzung):**

- Der Kalender-QR in den Einstellungen verbindet **nicht** die Flutter-Apps mit dem CRM; er führt auf die **Web-Terminbuchung**.
- Deep-Link-Scheme gibt es derzeit nur für die **Mitarbeiter-App** (`dg-mitarbeiter://…`). Die Kalender-App hat keinen Connect-Scheme-Listener (MA0b) → Kunden tippen die Adresse oder holen APK über `/app-download`.
- Es gibt **keinen** eigenen Einstellungs-Tab „Mobile App QR“ nur für die Basis-URL; Connect läuft über `/app-connect` + Einladungsmail.
- **MA0d:** CRM-Download nicht mehr unter `/apps` (Slug-Konflikt mit Website-Inhalten). Guard: `php bin/mobile-app-download-slug-selftest.php`.

**Folge:** MA1c (Shared Basis-URL/QR) kann `/app-connect` und Deep-Link nutzen; optional später Kalender-Scheme analog. Kein Duplikat des Buchungs-QR bauen.

---

## 8. Smoke-Notizen

| ID | Datum | Gerät | Ergebnis |
|----|-------|-------|----------|
| MA2a | 2026-11-27 | Dev API `ganz-om.de` | Register→Login→me→Logout ✅ · Konten bereinigt · UI-Validierung |
| MA2e | | | |
| MA3f | | | |

---

## 9. Verweise

| Datei | Rolle |
|-------|--------|
| [`MOBILE-APPS-PLAN.md`](MOBILE-APPS-PLAN.md) | Entscheidungen D1–D6 + API-Vertrag |
| [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) | §D3 Mobile |
| `src/Mobile/` | PHP API |
| `apps/kalender/` | Flutter Kunden |
| `apps/mitarbeiter/` | Flutter MA |
| `apps/*/lib/api_message.dart` | MA1a Fehlerformat (byte-identisch) |
| `apps/*/lib/api_auth.dart` | MA1b Token/401-Refresh (byte-identisch) |
| `apps/*/lib/api_base_url.dart` | MA1c Basis-URL resolve/save (byte-identisch) |
| `bin/mobile-api-message-selftest.php` | Guard MA1a |
| `bin/mobile-api-auth-selftest.php` | Guard MA1b |
| `bin/mobile-api-base-url-selftest.php` | Guard MA1c |
| `bin/mobile-api-network-selftest.php` | Guard MA1d |
| `bin/mobile-kalender-auth-selftest.php` | Guard MA2a |
| `bin/mobile-app-download-slug-selftest.php` | Guard MA0d |
| [`CONTACT-SIGNATURE-REST-PLAN.md`](CONTACT-SIGNATURE-REST-PLAN.md) | Unterschrift ES6 (App-Upload) |

---

## Änderungshistorie

| Datum | Inhalt |
|-------|--------|
| 2026-11-27 | Plan angelegt · Micro-IDs MA0–MA6 für Kalender + Mitarbeiter · Ist API vs. Flutter-Rest |
| 2026-11-27 | **MA0a ✅** Verweise Spec/TODOS/IQ-STROM |
| 2026-11-27 | **MA0b ✅** Ist-Matrix §7 API vs. Flutter (Lücken → MA1–MA3) |
| 2026-11-27 | **MA0c ✅** §7.1 CRM-Basis-URL: `/app-connect`, App-Einladung · Buchungs-QR ≠ App-Connect |
| 2026-11-27 | **MA0d ✅** CRM-Download `apps` → `app-download` · `/apps` frei für Website · Migration 162 |
| 2026-11-27 | **MA1b ✅** `api_auth.dart` Token speichern/Logout · 401→Refresh→Retry · Session-Expired → Login |
| 2026-11-27 | **MA1c ✅** `api_base_url.dart` resolve/save · Connect-Link/`base=` · kein QR-Plugin (Hinweis UI) |
| 2026-11-27 | **MA1d ✅** Netzfehler → `network_error` Fail-Map · kein Logout bei Offline-Refresh · MA1-Block fertig |
| 2026-11-27 | **MA2a ✅** Kalender Login/Register Client-Validierung · Dev-E2E Auth · `mobile-kalender-auth-selftest.php` |
| 2026-11-27 | **MA1a ✅** `api_message.dart` identisch in beiden Apps · UI über `apiUserMessage`/`apiIsOk` · `php bin/mobile-api-message-selftest.php` |
| 2026-11-27 | **MA0d ✅** CRM-Download-Slug `apps` → `app-download` · Legacy-Migration nur bei `page_kind=mobile_app_download` · `/apps` frei für Website |
