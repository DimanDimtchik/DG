<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require dirname(__DIR__) . '/bootstrap.php';

echo "bootstrap ok\n";

foreach ([
    'src/Mail/ImapMailboxClient.php',
    'src/Mail/MailImapCache.php',
    'src/Mail/MailFolderCatalog.php',
    'index.php',
] as $file) {
    $path = DG_ROOT . '/' . $file;
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    echo implode("\n", $out) . "\n";
    $out = [];
}

try {
    MigrationRunner::runPending();
    $box = MailboxRepository::findById(1);
    echo 'mailbox: ' . ($box['email_address'] ?? 'none') . "\n";
    if ($box !== null) {
        $path = MailFolderCatalog::imapPathForView('Sent', $box);
        echo "imap path Sent: {$path}\n";
        $rows = ImapMailboxClient::fetchHeaders($box, $path, 3);
        echo 'headers: ' . count($rows) . "\n";
    }
    echo "DONE\n";
    echo class_exists('MailImapCache') ? "MailImapCache class ok\n" : "MailImapCache MISSING\n";
    MailImapCache::shouldBypass();
    echo "shouldBypass ok\n";
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
