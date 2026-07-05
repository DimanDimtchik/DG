<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$box = MailboxRepository::findById(1);
if ($box === null) {
    exit(1);
}
$box['imap_host'] = 'w0217246.kasserver.com';

foreach (ImapMailboxClient::listFolders($box) as $folder) {
    $path = (string) ($folder['path'] ?? '');
    $headers = ImapMailboxClient::fetchHeaders($box, $path, 5);
    echo $path . ' (' . ($folder['label'] ?? '') . '): ' . count($headers) . PHP_EOL;
}
