# DG CRM — UI/CSS-Rest (tokenarm)

> **Eine Chat-Session = eine Mini-ID.** Ziel: Rest-Spacing/Checkbox/Readonly ohne teuren Agent.
> Katalog: [`UI-KLASSENKATALOG.md`](UI-KLASSENKATALOG.md) · Guard: `php bin/ui-form-spacing-selftest.php` · Scan: `php bin/ui-crm-scan.php`

Stand: **2026-11-08** · Branch-Empfehlung: `cursor/ui-css-…`

---

## Warum tokenarm (Free-/Hobby-Plan)

| Regel | Bedeutung |
|-------|-----------|
| **Kein Explorer-Chat** | Kein „schaue alle Views an“ — nur die Datei(en) der Mini-ID |
| **Kein CSS-Rewrite** | Nur Katalog-Klassen / vorhandene Utilities (`dg-mt-*`, `dg-stack`, …) |
| **Kein Design-Neu** | Keine neuen Themes, keine Feature-Button-Klassen |
| **1 ID = 1 Commit-fähig** | Nach Schritt: Guard grün → fertig |
| **Lokal zuerst** | Alles mit 🟢 kannst du ohne Agent: Edit + Selftest |

**Chat-Prompt-Vorlage (kopieren):**  
`Nur Mini-ID U3a laut docs/UI-CSS-REST-PLAN.md. Nur genannte Datei(en). Kein Refactor. Am Ende: php bin/ui-form-spacing-selftest.php und php bin/ui-crm-scan.php nennen.`

**Nicht im Agent-Chat:** gesamten `dg.css` umbauen, alle Inline-Styles global suchen, Katalog neu erfinden, parallele Mini-IDs.

---

## Token-Stufen

| Symbol | Wann | Typisch |
|--------|------|---------|
| 🟢 | **Lokal / Free** — 1 Klasse in CSS, 1 View-Zeile, Doc, Guard | Standard |
| 🟡 | **Kurz-Agent** — 1 Datei, klarer Diff | Sparsam |
| 🔴 | **Teuer** — viele Dateien, Layout-Review, Print-CSS | Nur wenn 🟢/🟡 vorbereitet; **auf Free vermeiden** |

---

## Ist-Stand (Inventar 2026-11-08)

| Fund | Zahl / Ort | Bedeutung |
|------|------------|-----------|
| Guard `ui-form-spacing-selftest` | grün | Kern-Form-Regeln ok |
| Scan `ui-crm-scan` | 1 Treffer: `views/website-kiosk.php` Checkbox | klarer Fix |
| Klasse `.dg-readonly` | in `tab-lohn.php` genutzt, **kein CSS** | optischer Bruch Firmendaten |
| Inline `style="margin|padding|gap|flex"` | ~63 View-Dateien | durch Utilities ersetzen |
| Top-Dateien Inline | `rezeptur-*`, `tab-letterhead`, `kdv-*`, `lohn-*` | in Wellen abarbeiten |
| Checkbox ohne Katalog-Klasse | u. a. Kiosk, Briefpapier, Notifications, Recruiting-Tabs | Markup-Angleich |

**Nicht Ziel dieses Plans:** Print-Layouts (`views/print/*`) umbauen — nur wenn Mini-ID ausdrücklich Print nennt (meist 🔴).

---

## Abhackplan (Mini-IDs)

Status: `[ ]` offen · `[~]` angefangen · `[x]` erledigt

### U0 — Fundament (🟢, lokal)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| U0a | CSS `.dg-readonly` (+ optional `.dg-field .dg-readonly`) in `dg.css`; Katalog-Zeile | 🟢 | [x] 2026-11-08 |
| U0b | Utility-Doku im Katalog: wann `dg-mt-2`…`dg-mt-5` / `dg-mb-*` / `dg-stack` statt Inline | 🟢 | [x] Katalog + Plan |
| U0c | Scan-Allowlist/Hinweis: Print-Views optional ignorieren (Doc-Satz) | 🟢 | [x] Katalog §1+§12 |
| U0d | Guard nach U0a: `ui-form-spacing-selftest` + manueller Blick Einstellungen→Lohn | 🟢 | [x] Guard grün |

**U0a Spez (verbindlich)**

```css
/* Nur-Anzeige in Formularen (Firmendaten-Snapshot o. Ä.) */
.dg-readonly {
  margin: 0;
  padding: var(--dg-space-2) var(--dg-space-3);
  border: 1px solid var(--dg-border, #d0d5dd);
  border-radius: var(--dg-radius, 6px);
  background: var(--dg-surface-muted, #f5f6f8);
  color: var(--dg-text, #111);
  min-height: 2.25rem;
  line-height: 1.4;
}
.dg-field > .dg-readonly { width: 100%; box-sizing: border-box; }
```

Keine neuen Farben erfinden — Tokens wie bestehende Form-Felder.

---

### U1 — Scan-Fails (🟢/🟡)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| U1a | `views/website-kiosk.php`: Checkboxen → `.dg-checkbox` (Kiosk behält eigenes Layout) | 🟢 | [x] 2026-11-08 |
| U1b | Scan erneut: `checkbox_without_field_checkbox` = 0 | 🟢 | [x] |

---

