<?php

declare(strict_types=1);

/**
 * JSON-API Text-Assist Dokumentenverfasser (D4).
 */
final class WriterAssistApi
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $user = AuthService::user();
        if ($user === null || !MenuRegistry::canAccess($user, 'dokumentenverfasser')) {
            self::error('Keine Berechtigung.', 403);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::error('Nur POST erlaubt.', 405);
        }

        $csrf = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Csrf::verify(is_string($csrf) ? $csrf : null)) {
            self::error('Ungültiges Formular (CSRF).', 403);
        }

        $action = (string) ($_POST['action'] ?? '');
        $text = (string) ($_POST['text'] ?? '');
        $cfg = WriterAssistSettings::config();

        try {
            match ($action) {
                'klartext' => self::klartext($text, $cfg),
                'languagetool' => self::languageTool($text, $cfg),
                'status' => self::ok([
                    'klartext_enabled' => !empty($cfg['klartext_enabled']),
                    'languagetool_ready' => WriterAssistSettings::languageToolReady($cfg),
                ]),
                default => self::error('Unbekannte Aktion.'),
            };
        } catch (Throwable $e) {
            self::error($e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $cfg
     * @return never
     */
    private static function klartext(string $text, array $cfg): never
    {
        if (empty($cfg['klartext_enabled'])) {
            self::error('Klartext-Assist ist in den Einstellungen ausgeschaltet.', 400);
        }
        self::ok(['assist' => 'klartext', 'result' => WriterKlartextAssist::analyze($text)]);
    }

    /**
     * @param array<string, mixed> $cfg
     * @return never
     */
    private static function languageTool(string $text, array $cfg): never
    {
        self::ok(['assist' => 'languagetool', 'result' => WriterLanguageToolClient::check($text, $cfg)]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return never
     */
    private static function ok(array $payload): never
    {
        echo json_encode(['ok' => true] + $payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    /** @return never */
    private static function error(string $message, int $status = 400): never
    {
        http_response_code($status);
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
