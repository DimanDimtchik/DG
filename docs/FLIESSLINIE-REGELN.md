# Fließlinie — Zustandsregeln (Blocking / Starving / Stopp)

Stand: **2026-11-08** · Mini-ID **FL3a** · Parent: [`FLIESSLINIE-REST-PLAN.md`](FLIESSLINIE-REST-PLAN.md)  
Geltung: **Offline-Simulation / Planungsstatus** im CRM — **kein** SPS-/Maschinenstopp vor Ort.

---

## Begriffe

| Code (intern) | Bedeutung | Endnutzer-Idee (FL7, kurze Texte) |
|---------------|-----------|-----------------------------------|
| `running` | Station darf theoretisch laufen | läuft / bereit |
| `blocked` | Ausgangspuffer **voll** | steht (Puffer voll) |
| `starved` | Eingangspuffer **leer** (≤ min) | steht (kein Material vom Vorgänger) |
| `down` | Störung / Wartung (FL6) | außer Betrieb |
| `stopped_material` | Rohware/Eingang fehlt (FL5) — oft Linien-Status | Material fehlt |

Puffer **nach** Station *i* = Lücke zwischen Station *i* und *i+1* (`after_station_order = i`).  
Letzte Station hat **keinen** Ausgangspuffer.

---

## Eingaben für die Bewertung (FL3b)

Pro Linie:

1. Stationen geordnet: `station_order` 1…n  
2. Puffer je Lücke 1…n−1: `capacity_qty`, `min_qty`, `current_qty`  
3. Optional: `down[i]` (FL6a Gate), `raw_material_ok` / Hilfsstoff (FL5)

---

## Stationsregeln (Kern — FL3)

Für jede Station *i* (1-basiert):

```
function stationStatus(i):
    if down[i] == true:                    # FL6; sonst false
        return "down"

    # Blocking: Ausgangspuffer voll (nicht für letzte Station)
    if i < n:
        bufOut = buffer_after(i)
        if bufOut.current_qty >= bufOut.capacity_qty:
            return "blocked"

    # Starving: Eingangspuffer leer (nicht für erste Station)
    if i > 1:
        bufIn = buffer_after(i - 1)
        if bufIn.current_qty <= bufIn.min_qty:
            return "starved"

    return "running"
```

**Priorität**, wenn mehrere Bedingungen greifen würden:  
`down` > `blocked` > `starved` > `running`  
(Auswertung in dieser Reihenfolge; erste zutreffende gewinnt.)

### Grenzfälle

| Fall | Ergebnis |
|------|----------|
| `current_qty == capacity_qty` | **blocked** (Vorgänger) |
| `current_qty == min_qty` | **starved** (Nachfolger) |
| `min_qty == 0` und `current_qty == 0` | Nachfolger **starved** |
| Eine Station | keine Puffer → nie blocked/starved über Puffer; nur `down` / Material (FL5) |

---

## Linienstatus

```
function lineStatus():
    if raw_material_ok == false:           # FL5; Default true bis FL5a
        return "stopped_material"

    statuses = [ stationStatus(i) for i in 1..n ]

    if all(s in {"blocked","starved","down"} for s in statuses):
        return "stopped"                   # keine Station lauffähig

    if any(s == "down" for s in statuses):
        return "degraded"                  # teilweise Störung (UI: „teilweise außer Betrieb“)

    if any(s in {"blocked","starved"} for s in statuses):
        return "constrained"               # läuft ungleichmäßig / gestaut

    return "running"
```

### FL5a — Rohware am Eingang (verbindlich)

| Bedingung | Wirkung |
|-----------|---------|
| `raw_material_ok = false` | `line_status = stopped_material` |
| zugleich Station 1 (sofern nicht `down`/`blocked`) | Station-Status `starved`, Reason `raw_material_missing` |
| `raw_material` im Evaluate-Ergebnis | `{ ok, hint }` — Hint ohne Plan-Codes |

Lager-Anbindung = **FL5c** `ProductionLineMaterialStockGate` (nur lesen, `booked=false`).  
Aufrufer kann weiterhin `raw_material_ok` manuell setzen (überschreibt Gate-Merge nicht, wenn schon gesetzt).

