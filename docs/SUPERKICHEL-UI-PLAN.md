# Super-Kichel — UI-Plan (tokenarm)

> **Eine Chat-Session = eine Mini-ID.** Freigabe vor Umsetzung.  
> Scope: [`SUPERKICHEL-PRODUKT-SKIZZE.md`](SUPERKICHEL-PRODUKT-SKIZZE.md) (SKC0a) · Server/Ollama: [`SUPERKICHEL-GEX-PLAN.md`](SUPERKICHEL-GEX-PLAN.md) · UI: [`UI-KLASSENKATALOG.md`](UI-KLASSENKATALOG.md) · Form: [`UI-FORM-REGELN.md`](UI-FORM-REGELN.md)  
> Guard: `php bin/ui-form-spacing-selftest.php`

Stand: **2026-11-12** · Dev: **`ganz-om.de`** · Seite: **`/superkihel`** (intern `/app?page=superkihel`) · Label UI: **Super-Kichel** (keine Plan-Codes in Endnutzer-Texten)

**Voraussetzungen:** SKC0a ✅ · SKB1–2 ✅ · SKC0 ✅ (Ollama auf Dev). Widget bleibt.

---

## 0. Ziel der UI

Eingeloggte Vollseite für längere Hilfe-Sitzungen: Verlauf, Eingabe, klare Aktionen — **dieselbe** PHP-Fachlogik wie das Widget (`KichelAssistant` / `POST /api/kichel`). Ollama nur Formulierung.

**Nicht in diesem Plan:** OpenHands/SearXNG in die CRM-Seite einbetten · Pretty-URL · separates Protokoll · Cutover GEX.

---

## 1. Parent + Mini-IDs

| ID | Aufgabe | Abnahme | Token | Status |
|----|---------|---------|-------|--------|
| **SKU0** | Dieser UI-Plan | Doc da · Haken in GEX/TODOS | 🟢 | [x] 2026-11-12 |
| **SKU1** | Seite-Gerüst `superkihel` — Kind-IDs unten | SKU1a–d ✅ | 🟢 | [x] 2026-11-12 |
| **SKU1a** | Ist: wie Seiten/Routen/Staff-Check im CRM (nur lesen) | [x] 2026-11-12 · siehe §5 | 🟢 |
| **SKU1b** | Route `page=superkihel` + Staff-only (kein View-Inhalt) | [x] 2026-11-12 · siehe §6 | 🟢 |
| **SKU1c** | Leere View: `.dg-page-header` / Titel / `.dg-panel` Platzhalter | [x] 2026-11-12 · Guard OK | 🟢 |
| **SKU1d** | Smoke Dev + Kurznotiz im Plan | [x] 2026-11-12 · siehe §8 | 🟢 |
| **SKU2** | Menü-Eintrag „Super-Kichel“ (sinnvolle Gruppe) | [x] 2026-11-12 · Navigation/Staff · §9 | 🟢 |
| **SKU3** | Chat-Layout (Mock, kein API) — Kind-IDs unten | SKU3a–d ✅ | 🟢 | [x] 2026-11-12 |
| **SKU3a** | Ist: Widget-Markup / `kichel.js` / `kichel.css` (nur lesen) | [x] 2026-11-12 · siehe §11 | 🟢 |
| **SKU3b** | Verlauf-Bereich + Begrüßung (statisch, kein JS/API) | [x] 2026-11-12 · §12 | 🟢 |
| **SKU3c** | Form: Textarea + Senden (ohne API; Submit no-op/Mock ok) | [x] 2026-11-12 · §13 | 🟢 |
| **SKU3d** | Guard + Kurznotiz (Dev/mobil) | [x] 2026-11-12 · §14 | 🟢 |
| **SKU4** | Anbindung `POST /api/kichel` — Kind-IDs unten | SKU4a–d ✅ | 🟢 | [x] 2026-11-12 |
| **SKU4a** | Ist: API-Vertrag `KichelApi` + Widget-Fetch (nur lesen) | [x] 2026-11-12 · siehe §16 | 🟢 |
| **SKU4b** | Seiten-JS: Submit → User-Msg + Fetch + Bot-Text (ohne Links-Extras) | [x] 2026-11-12 · §17 | 🟢 |
| **SKU4c** | Antwort-Extras: page/action-Links + Follow-up wie Widget | [x] 2026-11-12 · §18 | 🟢 |
| **SKU4d** | Smoke Dev + Kurznotiz | [x] 2026-11-12 · §19 | 🟢 |
| **SKU5** | UX-Feinschliff — Kind-IDs unten | SKU5a–d ✅ | 🟢 | [x] 2026-11-12 |
| **SKU5a** | Ist: Laden/Fehler-Seite + Widget-Kopf (nur lesen) | [x] 2026-11-12 · siehe §21 | 🟢 |
| **SKU5b** | Seite: Laden/Fehler klarer (Button-Sperre, Texte) | [x] 2026-11-12 · §22 | 🟢 |
| **SKU5c** | Widget-Link „Super-Kichel“ → `/app?page=superkihel` | [x] 2026-11-12 · §23 | 🟢 |
| **SKU5d** | Smoke Dev + Kurznotiz | [x] 2026-11-12 · §24 | 🟢 |
| **SKU6** | Docs/Haken + optional Guard/Scan-Notiz | [x] 2026-11-12 · §25 · UI-Plan fertig | 🟢 |
| **SKU7** | `kichel-protokoll` canAccess + Header-Link Admin | [x] 2026-11-12 · §26 | 🟢 |
| **SKU8** | Pretty-URL `/superkihel` | [x] 2026-11-12 · §27 | 🟢 |

