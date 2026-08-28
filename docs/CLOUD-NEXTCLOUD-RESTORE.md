# Nextcloud cloud.ganz-om.de — Wiederherstellung & ganz-om.de → CRM

Stand: **2026-08-28** · **Restore abgeschlossen** ✅

---

## Restore-Status (28.08.2026)

| Schritt | Status |
|---------|--------|
| DB **d046bc58** (`nextcloud_om`) Backup **16.08.** | ✅ eingespielt (Option 2) |
| Webspace **`cloud.ganz-om.de`** Backup **16.08.** | ✅ Option 5 (nur Verzeichnis) |
| Nextcloud erreichbar | ✅ https://cloud.ganz-om.de/ |
| Version | **33.0.1** · maintenance: false |
| Passwörter-App | ✅ installiert (`passwords` 2026.3.21) |
| Daten | **~11 GB** · 2008 Dateien · `files:scan` ohne Fehler |
| PHP 8.5 | ✅ `php85 occ status` OK |

**Noch manuell durch Nutzer:** Login testen + Passwörter-App mit **Master-Passwort** öffnen.

---

## Kurzfassung (ehrlich)

| Quelle | Nextcloud-Daten? |
|--------|------------------|
| **Git (DG-Repo)** | **Nein** — nur CRM/Shop-Code, keine Nextcloud-Dateien |
| **Server jetzt** | **Fast alles weg** — nur 14 Dateien (~7 MB) übrig |
| **CRM-Datenbanken** | **Nein** — keine `oc_*`-Tabellen in d0477ae6, d046f637, d046f8ab, d047f810 |
| **All-Inkl KAS-Backup** | **✅ Verfügbar** — Webspace **16.08.** (16 GB) + DB **d046bc58** (`nextcloud_om`) |

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

## Schritt 1 — KAS-Backup wiederherstellen (bestätigt 28.08.2026)

### A) Webspace-Backup (Tools → Backups → Webspace-Backup)

| Datum | Größe | Empfehlung |
|-------|-------|------------|
| **16.08.2026** | **16,061 GB** | **✅ DIESES** — letztes volles Backup vor Löschung (~21.08.) |
| 09.08.2026 | 16,061 GB | Alternative |
| 02.08.2026 | 16,180 GB | Fallback |
| 22.–28.08.2026 | ~0,27 GB | **❌ Nicht** — bereits ohne Cloud-Daten |

**Vorgehen:**

1. Wiederherstellungs-Icon bei **16.08.2026** klicken.
2. Wenn möglich: **nur `cloud.ganz-om.de`** — sonst in **Unterordner** legen (CRM nicht überschreiben!).
3. Prüfen: `status.php`, `config/config.php`, große `data/`.
4. Gezielt nach `/www/htdocs/w0217246/cloud.ganz-om.de/` kopieren.

### B) Datenbank-Backup (Tools → Backups → Datenbank-Backup)

| KAS-Eintrag | DB-ID | Aktion |
|-------------|-------|--------|
| **cloud.ganz-om.de: nextcloud_om** | **d046bc58** | **✅ Wiederherstellen** (Backup vor 21.08.) |
| ganz-om.de: wordpress_om | d046bc57 | Nur für WP — später durch CRM ersetzt |

1. Zeile **nextcloud_om** → Wiederherstellen.
2. Passendes Backup-Datum wählen (10 Versionen verfügbar).
3. Tabellen `oc_*` in **d046bc58** prüfen.

**Ohne DB + `data/` zusammen** funktioniert Nextcloud nicht; **Passwörter-App** braucht den **Master-Passwort-Schlüssel** der App.

### C) All-Inkl-Support (nur bei Problemen)

- support@all-inkl.com · +49 35872 353-41 · Account **w0217246**

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

- [x] **Webspace-Backup 16.08.2026** — Option 5 nur `cloud.ganz-om.de`
- [x] **DB d046bc58** (`nextcloud_om`) Backup 16.08.
- [x] `files:scan --all` ohne Fehler
- [ ] **Nutzer:** Login https://cloud.ganz-om.de/ + Passwörter-App Master-Passwort prüfen
- [ ] ganz-om.de → CRM planen (WP-DB **d046bc57** separat)

---

## Referenzen

- All-Inkl Datensicherung: https://all-inkl.com/webhosting/datensicherung/
- Server-Pfad: `/www/htdocs/w0217246/cloud.ganz-om.de`
- Domain-Übersicht: `docs/domains-report.json`
