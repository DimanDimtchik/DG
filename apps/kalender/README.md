# DG Kalender (Flutter) — Kunden-App

API-Spec: [`docs/MOBILE-APPS-PLAN.md`](../../docs/MOBILE-APPS-PLAN.md) · Serie **M1**.

## Setup

```bash
# Flutter SDK vorausgesetzt
cd apps/kalender
flutter pub get
flutter run
```

Erste Nutzung: CRM-Basis-URL eingeben (z. B. `https://dg.ganz-om.de`), dann Register/Login.

## API

- Auth: `POST /api/mobile/auth/customer/register|login`
- Katalog/Slots/Buchungen: `/api/mobile/customer/*`

## Status

Skelett für M1b/M1c — Backend M1a ist im CRM (`MobileCustomerApi`).
