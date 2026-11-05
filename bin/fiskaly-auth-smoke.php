#!/usr/bin/env php
<?php
/**
 * TEST-only: prüft fiskaly SIGN DE v2 Auth (keine Secrets ausgeben).
 *
 * Env: FISKALY_SIGN_DE_API_KEY + FISKALY_SIGN_DE_API_SECRET
 *      (Fallback: FISKALY_API_KEY + FISKALY_API_SECRET)
 */

declare(strict_types=1);

$key = getenv('FISKALY_SIGN_DE_API_KEY') ?: getenv('FISKALY_API_KEY') ?: '';
$secret = getenv('FISKALY_SIGN_DE_API_SECRET') ?: getenv('FISKALY_API_SECRET') ?: '';

if ($key === '' || $secret === '') {
    fwrite(STDERR, "FAIL: API-Key/Secret fehlen in der Umgebung.\n");
    fwrite(STDERR, "Erwartet: FISKALY_SIGN_DE_API_KEY + FISKALY_SIGN_DE_API_SECRET\n");
    fwrite(STDERR, "Cursor: Dashboard → Cloud Agents → Secrets → Runtime Secrets, dann neuen Agent starten.\n");
    exit(2);
}

$url = 'https://kassensichv-middleware.fiskaly.com/api/v2/auth';
$body = json_encode(['api_key' => $key, 'api_secret' => $secret], JSON_THROW_ON_ERROR);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
$http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($response === false) {
    fwrite(STDERR, "FAIL: HTTP-Fehler: {$err}\n");
    exit(1);
}

$data = json_decode($response, true);
if (!is_array($data)) {
    fwrite(STDERR, "FAIL: Ungültige JSON-Antwort (HTTP {$http}).\n");
    exit(1);
}

if ($http !== 200 || empty($data['access_token'])) {
    $msg = $data['message'] ?? $data['error'] ?? 'Unbekannter Fehler';
    fwrite(STDERR, "FAIL: Auth abgelehnt (HTTP {$http}): {$msg}\n");
    fwrite(STDERR, "Prüfen: TEST-Key aus test.hub.fiskaly.com, Secret ohne Leerzeichen/Zeilenumbruch.\n");
    exit(1);
}

$expires = isset($data['access_token_expires_in']) ? (string) $data['access_token_expires_in'] : '?';
echo "OK: fiskaly TEST-Auth erfolgreich (access_token erhalten, expires_in={$expires}s).\n";
exit(0);
