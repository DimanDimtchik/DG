<?php
declare(strict_types=1);

/**
 * Minimal-Bootstrap für ganz-om.de (Platzhalter).
 * CRM-Code vom Master im gleichen Account; Wartungstexte lokal in config/site-maintenance.php.
 */
define('DG_SITE_ROOT', __DIR__);

$candidates = [
    dirname(__DIR__) . '/dg.ganz-om.de',
    dirname(__DIR__, 2),
];

$crmRoot = '';
foreach ($candidates as $candidate) {
    if (is_file($candidate . '/src/autoload.php')) {
        $crmRoot = $candidate;
        break;
    }
}

if ($crmRoot === '') {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Wartungsmodus — CRM-Code nicht gefunden.');
}

define('DG_ROOT', $crmRoot);
require_once DG_ROOT . '/src/autoload.php';
