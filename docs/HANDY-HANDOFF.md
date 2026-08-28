# Handy-Handoff — DG CRM (2026-08-21)

Weiterarbeit vom Handy / Cursor Browser.

**Regeln:** [`AGENTS.md`](../AGENTS.md) · **ToDos:** [`docs/TODOS.md`](TODOS.md)

- Repo: https://github.com/DimanDimtchik/DG
- Branch: **`master`** (aktuell, gepusht)
- Cloud-Zugänge: [`docs/CLOUD-AGENT-ACCESS.md`](CLOUD-AGENT-ACCESS.md)

## Cursor Browser SSH

Kein Passwort — Key `id_ed25519_ganzom`. Secrets unter  
https://cursor.com/dashboard/cloud-agents  

Dann im Agent: `bash bin/cloud-agent-ssh-setup.sh`

GitHub Secrets sind als Backup gesetzt (gleiche Namen), greifen aber **nicht** automatisch in Cursor Cloud.

## Live-Status

| Instanz | Wartung | Bemerkung |
|---------|---------|-----------|
| ganz-soft.de | ON | öffentliche 503 |
| dg.ganz-om.de | ON | Login ok |
| kontur-cosmetics.de | ON | Adresse ggf. in CRM prüfen |
| shop.ganz-soft.de | — | Stripe noch nicht live |

## Offene Themen

1. **PHP 8.5:** Alle Domains im KAS auf 8.5 → **dann** testen. Details: [`docs/PHP85-TEST-HANDOFF.md`](PHP85-TEST-HANDOFF.md)
2. **Nextcloud cloud.ganz-om.de:** Wiederherstellung — [`docs/CLOUD-NEXTCLOUD-RESTORE.md`](CLOUD-NEXTCLOUD-RESTORE.md)
3. Stripe Keys + Webhook (bewusst später)
4. AGB/Widerruf juristisch
5. DATEV EXTF / ShiftBase API
6. Kontur-Adresse nach Restore prüfen
7. ganz-om.de → CRM (WP entfernen)

## Deploy (nach SSH-Setup)

```bash
bash bin/sync-crm-from-master.sh
```

Nie Account-Root mit `--delete` syncen.
