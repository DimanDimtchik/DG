# Rechtstexte — Mehrprodukt-Tabs

Stand: **2026-09-13**

## Pflichtseiten (Installation)

Bei Installation / Bootstrap werden automatisch angelegt:

| Slug | Inhalt |
|------|--------|
| `impressum` | Impressum (§ 5 TMG) |
| `datenschutz` | Datenschutzerklärung (DSGVO) |
| `agb` | AGB inkl. Widerrufshinweis (je Geschäftsart) |
| `widerruf` | Eigene Widerrufsbelehrung |

Generator: `LegalPageGenerator` · Bootstrap: `WebsiteBootstrapService`

## Mehrprodukt-Modus

**Einstellungen → Rechtliches / Produkte**

- Ohne Modus: ein Text pro Seite (Variante „Allgemein“).
- Mit Modus: Tabs je **Produktgruppe** auf derselben URL (z. B. `/datenschutz?produkt=klarwin`).

### Status je Tab (wie Website-Seiten)

| DB-Wert | UI | Bedeutung |
|---------|-----|-----------|
| `published` | Online | Tab öffentlich sichtbar |
| `draft` | Entwurf | Nur Vorschau (eingeloggt) |
| `private` | Offline | Nicht öffentlich |

## Technik

- Tabelle: `dg_website_legal_variants` (Migration 066)
- Settings: `legal_products` in SettingsStore
- Klassen: `LegalProductSettings`, `WebsiteLegalVariantRepository`, `LegalPagePublicHelper`

## KlarWin / HP LaserJet

Produktgruppen z. B. `klarwin`, `hp-laserjet700` anlegen — Texte aus dem KlarWin-Chat in die jeweilige Variante kopieren oder HTML einfügen.

Kein Sondercode pro Domain: Inhalte liegen in der **Instanz-DB**, Ordner `klarwin/` auf dem Server bleiben Nutzerinhalt (Sync-Ausschluss).
