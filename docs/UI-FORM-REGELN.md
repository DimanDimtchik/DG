# CRM UI — Form-/Button-Regeln (verbindlich)

> Stand: 2026-10-30 · Quelle: `assets/css/dg.css` (Spacing-Skala, Button- und Aktions-Algorithmen)

## Warum manche Seiten „willkürlich“ wirken

Das Design-System greift **nur**, wenn das Markup die vorgesehenen Klassen nutzt.  
Neue Lohn-Seiten haben oft nackte `<button class="dg-button">` direkt nach Textarea/Grid gelegt — dann fehlen Abstand und Aktionszeile → **Verschmelzung**.

## Pflicht-Muster

1. Formulare: `class="dg-form"`
2. Felder: `label.dg-field` (ggf. in `.dg-form-grid`)
3. Submit-Zeile: **immer** `.dg-form-actions` (oder Inline `.dg-btn-row` / `.dg-field--actions`)
4. Buttons:
   - Standard: `.dg-button`
   - Hauptaktion: `.dg-button--primary` (oder erster Submit in Aktionszeile ohne Variante)
   - Kompakt in Tabellen: `.dg-button--small`
   - Sekundär/Ghost/Danger: `--ghost` / `--danger` / `--warning` / `--back`
5. Keine Ad-hoc-`style="margin…"` für Abstände — `--dg-space-*` / Utilities (`.dg-mt-3`, `.dg-stack`, `.dg-gap-3`)

## CSS-Sicherheitsnetz

Seit 2026-10-30: auch nackter `.dg-button` nach `.dg-form-grid` / `.dg-field` bekommt `margin-top`.  
Das ersetzt **nicht** `.dg-form-actions` (Trennlinie + konsistente Aktionszeile).

## Check vor Merge neuer CRM-UI

- [ ] Kein Submit direkt unter Textarea ohne `.dg-form-actions`
- [ ] Keine zweite Button-„Erfindung“ neben `.dg-button*`
- [ ] Inline-`display:flex;gap` nur wenn `.dg-btn-row` / `.dg-stack` nicht passt
