<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$mailboxId = (int) ($argv[1] ?? 0);
if ($mailboxId <= 0) {
    fwrite(STDERR, "Usage: php bin/provision-mailbox-kas.php <mailbox_id>\n");
    exit(1);
}

try {
    $result = MailboxProvisioner::provisionKasForMailbox($mailboxId);
    echo $result['message'] . ' (' . $result['email'] . ')' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
