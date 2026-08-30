# Handoff — Website-Menü Icons (Erweiterung)

Stand: 2026-08-30 · Branch: `master`

## Ziel

Untermenü-Icons pro Eintrag konfigurieren (keine globalen Icon-Stile). Lucide lokal eingebunden, großer Icon-Picker mit Suche.

## Umgesetzt

### Untermenü `icon_style` (pro Child)

| Feld | Werte | Wirkung |
|------|--------|---------|
| `visibility` | `show` / `hidden` | Icon ein-/ausblenden |
| `badge` | Text (max. 24) | Kleines Label neben Menütext |
| `size` | sm / md / lg | Icon-Größe |
| `color` | primary / text / inherit / custom | Icon-Farbe |
| `color_custom` | `#rrggbb` | Bei color=custom |
| `hover` | inherit / primary / text / custom | Hover-Farbe |
| `hover_color_custom` | `#rrggbb` | Bei hover=custom |
| `position` | left / right | Icon links/rechts |
| `gap` | tight / normal / wide | Abstand Icon–Text |
| `stroke` | light / normal / bold | Strichstärke |
| `hide_mobile` | bool | Icon im Hamburger-Menü aus |

Hauptmenü-Icons: nur Icon-Auswahl (auto/manuell), keine `icon_style`.

### Lucide (lokal)

- Daten: `src/Website/data/lucide-menu-icons.php` (~1534 Icons)
- Build: `python3 bin/build-website-menu-lucide.py`
- Legacy-Aliase: `home`→`house`, `contacts`→`users`, …

### UI

- CRM → Website → Menü → Untermenü → Icon-Darstellung
- Icon-Picker: Suche + Featured-Icons, kein CDN

## Dateien

| Datei | Rolle |
|-------|--------|
| `src/Website/WebsiteMenuIcons.php` | API, Normalisierung, SVG |
| `src/Website/data/lucide-menu-icons.php` | Generierte Pfade |
| `views/partials/website-menu-icon-field.php` | Picker |
| `views/partials/website-menu-submenu-icon-style.php` | Style-Felder |
| `views/modules/website-menu.php` | Editor + JS |
| `views/website-public.php` | Öffentliche Navigation |

## Deploy

```bash
# Cloud-Agent oder PC: SFTP/SCP + SSH-Key (Passwort leer) — wie KlarWin nach /klarwin/
bash bin/cloud-agent-ssh-setup.sh
bash bin/deploy-via-sftp.sh --all
ssh allinkl-ganzom "bash www/htdocs/w0217246/dg.ganz-om.de/bin/sync-crm-from-master.sh"

# PC-Alternative
./deploy.bat
```

Secrets: nur `DG_ALLINKL_SSH_*` (siehe `docs/CLOUD-AGENT-ACCESS.md`). **Kein** KAS-Passwort, kein manueller KAS-Upload.

## Test

1. Menü → Untermenüpunkt → Icon wählen (Suche „mail“)
2. Badge „Neu“, Hover Primärfarbe, Mobile aus → speichern
3. Öffentliche Seite: Dropdown prüfen (Desktop + schmale Breite)
