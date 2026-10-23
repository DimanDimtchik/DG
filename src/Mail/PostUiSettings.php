<?php

declare(strict_types=1);

/**
 * Darstellung der Post-Liste (Mails pro Seite u. a.).
 */
final class PostUiSettings
{
    public const STORE_KEY = 'post_ui';

    public const DEFAULT_PER_PAGE = 10;

    public const MIN_PER_PAGE = 5;

    public const MAX_PER_PAGE = 100;

    /**
     * @return array{messages_per_page: int}
     */
    public static function defaults(): array
    {
        return [
            'messages_per_page' => self::DEFAULT_PER_PAGE,
        ];
    }

    /**
     * @return array{messages_per_page: int}
     */
    public static function config(): array
    {
        $defaults = self::defaults();
        if (!class_exists('SettingsStore') || !Database::isConfigured()) {
            return $defaults;
        }
        try {
            $stored = SettingsStore::get(self::STORE_KEY, []);
        } catch (Throwable) {
            return $defaults;
        }
        if (!is_array($stored)) {
            return $defaults;
        }

        return [
            'messages_per_page' => self::clampPerPage($stored['messages_per_page'] ?? $defaults['messages_per_page']),
        ];
    }

    public static function messagesPerPage(): int
    {
        return self::config()['messages_per_page'];
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function saveFromPost(array $input): void
    {
        $perPage = self::clampPerPage($input['messages_per_page'] ?? self::DEFAULT_PER_PAGE);
        SettingsStore::set(self::STORE_KEY, [
            'messages_per_page' => $perPage,
        ]);
    }

    private static function clampPerPage(mixed $value): int
    {
        $n = (int) $value;
        if ($n < 1) {
            $n = self::DEFAULT_PER_PAGE;
        }

        return max(self::MIN_PER_PAGE, min(self::MAX_PER_PAGE, $n));
    }
}
