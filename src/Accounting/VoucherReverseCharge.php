<?php
declare(strict_types=1);

/**
 * Reverse Charge (§13b UStG) — Lexoffice-Parität: Steuersätze, Nebenbuchungen, UStVA-Kennziffern.
 */
final class VoucherReverseCharge
{
    public const TYPE_EU = 'eu';
    public const TYPE_THIRD_COUNTRY = 'third_country';
    public const TYPE_CONSTRUCTION = 'construction';

    public const LINE_BOOKING = 'booking';
    public const LINE_INPUT_VAT = 'input_vat_13b';
    public const LINE_OUTPUT_VAT = 'output_vat_13b';

    /**
     * typeOptions.
     *
     * @return array<string, string>
     */
        public static function typeOptions(): array
    {
        return [
            self::TYPE_EU => 'Fremdleistungen §13b (EU)',
            self::TYPE_THIRD_COUNTRY => 'Fremdleistungen §13b (Drittland)',
            self::TYPE_CONSTRUCTION => 'Bauleistungen §13b',
        ];
    }

    /**
     * typeHint
     * @param string $type
     * @return string
     */
    public static function typeHint(string $type): string
    {
        return match (self::sanitizeType($type)) {
            self::TYPE_EU => 'Rechnungen aus dem EU-Ausland ohne USt. — z. B. Google, Adobe, Amazon. UStVA: Kennziffern 46, 47, 67.',
            self::TYPE_THIRD_COUNTRY => 'Rechnungen aus Drittländern (nicht-EU) ohne USt. UStVA: Kennziffern 84, 85, 67.',
            self::TYPE_CONSTRUCTION => 'Bauleistungen im Inland mit Steuerschuldnerschaft des Leistungsempfängers. UStVA: Kennziffern 48/49, 67.',
            default => '',
        };
    }

    /**
     * sanitizeType
     * @param string $type
     * @return string
     */
    public static function sanitizeType(string $type): string
    {
        $type = strtolower(trim($type));

        return isset(self::typeOptions()[$type]) ? $type : '';
    }

    /**
     * isActive
     * @param string $type
     * @return bool
     */
    public static function isActive(string $type): bool
    {
        return self::sanitizeType($type) !== '';
    }

        /**
     * Liefert erlaubte Steuersätze
     * @param string $type
     * @return list<int>
     */
    public static function allowedTaxRates(string $type): array
    {
        return match (self::sanitizeType($type)) {
            self::TYPE_CONSTRUCTION => [7, 19],
            self::TYPE_EU, self::TYPE_THIRD_COUNTRY => [19],
            default => VoucherTaxKeys::allowedTaxRates(),
        };
    }

    /**
     * defaultTaxRate
     * @param string $type
     * @return int
     */
    public static function defaultTaxRate(string $type): int
    {
        $rates = self::allowedTaxRates($type);

        return $rates[0] ?? 19;
    }

    /**
     * sanitizeLineTaxRate
     * @param string $type
     * @param int $rate Steuersatz in Prozent
     * @return int
     */
    public static function sanitizeLineTaxRate(string $type, int $rate): int
    {
        $allowed = self::allowedTaxRates($type);
        if ($allowed === []) {
            return VoucherTaxKeys::sanitizeTaxRate($rate);
        }

        return in_array($rate, $allowed, true) ? $rate : self::defaultTaxRate($type);
    }

        /**
     * taxAccounts
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @return array{input_vat: string, output_vat: string}
     */
    public static function taxAccounts(string $skrType): array
    {
        $skrType = ChartOfAccountsSettings::sanitizeSkrType($skrType);

        return match ($skrType) {
            'skr04' => ['input_vat' => '1407', 'output_vat' => '3837'],
            default => ['input_vat' => '1577', 'output_vat' => '1787'],
        };
    }

        /**
     * UStVA-Kennziffern je Typ und Steuersatz (Anlage zur Umsatzsteuer-Voranmeldung).
     * @param string $type
     * @param int $taxRate Steuersatz in Prozent
     * @return array{base: string, output_vat: string, input_vat: string}
     */
    public static function ustvaKennziffern(string $type, int $taxRate): array
    {
        $type = self::sanitizeType($type);
        $taxRate = VoucherTaxKeys::sanitizeTaxRate($taxRate);

        return match ($type) {
            self::TYPE_EU => [
                'base' => '46',
                'output_vat' => '47',
                'input_vat' => '67',
            ],
            self::TYPE_THIRD_COUNTRY => [
                'base' => '84',
                'output_vat' => '85',
                'input_vat' => '67',
            ],
            self::TYPE_CONSTRUCTION => $taxRate === 7
                ? ['base' => '49', 'output_vat' => '49', 'input_vat' => '67']
                : ['base' => '48', 'output_vat' => '48', 'input_vat' => '67'],
            default => ['base' => '', 'output_vat' => '', 'input_vat' => ''],
        };
    }

