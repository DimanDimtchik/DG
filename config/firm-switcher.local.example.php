<?php
declare(strict_types=1);

/**
 * Multi-Firma MF1 — manuelle Sibling-Liste für Kundeninstanzen ohne KDV-DB.
 * Datei nach config/firm-switcher.local.php kopieren (wird vom CRM-Sync ausgeschlossen).
 *
 * Auf dem Master reicht i. d. R. die KDV-Org-Verknüpfung (Phase 0) — dann keine local-Datei nötig.
 */
return [
    'firms' => [
        // ['domain' => 'firma-a.example', 'company_name' => 'Firma A GmbH', 'status' => 'active', 'relation' => 'standalone'],
        // ['domain' => 'firma-b.example', 'company_name' => 'Firma B UG', 'status' => 'active', 'relation' => 'schwester'],
    ],
];
