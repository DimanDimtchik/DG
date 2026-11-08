# Vor Hetzner — CRM-Grundcheck

Stand: **2026-11-08** · Ziel: vor Server-Umzug Architektur, Assets und Rechts-/Steuerpflichten klären  
Bezug: Shared-Core-Idee · [`MULTI-FIRMA-KONZEPT.md`](MULTI-FIRMA-KONZEPT.md) · [`SERVER-DOMAIN-DATENBANK.md`](SERVER-DOMAIN-DATENBANK.md) · [`BELEGKETTE-DARSTELLUNG.md`](BELEGKETTE-DARSTELLUNG.md)

---

## 1. Architektur / Vereinfachungen

| Thema | Ist | Empfehlung vor/bei Hetzner |
|-------|-----|----------------------------|
| Code-Verteilung | `sync-crm-from-master.sh` kopiert ganzen CRM-Baum | **Shared Core** + Instanz nur Config/Uploads/Logs (siehe Ordnerplan) |
| `index.php` | Sehr groß (Routing + POST) | Schrittweise Router/Controller auslagern (nicht Blocker für Umzug) |
| Doppelte Logik | Teilweise Settings + Module | Vor Umzug nur **kritische** Dubletten; kein Big-Bang-Refactor |
| Shop / KDV / Plugins | Getrennt | Getrennt lassen |
| DB pro Instanz | Ja | Beibehalten; **nie** Shared-DB |

**Priorität Umzug:** Ordnerlayout + Deploy-Pipeline zuerst. Refactor PHP nur, wenn es Deploy/Shared-Core erleichtert.  
**PA3a (2026-11-08):** Ordnerplan unten dokumentiert — **kein Cutover**, kein Symlink-Umbau auf All-Inkl.

### 1.1 Shared-Core-Ordnerplan (PA3a)

#### Ist (All-Inkl heute)

| Rolle | Pfad (Account `w0217246`) | Inhalt |
|-------|---------------------------|--------|
| Code-Kern / Master | `…/dg.ganz-soft.de/` | vollständiger CRM-Baum (Deploy-Ziel) |
| Instanzen | `ganz-om.de`, `dg.ganz-om.de`, `ganz-soft.de`, `kontur-cosmetics.de`, … | **Kopie** des Baums via `bin/sync-crm-from-master.sh` |
| IQ Strom | Account `w01f1176` / `office.iqstrom.com` | eigener Sync vom Kern (nicht im w0217246-Skript) |

Sync-Excludes (Instanzdaten bleiben lokal) — Auszug aus `bin/sync-crm-from-master.sh`:

- `config/*.local.php` (und benannte Locals)
- `storage/`, `logs/`, `tmp-upload/`
- Nutzerinhalt am Domain-Root (z. B. `klarwin/`), `apps/`, `license-server/`, `.git/`

**Regel:** gleicher PHP-Code · eigene DB · eigene Locals/Storage.  
**Verboten:** `sync-crm-from-master.sh --delete` gegen den Account-Root.

#### Zielbild Hetzner (nach Cutover — hier nur Plan)

Ein physischer **Shared Core** (einmal Code), Instanzen nur als dünne Hüllen:

```text
/var/www/dg-crm/
  shared/                         # Shared Core (Git-Deploy, eine Version)
    index.php
    src/  views/  assets/  bin/  database/  …
    config/version.php            # kein *.local.php im Shared
  instances/
    ganz-om/                      # Dev
      public -> ../../shared      # oder DocumentRoot = shared + bootstrap
      config/*.local.php
      storage/
      logs/
    dg-ganz-om/                   # Firma
      config/  storage/  logs/
    ganz-soft/
      config/  storage/  logs/
    kontur/
      config/  storage/  logs/
    iqstrom/                      # ggf. anderer Host/Account, gleiches Muster
      config/  storage/  logs/
```

| Schicht | Gehört dahin | Gehört **nicht** dahin |
|---------|--------------|-------------------------|
| **Shared** | `src/`, `views/`, `assets/`, `bin/`, `database/migrations/`, öffentliche `index.php`/`bootstrap.php` | Firmendaten, Secrets, Uploads |
| **Instanz** | `config/*.local.php`, `storage/`, `logs/`, Cron-Wrapper falls nötig | eigener Fork von `src/` |
| **DB** | eine MariaDB-DB **pro** Instanz | eine DB für alle Mandanten |

Bootstrap-Idee (ohne Implementierung in PA3a): DocumentRoot zeigt auf Shared; `DG_INSTANCE_ROOT` (oder `config/instance.path`) zeigt auf den Instanzordner für Locals/Storage. Bis dahin bleibt Full-Tree-Sync gültig.

#### Übergangsregeln (vor Cutover)

