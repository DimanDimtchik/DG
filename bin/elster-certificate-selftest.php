<?php
declare(strict_types=1);

/**
 * Selftest: ELSTER-Zertifikat-Passwortregeln + Reminder-Empfängerlogik (ohne Mailversand).
 * php bin/elster-certificate-selftest.php
 */
require dirname(__DIR__) . '/bootstrap.php';

$fail = 0;

function expect_true(bool $cond, string $msg): void
{
    global $fail;
    if ($cond) {
        echo "OK  {$msg}\n";
    } else {
        echo "FAIL {$msg}\n";
        $fail++;
    }
}

expect_true(!ElsterCertificatePassword::isValid('short1'), 'zu kurz abgelehnt');
expect_true(!ElsterCertificatePassword::isValid('abcdefghij'), 'ohne Ziffer abgelehnt');
expect_true(!ElsterCertificatePassword::isValid('1234567890'), 'ohne Buchstabe abgelehnt');
expect_true(!ElsterCertificatePassword::isValid("abc1234567ä"), 'Umlaut abgelehnt');
expect_true(ElsterCertificatePassword::isValid('Abcdefgh12'), 'Buchstaben+Ziffern ok');
expect_true(ElsterCertificatePassword::isValid('Abcdefgh1!'), 'mit Sonderzeichen ok');
expect_true(ElsterCertificatePassword::isValid('Ab12!"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~ '), 'ELSTER-Sonderzeichensatz ok');

expect_true(ElsterCertificateStorage::LOGIN_URL !== '', 'Login-URL gesetzt');
expect_true(ElsterCertificateStorage::daysUntilExpiry('1970-01-01') < 0, 'Ablauf in der Vergangenheit');
expect_true(ElsterCertificateStorage::expiryStatus(date('Y-m-d', strtotime('+100 days'))) === 'ok', 'Status ok weit in Zukunft');

echo "\nEmpfänger-Vorschau (Instanz):\n";
$buch = ElsterCertificateReminderService::emailsForBuchhaltung();
$gf = ElsterCertificateReminderService::emailsForGeschaeftsfuehrung();
$recv = ElsterCertificateReminderService::reminderRecipients();
echo '  Buchhaltung: ' . ($buch !== [] ? implode(', ', $buch) : '—') . "\n";
echo '  GF: ' . ($gf !== [] ? implode(', ', $gf) : '—') . "\n";
echo '  Effektiv: ' . ($recv !== [] ? implode(', ', $recv) : '—') . "\n";
if ($buch !== []) {
    expect_true($recv === $buch, 'bei Buchhaltung nur Buchhaltung (kein GF)');
} else {
    expect_true($recv === $gf || $recv !== [], 'ohne Buchhaltung Fallback GF/Firma');
}

exit($fail > 0 ? 1 : 0);
