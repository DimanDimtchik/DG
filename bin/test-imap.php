<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

echo 'imap extension: ' . (function_exists('imap_open') ? 'yes' : 'no') . PHP_EOL;

$box = MailboxRepository::findById(1);
if ($box === null) {
    fwrite(STDERR, "mailbox 1 not found\n");
    exit(1);
}

echo 'email: ' . ($box['email_address'] ?? '') . PHP_EOL;
echo 'imap_host: ' . ($box['imap_host'] ?? '') . PHP_EOL;
echo 'imap_user: ' . ($box['imap_username'] ?? '') . PHP_EOL;
echo 'pass len: ' . strlen((string) ($box['imap_password'] ?? '')) . PHP_EOL;

$hosts = array_unique([
    (string) ($box['imap_host'] ?? ''),
    'w0217246.kasserver.com',
    'imap.ganz-om.de',
]);

foreach ($hosts as $host) {
    if ($host === '') {
        continue;
    }
    $test = $box;
    $test['imap_host'] = $host;
    $folders = ImapMailboxClient::listFolders($test);
    $count = count($folders);
    $headers = ImapMailboxClient::fetchHeaders($test, 'INBOX', 5);
    echo "host {$host}: folders={$count}, inbox_headers=" . count($headers) . PHP_EOL;
    if ($headers !== []) {
        echo '  first: ' . json_encode($headers[0], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
    if ($count > 0 && count($headers) > 0) {
        break;
    }
}

echo 'mail_log inbox count: ' . count(MailLogRepository::folderMessagesForMailboxes([1], 'INBOX', 50, 1)) . PHP_EOL;
