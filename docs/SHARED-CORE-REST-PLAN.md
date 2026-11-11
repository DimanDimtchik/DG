# Shared Core — Rest-Plan (tokenarm)

> **Eine Chat-Session = eine Mini-ID.**  
> Ziel: **ein** Code-Baum auf GEX45, Instanzen nur Hülle + DB + Storage.  
> Fach-Skizze: [`VOR-HETZNER-CHECK.md`](VOR-HETZNER-CHECK.md) §1.1 · Platten: [`GEX45-DISK-LAYOUT.md`](GEX45-DISK-LAYOUT.md) · Umzug: [`PHASE-B-REST-PLAN.md`](PHASE-B-REST-PLAN.md)

Stand: **2026-11-09** · Entscheidung: Kern = `/var/www/dg-crm/shared` (**kein** öffentliches `kern.ganz-om.de`) · MariaDB **eine DB pro Instanz** · Storage auf NVMe B

---

## Warum tokenarm

| Regel | Bedeutung |
|-------|-----------|
| **Kein Explorer** | Nur die Mini-ID |
| **Kein Big-Bang** | Spec → Bootstrap → Pilot Dev → Rest |
| **Kein Tenant im Shared** | Firmendaten nur Instanz-Locals/DB |
| **1 ID = 1 Commit oder 1 Ops-Nachweis** | Doc / kleines PHP / nginx-Notiz |
| **All-Inkl bleibt Full-Tree** | Shared Core **nur GEX45** (nach PB2) |

**Chat-Prompt:**  
`Nur Mini-ID SC1a laut docs/SHARED-CORE-REST-PLAN.md. Nur Docs. Kein Cutover. Kein Tenant-Hardcode.`

---

## Token-Stufen

| Symbol | Wann |
|--------|------|
| 🟢 | Doc, Checkliste, Statusnotiz |
| 🟡 | kleines PHP/Bootstrap/Deploy-Snippet im Repo |
| 🔴 | Mensch am Server (nginx, Rechte, DNS) |

---

## Entscheidungen (fest)

| Punkt | Wahl |
|-------|------|
| Kern-Pfad | `/var/www/dg-crm/shared` |
| Öffentliche Domain für Kern | **nein** (kein Kunden-DocumentRoot auf Shared) |
| Instanz | `/var/www/dg-crm/instances/<host>/` + `public/` als nginx-Root |
| Storage | Symlink → `/mnt/data/crm-storage/<host>/` |
| DB | MariaDB, **eine DB pro Instanz** |
| Deploy | einmal nach `shared/` · kein N× Vollbaum-Sync |
| Reihenfolge zu Phase B | Spec (SC0–SC1) **jetzt** · Live erst nach **PB2** (Stack) · Pilot vor Massen-Cutover ideal |

---

## Parent

| ID | Thema | Status | Token |
|----|--------|--------|-------|
| SC0 | Inventar / dieser Plan | [x] 2026-11-09 | 🟢 |
| SC1 | Ordner + nginx Spec (Docs) | [x] SC1a–c ✅ 2026-11-09 | 🟢 |
| SC2 | Bootstrap `DG_INSTANCE_ROOT` (Repo) | [x] SC2a–c ✅ 2026-11-09 | 🟡 |
| SC3 | Pilot-Instanz Dev auf GEX45 | [ ] | 🔴🟡 |
| SC4 | Deploy-Pipeline → nur `shared/` | [ ] | 🟡🔴 |
| SC5 | Weitere Instanzen umhängen | [~] SC5a ✅ 2026-11-11 · SC5b–c offen | 🔴🟢 |
| SC6 | Backup-Doku an Shared-Core | [ ] | 🟢 |
| SC7 | Full-Tree-Sync abschalten / Nachzug | [ ] | 🟢🟡 |

---

## Mini-IDs

### SC0 — Plan

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| SC0a | Dieser Plan + Verweis in `TODOS.md` / Phase B / VOR-HETZNER | 🟢 | [x] 2026-11-09 |

### SC1 — Ordner & nginx (nur Docs)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| SC1a | Zielbaum final in diesem Plan § „Zielbaum“ (shared / instances / storage) | 🟢 | [x] 2026-11-09 |
| SC1b | nginx-Beispiel vHost: `root …/instances/<host>/public` · PHP-FPM · Hinweis kein Shared-vHost | 🟢 | [x] 2026-11-09 |
| SC1c | Exclude-Liste Shared vs Instanz (Locals, storage, logs) — Abgleich Sync-Skript | 🟢 | [x] 2026-11-09 |