**Reihenfolge:** SKU0 → **SKU1a→d** → SKU2 → **SKU3a→d** → **SKU4a→d** → **SKU5a→d** → SKU6.

**Chat-Prompt:**

```text
(UI-Plan SKU0–6 abgeschlossen — neue Mini-IDs nur bei Folgearbeit.)
```

---

## 2. UI-Skizze (SKU3/4)

```
.dg-wrap
  .dg-page-header
    h1.dg-page-title  Super-Kichel
    .dg-toolbar       (optional: „Hilfe-Protokoll“ nur Admin → kichel-protokoll)
  .dg-panel
    .dg-kichel-thread   (Verlauf — Feature-Klasse nur wenn nötig)
    .dg-form
      .dg-field  textarea Frage
      .dg-form-actions
        button.dg-button.dg-button--primary  Senden
```

Wiederverwenden wo sinnvoll: bestehende `assets/js/kichel.js` / API-Payload. Keine Parallelwelt-Buttons.

---

## 3. Abgrenzung

| Thema | In SKU*? | Später |
|-------|----------|--------|
| FAB-Widget | unverändert | — |
| Pretty-URL `/superkihel` | **SKU8 ✅** | — |
| SearXNG/OpenHands in UI | nein | nach Bedarf |
| Eigenes Protokoll | nein | Entscheidung offen |
| TLS Ollama-Proxy | nein | **SKC1 ✅** (GEX-Plan, `llm.ganz-soft.de`) |

---

## 4. Risiken

- Seite ohne Staff-Check = Leck → SKU1b/c hart absichern  
- Doppelte JS-Logik Widget vs. Seite → SKU4 gemeinsame API, wenig Copy-Paste  
- Spacing/nackte Links → Guard nach jeder View-Änderung  
- Nur `index.php`-Zweig ohne `MenuRegistry::canAccess` → Redirect vor Render (siehe §5)

---

## 5. SKU1a — Ist Routen/Zugriff (2026-11-12, nur lesen)

