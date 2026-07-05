# DG

Standalone-CRM für **dg.ganz-om.de** (ohne WordPress).

## Hosting

- **Domain:** https://dg.ganz-om.de
- **Server:** Kasserver (`w0217246.kasserver.com`)
- **SSH:** `ssh-w0217246@dg.ganz-om.de` (Key: `~/.ssh/id_ed25519_ganzom`)

## Zugang

- Öffentlich: schwarze Platzhalterseite (`/`)
- CRM-Login: `/login` (nur für Admin oder aktive Mitarbeiter)
- Nach Login: `/app` mit WP-Admin-ähnlicher Kopfzeile und Seitenmenü

### Menü (CRM)

| Eintrag | Modul | Zugriff |
|---------|-------|---------|
| Dashboard | Übersicht | alle CRM-Benutzer |
| Kontakte | dg-user-plugin | Admin |
| Terminkalender | Terminkalender-Plugin | Admin + Mitarbeiter |
| Einstellungen | Vereinte Plugin-Tabs (unten im Menü) | Admin |

Einstellungen bündeln alle Tabs aus **dg-user-plugin** (Kontakte) und **Terminkalender** in einer Oberfläche.

Plugins liegen unter `admin/` auf dem Server (nicht öffentlich erreichbar). Shortcodes bleiben im Modul-Code; Seiten dafür legen wir später an.

### Deploy

**Empfohlen (kein Kaspersky-Fehlalarm):**

```cmd
deploy.bat
```

Optional mit Migrationen:

```powershell
.\deploy.ps1 -Migrate
```

Plugins (falls Repos als Geschwisterordner liegen):

```powershell
.\deploy.ps1 -WithPlugins
```

**Kaspersky:** `deploy.ps1` kann fälschlich als `PDM:Trojan.Win32.Generic` gemeldet werden (Heuristik wegen ssh/scp). Das ist ein **Fehlalarm** — kein Trojaner. Lösung: `deploy.bat` nutzen oder in Kaspersky eine Ausnahme für `C:\Users\dietr\Projects\DG` setzen und die Datei aus der Quarantäne wiederherstellen.

### Demo-Accounts (Passwort: `demo`)

| Benutzer | Rolle | Menü |
|----------|-------|------|
| `admin` | Administrator | Admin |
| `leiter` | Mitarbeiter + Abteilungsleiter | Abteilung |
| `mitarbeiter` | Mitarbeiter | Abteilung |
| `ohne_abteilung` | Mitarbeiter ohne Abteilung | Hinweis im Dashboard |
| `gast` | Kein CRM-Zugang | Login abgelehnt |

Rollen orientieren sich am **dg-user-plugin** (`administrator`, `dg_eigenmitarbeiter`, Abteilungen mit `member`/`leader`).

## Entwicklung

```bash
git clone https://github.com/DimanDimtchik/DG.git
cd DG
php -S localhost:8080
```

## Struktur

- `index.php` – Routing
- `src/` – Auth, Rollen, Views
- `config/` – App-Konfiguration und Benutzer (später DB-Anbindung)
- `views/` – Templates
- `assets/` – CSS/JS/Logo