### SC2 — Bootstrap (Repo, ohne Live-Umschalten)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| SC2a | Spec: `DG_INSTANCE_ROOT` + wie `DG_ROOT` = Shared; Locals/Storage nur über Instanz | 🟢 | [x] 2026-11-09 |
| SC2b | `public/index.php`-Stub (Instanz) → `require shared/bootstrap.php` — Beispiel im Repo (`docs/` oder `deploy/shared-core/`) | 🟡 | [x] 2026-11-09 `deploy/shared-core/public-index.php.example` |
| SC2c | Selftest oder kurzer Guard: Shared ohne `*.local.php`; Instanz-Pfad lesbar | 🟡 | [x] 2026-11-09 `bin/shared-core-layout-selftest.php` |

**Gate:** SC2 Live-Nutzung erst mit SC3 auf GEX45 (nach PB2).

### SC3 — Pilot Dev (nach PB2)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| SC3a | Ordner `shared/` + `instances/ganz-om.de/` auf GEX45 anlegen (Rechte `deploy`) | 🔴 | [x] 2026-11-11 shared schon PB2b · Hülle public/config/logs + index-Stub |
| SC3b | Storage-Symlink → `/mnt/data/crm-storage/ganz-om.de` | 🔴 | [ ] |
| SC3c | nginx Dev-vHost auf Instanz-`public/` · Smoke `/login` | 🔴 | [ ] |
| SC3d | Statusnotiz Pilot OK in IQ-STROM / diesem Plan | 🟢 | [ ] |

**Prompt SC3a:**  
`Nur Mini-ID SC3a laut docs/SHARED-CORE-REST-PLAN.md. Nur Ordner anlegen. Kein DNS-Cutover.`

**Voraussetzung:** PB2a (Stack) grün.

### SC4 — Deploy nur Shared

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| SC4a | Deploy-Ziel dokumentieren: rsync/scp → `/var/www/dg-crm/shared` (PC + Cloud) | 🟢 | [ ] |
| SC4b | Skript oder `deploy.ps1`-Pfad anpassen / Parallel-Skript `deploy-shared-core` (ohne All-Inkl brechen) | 🟡 | [ ] |
| SC4c | Kurztest: Datei in Shared sichtbar für Pilot-Instanz | 🔴 | [ ] |

### SC5 — Weitere Instanzen

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| SC5a | Checkliste „neue Instanz“: Ordner, Locals, DB-User, Storage-Symlink, vHost, TLS | 🟢 | [x] 2026-11-11 · § unten |
| SC5b | `dg.ganz-om.de` / `ganz-soft.de` / `kontur` umhängen (einzeln, mit Freigabe) | 🔴 | [ ] |
| SC5c | `office.iqstrom.com` gleiches Muster (eigener Account-Pfad egal, gleiches Layout) | 🔴 | [ ] |

**Regel:** eine Instanz pro Chat/Freigabe — kein Massen-Umbau.

#### SC5a — Checkliste neue Instanz (GEX Shared Core, nur Docs)

Platzhalter: `<fqdn>` = Domain der Instanz (z. B. `ganz-om.de`). **Kein** Code in Shared kopieren — Shared einmal deployen (SC4). Live-Ausführen = SC5b/c mit Freigabe.

