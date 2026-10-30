# CRM UI — Klassenkatalog (verbindlich)

> Stand: **2026-10-30** · Quelle: **`assets/css/dg.css`** (eine Datei, Design System)  
> Verankert: [`AGENTS.md`](../AGENTS.md) §7 · `.cursor/rules/00-dg-regeln.mdc` · Guard `bin/ui-form-spacing-selftest.php`  
> Form-Detail (historisch): [`UI-FORM-REGELN.md`](UI-FORM-REGELN.md) → zeigt hierher  

**Ziel:** Ein Katalog für **alle** wiederkehrenden UI-Bausteine (nicht nur `<form>`).  
Cloud-/Lokal-Agenten erfinden **keine** parallelen Klassen pro Seite.

---

## 0. Architektur (kurz)

| Schicht | Was | Beispiel |
|---------|-----|----------|
| Tokens | `:root` Variablen | `--dg-space-*`, `--dg-primary`, `--dg-control-h` |
| Kern-Primitives | überall gleich | `.dg-button`, `.dg-table`, `.dg-panel`, `.dg-form` |
| Layout-Shell | App-Rahmen | `.dg-sidebar`, `.dg-adminbar`, `.dg-wrap` |
| Feature-Zusätze | nur echte Sonder-UI | `.dg-lager…`, `.dg-akademie…` — **kein** Ersatz für Kern-Buttons/Tabellen |

Es gibt **keine** tausend CSS-Dateien pro Seite. Es gibt **eine** Quelle `dg.css` mit ~880 `.dg-*`-Klassen; neu schreiben nur, was der Katalog nicht abdeckt — und dann den Katalog nachziehen.

**Auslieferung (ohne Split):** `bin/minify-dg-css.sh` erzeugt `assets/css/dg.app.min.css`.  
`Asset::crmStylesheetUrl()` liefert die Min-Datei, wenn sie aktuell ist.  
`assets/css/dg.min.css` = **nur** Offline-Stub (nicht die App). Debug: `DG_CSS_SOURCE=1`.

---

## 1. Tokens (nie hardcoden)

| Token | Bedeutung |
|-------|-----------|
| `--dg-space-1` … `--dg-space-5` | 4 / 8 / 12 / 16 / 24 px |
| `--dg-control-h` / `--dg-control-h-sm` | 40px / 32px Kontrollhöhe |
| `--dg-primary` (+ hover, focus-ring) | Marken-/Aktionsfarbe (Theme) |
| `--dg-border`, `--dg-surface`, `--dg-text*` | Flächen/Text |
| `--dg-radius-sm`, `--dg-font`, `--dg-transition` | Form/Typo |

Utilities: `.dg-mt-*`, `.dg-mb-*`, `.dg-gap-*`, `.dg-stack` / `--tight` / `--loose`.

---

## 2. Seite / Layout

| Element | Klasse | Pflicht |
|---------|--------|---------|
| Seitenrahmen | `.dg-wrap` | ja (Modul-Inhalt) |
| Titelzeile | `.dg-page-header` (+ `--toolbar`) | ja bei Modulköpfen |
| H1 | `.dg-page-title` | ja |
| Lead-Text | `.dg-lead` | empfohlen |
| Toolbar rechts | `.dg-toolbar` / `.dg-page-header__actions` | bei Aktionen im Header |
| Unterüberschrift | `.dg-subsection-title` | in Panels |
| Zurück-Nav | `.dg-back-nav` + `.dg-button--back` | Listen←Detail |

**Nicht:** eigene `h1 { … }` oder Seiten-Wrapper-Klassen statt `.dg-wrap`.

---

## 3. Flächen

| Element | Klasse | Wann |
|---------|--------|------|
| Karte / Block | `.dg-panel` | Standard-Inhalt |
| Varianten | `--compact` `--muted` `--nested` `--notice` `--danger` `--wide` | nur bei Bedarf |
| Panel-Toolbar | `.dg-panel__toolbar` | Aktionen im Panel-Kopf |
| Grid-Kacheln | `.dg-grid` | Dashboard-ähnliche Raster |
| Karte (selten) | `.dg-card` | nur wo Panel nicht passt |

---

## 4. Typografie / Hinweise

| Element | Klasse |
|---------|--------|
| Gedämpfter Text | `.dg-muted` |
| Flash / Alert | `.dg-flash` + `--success` `--error` `--warning` `--info` |
| Badge / Status | `.dg-badge` + `--ok` `--pending` `--error` `--muted` `--accent` |
| Definitionsliste | `.dg-dl` / `--compact` |
| Feldhinweis | `.dg-field-hint` (+ `--warn` `--error`) |

---

## 5. Links (`<a>`) — Entscheidungsbaum

| Absicht | Markup | Nicht |
|---------|--------|-------|
| **Aktion** (Bearbeiten, Öffnen, Speichern-Optik, Toolbar) | `<a class="dg-button …">` bzw. `--small` / `--primary` / `--ghost` | nackter Textlink neben Buttons |
| **Tabellen-Aktion** | `.dg-button.dg-button--small` in `.dg-table__actions` → `.dg-table__actions-group` | nacktes `<a>Bearbeiten</a>` |
| **Sidebar-Nav** | `.dg-sidebar__link` (Layout, nicht Inhalt) | `.dg-button` in der Sidebar |
| **Fließtext-Link** (Doku, externe URL im Absatz) | normales `<a href>` im Content | als Button stylen |
| **Button der wie Link aussieht** (selten, z. B. Inline-Aktion ohne Navigation) | `button.dg-linklike` / `.dg-link-button` | neue „link-button“-Klasse erfinden |

