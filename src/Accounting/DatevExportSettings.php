<?php
declare(strict_types=1);

/** DATEV-Export (Berater-/Mandantennummer für EXTF Buchungsstapel). */
final class DatevExportSettings
{
    public const STORE_KEY = 'datev_export';

    /**
     * @return array{consultant_number: string, client_number: string}
     */
    public static function defaults(): array
    {
        return [
            'consultant_number' => '',
            'client_number' => '',
        ];
    }

    /**
     * @return array{consultant_number: string, client_number: string}
     */
    public static function config(): array
    {
        $stored = SettingsStore::get(self::STORE_KEY, self::defaults());

        return [
            'consultant_number' => self::sanitizeNumber((string) ($stored['consultant_number'] ?? '')),
            'client_number' => self::sanitizeNumber((string) ($stored['client_number'] ?? '')),
        ];
    }

    /**
     * @return array{consultant_number: string, client_number: string}
     */
    public static function forForm(): array
    {
        return self::config();
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function saveFromPost(array $input): void
    {
        SettingsStore::set(self::STORE_KEY, [
            'consultant_number' => self::sanitizeNumber((string) ($input['datev_consultant_number'] ?? '')),
            'client_number' => self::sanitizeNumber((string) ($input['datev_client_number'] ?? '')),
        ]);
    }

    public static function sanitizeNumber(string $value): string
    {
        $value = preg_replace('/\D/', '', trim($value)) ?? '';

        return mb_substr($value, 0, 7);
    }

    public static function isConfigured(): bool
    {
        $cfg = self::config();

        return $cfg['consultant_number'] !== '' && $cfg['client_number'] !== '';
    }
}
