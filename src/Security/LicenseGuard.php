<?php
declare(strict_types=1);

/**
 * Prüft die CRM-Lizenz gegen den zentralen Lizenzserver (Grace-Period bei Ausfall).
 */
final class LicenseGuard
{
    private const LICENSE_SERVER = 'https://dg-user.ganz-soft.de/check';
    private const STATE_FILE     = '/storage/license_state.json';
    private const CHECK_INTERVAL = 86400; // 24h
    private const GRACE_DAYS     = 7;

    /**
     * Validiert die Lizenz bei Web-Requests; blockiert bei ungültiger Lizenz mit HTTP 503.
     */
    public static function verify(): void
    {
        // CLI tools (updates, migrations, seeds) must not be blocked by license UI.
        if (PHP_SAPI === 'cli') {
            return;
        }

        $state = self::loadState();
        $now   = time();

        if (isset($state['last_check']) && ($now - (int) $state['last_check']) < self::CHECK_INTERVAL) {
            if (($state['valid'] ?? false) === true) {
                return;
            }
            if (self::withinGrace($state)) {
                return;
            }
            self::block();
        }

        $domain     = self::currentDomain();
        $licenseKey = self::licenseKey();

        if ($licenseKey === '') {
            self::block();
        }

        $result = self::checkRemote($domain, $licenseKey);

        // Server/DB-Fehler wie Verbindungsausfall behandeln (Grace-Period)
        if ($result === null || isset($result['error'])) {
            // Server nicht erreichbar – Grace-Period nutzen
            if (($state['valid'] ?? false) === true && self::withinGrace($state)) {
                $state['last_check'] = $now;
                self::saveState($state);
                return;
            }
            if (!isset($state['last_check'])) {
                $state['last_check'] = $now;
                $state['valid'] = true;
                $state['grace_start'] = $now;
                self::saveState($state);
                return;
            }
            if (self::withinGrace($state)) {
                return;
            }
            self::block();
        }

        $state = [
            'valid'       => $result['valid'] ?? false,
            'plan'        => $result['plan'] ?? '',
            'domain'      => $result['domain'] ?? $domain,
            'last_check'  => $now,
            'grace_start' => null,
        ];
        self::saveState($state);

        if (!($result['valid'] ?? false)) {
            self::block();
        }
    }

    /**
     * Liest den Lizenzschlüssel aus config/license.php.
     */
    public static function licenseKey(): string
    {
        $file = DG_ROOT . '/config/license.php';
        if (!is_readable($file)) {
            return '';
        }
        $data = require $file;
        return trim((string) ($data['key'] ?? ''));
    }

    /**
     * Liefert die konfigurierte oder aktuelle HTTP-Host-Domain.
     */
    public static function currentDomain(): string
    {
        $configured = trim((string) App::config('domain', ''));
        if ($configured !== '') {
            return strtolower($configured);
        }
        return strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    }

    /**
     * @return array{valid?: bool, plan?: string, domain?: string, last_check?: int, grace_start?: int|null}
     */
    public static function status(): array
    {
        return self::loadState();
    }

    /**
     * Fragt den Lizenzserver per POST ab.
     *
     * @return array<string, mixed>|null Server-Antwort oder null bei Verbindungsfehler.
     */
    private static function checkRemote(string $domain, string $key): ?array
    {
        $payload = json_encode([
            'domain'      => $domain,
            'license_key' => $key,
            'version'     => App::version(),
        ]);

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 5,
            ],
            'ssl' => [
                'verify_peer' => true,
            ],
        ]);

        $response = @file_get_contents(self::LICENSE_SERVER, false, $ctx);
        if ($response === false) {
            return null;
        }

        return json_decode($response, true) ?: null;
    }

    /**
     * Prüft, ob die Grace-Period nach dem letzten erfolgreichen Check noch läuft.
     *
     * @param array<string, mixed> $state
     */
    private static function withinGrace(array $state): bool
    {
        $graceStart = (int) ($state['grace_start'] ?? $state['last_check'] ?? 0);
        if ($graceStart <= 0) {
            return false;
        }
        return (time() - $graceStart) < (self::GRACE_DAYS * 86400);
    }

    /**
     * Zeigt die Lizenzfehler-Seite und beendet das Skript.
     *
     * @return never
     */
    private static function block(): void
    {
        http_response_code(503);
        echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Lizenzfehler</title>'
            . '<style>body{font-family:system-ui,sans-serif;display:flex;justify-content:center;align-items:center;'
            . 'min-height:100vh;margin:0;background:#f5f3f0;color:#1e293b;}'
            . '.box{text-align:center;padding:40px;background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.08);max-width:500px;}'
            . 'h1{margin:0 0 12px;font-size:1.5rem;}p{color:#64748b;margin:0;}</style></head>'
            . '<body><div class="box"><h1>Lizenzfehler</h1>'
            . '<p>Diese CRM-Installation ist nicht lizenziert oder die Lizenz ist abgelaufen.</p>'
            . '<p style="margin-top:12px;">Bitte kontaktieren Sie Ihren Administrator.</p>'
            . '</div></body></html>';
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadState(): array
    {
        $file = DG_ROOT . self::STATE_FILE;
        if (!is_readable($file)) {
            return [];
        }
        return json_decode((string) file_get_contents($file), true) ?: [];
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function saveState(array $state): void
    {
        $dir = dirname(DG_ROOT . self::STATE_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents(DG_ROOT . self::STATE_FILE, json_encode($state, JSON_PRETTY_PRINT));
    }
}
