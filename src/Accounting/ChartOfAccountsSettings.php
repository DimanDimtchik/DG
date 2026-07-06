<?php
declare(strict_types=1);

/** Kontenrahmen-Einstellungen (SKR03/SKR04). */
final class ChartOfAccountsSettings
{
    public const STORE_KEY = 'chart_of_accounts';

    /** @return array{skr_type: string, account_digits: int} */
    public static function defaults(): array
    {
        return [
            'skr_type' => 'skr03',
            'account_digits' => 4,
        ];
    }

    /** @return array{skr_type: string, account_digits: int} */
    public static function forForm(): array
    {
        $stored = SettingsStore::get(self::STORE_KEY, self::defaults());

        return [
            'skr_type' => self::sanitizeSkrType((string) ($stored['skr_type'] ?? 'skr03')),
            'account_digits' => self::sanitizeDigits((int) ($stored['account_digits'] ?? 4)),
        ];
    }

    public static function activeSkrType(): string
    {
        return self::forForm()['skr_type'];
    }

    public static function accountDigits(): int
    {
        return self::forForm()['account_digits'];
    }

    /** @param array<string, mixed> $input */
    public static function saveFromPost(array $input): void
    {
        $skrType = self::sanitizeSkrType((string) ($input['skr_type'] ?? 'skr03'));
        $digits = self::sanitizeDigits((int) ($input['account_digits'] ?? 4));

        SettingsStore::set(self::STORE_KEY, [
            'skr_type' => $skrType,
            'account_digits' => $digits,
        ]);

        ChartAccountRepository::ensureSeeded($skrType);
    }

    public static function sanitizeSkrType(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['skr03', 'skr04'], true) ? $value : 'skr03';
    }

    public static function sanitizeDigits(int $value): int
    {
        return $value >= 4 && $value <= 8 ? $value : 4;
    }

    /** @return array<string, string> */
    public static function skrTypeOptions(): array
    {
        return [
            'skr03' => 'SKR03 (Prozessgliederung)',
            'skr04' => 'SKR04 (Abschlussgliederung)',
        ];
    }
}
