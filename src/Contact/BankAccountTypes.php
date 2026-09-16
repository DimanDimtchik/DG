<?php
declare(strict_types=1);

/**
 * Bank Account Types.
 */
final class BankAccountTypes
{
    /**
     * @return array<string, string>
     */
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

    /**
     * @return array<string, string>
     */
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
            'card_brand' => '',
            'card_issuer' => '',
            'card_bin' => '',
            'card_last4' => '',
            'email' => '',
            'merchant_id' => '',
            'account_id' => '',
            'profile_id' => '',
            'creditor_id' => '',
            'card_number_masked' => '',
            'expiry' => '',
            'account_ref' => '',
            'is_primary' => '',
            'responsible_user_id' => '0',
        ];
    }

    /**
     * @param array<string, mixed> $account
     * @return array<string, string>
     */
    public static function sanitizeRow(array $account): array
    {
        $type = trim((string) ($account['type'] ?? 'giro'));
        $labels = self::labels();

        $row = [
            'type' => isset($labels[$type]) ? $type : 'giro',
            'label' => trim((string) ($account['label'] ?? '')),
            'account_holder' => trim((string) ($account['account_holder'] ?? '')),
            'iban' => strtoupper(str_replace(' ', '', trim((string) ($account['iban'] ?? '')))),
            'bic' => strtoupper(trim((string) ($account['bic'] ?? ''))),
            'bank_name' => trim((string) ($account['bank_name'] ?? '')),
            'provider' => trim((string) ($account['provider'] ?? '')),
            'card_brand' => trim((string) ($account['card_brand'] ?? '')),
            'card_issuer' => trim((string) ($account['card_issuer'] ?? '')),
            'card_bin' => trim((string) ($account['card_bin'] ?? '')),
            'card_last4' => trim((string) ($account['card_last4'] ?? '')),
            'email' => trim((string) ($account['email'] ?? '')),
            'merchant_id' => trim((string) ($account['merchant_id'] ?? '')),
            'account_id' => trim((string) ($account['account_id'] ?? '')),
            'profile_id' => trim((string) ($account['profile_id'] ?? '')),
            'creditor_id' => trim((string) ($account['creditor_id'] ?? '')),
            'card_number_masked' => trim((string) ($account['card_number_masked'] ?? '')),
            'card_number_entry' => trim((string) ($account['card_number_entry'] ?? '')),
            'expiry' => trim((string) ($account['expiry'] ?? '')),
            'account_ref' => trim((string) ($account['account_ref'] ?? '')),
            'is_primary' => !empty($account['is_primary']) ? '1' : '',
            'responsible_user_id' => (string) max(0, (int) ($account['responsible_user_id'] ?? 0)),
        ];

        $row = CardNumberHelper::sanitizeStoredCardFields($row);

        // Issuer ggf. aus BIN nachziehen
        if ($row['card_issuer'] === '' && $row['card_bin'] !== '') {
            $row['card_issuer'] = CardNumberHelper::suggestIssuer($row['card_bin']);
        }
        if ($row['card_brand'] !== '' && $row['provider'] === '') {
            $brand = CardNumberHelper::detectBrand($row['card_bin'] !== '' ? $row['card_bin'] : '0');
            // detectBrand needs more digits for visa (starts with 4) — use brand code map
            $labelsBrand = [
                'visa' => 'Visa',
                'mastercard' => 'Mastercard',
                'amex' => 'American Express',
                'discover' => 'Discover',
                'jcb' => 'JCB',
                'diners' => 'Diners Club',
                'unionpay' => 'UnionPay',
                'dankort' => 'Dankort',
            ];
            $row['provider'] = $labelsBrand[$row['card_brand']] ?? $row['card_brand'];
        }

        return $row;
    }

    /**
     * @param array<string, string> $row
     */
    public static function isEmpty(array $row): bool
    {
        $check = $row;
        unset($check['type'], $check['responsible_user_id'], $check['is_primary']);

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
            'provider' => 'Kartennetz / Anbieter',
            'card_brand' => 'Kartenart (Code)',
            'card_issuer' => 'Karteninstitut',
            'card_bin' => 'BIN',
            'card_number_masked' => 'Kartennummer (maskiert)',
            'card_last4' => 'Letzte 4 Ziffern',
            'expiry' => 'Gültig bis',
            'email' => 'E-Mail',
            'merchant_id' => 'Merchant-ID',
            'account_id' => 'Account-ID',
            'profile_id' => 'Profile-ID',
            'creditor_id' => 'Gläubiger-ID',
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
        if (!empty($account['is_primary'])) {
            $fields[] = ['label' => 'Hauptkonto (Dokumente)', 'value' => 'ja', 'kind' => 'text'];
        }

        return $fields;
    }
}
