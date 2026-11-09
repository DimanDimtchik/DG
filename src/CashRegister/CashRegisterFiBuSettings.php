<?php
declare(strict_types=1);

/**
 * Mapping Zahlart → FiBu-Konto für Registerkasse (K15a).
 *
 * Nur Geld-/Verrechnungskonten je Zahlart. Erlöse und USt kommen in K15b
 * aus den Bon-Positionen (Steuersatz), nicht aus diesem Mapping.
 * Abgestimmt mit LedgerAccounts (Kasse/Bank/Gutscheinverbindlichkeit).
 */
final class CashRegisterFiBuSettings
{
    public const STORE_KEY = 'cash_register_fibu';

    /** @var list<string> */
    public const PAYMENT_METHODS = ['cash', 'ec', 'card', 'voucher'];

    /**
     * Standardkonten je Kontenrahmen (SKR03 / SKR04).
     *
     * @return array<string, array<string, string>>
     */
    public static function defaultMaps(): array
    {
        return [
            'skr03' => [
                'cash' => '1000',
                'ec' => '1200',
                'card' => '1200',
                'voucher' => '1796',
            ],
            'skr04' => [
                'cash' => '1600',
                'ec' => '1800',
                'card' => '1800',
                'voucher' => '3786',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function defaultsForSkr(string $skrType): array
    {
        $skr = ChartOfAccountsSettings::sanitizeSkrType($skrType);
        $maps = self::defaultMaps();

        return $maps[$skr] ?? $maps['skr03'];
    }

    /**
     * Gespeicherte Overrides (leer = Defaults nutzen).
     *
     * @return array{accounts: array<string, string>}
     */
    public static function stored(): array
    {
        if (!class_exists('SettingsStore') || !Database::isConfigured()) {
            return ['accounts' => []];
        }
        try {
            $raw = SettingsStore::get(self::STORE_KEY, ['accounts' => []]);
        } catch (Throwable) {
            return ['accounts' => []];
        }
        $accounts = is_array($raw['accounts'] ?? null) ? $raw['accounts'] : [];

        return ['accounts' => self::sanitizeAccounts($accounts, false)];
    }

    /**
     * Wirksames Mapping für den aktiven Kontenrahmen (Defaults + Overrides).
     *
     * @return array{
     *   skr_type: string,
     *   accounts: array<string, string>,
     *   labels: array<string, string>,
     *   hints: array<string, string>
     * }
     */
    public static function forForm(?string $skrType = null): array
    {
        $skr = ChartOfAccountsSettings::sanitizeSkrType(
            $skrType ?? ChartOfAccountsSettings::activeSkrType()
        );
        $defaults = self::defaultsForSkr($skr);
        $stored = self::stored()['accounts'];
        $accounts = $defaults;
        foreach (self::PAYMENT_METHODS as $method) {
            if (isset($stored[$method]) && $stored[$method] !== '') {
                $accounts[$method] = $stored[$method];
            }
        }

        return [
            'skr_type' => $skr,
            'accounts' => $accounts,
            'labels' => CashPaymentRepository::methodLabels(),
            'hints' => self::methodHints(),
        ];
    }

    /**
     * Konto für eine Zahlart (K15b+).
     */
    public static function accountForMethod(string $method, ?string $skrType = null): string
    {
        $method = strtolower(trim($method));
        if (!in_array($method, self::PAYMENT_METHODS, true)) {
            throw new InvalidArgumentException('Unbekannte Zahlart: ' . $method);
        }
        $map = self::forForm($skrType)['accounts'];

        return (string) ($map[$method] ?? '');
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function saveFromPost(array $input): void
    {
        if (!class_exists('SettingsStore') || !Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        $skr = ChartOfAccountsSettings::activeSkrType();
        $defaults = self::defaultsForSkr($skr);
        $raw = is_array($input['accounts'] ?? null) ? $input['accounts'] : [];
        $sanitized = self::sanitizeAccounts($raw, true);

        // Nur Abweichungen vom Default speichern (SKR-Wechsel behält sinnvolle Defaults).
        $overrides = [];
        foreach (self::PAYMENT_METHODS as $method) {
            $value = $sanitized[$method] ?? '';
            if ($value === '') {
                continue;
            }
            if ($value !== ($defaults[$method] ?? '')) {
                $overrides[$method] = $value;
            }
        }

        SettingsStore::set(self::STORE_KEY, [
            'accounts' => $overrides,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function methodHints(): array
    {
        return [
            'cash' => 'Kassenbestand — wie FiBu-Barzahlung (SKR03 1000 / SKR04 1600).',
            'ec' => 'Bank oder Geldtransit für EC-Terminal.',
            'card' => 'Bank oder Geldtransit für Kreditkarte — oft wie EC.',
            'voucher' => 'Gutscheinverbindlichkeit bei Einlösung (nicht Erlöskonto).',
        ];
    }

    /**
     * @param array<mixed, mixed> $accounts
     * @return array<string, string>
     */
    private static function sanitizeAccounts(array $accounts, bool $requireKnownMethods): array
    {
        $out = [];
        foreach (self::PAYMENT_METHODS as $method) {
            if (!array_key_exists($method, $accounts) && $requireKnownMethods) {
                continue;
            }
            if (!array_key_exists($method, $accounts)) {
                continue;
            }
            $num = preg_replace('/\D/', '', (string) $accounts[$method]) ?? '';
            if ($num === '') {
                continue;
            }
            if (strlen($num) > 8) {
                throw new InvalidArgumentException('Kontonummer zu lang für Zahlart ' . $method . '.');
            }
            $out[$method] = $num;
        }

        return $out;
    }
}