| Schritt | Wo | Verhalten |
|---------|-----|-----------|
| Login | `index.php` `case '/app'` | kein User → `/login` |
| Page-Slug | `$_GET['page']` · nur `[a-z0-9_-]` | Default `dashboard` |
| Zugang | `RoleResolver::canAccessArea` → meist `MenuRegistry::canAccess($user, $page)` | false → Redirect `homePath` |
| Render | großer `elseif ($page === '…')` in `index.php` | setzt `$contentTemplate`, `$title`, `$currentPage` |
| Layout | `views/layout/app.php` | inkl. Kichel-Widget-Partial |

**Referenz Kichel:**

| | |
|--|--|
| API | `POST /api/kichel` · `KichelApi` · nur `RoleResolver::isStaff` |
| Protokoll-View | `views/modules/kichel-protokoll.php` · Katalog `.dg-wrap` / `.dg-page-header` / `.dg-panel` |
| Protokoll-Route | `index.php`: `$page === 'kichel-protokoll' && RoleResolver::isAdmin` |
| **Lücke** | `kichel-protokoll` **fehlt** in `MenuRegistry::canAccess` → Gate redirected vermutlich vor dem Admin-Zweig (Widget-Link „Protokoll“) |

**SKU0–8 ✅** — inkl. Pretty-URL (2026-11-12).

---

## 6. SKU1b — Route + Staff (2026-11-12)

| Datei | Änderung |
|-------|----------|
| `src/Menu/MenuRegistry.php` | `canAccess('superkihel')` → `RoleResolver::isStaff` |
| `index.php` | Zweig `$page === 'superkihel' && isStaff` → Template/`title`/`currentPage` |
| `views/modules/superkihel.php` | Stub → ab SKU1c Katalog-Gerüst |

**Verhalten:** unauth → `/login` · Kunde/ohne Staff → Redirect `homePath` · Staff → Layout ohne Crash.

**Nicht in SKU1b:** Menü (SKU2) · Chat (SKU3+) · Protokoll-`canAccess`-Lücke unberührt.

---

## 7. SKU1c — Leere View (2026-11-12)

`views/modules/superkihel.php`: `.dg-wrap` · Flash · `.dg-page-header` / `.dg-page-title` „Super-Kichel“ · `.dg-lead` · `.dg-panel` Platzhaltertext.  
Guard: `php bin/ui-form-spacing-selftest.php` ✅ · kein Chat/Form/Menü.

---

## 8. SKU1d — Smoke Dev (2026-11-12)

**Ist:** Auf `ganz-om.de` (All-Inkl) liegen Route/View; unauth `GET /app?page=superkihel` → **302 `/login`**; `php -l` View+MenuRegistry OK.  
Staff-HTML nicht aus Agent-Browser (lokale `hosts`: `ganz-om.de` → GEX `2.29.242.28`, HTTPS refused) — manuell eingeloggt prüfen: Titel „Super-Kichel“ + Platzhalter-Panel.

---

## 9. SKU2 — Menü (2026-11-12)

| Datei | Änderung |
|-------|----------|
| `MenuRegistry::modules` | Staff: Eintrag „Super-Kichel“ → `/app?page=superkihel` (Gruppe Navigation, neben Support) |
| `moduleDescriptions` / `kichelAliases` | Kachel-Text + Suchaliase |

Dashboard-Kachel kommt über `dashboardTiles` ← `sidebarItems` automatisch. Keine neuen CSS-Klassen.

---

## 10. SKU3 — Zerlegung (2026-11-12, nur Plan)

| Kind | Scope | Nicht |
|------|--------|-------|
| **SKU3a** | Ist Widget: `views/partials/kichel-widget.php` · `assets/js/kichel.js` · `assets/css/kichel.css` · Klassen `dg-kichel-*` | kein Code |
| **SKU3b** | In `views/modules/superkihel.php`: Verlauf/Begrüßung (statisch); Feature-Klasse nur wenn Katalog-Kern nicht reicht | kein Fetch |
| **SKU3c** | `.dg-form` + Textarea + Senden in `.dg-form-actions`; Submit ohne API (no-op) | kein `POST /api/kichel` → **SKU4** |
| **SKU3d** | Guard + Kurznotiz | Menü-Link Widget→Seite → **SKU5** |

