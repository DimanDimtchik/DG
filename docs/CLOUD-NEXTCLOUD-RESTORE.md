# Nextcloud cloud.ganz-om.de — Wiederherstellung & ganz-om.de → CRM

Stand: **2026-08-28** · Server-Check per SSH

---

## Kurzfassung (ehrlich)

| Quelle | Nextcloud-Daten? |
|--------|------------------|
| **Git (DG-Repo)** | **Nein** — nur CRM/Shop-Code, keine Nextcloud-Dateien |
| **Server jetzt** | **Fast alles weg** — nur 14 Dateien (~7 MB) übrig |
| **CRM-Datenbanken** | **Nein** — keine `oc_*`-Tabellen in d0477ae6, d046f637, d046f8ab, d047f810 |
| **All-Inkl KAS-Backup** | **Beste Chance** — FTP (~2/4 Wochen) + MySQL (Vortag, 5–8, 9–12 Tage) |

**Wahrscheinliches Löschdatum:** Ordner `cloud.ganz-om.de` zuletzt geändert **2026-08-21 17:02** (passt zu „vor ein paar Tagen“).

Nextcloud lief bis August 2026 noch (Log: `/ocs/v2.php/…` in Aufruf-Statistik).

---

## Was noch auf dem Server liegt

Pfad: `/www/htdocs/w0217246/cloud.ganz-om.de/`

```
cloud.ganz-om.de/
└── data/info@ganz-om.de/files/Steuer-Privat/Steuer/Buhl/steuer/…
    └── 14× *.steuer*.bak   (Buhl-Steuer-Backups, keine Passwörter-App)
```

**Fehlt komplett:**

- Nextcloud-Programm (`index.php`, `status.php`, `apps/`, `3rdparty/`, `config/config.php`)
- Weitere User-Dateien / Passwörter-App (`files/Passwords/` o. ä.)
- Eigene Nextcloud-MySQL-DB (nicht in CRM-DBs)

→ **403 Forbidden** ist normal: kein Web-Frontend mehr, nur Rest-`data/`.

---

## Schritt 1 — All-Inkl-Backup prüfen (Priorität 1)

Im **KAS** → **Tools** → **Datensicherung** / **Backup** (Bezeichnung je nach KAS-Version):

### A) FTP-/Webspace-Backup

All-Inkl legt ca. **alle 14 Tage** Sicherungen an (typisch **2 Stück**: ~2 Wochen + ~4 Wochen alt).

**Wiederherstellen:**

1. Backup **vor dem 21.08.2026** wählen (je älter, desto vollständiger — aber vor dem Löschtag).
2. Nur Ordner **`cloud.ganz-om.de`** (oder gesamten Account, vorsichtig) wiederherstellen.
3. Wiederhergestellte Dateien oft unter separatem Pfad (z. B. `_ProviderRestore` o. ä. — KAS-Hinweis beachten).
4. **Nicht** blind überschreiben — erst in Unterordner entpacken, prüfen, dann gezielt zurückkopieren.

**Erwartung bei Erfolg:** Ordner mit `status.php`, `config/config.php`, `data/` (groß), ggf. `apps/`.

### B) MySQL-Backup

Separate Snapshots: **Vortag**, **5–8 Tage**, **9–12 Tage**.

1. KAS → **MySQL** → alle Datenbanken anzeigen.
2. Nach DB suchen, die **nicht** CRM ist (nicht d0477ae6, d046f637, d046f8ab, d047f810).
3. Typisch: eigener Name `d04xxxxxx` mit Tabellen **`oc_*`** (Nextcloud-Prefix).
4. Falls DB **noch existiert**, aber leer: Backup aus KAS importieren.
5. Falls DB **gelöscht**: MySQL-Backup aus KAS wiederherstellen oder All-Inkl-Support anfragen.

**Ohne DB + `data/` zusammen** funktioniert Nextcloud nicht; **Passwörter-App** braucht zusätzlich den **Master-Passwort-Schlüssel** der App.

### C) All-Inkl-Support

Falls im KAS nichts Passendes sichtbar:

- support@all-inkl.com · +49 35872 353-41  
- Account: **w0217246**  
- Bitte: Wiederherstellung **`cloud.ganz-om.de`** vom **FTP-Backup vor 2026-08-21** + zugehörige **MySQL-DB** mit `oc_*`-Tabellen.

---

## Schritt 2 — Eigene Backups prüfen

- **PC/Laptop:** Nextcloud-Desktop-Client → Sync-Ordner (`Nextcloud`, `cloud.ganz-om.de`)?
- **Browser:** Passwort-Manager-Export?
- **Handy:** Nextcloud-App Offline-Dateien?
- **Externe Festplatte / NAS:** manuelle Kopien?
- **Buhl/Steuer:** die 14 `.bak` auf dem Server sind **Steuerfälle**, keine Cloud-Passwörter.

---

## Schritt 3 — Nach erfolgreicher Wiederherstellung

1. Nextcloud-Version prüfen (`status.php` oder `occ status`).
2. PHP **8.5** kompatibel? (Nextcloud-Version ggf. updaten.)
3. `config/config.php` — DB-Zugangsdaten aus KAS prüfen.
4. Rechte: `data/` für Webserver beschreibbar.
5. **Erst dann** öffentlich schalten; Passwörter-App mit Master-Passwort testen.

Frische Installation **ohne** Backup hilft **nicht** bei Passwörter-Wiederherstellung — nur bei leerer Neuanlage.

---

## ganz-om.de → CRM (separates Vorhaben)

| Thema | Stand |
|-------|--------|
| **ganz-om.de** | Nur `wp-content/` + Platzhalter `index.php` — **kein** WordPress-Core |
| **Ziel** | CRM wie ganz-soft.de (eigene Instanz oder Sync) |
| **WP bereinigen** | `ganz-om.de/wp-content/` löschen/archivieren, wenn CRM steht |
| **wp-backup-archive/** | 300 KB — kleines WP-Restarchiv, kein Nextcloud |

**Vorgehen (später, nach Cloud-Klarheit):**

1. Entscheiden: eigene DB + frische CRM-Installation **oder** Sync von Master.
2. `ganz-om.de/` mit CRM befüllen (nicht Account-Root).
3. `config/database.local.php`, `license.php`, Wartungsmodus setzen.
4. KAS: bleibt `/ganz-om.de/`, PHP 8.5.

---

## Checkliste für nächste Session

- [ ] KAS Datensicherung: FTP-Backup `cloud.ganz-om.de` vor 21.08. finden
- [ ] KAS MySQL: alle DBs listen, `oc_*`-DB identifizieren / wiederherstellen
- [ ] PC/Handy auf lokale Nextcloud-Sync prüfen
- [ ] Bei Erfolg: Nextcloud testen, dann ganz-om.de → CRM planen
- [ ] Bei Misserfolg: Support All-Inkl + externe Backups

---

## Referenzen

- All-Inkl Datensicherung: https://all-inkl.com/webhosting/datensicherung/
- Server-Pfad: `/www/htdocs/w0217246/cloud.ganz-om.de`
- Domain-Übersicht: `docs/domains-report.json`
