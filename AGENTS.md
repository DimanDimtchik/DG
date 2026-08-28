# DG CRM — Regeln (für jeden Chat)

> **Keine Aufgabenliste hier.** Offener Stand → [`docs/TODOS.md`](docs/TODOS.md)

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
| **Entwickeln** | Nur auf **`dg.ganz-om.de`** (Master-CRM im Repo + Server-Pfad `…/dg.ganz-om.de/`) |
| **Code verteilen** | Nach Deploy des Masters: `bin/sync-crm-from-master.sh` auf alle CRM-Instanzen |
| **Gleicher Code** | Alle Instanzen nutzen **dieselben** PHP-Dateien (`index.php`, `src/`, `views/`, …) — **kein Sondercode** pro Domain |
| **Eigene Daten** | Jede Instanz hat **eigene DB** und `config/*.local.php` — **nie** fremde Instanz-DB anzapfen |
| **Merge** | Einzelentwickler: getestet → direkt **`master`**, keine Pflicht-PRs |

### Instanzen (CRM)

| Instanz | Rolle |
|---------|--------|
| `dg.ganz-om.de` | Master — hier entwickeln & deployen |
| `ganz-soft.de` | Live-Test / Produktion (Sync vom Master) |
| `kontur-cosmetics.de` | Kunden-Instanz (Sync vom Master) |
| `ganz-om.de` | Platzhalter bis CRM-Install (Sync vom Master, ohne DB) |
| `shop.ganz-soft.de` | **Eigenes** Projekt unter `shop/` — **nicht** im CRM-Sync |

### Deploy-Ablauf

**PC:** `deploy.bat` / `deploy.ps1` → Master hochladen  

**Server (nach Master-Upload):**

```bash
bash bin/sync-crm-from-master.sh
```

**Cloud-Agent:** zuerst `bash bin/cloud-agent-ssh-setup.sh` (muss `SSH_OK` liefern). Details: [`docs/CLOUD-AGENT-ACCESS.md`](docs/CLOUD-AGENT-ACCESS.md)

**Verboten:** `sync-crm-from-master.sh` mit `--delete` gegen den Account-Root (`/www/htdocs/w0217246/`).

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