### FL5b — Hilfsstoff ab Station *k*

| Option | Wirkung |
|--------|---------|
| `missing_aux_from_station_order = k` (*k* ≥ 1) | Alle Stationen mit `order ≥ k` (soweit nicht `down`/`blocked`/bereits starved) → `starved`, Reason `aux_material_missing` |
| `auxiliary_material` im Ergebnis | `{ ok, from_station_order, hint }` |

Linienstatus folgt den Stationszuständen (`constrained` wenn davor noch etwas läuft, sonst `stopped`). Rohware-Flag (`stopped_material`) hat Vorrang auf Linien-Ebene.

### FL5c — Lagerbestand nur lesen

| Regel | Bedeutung |
|-------|-----------|
| Quelle | `StockAvailabilityService::snapshot` → `available` (wenn `track_stock`) |
| Fixture | Resolver `article_id → qty` für Selftests ohne DB |
| Rohware | `required > available` → `raw_material_ok=false` |
| Hilfsstoff | früheste fehlende `station_order` → `missing_aux_from_station_order` |
| Nicht trackbar / unbekannt | **nicht** blockieren (`null`) |
| Buchen | **verboten** in diesem Gate (`booked=false` immer) |

---

## Pseudocode Engine-Vertrag (für FL3b)

```
Input:  lineId
Output: {
  line_id,
  line_status,                 # running | constrained | stopped | stopped_material | degraded
  stations: [
    { station_id, station_order, work_center_id, status, reasons[] }
  ],
  buffers: [
    { after_station_order, current_qty, capacity_qty, min_qty,
      blocks_predecessor: current >= capacity,
      starves_successor: current <= min }
  ]
}
```

`reasons[]` intern/technisch (z. B. `buffer_full`, `buffer_empty`); UI-Labels erst FL7 — **keine** Plan-Codes (`FL3`) in der Oberfläche.

---

## Engpass (FL4a) & Linien-Durchsatz (FL4b)

`ProductionLineStatusService` liefert optional:

| Feld | Regel |
|------|--------|
| `bottleneck` | Station mit **geringstem positivem** `output_qty_per_hour` + `hint` |
| `line_throughput` | grob **= Engpass-Rate** (min der Stations-Outputs) |

Puffer-Annahmen für `line_throughput`:

- Linie `running` → Rate Engpass, Annahme ausreichende Puffer  
- `constrained` / `degraded` / Blocking/Starving-Flags → gleiche Rate, Annahme „nach Erholung der Puffer“  
- `stopped` / `stopped_material` → **0**  

Keine Plan-Codes in `hint`-Texten.

---

### FL6a — Station `down`

| Quelle | Wirkung |
|--------|---------|
| Manuelles Stör-Flag (`manual_fault_station_orders`) | betroffene `station_order` → `down` |
| Offene Belegung `maintenance` / `blocked` (Status nicht cancelled/done, Zeitfenster enthält *jetzt*) | zugehöriges Work Center → Station `down` |
| Gate | `ProductionLineStationDownGate` → `down_station_orders` für Evaluate |

### FL6b — Propagation

Bei Station *i* = `down` (ohne Mengen zu schreiben):

| Nachbar | Wirkung |
|---------|---------|
| Station *i−1* (wenn `running`) | `block_risk` + Reason `downstream_down_block_risk` |
| Puffer nach *i−1* | Flag `fill_risk_due_to_downstream_down` |
| Station *i+1* (wenn `running`/`blocked`) | → `starved`, Reason `upstream_down` |
| Puffer nach *i* | Flag `starve_risk_due_to_upstream_down` |

Ergebnis-Feld `propagation` mit `events` + `hint`.

---

## Was FL3a bewusst nicht festlegt

- Echtzeit-PLC / Sensorik  
- Automatisches Ändern von `current_qty` (Simulationstakte = später optional)  
- Lagerbuchung von WIP  

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`FLIESSLINIE-REST-PLAN.md`](FLIESSLINIE-REST-PLAN.md) | Mini-IDs |
| `ProductionLineStore` | Stammdaten + Puffer-Ist |
| FL3b | `ProductionLineStatusService` implementiert diese Regeln |
| FL3c | Selftest blocked/starved |
