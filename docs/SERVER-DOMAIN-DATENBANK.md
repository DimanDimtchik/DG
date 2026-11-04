# Server / Domains / Verwendung

> Stand: **2026-11-01** (Rollen korrigiert)  
> Druck-PDF: [`Server-Domains-Ueberblick.pdf`](Server-Domains-Ueberblick.pdf) · HTML: [`Server-Domains-Ueberblick.html`](Server-Domains-Ueberblick.html) · CSV: [`server-domain-databases.csv`](server-domain-databases.csv)

**Accounts:** Ganz Soft / ganz-om → All-Inkl **`w0217246`** · IQ Strom → All-Inkl **`w01f1176`**  
**Keine Passwörter** in dieser Liste (nur im Passwortmanager).

### Architektur (verbindlich)

| Rolle | Domain |
|--------|--------|
| **Code-Kern (Master)** | `dg.ganz-soft.de` — nur CRM-Code; Instanzen ziehen Code von hier |
| **Dev** | `ganz-om.de` — neue CRM-Installation zum Entwickeln/Testen; wenn ok → auf Master |
| **Firma Ganz OM** | `dg.ganz-om.de` — eigenes CRM (Einzelunternehmen), wie produktive Nutzung |
| **Kunde (künftig GmbH)** | `ganz-soft.de` — CRM für spätere Ganz Soft GmbH, **wie Kunde** behandeln |
| **Kunde** | `kontur-cosmetics.de`, `office.iqstrom.com` |

Instanzen: **gleicher Code** · **eigene DB** · **eigene Instanz-Dateien** (`config/*.local.php`, `storage/`, …) — speichert Platz.

**Gelöscht 2026-11-01 (w0217246):** `d046f8ab` (dg-user), `d0472643` (hr), `d0472087` (terminkalender).

---

## Domainliste mit Verwendung

| Domain | Account | DB | System | Verwendung |
|--------|---------|-----|--------|------------|
| **dg.ganz-soft.de** | w0217246 | — | CRM **Code-Kern** | Nur Code (Master). Andere Instanzen syncen von hier. Keine eigene Fach-DB nötig. |
| **ganz-om.de** | w0217246 | d046bc57 | CRM **Dev** | Neu installieren / entwickeln / testen. Wenn ok → auf Code-Master ausrollen. Vor Umbau Website/DB-Reste retten. |
| **dg.ganz-om.de** | w0217246 | d0477ae6 | CRM Firma **Ganz OM** | Eigenes CRM des Einzelunternehmens Ganz OM (nicht Code-Master). |
| **ganz-soft.de** | w0217246 | d046f637 | CRM Kunde (künftig) | Später Ganz Soft GmbH — **wie Kundeninstanz** behandeln (Sync vom Code-Kern). |
| **kontur-cosmetics.de** | w0217246 | d047f810 | CRM Kunde | Kundeninstanz Kontur Cosmetics |
| **office.iqstrom.com** | w01f1176 | office.iqstrom.com | CRM Kunde **IQ Strom** | Neu: komplette Einrichtung + Test mit **echten Daten** ab 2026-11-01 |
| **cloud.ganz-om.de** | w0217246 | d046bc58 | Nextcloud | Datei-Cloud |
| **shop.ganz-soft.de** | w0217246 | d0472653 | Shop | SaaS-Shop (`shop/`), nicht im CRM-Sync |
| **kasse.ganz-soft.de** | w0217246 | d0472650 | Kasse | Kassensystem |
| **license-server.ganz-soft.de** | w0217246 | d048ba6c | Lizenzserver | LicenseGuard + KDV |
| **wg.ganz-soft.de** | w0217246 | — | WG-Tool | Experiment |

---

## Ablauf Code

1. Entwickeln/testen auf **`ganz-om.de`** (Dev)  
2. Freigabe → Code auf **`dg.ganz-soft.de`** (Master/Kern)  
3. `bin/sync-crm-from-master.sh` → Kunden-/Firmen-Instanzen (eigene DB bleibt)

> **Übergang:** Ältere Skripte/Docs zeigten noch `dg.ganz-om.de` als Master — Ziel ist **`dg.ganz-soft.de`**.

---

## office.iqstrom.com — IQ Strom

- Hosting: IQ-All-Inkl **`w01f1176`** (`IQ_ALLINKL_*` / `bin/cloud-agent-iq-ssh-setup.sh`)  
- Kundeninstanz: eigener Code-Sync-Pfad vom Kern, eigene DB, echte Daten  
- ToDo: [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) A4  
