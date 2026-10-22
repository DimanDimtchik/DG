# Admin

Früher lagen hier WordPress-Plugin-Bundles (`Kontakte/`, `Terminkalender/`) aus separaten Repos.

**Stand heute:** Kontakte, Terminkalender und Einstellungen sind native Module im CRM unter `src/` (u. a. `Contact`, `Calendar`, `Booking`, `Settings`). Deploy läuft nur noch über `deploy.bat` / `deploy.ps1` — keine Geschwister-Repos nötig.
