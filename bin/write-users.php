<?php
$hash = password_hash('Globus01+', PASSWORD_DEFAULT);
$content = '<?php
return [
    \'users\' => [
        1 => [
            \'id\' => 1,
            \'username\' => \'admin\',
            \'display_name\' => \'Administrator\',
            \'email\' => \'\',
            \'password_hash\' => \'' . $hash . '\',
            \'roles\' => [\'administrator\'],
            \'employee_active\' => false,
        ],
        2 => [
            \'id\' => 2,
            \'username\' => \'info@ganz-om.de\',
            \'display_name\' => \'Dietrich Ganz\',
            \'email\' => \'info@ganz-om.de\',
            \'password_hash\' => \'' . $hash . '\',
            \'roles\' => [\'administrator\'],
            \'employee_active\' => false,
        ],
    ],
    \'departments\' => [],
    \'department_members\' => [],
];
';
file_put_contents(dirname(__DIR__) . '/config/users.php', $content);
echo "Written with hash: $hash\n";
