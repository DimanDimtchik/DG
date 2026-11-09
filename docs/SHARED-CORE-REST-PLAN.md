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
| SC1 | Ordner + nginx Spec (Docs) | [ ] | 🟢 |
| SC2 | Bootstrap `DG_INSTANCE_ROOT` (Repo) | [ ] | 🟡 |
| SC3 | Pilot-Instanz Dev auf GEX45 | [ ] | 🔴🟡 |
| SC4 | Deploy-Pipeline → nur `shared/` | [ ] | 🟡🔴 |
| SC5 | Weitere Instanzen umhängen | [ ] | 🔴 |
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
| SC1a | Zielbaum final in diesem Plan § „Zielbaum“ (shared / instances / storage) | 🟢 | [ ] |
| SC1b | nginx-Beispiel vHost: `root …/instances/<host>/public` · PHP-FPM · Hinweis kein Shared-vHost | 🟢 | [ ] |
| SC1c | Exclude-Liste Shared vs Instanz (Locals, storage, logs) — Abgleich Sync-Skript | 🟢 | [ ] |

**Prompt SC1a:**  
`Nur Mini-ID SC1a laut docs/SHARED-CORE-REST-PLAN.md. Zielbaum-Abschnitt ausfüllen/schärfen. Nur Docs.`

### SC2 — Bootstrap (Repo, ohne Live-Umschalten)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| SC2a | Spec: `DG_INSTANCE_ROOT` + wie `DG_ROOT` = Shared; Locals/Storage nur über Instanz | 🟢 | [ ] |
| SC2b | `public/index.php`-Stub (Instanz) → `require shared/bootstrap.php` — Beispiel im Repo (`docs/` oder `deploy/shared-core/`) | 🟡 | [ ] |
| SC2c | Selftest oder kurzer Guard: Shared ohne `*.local.php`; Instanz-Pfad lesbar | 🟡 | [ ] |

**Prompt SC2a:**  
`Nur Mini-ID SC2a laut docs/SHARED-CORE-REST-PLAN.md. Nur Spec-Abschnitt Bootstrap. Kein Live-Server.`

**Gate:** SC2 Live-Nutzung erst mit SC3 auf GEX45.

### SC3 — Pilot Dev (nach PB2)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| SC3a | Ordner `shared/` + `instances/ganz-om.de/` auf GEX45 anlegen (Rechte `deploy`) | 🔴 | [ ] |
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
| SC5a | Checkliste „neue Instanz“: Ordner, Locals, DB-User, Storage-Symlink, vHost, TLS | 🟢 | [ ] |
| SC5b | `dg.ganz-om.de` / `ganz-soft.de` / `kontur` umhängen (einzeln, mit Freigabe) | 🔴 | [ ] |
| SC5c | `office.iqstrom.com` gleiches Muster (eigener Account-Pfad egal, gleiches Layout) | 🔴 | [ ] |

**Regel:** eine Instanz pro Chat/Freigabe — kein Massen-Umbau.

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

## Zielbaum (Kurz — Detail in SC1a)

```text
/var/www/dg-crm/
  shared/                          # Deploy-Ziel (eine Version)
    index.php  bootstrap.php
    src/  views/  assets/  bin/  database/  vendor/
    config/version.php             # kein *.local.php
  instances/
    <host>/
      public/index.php             # Stub → shared
      config/*.local.php
      logs/
      storage → /mnt/data/crm-storage/<host>

/mnt/data/crm-storage/<host>/      # Belege, Uploads
```

| Schicht | Ja | Nein |
|---------|----|------|
| **shared** | PHP-Code, Assets, Migrationen-Dateien | Secrets, Uploads, Firmendaten |
| **instance** | Locals, logs, storage-Link, nginx public | Fork von `src/` |
| **DB** | eine MariaDB-DB / Instanz | eine DB für alle Mandanten |

---

## Empfohlene Reihenfolge

1. **Jetzt (ohne Server):** SC0 ✅ → **SC1a–c** → **SC2a–c** (Repo-ready)  
2. **Nach PB2 (Stack):** SC3 Pilot Dev  
3. **SC4** Deploy-Pipeline Shared  
4. Cutover weiterer Domains: **SC5** (einzeln) · parallel **SC6** Backup-Docs  
5. **SC7** Alt-Sync / Aufräumen  

**Nächster Chat:**  
`Nur Mini-ID SC1a laut docs/SHARED-CORE-REST-PLAN.md. Zielbaum schärfen. Nur Docs. Kein Cutover.`

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`VOR-HETZNER-CHECK.md`](VOR-HETZNER-CHECK.md) §1.1 | Erste Shared-Core-Skizze |
| [`GEX45-DISK-LAYOUT.md`](GEX45-DISK-LAYOUT.md) | NVMe A Code · NVMe B Storage |
| [`PHASE-B-REST-PLAN.md`](PHASE-B-REST-PLAN.md) | Server-Lieferung / Cutover |
| [`DEPLOY-PIPELINE-PREP.md`](DEPLOY-PIPELINE-PREP.md) | heutiges Deploy (Full-Tree) |
| [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) | Roadmap A→C |