1. Weiter Master → Sync wie heute; Ordnerplan ist die **Zielskizze** für Hetzner-Pipeline (PA3e).
2. Tages-Backup bereits **instanzdaten-orientiert** ([`CRON-AUTOSTART.md`](CRON-AUTOSTART.md): Dump + `storage/` + Locals, kein CRM-Code) — passt zum Shared-Core-Zielbild.
3. Backup/Restore-**Test** = separates Mini-ID **PA3b** (nicht PA3a).
4. Kein Tenant-Sondercode im Shared; keine Firmendaten hardcoden.

**Abnahme PA3a:** Ordnerplan Ist/Ziel + Exclude-Liste nachvollziehbar dokumentiert. ✅ 2026-11-08

---

## 2. CSS / JS — Minimierung

| Asset | Beobachtung | Maßnahme |
|-------|-------------|----------|
| `assets/css/dg.css` | Groß; App-Minify `dg.app.min.css` | ✅ `bin/minify-dg-css.sh` + `Asset::crmStylesheetUrl()` (Deploy: `deploy-via-rsync.sh` / `deploy-via-sftp.sh` / `deploy.bat`). `dg.min.css` = Offline-Stub · **PA3c** Selftest `bin/asset-css-minify-selftest.php` grün 2026-11-08 |
| Viele Einzel-JS | Seite lädt nur bedarfsweise (gut) | Beibehalten; Bundle nur wo sinnvoll (z. B. Belege) |
| Vendor bereits `.min` | cropper, JsBarcode, … | Unverändert |
| Kein einheitlicher Build | Manuell | Optional: `bin/minify-assets.sh` (cssnano / terser) im Deploy |

**Erwartung:** spürbar weniger Transfer, aber kein Ersatz für Shared Core (Platte). Minimierung = Performance, Shared Core = Speicher/Deploy.

**Nicht priorisieren:** aggressives Bundling aller Seiten-JS (erschwert Debugging ohne großen Gewinn).

---

## 3. Gesetzliche / steuerliche Bestimmungen (Checkliste)

Perspektive: GoBD · UStG · Belegpflicht · Datenschutz · Handelsregister-Angaben.

### Buchhaltung / Belege

| Anforderung | Status (Kurz) | Lücke / nächster Schritt |
|-------------|---------------|--------------------------|
| Belegkette nachvollziehbar | ✅ Kette + Status | Auto-AB aus Mail noch offen |
| Keine Buchung vor Rechnung (Angebot/AB/LS) | ✅ | — |
| Rechnungsangaben § 14 UStG | ✅ Pflichtzeilen/Logo/Steuernr. | Stichprobe Druck je Dokumentart |
| Netto / USt je Satz / Brutto | ✅ Summentabelle | Test gemischte Sätze 7/19/0 |
| § 19 Kleinunternehmer-Hinweis | ✅ Firmendaten → Steuerliche Sonderfälle | Produktiv aktivieren wenn zutreffend |
| Skonto / Mahnung / Teilzahlung | ✅ | Smoke-Test |
| Unveränderbarkeit / Änderungsnachweis | ✅ Ausgang Revision; Eingang nach Buchung gesperrt; manuelle Buchung = Storno/Gegenbuchung | Smoke auf Instanz; siehe `FIBU-VERKAUFSREIFE.md` |
| Aufbewahrung Belegdateien | ✅ Storage pro Instanz | Backup-Konzept Hetzner |
| DATEV / UStVA / ELSTER | ✅ / ELSTER live blockiert | Nach Hetzner ERiC |

### Datenschutz / Website

| Anforderung | Status | Lücke |
|-------------|--------|-------|
| Rechtstexte (Impressum, DSGVO, AGB …) | ✅ Mehrprodukt | Inhalte je Instanz prüfen |
| Wartungsmodus + Kontakt | ✅ | — |
| Auftragsverarbeitung / TOMs | 📄 organisatorisch | Mit Hetzner-AVV abstimmen |
| Löschkonzepte (Mitarbeiterdaten-Cron) | ✅ teilweise | Vollständige Übersicht Retention |

### Sonstiges

| Thema | Status |
|-------|--------|
| Stripe Shop live | bewusst **nicht** |
| LDAP | Phase 2 nach Hetzner |
| Multi-Firma | Phase 0; Switcher später |

---

## 4. Reihenfolge (Vorschlag)

1. **Jetzt:** Deploy/Test Belegdarstellung + AB-Druck (läuft)
2. **Vor Umzug:** Shared-Core-Ordnerplan ✅ PA3a · Backup/Restore-Test (PA3b)
3. **Vor Umzug:** Asset-Minify im Deploy (klein, risikoarm)
4. **Vor Umzug:** GoBD-Stichprobe (Änderung/Storno, Beleg-PDF, USt-Aufteilung)
5. **Beim Umzug:** Domains, DB-Restore, ELSTER/ERiC Linux
6. **Nach Umzug:** LDAP, Auto-AB-Mail, Material-Anzahlung

---

## 5. Bewusst nicht vor dem Umzug

- Großes UI-Redesign
- Komplettes Aufteilen von `index.php`
- Live-ELSTER auf All-Inkl erzwingen