Optional Admin-Link „Hilfe-Protokoll“ im Header: erst wenn `kichel-protokoll` in `canAccess` (Lücke §5) — sonst **SKU5**/eigene Mini-ID.

---

## 11. SKU3a — Ist Widget (2026-11-12, nur lesen)

| Datei | Rolle |
|-------|--------|
| `views/partials/kichel-widget.php` | FAB + Panel; lädt `kichel.css` + `kichel.js`; `window.dgKichel` = `{ apiUrl, csrf, chatVersion }` |
| `assets/js/kichel.js` | Panel-Toggle, Chips, sessionStorage-Verlauf, `POST /api/kichel` (FormData `_csrf`+`query`), Rendern von Msg/Links/Follow-up |
| `assets/css/kichel.css` | Feature-Styles `dg-kichel-*` (nicht in `dg.css`) |
| `views/layout/app.php` | bindet Widget-Partial ein |
| `views/modules/superkihel.php` | nur Katalog-Gerüst, **kein** Chat-Markup |

**DOM / data-Attribute (Widget):** `data-kichel-fab|panel|close|body|messages|form|input|chip|protokoll|feedback`

**Msg-Klassen:** `.dg-kichel-msg` + `--bot` / `--user` · Container `.dg-kichel-messages` · Form `.dg-kichel-form` (eigene Buttons, **kein** `.dg-form`)

**Für SKU3b/c (Seite):**

1. Katalog-Kern auf der Seite: `.dg-panel` + `.dg-form` / `.dg-field` / `.dg-form-actions` / `.dg-button--primary`  
2. Msg-Bubble-Optik: bestehende `.dg-kichel-msg*` aus `kichel.css` wiederverwenden (kein Duplikat in `dg.css`)  
3. **Nicht** ganzes `kichel.js` auf die Seite laden (FAB/Panel-Pflicht) — API-Anbindung = **SKU4**  
4. Chips optional später; SKU3b nur Begrüßung + leerer/statischer Thread  
5. Admin-Protokoll-Link im Widget: nackter `<a>` — Seite später `.dg-button` (Katalog); `canAccess`-Lücke Protokoll unberührt

---

## 12. SKU3b — Verlauf statisch (2026-11-12)

`views/modules/superkihel.php`: in `.dg-panel` → `.dg-kichel-messages` + eine `.dg-kichel-msg--bot` Begrüßung. Styles über bestehendes `kichel.css` (Widget im Layout). Kein Form/JS/API. Guard ✅.

---

## 13. SKU3c — Form Mock (2026-11-12)

Unter dem Verlauf: `.dg-form` · `.dg-field` Textarea (`query`, max 500) · `.dg-form-actions` + `.dg-button--primary` „Senden“ · `onsubmit="return false;"` (kein API). Guard ✅.

---

## 14. SKU3d — Guard / Smoke (2026-11-12)

**Ist:** Guard `ui-form-spacing-selftest.php` ✅ · Dev-View enthält Verlauf+Form · unauth → 302 `/login`. Mobil/Staff-UI manuell (lokale hosts→GEX). SKU3 Mock komplett — API = **SKU4**.

---

## 15. SKU4 — Zerlegung (2026-11-12, nur Plan)

| Kind | Scope | Nicht |
|------|--------|-------|
| **SKU4a** | Ist: `KichelApi` Request/Response · Widget-`fetch` in `kichel.js` · CSRF | kein Code |
| **SKU4b** | Eigenes Seiten-JS (nicht ganzes Widget-JS): `onsubmit` weg → FormData `_csrf`+`query` → User-/Bot-Bubble Text | Laden/Fehler-Politur → **SKU5** |
| **SKU4c** | `page_links` / `action_links` / `follow_up` wie Widget-Render | Chips, sessionStorage-Panel |
| **SKU4d** | Smoke Dev (Staff-Frage) + Kurznotiz | Widget→Seite-Link → **SKU5** |

