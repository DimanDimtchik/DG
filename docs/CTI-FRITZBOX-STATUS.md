# CTI / Fritzbox — Statusnotiz

> Stand: **2026-11-08** · Mini-ID **PA6a** · Fach: [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) A6  
> Tokenarm: [`PHASE-A-REST-PLAN.md`](PHASE-A-REST-PLAN.md)

## Kurz

| Ebene | Stand |
|-------|--------|
| **CRM-Code** | ✅ vorhanden (Migration `113_call_log.sql`, `src/Cti/*`, UI Telefon, Popup, Bridge-CLI) |
| **`office.iqstrom.com`** | Schema ✅ (`dg_call_log`) · Setting `cti` **nicht** gesetzt → Defaults: **enabled=false**, Provider Fritz, Dial `sip:`, Host `fritz.box`, kein Bridge-Token |
| **Fritzbox / Linphone / Bridge** | ❌ nicht aus dem Cloud-Agent aktivierbar — braucht LAN (oder VPN) + manuelle Schritte vor Ort |

Keine Tenant-Hardcodes im Produkt. CTI-Daten nur Instanz-Settings / lokale Bridge.

## Was der Code kann

- Einstellungen → **Telefon**: CTI ein/aus, Anbieter Fritzbox, Wahl-Schema `sip:` (Linphone) oder `tel:`, Fritz-Host, Bridge-Token rotieren  
- Click-to-Call über Softphone-URI  
- Eingehende Events: lokale Bridge `bin/fritz-callmonitor-bridge.php` liest CallMonitor TCP **1012** und POSTet an `/api/cti`  
- Popup / Anrufliste über `dg_call_log`

## Manuelle Aktivierung (Org / vor Ort)

1. In der Fritzbox **CallMonitor** freigeben (TCP 1012 im LAN).  
2. Softphone (z. B. **Linphone**) als IP-Telefon an der Fritzbox einrichten.  
3. Im CRM unter Telefon: CTI **aktivieren**, Token erzeugen/speichern, Host falls nicht `fritz.box` setzen.  
4. Auf einem PC **im Fritz-LAN** (oder VPN) Bridge starten, z. B.:

```bash
php bin/fritz-callmonitor-bridge.php \
  --crm=https://office.iqstrom.com \
  --token=TOKEN_AUS_CRM \
  --host=fritz.box
```

5. Probe: ausgehend Click-to-Call · eingehend RING → Popup/Log.

## Nachweis PA6a (Cloud-Agent)

- Migration `113_call_log.sql` auf der IQ-Instanz vorhanden  
- `CtiSettings::isEnabled()` = **no** (Defaults)  
- `dg_call_log` leer · kein `cti`-Key in `dg_settings`  
- Bridge/Linphone: kein LAN-Zugang vom Cloud-Agent → bewusst **nicht** remote „aktiviert“

## Nächster Schritt

Org: Schritte 1–5 auf dem IQ-Standort (oder VPN). Optional später Dev/andere Instanzen analog, jeweils eigene Settings/Token.
