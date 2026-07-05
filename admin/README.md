# Admin-Module für dg.ganz-om.de

Diese Module werden für die CRM-Integration auf **dg.ganz-om.de** bereitgestellt.

| Ordner | CRM-Menü |
|--------|----------|
| `Kontakte/` | Kontakte (ehem. dg-user-plugin) |
| `Terminkalender/` | Terminkalender |

- **Shortcodes** bleiben im Modul-Code erhalten.
- **Seiten** für Shortcodes werden später manuell angelegt (kein Auto-Setup).
- Kontakte Standard-Ausgabemodus für CRM: `public_only` (kein WP-Admin-Menü).

Deploy aus den Quell-Repos:

```powershell
..\dg-user-plugin\deploy-dg.ps1
..\Terminkalender\deploy-dg.ps1
```

Oder alles zusammen aus dem DG-Repo:

```powershell
.\deploy.ps1 -WithPlugins
```
