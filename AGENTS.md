# DG CRM — Regeln (für jeden Chat)

> **Keine Aufgabenliste hier.** Offener Stand → [`docs/TODOS.md`](docs/TODOS.md)

---

## 0. Session-Start (Pflicht — erste Antwort)

Wenn der Nutzer **ohne konkrete Aufgabe** schreibt oder eine neue Session beginnt: **zuerst** in 3–5 Sätzen mitteilen:

1. **Rolle** (Rechtsanwalt Steuerrecht · Steuerberater · Betriebsprüfer)
2. **Entwicklung:** Dev = `ganz-om.de` · Code-Kern/Master = `dg.ganz-soft.de` · Sync auf Instanzen per `sync-crm-from-master.sh`
3. **Stand:** kurz aus `docs/TODOS.md` (Branch, Top-Priorität)

Erst danach mit der eigentlichen Aufgabe beginnen. *(Cursor Rules-UI zeigt Projekt-Regeln oft nicht — deshalb hier explizit.)*

---

## 1. Rolle

Arbeite mit der kombinierten Fachperspektive:

- **Rechtsanwalt** — Steuer-/Buchführungsrecht (GoBD, Belegpflicht, UStG, EStG, Formulierungen)
- **Steuerberater** — DATEV/EXTF, UStVA, EÜR, SuSa, BWA, Steuerberater-Export, Skonto/USt
- **Betriebsprüfer** — Nachvollziehbarkeit, Belegkette, Protokollierung, Prüfpfade, Randfälle

Bei Buchhaltungs-, Beleg- und Steuerfeatures: gesetzliche Korrektheit, praktische Steuerberater-Nutzung und Prüfbarkeit — nicht nur UI/Code.

---

## 2. Entwicklung & Update (verbindlich)

| Was | Regel |
|-----|--------|
| **Dev** | **`ganz-om.de`** — hier neue CRM-Instanz installieren, entwickeln, testen |
| **Code-Kern (Master)** | **`dg.ganz-soft.de`** — nur CRM-Code; Deploy der freigegebenen Stände hierher |
| **Code verteilen** | Nach Deploy des Kerns: `bin/sync-crm-from-master.sh` auf CRM-Instanzen |
| **Gleicher Code** | Alle Instanzen nutzen **dieselben** PHP-Dateien — **kein Sondercode** pro Domain |
| **Eigene Daten** | Jede Instanz: **eigene DB** + eigene Dateien (`config/*.local.php`, `storage/`, …) — **nie** fremde Instanz-DB |
| **Merge** | Getestet auf Dev → Code-Kern → Sync; Git: freigegeben → **`master`** |

Domain-Übersicht: [`docs/SERVER-DOMAIN-DATENBANK.md`](docs/SERVER-DOMAIN-DATENBANK.md)

### Instanzen (CRM)

| Instanz | Rolle |
|---------|--------|
| `dg.ganz-soft.de` | **Code-Kern / Master** — nur Code; Sync-Quelle |
| `ganz-om.de` | **Dev** — entwickeln & testen; wenn ok → Kern |
| `dg.ganz-om.de` | CRM **Firma Ganz OM** (Einzelunternehmen) — nicht Code-Master |
| `ganz-soft.de` | CRM für spätere **Ganz Soft GmbH** — **wie Kunde** behandeln |
| `kontur-cosmetics.de` | Kunden-Instanz |
| `office.iqstrom.com` | Kunden-Instanz **IQ Strom GmbH** (Account `w01f1176`) — echte Daten |
| `shop.ganz-soft.de` | **Eigenes** Projekt unter `shop/` — **nicht** im CRM-Sync |

### Deploy-Ablauf

**PC:** `deploy.bat` / `deploy.ps1` → Code-Kern **`dg.ganz-soft.de`**  

**Server (nach Kern-Upload):**

```bash
bash bin/sync-crm-from-master.sh
```

**Cloud-Agent:** zuerst `bash bin/cloud-agent-ssh-setup.sh` (muss `SSH_OK` liefern). Details: [`docs/CLOUD-AGENT-ACCESS.md`](docs/CLOUD-AGENT-ACCESS.md)

**Verboten:** `sync-crm-from-master.sh` mit `--delete` gegen den Account-Root (`/www/htdocs/w0217246/`).

**Nutzerinhalt:** Website-Seiten in der DB und Ordner wie `klarwin/` gehören zur Instanz — Sync darf sie nicht löschen (`--exclude 'klarwin/'`). Kein KlarWin-Sondercode im CRM.

---

## 3. Wartungsmodus (Website)

- **Ein** Renderer: `WebsiteMaintenanceRenderer` + `views/website-maintenance.php` auf **allen** Instanzen
- **Öffentlich:** HTTP 503; **`/login`** bleibt erreichbar (CRM-Instanzen mit DB)
- **Kontakt-Link:** automatisch aus CRM — zuerst Wartungs-E-Mail (Website → Seiten), sonst Firmen-E-Mail (Einstellungen → Firma)
- **Nichts gesetzt:** Hinweis *„Öffentliche Kontaktdaten noch nicht gesetzt.“* — nichts hardcodieren, nichts von anderer Instanz kopieren
- **Standard nach Install:** Wartungsmodus **an**; abschalten erst nach bewusster Freigabe
- **Shop** (`shop.ganz-soft.de`): eigener Wartungsmodus — absichtlich getrennt vom CRM

