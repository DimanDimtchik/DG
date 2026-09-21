<?php
declare(strict_types=1);

/**
 * Multi-Firma MF5 — SSO Shared Secret (Sync-Exclude: config/*.local.php).
 * Auf allen Org-Instanzen identisch setzen. Ohne gültiges Secret (≥32 Zeichen)
 * fällt der Switcher auf Redirect /login ohne Token zurück (MF1).
 */
return [
    'shared_secret' => '', // min. 32 Bytes Zufall, z. B. bin2hex(random_bytes(32))
    'ttl_seconds' => 60,
    'allowed_domains' => [
        // optional leer = nur Sibling-Liste aus KDV / firm-switcher.local.php
        // 'firma-a.example',
        // 'firma-b.example',
    ],
];
