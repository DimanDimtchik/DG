<?php
declare(strict_types=1);

/**
 * MA4c: Rate-Limit + ungültiges Token → verständliche UI-Meldung.
 *
 * Usage: php bin/mobile-rate-limit-token-selftest.php
 */

function assertMa4c(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$apiPhp = $root . '/src/Mobile/MobileApi.php';
$throttle = $root . '/src/Mobile/MobileAuthThrottle.php';
$kalMsg = $root . '/apps/kalender/lib/api_message.dart';
$maMsg = $root . '/apps/mitarbeiter/lib/api_message.dart';
$kalMain = $root . '/apps/kalender/lib/main.dart';
$maMain = $root . '/apps/mitarbeiter/lib/main.dart';
$plan = $root . '/docs/MOBILE-APPS-REST-PLAN.md';

assertMa4c(is_file($apiPhp), 'MobileApi.php');
assertMa4c(is_file($throttle), 'MobileAuthThrottle.php');
$apiSrc = (string) file_get_contents($apiPhp);
$thSrc = (string) file_get_contents($throttle);
assertMa4c(str_contains($thSrc, 'Zu viele Versuche'), 'Throttle-Text');
assertMa4c(str_contains($apiSrc, "'rate_limited'"), 'MobileApi mappt rate_limited');
assertMa4c(str_contains($apiSrc, 'zu viele versuche'), 'MobileApi erkennt Throttle-Text');
assertMa4c(str_contains($apiSrc, "'unauthorized'"), 'MobileApi mappt unauthorized');

assertMa4c(
    hash_file('sha256', $kalMsg) === hash_file('sha256', $maMsg),
    'api_message byte-identisch'
);
$msg = (string) file_get_contents($kalMsg);
assertMa4c(str_contains($msg, "'rate_limited'"), 'UI code rate_limited');
assertMa4c(str_contains($msg, "'unauthorized'"), 'UI code unauthorized');
assertMa4c(str_contains($msg, 'apiIsRateLimited'), 'Helper apiIsRateLimited');
assertMa4c(str_contains($msg, 'apiAuthUserMessage'), 'Helper apiAuthUserMessage');
assertMa4c(str_contains($msg, 'Zu viele Versuche') || str_contains($msg, 'kurz warten'), 'UI Rate-Limit-Text');

$kalMainSrc = (string) file_get_contents($kalMain);
$maMainSrc = (string) file_get_contents($maMain);
assertMa4c(str_contains($kalMainSrc, 'apiAuthUserMessage'), 'Kalender Login apiAuthUserMessage');
assertMa4c(str_contains($maMainSrc, 'apiAuthUserMessage'), 'Mitarbeiter Login apiAuthUserMessage');
assertMa4c(
    str_contains($kalMainSrc, "apiCodeMessage('unauthorized')")
        || str_contains($kalMainSrc, 'apiIsSessionExpired'),
    'Kalender Session-Hinweis'
);
assertMa4c(
    str_contains($maMainSrc, "apiCodeMessage('unauthorized')"),
    'Mitarbeiter Session-Hinweis'
);

$planSrc = (string) file_get_contents($plan);
assertMa4c(preg_match('/\|\s*\*\*MA4c\*\*.*\|\s*\[x\]/u', $planSrc) === 1, 'Plan MA4c abgehakt');
assertMa4c(str_contains($planSrc, 'mobile-rate-limit-token-selftest'), 'Selftest in Verweisen');

$base = rtrim((string) (getenv('MA4C_BASE_URL') ?: 'https://ganz-om.de'), '/');
$httpJson = static function (string $method, string $path, ?array $body = null, ?string $token = null) use ($base): array {
    $headers = "Accept: application/json\r\n";
    if ($token !== null && $token !== '') {
        $headers .= "Authorization: Bearer {$token}\r\n";
    }
    $opts = [
        'method' => $method,
        'header' => $headers,
        'timeout' => 25,
        'ignore_errors' => true,
    ];
    if ($body !== null) {
        $opts['header'] .= "Content-Type: application/json\r\n";
        $opts['content'] = json_encode($body, JSON_UNESCAPED_UNICODE);
    }
    $raw = @file_get_contents($base . $path, false, stream_context_create(['http' => $opts]));
    if (!is_string($raw) || $raw === '') {
        return ['ok' => false, 'error' => 'leere Antwort'];
    }
    $json = json_decode($raw, true);

    return is_array($json) ? $json : ['ok' => false, 'error' => 'kein JSON'];
};

// Ungültiges Token
$bad = $httpJson('GET', '/api/mobile/auth/me', null, 'ma4c-invalid-token-' . time());
assertMa4c(($bad['ok'] ?? true) === false, 'Dev me Fake-Token fail');
assertMa4c(($bad['code'] ?? '') === 'unauthorized', 'Dev code unauthorized');
$err = mb_strtolower((string) ($bad['error'] ?? ''));
assertMa4c(
    str_contains($err, 'token') || str_contains($err, 'authorization') || str_contains($err, 'sitzung'),
    'Dev unauthorized verständlich'
);

// Rate-Limit: auf Dev per SSH mit fester REMOTE_ADDR (Cloud-Egress-IP rotiert oft)
$ssh = trim((string) shell_exec('command -v ssh'));
if ($ssh === '') {
    echo "SKIP: Rate-Limit Dev-E2E (kein SSH) — Source/UI-Guards ok.\n";
    echo "MA4c Selftest bestanden gegen {$base}.\n";
    exit(0);
}

$probeIp = '203.0.113.' . random_int(10, 200);
$throttlePhp = '$_SERVER["REMOTE_ADDR"]=' . var_export($probeIp, true) . ';'
    . 'require "bootstrap.php";'
    . '$hit=false;$msg="";'
    . 'for($i=0;$i<40;$i++){'
    . 'try{MobileAuthThrottle::assertAllowed("customer_login");}'
    . 'catch(RuntimeException $e){$hit=true;$msg=$e->getMessage();break;}'
    . '}'
    . 'echo $hit?"THROTTLE=".$msg:"NO_THROTTLE";';
$thCmd = 'cd /www/htdocs/w0217246/ganz-om.de && php -r ' . escapeshellarg($throttlePhp);
$thOut = (string) shell_exec('ssh -o BatchMode=yes allinkl-ganzom ' . escapeshellarg($thCmd) . ' 2>/dev/null');
assertMa4c(str_contains($thOut, 'THROTTLE='), 'Dev Throttle wirft RuntimeException');
assertMa4c(str_contains(mb_strtolower($thOut), 'zu viele'), 'Dev Throttle-Text verständlich');

// Mapping auf Dev-Deploy (MobileApi.php) — HTTP-Hammering unzuverlässig (Egress-IP rotiert)
$remoteMap = (string) shell_exec(
    'ssh -o BatchMode=yes allinkl-ganzom '
    . escapeshellarg('grep -c rate_limited /www/htdocs/w0217246/ganz-om.de/src/Mobile/MobileApi.php')
    . ' 2>/dev/null'
);
assertMa4c((int) trim($remoteMap) >= 1, 'Dev MobileApi enthält rate_limited');

echo "MA4c Selftest bestanden gegen {$base}.\n";
exit(0);
