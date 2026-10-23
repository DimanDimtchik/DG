<?php

declare(strict_types=1);

/**
 * Text-Assist Dokumentenverfasser (D4).
 * Klartext lokal; LanguageTool nur opt-in (idealerweise Self-Host).
 */
final class WriterAssistSettings
{
    public const STORE_KEY = 'writer_assist';

    /**
     * @return array{
     *   klartext_enabled: bool,
     *   languagetool_enabled: bool,
     *   languagetool_url: string,
     *   languagetool_language: string,
     *   languagetool_allow_public_cloud: bool
     * }
     */
    public static function defaults(): array
    {
        return [
            'klartext_enabled' => true,
            'languagetool_enabled' => false,
            'languagetool_url' => '',
            'languagetool_language' => 'de-DE',
            'languagetool_allow_public_cloud' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        $defaults = self::defaults();
        if (!Database::isConfigured()) {
            return $defaults;
        }
        try {
            $stored = SettingsStore::get(self::STORE_KEY, []);
        } catch (Throwable) {
            return $defaults;
        }

        return array_merge($defaults, is_array($stored) ? self::sanitize($stored, false) : []);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function sanitize(array $input, bool $fromForm = true): array
    {
        $d = self::defaults();
        $flag = static function (array $src, string $key, bool $default) use ($fromForm): bool {
            if (array_key_exists($key, $src)) {
                return !empty($src[$key]) && (string) $src[$key] !== '0';
            }

            return $fromForm ? false : $default;
        };

        $url = trim((string) ($input['languagetool_url'] ?? ''));
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = '';
        }
        if (mb_strlen($url) > 500) {
            $url = mb_substr($url, 0, 500);
        }

        $lang = trim((string) ($input['languagetool_language'] ?? $d['languagetool_language']));
        if (!preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $lang)) {
            $lang = 'de-DE';
        }

        $enabled = $flag($input, 'languagetool_enabled', false);
        $allowPublic = $flag($input, 'languagetool_allow_public_cloud', false);
        if ($enabled && $url !== '' && self::isPublicLanguageToolHost($url) && !$allowPublic) {
            $enabled = false;
        }

        return [
            'klartext_enabled' => $flag($input, 'klartext_enabled', true),
            'languagetool_enabled' => $enabled,
            'languagetool_url' => $url,
            'languagetool_language' => $lang,
            'languagetool_allow_public_cloud' => $allowPublic,
        ];
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function saveFromPost(array $post): void
    {
        SettingsStore::set(self::STORE_KEY, self::sanitize($post, true));
    }

    public static function isPublicLanguageToolHost(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

        return $host === 'api.languagetool.org'
            || $host === 'languagetool.org'
            || str_ends_with($host, '.languagetool.org');
    }

    public static function languageToolReady(?array $cfg = null): bool
    {
        $cfg = $cfg ?? self::config();
        if (empty($cfg['languagetool_enabled'])) {
            return false;
        }
        $url = trim((string) ($cfg['languagetool_url'] ?? ''));
        if ($url === '') {
            return false;
        }
        if (self::isPublicLanguageToolHost($url) && empty($cfg['languagetool_allow_public_cloud'])) {
            return false;
        }

        return true;
    }
}
