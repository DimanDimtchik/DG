# Website-Blöcke — Advanced & Content v2 (Source of Truth)

Stand: **2026-09-21** · Bezug: `WebsiteBlockAdvanced`, `assets/js/website-builder.js`, `/vorschau/`

> Zwei Schienen: **Darstellung (Advanced)** vs. **Inhalt (normale Einstellungen)**. Nicht vermischen.

---

## 1. Architektur (Ist)

- Speicherung: `block.advanced` im Layout-JSON (Allowlist, sanitize in PHP + JS).
- Öffentlich: Inline-CSS + Attribute an `.ws-block` (`WebsiteBlockAdvanced`).
- Editor-Live: kleines iframe `/vorschau/{slug}?frame=1` nur **gewählter Block** (Crop-Mode).
- Hover/Visited: **nicht** über beliebiges Inline-CSS am Wrapper lösen → gezielte Klassen für Link/Button.

---

## 2. Schiene A — Darstellung (Advanced-Popover)

Tabs bleiben: Anzeige | Farben | Rahmen | Attribute (+ ggf. Hintergrund).

### A1 — Rahmen (Chat W1)

| Feld | Allowlist |
|------|-----------|
| width / style / color | wie bisher, plus **je Seite**: `top/right/bottom/left` (width+style+color) |
| radius | **ein Wert** + optional **je Ecke** oder shorthand 1–4 Längen |
| RGBA | `rgba(r,g,b,a)` und `#rrggbbaa` optional; Hex weiter erlaubt |

Selftest erweitern: ungültige Farben verwerfen.

### A2 — Hintergrundbild (Chat W2)

| Feld | Allowlist |
|------|-----------|
| `background` Farbe | Hex/RGBA |
| `backgroundImage` | nur `url("…")` mit gleicher Origin bzw. `/media/…` / https allowlist wie Bild-Block |
| `backgroundSize` | `cover` \| `contain` \| `auto` \| Längen-Whitelist |
| `backgroundPosition` | Enum/`center`/`top`/`left`… oder sichere Keyword-Liste |
| `backgroundRepeat` | `no-repeat` \| `repeat` \| `repeat-x` \| `repeat-y` |

UI: „Aus Mediathek“ wie Bild-Block. Kein freies CSS.

### A3 — Hover / Visited (Chat W5, **nach** W1–W4)

Nur Blocktypen `button` und Text/Überschrift **mit Link**:

- Generierte Klasse z. B. `ws-adv-h-{hash}` + `<style>` nur in Public/Preview mit Allowlist-Properties (`color`, `background`, `text-decoration`, `opacity`, `border-color`).
- Kein `javascript:`, kein `@import`.

### A4 — Nicht in Advanced

Fett, Zitate, Listen, freie HTML-Tags, beliebige Links im Fließtext → Schiene B.

---

## 3. Schiene B — Inhalt (normale Inspector-Felder)

### B1 — Zeichen-Picker (Chat W3)

- Position: **über** den Text-Eingabefeldern (Überschrift/Text), Icon-Button.
- Dropdown-Gruppen: HTML Symbol Entities | HTML Character Entities | Emojis (kuratierte Listen, nicht die ganze Unicode-Welt).
- Insert an **Cursorposition** im focused `<input>`/`<textarea>` (Selection API).
- Keine Speicherung extra — nur Zeichen in den Feldwert.

### B2 — Formatierung & Link (Chat W4)

Minimal, sicher:

- Toolbar: Fett (`**…**` oder gespeichertes sicheres Markup — Entscheidung in Chat W4 festhalten; Prefer: eingeschränktes Subset, das `WebsiteContent` schon escaped/rendert).
- Link: Auswahl oder URL-Feld für Button bleibt; für Text optional `a[href]` nur `http(s):`/`mailto:`/`/`.
- Zitat: optional Blockquote-Darstellung über `block.quote = true` oder Prefixed-Markup.

**Entscheidungspflicht in W4:** Speichern wir weiter Plaintext + Marker oder strukturiertes JSON? Default-Vorschlag: Plaintext mit wenigen Markern, die serverseitig zu sicherem HTML werden (wie bisher escape + nl2br, plus `**fett**` und `[text](url)`).

---

## 4. Chat-Phasen (Token-sparend)

| Chat | Scope | Dateien (nur diese) |
|------|--------|---------------------|
| **W1** | Rahmen je Seite + Radius + RGBA | `WebsiteBlockAdvanced.php`, `website-builder.js`, Selftest, `website-public.php` nur wenn nötig |
| **W2** | Hintergrundbild + size/position/repeat | wie W1 + Mediathek-Hook analog Bild |
| **W3** | Zeichen-Picker | `website-builder.js`, `dg.css` |
| **W4** | Fett/Link Content | `website-builder.js`, `WebsiteContent.php`, `website-public.php` |
| **W5** | Hover/Visited Button/Link | Advanced + Public CSS/Style-Injection |

**Chat-Vorlage:**

```text
Scope: Website W1 laut docs/WEBSITE-BLOCK-ADVANCED-V2.md
Nur die dort genannten Dateien. Kein Rezeptur, kein Deploy außer ich sage es.
Selftest muss grün sein.
```

---

## 5. Sicherheit

- Alles über Allowlist; leere Werte weglassen.
- Kein freies Custom-CSS-Feld.
- Hintergrund-URL wie Bild-`src` prüfen.
- RGBA: nur numerische Komponenten, alpha 0–1.
- Entities/Emojis: Insert als Text, nie als HTML-Attribut ungefiltert.

---

## 6. Abnahme

- Speichern → `/vorschau/{slug}` zeigt Styles am Block.
- Popup-Crop zeigt nur gewählten Block.
- Ungültige Eingaben verschwinden nach Normalize (Selftest).
