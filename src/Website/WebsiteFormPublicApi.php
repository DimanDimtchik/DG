<?php

declare(strict_types=1);

/**
 * Public JSON helpers for website forms (booking-code lookup).
 */
final class WebsiteFormPublicApi
{
    private const RATE_MAX = 20;
    private const RATE_WINDOW = 600; // 10 minutes

    /**
     * GET /api/website-form/appointments?code=
     * @return never
     */
    public static function appointments(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Nur GET']);
            exit;
        }

        $ip = self::clientIp();
        if (!self::allowRate($ip)) {
            http_response_code(429);
            echo json_encode(['ok' => false, 'error' => 'Zu viele Anfragen. Bitte kurz warten.', 'items' => []]);
            exit;
        }

        $code = BookingCode::normalize((string) ($_GET['code'] ?? ''));
        if ($code === '') {
            echo json_encode(['ok' => true, 'items' => [], 'hint' => 'code_required']);
            exit;
        }

        if (!Database::isConfigured()) {
            echo json_encode(['ok' => true, 'items' => []]);
            exit;
        }

        MigrationRunner::runPending();
        $booking = BookingRepository::findByCode($code);
        if ($booking === null || strtolower($booking->status) === 'storniert') {
            // Einheitliche leere Antwort — kein Enumerieren gültiger Codes
            echo json_encode(['ok' => true, 'items' => [], 'found' => false]);
            exit;
        }

        $articleTitle = '';
        if ($booking->articleId > 0) {
            $art = CalendarArticleRepository::findById($booking->articleId);
            $articleTitle = is_array($art) ? (string) ($art['title'] ?? '') : '';
        }
        $label = $booking->publicCode() . ' · ' . $booking->slotFormatted();
        if ($articleTitle !== '') {
            $label .= ' – ' . $articleTitle;
        }
        $label .= ' (' . $booking->statusLabel() . ')';

        echo json_encode([
            'ok' => true,
            'found' => true,
            'items' => [[
                'id' => $booking->publicCode(),
                'code' => $booking->publicCode(),
                'label' => $label,
                'slot' => $booking->slotDatetime,
            ]],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function clientIp(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        return preg_replace('/[^0-9a-fA-F:.\-]/', '', $ip) ?: '0.0.0.0';
    }

    private static function allowRate(string $ip): bool
    {
        $dir = DG_ROOT . '/storage/cache';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return true;
        }
        $file = $dir . '/ws-appt-rate.json';
        $now = time();
        $data = [];
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        $hits = [];
        foreach ((array) ($data[$ip] ?? []) as $ts) {
            $t = (int) $ts;
            if ($t >= $now - self::RATE_WINDOW) {
                $hits[] = $t;
            }
        }
        if (count($hits) >= self::RATE_MAX) {
            return false;
        }
        $hits[] = $now;
        $data[$ip] = $hits;
        foreach ($data as $k => $list) {
            if (!is_array($list)) {
                unset($data[$k]);
                continue;
            }
            $keep = array_values(array_filter($list, static fn ($t) => (int) $t >= $now - self::RATE_WINDOW));
            if ($keep === []) {
                unset($data[$k]);
            } else {
                $data[$k] = $keep;
            }
        }
        @file_put_contents($file, json_encode($data), LOCK_EX);

        return true;
    }
}
