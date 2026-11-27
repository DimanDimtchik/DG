# DG CRM Mobile Apps — Privacy & Datenfluss (kurz)

Stand: **2026-11-27** · Mini-ID **MA5b** · Spec: [`MOBILE-APPS-PLAN.md`](MOBILE-APPS-PLAN.md) · Rest: [`MOBILE-APPS-REST-PLAN.md`](MOBILE-APPS-REST-PLAN.md)

> **Kein Store-Upload hier.** Text für interne Freigabe / spätere Store-Privacy-Felder.  
> **Kein Tenant im Kern:** Firmendaten kommen nur von der gewählten CRM-Instanz (Basis-URL).

---

## 1. Apps & Zweck

| App | Zweck | Wer |
|-----|--------|-----|
| **DG Kalender** (`de.ganzsoft.dgkalender`) | Online-Termine ansehen, buchen, umbuchen, stornieren | Kunden / Kontakte |
| **DG Mitarbeiter** (`de.ganzsoft.dgmitarbeiter`) | Stempel, Schichten, Abwesenheit, Profil, Akte, Unterschrift | Eigenmitarbeiter |

Beide Apps sprechen nur die **Mobile-API** der vom Nutzer eingetragenen CRM-Instanz an (`https://…/api/mobile/*`). Es gibt **keinen** zentralen Cloud-Login bei Ganz Soft und **keinen** CRM-Admin in der App.

---

## 2. Anmeldung (Login)

### Kalender (Kunde)

1. Nutzer gibt **CRM-Adresse** (oder Connect-Link) ein → lokal gespeichert.
2. **E-Mail + Passwort** → `POST /api/mobile/auth/customer/login` bzw. Register.
3. Server prüft App-Konto am **Kontakt** (kein `dg_users`-CRM-Login).
4. Antwort: **Bearer-Token** (+ Kontakt-Kurzdaten).

### Mitarbeiter

1. CRM-Adresse bzw. Deep-Link `dg-mitarbeiter://setup?base=…`.
2. **Kennung + Stempeluhr-PIN** → `POST /api/mobile/auth/staff/login`.
3. Server prüft Mitarbeiter-Kontakt + PIN (wie Stempeluhr-Kiosk).
4. Antwort: **Bearer-Token**.

**Passwort/PIN** werden nicht dauerhaft in der App gespeichert — nur für den Login-Request.

---

## 3. Token & lokale Speicherung

| Was | Wo | Zweck |
|-----|-----|--------|
| CRM-**Basis-URL** | `SharedPreferences` (`base_url`) | Welche Instanz |
| **Bearer-Token** | `SharedPreferences` (`token`) | API-Auth |
| Token im RAM | `ApiClient.token` | laufende Session |

- **TTL:** ca. **30 Tage** (Server); Logout **revokiert** das Token serverseitig und löscht es lokal.
- **Refresh:** bei HTTP 401 → `POST /api/mobile/auth/refresh` → neues Token, altes ungültig; bei Fehlschlag → Login.
- **Netzfehler:** kein automatisches Logout (nur Hinweis „Keine Verbindung…“).
- Kein Refresh-Token getrennt vom Bearer; kein Keychain/Keystore-Sonderweg in Phase 1 (SharedPreferences).

---

## 4. Datenfluss (Überblick)

```
Gerät (App)
  ├─ speichert: baseUrl, bearerToken
  └─ HTTPS → CRM-Instanz /api/mobile/*
        ├─ auth (login / register / me / refresh / logout)
        ├─ customer/*  (Kalender: Katalog, Slots, Buchungen)
        └─ staff/*     (Mitarbeiter: Stempel, Abwesenheit, Profil, Akte, Unterschrift)
CRM-Instanz
  └─ eigene Datenbank + Dateien (keine fremde Instanz-DB)
```

**Übertragen (je nach Aktion, Beispiele):** E-Mail/Passwort oder Kennung/PIN; Buchungsdaten; Stempel-Ereignisse; Abwesenheitsanträge; Profilfelder; Dokument-Upload/Download; Unterschriftsbild (PNG/WebP).  
**Antworten:** JSON `ok` / `error` / `code` bzw. Datei-Bytes bei Download.

Rate-Limit greift serverseitig bei Auth (IP); App zeigt verständliche Meldung (`rate_limited`).

---

## 5. Was die Apps **nicht** tun (Phase 1)

| Thema | Status |
|-------|--------|
| **GPS / Standort** | **Nein** — kein Location-Plugin, keine Standort-Permission |
| Gesichtserkennung / Biometrie-Pflicht | Nein |
| Push-Zwang | Nein (kein Pflicht-Push) |
| In-App-Zahlung / Stripe | Nein |
| CRM-Admin / fremde Mandanten | Nein |
| Offline-Vollmodus | Nein (nur Netzfehler-Hinweis) |
| Tracking/Analytics-SDK | Kein separates Analytics in den Apps |

Android: im Manifest der Mitarbeiter-App steht **`INTERNET`**; keine `ACCESS_FINE_LOCATION` / Kamera-Pflicht für Login. (Datei-Picker für Akte/Unterschrift nur bei Nutzeraktion.)

---

## 6. Löschen / Abmelden

| Aktion | Effekt |
|--------|--------|
| **Logout** in der App | Token serverseitig revokiert + lokal gelöscht |
| App deinstallieren | lokale Prefs weg; Server-Token läuft ab bzw. bleibt bis TTL/Revoke |
| Konto/Daten löschen | über die **CRM-Instanz** (Betreiber), nicht in der App |

---

## 7. Store-Hinweis (später)

Für Play Console / App Store Privacy-Fragen grob:

- **Account / Login:** Ja (E-Mail oder Kennung)
- **Identifiers:** App-eigene Session (Bearer), keine Werbe-ID-Nutzung vorgesehen
- **Location:** Nein
- **Diagnostics/Tracking:** Nein (Phase 1)
- Daten gehen an die **vom Nutzer konfigurierte CRM-URL** (Instanz-Betreiber = Verantwortlicher der Fachdaten)

Verbindliche juristische Texte (AVV/Datenschutzerklärung der Instanz) liegen beim jeweiligen Betreiber — diese Datei ist die **technische** Kurzbeschreibung.

---

## 8. Verweise

| Datei | Rolle |
|-------|--------|
| `apps/*/lib/api_auth.dart` | Token speichern / Refresh |
| `apps/*/lib/api_base_url.dart` | Basis-URL |
| `src/Mobile/` | PHP API |
| `bin/mobile-privacy-selftest.php` | Guard MA5b |
