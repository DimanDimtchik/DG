# Super-Kichel — UI-Plan (tokenarm)

> **Eine Chat-Session = eine Mini-ID.** Freigabe vor Umsetzung.  
> Scope: [`SUPERKICHEL-PRODUKT-SKIZZE.md`](SUPERKICHEL-PRODUKT-SKIZZE.md) (SKC0a) · Server/Ollama: [`SUPERKICHEL-GEX-PLAN.md`](SUPERKICHEL-GEX-PLAN.md) · UI: [`UI-KLASSENKATALOG.md`](UI-KLASSENKATALOG.md) · Form: [`UI-FORM-REGELN.md`](UI-FORM-REGELN.md)  
> Guard: `php bin/ui-form-spacing-selftest.php`

Stand: **2026-11-12** · Dev: **`ganz-om.de`** · Seite: `/app?page=superkihel` · Label UI: **Super-Kichel** (keine Plan-Codes in Endnutzer-Texten)

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
| **SKU2** | Menü-Eintrag „Super-Kichel“ (sinnvolle Gruppe) | Link sichtbar für Staff · Katalog-Klassen | 🟡 | [ ] |
| **SKU3** | Chat-Layout: Verlauf + Eingabe + Senden (nur UI, Mock/leer ok) | Katalog · Guard grün · mobil lesbar | 🟡 | [ ] |
| **SKU4** | Anbindung `POST /api/kichel` · Antwort in Verlauf | Smoke: Frage → Antwort wie Widget | 🔴🟡 | [ ] |
| **SKU5** | UX-Feinschliff: Laden/Fehler · Link aus Widget-Panel zur Seite | Klartext · kein Jargon | 🟡 | [ ] |
| **SKU6** | Docs/Haken + optional Guard/Scan-Notiz | Plan abgehakt · `TODOS` | 🟢 | [ ] |

**Reihenfolge:** SKU0 → **SKU1a→d** → SKU2 → SKU3 → SKU4 → SKU5 → SKU6.

**Chat-Prompt:**

```text
Nur Mini-ID SKU1a laut docs/SUPERKICHEL-UI-PLAN.md.
Untersuche zuerst den Ist-Zustand. Ändere noch nichts.
Nenne betroffene Dateien/Pfade, geplante Änderungen, Risiken und Prüfungen.
Warte auf ausdrückliche Freigabe.
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
| Pretty-URL `/superkihel` | nein | eigene ID |
| SearXNG/OpenHands in UI | nein | nach Bedarf |
| Eigenes Protokoll | nein | Entscheidung offen |
| TLS Ollama-Proxy | nein | **PB2c4** |

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

**SKU1 ✅** (§5–8) · **Nächste ID:** **SKU2** (Menü, Freigabe).

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

**Nächste ID:** **SKU2**.

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
