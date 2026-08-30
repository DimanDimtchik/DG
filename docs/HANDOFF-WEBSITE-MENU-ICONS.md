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
# SSH (wenn Port 22 geht)
./deploy.bat
ssh allinkl-ganzom "bash www/htdocs/w0217246/dg.ganz-om.de/bin/sync-crm-from-master.sh"

# FTP-Fallback (wie KlarWin-Chat, wenn SSH timeout)
bash bin/cloud-agent-ftp-setup.sh
bash bin/deploy-via-ftp.sh --all
```

Secrets: `DG_KAS_LOGIN` + `DG_KAS_AUTH_DATA` in Cursor Cloud Agents (siehe `docs/CLOUD-AGENT-ACCESS.md`).

## Test

1. Menü → Untermenüpunkt → Icon wählen (Suche „mail“)
2. Badge „Neu“, Hover Primärfarbe, Mobile aus → speichern
3. Öffentliche Seite: Dropdown prüfen (Desktop + schmale Breite)
