# Szenario: Bankabgleich — Überblick

> **Regeln:** [`VIDEO-REGELN.md`](VIDEO-REGELN.md) · **Text:** [`locales/de/bankabgleich-ueberblick.json`](locales/de/bankabgleich-ueberblick.json)  
> **Demo-Auszüge:** [`docs/demo/bankabgleich/`](../demo/bankabgleich/)

**Zielgruppe:** Buchhaltung  
**Dauer:** ca. 2,5–3 Minuten  
**Stil:** Dashboard → Import CAMT/MT940 → was das CRM daraus macht

## Demo-Daten (bleiben in der Instanz)

| Datei | Zeitraum |
|-------|----------|
| q1-2026 | 1. Quartal 2026 |
| q2a-2026 | 2. Quartal 2026, Apr–Mai |
| q2b-2026 | 2. Quartal 2026, Juni |

Jeweils CAMT.053 und MT940. Import in die DB über CAMT (Umsätze bleiben).

## Ablauf

1. Dashboard → Kachel Bankabgleich  
2. Zweck: Kontoauszug einlesen, Belege zuordnen  
3. CAMT.053 importieren  
4. MT940 als Alternative  
5. Offene Umsätze (Zuordnen / Ignorieren)  
6. Geisterumsätze (nie automatisch ausblenden)  
7. Zugeordnet  
8. Abschluss
