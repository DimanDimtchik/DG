# Hintergrundjobs ohne KAS-Cron (Autostart)

Auf KAS können Cronjobs nur manuell eingetragen werden. Das CRM nutzt deshalb **Autostart-Klassen**, die bei normalen Seitenaufrufen prüfen, ob ein Job fällig ist.

## Zwei Mechanismen

| Mechanismus | Wo | Wann |
|-------------|-----|------|
| **`runIfDue()`** | `App::boot()` (jeder Request nach `bootstrap.php`) | Kalender-/Tageslogik in der Klasse |
| **`runOnCrmAccess()`** | `index.php` beim Aufruf von `/app` | Nur CRM-Login, oft mit Sitzungs- oder Datumsfenster |

## Bereits implementiert

| Aufgabe | Klasse | Trigger |
|---------|--------|---------|
| Migrationen | `MigrationRunner::runOnCrmAccess()` | CRM `/app`, Benutzer mit Bearbeitungsrecht |
| Tages-Backup | `BackupService::runIfDue()` | Erster Request pro Tag |
| Update-Check | `UpdateChecker::runIfDue()` | Erster **Werktag** im Monat (Tag 1–7, DE-Feiertage) |
| Datei-Integrität | `FileIntegrity::runIfDue()` | Max. alle 24 h |
| Mitarbeiter-Purge (10 J.) | `EmployeeRetentionService::runOnCrmAccess()` | CRM `/app`, nur **1.–14. Januar**, 1× pro Sitzung |
| **Mahnversand auto** | `DunningService::runIfDue()` | Erster Request pro Tag, wenn in Einstellungen aktiv |
| **Zeiterfassung offener Tag** | `TimeClockService::runIfDue()` | Erster Request pro Tag — schließt Vortag ohne Ausstempeln |

## Optional: HTTP-Cron (`cron.php`)

Für feste Uhrzeiten oder Jobs ohne Website-Traffic:

- `cron.php?job=employee-retention&token=…`
- `cron.php?job=dunning-auto&token=…`

Token: `config/cron.local.php` (Vorlage: `config/cron.local.php.example`).

CLI-Alternativen: `bin/db-purge-expired-employees.php`, `bin/run-migrations.php`.

## Logs

| Job | Log |
|-----|-----|
| Mitarbeiter-Purge | `storage/logs/cron-purge.log` |
| Mahn auto | `storage/logs/dunning-auto.log` |
| Zeiterfassung Autoclose | `storage/logs/time-clock-autoclose.log` |

## Hinweis Mahnwesen

Zahlungserinnerung = **Stufe 1** in den Mahnstufen (Einstellungen → Zahlungsbedingungen & Mahnung), kein separater Service.