| # | Schritt | Soll | Nicht |
|---|---------|------|--------|
| 1 | **Ordner Instanz** | `/var/www/dg-crm/instances/<fqdn>/` mit `public/`, `config/`, `logs/` · Owner typisch `deploy:www-data` | PHP-App/`src`/`vendor` in die Hülle legen |
| 2 | **Public-Stub** | `public/index.php` aus `deploy/shared-core/public-index.php.example` · setzt `DG_INSTANCE_ROOT` → Shared-Bootstrap | Full-Tree `index.php` aus All-Inkl spiegeln |
| 3 | **Locals** | Mind. `config/database.local.php` · `config/app.local.php` · weitere `*.local.php` nach Bedarf · Rechte eng (nicht weltlesbar) | Locals in `/var/www/dg-crm/shared/config/` · Secrets in Git |
| 4 | **DB** | **Eigene** MariaDB-Datenbank + User **nur** für diese Instanz · Zugangsdaten nur in `database.local.php` | Eine DB für mehrere FQDNs · fremde Instanz-DB |
| 5 | **Schema** | Migrationen/`runPending` gegen **diese** DB (Shared-`bin/`, mit `DG_INSTANCE_ROOT`) | Schema von anderer Instanz „mitbenutzen“ |
| 6 | **Storage-Ordner** | `/mnt/data/crm-storage/<fqdn>/` anlegen (NVMe B) | Storage unter Shared oder unter `instances/…/storage` als echte Kopie |
| 7 | **Storage-Symlink** | `instances/<fqdn>/storage` → `/mnt/data/crm-storage/<fqdn>` | Relativer Pfad ohne Mount · Shared-`storage/` |
| 8 | **nginx vHost** | Ein `server` pro FQDN · `root …/instances/<fqdn>/public` · PHP-FPM (SC1b-Beispiel) | `root …/shared` · ein vHost für alle Instanzen |
| 9 | **TLS** | certbot/Let’s Encrypt für `<fqdn>` (nach DNS oder Staging-Host) | Zertifikat einer anderen Domain wiederverwenden ohne SAN |
| 10 | **DNS / Cutover** | Erst nach Freigabe (PB4 / SC5b) · bis dahin All-Inkl oder Hosts-Datei | DNS umstellen „zum Testen“ ohne Rollback-Plan |
| 11 | **Smoke** | HTTPS `/login` · Session · eine geschützte App-Seite | Nur Default-nginx-Seite als „fertig“ werten |
| 12 | **Wartung / Kontakt** | Öffentliche Website: Wartungsmodus laut Produktregel · Kontakt aus CRM (keine Hardcodes) | Firmendaten anderer Instanz kopieren |

**Kurzbefehle (Skizze — nicht SC5a-Abnahme):**

```bash
FQDN=ganz-om.de   # Beispiel
sudo mkdir -p /var/www/dg-crm/instances/$FQDN/{public,config,logs}
sudo mkdir -p /mnt/data/crm-storage/$FQDN
sudo ln -sfn /mnt/data/crm-storage/$FQDN /var/www/dg-crm/instances/$FQDN/storage
# public/index.php aus deploy/shared-core/public-index.php.example
# config/*.local.php anlegen · DB+User · nginx+certbot · Smoke /login
```

**Abnahme SC5a:** Checkliste #1–12 im Plan · Verweis Stub/Zielbaum · **ohne** Live-Ordner und **ohne** DNS.

### SC6 — Backup

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| SC6a | Backup-Matrix: Shared (selten) · Locals · DB/Instanz · `crm-storage/<host>` (oft) | 🟢 | [ ] |
| SC6b | Verweis in `CRON-AUTOSTART.md` / `GEX45-DISK-LAYOUT.md` nachziehen | 🟢 | [ ] |

### SC7 — Altwege abschalten

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| SC7a | `sync-crm-from-master.sh`: Status „GEX45 Shared Core = obsolet / All-Inkl only“ dokumentieren | 🟢 | [ ] |
| SC7b | Nach allen Instanzen: Full-Tree-Kopien auf GEX45 entfernen (Freigabe) | 🔴 | [ ] |
| SC7c | Parent-Haken SC0–SC7 + TODOS/IQ-STROM Nachzug | 🟢 | [ ] |

---

## Zielbaum (SC1a — verbindlich)

Platten: **NVMe A** = Code + Instanz-Hüllen · **NVMe B** = `crm-storage` ([`GEX45-DISK-LAYOUT.md`](GEX45-DISK-LAYOUT.md)).  
Ordnername Instanz = **FQDN** (wie Storage-Ordner), z. B. `ganz-om.de` — nicht verkürzen.

```text
# NVMe A
/var/www/dg-crm/
  shared/                              # genau eine Version — Deploy-Ziel
    bootstrap.php
    index.php                          # nur falls CLI/Cron Shared braucht; nginx root = Instanz-public
    .htaccess                          # Referenz; Live-Regeln primär nginx (SC1b)
    composer.json  composer.lock
    vendor/
    src/  views/  assets/  bin/  database/
    downloads/                         # statische Kern-Downloads (falls vorhanden)
    config/
      version.php                      # erlaubt
      # verboten: *.local.php, users.php, license.php, Secrets
    # verboten: storage/  logs/  tmp-upload/  .git/

  instances/
    ganz-om.de/                        # Dev — Pilot SC3
      public/
        index.php                      # Stub: DG_INSTANCE_ROOT setzen → shared/bootstrap.php (SC2)
        # optional: robots.txt, favicon — keine PHP-App-Kopie
      config/
        database.local.php
        app.local.php
        # weitere *.local.php nach Bedarf
      logs/                            # Instanz-Logs (nicht Shared)
      storage -> /mnt/data/crm-storage/ganz-om.de
    dg.ganz-om.de/
      public/  config/  logs/
      storage -> /mnt/data/crm-storage/dg.ganz-om.de
    ganz-soft.de/
      public/  config/  logs/
      storage -> /mnt/data/crm-storage/ganz-soft.de
    kontur-cosmetics.de/
      public/  config/  logs/
      storage -> /mnt/data/crm-storage/kontur-cosmetics.de
    office.iqstrom.com/                # gleiches Layout; physisch ggf. anderer Mount — Muster gleich
      public/  config/  logs/
      storage -> /mnt/data/crm-storage/office.iqstrom.com

# NVMe B
/mnt/data/crm-storage/
  ganz-om.de/                          # Belege, Uploads, Mail-Anhänge, Akademie-Medien …
  dg.ganz-om.de/
  ganz-soft.de/
  kontur-cosmetics.de/
  office.iqstrom.com/
  …
```