**Regel:** Steht die Aktion in einer Aktionszeile oder Tabellenzelle neben anderen Controls → **immer** `.dg-button*`, nie nackter Link.

---

## 6. Buttons (`button` / aktives `a`)

| Variante | Klasse | Rolle |
|----------|--------|-------|
| Standard / sekundär | `.dg-button` | Abbrechen, neutrale Aktion |
| Hauptaktion | `.dg-button--primary` | Speichern, Anlegen, Import |
| Kompakt | `.dg-button--small` | Tabellen, dichte Toolbars |
| Ghost | `.dg-button--ghost` | sehr zurückhaltend |
| Gefahr | `.dg-button--danger` | Löschen / destruktiv |
| Warnung | `.dg-button--warning` | riskant, nicht destruktiv |
| Zurück | `.dg-button--back` | Navigation zurück |
| Volle Breite | `.dg-button--block` | seltene Vollbreiten-CTA |

**Algorithmus:** Erster `type="submit"` in `.dg-form-actions` / `.dg-btn-row` / `.dg-field--actions` ohne Variante erhält Primary-Look automatisch.

**Verboten:** neue Button-Klassen, Inline-Farben, serverseitig „einmalig“ gestylte Buttons.

---

## 7. Formulare

| Baustein | Klasse |
|----------|--------|
| Form | `.dg-form` |
| Feldgrid | `.dg-form-grid` (+ `--compact`) |
| Feld | `label.dg-field` (+ `--wide` `--checkbox` `--actions`) |
| Checkbox-Zeile | `.dg-checkbox` / `.dg-field--checkbox` |
| Abschnitte | `.dg-form-section` (flex-Spalte + `gap` — auch für Checkbox-Stapel in fieldset) |
| **Aktionszeile** | `.dg-form-actions` (+ `--split`) — **Pflicht** vor Submit |
| Inline-Filter | `.dg-btn-row` |
| Nur-GET-Filter neben Feld | `.dg-btn-row` / Stack, nicht nackter Button unter Textarea |

Abstand: Tokens / `.dg-stack`; CSS-Fallback für nackte Buttons nach Grid/Feld existiert — ersetzt **nicht** `.dg-form-actions`.

---

## 8. Tabellen

| Baustein | Klasse |
|----------|--------|
| Wrapper | `.dg-table-wrap` |
| Tabelle | `.dg-table` (+ `--compact`) |
| Aktionszelle | `.dg-table__actions` |
| Aktionsgruppe | `.dg-table__actions-group` |
| Leer / Zahl / Summe | `.dg-table__empty` / `__num` / `__total` |

Alle Zellen-Aktionen = Small-Buttons in `__actions-group`. Kein Mix Link+Button in derselben `td`.

---

## 9. Navigation / Tabs

| Baustein | Klasse |
|----------|--------|
| Sidebar | `.dg-sidebar` + `__section` `__heading` `__list` `__link` `__icon` |
| Admin-Leiste | `.dg-adminbar` (+ BEM-Kinder) |
| Unter-Tabs | `.dg-subtabs` / `__link` / `__group` |
| Pagination | `.dg-pagination` |

---

## 10. Feature-Klassen (erlaubt, aber begrenzt)

Erlaubt für **modulspezifische** Layouts (Kalender-Raster, Lager-Scan, Akademie-Karte, …), wenn:

1. Kern-Primitive (`button`/`table`/`form`/`panel`) **weiterhin** genutzt werden  
2. keine zweite Button-/Tabellen-Optik entsteht  
3. der Name `dg-<modul>-…` klar featurebezogen ist  

**Nicht erlaubt:** `.dg-lohn-button-special`, seitenlokales `<style>`, parallele `.btn`/`.button`-Systeme.

---

## 11. Checkliste vor Merge (jede UI-Änderung)

- [ ] Seite nutzt `.dg-wrap` / `.dg-page-title` / `.dg-panel` wo üblich  
- [ ] Aktionen = `.dg-button*` (Tabelle: `--small` + `__actions-group`)  
- [ ] Form-Submit in `.dg-form-actions`  
- [ ] Keine neuen globalen Button-/Link-Klassen  
- [ ] `php bin/ui-form-spacing-selftest.php` grün  

---

## 12. Guard / Allowlist

- Lohn-Views: **null** nackte Aktionslinks; Filterzeilen = `.dg-form.dg-btn-row` (kein Inline `display:flex`)  
- CRM-weit: **null** nackte Aktionslinks (`Bearbeiten`/`Anzeigen`/…); Allowlist in `bin/ui-form-spacing-selftest.php` aktuell **leer**  
- Link+Button-Mix in derselben `td`: **verboten**  
- Nackter Submit direkt nach Textarea: **verboten** (immer `.dg-form-actions`)  
- Report-Scan: `php bin/ui-crm-scan.php` (zusätzliche Hinweiskategorien, z. B. Checkbox-Markup)  
- Katalog-Datei und Kernklassen in `dg.css` müssen existieren (Selftest)
