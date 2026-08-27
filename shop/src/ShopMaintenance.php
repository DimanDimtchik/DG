<?php

declare(strict_types=1);

/** Öffentlicher Wartungsmodus für shop.ganz-soft.de — blockiert alle Shop-Seiten mit HTTP 503. */
final class ShopMaintenance
{
    private const FLAG_FILE = SHOP_ROOT . '/config/.maintenance';

    /** @var array{enabled: bool, headline: string, message: string, email: string, retry_after: int}|null */
    private static ?array $config = null;

    /**
     * Prüft Wartungsmodus und beendet den Request mit 503, falls aktiv.
     */
    public static function guard(): void
    {
        if (!self::isActive()) {
            return;
        }

        self::renderAndExit();
    }

    public static function isActive(): bool
    {
        $cfg = self::config();

        return !empty($cfg['enabled']) || is_file(self::FLAG_FILE);
    }

    /** @return array{enabled: bool, headline: string, message: string, email: string, retry_after: int} */
    public static function config(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        /** @var array{enabled: bool, headline: string, message: string, email: string, retry_after: int} $cfg */
        $cfg = require SHOP_ROOT . '/config/maintenance.php';
        $cfg['enabled'] = !empty($cfg['enabled']);
        $cfg['headline'] = trim((string) ($cfg['headline'] ?? 'Shop im Aufbau'));
        $cfg['message'] = trim((string) ($cfg['message'] ?? ''));
        $cfg['email'] = trim((string) ($cfg['email'] ?? ''));
        $cfg['retry_after'] = max(300, (int) ($cfg['retry_after'] ?? 3600));

        self::$config = $cfg;

        return $cfg;
    }

    /** @return never */
    public static function renderAndExit(): void
    {
        $cfg = self::config();
        http_response_code(503);
        header('Retry-After: ' . (string) $cfg['retry_after']);
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Content-Type: text/html; charset=utf-8');

        $headline = htmlspecialchars($cfg['headline'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $message = htmlspecialchars($cfg['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $email = $cfg['email'];
        $emailHtml = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)
            ? '<p class="shop-maintenance__contact"><a href="mailto:' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</a></p>'
            : '';

        echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex, nofollow">'
            . '<title>' . $headline . '</title>'
            . '<style>'
            . 'body{margin:0;font-family:system-ui,sans-serif;background:#f5f3f0;color:#1e293b;'
            . 'display:flex;min-height:100vh;align-items:center;justify-content:center;padding:24px;}'
            . '.shop-maintenance{max-width:520px;background:#fff;border-radius:12px;'
            . 'padding:32px 28px;box-shadow:0 2px 12px rgba(0,0,0,.08);text-align:center;}'
            . 'h1{margin:0 0 12px;font-size:1.5rem;}'
            . 'p{margin:0;color:#64748b;line-height:1.5;}'
            . '.shop-maintenance__contact{margin-top:16px;}'
            . 'a{color:#8b6914;}'
            . '</style></head><body><div class="shop-maintenance">'
            . '<h1>' . $headline . '</h1>'
            . '<p>' . $message . '</p>'
            . $emailHtml
            . '</div></body></html>';
        exit;
    }
}