### Schichten

| Schicht | Pfad | Ja | Nein |
|---------|------|----|------|
| **shared** | `/var/www/dg-crm/shared` | `src/`, `views/`, `assets/`, `bin/`, `database/migrations/`, `vendor/`, `config/version.php` | `*.local.php`, Uploads, Firmendaten, Tenant-Ordner (`klarwin/` …) |
| **instance** | `/var/www/dg-crm/instances/<fqdn>` | `public/` (nginx root), `config/*.local.php`, `logs/`, Symlink `storage` | Fork von `src/`/`views/`, zweite `vendor/`-Kopie |
| **storage** | `/mnt/data/crm-storage/<fqdn>` | Belege, Medien, Instanz-Dateien | PHP-Code |
| **DB** | MariaDB | **eine** DB (+ User) **pro** Instanz | eine DB für alle Mandanten |

### Regeln (kurz)

1. **nginx `root`** = `…/instances/<fqdn>/public` — **nie** `…/shared` (SC1b).  
2. **`DG_ROOT`** (später SC2) = Shared-Pfad; **`DG_INSTANCE_ROOT`** = Instanz-Ordner (Locals + Storage).  
3. Owner typisch `deploy:www-data` · Shared lesbar für FPM · Instanz-`config/` eng (keine Welt-lesbar).  
4. Nutzerinhalt am Domain-Root (z. B. `klarwin/`) nur unter **Instanz** oder Storage — nicht in Shared.  
5. All-Inkl bleibt Full-Tree; dieser Baum gilt **nur GEX45**.

### nginx (SC1b — Beispiel, nicht live einspielen)

**Verboten:** `server` mit `root /var/www/dg-crm/shared` (kein öffentlicher Shared-vHost, kein `kern.*`).

**Pflicht:** ein vHost **pro Instanz-FQDN**; `root` = Instanz-`public/`. Assets unter Shared werden vom PHP-Code bzw. Alias bedient (siehe unten).

Datei z. B. `/etc/nginx/sites-available/ganz-om.de` (Let’s Encrypt später per certbot):

```nginx
# Beispiel Dev — GEX45 Shared Core (SC1b)
# PHP-FPM-Sock anpassen (php8.2-fpm / php8.3-fpm).

server {
    listen 80;
    listen [::]:80;
    server_name ganz-om.de www.ganz-om.de;

    root /var/www/dg-crm/instances/ganz-om.de/public;
    index index.php;

    # Belege/Uploads nicht per URL freigeben (nur App)
    location ^~ /storage/ {
        deny all;
        return 404;
    }

    # Statische Kern-Assets aus Shared (eine Kopie für alle Instanzen)
    location ^~ /assets/ {
        alias /var/www/dg-crm/shared/assets/;
        access_log off;
        expires 7d;
        try_files $uri =404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        # Instanz-Wurzel für Bootstrap (SC2) — FPM pool darf das auch per env setzen
        fastcgi_param DG_INSTANCE_ROOT /var/www/dg-crm/instances/ganz-om.de;
        include fastcgi_params;
    }

    location ~* \.(?:bak|local\.php)$ {
        deny all;
    }
}
```

| Punkt | Regel |
|-------|--------|
| Weitere Domains | gleiche Vorlage, `server_name` + `root` + `DG_INSTANCE_ROOT` tauschen |
| TLS | certbot `--nginx` nach DNS; HTTP→HTTPS Redirect OK |
| Shared | **kein** `server_name` auf `/var/www/dg-crm/shared` |
| CLI/Cron | `cd …/shared && DG_INSTANCE_ROOT=…/instances/<fqdn> php bin/cron.php` (Detail SC2) |
| Live-Anlegen | erst **SC3c** / PB2 — dieses Beispiel ist Spec |

