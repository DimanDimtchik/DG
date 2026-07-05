<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$box = MailboxRepository::findById(1);
$box['imap_host'] = 'w0217246.kasserver.com';
$conn = (new ReflectionClass(ImapMailboxClient::class))->getMethod('open');
// can't call private - duplicate open logic
$host = $box['imap_host'];
$user = $box['imap_username'];
$pass = $box['imap_password'];
$port = 993;
$mb = '{' . $host . ':' . $port . '/imap/ssl/novalidate-cert}';
$c = @imap_open($mb . 'INBOX', $user, $pass, 0, 1);
if ($c === false) {
    echo 'INBOX open failed: ' . imap_last_error() . PHP_EOL;
} else {
    echo 'INBOX messages: ' . imap_num_msg($c) . PHP_EOL;
    imap_close($c);
}

$raw = @imap_open($mb, $user, $pass, 0, 1);
if ($raw === false) {
    echo 'server open failed: ' . imap_last_error() . PHP_EOL;
    exit(1);
}
$list = imap_list($raw, $mb, '*') ?: [];
foreach ($list as $f) {
    $path = str_starts_with($f, $mb) ? substr($f, strlen($mb)) : $f;
    $sub = @imap_open($mb . $path, $user, $pass, 0, 1);
    $n = $sub !== false ? imap_num_msg($sub) : -1;
    if ($sub !== false) {
        imap_close($sub);
    }
    echo $path . ' => ' . $n . ' msgs' . PHP_EOL;
}
imap_close($raw);