**Technik-Hinweis:** `window.dgKichel` kommt schon vom Widget-Partial im Layout — CSRF/apiUrl wiederverwendbar. Eigenes Script z. B. `assets/js/superkihel.js` an View hängen; FAB-Logik nicht mitladen.

---

## 16. SKU4a — Ist API (2026-11-12, nur lesen)

| | |
|--|--|
| Route | `index.php` `case '/api/kichel'` → `KichelApi::handle()` |
| Auth | Staff (`RoleResolver::isStaff`) · sonst 403 JSON |
| Method/CSRF | nur **POST** · `Csrf::verify($_POST['_csrf'])` · sonst 403 |
| Body | FormData/POST: `_csrf`, `query` (trim) |
| Fachlogik | `KichelAssistant::answer($user, $query)` · Protokoll `KichelProtocolRepository::record` |
| Erfolg | `{ success: true, data: <result> }` |
| Fehler | `{ success: false, message: "…" }` |

**`data` (üblich):** `answer` (string) · `follow_up` (string) · `action_links` (`[{label, href}]`) · optional `page_links` · oft `presentation` / `kind`

**Widget-Fetch (`kichel.js`):** `window.dgKichel.{apiUrl, csrf}` · FormData → `fetch` same-origin · bei Erfolg: Bot-`answer`, dann `page_links` / `action_links` / `follow_up` · Loading-Text „Einen Moment …“

**Für SKU4b:** View: `onsubmit` entfernen · CSRF aus `dgKichel` (oder Hidden-Feld) · neues `assets/js/superkihel.js` · nur User-Bubble + Bot-`data.answer` (Extras = SKU4c). Guard beachten: keine nackten Aktionslinks ohne `.dg-button`.

---

## 17. SKU4b — Fetch + Antworttext (2026-11-12)

| Datei | Änderung |
|-------|----------|
| `assets/js/superkihel.js` | neu: Submit → User-Msg · `POST /api/kichel` · Bot-`answer` (keine Links/Follow-up) |
| `views/modules/superkihel.php` | no-op-`onsubmit` weg · Script-Tag · Begrüßung angepasst |

CSRF/`apiUrl` aus `window.dgKichel` (Widget im Layout). Guard ✅.

---

## 18. SKU4c — Links / Follow-up (2026-11-12)

`assets/js/superkihel.js`: nach `answer` → `page_links` / `action_links` / `follow_up` (Klassen wie Widget `dg-kichel-*`). Feedback Ja/Nein lokal. Labels/Hrefs escaped. Guard ✅.

---

## 19. SKU4d — Smoke (2026-11-12)

**Ist:** Guard ✅ · Dev: `superkihel.js` + View-Script · unauth Seite → 302 `/login` · JS-Asset 200 · API ohne Session → 403. Staff-Frage im Browser manuell: `/app?page=superkihel`.

---

## 20. SKU5 — Zerlegung (2026-11-12, nur Plan)

| Kind | Scope | Nicht |
|------|--------|-------|
| **SKU5a** | Ist: `superkihel.js` Laden/Fehler · Widget-Kopf (Protokoll-Link) | kein Code |
| **SKU5b** | Klarere Lade-/Fehlertexte auf der Seite (ggf. `aria-busy`) | Widget-JS-Rewrite |
| **SKU5c** | Link im Widget zur Vollseite Super-Kichel (Staff) | Pretty-URL |
| **SKU5d** | Smoke + Kurznotiz | Docs-Abschluss → **SKU6** |

Optional später (nicht Pflicht SKU5): `kichel-protokoll` in `MenuRegistry::canAccess` + Header-Toolbar auf der Seite.

