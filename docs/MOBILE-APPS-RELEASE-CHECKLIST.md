# DG CRM Mobile Apps — Release-Build-Checkliste

Stand: **2026-11-27** · Mini-ID **MA5c** · **Doc only** — kein Store-Upload, kein TestFlight (→ **MA5d** nur nach Auftrag).

Apps: `apps/kalender` · `apps/mitarbeiter`  
App-IDs: [`MOBILE-APPS-REST-PLAN.md`](MOBILE-APPS-REST-PLAN.md) §5.1 · Privacy: [`MOBILE-APPS-PRIVACY.md`](MOBILE-APPS-PRIVACY.md)  
Interne APKs: [`downloads/README.md`](../downloads/README.md)

---

## 0. Vor dem Build (beide Apps)

| # | Check | Hinweis |
|---|--------|---------|
| 0.1 | Branch/Stand freigegeben | Getestet auf Dev; Code-Kern-Deploy/Sync wenn PHP-API mitgehört |
| 0.2 | `applicationId` / Bundle-ID | `de.ganzsoft.dgkalender` · `de.ganzsoft.dgmitarbeiter` — **kein** Placeholder |
| 0.3 | Anzeigename + Icons | Android-Label, Web-Manifest, Branding-Icon vorhanden |
| 0.4 | Version | `pubspec.yaml` → `version: x.y.z+build` (Name + Build-Nummer) |
| 0.5 | Smoke | `bash bin/mobile-flutter-minstand.sh` oder je App analyze/test |
| 0.6 | Privacy-Text gelesen | Store-Fragen später aus `MOBILE-APPS-PRIVACY.md` |
| 0.7 | Kein Tenant-Hardcode | Keine Kundenfirmen/Personen in App-Defaults |

---

## 1. Android — App Bundle (Play Store) und APK (intern)

### 1.1 Signing (Pflicht vor Store)

Aktuell in `android/app/build.gradle.kts`: Release signiert noch mit **debug**-Config (nur Dev/Smoke).

Vor Play Store:

1. Upload-Keystore erzeugen (nicht committen; Secret/Passwort nur in CI oder lokal sicher).
2. `key.properties` (gitignored) oder CI-Secrets: `storeFile`, `storePassword`, `keyAlias`, `keyPassword`.
3. `buildTypes.release.signingConfig` auf den Release-Key setzen.
4. Play App Signing: Google-Verwaltung des App-Signing-Keys empfohlen.

### 1.2 Build-Befehle

```bash
# Kalender — AAB (Play)
cd apps/kalender
flutter pub get
flutter build appbundle --release
# → build/app/outputs/bundle/release/app-release.aab

# Kalender — APK (intern /app-download)
flutter build apk --release
cp build/app/outputs/flutter-apk/app-release.apk ../../downloads/dg-kalender.apk

# Mitarbeiter — AAB
cd ../mitarbeiter
flutter pub get
flutter build appbundle --release
# → build/app/outputs/bundle/release/app-release.aab

# Mitarbeiter — APK
flutter build apk --release
cp build/app/outputs/flutter-apk/app-release.apk ../../downloads/dg-mitarbeiter.apk
```

### 1.3 Nach dem Android-Build

| # | Check |
|---|--------|
| 1.a | AAB/APK-Größe plausibel; App öffnet, CRM-URL setztbar |
| 1.b | Login-Pfad (Kunde bzw. Kennung+PIN) gegen eine Test-Instanz |
| 1.c | Package-Name in Geräte-Info = `de.ganzsoft.*` |
| 1.d | Interne APKs ggf. auf Code-Kern/`downloads/` legen (nicht zwingend committen) |
| 1.e | **Kein** Play-Upload in MA5c — erst nach explizitem Auftrag |

---

## 2. iOS — Archive (Xcode)

Im Repo ist **noch kein** `ios/`-Ordner. Vor dem ersten Archive:

1. `flutter create --platforms=ios .` in der jeweiligen App (oder Projekt neu mit iOS).
2. Bundle-ID setzen: `de.ganzsoft.dgkalender` / `de.ganzsoft.dgmitarbeiter`.
3. Display Name: **DG Kalender** / **DG Mitarbeiter**.
4. Signing Team / Provisioning in Xcode (Apple Developer).
5. Icons aus `assets/branding/app_icon.png` (z. B. via `flutter_launcher_icons`, `ios: true`).

### 2.1 Build / Archive

```bash
cd apps/kalender   # bzw. mitarbeiter
flutter pub get
flutter build ipa --release
# oder: Xcode → Product → Archive
```

| # | Check |
|---|--------|
| 2.a | Archive ohne Signing-Fehler |
| 2.b | Bundle-ID korrekt; kein Placeholder |
| 2.c | App startet auf Gerät/Simulator; Basis-URL + Login |
| 2.d | **Kein** App-Store-Connect-Upload / TestFlight in MA5c (→ MA5d nur nach Auftrag) |

---

## 3. Kurz-Checkliste „Release-fertig?“

- [ ] Version in `pubspec.yaml` erhöht
- [ ] Android: Release-Signing (nicht Debug) für Store-AAB
- [ ] Android: `flutter build appbundle --release` OK
- [ ] Optional intern: APK nach `downloads/dg-*.apk`
- [ ] iOS: Projekt vorhanden, Bundle-ID, Archive/IPA OK
- [ ] Privacy-Punkte für Store-Formulare aus `MOBILE-APPS-PRIVACY.md` vorbereitet
- [ ] Store-Upload / TestFlight **bewusst zurückgestellt** (MA5d)

---

## 4. Verweise

| Datei | Rolle |
|-------|--------|
| `downloads/README.md` | Interne APK-Dateinamen |
| `bin/mobile-flutter-minstand.sh` | Analyze + Tests |
| `bin/mobile-release-checklist-selftest.php` | Guard MA5c |
| `docs/MOBILE-APPS-PRIVACY.md` | Datenfluss / Store-Privacy-Stichworte |
