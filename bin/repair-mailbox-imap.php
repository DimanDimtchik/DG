<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$mailboxId = (int) ($argv[1] ?? 0);
if ($mailboxId <= 0) {
    fwrite(STDERR, "Usage: php bin/repair-mailbox-imap.php <mailbox_id>\n");
    exit(1);
}

try {
    $result = MailboxProvisioner::repairKasImapPassword($mailboxId);
    echo $result['message'] . ' — ' . $result['email'] . PHP_EOL;

    $mailbox = MailboxRepository::findById($mailboxId);
    if ($mailbox !== null) {
        ImapMailboxClient::releaseConnections();
        $ok = ImapMailboxClient::probeInbox($mailbox);
        echo 'IMAP probe: ' . ($ok ? 'OK' : 'FAILED') . PHP_EOL;
        if (!$ok) {
            echo 'imap_error: ' . ImapMailboxClient::lastError() . PHP_EOL;
            exit(2);
        }
        $headers = ImapMailboxClient::fetchHeaders($mailbox, 'INBOX', 5);
        echo 'INBOX headers: ' . count($headers) . PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
