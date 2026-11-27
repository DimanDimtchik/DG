<?php
declare(strict_types=1);

/**
 * MA1d: Netzwerk-/Offline-Hinweis in beiden Flutter-Apps (kein Offline-Modus).
 *
 * Usage: php bin/mobile-api-network-selftest.php
 */

function assertMa1d(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$kalMsg = $root . '/apps/kalender/lib/api_message.dart';
$maMsg = $root . '/apps/mitarbeiter/lib/api_message.dart';
$kalAuth = $root . '/apps/kalender/lib/api_auth.dart';
$maAuth = $root . '/apps/mitarbeiter/lib/api_auth.dart';

assertMa1d(
    hash_file('sha256', $kalMsg) === hash_file('sha256', $maMsg),
    'api_message.dart byte-identisch'
);
assertMa1d(
    hash_file('sha256', $kalAuth) === hash_file('sha256', $maAuth),
    'api_auth.dart byte-identisch'
);

$msg = (string) file_get_contents($kalMsg);
$auth = (string) file_get_contents($kalAuth);

assertMa1d(str_contains($msg, 'apiNetworkErrorMessage'), 'apiNetworkErrorMessage');
assertMa1d(str_contains($msg, 'apiIsNetworkException'), 'apiIsNetworkException');
assertMa1d(str_contains($msg, 'apiNetworkFailBody'), 'apiNetworkFailBody');
assertMa1d(str_contains($msg, "'network_error'"), 'code network_error');
assertMa1d(str_contains($msg, 'Keine Verbindung zum Server'), 'menschliche Netz-Meldung');
assertMa1d(str_contains($msg, 'socketexception'), 'SocketException erkannt');
assertMa1d(str_contains($msg, 'failed host lookup'), 'DNS-Fehler erkannt');

assertMa1d(str_contains($auth, 'apiNetworkFailBody()'), 'Refresh-Pfad Netz-Fail-Map');
assertMa1d(str_contains($auth, 'MobileRefreshOutcome'), 'Refresh-Outcome enum');
assertMa1d(str_contains($auth, 'MobileRefreshOutcome.network'), 'Outcome network');
assertMa1d(
    str_contains($auth, 'MA1d') || str_contains($auth, 'network_error'),
    'MA1d in api_auth dokumentiert'
);

$maMain = (string) file_get_contents($root . '/apps/mitarbeiter/lib/main.dart');
assertMa1d(str_contains($maMain, 'apiNetworkErrorMessage'), 'mitarbeiter Binary Netzhinweis');
assertMa1d(str_contains($maMain, 'MobileRefreshOutcome.network'), 'mitarbeiter Binary Outcome');

echo "MA1d Selftest bestanden.\n";
exit(0);
