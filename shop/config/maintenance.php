<?php

declare(strict_types=1);

/**
 * Shop-Wartungsmodus — solange der Shop im Ausbau ist, bleibt enabled true.
 * Live-Schaltung: maintenance.local.php mit enabled=false + config/.maintenance löschen.
 */
$defaults = [
    'enabled' => true,
    'headline' => 'Shop im Aufbau',
    'message' => 'Der DG CRM Shop wird derzeit vorbereitet. Bitte schauen Sie in Kürze wieder vorbei.',
    'email' => 'info@ganz-soft.de',
    'retry_after' => 3600,
];

$local = __DIR__ . '/maintenance.local.php';
if (is_readable($local)) {
    $overrides = require $local;
    if (is_array($overrides)) {
        $defaults = array_merge($defaults, $overrides);
    }
}

return $defaults;