### U2 — Lohn / Firmendaten-Readonly (🟢/🟡)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| U2a | `views/settings/tab-lohn.php`: fieldset-Inline `border:0;padding:0…` → Klasse `.dg-form-section` ohne Style (CSS ggf. `.dg-form-section--bare` **nur wenn nötig**) | 🟡 | [x] 2026-11-08 fieldset.dg-form-section Reset |
| U2b | Sichtcheck: Block „Aus Firmendaten“ wirkt wie Formularfelder (Readonly), Abstände wie andere Sections | 🟢 | [ ] |
| U2c | `lohn-monatslauf` / `lohn-elster` / `lohn-auswertungen`: `style="margin…"` an Toolbar/Details → `dg-mt-*` / `dg-stack` (eine Datei = eine Session) | 🟡 | [x] 2026-11-08 |

Empfohlene Chat-Reihenfolge U2c: `U2c-monatslauf` → `U2c-elster` → `U2c-auswertungen` (je 1 Prompt).

---

### U3 — Settings-Wellen (🟡, je 1 Datei)

Nur **eine** Datei pro Chat. Prompt: `Nur Mini-ID U3 + Dateiname …`.

| ID | Datei | Was | Token | Status |
|----|-------|-----|-------|--------|
| U3a | `tab-letterhead.php` | Inline margin/padding → Utilities; Checkboxen Katalog | 🟡 | [x] 2026-11-08 |
| U3b | `tab-calendar-embed.php` | wie U3a | 🟡 | [x] 2026-11-08 |
| U3c | `tab-email.php` | wie U3a | 🟡 | [x] 2026-11-08 |
| U3d | `tab-notifications.php` | Checkboxen Katalog | 🟡 | [x] 2026-11-08 bereits konform (kein Diff) |
| U3e | `tab-steuerkanzlei.php` + `tab-chart-of-accounts.php` | Panel-Margins → `dg-mt-5` | 🟡 | [x] 2026-11-08 |
| U3f | Recruiting/Publish-Tabs (`tab-recruiting-*`, `tab-linkedin-recruiting`, `tab-tiktok-publish`) — Checkboxen | 🟡 | [x] 2026-11-08 |
| U3g | `tab-company.php` / `tab-belegdarstellung.php` / `tab-payment-terms.php` — fieldset-Margins | 🟡 | [x] 2026-11-08 |

---

### U4 — Modul-Wellen (🟡, je 1 Datei; große Dateien splitten)

| ID | Datei(en) | Hinweis | Token | Status |
|----|-----------|---------|-------|--------|
| U4a | `views/modules/kontakte.php` | Checkbox-Klasse | 🟢 | [ ] |
| U4b | `kdv-umfirmierung.php` / `kdv-provision.php` | Checkbox + wenig Spacing | 🟡 | [ ] |
| U4c | `rezeptur-form.php` | **viele** Inlines — nur `margin/padding/gap/flex` → Utilities; kein Logik-Umbau | 🟡 | [ ] |
| U4d | `rezeptur-maschine-form.php` | wie U4c | 🟡 | [ ] |
| U4e | `rezeptur-maschinen.php` | wie U4c | 🟡 | [ ] |
| U4f | `kdv-dashboard.php` / `kdv-kunde-form.php` | Spacing | 🟡 | [ ] |
| U4g | Rest-Module laut `rg style="…margin` (je Datei neue Mini-ID `U4g-<slug>`) | 🟡 | [ ] |

**Free-Plan-Tipp U4c–e:** Wenn der Agent zu viel Kontext zieht → lokal mit Suche/Ersetzen (`style="margin-top:1rem"` → `class="… dg-mt-4"`) und nur Guard im Agent.

---

### U5 — Abschluss (🟢)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| U5a | `php bin/ui-crm-scan.php` → alle Kategorien 0 (außer bewusst Doc-Ausnahmen) | 🟢 | [~] nach U0/U1 bereits 0 — erneut nach jeder Welle |
| U5b | `php bin/ui-form-spacing-selftest.php` grün | 🟢 | [~] nach U0/U1 grün |
| U5c | Kurz in `TODOS.md`: UI/CSS-Rest abhaken + Verweis dieser Datei | 🟢 | [~] Verweis da; Abhaken erst nach U2–U4 |
| U5d | Optional: `bash bin/minify-dg-css.sh` vor Deploy | 🟢 | [x] mit U0a |

---

## Empfohlene Free-Plan-Reihenfolge (erste Woche)

1. **U0a–U0d** lokal (Readonly-CSS) — größter sichtbarer Gewinn  
2. **U1a–U1b** lokal (Kiosk-Scan)  
3. **U2a–U2b** lokal oder 1 Kurz-Chat  
4. Dann **eine** Settings-Datei pro Tag (U3a…)  
5. Rezeptur/KDV nur wenn Token übrig — oder lokal  

**Vermeiden auf Free:** 🔴 Print-Review, „mach alle Inline-Styles weg“, Katalog-Großumbau.

---

## Parent-Übersicht

| ID | Thema | Status |
|----|--------|--------|
| U0 | Fundament Readonly + Utilities | [x] |
| U1 | Scan-Fails | [x] |
| U2 | Lohn / Firmendaten | [~] U2a+U2c ✅; U2b Sichtcheck offen |
| U3 | Settings-Wellen | [x] |
| U4 | Modul-Wellen | [ ] |
| U5 | Abschluss Guards + TODOS | [ ] |

---

## Nicht vergessen

- Eine CSS-Datei: `assets/css/dg.css`  
- Endnutzer-Texte: keine Plan-Codes (`U3a`) in der UI  
- Kein Tenant-Hardcode  
- Nach UI-Änderung: Guard grün  
- Print (`views/print/*`) und E-Mail-HTML: **nicht** in U3/U4 mischen  
