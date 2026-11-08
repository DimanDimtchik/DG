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
3. Optional (später): `down[i]`, `raw_material_ok` (FL5/FL6)

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

Hinweis FL5a: fehlende Rohware am Eingang setzt Linienstatus `stopped_material` und kann Station 1 wie starved behandeln (Detail in FL5).

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

## Engpass (FL4a)

Zusätzlich zu Blocking/Starving liefert `ProductionLineStatusService` optional `bottleneck`:

- Quelle: aktives Kapazitätsprofil je Work Center (`output_qty_per_hour`)  
- Engpass = Station mit **geringstem positivem** Output/h  
- `hint`: kurzer Endnutzer-Text („Engpass: … begrenzt die Linie …“) — **keine** Plan-Codes  

---

## Was FL3a bewusst nicht festlegt

- Echtzeit-PLC / Sensorik  
- Automatisches Ändern von `current_qty` (Simulationstakte = später optional)  
- Linien-Durchsatz unter Puffer-Annahmen (FL4b)  
- Lagerbuchung von WIP  

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`FLIESSLINIE-REST-PLAN.md`](FLIESSLINIE-REST-PLAN.md) | Mini-IDs |
| `ProductionLineStore` | Stammdaten + Puffer-Ist |
| FL3b | `ProductionLineStatusService` implementiert diese Regeln |
| FL3c | Selftest blocked/starved |
