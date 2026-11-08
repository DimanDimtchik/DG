# Fließlinie — FL8 Verfeinerungen (Backlog)

Stand: **2026-11-08** · Parent: [`FLIESSLINIE-REST-PLAN.md`](FLIESSLINIE-REST-PLAN.md)  
Status: **Dokumentiert, nicht implementiert** — bewusst nach FL7. Kein MES/PLC, keine stillen Lagerbuchungen.

Kern heute: lineare Stationen + Zwischenpuffer + Offline-Status ([`FLIESSLINIE-REGELN.md`](FLIESSLINIE-REGELN.md)).  
FL8 sammelt fachliche Erweiterungen, die **eigene Mini-IDs** brauchen — nicht in FL1–FL7 nachrüsten.

---

## FL8a — Ausschuss / Nacharbeit-Schleife

**Problem:** Ausschuss und Nacharbeit sind keine „fehlenden Rohstoffe“, sondern Mengen, die die Linie verlassen bzw. zurückkommen.

| Begriff | Bedeutung |
|---------|-----------|
| Ausschuss (Scrap) | Menge verlässt die Gutmenge; darf Engpass-/Durchsatz-Annahmen verzerren |
| Nacharbeit | Rückfluss auf eine frühere Station oder einen Nebenweg |

**Regel-Idee (später):**

- Pro Station optional `scrap_rate` (Anteil) oder Ereignis `scrap_qty` — nur Simulation/Notiz, **keine** FiBu.
- Nacharbeit = eigener Stations- oder Puffer-Zweig, **nicht** still `current_qty` erhöhen ohne Protokoll.
- Status: Ausschuss allein macht Station nicht `down`; hohe Ausschussquote → Hinweis „Qualität“, nicht „Puffer voll“.

**Nicht mischen mit:** FL5 Material-Gate, FL6 Wartung/`down`.

**Nächster Code-Schnitt (wenn gebraucht):** Doc → optionale Felder/Events → Selftest „Gutmenge vs. Ausschuss“.

---

## FL8b — Push vs. Pull (Kanban)

**Heute (implizit Push):** Station *i* produziert, bis Ausgangspuffer voll → `blocked`. Nachfolger wartet bei leerem Eingang → `starved`.

**Pull/Kanban-Idee:** Station *i* darf nur produzieren, wenn im Ausgangspuffer **Platz** ist *und* (streng) der Nachfolger „Nachfrage“ signalisiert (freier Platz unter Zielbestand).

| Modus | Wann Station läuft |
|-------|-------------------|
| Push (Ist) | solange nicht blocked/starved/down/Material |
| Pull | zusätzlich: Ausgangspuffer unter Ziel (`current < capacity − kanban_size` o. Ä.) |

**UI-Texte später (kein Jargon):** z. B. „wartet (kein Abruf)“ statt „Kanban“.

**Technik-Skizze:** Flag `flow_mode = push|pull` an der Linie; Status-Service liest es in `evaluate`-Optionen. Default bleibt **push**, damit bestehende Smoke/Tests stabil bleiben.

---

## FL8c — Parallele Stationen / Umgehung

**Heute:** streng linear `station_order` 1…n, eine Maschine je Slot.

| Erweiterung | Nutzen | Aufwand |
|-------------|--------|---------|
| Parallel (gleiche Stufe) | zwei Maschinen teilen Last | Modell: Stufe statt nur Order; Engpass = Summe/Min je Stufe |
| Umgehung (Bypass) | Station überspringen bei Störung | Kantengraph, nicht nur Kette |

**Empfehlung:** erst brauchen, dann modellieren. Bis dahin: zweite Linie anlegen statt Graph im CRM.

---

## FL8d — WIP ↔ Lagerplatz (GoBD)

**Ist:** `current_qty` = betrieblicher Zwischenbestand **nur** Simulation/manuell — **keine** Lager- oder FiBu-Buchung ([Plan § Recht](FLIESSLINIE-REST-PLAN.md)).

| Darf | Darf nicht |
|------|------------|
| Lesen aus Lager (wie Material-Gate, `booked = false`) | Still `StockMovement` / EXT F aus Pufferständen |
| Später: explizites Gate „WIP auf Lagerplatz buchen“ mit Beleg + User | Puffer-Update = Lagerbuchung in einem Schritt ohne Audit |

**Prüfer-Sicht:** Wer hat `current_qty` geändert? Wann? Annahme Simulation vs. Inventur.  
**Steuerberater:** bewertungsrelevanter WIP → Inventur/Bestandskonten, nicht Fließlinien-UI.

Eigenes Gate analog `ProductionLineMaterialStockGate` — Name-Skizze `ProductionLineWipStockGate` — erst wenn Produkt das verlangt.

---

## Abgrenzung

| Thema | Ort |
|-------|-----|
| Blocking/Starving/Material/`down` | FL3–FL6 + Regeln-Doc |
| UI Status-Texte | FL7 / FL9a |
| Scrap, Pull, Parallel, WIP-Lager | **dieses Doc (FL8)** |

---

## Checkliste bei Umsetzung

1. Eigene Mini-ID (nicht „kurz in StatusService“)  
2. Default-Verhalten unverändert (Push, keine Lagerbuchung)  
3. Selftest + kurze Endnutzer-Texte  
4. Kein Tenant-/Firmen-Hardcode  
