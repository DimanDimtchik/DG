# PHP 8.5 — Test-Handoff

Stand: **2026-08-27** · Für die nächste Session (nach Feierabend-Umstellung im KAS)

---

## Ausgangslage

| Domain / Instanz | Pfad | PHP (27.08.) | Ziel |
|------------------|------|--------------|------|
| **kontur-cosmetics.de** | `/www/htdocs/w0217246/kontur-cosmetics.de` | **8.5** (bereits) | 8.5 |
| **ganz-soft.de** (Live) | `/www/htdocs/w0217246/ganz-soft.de` | 8.4 → Nutzer stellt auf **8.5** um | 8.5 |
| **dg.ganz-om.de** (Master) | `/www/htdocs/w0217246/dg.ganz-om.de` | 8.4 → Nutzer stellt auf **8.5** um | 8.5 |
| **shop.ganz-soft.de** | `/www/htdocs/w0217246/shop.ganz-soft.de` | 8.4 → Nutzer stellt auf **8.5** um | 8.5 |
| **ganz-om.de** | `/www/htdocs/w0217246/ganz-om.de` | 8.5 | 8.5 (Platzhalter, kein WordPress-Core) |

**Hinweis ganz-om.de (28.08.):** Ordner hatte nur `wp-content/`, kein `index.php` → Apache 403. Platzhalter `sites/ganz-om.de/index.php` auf Server deployen. WordPress-Core fehlt — Marketing-Site später separat wiederherstellen.

**Wichtig:** KAS-Umstellung erfolgt **pro Domain** (Domain bearbeiten → PHP-Version).

---

## Reihenfolge — unbedingt einhalten

1. **Zuerst:** Alle oben genannten Domains im KAS auf **PHP 8.5** stellen und speichern.
2. **Erst dann:** Testen (nicht umgekehrt — sonst 8.4- vs. 8.5-Ergebnisse vermischen).

Prüfen auf dem Server (SSH):

```bash
ssh allinkl-ganzom
php85 -v
# Pro Instanz per curl oder KAS prüfen, ob die Domain wirklich 8.5 nutzt
```

---

## Kontext vom 27.08. (erledigt vor Feierabend)

- KAS: **ganz-soft.de** zeigt auf Webspace **`/ganz-soft.de/`** (nicht mehr Account-Root).
- Deploy: Master → `ganz-soft.de/` + `kontur-cosmetics.de` via `bin/sync-crm-from-master.sh`.
- Lizenz **ganz-soft.de:** `config/license.php` + `storage/license_state.json` aus Account-Root übernommen (alter Subfolder-Key `GS-AC90…` war ungültig).
- Shop-Wartung: `/admin/wartung` — Admin-Passwort auf Server in `config/admin.local.php`.

Randfälle-Fixes sind auf **`master`** gemergt (Stand 2026-09-03).

---

## Bekannte PHP-8.5-Hinweise im Code (Deprecation, kein Hard-Break)

Vor produktivem Dauerbetrieb optional bereinigen:

| Thema | Dateien |
|-------|---------|
| `$http_response_header` deprecated | `src/Kdv/KdvLicenseClient.php`, `shop/src/ShopDomainCheck.php`, `shop/src/ShopAccountApi.php` → `http_get_last_response_headers()` |
| `curl_close()` / `imagedestroy()` deprecated | `src/Accounting/ElsterEricClient.php`, `shop/src/ShopStripe.php`, `src/Media/MediaImageProcessor.php`, `src/Media/MediaFaviconGenerator.php` |

Syntax-Check aller PHP-Dateien mit `php85 -l` auf dem Kasserver: **OK** (27.08.).

---

## Testplan (nach vollständiger 8.5-Umstellung)

Quelle: [`docs/TESTLISTE-2026-08-21.md`](TESTLISTE-2026-08-21.md) + offene Punkte aus Buchhaltungs-Branch.

### CRM — ganz-soft.de (Live)

- [ ] Login (Wartungsmodus: öffentlich 503, `/login` erreichbar)
- [ ] Belege-Filter: keine Textüberlappungen
- [ ] Kontakt neu: Auto-Vorlage Anzeigename/Benutzername
- [ ] Postfach-Checkbox nur bei Mitarbeiter/Admin vorausgewählt
- [ ] Beleg aus Kontaktsuche: Firmenname korrekt (gan→Ganz)
- [ ] Keine Lexoffice/Fremdsoftware-Texte in Buchhaltung
- [ ] Beleg Drucken/PDF ohne 500
- [ ] Teilzahlung sichtbar, Rechtsklauseln ohne Überlappung

### CRM — dg.ganz-om.de (Master)

- [ ] Login, Migration, Stichprobe Buchhaltung
- [ ] Cron: `bin/run-cron-purge-expired-employees.sh` / `cron.php` (CLI nutzt ohnehin php85)

### CRM — kontur-cosmetics.de

- [ ] Bereits 8.5 — Regression nach Sync: Login, Firmendaten, Stichprobe Module

### Shop — shop.ganz-soft.de

- [ ] Öffentlich: Wartungsmodus 503
- [ ] `/admin/login` + `/admin/wartung`: Toggle Wartung an/aus
- [ ] `/preise`, Impressum, Datenschutz

### Logs

- [ ] PHP-Error-Log / All-Inkl-Log auf neue **Deprecated**-Meldungen prüfen (s. Tabelle oben)

---

## Deploy (falls vor Test noch Code nachziehen)

```bash
bash bin/cloud-agent-ssh-setup.sh
# Master hochladen (deploy.bat / scp), dann:
ssh allinkl-ganzom "bash www/htdocs/w0217246/dg.ganz-om.de/bin/sync-crm-from-master.sh"
# Shop separat: deploy-shop.bat
```

---

## Merge-Empfehlung nach grünem 8.5-Test

```bash
git checkout master
git merge cursor/buchhaltung-randfaelle-1c3a
git push origin master
```

Dann erneut Master deployen + `sync-crm-from-master.sh`.