---

## 21. SKU5a — Ist UX (2026-11-12, nur lesen)

### Seite (`superkihel.js` / View)

| Thema | Ist |
|-------|-----|
| Laden | Bot-Bubble „Einen Moment …“ · Submit-Button `disabled` · Textarea bleibt editierbar |
| API-Fehler | `json.message` oder „Fehler bei der Anfrage.“ + Follow-up Retry |
| Netz | „Verbindungsfehler — bitte erneut versuchen.“ + Follow-up |
| Nicht-JSON | `res.json()` wirft → landet im `catch` (Verbindungstext, ungenau) |
| a11y | kein `aria-busy` / keine Lade-Kennzeichnung am Form |

### Widget-Kopf (`kichel-widget.php`)

| | |
|--|--|
| Admin | nackter Link „Protokoll“ → `kichel-protokoll` (canAccess-Lücke §5) |
| Staff | **kein** Link zur Vollseite Super-Kichel |
| CSS | `.dg-kichel-panel__admin a` (Feature, kein `.dg-button`) |

### Für SKU5b/c

1. **SKU5b:** Textarea+Form während Fetch sperren · `aria-busy` · ggf. Lade-/Fehlertexte schärfen · JSON-Parse getrennt behandeln  
2. **SKU5c:** im Widget-Kopf Link „Super-Kichel“ → `/app?page=superkihel` (Staff); Katalog: eher `.dg-kichel-action` oder klarer Textlink in bestehendem Admin-Slot — Guard: keine nackten Aktionslinks in Views-Scan beachten  

---

## 22. SKU5b — Laden/Fehler Seite (2026-11-12)

`superkihel.js`: `setBusy` sperrt Textarea+Senden · `aria-busy` am Form · Lade-Text „Antwort wird vorbereitet …“ · JSON-Parse getrennt · klarere Fehlertexte · Doppel-Submit geblockt. Guard ✅ · Dev aktualisiert.

---

## 23. SKU5c — Widget-Link (2026-11-12)

| Datei | Änderung |
|-------|----------|
| `kichel-widget.php` | Staff: Link „Super-Kichel“ (`.dg-kichel-action`) → `/app?page=superkihel` |
| `kichel.css` | Admin-Leiste Flex/Gap · kompakter Action-Link |
| `kichel.js` | Panel nach Navigation schließen (wie Protokoll) |

Guard ✅ · Dev aktualisiert.

---

## 24. SKU5d — Smoke (2026-11-12)

**Ist:** Guard ✅ · Dev: Widget-Link `data-kichel-super` · `setBusy` in `superkihel.js` · CSS Admin-Flex · Assets `kichel.css`/`kichel.js`/`superkihel.js` HTTP 200 · unauth Seite → 302 `/login`. Staff manuell: FAB → „Super-Kichel“ → Vollseite.

---

## 25. SKU6 — Docs-Abschluss (2026-11-12)

**UI-Plan SKU0–6 ✅.** Guard `ui-form-spacing-selftest.php` ✅ · `ui-crm-scan.php`: keine nackten Aktionslinks / kein Plan-Jargon in Views (unrelated Checkbox-Hinweise in anderen Modulen bleiben).

**Geliefert:** Staff-Seite `/app?page=superkihel` · Menü · API wie Widget · UX Busy/Fehler · Widget-Link zur Vollseite.

**Außerhalb SKU (Stand):** TLS Ollama-Proxy **SKC1 ✅** · Docs **SKD0 ✅** · CRM-Probe-HTTPS **PB2c ✅** (`crm-gex…`) · GEX-Cutover weiter **PB4**.

---

## 26. SKU7 — Protokoll canAccess + Header (2026-11-12)

