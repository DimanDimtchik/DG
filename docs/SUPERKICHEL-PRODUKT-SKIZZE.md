# Super-Kichel — Produkt-Skizze (SKC0a)

> **Nur Scope / Abgrenzung.** Kein UI-Code, keine Server-Install in dieser Mini-ID.  
> Plan: [`SUPERKICHEL-GEX-PLAN.md`](SUPERKICHEL-GEX-PLAN.md) · Technik PHP/Ollama: [`KICHEL-PHASE2.md`](KICHEL-PHASE2.md)

Stand: **2026-11-11** · Dev-Ziel: **`ganz-om.de`** (eingeloggt) · Host heute: **All-Inkl** (DNS; Cutover PB4 offen)

---

## 1. Ist: „Kichel“ (Widget)

| Punkt | Ist |
|-------|-----|
| **UI** | FAB + Panel auf CRM-Seiten (`views/partials/kichel-widget.php`) |
| **API** | `POST /api/kichel` — nur Staff (`KichelApi`) |
| **Logik** | Regeln/Nav/Felder/Skonto/… in PHP (`KichelAssistant`); Ollama optional nur Formulierung |
| **Admin** | `/app?page=kichel-protokoll` |
| **Zweck** | Kurze Hilfe „Wo finde ich X?“ / Stichwort-Chips, ohne den Arbeitskontext zu verlassen |

---

## 2. Soll: „Super-Kichel“

| Punkt | Soll (Skizze) |
|-------|----------------|
| **UI** | Eigene **Vollseite** im eingeloggten CRM — Nutzerpfad heute: `/app?page=superkihel` (Pretty-URL `/superkihel` optional später) |
| **Sichtbarkeit** | **Nur eingeloggte** Mitarbeiter/Admins (wie Kichel-API) — öffentlich nie |
| **Zweck** | Längere Sitzungen: mehr Kontext, Verlauf auf der Seite, ggf. erweiterte Werkzeuge — **nicht** Ersatz für das FAB-Widget |
| **Fachregel** | Unverändert Phase 2: **Beträge/Steuer/Buchung = PHP**; LLM (Ollama auf GEX) nur Text/Formulierung bzw. freigegebene Hilfen |
| **Instanz** | Zuerst **Dev `ganz-om.de`**; kein Tenant-Hardcode; keine Produktiv-Instanz ohne Freigabe |
| **Backend-KI** | GEX Ollama · Anbindung Dev **SKC0 ✅** (`kichel.local.php` + nginx path-secret Proxy) |

**Arbeitsname in der URL:** `superkihel` (Ist auf Dev). Produktname in UI-Texten: **Super-Kichel** (lesbar). Keine Plan-Codes (`SKC0a` …) in Endnutzer-Texten.

---

## 3. Abgrenzung Widget vs. Super-Kichel

| | **Kichel (Widget)** | **Super-Kichel (Seite)** |
|--|---------------------|---------------------------|
| Ort | Overlay überall | Eigene Seite |
| Session-Länge | Kurzfrage | Längeres Arbeiten |
| Einstieg | FAB / Chips | Menü oder Deep-Link `/app?page=superkihel` |
| Pflicht | Bleibt Standard-Hilfe | Zusatz / Intensiv-Modus |
| Protokoll | weiter `dg_kichel_log` (ob gemeinsames oder getrenntes Log = spätere ID) | |
| OpenHands / SearXNG / Forgejo | nein | nur nach **SKB0**-Freigabe, nicht Teil der CRM-Seite selbst |

**Nicht in Super-Kichel (bewusst):**

- öffentliche Website-Seite ohne Login  
- LLM, das Buchungen, USt oder Löhne „ausrechnet“  
- Kunden-/Instanznamen als Produkt-Default  
- Cutover DNS auf GEX (bleibt **PB4**)

---

## 4. Abhängigkeiten (Reihenfolge)

1. **SKC0a** (diese Skizze) ✅  
2. **SKB0/SKB1/SKB2** ✅ · **SKC0** CRM↔Ollama Dev ✅  
3. UI-Ausbau der Seite `superkihel` = **eigene Mini-IDs** (nicht SKC0) — Katalog/`dg.css`

---

## 5. Offene Punkte (spätere Freigabe)

| Thema | Status |
|-------|--------|
| Pretty-URL `/superkihel` vs. nur `page=superkihel` | offen |
| Eigenes Menü-Item „Super-Kichel“ | offen |
| Gemeinsames vs. separates Protokoll | offen |
| SearXNG o. Ä. als Werkzeug hinter der Seite | erst nach SKB0 |

---

## Änderungshistorie

| Datum | Inhalt |
|-------|--------|
| 2026-11-11 | **SKC0a** Erstfassung Abgrenzung Widget vs. Super-Kichel |