        /**
     * lines: list<array{line_kind: string, account_number: string, description: string, gross_amount: float, net_amount: float, tax_amount: float, tax_rate: int, ustva_kz: string, posting_side: string}>,
     * @param array $bookingLines
     * @param string $type
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @return array{
     */
    public static function buildPostings(array $bookingLines, string $type, string $skrType): array
    {
        $type = self::sanitizeType($type);
        if ($type === '') {
            return ['lines' => $bookingLines, 'ustva_positions' => []];
        }

        $taxAccounts = self::taxAccounts($skrType);
        /** @var array<string, array{kz: string, net: float, tax: float}> $ustvaAgg */
        $ustvaAgg = [];
        $allLines = [];

        foreach ($bookingLines as $booking) {
            $account = (string) ($booking['account_number'] ?? '');
            $net = round((float) ($booking['net_amount'] ?? $booking['gross_amount'] ?? 0), 2);
            if ($account === '' || $net <= 0) {
                continue;
            }
            $rate = self::sanitizeLineTaxRate($type, (int) ($booking['tax_rate'] ?? 19));
            $tax = round((float) ($booking['tax_amount'] ?? $net * $rate / 100), 2);
            $kz = self::ustvaKennziffern($type, $rate);
            $description = trim((string) ($booking['description'] ?? ''));

            $allLines[] = [
                'line_kind' => self::LINE_BOOKING,
                'account_number' => $account,
                'description' => $description,
                'gross_amount' => $net,
                'net_amount' => $net,
                'tax_amount' => $tax,
                'tax_rate' => $rate,
                'ustva_kz' => $kz['base'],
                'posting_side' => 'debit',
            ];

            if ($tax > 0) {
                $allLines[] = [
                    'line_kind' => self::LINE_INPUT_VAT,
                    'account_number' => $taxAccounts['input_vat'],
                    'description' => 'Abziehbare Vorsteuer §13b ' . $rate . ' %',
                    'gross_amount' => $tax,
                    'net_amount' => $tax,
                    'tax_amount' => 0.0,
                    'tax_rate' => $rate,
                    'ustva_kz' => $kz['input_vat'],
                    'posting_side' => 'debit',
                ];
                $allLines[] = [
                    'line_kind' => self::LINE_OUTPUT_VAT,
                    'account_number' => $taxAccounts['output_vat'],
                    'description' => 'Umsatzsteuer nach §13b ' . $rate . ' %',
                    'gross_amount' => $tax,
                    'net_amount' => $tax,
                    'tax_amount' => 0.0,
                    'tax_rate' => $rate,
                    'ustva_kz' => $kz['output_vat'],
                    'posting_side' => 'credit',
                ];
            }

            self::addUstvaAgg($ustvaAgg, $kz['base'], $net, 0.0);
            self::addUstvaAgg($ustvaAgg, $kz['output_vat'], 0.0, $tax);
            self::addUstvaAgg($ustvaAgg, $kz['input_vat'], 0.0, $tax);
        }

        $ustvaPositions = array_values(array_map(
            static fn (array $row): array => [
                'kz' => $row['kz'],
                'net' => round($row['net'], 2),
                'tax' => round($row['tax'], 2),
            ],
            $ustvaAgg
        ));

        return ['lines' => $allLines, 'ustva_positions' => $ustvaPositions];
    }

        /**
     * clientConfig
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @return array<string, mixed>
     */
    public static function clientConfig(string $skrType): array
    {
        $types = [];
        foreach (self::typeOptions() as $key => $label) {
            $types[$key] = [
                'label' => $label,
                'hint' => self::typeHint($key),
                'defaultTaxRate' => self::defaultTaxRate($key),
                'allowedTaxRates' => self::allowedTaxRates($key),
                'ustva' => [
                    '19' => self::ustvaKennziffern($key, 19),
                    '7' => self::ustvaKennziffern($key, 7),
                ],
            ];
        }

        return [
            'types' => $types,
            'taxAccounts' => self::taxAccounts($skrType),
            'taxKey' => VoucherTaxKeys::KEY_REVERSE_CHARGE,
            'lineKinds' => [
                self::LINE_BOOKING => 'Aufwand',
                self::LINE_INPUT_VAT => 'Vorsteuer §13b',
                self::LINE_OUTPUT_VAT => 'Umsatzsteuer §13b',
            ],
        ];
    }

        /**
     * addUstvaAgg
     * @param mixed $agg
     * @param string $kz
     * @param float $net
     * @param float $tax
     * @return void
     */
    private static function addUstvaAgg(array &$agg, string $kz, float $net, float $tax): void
    {
        if ($kz === '' || ($net <= 0 && $tax <= 0)) {
            return;
        }
        if (!isset($agg[$kz])) {
            $agg[$kz] = ['kz' => $kz, 'net' => 0.0, 'tax' => 0.0];
        }
        $agg[$kz]['net'] += $net;
        $agg[$kz]['tax'] += $tax;
    }
}
