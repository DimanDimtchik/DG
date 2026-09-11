# LDAP / dg-user — Integration ins DG CRM

Stand: **2026-09-11** · Vorbereitung für Server-Umzug (Hetzner). Auf **All-Inkl Kasserver** ist LDAP-Login **nicht** live nutzbar.

---

## Ziel

Ein zentraler Benutzerstamm (LDAP/OpenLDAP) plus optional WordPress-Plugin **dg-user** für Webseiten-Konten. Das CRM authentifiziert sich als **LDAP-Client** — kein eigener LDAP-Server im CRM-Code.

| Komponente | Rolle |
|------------|--------|
| **LDAP-Server** | Benutzer, Gruppen, Passwörter (extern, z. B. VPS) |
| **DG CRM** | Login `/login`, JIT-Provision in `dg_users`, Rollen-Mapping |
| **WordPress + dg-user** | Frontend-Konten, optional Sync per REST (Plugin aus separatem Chat/Repo) |

---

## Was im CRM bereits vorbereitet ist

| Datei / Ort | Zweck |
|-------------|--------|
| `database/migrations/065_user_auth_source.sql` | Spalten `auth_source`, `auth_external_id` in `dg_users` |
| `config/ldap.local.php.example` | Server-Host, Bind, Filter, Gruppen→Rollen (nicht committen) |
| `src/Auth/LdapAuthenticator.php` | Bind, Suche, Passwort-Prüfung, Profil-Mapping |
| `src/Settings/LdapSettings.php` | Modus local / hybrid / ldap_only, JIT-Flag |
| `src/Auth/AuthService.php` | Login: LDAP zuerst (wenn aktiv), sonst lokal |
| `src/User/UserRepository.php` | `syncFromLdap()` — Update oder JIT-Anlage |
| Einstellungen → Organisation → **Anmeldung / LDAP** | UI + Readiness |
| `bin/ldap-readiness.php` | CLI-Check vor Go-Live |

---

## All-Inkl (jetzt)

- PHP-Extension **`ldap`** fehlt auf Shared Hosting in der Regel.
- Kein eingehender LDAP-Port, kein Docker.
- **Modus bleibt `local`** — Code liegt bereit, schadet nicht.
- Migration **065** kann trotzdem laufen (Spalten sind abwärtskompatibel).

Readiness (erwartetes Ergebnis auf Kasserver):

```bash
php bin/ldap-readiness.php
# → php-ldap OFFEN, Verbindung OFFEN, Exit-Code 1
```

---

## Schritte nach Server-Umzug

### 1. Migration

```bash
php bin/migrate.php
# oder über CRM: Einstellungen → Datenbank
```

### 2. LDAP-Server

- OpenLDAP oder FreeIPA auf Hetzner-VPS (gleiches Netz/VPN wie CRM).
- TLS (`use_tls` oder `ldaps://`).
- Service-Account für Readonly-Suche (`bind_dn` / `bind_password`).
- Gruppen z. B. `crm-admins`, `crm-staff` → Mapping in `group_role_map`.

### 3. CRM-Konfiguration

```bash
cp config/ldap.local.php.example config/ldap.local.php
# host, base_dn, bind_dn, filter anpassen
# enabled => true
```

### 4. CRM-Einstellungen (Admin)

Einstellungen → Organisation → **Anmeldung / LDAP**:

- Modus: zuerst **hybrid** testen, dann optional **ldap_only**.
- JIT: an, wenn neue LDAP-Nutzer automatisch CRM-Konten bekommen sollen.
- Standard-Rolle: `dg_eigenmitarbeiter` (oder per Gruppen-Mapping überschreiben).

### 5. WordPress dg-user (Plugin — separates Repo)

Das Plugin aus dem anderen Chat ist **nicht** in diesem CRM-Repo. Nach Import dort:

- REST-Basis-URL in `wordpress_base_url` (ldap.local.php).
- API-Token nur lokal, nicht in Git.
- Später: Cron/Webhook für Abgleich LDAP ↔ WP ↔ CRM (noch nicht im CRM implementiert).

**Handoff:** Plugin-Quellcode in eigenes Repo `dg-user-wp-plugin` legen oder hier unter `wordpress/dg-user/` committen, wenn gewünscht.

---

## Betriebsmodi

| Modus | Verhalten |
|-------|-----------|
| `local` | Nur CRM-Passwort (`password_hash`) — Standard |
| `hybrid` | LDAP-Login zuerst; bei Fehlschlag lokales Passwort |
| `ldap_only` | Nur Verzeichnis; lokale Passwörter werden ignoriert |

LDAP ist nur aktiv, wenn **gleichzeitig**:

1. Modus ≠ `local` (in DB-Einstellungen),
2. `config/ldap.local.php` mit `enabled = true`,
3. php-ldap geladen und Host erreichbar.

---

## Rollen (dg-user-kompatibel)

| CRM-Rolle | Typisch |
|-----------|---------|
| `administrator` | CRM-Admins |
| `dg_eigenmitarbeiter` | Mitarbeiter (employee_active = 1) |
| `dg_kunde` | Kundenportal |

Gruppen-Mapping in `ldap.local.php` → `group_role_map`.

---

## Sicherheit / Prüfpfad

- Bind-Passwort und WP-Token nur in `ldap.local.php` (gitignored).
- LDAP-User erhalten zufälligen `password_hash` (Login nur über LDAP).
- Login-Fehler weiter über `LoginThrottle` + `AuditLog`.
- JIT nur wenn explizit aktiviert — sonst müssen Konten vorher existieren.

---

## Checkliste Go-Live

- [ ] Migration 065 auf allen CRM-Instanzen
- [ ] `php bin/ldap-readiness.php` → Exit 0
- [ ] Test-User hybrid-Login
- [ ] Admin-Rolle via Gruppe prüfen
- [ ] Fallback lokaler Admin in hybrid bis Cutover
- [ ] WordPress-Plugin deployed und REST erreichbar (optional)
- [ ] Dokumentation in `docs/TODOS.md` aktualisieren

---

## Referenzen

- `docs/SERVER-MIGRATION.md` — Hetzner-Umzug
- `AGENTS.md` — Entwicklung nur Master, Sync auf Instanzen
- ELSTER-Vorbereitung analog: `docs/ELSTER-ERIC-TODO.md`
