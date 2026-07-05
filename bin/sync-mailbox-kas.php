<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$mailboxId = (int) ($argv[1] ?? 0);
if ($mailboxId <= 0) {
    fwrite(STDERR, "Usage: php bin/sync-mailbox-kas.php <mailbox_id>\n");
    exit(1);
}

$mailbox = MailboxRepository::findById($mailboxId);
if ($mailbox === null) {
    fwrite(STDERR, "Mailbox not found\n");
    exit(1);
}

$email = strtolower(trim((string) ($mailbox['email_address'] ?? '')));
[$local, $domain] = explode('@', $email, 2);
$kasRow = KasMailProvisioner::findMailAccountByEmail($email);
if ($kasRow === null) {
    fwrite(STDERR, "KAS account not found for {$email}\n");
    exit(1);
}

$kasLogin = trim((string) ($kasRow['mail_login'] ?? ''));
$imapPassword = trim((string) ($kasRow['mail_password'] ?? ''));
if ($imapPassword === '') {
    if ($kasLogin === '') {
        fwrite(STDERR, "KAS mail_login missing for {$email}\n");
        exit(1);
    }
    echo "KAS returned no password — resetting via API …\n";
    $imapPassword = KasMailProvisioner::resetMailboxPassword($kasLogin);
}

$imapUser = $local . '##' . $domain;
$kasHost = KasMailProvisioner::kasServerHost();

$testBox = array_merge($mailbox, [
    'imap_host' => $kasHost,
    'imap_username' => $imapUser,
    'imap_password' => $imapPassword,
]);
ImapMailboxClient::releaseConnections();
if (!ImapMailboxClient::probeInbox($testBox)) {
    $candidates = array_values(array_unique(array_filter([
        $kasLogin,
        $kasLogin !== '' ? $kasLogin . '##' . $domain : '',
        $email,
    ])));
    foreach ($candidates as $candidate) {
        $testBox['imap_username'] = $candidate;
        ImapMailboxClient::releaseConnections();
        if (ImapMailboxClient::probeInbox($testBox)) {
            $imapUser = $candidate;
            break;
        }
    }
}

MailboxRepository::save([
    'type' => (string) ($mailbox['type'] ?? 'private'),
    'name' => (string) ($mailbox['name'] ?? $email),
    'email_address' => $email,
    'owner_user_id' => (int) ($mailbox['owner_user_id'] ?? 0),
    'contact_id' => (int) ($mailbox['contact_id'] ?? 0),
    'kas_mail_login' => $kasLogin !== '' ? $kasLogin : $imapUser,
    'kas_provisioned' => true,
    'provider_preset' => 'kasserver',
    'imap_host' => $kasHost,
    'imap_port' => 993,
    'imap_encryption' => 'ssl',
    'imap_username' => $imapUser,
    'imap_password' => $imapPassword,
    'smtp_host' => $kasHost,
    'smtp_port' => 587,
    'smtp_encryption' => 'tls',
    'smtp_username' => $imapUser,
    'smtp_password' => $imapPassword,
    'from_name' => (string) ($mailbox['from_name'] ?? ''),
    'inbound_webhook_token' => (string) ($mailbox['inbound_webhook_token'] ?? ''),
    'is_active' => !empty($mailbox['is_active']),
], $mailboxId);

echo "Synced {$email} (KAS login {$kasLogin}, IMAP user {$imapUser})" . PHP_EOL;
