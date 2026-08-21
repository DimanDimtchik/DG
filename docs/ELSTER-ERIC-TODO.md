# ELSTER / ERiC — Todo nach Server-Umzug

> **Nicht vor Umzug starten** (Kasserver: kein ERiC).  
> Voraussetzung: Root-Server gemäß [SERVER-MIGRATION.md](./SERVER-MIGRATION.md) — Favorit **Hetzner SX65-2**.

---

## Phase 0 — Bereits erledigt (Kasserver, ohne ERiC)

- [x] UStVA-Kennziffern aus Belegen (`UstvaReportService`)
- [x] ELSTER/EÜR-CSV (`ElsterExportService`)
- [x] DIY-Jahresabschluss-Assistent (`FiscalCloseService`)
- [x] Jahres-Sperre für Belege
- [x] `ElsterSettings` + Readiness-Stub (`ElsterEricClient`)
- [x] Einstellungen-Tab ELSTER (Vorbereitung, Modus CSV)
- [x] `config/elster.local.php.example`
- [x] `bin/elster-readiness.php`

---

## Phase 1 — Server & Basis (nach Umzug auf Hetzner)

### Infrastruktur

- [ ] Server **Hetzner SX65-2** (oder gleichwertig) produktiv
- [ ] OS: Debian 12 oder Ubuntu 22.04 LTS, Updates, Zeitzone `Europe/Berlin`
- [ ] Nutzer `deploy`, SSH-Key, Firewall (22/80/443)
- [ ] nginx + PHP 8.2 FPM + MariaDB 10.11
- [ ] TLS (Let’s Encrypt) für `dg.ganz-om.de`
- [ ] CRM deployen (`deploy.bat` / rsync anpassen auf neuen Host)
- [ ] DB migrieren (Dump Kasserver → Import Hetzner)
- [ ] `storage/` migrieren (Belege, Medien, Mail-Archive)
- [ ] Cronjobs (`cron.php`, Mitarbeiter-Purge) auf neuem Server
- [ ] SMTP/Versand testen
- [ ] DNS Cutover + 48 h Monitoring
- [ ] Kasserver-Instanz read-only / abschalten

### Konfiguration

- [ ] `config/elster.local.php` aus Example anlegen (nur auf Server, nicht in Git)
- [ ] In **Einstellungen → ELSTER**: Modus vorerst `csv` lassen bis ERiC steht

---

## Phase 2 — ERiC organisatorisch

- [ ] Registrierung [ELSTER Entwickler](https://www.elster.de/eportal/infoseite/entwickler)
- [ ] **Hersteller-ID** beantragen und in `elster.local.php` eintragen
- [ ] ELSTER-Newsletter abonnieren
- [ ] ERiC Linux x64 (aktuelle Mindestversion) aus Entwicklerbereich laden
- [ ] Lizenzvereinbarung ERiC akzeptieren / dokumentieren
- [ ] **Test-Softwarezertifikat** in Mein ELSTER anlegen (nicht Produktions-Zertifikat für Tests)
- [ ] Testfinanzamt / Testmerker `700000004` für UStVA-Tests dokumentieren

---

## Phase 3 — ERiC technisch auf Server

- [ ] Verzeichnis z. B. `/opt/eric/` (Owner `deploy`, Rechte 750)
- [ ] ERiC entpacken, Abhängigkeiten (`libssl`, `libxml2`) prüfen
- [ ] Wrapper `bin/eric-worker` (Shell/PHP/Go — ruft ERiC-API auf)
- [ ] Worker nur localhost oder internes Netz (kein öffentliches Endpoint ohne Auth)
- [ ] `ElsterEricClient` → HTTP/CLI zum Worker implementieren
- [ ] Logging: `storage/logs/elster.log` (ohne PIN/Zertifikat-Inhalt)
- [ ] Migration `053_elster_submissions.sql` anwenden (Abgabe-Historie)
- [ ] `ElsterSubmissionRepository` aktivieren

---

## Phase 4 — UStVA Testbetrieb

- [ ] `ElsterXmlBuilder` — XML aus `UstvaReportService`-Positionen
- [ ] Einstellungen: Testmodus an, Test-Zertifikat hochladen (verschlüsselt)
- [ ] Button **„ELSTER prüfen“** (`dryRun` / Testmerker) auf UStVA-Seite
- [ ] Testübermittlung ans Testfinanzamt
- [ ] Antwort-PDF speichern und in UI anzeigen
- [ ] Fehlercodes ERiC → verständliche Meldungen
- [ ] Regression: CSV-Export weiterhin verfügbar (Fallback)

---

## Phase 5 — UStVA Produktion

- [ ] Echtes ELSTER-Softwarezertifikat auf Server (verschlüsselt, Backup-Plan)
- [ ] PIN-Handling: nur verschlüsselt in DB/Settings, nie Logs
- [ ] Modus in Einstellungen: `eric` aktivieren
- [ ] Button **„An Finanzamt senden“** mit Bestätigungsdialog
- [ ] Abgabe in `dg_elster_submissions` protokollieren
- [ ] Berichtigte Anmeldung (Kennzeichen „Berichtigung“) — Anforderungen klären
- [ ] AuditLog-Eintrag bei jeder Abgabe

---

## Phase 6 — Erweiterungen (optional, später)

- [ ] EÜR per ERiC (Anlage EÜR XML)
- [ ] USt-Jahreserklärung
- [ ] KDV-Kundeninstanzen: ERiC pro Mandant vs. zentraler Worker (Architektur entscheiden)
- [ ] Automatische ERiC-Mindestversion prüfen (Cron + Admin-Warnung)
- [ ] Zertifikat-Ablauf 30 Tage vorher mailen

---

## Zertifikat-Regeln (Merken)

| Situation | Zertifikat | Testmerker |
|-----------|------------|------------|
| Entwicklung | Test-Zertifikat (Mein ELSTER) | `700000004` |
| Staging | Test-Zertifikat | `700000004` |
| Live-Abgabe | **Echtes** Software-Zertifikat | **keiner** |

⚠️ Test-UStVA kann als abgegeben gelten — nur mit Testdaten / Testmandant testen.

---

## Nützliche Befehle (nach Umzug)

```bash
# Readiness-Check im CRM-Verzeichnis
php bin/elster-readiness.php

# Migration (wenn 053 existiert)
php bin/db-migrate.php
```

---

## Offene Architektur-Fragen (vor Phase 5 klären)

1. Ein ERiC-Worker für alle KDV-Mandanten oder Worker pro Kunde?
2. Zertifikat pro Mandant in KDV-Instanz oder nur Master-CRM?
3. Backups: Zertifikat `.pfx` separat offline sichern?
