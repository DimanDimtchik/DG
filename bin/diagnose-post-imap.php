<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "=== Post / IMAP Diagnose ===\n\n";

echo 'KAS configured: ' . (KasSettings::isConfigured() ? 'yes' : 'no') . "\n";
echo 'IMAP ext: ' . (function_exists('imap_open') ? 'yes' : 'no') . "\n\n";

foreach (MailboxRepository::allForAdmin() as $box) {
    $id = (int) ($box['id'] ?? 0);
    echo "--- Mailbox #{$id} {$box['email_address']} ---\n";
    echo '  kas_provisioned: ' . ($box['kas_provisioned'] ?? 0) . "\n";
    echo '  imap_host: ' . ($box['imap_host'] ?? '') . "\n";
    echo '  imap_user: ' . ($box['imap_username'] ?? '') . "\n";
    echo '  pass_len: ' . strlen((string) ($box['imap_password'] ?? '')) . "\n";

    if (!ImapMailboxClient::hasCredentials($box)) {
        echo "  SKIP: no IMAP credentials\n\n";
        continue;
    }

    $lastError = '';
    $headers = [];
    try {
        if (function_exists('imap_timeout')) {
            imap_timeout(IMAP_OPENTIMEOUT, 8);
            imap_timeout(IMAP_READTIMEOUT, 15);
        }
        $headers = ImapMailboxClient::fetchHeaders($box, 'INBOX', 10);
        $lastError = (string) imap_last_error();
    } catch (Throwable $e) {
        echo '  IMAP ERROR: ' . $e->getMessage() . "\n";
    }

    echo '  imap_last_error: ' . ($lastError !== '' ? $lastError : '(none)') . "\n";
    echo '  INBOX headers: ' . count($headers) . "\n";

    foreach (['INBOX', 'Sent', 'Junk'] as $folder) {
        $c = count(ImapMailboxClient::fetchHeaders($box, $folder, 5));
        echo "  folder {$folder}: {$c}\n";
    }

    try {
        $synced = MailImapSync::syncFolder($box, 'INBOX', true);
        echo "  sync INBOX: {$synced} new\n";
    } catch (Throwable $e) {
        echo '  sync ERROR: ' . $e->getMessage() . "\n";
    }

    $dbRows = MailLogRepository::folderMessagesForMailboxes([$id], 'INBOX', 20, $id);
    echo '  DB INBOX rows: ' . count($dbRows) . "\n\n";
}