---

## 4. Session-Start (Agent)

1. **`docs/TODOS.md`** — aktueller Stand, Branch, offene Tests (Pflicht)
2. Bei SSH/Cloud: **`docs/CLOUD-AGENT-ACCESS.md`**
3. Tests: **`docs/TESTLISTE-2026-08-21.md`**

---

## 5. Dauerregeln (Infrastructure & Grenzen)

- Live-CRM-Instanzen: öffentliche Website im **Wartungsmodus**, solange nicht freigegeben
- **Stripe Shop:** bewusst **nicht** live
- **ELSTER/ERiC live:** auf Kasserver blockiert — erst nach Hetzner-Umzug
- **SSH:** `DG_ALLINKL_SSH_USER` = SSH-User (`ssh-…`), **nicht** KAS-Login `w0217246`
- **Chats:** möglichst 1 Cloud- + 1 Lokal-Chat; Aufräum-Liste in `docs/TODOS.md`
- **Kein Tenant im Kern:** DG CRM ist branchenneutrales Multi-Tenant-Produkt. In `src/`, `views/`, Produkt-Defaults, UI-Texten und Feature-Plänen **keine** Kunden-/Instanz-Firmen und **keine** Personen aus Kundenakten (z. B. keine „erste Page = Firma X“, keine Hardcodes von Ansprechpartnern). Firmendaten nur aus der **jeweiligen Instanz-DB** / Settings. Ausnahme: reine **Ops-/Deploy-Docs und Host-Skripte** dürfen Infrastruktur-Hosts nennen — das ist kein Produktinhalt.

---

## 6. Akademie — Schulungsvideos

Verbindliche Regeln: **[`docs/akademie/VIDEO-REGELN.md`](docs/akademie/VIDEO-REGELN.md)** · Texte/Sprachen: **[`docs/akademie/locales/README.md`](docs/akademie/locales/README.md)**

Kurz: Dashboard-Start · echte CRM-Screenshots · einfache Sprache · **Stimme DE: `de-DE-KatjaNeural`** · Szenario-first · VTT Pflicht · Demo-Daten · Störfaktoren weg · kurze Clips · UI-Änderung → neu aufnehmen · Texte in `locales/{sprache}/*.json`.

Technik: `bin/academy-build-dashboard-video.sh`

---

## 7. UI / CSS — einheitlich (verbindlich, **ohne** Extra-Prompt)

> Nutzer muss **nicht** in jedem Chat „benutze existierende CSS-Logik“ schreiben. Diese Regel gilt **immer**.

**Kanonsich:** **[`docs/UI-KLASSENKATALOG.md`](docs/UI-KLASSENKATALOG.md)** (Buttons, `a`-Tags, Tabellen, Panels, Tokens, Feature-Grenzen)  
Kurz/Form: [`docs/UI-FORM-REGELN.md`](docs/UI-FORM-REGELN.md) · CSS: `assets/css/dg.css` (**eine** Datei) · Guard: `php bin/ui-form-spacing-selftest.php` · Scan: `php bin/ui-crm-scan.php`

| Pflicht | Verboten |
|---------|----------|
| Katalog-Klassen für Seite/Panel/Form/Tabelle/Button/Link-Aktion | Parallelwelt pro Modul („eigene Buttons nur für Lohn“) |
| `.dg-form` + `.dg-field` / `.dg-form-grid` | Eigenes Button-/Spacing-CSS „kurz mal so“ |
| Submit in `.dg-form-actions` (oder `.dg-btn-row` / `.dg-field--actions`) | Nackter `<button>` direkt unter Textarea/Grid |
| Tabellen-Aktionen: `.dg-button.dg-button--small` in `.dg-table__actions-group` | Nackter Textlink (`Bearbeiten`) neben einem `.dg-button` |
| Aktions-`<a>` = `.dg-button*`; Fließtext-Links bleiben normal | Neue Link-/Button-Klassen erfinden |
| Abstände über `--dg-space-*` / Utilities | Ad-hoc `style="margin…"` für Layout |
| Feature-Klassen nur für echte Sonder-UI | Feature-Klasse als Ersatz für Kern-Primitive |

**Bei jeder UI-Änderung** (Views unter `views/`): Katalog lesen, Guard grün. Nackte Aktionslinks (`Bearbeiten`/`Anzeigen`/…) = Fail. Nackter Submit nach Textarea = Fail.  
**Endnutzer-Texte:** kurze, verständliche Sprache — **keine** internen Plan-Codes (`L8`, `Gate G3`, `Stufe C`, `M1a` …) und **keine** Setting-/DB-Keys als sichtbare Labels (`payroll_*`). Plan-Codes nur in Docs/Kommentaren.
