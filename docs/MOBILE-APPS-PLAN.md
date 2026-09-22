# DG CRM — Mobile Apps (Flutter)

> Agent-Regel: Pro Chat **eine** ID (`m0a` …). Spec nur dieser Abschnitt + genannte Dateien.  
> Kein Deploy außer „deploy“. Flutter erst nach **M0c**. Kalender (M1) und Mitarbeiter (M2) nicht mischen.

Stand: **2026-09-22** — M0 API + M1/M2 Backend + Flutter-Skelette (`apps/kalender`, `apps/mitarbeiter`).

---

## Entscheidungen (verbindlich)

| # | Wert |
|---|------|
| D1 | **Flutter** (iOS + Android) |
| D2 | Zwei Store-Apps: **Kalender** (Kunden) · **Mitarbeiter** |
| D3 | Kunden: E-Mail + Passwort → `dg_mobile_customer_accounts` an **Kontakt**, **kein** `dg_users` / kein CRM-Login |
| D4 | Mitarbeiter: Kennung + Stempeluhr-PIN → Bearer-Token |
| D5 | App speichert CRM-**Basis-URL** (QR aus Einstellungen) |
| D6 | API: `/api/mobile/*` · gleicher Code · eigene DB pro Instanz |

**Register-Regel (Kunden):** Bestehenden Kunden-Kontakt per E-Mail verknüpfen; sonst neuen Kontakt `dg_kunde` anlegen.

---

## Fehler- und Erfolgsformat

```json
{ "ok": true, "data": { } }
{ "ok": false, "error": "Menschliche Meldung", "code": "invalid_credentials" }
```

Auth: Header `Authorization: Bearer <token>`.

Token-TTL: **30 Tage**; Logout revokiert. Refresh = neues Token, altes invalid.

---

## Serie M0 — API-Fundament

| ID | Inhalt | Status |
|----|--------|--------|
| M0a | Diese Spec | ✅ |
| M0b | Migration Accounts + Tokens | ✅ 102 |
| M0c | Auth-Endpunkte | ✅ |
| M0d | Rate-Limit Login/Register (pro IP) | ✅ |

### Auth-Endpunkte

| Methode | Pfad | Auth | Body / Antwort |
|---------|------|------|----------------|
| POST | `/api/mobile/auth/customer/register` | — | email, password, name?, phone? → token (nach Soft-Verify: Konto sofort nutzbar; Verify-Mail optional Phase 2) |
| POST | `/api/mobile/auth/customer/login` | — | email, password → token, contact |
| POST | `/api/mobile/auth/staff/login` | — | identifier, pin → token, contact |
| POST | `/api/mobile/auth/logout` | Bearer | — |
| POST | `/api/mobile/auth/refresh` | Bearer | → neues token |
| GET | `/api/mobile/auth/me` | Bearer | subject_type, contact |

---

## Serie M1 — Kalender (Kunden)

| ID | Inhalt |
|----|--------|
| M1a | API Buchung | ✅ |
| M1b | Flutter Shell | ✅ Skelett |
| M1c | UI Buchen / Meine Termine | ✅ Skelett |
| M1d | Push (später) | |

### Kunden-API (nur eigener Kontakt / eigene E-Mail)

| Methode | Pfad | Beschreibung |
|---------|------|--------------|
| GET | `/api/mobile/customer/catalog` | Buchbare Artikel + Mitarbeiter (wie Public Booking) |
| GET | `/api/mobile/customer/slots?article_id=&employee_id=&date=` | Freie Slots |
| GET | `/api/mobile/customer/bookings` | Eigene Termine |
| POST | `/api/mobile/customer/bookings` | Buchen |
| POST | `/api/mobile/customer/bookings/{id}/cancel` | Storno |
| POST | `/api/mobile/customer/bookings/{id}/reschedule` | Umbuchen (neuer slot_datetime) |

Voraussetzung: Online-Buchung in Kalender-Einstellungen aktiv.

---

## Serie M2 — Mitarbeiter

| ID | Inhalt |
|----|--------|
| M2a | Rechte: nur eigen; HR im CRM | ✅ |
| M2b | profil + stempel + konto | ✅ |
| M2c | abwesenheit + schichten | ✅ |
| M2d | personalakte | ✅ |
| M2e–g | Flutter Screens | ✅ Skelett |

### Mitarbeiter-API

| Methode | Pfad | Beschreibung |
|---------|------|--------------|
| GET | `/api/mobile/staff/profile` | Stammdaten + EmployeeData (ohne Secrets) |
| GET | `/api/mobile/staff/clock` | Status + Tages-Summary |
| POST | `/api/mobile/staff/clock` | `{event_type: clock_in\|clock_out\|break_start\|break_end}` Source=`mobile` |
| GET | `/api/mobile/staff/konto` | Saldo + Lots |
| GET | `/api/mobile/staff/absences` | Eigene Abwesenheiten |
| POST | `/api/mobile/staff/absences` | Antrag (multipart bei sick/special_leave) |
| GET | `/api/mobile/staff/shifts?week=` | Wochenplan (Montag ISO) |
| GET | `/api/mobile/staff/documents` | Akte-Liste |
| GET | `/api/mobile/staff/documents/download?type=&file=` | Stream |

---

## Repo

| Pfad | Inhalt | Sync |
|------|--------|------|
| `src/Mobile/*` | PHP API | CRM-Sync |
| `apps/kalender/` | Flutter Kunden | **nicht** rsync |
| `apps/mitarbeiter/` | Flutter MA | **nicht** rsync |

---

## Nicht geplant (Phase 1)

GPS, Gesichtserkennung, In-App-Zahlung, Team-Freigaben in der App, CRM-Login für Kunden.
