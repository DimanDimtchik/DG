<?php

declare(strict_types=1);



/**

 * Demo-Benutzer und Standard-Abteilungen in MariaDB importieren.

 * php bin/db-seed.php

 */

require dirname(__DIR__) . '/bootstrap.php';



$pdo = Database::pdo();

$data = require DG_ROOT . '/config/users.php';



$pdo->beginTransaction();



try {

    $pdo->exec('DELETE FROM dg_department_module_access');

    $pdo->exec('DELETE FROM dg_department_members');

    $pdo->exec('DELETE FROM dg_departments');

    $pdo->exec('DELETE FROM dg_users');



    $insertUser = $pdo->prepare(

        'INSERT INTO dg_users (id, username, password_hash, email, display_name, role, employee_active)

         VALUES (:id, :username, :password_hash, :email, :display_name, :role, :employee_active)'

    );



    foreach ($data['users'] as $id => $user) {

        $roles = $user['roles'] ?? ['subscriber'];

        $insertUser->execute([

            'id' => (int) $id,

            'username' => (string) $user['username'],

            'password_hash' => (string) $user['password_hash'],

            'email' => (string) $user['email'],

            'display_name' => (string) $user['display_name'],

            'role' => (string) $roles[0],

            'employee_active' => !empty($user['employee_active']) ? 1 : 0,

        ]);

    }



    $insertDept = $pdo->prepare(

        'INSERT INTO dg_departments (id, name, description, is_hr, allow_contact_delete, sort_order)

         VALUES (:id, :name, :description, :is_hr, :allow_contact_delete, :sort_order)'

    );

    $insertModule = $pdo->prepare(

        'INSERT INTO dg_department_module_access (department_id, module_key, access_level)

         VALUES (:department_id, :module_key, :access_level)'

    );

    $insertMember = $pdo->prepare(
        'INSERT INTO dg_department_members (department_id, contact_id, member_role)
         VALUES (:department_id, :contact_id, :member_role)'
    );

    $membersByDept = DefaultDepartments::membersFromConfigFile();
    $departments = DefaultDepartments::withModulesAndMembers($membersByDept);

    foreach ($departments as $department) {
        $insertDept->execute([
            'id' => (string) $department['id'],
            'name' => (string) $department['name'],
            'description' => (string) $department['description'],
            'is_hr' => !empty($department['is_hr']) ? 1 : 0,
            'allow_contact_delete' => !empty($department['allow_contact_delete']) ? 1 : 0,
            'sort_order' => (int) $department['sort_order'],
        ]);

        foreach ($department['modules'] as $moduleKey => $level) {
            $insertModule->execute([
                'department_id' => (string) $department['id'],
                'module_key' => (string) $moduleKey,
                'access_level' => (string) $level,
            ]);
        }

        foreach ($department['members'] as $member) {
            $userId = (int) ($member['user_id'] ?? 0);
            if ($userId < 1) {
                continue;
            }
            $user = UserRepository::findById($userId);
            if ($user === null) {
                continue;
            }
            $contactId = ContactRepository::findStaffContactIdForUser($user);
            if ($contactId === null || $contactId < 1) {
                continue;
            }
            $insertMember->execute([
                'department_id' => (string) $department['id'],
                'contact_id' => $contactId,
                'member_role' => (string) ($member['role'] ?? 'member'),
            ]);
        }
    }



    $pdo->commit();

    echo count($data['users']) . ' Benutzer und ' . count($departments) . " Abteilungen importiert.\n";

} catch (Throwable $e) {

    $pdo->rollBack();

    throw $e;

}

