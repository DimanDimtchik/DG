<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

MigrationRunner::runPending();
$pdo = Database::pdo();

echo "KAS configured: " . (KasSettings::isConfigured() ? 'yes' : 'no') . PHP_EOL;

$mailCfg = MailAddressSettings::config();
echo "mail_address: " . json_encode($mailCfg, JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo "effective_domain: " . MailAddressSettings::effectiveDomain() . PHP_EOL;

echo PHP_EOL . "Contacts (ganz):" . PHP_EOL;
$stmt = $pdo->query("SELECT id, first_name, last_name, login, email, email_2, contact_role, created_at FROM dg_contacts WHERE last_name LIKE '%ganz%' OR email LIKE '%ganz%' OR login LIKE '%ganz%' ORDER BY id");
foreach ($stmt ?: [] as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    $contact = ContactRepository::findById((int) $row['id']);
    if ($contact !== null) {
        $eval = MailAddressBuilder::evaluateAutoCreate($contact);
        echo "  evaluateAutoCreate: " . json_encode($eval, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        echo "  preview: " . MailAddressBuilder::preview(MailAddressBuilder::personContextFromContact($contact)) . PHP_EOL;
    }
}

echo PHP_EOL . "Mailboxes:" . PHP_EOL;
foreach ($pdo->query('SELECT id, email_address, contact_id, kas_provisioned, type, owner_user_id FROM dg_mailboxes ORDER BY id') ?: [] as $row) {
    echo json_encode($row) . PHP_EOL;
}
