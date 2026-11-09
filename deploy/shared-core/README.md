# Shared Core — Deploy-Vorlagen (GEX45)

Spec: [`docs/SHARED-CORE-REST-PLAN.md`](../../docs/SHARED-CORE-REST-PLAN.md) (SC2b).

| Datei | Zweck |
|-------|--------|
| `public-index.php.example` | Nach `…/instances/<fqdn>/public/index.php` kopieren |

Guard: `php bin/shared-core-layout-selftest.php`  
Optional Live: `DG_SHARED_ROOT=… DG_INSTANCE_ROOT=… php bin/shared-core-layout-selftest.php`

Noch **kein** Live-Umschalten (SC3). All-Inkl bleibt Full-Tree.
