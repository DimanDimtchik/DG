<?php

declare(strict_types=1);

/**
 * Standard-Wartungsmodus (Shop im Ausbau: enabled true).
 * Gespeicherte Einstellungen: config/maintenance.local.php (via /admin/wartung).
 */
return [
    'enabled' => true,
    'headline' => 'Shop im Aufbau',
    'message' => 'Der DG CRM Shop wird derzeit vorbereitet. Bitte schauen Sie in Kürze wieder vorbei.',
    'email' => 'info@ganz-soft.de',
    'retry_after' => 3600,
];
