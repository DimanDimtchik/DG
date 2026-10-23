#!/usr/bin/env php
<?php
/**
 * Lokale Bridge: Fritzbox CallMonitor (TCP 1012) → CRM /api/cti
 *
 * Beispiel:
 *   php bin/fritz-callmonitor-bridge.php \
 *     --host=fritz.box \
 *     --crm=https://kontur-cosmetics.de \
 *     --token=GEHEIM_AUS_CRM
 *
 * Voraussetzungen: PC im Fritz-LAN (oder VPN), CallMonitor in Fritz aktiv,
 * Softphone (z. B. Linphone) als IP-Telefon an der Fritzbox.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Nur CLI.\n");
    exit(1);
}

$opts = getopt('', ['host::', 'port::', 'crm:', 'token:', 'help']);
if (isset($opts['help']) || empty($opts['crm']) || empty($opts['token'])) {
    fwrite(STDOUT, "Usage: php fritz-callmonitor-bridge.php --crm=https://… --token=… [--host=fritz.box] [--port=1012]\n");
    exit(isset($opts['help']) ? 0 : 1);
}

$host = (string) ($opts['host'] ?? 'fritz.box');
$port = (int) ($opts['port'] ?? 1012);
$crm = rtrim((string) $opts['crm'], '/');
$token = (string) $opts['token'];
$api = $crm . '/api/cti';

fwrite(STDOUT, "Verbinde CallMonitor {$host}:{$port} → {$api}\n");

while (true) {
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen($host, $port, $errno, $errstr, 10);
    if ($fp === false) {
        fwrite(STDERR, date('c') . " connect fail: {$errstr} ({$errno}) — retry 5s\n");
        sleep(5);
        continue;
    }
    stream_set_timeout($fp, 120);
    fwrite(STDOUT, date('c') . " verbunden\n");
    while (!feof($fp)) {
        $line = fgets($fp, 2048);
        if ($line === false) {
            break;
        }
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $payload = parse_callmonitor_line($line);
        if ($payload === null) {
            continue;
        }
        $ok = post_event($api, $token, $payload);
        fwrite(STDOUT, date('c') . ' ' . $payload['event'] . ' ' . ($payload['phone'] ?? '') . ' → ' . ($ok ? 'ok' : 'fail') . "\n");
    }
    fclose($fp);
    fwrite(STDERR, date('c') . " Verbindung weg — reconnect 3s\n");
    sleep(3);
}

/** @return array{event: string, external_id: string, phone?: string, duration_sec?: int, direction?: string}|null */
function parse_callmonitor_line(string $line): ?array
{
    // dd.mm.yy HH:MM:SS;TYPE;id;...
    $parts = explode(';', $line);
    if (count($parts) < 3) {
        return null;
    }
    $type = strtoupper(trim($parts[1] ?? ''));
    $id = trim($parts[2] ?? '');
    if ($type === 'RING') {
        // RING;id;caller;called
        $caller = trim($parts[3] ?? '');
        if ($caller === '') {
            return null;
        }

        return ['event' => 'ring', 'external_id' => $id, 'phone' => $caller, 'direction' => 'in'];
    }
    if ($type === 'CALL') {
        // CALL;id;extension;called
        $called = trim($parts[4] ?? $parts[3] ?? '');
        if ($called === '') {
            return null;
        }

        return ['event' => 'ring', 'external_id' => $id, 'phone' => $called, 'direction' => 'out'];
    }
    if ($type === 'CONNECT') {
        $num = trim($parts[3] ?? '');

        return ['event' => 'connect', 'external_id' => $id, 'phone' => $num];
    }
    if ($type === 'DISCONNECT') {
        $dur = (int) ($parts[3] ?? 0);

        return ['event' => 'disconnect', 'external_id' => $id, 'duration_sec' => $dur];
    }

    return null;
}

/** @param array<string, mixed> $payload */
function post_event(string $url, string $token, array $payload): bool
{
    $payload['token'] = $token;
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        return false;
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-CTI-Token: ' . $token,
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
        ]);
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $res !== false && $code >= 200 && $code < 300;
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nX-CTI-Token: {$token}\r\n",
            'content' => $body,
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);
    $res = @file_get_contents($url, false, $ctx);

    return $res !== false;
}
