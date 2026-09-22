<?php
declare(strict_types=1);

/** JSON-API Router `/api/mobile/*`. */
final class MobileApi
{
    /** @return never */
    public static function handle(string $path): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if (!Database::isConfigured()) {
            self::fail(503, 'Datenbank nicht konfiguriert.', 'db_unavailable');
        }
        MigrationRunner::runPending();

        $suffix = trim(substr($path, strlen('/api/mobile')), '/');
        $parts = $suffix === '' ? [] : explode('/', $suffix);
        $area = $parts[0] ?? '';

        try {
            if ($area === 'auth') {
                MobileAuthApi::handle(array_slice($parts, 1));
            }
            if ($area === 'customer') {
                MobileCustomerApi::handle(array_slice($parts, 1));
            }
            if ($area === 'staff') {
                MobileStaffApi::handle(array_slice($parts, 1));
            }
            self::fail(404, 'Unbekannter Endpunkt.', 'not_found');
        } catch (InvalidArgumentException $e) {
            self::fail(400, $e->getMessage(), 'bad_request');
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            $code = str_contains(strtolower($msg), 'token') || str_contains(strtolower($msg), 'authorization')
                ? 401
                : 403;
            self::fail($code, $msg, $code === 401 ? 'unauthorized' : 'forbidden');
        } catch (Throwable $e) {
            self::fail(500, 'Interner Fehler.', 'server_error');
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return never
     */
    public static function ok(array $data = []): void
    {
        echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** @return never */
    public static function fail(int $http, string $error, string $code): void
    {
        http_response_code($http);
        echo json_encode([
            'ok' => false,
            'error' => $error,
            'code' => $code,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    public static function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return $_POST;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : $_POST;
    }
}