### Abgrenzung zu älteren Skizzen

| Quelle | Abweichung → SC1a |
|--------|-------------------|
| VOR-HETZNER §1.1 `ganz-om/` | → FQDN `ganz-om.de/` |
| VOR-HETZNER `public -> ../../shared` | → **eigene** `public/index.php`-Stub, nicht DocumentRoot=Shared |
| GEX45 früher `/var/www/instances/…` | → **`/var/www/dg-crm/instances/…`** |

### Exclude-Liste Shared vs Instanz (SC1c)

Abgleich mit `bin/sync-crm-from-master.sh` (All-Inkl Full-Tree). Unter Shared Core **wandern** dieselben Instanzdaten **nicht mehr per Sync**, sondern liegen nur unter `instances/<fqdn>/` bzw. NVMe B.

| Pfad / Muster | Heute Sync-Exclude | Shared Core | Bemerkung |
|---------------|--------------------|-------------|-----------|
| `config/*.local.php` | ja | **nur Instanz** `instances/<fqdn>/config/` | inkl. `database`, `app`, `mail`, `kas`, `cron`, `company`, … |
| `config/users.php` | ja | **nur Instanz** | Secrets |
| `config/license.php` | ja | **nur Instanz** | |
| `config/version.php` | nein (wird kopiert) | **Shared** | erlaubt im Kern |
| `storage/` | ja | **Symlink** → `/mnt/data/crm-storage/<fqdn>/` | nie im Shared-Baum |
| `logs/` | ja | **nur Instanz** | |
| `tmp-upload/` | ja | **nur Instanz** (oder unter storage) | |
| `cursor-transfer/` | ja | nicht deployen | Ops-Müll, nicht Produkt |
| `.git/` | ja | nicht in `shared/` auf Server | Deploy ohne `.git` |
| `probe*.txt` / `*.bak` | ja | nicht deployen | |
| `www/` | ja | All-Inkl-Artefakt | auf GEX45 irrelevant |
| `wp-backup-archive/` | ja | entsorgt / nicht anlegen | |
| `klarwin/` | ja | **nur Instanz** oder Storage | Nutzerinhalt, kein Kern |
| `apps/` | ja | getrennt (Flutter o. ä.) | nicht in Shared-CRM-Deploy mischen |
| `license-server/` | ja | eigener Dienst | nicht Shared-CRM |
| `shop/` | (IQ-Sync) | getrennt | Shop bleibt eigenes Projekt |
| `vendor/` | nein (mitkopiert) | **Shared** einmal | kein N× pro Instanz |
| `src/` `views/` `assets/` `bin/` `database/` | nein | **Shared** | |
| `public/` (Instanz-Stub) | n/a | **nur Instanz** | SC2b |

**Deploy nach `shared/` (SC4):** nur Kern-Dateien; Excludes mindestens: `config/*.local.php`, `storage/`, `logs/`, `tmp-upload/`, `.git/`, `instances/`, Nutzerordner.

**Neue Instanz:** Checkliste **SC5a** (oben) — `public/` + Locals + `logs/` + Storage-Symlink + eigene DB — **kein** Code-Sync in die Hülle.

**All-Inkl:** `sync-crm-from-master.sh` Excludes bleiben gültig, bis GEX45 Shared Core alle Instanzen trägt (SC7).

### Bootstrap-Konstanten (SC2a — Spec, noch kein Code)

Heute: `bootstrap.php` setzt `DG_ROOT = __DIR__` (voller Instanzbaum). Locals und `storage/` hängen überall an `DG_ROOT` (`Database`, `BackupService`, Media, …).

**Zielvertrag Shared Core:**

| Konstante | Wert | Bedeutung |
|-----------|------|-----------|
| `DG_ROOT` | `/var/www/dg-crm/shared` | Code, `vendor/`, `views/`, `assets/`, `bin/`, `database/migrations/`, `config/app.php`, `config/database.php` (Defaults), `config/version.php` |
| `DG_INSTANCE_ROOT` | `/var/www/dg-crm/instances/<fqdn>` | Instanz-Hülle: `config/*.local.php`, `logs/`, Symlink `storage` |

**Setzen von `DG_INSTANCE_ROOT` (Reihenfolge):**

