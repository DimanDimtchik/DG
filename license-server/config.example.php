<?php
/**
 * Kopie nach config.php und echte Werte eintragen (nicht in Git).
 */
return [
    'host'        => 'localhost',
    'name'        => 'DATABASE',
    'user'        => 'USER',
    'pass'        => 'PASSWORD',
    'admin_token' => 'CHANGE_ME',
    // Leer = Admin mit Token von jeder IP. Sonst z. B. ['203.0.113.10'].
    'admin_allow_ips' => [],
    // /check: max. Anfragen bzw. Fehlversuche pro IP und Minute.
    'rate_limit_per_minute' => 60,
    'rate_limit_fail_per_minute' => 20,
];
