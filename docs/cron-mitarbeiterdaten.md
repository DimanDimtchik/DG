# Mitarbeiterdaten: 10 Jahre nach Austritt

Nach dem **Austritt** bleiben Mitarbeiterdaten 10 Jahre gespeichert. Danach:

- **Entfernt:** nur HR-Felder (`employee_data`) und Dokumente
- **Bleibt:** Stamm-/Kontaktdaten wie bei Kunden (Name, Adresse, E-Mail, Bank …)
- **Rolle:** automatisch **Kunde**

## Automatische Bereinigung (Standard)

Beim **CRM-Aufruf** durch **Administrator** oder **Mitarbeiter** (`/app`):

- nur in den **ersten zwei Januarwochen** (**1.–14. Januar**)
- einmal pro Login-Sitzung werden alle fälligen Kontakte bereinigt
- Meldung erscheint, wenn mindestens ein Kontakt bereinigt wurde
- Log: `storage/logs/cron-purge.log`

Außerhalb dieses Zeitraums findet **keine** automatische Bereinigung statt (auch nicht beim Öffnen einzelner Kontakte).

**Hinweis:** Wird das CRM im Januar nicht genutzt, verschiebt sich die Bereinigung auf das nächste Jahr.

## Optional: manueller Aufruf

```bash
php bin/db-purge-expired-employees.php
```

Nur wirksam im Zeitraum **1.–14. Januar** (gleiche Logik wie CRM).

HTTP-Endpunkt `cron.php` ebenfalls — siehe `config/cron.local.php`.
