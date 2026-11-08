# DG Mitarbeiter (Flutter)

API-Spec: [`docs/MOBILE-APPS-PLAN.md`](../../docs/MOBILE-APPS-PLAN.md) · Serie **M2**.

## Setup

```bash
cd apps/mitarbeiter
flutter pub get
flutter run
```

Login: Kennung (Login/E-Mail/Nachname/Mitarbeiternummer) + Stempeluhr-PIN.

## Status

Skelett M2e–M2g — Backend M2b–M2d in `MobileStaffApi`.  
**ES6b:** Profil → „Unterschrift aufnehmen“ (`lib/signature_page.dart`) → `POST /api/mobile/staff/signature` (`source=app`).
