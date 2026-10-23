<?php
declare(strict_types=1);

/** JSON-API: Bridge-Events (Fritz CallMonitor) + Popup-Poll + ausgehende Wahl. */
final class CtiApi
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if ($method === 'GET') {
            self::handleGet();
            return;
        }
        if ($method === 'POST') {
            self::handlePost();
            return;
        }
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'GET oder POST'], JSON_UNESCAPED_UNICODE);
    }

    private static function handleGet(): void
    {
        $user = AuthService::user();
        if ($user === null || !MenuRegistry::canAccess($user, 'telefon')) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Nicht angemeldet'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!CtiSettings::isEnabled()) {
            echo json_encode(['ok' => true, 'enabled' => false, 'ringing' => []], JSON_UNESCAPED_UNICODE);
            return;
        }
        $rows = CallLogRepository::activeRinging();
        $ringing = [];
        foreach ($rows as $row) {
            $ringing[] = [
                'id' => (int) ($row['id'] ?? 0),
                'phone' => (string) ($row['phone_e164'] ?? $row['phone_raw'] ?? ''),
                'phone_display' => PhoneNormalizer::formatForDisplay((string) ($row['phone_raw'] ?? $row['phone_e164'] ?? '')),
                'contact_id' => (int) ($row['contact_id'] ?? 0),
                'contact_name' => (string) ($row['contact_name'] ?? ''),
                'started_at' => (string) ($row['started_at'] ?? ''),
            ];
        }
        echo json_encode(['ok' => true, 'enabled' => true, 'ringing' => $ringing], JSON_UNESCAPED_UNICODE);
    }

    private static function handlePost(): void
    {
        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            $json = $_POST;
        }
        $action = (string) ($json['action'] ?? $_GET['action'] ?? 'event');

        if ($action === 'dial') {
            self::handleDial($json);
            return;
        }

        self::handleBridgeEvent($json);
    }

    /** @param array<string, mixed> $json */
    private static function handleDial(array $json): void
    {
        $user = AuthService::user();
        if ($user === null || !MenuRegistry::canAccess($user, 'telefon')) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Nicht angemeldet'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!Csrf::verify($json['_csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'CSRF'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $phone = trim((string) ($json['phone'] ?? ''));
        $href = CtiSettings::dialHref($phone);
        if ($href === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Ungültige Nummer'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $id = CallLogRepository::insert([
            'direction' => 'out',
            'status' => 'dialing',
            'phone_raw' => $phone,
            'user_id' => $user->id,
        ]);
        echo json_encode([
            'ok' => true,
            'call_id' => $id,
            'href' => $href,
            'contact_id' => CtiPhoneLookup::contactIdForPhone($phone),
        ], JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string, mixed> $json */
    private static function handleBridgeEvent(array $json): void
    {
        $cfg = CtiSettings::config();
        $token = trim((string) ($cfg['bridge_token'] ?? ''));
        $given = trim((string) ($_SERVER['HTTP_X_CTI_TOKEN'] ?? $json['token'] ?? ''));
        if ($token === '' || $given === '' || !hash_equals($token, $given)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Token ungültig'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!$cfg['enabled'] || $cfg['provider'] !== 'fritz') {
            http_response_code(503);
            echo json_encode(['ok' => false, 'error' => 'CTI nicht aktiv (Fritz)'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $event = strtolower(trim((string) ($json['event'] ?? '')));
        $externalId = trim((string) ($json['external_id'] ?? ''));
        $phone = trim((string) ($json['phone'] ?? ''));
        $duration = (int) ($json['duration_sec'] ?? 0);

        if ($event === 'ring') {
            if ($phone === '') {
                http_response_code(422);
                echo json_encode(['ok' => false, 'error' => 'phone fehlt'], JSON_UNESCAPED_UNICODE);
                return;
            }
            if ($externalId !== '') {
                $open = CallLogRepository::findOpenByExternal($externalId);
                if ($open !== null) {
                    echo json_encode(['ok' => true, 'call_id' => (int) $open['id'], 'dup' => true], JSON_UNESCAPED_UNICODE);
                    return;
                }
            }
            $direction = ((string) ($json['direction'] ?? 'in')) === 'out' ? 'out' : 'in';
            $id = CallLogRepository::insert([
                'direction' => $direction,
                'status' => $direction === 'out' ? 'dialing' : 'ringing',
                'phone_raw' => $phone,
                'external_id' => $externalId,
            ]);
            echo json_encode(['ok' => true, 'call_id' => $id], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($event === 'connect') {
            $open = $externalId !== '' ? CallLogRepository::findOpenByExternal($externalId) : null;
            if ($open === null && $phone !== '') {
                $id = CallLogRepository::insert([
                    'direction' => ((string) ($json['direction'] ?? 'in')) === 'out' ? 'out' : 'in',
                    'status' => 'answered',
                    'phone_raw' => $phone,
                    'external_id' => $externalId,
                ]);
                CallLogRepository::markAnswered($id);
                echo json_encode(['ok' => true, 'call_id' => $id], JSON_UNESCAPED_UNICODE);
                return;
            }
            if ($open !== null) {
                CallLogRepository::markAnswered((int) $open['id']);
                echo json_encode(['ok' => true, 'call_id' => (int) $open['id']], JSON_UNESCAPED_UNICODE);
                return;
            }
            echo json_encode(['ok' => true, 'ignored' => true], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($event === 'disconnect') {
            $open = $externalId !== '' ? CallLogRepository::findOpenByExternal($externalId) : null;
            if ($open !== null) {
                CallLogRepository::markEnded((int) $open['id'], $duration);
                echo json_encode(['ok' => true, 'call_id' => (int) $open['id']], JSON_UNESCAPED_UNICODE);
                return;
            }
            echo json_encode(['ok' => true, 'ignored' => true], JSON_UNESCAPED_UNICODE);
            return;
        }

        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Unbekanntes event'], JSON_UNESCAPED_UNICODE);
    }
}
