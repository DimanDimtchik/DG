# App-Downloads (Android APK)

Hier die gebauten Flutter-APKs ablegen (Dateinamen fest):

| Datei | App |
|-------|-----|
| `dg-kalender.apk` | Termin-App (Kunden) |
| `dg-mitarbeiter.apk` | Mitarbeiter-App |

Öffentliche Seite: `/apps` (bei Installation bzw. Migration angelegt).

Build-Beispiel:

```bash
cd apps/kalender && flutter build apk --release
cp build/app/outputs/flutter-apk/app-release.apk ../../downloads/dg-kalender.apk

cd ../mitarbeiter && flutter build apk --release
cp build/app/outputs/flutter-apk/app-release.apk ../../downloads/dg-mitarbeiter.apk
```

`downloads/` wird per Sync vom Master mitverteilt (ohne Geheimnisse). Große APKs ggf. nicht committen — nur auf dem Server ablegen.
