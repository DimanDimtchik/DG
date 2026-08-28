<?php

declare(strict_types=1);

/** Öffentlicher Wartungsmodus für shop.ganz-soft.de — blockiert alle Shop-Seiten mit HTTP 503. */
final class ShopMaintenance
{
    private const FLAG_FILE = SHOP_ROOT . '/config/.maintenance';
    private const LOCAL_FILE = SHOP_ROOT . '/config/maintenance.local.php';

    /** @var array{enabled: bool, headline: string, message: string, email: string, retry_after: int}|null */
    private static ?array $config = null;

    /**
     * Prüft Wartungsmodus und beendet den Request mit 503, falls aktiv.
     */
    public static function guard(): void
    {
        if (self::isBypassPath(self::currentPath())) {
            return;
        }
        if (!self::isActive()) {
            return;
        }

        self::renderAndExit();
    }

    public static function isActive(): bool
    {
        return !empty(self::config()['enabled']);
    }

    /**
     * @param array{enabled?: mixed, headline?: string, message?: string, email?: string} $post
     */
    public static function save(array $post): void
    {
        $defaults = self::loadDefaults();
        $headline = mb_substr(trim((string) ($post['headline'] ?? $defaults['headline'])), 0, 160);
        $message = mb_substr(trim((string) ($post['message'] ?? $defaults['message'])), 0, 500);
        $email = mb_substr(trim((string) ($post['email'] ?? $defaults['email'])), 0, 191);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Bitte eine gültige E-Mail-Adresse eingeben.');
        }

        $enabled = !empty($post['enabled']);
        $payload = [
            'enabled' => $enabled,
            'headline' => $headline !== '' ? $headline : $defaults['headline'],
            'message' => $message !== '' ? $message : $defaults['message'],
            'email' => $email !== '' ? $email : $defaults['email'],
            'retry_after' => $defaults['retry_after'],
        ];

        self::writeLocalConfig($payload);
        self::syncFlagFile($enabled);
        self::$config = null;
        self::syncPublicMaintenanceHtml($payload);
    }

    /** @return array{enabled: bool, headline: string, message: string, email: string, retry_after: int} */
    public static function config(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $defaults = self::loadDefaults();
        $local = self::LOCAL_FILE;
        if (is_readable($local)) {
            $overrides = require $local;
            if (is_array($overrides)) {
                $defaults = array_merge($defaults, $overrides);
            }
        }

        $defaults['enabled'] = !empty($defaults['enabled']);
        $defaults['headline'] = trim((string) ($defaults['headline'] ?? 'Shop im Aufbau'));
        $defaults['message'] = trim((string) ($defaults['message'] ?? ''));
        $defaults['email'] = trim((string) ($defaults['email'] ?? ''));
        $defaults['retry_after'] = max(300, (int) ($defaults['retry_after'] ?? 3600));

        self::$config = $defaults;

        return $defaults;
    }

    public static function isBypassPath(string $path): bool
    {
        $path = rtrim($path, '/') ?: '/';

        return str_starts_with($path, '/admin')
            || $path === '/maintenance.html';
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

    /** @return array{enabled: bool, headline: string, message: string, email: string, retry_after: int} */
    private static function loadDefaults(): array
    {
        /** @var array{enabled: bool, headline: string, message: string, email: string, retry_after: int} $cfg */
        $cfg = require SHOP_ROOT . '/config/maintenance.php';

        return $cfg;
    }

    /** @param array{enabled: bool, headline: string, message: string, email: string, retry_after: int} $payload */
    private static function writeLocalConfig(array $payload): void
    {
        $export = var_export($payload, true);
        $php = "<?php\n\ndeclare(strict_types=1);\n\n/** Automatisch gespeichert — Shop-Wartungsmodus. */\nreturn {$export};\n";
        if (@file_put_contents(self::LOCAL_FILE, $php, LOCK_EX) === false) {
            throw new RuntimeException('Wartungseinstellungen konnten nicht gespeichert werden.');
        }
    }

    private static function syncFlagFile(bool $enabled): void
    {
        if ($enabled) {
            if (!is_file(self::FLAG_FILE)) {
                @file_put_contents(self::FLAG_FILE, "Shop-Wartungsmodus aktiv.\n", LOCK_EX);
            }
            return;
        }
        if (is_file(self::FLAG_FILE)) {
            @unlink(self::FLAG_FILE);
        }
    }

    /** @param array{headline: string, message: string, email: string} $cfg */
    private static function syncPublicMaintenanceHtml(array $cfg): void
    {
        $headline = htmlspecialchars($cfg['headline'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $message = htmlspecialchars($cfg['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $email = trim((string) ($cfg['email'] ?? ''));
        $emailHtml = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)
            ? '    <p class="shop-maintenance__contact"><a href="mailto:' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</a></p>'
            : '';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>{$headline}</title>
  <style>
    body { margin: 0; font-family: system-ui, sans-serif; background: #f5f3f0; color: #1e293b;
      display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 24px; }
    .shop-maintenance { max-width: 520px; background: #fff; border-radius: 12px;
      padding: 32px 28px; box-shadow: 0 2px 12px rgba(0,0,0,.08); text-align: center; }
    h1 { margin: 0 0 12px; font-size: 1.5rem; }
    p { margin: 0; color: #64748b; line-height: 1.5; }
    .shop-maintenance__contact { margin-top: 16px; }
    a { color: #8b6914; }
  </style>
</head>
<body>
  <div class="shop-maintenance">
    <h1>{$headline}</h1>
    <p>{$message}</p>
{$emailHtml}
  </div>
</body>
</html>

HTML;
        @file_put_contents(SHOP_ROOT . '/maintenance.html', $html, LOCK_EX);
    }

    private static function currentPath(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        return rtrim($path, '/') ?: '/';
    }
}
