# PC-Handoff — Stand 2026-08-21

> **Aktuell:** Buchhaltung Phasen A–C auf Branch **`cursor/buchhaltung-phase-abc-6a0c`** — Deploy/Migration vom PC.  
> **→ Deploy-Anleitung:** [`docs/BUCHHALTUNG-PC-HANDOFF.md`](BUCHHALTUNG-PC-HANDOFF.md)  
> Handy/Cloud: [`docs/HANDY-HANDOFF.md`](HANDY-HANDOFF.md) · SSH/Secrets: [`docs/CLOUD-AGENT-ACCESS.md`](CLOUD-AGENT-ACCESS.md)

> Historisch: Zwei Feature-Branches waren unterwegs; Website-Bootstrap und Datenimport sind inzwischen auf `master`.

---

## 1. Git — Branches checkouten

```powershell
cd C:\Users\dietr\Projects\DG   # oder dein Pfad
git fetch origin
```

### Branch A: Website-Bootstrap (bereits auf master?)

Prüfen: `git log master --oneline -3`  
Falls Pflichtseiten schon auf master: nichts tun.

Falls noch separater Branch:

```powershell
git checkout cursor/website-bootstrap-pflichtseiten-6a0c
```

### Branch B: Datenimport Installation (aktuell)

```powershell
git checkout cursor/install-data-import-6a0c
git pull origin cursor/install-data-import-6a0c
```

**Merge nach master (wenn Tests OK):**

```powershell
git checkout master
git pull origin master
git merge cursor/install-data-import-6a0c
git push origin master
```

**PR manuell:** https://github.com/DimanDimtchik/DG/compare/master...cursor/install-data-import-6a0c

---

## 2. Was ist implementiert?

### Website-Bootstrap (master)

| Was | Wo |
|-----|-----|
| Impressum, Datenschutz, AGB | `src/Legal/LegalPageGenerator.php` |
| Startseite je Branche | `src/Website/WebsiteHomepageTemplates.php` |
| Bootstrap nach Install | `src/Website/WebsiteBootstrapService.php` |
| CLI + Server-Skript | `bin/seed-website-defaults.php`, `bin/run-website-bootstrap-on-instances.sh` |
| CRM-Button | Website → Seiten → „Pflichtseiten jetzt anlegen“ |
| Shop Unternehmenstyp | `shop/` bei voller Domain |

### Datenimport Installation (Branch `cursor/install-data-import-6a0c`)

| Was | Status |
|-----|--------|
| Install-Schritt 5: Datenimport | ✅ |
| Install-Schritt 6: Benutzer | ✅ |
| Fortschritts-UI nach Installation | ✅ (`assets/js/install-import.js`) |
| Kontakte, Mitarbeiter, Termine | ✅ Excel, CSV, XML, JSON |
| Artikel/Leistungen | ✅ inkl. PDF |
| Quellsystem-Presets (DATEV, Lexware, ShiftBase, …) | ✅ |
| Belege/Rechnungen | ✅ Entwürfe + Dateianhang (`docs/IMPORT-BELEGE-TODO.md`) |

**Neue Dateien:**

```
src/Install/
  InstallImportQueue.php
  InstallImportRunner.php
  InstallImportSourcePresets.php
  InstallContactImporter.php
  InstallEmployeeImporter.php
  InstallBookingImporter.php
  InstallVoucherImporter.php      # Stub
  InstallCsvHelper.php
assets/js/install-import.js
docs/IMPORT-FORMATE.md
docs/IMPORT-BELEGE-TODO.md
```

---

## 3. Lokal testen (Installationsassistent)

```powershell
# PHP built-in server (im Repo-Root)
php -S localhost:8080
```

1. `storage/.installed` löschen (falls vorhanden)
2. `config/database.local.php` + `config/app.local.php` ggf. umbenennen/entfernen für frische Installation
3. Browser: http://localhost:8080/install.php
4. Schritte 1–4 wie gewohnt
5. **Schritt 5:** Import-Typ wählen, Quellsystem, Datei hochladen (Test-Excel)
6. **Schritt 6:** Benutzer → Installation
7. Fortschrittsanzeige abwarten → Login

**Beispiel-Vorlagen:** `install.php?action=import-template&type=contacts` (employees, bookings, articles)

**Ohne echte DB:** Nur UI/Flow prüfbar; Import braucht laufende MySQL + KAS oder `database.local.php`.

---

## 4. Deploy auf Live-Instanzen

### Code deployen

```powershell
.\deploy.ps1 -WithPlugins
```

oder Server:

```bash
bash bin/sync-crm-from-master.sh
```

### Website-Bootstrap auf bestehenden Instanzen

```bash
bash bin/run-website-bootstrap-on-instances.sh --overwrite
```

### SSH-Problem (gelöst am PC)

SSH funktioniert lokal mit Host **`allinkl-ganzom`** / Key `~/.ssh/id_ed25519_ganzom`  
(`ssh-[REDACTED]@[REDACTED].kasserver.com`).

**Cloud Agent:** gleicher Key, aber `DG_ALLINKL_SSH_USER` muss der **SSH-Login** (`ssh-…`) sein — nicht der KAS-Weblogin. Details: [`docs/CLOUD-AGENT-ACCESS.md`](CLOUD-AGENT-ACCESS.md) Abschnitt 4.

```powershell
ssh allinkl-ganzom
.\deploy.bat
.\deploy-shop.bat
```

**Wichtig:** `bin/sync-crm-from-master.sh` syncronisiert den Live-Root **ohne** `--delete` (sonst würden Geschwister-Domains gelöscht).
---

## 5. Offene TODOs (Priorität)

| Prio | Thema | Doku |
|------|-------|------|
| 1 | Stripe Keys in `shop/config/stripe.local.php` + Webhook (bewusst zurückgestellt) | `docs/SHOP-TODO.md` Phase 2 |
| 2 | AGB/Widerruf juristisch prüfen lassen | `/agb`, `/widerruf` |
| 3 | DATEV EXTF / API ShiftBase (später) | `docs/IMPORT-FORMATE.md` |

---

## 6. Testliste

Manuelle Tests: **`docs/TESTLISTE-2026-08-21.md`**

- Abschnitt **G** — Website-Bootstrap
- Abschnitt **H** — Shop Unternehmenstyp
- Abschnitt **I** — Datenimport Installation (neu)

---

## 7. Commits (Referenz)

```
ad96af5 Import: Excel und Quellsystem-Presets statt nur CSV
454b262 Datenimport im Installationsassistenten mit Fortschrittsanzeige
97d1a46 README: Anleitung Website-Bootstrap und Pflichtseiten
5509dbd Hinweise Wartungsmodus, Testliste und Server-Bootstrap-Skript
050347b Automatische Pflichtseiten, Startseite und Kontaktformular nach Installation
```

---

## 8. Schnellstart am PC (5 Min)

```powershell
git fetch origin
git checkout cursor/install-data-import-6a0c
git pull
# Testen oder mergen — siehe oben
```

Fragen / Blocker: Branch-Namen und Dateipfade in dieser Datei sind die Ankerpunkte.
