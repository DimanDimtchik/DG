<?php
declare(strict_types=1);

final class BankAccountTypes
{
    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            'giro' => 'Girokonto',
            'sparkonto' => 'Sparkonto / Tagesgeld',
            'kreditkarte' => 'Kreditkarte',
            'paypal' => 'PayPal',
            'klarna' => 'Klarna',
            'stripe' => 'Stripe',
            'mollie' => 'Mollie',
            'amazon_pay' => 'Amazon Pay',
            'apple_pay' => 'Apple Pay (über Acquirer)',
            'google_pay' => 'Google Pay (über Acquirer)',
            'sepa_lastschrift' => 'SEPA-Lastschrift (Einzug)',
            'sonstiges' => 'Sonstiger Zahlungsdienst',
        ];
    }

    public static function label(string $type): string
    {
        return self::labels()[$type] ?? $type;
    }

    /** @return array<string, string> */
    public static function emptyAccount(string $type = 'giro'): array
    {
        return [
            'type' => isset(self::labels()[$type]) ? $type : 'giro',
            'label' => '',
            'account_holder' => '',
            'iban' => '',
            'bic' => '',
            'bank_name' => '',
            'provider' => '',
            'email' => '',
            'merchant_id' => '',
            'account_id' => '',
            'profile_id' => '',
            'creditor_id' => '',
            'card_number_masked' => '',
            'expiry' => '',
            'account_ref' => '',
            'responsible_user_id' => '0',
        ];
    }

    /** @param array<string, mixed> $account */
    public static function sanitizeRow(array $account): array
    {
        $type = trim((string) ($account['type'] ?? 'giro'));
        $labels = self::labels();

        return [
            'type' => isset($labels[$type]) ? $type : 'giro',
            'label' => trim((string) ($account['label'] ?? '')),
            'account_holder' => trim((string) ($account['account_holder'] ?? '')),
            'iban' => strtoupper(str_replace(' ', '', trim((string) ($account['iban'] ?? '')))),
            'bic' => strtoupper(trim((string) ($account['bic'] ?? ''))),
            'bank_name' => trim((string) ($account['bank_name'] ?? '')),
            'provider' => trim((string) ($account['provider'] ?? '')),
            'email' => trim((string) ($account['email'] ?? '')),
            'merchant_id' => trim((string) ($account['merchant_id'] ?? '')),
            'account_id' => trim((string) ($account['account_id'] ?? '')),
            'profile_id' => trim((string) ($account['profile_id'] ?? '')),
            'creditor_id' => trim((string) ($account['creditor_id'] ?? '')),
            'card_number_masked' => trim((string) ($account['card_number_masked'] ?? '')),
            'expiry' => trim((string) ($account['expiry'] ?? '')),
            'account_ref' => trim((string) ($account['account_ref'] ?? '')),
            'responsible_user_id' => (string) max(0, (int) ($account['responsible_user_id'] ?? 0)),
        ];
    }

    /** @param array<string, string> $row */
    public static function isEmpty(array $row): bool
    {
        $check = $row;
        unset($check['type'], $check['responsible_user_id']);

        return implode('', $check) === '';
    }

    /**
     * @param array<string, string> $account
     * @return list<array{label: string, value: string, kind: string}>
     */
    public static function detailFields(array $account): array
    {
        $map = [
            'account_holder' => 'Kontoinhaber',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'bank_name' => 'Bank / Anbieter',
            'provider' => 'Kartenanbieter',
            'email' => 'E-Mail',
            'merchant_id' => 'Merchant-ID',
            'account_id' => 'Account-ID',
            'profile_id' => 'Profile-ID',
            'creditor_id' => 'Gläubiger-ID',
            'card_number_masked' => 'Kartennummer (maskiert)',
            'expiry' => 'Gültig bis',
        ];

        $fields = [];
        foreach ($map as $key => $label) {
            $value = trim((string) ($account[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $fields[] = [
                'label' => $label,
                'value' => $value,
                'kind' => $key === 'email' ? 'email' : 'text',
            ];
        }

        return $fields;
    }
}