1. schon definiert (CLI: `DG_INSTANCE_ROOT=… php …`) → belassen  
2. sonst FastCGI/nginx `DG_INSTANCE_ROOT` (SC1b)  
3. sonst Umgebungsvariable `DG_INSTANCE_ROOT`  
4. **fehlt** → Fail-fast mit klarer Meldung (kein stilles Zurückfallen auf Shared als Instanz)

**All-Inkl / Full-Tree-Kompatibilität (Übergang):** Wenn `DG_INSTANCE_ROOT` **nicht** gesetzt und Layout = klassisch (Locals liegen unter `DG_ROOT/config/`), dann `DG_INSTANCE_ROOT := DG_ROOT`. So bleibt Kasserver lauffähig, bis SC7.

**Pfad-Hilfen (Soll, Umsetzung SC2b+ / spätere Code-IDs):**

| Hilfsfunktion (Name frei) | Liefert |
|---------------------------|---------|
| `dg_shared_path($rel)` | `DG_ROOT . '/' . $rel` — Code/Assets |
| `dg_instance_path($rel)` | `DG_INSTANCE_ROOT . '/' . $rel` — Locals, logs |
| `dg_storage_path($rel)` | `DG_INSTANCE_ROOT . '/storage/' . $rel` — Belege (Symlink → NVMe B) |

**Muss über Instanz laufen (nicht Shared):**

- alle `config/*.local.php`, `users.php`, `license.php`
- `storage/**` (Media, Belege, Logs unter storage, State-JSON, …)
- Instanz-`logs/` falls getrennt von storage

**Darf/Shared bleiben:**

- `config/app.php`, `config/database.php` (ohne Secrets), `config/version.php`
- `src/`, `views/`, `assets/`, `bin/`, `vendor/`, Migrations-**Dateien**

**Boot-Ablauf (Soll):**

```text
instances/<fqdn>/public/index.php     ← Vorlage: deploy/shared-core/public-index.php.example (SC2b)
  → define DG_INSTANCE_ROOT = dirname(__DIR__)
  → require /var/www/dg-crm/shared/index.php
       → bootstrap.php: DG_ROOT = shared · DG_INSTANCE_ROOT auflösen (SC2a)
       → vendor + src/autoload + App::boot() + Routing in shared/index.php
```

**CLI/Cron:**  
`DG_INSTANCE_ROOT=/var/www/dg-crm/instances/<fqdn> php /var/www/dg-crm/shared/bin/cron.php`  
(Arbeitsverzeichnis egal, solange Konstanten stimmen.)

**Nicht in SC2a:** PHP-Produktion ändern, Live-Server (→ SC3).  
**SC2b erledigt:** Stub-Vorlage `deploy/shared-core/public-index.php.example` (+ README).

---

## Empfohlene Reihenfolge

1. **Jetzt (ohne Server):** SC0–SC2 ✅ · **SC5a** ✅ (Instanz-Checkliste) · SC4a/SC6a Docs parallel  
2. **Nach PB2 (Stack):** **SC3** Pilot Dev (SC3b Storage-Symlink …)  
3. **SC4** Deploy-Pipeline Shared (Skript SC4b)  
4. Cutover weiterer Domains: **SC5b/c** (einzeln) · parallel **SC6** Backup-Docs  
5. **SC7** Alt-Sync / Aufräumen  

**Nächster Chat (Doc):**  
`Nur Mini-ID SC6a laut docs/SHARED-CORE-REST-PLAN.md. Nur Docs.`  
**Server:** `Nur Mini-ID SC3b …` / `SC5b …` (Freigabe je Host).

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`VOR-HETZNER-CHECK.md`](VOR-HETZNER-CHECK.md) §1.1 | Erste Shared-Core-Skizze |
| [`GEX45-DISK-LAYOUT.md`](GEX45-DISK-LAYOUT.md) | NVMe A Code · NVMe B Storage |
| [`PHASE-B-REST-PLAN.md`](PHASE-B-REST-PLAN.md) | Server-Lieferung / Cutover |
| [`DEPLOY-PIPELINE-PREP.md`](DEPLOY-PIPELINE-PREP.md) | heutiges Deploy (Full-Tree) |
| [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) | Roadmap A→C |
| [`deploy/shared-core/`](../deploy/shared-core/) | Stub `public-index.php.example` (SC2b) |
| [`bin/shared-core-layout-selftest.php`](../bin/shared-core-layout-selftest.php) | Guard Vorlagen + optional Live-Pfade (SC2c) |
