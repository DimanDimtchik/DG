<?php
declare(strict_types=1);

/**
 * Entwicklungs-Benutzer bis Anbindung an dg-user / WordPress-Datenbank.
 * Passwort für alle Demo-Accounts: demo
 */
$demoPassword = password_hash('demo', PASSWORD_DEFAULT);

return [
    /** Demo-Zuordnung Mitarbeiter → Abteilung (nur Entwicklung / db-seed.php). */
    'department_members' => [
        'dept-verwaltung' => [
            ['user_id' => 2, 'role' => 'leader'],
            ['user_id' => 3, 'role' => 'member'],
        ],
        'dept-buchhaltung' => [
            ['user_id' => 3, 'role' => 'member'],
        ],
    ],
    'users' => [
        1 => [
            'id' => 1,
            'username' => 'admin',
            'display_name' => 'Dietrich Ganz',
            'email' => 'admin@ganz-om.de',
            'password_hash' => $demoPassword,
            'roles' => ['administrator'],
            'employee_active' => false,
        ],
        2 => [
            'id' => 2,
            'username' => 'leiter',
            'display_name' => 'Anna Leiterin',
            'email' => 'leiter@ganz-om.de',
            'password_hash' => $demoPassword,
            'roles' => ['dg_eigenmitarbeiter'],
            'employee_active' => true,
        ],
        3 => [
            'id' => 3,
            'username' => 'mitarbeiter',
            'display_name' => 'Max Mitarbeiter',
            'email' => 'mitarbeiter@ganz-om.de',
            'password_hash' => $demoPassword,
            'roles' => ['dg_eigenmitarbeiter'],
            'employee_active' => true,
        ],
        4 => [
            'id' => 4,
            'username' => 'ohne_abteilung',
            'display_name' => 'Lisa Neu',
            'email' => 'neu@ganz-om.de',
            'password_hash' => $demoPassword,
            'roles' => ['dg_eigenmitarbeiter'],
            'employee_active' => true,
        ],
        5 => [
            'id' => 5,
            'username' => 'gast',
            'display_name' => 'Gast Benutzer',
            'email' => 'gast@ganz-om.de',
            'password_hash' => $demoPassword,
            'roles' => ['subscriber'],
            'employee_active' => false,
        ],
        6 => [
            'id' => 6,
            'username' => 'kunde',
            'display_name' => 'Karl Kunde',
            'email' => 'kunde@example.de',
            'password_hash' => $demoPassword,
            'roles' => ['dg_kunde'],
            'employee_active' => false,
        ],
    ],
];
