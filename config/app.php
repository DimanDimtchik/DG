<?php
declare(strict_types=1);

return [
    'name' => 'DG',
    'crm_name' => 'DG',
    'home_url' => 'https://ganz-om.de',
    'session_name' => 'dg_crm_session',
    'timezone' => 'Europe/Berlin',

    // Rollen (kompatibel mit dg-user-plugin)
    'roles' => [
        'admin' => 'administrator',
        'employee' => 'dg_eigenmitarbeiter',
        'customer' => 'dg_kunde',
    ],
    'employee_active_meta' => 'dg_employee_active',

    'database' => require __DIR__ . '/database.php',
];
