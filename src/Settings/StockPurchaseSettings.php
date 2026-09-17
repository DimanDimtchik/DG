<?php
declare(strict_types=1);

/** Lager/Einkauf: Unterbestand-Policy und Aktion (SettingsStore key `stock_purchase`). */
final class StockPurchaseSettings
{
    public const STORE_KEY = 'stock_purchase';

    public const POLICY_WARN = 'warn';
    public const POLICY_BLOCK = 'block';

    public const ACTION_PURCHASE_LIST = 'purchase_list';
    public const ACTION_PURCHASE_LIST_AND_OPEN_URL = 'purchase_list_and_open_url';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'shortage_policy' => self::POLICY_WARN,
            'shortage_action' => self::ACTION_PURCHASE_LIST,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        $stored = SettingsStore::get(self::STORE_KEY, self::defaults());
        $defaults = self::defaults();

        return [
            'shortage_policy' => self::sanitizePolicy((string) ($stored['shortage_policy'] ?? $defaults['shortage_policy'])),
            'shortage_action' => self::sanitizeAction((string) ($stored['shortage_action'] ?? $defaults['shortage_action'])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return self::forForm();
    }

    public static function shortagePolicy(): string
    {
        return (string) (self::config()['shortage_policy'] ?? self::POLICY_WARN);
    }

    public static function shortageAction(): string
    {
        return (string) (self::config()['shortage_action'] ?? self::ACTION_PURCHASE_LIST);
    }

    public static function usesPurchaseList(): bool
    {
        $action = self::shortageAction();

        return $action === self::ACTION_PURCHASE_LIST
            || $action === self::ACTION_PURCHASE_LIST_AND_OPEN_URL;
    }

    public static function shouldOpenOrderUrl(): bool
    {
        return self::shortageAction() === self::ACTION_PURCHASE_LIST_AND_OPEN_URL;
    }

    public static function isBlocking(): bool
    {
        return self::shortagePolicy() === self::POLICY_BLOCK;
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function saveFromPost(array $input): void
    {
        SettingsStore::set(self::STORE_KEY, [
            'shortage_policy' => self::sanitizePolicy((string) ($input['shortage_policy'] ?? self::POLICY_WARN)),
            'shortage_action' => self::sanitizeAction((string) ($input['shortage_action'] ?? self::ACTION_PURCHASE_LIST)),
        ]);
    }

    public static function sanitizePolicy(string $policy): string
    {
        $policy = strtolower(trim($policy));

        return in_array($policy, [self::POLICY_WARN, self::POLICY_BLOCK], true)
            ? $policy
            : self::POLICY_WARN;
    }

    public static function sanitizeAction(string $action): string
    {
        $action = strtolower(trim($action));

        return in_array($action, [
            self::ACTION_PURCHASE_LIST,
            self::ACTION_PURCHASE_LIST_AND_OPEN_URL,
        ], true) ? $action : self::ACTION_PURCHASE_LIST;
    }
}
