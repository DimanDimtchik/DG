<?php
declare(strict_types=1);

/** Konten und Defaults für Mehrzweck-Gutscheine (SKR03: Verbindlichkeit 1796, Aufwand Garantie 4780). */
final class GiftVoucherSettings
{
    public const KEY = 'gift_voucher_accounts';

    public const DEFAULT_LIABILITY_ACCOUNT = '1796';
    public const DEFAULT_EXPENSE_ACCOUNT = '4780';

    /**
     * @return array{liability_account: string, expense_account: string, default_valid_days: int}
     */
    public static function get(): array
    {
        $raw = SettingsStore::get(self::KEY, []);
        if (!is_array($raw)) {
            $raw = [];
        }

        return [
            'liability_account' => self::sanitizeAccount(
                (string) ($raw['liability_account'] ?? self::DEFAULT_LIABILITY_ACCOUNT),
                self::DEFAULT_LIABILITY_ACCOUNT
            ),
            'expense_account' => self::sanitizeAccount(
                (string) ($raw['expense_account'] ?? self::DEFAULT_EXPENSE_ACCOUNT),
                self::DEFAULT_EXPENSE_ACCOUNT
            ),
            'default_valid_days' => max(0, min(3650, (int) ($raw['default_valid_days'] ?? 365))),
        ];
    }

    private static function sanitizeAccount(string $account, string $fallback): string
    {
        $digits = preg_replace('/\D/', '', $account) ?? '';

        return $digits !== '' ? $digits : $fallback;
    }
}
