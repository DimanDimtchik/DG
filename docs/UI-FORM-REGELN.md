# CRM UI — Form-Regeln

> **Kanonsich ab 2026-10-30:** vollständiger Katalog → **[`UI-KLASSENKATALOG.md`](UI-KLASSENKATALOG.md)**  
> (Buttons, Links/`a`, Tabellen, Panels, Tokens, Feature-Grenzen — nicht nur `<form>`.)

Diese Datei bleibt als Kurzverweis, damit alte Links/AGENTS-Verweise nicht brechen.

## Form-Kern (Auszug)

1. `.dg-form` + `.dg-field` / `.dg-form-grid`  
2. Submit immer in `.dg-form-actions` (oder `.dg-btn-row` / `.dg-field--actions`)  
3. Tabellen-Aktionen: `.dg-button.dg-button--small` in `.dg-table__actions` → `.dg-table__actions-group`  
4. Guard: `php bin/ui-form-spacing-selftest.php`  
5. **Keine Mehrfeld-/Upload-Formulare in Tabellenzellen** — Liste/Status in der Tabelle, Upload als eigenes `.dg-form` darunter (sonst verschmelzen Buttons/Felder). Panels stapeln mit `.dg-stack`.  
6. **Abstand nach Formularende:** Folgt nach `</form>` ein `.dg-panel` / `.dg-stack`, greift `.dg-form + .dg-panel|.dg-stack` (Speichern-Button darf nicht am nächsten Panel kleben).

Alles Weitere (Entscheidungsbaum für `<a>`, Badge, Panel, Sidebar, Tokens): **Klassenkatalog**.
