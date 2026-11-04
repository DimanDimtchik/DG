# Installations-Log — ganz-om.de (Dev)

> Parallel-Dokumentation während der Neuinstallation.  
> Zielinstanz: **https://ganz-om.de** = CRM **Dev**  
> Akademie-Anleitung (Ergebnis): folgt als [`docs/akademie/…`](akademie/) + Szenario  
> Domain-Rollen: [`SERVER-DOMAIN-DATENBANK.md`](SERVER-DOMAIN-DATENBANK.md)

**Stand:** 2026-11-01 — Installation auf **ganz-om.de erfolgreich** (Einladung an info@ganz-om.de).

---

## 0. Vor dem Assistenten (Checkliste)

| # | Schritt | Status | Notiz |
|---|---------|--------|-------|
| 0.1 | Domain-Rolle klar: Dev = `ganz-om.de` | ✅ | Code-Kern später `dg.ganz-soft.de` |
| 0.2 | Alte Website/DB-Inhalte prüfen & retten (falls nötig) | ✅ | 113× `n2x4x_*` gelöscht (2026-11-01); Backup `storage/backups/wordpress-n2x4x-20261101-131614.sql` (~3,7 MB); DB nur noch 142× `dg_*` |
| 0.3 | CRM-Code auf `ganz-om.de` vorhanden | ✅ | `install.php` läuft, Version **1.0.29** |
| 0.4 | Systemprüfung im Assistenten grün | ✅ | Anzeige war „PHP ≥ 8.1“ (nur Mindestcheck). **Nach Installation:** Anzeige/Mindest = **PHP 8.5** (`bin/install.php` + `composer.json`) — wegen Verwechslung + Sicherheitsstand 8.5. Laufender Installer auf ganz-om.de noch alt bis Sync. |
| 0.5 | KAS-Zugangsdaten bereithalten (`w0217246` + Passwort) | ⏳ | Nutzer |
| 0.6 | SMTP-Postfach bereithalten (z. B. `info@ganz-om.de` + Postfach-Passwort) | ⏳ | Nutzer |
| 0.7 | Entscheiden: **neue DB** oder **bestehende** `d046bc57` | ⏳ | Empfehlung Dev: neue DB *oder* bestehende leeren nach Backup |

---

## 1. Assistent — Schritte (Live)

Installer: **https://ganz-om.de/install.php**

| Schritt | Titel | Inhalt (kurz) | Status | Beobachtung |
|---------|--------|---------------|--------|-------------|
| 1 | DB | KAS für Kunden **unsichtbar** (Betreiber-`kas.local.php`); DB neu/bestehend; Absender-Mail | ✅ | DB `d046bc57` |
| 2 | Domain | Domain-Zuordnung / ggf. Migration | ✅ | |
| 3 | Firma | Firmendaten | ✅ | |
| 4 | Recht | Rechtstexte / Pflichtangaben | ✅ | |
| 5 | Import | optional; **ohne Import möglich** | ✅ | Skip; Fix `templateCsv` Mitarbeiter |
| 6 | Benutzer | Admin-Benutzer anlegen → Abschluss | ✅ | Dietrich Ganz / info@ganz-om.de; `storage/.installed` |

---

## 2. Entscheidungen (während Installation ausfüllen)

| Thema | Entscheidung | Warum |
|-------|--------------|-------|
| Datenbank | _offen_ | |
| Absender-Mail | _offen_ | |
| Firma / Rechtsform | Dietrich Ganz / Einzelunternehmen (laut CRM `dg.ganz-om.de`) | Adresse: Auf dem Bühl 1, 87437 Kempten |
| Steuernummer | **127/219/40770** | aus Firmeneinstellungen `dg.ganz-om.de` |
| USt-IdNr. | **DE461693381** | aus Firmeneinstellungen `dg.ganz-om.de` |
| Steuer-ID (11-stellig) | leer im CRM | ggf. privat / später; Installation darf ohne |
| Import | **übersprungen** | nichts zu importieren |
| Admin-Benutzer | Dietrich Ganz / info@ganz-om.de | Einladungsmail gesendet |

---

## 3. Nach der Installation (noch offen)

- [ ] Login `https://ganz-om.de/login` (Konto über Einladungslink aktivieren)
- [ ] Wartungsmodus bewusst belassen oder später freigeben
- [x] Cookie-Details in Datenschutzerklärung (`#cookies` + Tabelle); Banner-Link `/datenschutz#cookies` (2026-11-01, ganz-om regeneriert)
- [ ] Firmendaten / ELSTER-Vorbereitung / Buchhaltung prüfen
- [ ] **Deploy/Sync:** PHP-Mindestanzeige 8.5 (`bin/install.php`) auf Code-Kern → Instanzen
- [ ] Sync-Pfad: freigegebene Stände → Code-Kern `dg.ganz-soft.de` → andere Instanzen
- [ ] Installationsanleitung für Akademie finalisieren

---

## 4. Hinweise für das spätere Akademie-Video

- Einfache Sprache, echte CRM-Screenshots vom Installer + Dashboard nach Login  
- Dashboard als Einstieg **nach** fertiger Installation (Video-Regeln)  
- Keine echten Passwörter / KAS-Secrets im Video — Demo-Platzhalter  
- Stimme DE: `de-DE-KatjaNeural`  
- Szenario-Datei: `docs/akademie/szenario-crm-installation.md` (wird parallel aufgebaut)
