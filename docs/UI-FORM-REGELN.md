# CRM UI — Form-/Button-Regeln (verbindlich)

> Stand: 2026-10-30 · Quelle: `assets/css/dg.css` (Spacing-Skala, Button- und Aktions-Algorithmen)  
> **Verankert in:** [`AGENTS.md`](../AGENTS.md) §7 · `.cursor/rules/00-dg-regeln.mdc` · Guard `bin/ui-form-spacing-selftest.php`  
> **Kein Extra-Prompt nötig** — Agenten müssen diese Regeln bei jeder UI-Arbeit von selbst einhalten.

## Warum manche Seiten „willkürlich“ wirkten

Das Design-System greift **nur**, wenn das Markup die vorgesehenen Klassen nutzt.  
Ohne `.dg-form-actions` / `.dg-button--small` entstehen Verschmelzung und Link-neben-Button.

## Pflicht-Muster

1. Formulare: `class="dg-form"`
2. Felder: `label.dg-field` (ggf. in `.dg-form-grid`)
3. Submit-Zeile: **immer** `.dg-form-actions` (oder Inline `.dg-btn-row` / `.dg-field--actions`)
4. Buttons:
   - Standard: `.dg-button`
   - Hauptaktion: `.dg-button--primary` (oder erster Submit in Aktionszeile ohne Variante)
   - Kompakt in Tabellen: `.dg-button--small`
   - Sekundär/Ghost/Danger: `--ghost` / `--danger` / `--warning` / `--back`
5. Tabellen-Aktionen: **alle** Aktionen als `.dg-button.dg-button--small` in `.dg-table__actions` → `.dg-table__actions-group` — **kein** nackter Textlink neben einem Button (z. B. Bearbeiten + Deaktivieren).
6. Keine Ad-hoc-`style="margin…"` für Abstände — `--dg-space-*` / Utilities (`.dg-mt-3`, `.dg-stack`, `.dg-gap-3`)

## CSS-Sicherheitsnetz

Seit 2026-10-30: auch nackter `.dg-button` nach `.dg-form-grid` / `.dg-field` bekommt `margin-top`.  
Das ersetzt **nicht** `.dg-form-actions` (Trennlinie + konsistente Aktionszeile).

## Check vor Merge neuer CRM-UI

- [ ] Kein Submit direkt unter Textarea ohne `.dg-form-actions`
- [ ] Keine zweite Button-„Erfindung“ neben `.dg-button*`
- [ ] Inline-`display:flex;gap` nur wenn `.dg-btn-row` / `.dg-stack` nicht passt