| Datei | Änderung |
|-------|----------|
| `MenuRegistry::canAccess` | `kichel-protokoll` → `isAdmin` (schließt Lücke §5) |
| `index.php` | Zweig nutzt `MenuRegistry::canAccess` |
| `views/modules/superkihel.php` | Admin: Toolbar „Hilfe-Protokoll“ (`.dg-button`) |

Guard ✅ · Dev aktualisiert. Widget-Link „Protokoll“ funktioniert damit wieder über das Gate.

---

## 27. SKU8 — Pretty-URL `/superkihel` (2026-11-12)

**Konflikt:** Auf Dev existierte private CMS-Seite `dg_website_pages` id=15 Slug `superkihel` („Super Kihel“) → bei Login hätte die Website den CRM-Pfad geschluckt. Slug auf Dev umbenannt zu `superkihel-cms-alt` (privat).

| Datei | Änderung |
|-------|----------|
| `index.php` | `/superkihel` → Staff-Check → intern `$path=/app` + `page=superkihel` · unauth → `/login` |
| `MenuRegistry` | Menü-`href` `/superkihel` |
| `kichel-widget.php` | Link → `/superkihel` |

`/app?page=superkihel` bleibt gültig. Guard ✅ · unauth Smoke: 302 `/login`.

---

## Änderungshistorie

| Datum | Inhalt |
|-------|--------|
| 2026-11-12 | **SKU0** Erstfassung Mini-IDs SKU1–6 |
| 2026-11-12 | **SKU1** in **SKU1a–d** zerlegt (Ist / Route / View / Smoke) |
| 2026-11-12 | **SKU1a ✅** Routen/Auth-Ist · Hinweis canAccess-Lücke Protokoll |
| 2026-11-12 | **SKU1b ✅** Route+Staff `superkihel` · Stub-View |
| 2026-11-12 | **SKU1c ✅** Katalog-Gerüst · Guard grün |
| 2026-11-12 | **SKU1d ✅** Dev-Smoke unauth 302 · Dateien auf Dev |
| 2026-11-12 | **SKU2 ✅** Menü Navigation/Staff · Kachel/Aliase |
| 2026-11-12 | **SKU3** in **SKU3a–d** zerlegt (Ist / Verlauf / Form / Guard) |
| 2026-11-12 | **SKU3a ✅** Widget-Ist · Empfehlung Katalog+Msg-Klassen |
| 2026-11-12 | **SKU3b ✅** Statischer Verlauf + Begrüßung |
| 2026-11-12 | **SKU3c ✅** Form Textarea+Senden (no-op) |
| 2026-11-12 | **SKU3d ✅** Guard+Dev-Smoke · SKU3 fertig |
| 2026-11-12 | **SKU4** in **SKU4a–d** zerlegt (Ist / Fetch-Text / Extras / Smoke) |
| 2026-11-12 | **SKU4a ✅** API-Vertrag + Widget-Fetch dokumentiert |
| 2026-11-12 | **SKU4b ✅** Seiten-Fetch + Antworttext |
| 2026-11-12 | **SKU4c ✅** page/action-Links + Follow-up |
| 2026-11-12 | **SKU4d ✅** Smoke · SKU4 fertig |
| 2026-11-12 | **SKU5** in **SKU5a–d** zerlegt (Ist / Laden / Widget-Link / Smoke) |
| 2026-11-12 | **SKU5a ✅** UX-Ist Seite+Widget-Kopf |
| 2026-11-12 | **SKU5b ✅** Busy/Fehlertexte Seite |
| 2026-11-12 | **SKU5c ✅** Widget-Link zur Vollseite |
| 2026-11-12 | **SKU5d ✅** Smoke · SKU5 fertig |
| 2026-11-12 | **SKU6 ✅** Docs/Guard-Notiz · UI-Plan fertig |
| 2026-11-12 | **SKU7 ✅** Protokoll canAccess + Header-Link |
| 2026-11-12 | **SKU8 ✅** Pretty-URL `/superkihel` · CMS-Slug-Konflikt Dev |
