<?php
declare(strict_types=1);

/** Rechnungsabgrenzung (ARAP bei Aufwand, PRAP bei Erlös) — Jahresverteilung in %. */
final class VoucherAccrual
{
    public const LINE_ACCRUAL = 'accrual_deferred';

        /**
     * parseFromData
     * @param array $data
     * @return array{enabled: bool, current_year_percent: int, next_year_percent: int}
     * @throws InvalidArgumentException
     */
    public static function parseFromData(array $data): array
    {
        $enabled = !empty($data['arap_enabled']);
        $current = self::sanitizePercent($data['arap_current_year_percent'] ?? 100);
        $next = self::sanitizePercent($data['arap_next_year_percent'] ?? (100 - $current));

        if ($enabled) {
            if ($current + $next !== 100) {
                throw new InvalidArgumentException('ARAP: Die Verteilung muss zusammen 100 % ergeben.');
            }
            if ($next < 1) {
                throw new InvalidArgumentException('ARAP: Für die Abgrenzung ist ein Anteil fürs Folgejahr erforderlich.');
            }
        } else {
            $current = 100;
            $next = 0;
        }

        return [
            'enabled' => $enabled,
            'current_year_percent' => $current,
            'next_year_percent' => $next,
        ];
    }

    /**
     * isIncomeType
     * @param string $voucherType Belegtyp
     * @return bool
     */
    public static function isIncomeType(string $voucherType): bool
    {
        return in_array(VoucherRepository::normalizeVoucherType($voucherType), ['income', 'income_reduction'], true);
    }

    /**
     * PRAP nur für Einnahmen (Vorausrechnung über Jahreswechsel), nicht für Minderungen/Gutschriften.
     */
    public static function supportsAccrual(string $voucherType, string $documentKind = ''): bool
    {
        if (!VoucherDocumentKind::isBookable($documentKind, $voucherType)) {
            return false;
        }

        return in_array(
            VoucherRepository::normalizeVoucherType($voucherType),
            ['income', 'expense', 'expense_reduction'],
            true
        );
    }

    /**
     * showAccrualUi
     * @param string $voucherType Belegtyp
     * @param bool $enabled
     * @param bool $readOnly
     * @param string $documentKind
     * @return bool
     */
    public static function showAccrualUi(string $voucherType, bool $enabled, bool $readOnly, string $documentKind = ''): bool
    {
        if ($readOnly && $enabled) {
            return true;
        }

        return self::supportsAccrual($voucherType, $documentKind);
    }

    /**
     * labelForType
     * @param string $voucherType Belegtyp
     * @return string
     */
    public static function labelForType(string $voucherType): string
    {
        return self::isIncomeType($voucherType)
            ? 'Passive Rechnungsabgrenzung (PRAP)'
            : 'Aktive Rechnungsabgrenzung (ARAP)';
    }

    /**
     * hintForType
     * @param string $voucherType Belegtyp
     * @return string
     */
    public static function hintForType(string $voucherType): string
    {
        return self::isIncomeType($voucherType)
            ? 'Erlösanteil fürs Folgejahr wird auf das Abgrenzungskonto (PRAP) ausgebucht — z. B. Vorausrechnung über den Jahreswechsel.'
            : 'Aufwandsanteil fürs Folgejahr wird als aktive Rechnungsabgrenzung (ARAP) gebucht — z. B. vorausbezahlte Leistungen.';
    }

        /**
     * Liefert Rechnungsabgrenzungskonten
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @return array{active: string, passive: string}
     */
    public static function accrualAccounts(string $skrType): array
    {
        return match (ChartOfAccountsSettings::sanitizeSkrType($skrType)) {
            'skr04' => ['active' => '1900', 'passive' => '3900'],
            default => ['active' => '0980', 'passive' => '0990'],
        };
    }

    /**
     * accrualAccount
     * @param string $voucherType Belegtyp
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @return string
     */
    public static function accrualAccount(string $voucherType, string $skrType): string
    {
        $accounts = self::accrualAccounts($skrType);

        return self::isIncomeType($voucherType) ? $accounts['passive'] : $accounts['active'];
    }

        /**
     * buildPostings
     * @param array $bookingLines
     * @param string $voucherType Belegtyp
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @param int $currentPercent
     * @param int $nextPercent
     * @param int $nextFiscalYear
     * @return list<array<string, mixed>>
     */
    public static function buildPostings(
        array $bookingLines,
        string $voucherType,
        string $skrType,
        int $currentPercent,
        int $nextPercent,
        int $nextFiscalYear,
    ): array {
        if ($nextPercent < 1 || $currentPercent + $nextPercent !== 100) {
            return $bookingLines;
        }

        $accrualAccount = self::accrualAccount($voucherType, $skrType);
        $allLines = [];

        foreach ($bookingLines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $gross = round((float) ($line['gross_amount'] ?? 0), 2);
            if ($gross <= 0) {
                continue;
            }

            $rate = VoucherTaxKeys::sanitizeTaxRate((int) ($line['tax_rate'] ?? 19));
            $currentGross = round($gross * $currentPercent / 100, 2);
            $nextGross = round($gross - $currentGross, 2);
            $currentAmounts = VoucherTaxKeys::calcLineAmounts($currentGross, $rate, false);

            $allLines[] = array_merge($line, [
                'line_kind' => VoucherReverseCharge::LINE_BOOKING,
                'gross_amount' => $currentAmounts['gross_amount'],
                'net_amount' => $currentAmounts['net_amount'],
                'tax_amount' => $currentAmounts['tax_amount'],
                'tax_rate' => $rate,
                'ustva_kz' => (string) ($line['ustva_kz'] ?? ''),
                'posting_side' => (string) ($line['posting_side'] ?? 'debit'),
            ]);

            if ($nextGross <= 0) {
                continue;
            }

            $nextAmounts = VoucherTaxKeys::calcLineAmounts($nextGross, $rate, false);
            $description = trim((string) ($line['description'] ?? ''));
            if ($description !== '') {
                $description .= ' — ';
            }
            $description .= $nextPercent . ' % ' . $nextFiscalYear;

            $allLines[] = [
                'line_kind' => self::LINE_ACCRUAL,
                'account_number' => $accrualAccount,
                'description' => $description,
                'gross_amount' => $nextAmounts['gross_amount'],
                'net_amount' => $nextAmounts['net_amount'],
                'tax_amount' => $nextAmounts['tax_amount'],
                'tax_rate' => $rate,
                'ustva_kz' => '',
                'posting_side' => self::isIncomeType($voucherType) ? 'credit' : 'debit',
            ];
        }

        return $allLines;
    }

        /**
     * previewRows
     * @param array $bookingLines
     * @param string $voucherType Belegtyp
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @param int $currentPercent
     * @param int $nextPercent
     * @param int $currentFiscalYear
     * @param int $nextFiscalYear
     * @return list<array{account_number: string, account_name: string, description: string, gross_amount: string, tax_rate: int, share_label: string}>
     */
    public static function previewRows(
        array $bookingLines,
        string $voucherType,
        string $skrType,
        int $currentPercent,
        int $nextPercent,
        int $currentFiscalYear,
        int $nextFiscalYear,
    ): array {
        if ($nextPercent < 1 || $currentPercent + $nextPercent !== 100) {
            return [];
        }

        $accrualAccount = self::accrualAccount($voucherType, $skrType);
        $accrualName = '';
        $account = ChartAccountRepository::findByNumber($accrualAccount, $skrType);
        if ($account !== null) {
            $accrualName = (string) ($account['name'] ?? '');
        }

        $rows = [];
        foreach ($bookingLines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $gross = round((float) str_replace(',', '.', (string) ($line['gross_amount'] ?? '0')), 2);
            if ($gross <= 0) {
                continue;
            }
            $accountNumber = (string) ($line['account_number'] ?? '');
            $accountName = (string) ($line['account_name'] ?? '');
            if ($accountName === '' && $accountNumber !== '') {
                $found = ChartAccountRepository::findByNumber($accountNumber, $skrType);
                if ($found !== null) {
                    $accountName = (string) ($found['name'] ?? '');
                }
            }

            $currentGross = round($gross * $currentPercent / 100, 2);
            $nextGross = round($gross - $currentGross, 2);
            $rate = (int) ($line['tax_rate'] ?? 19);

            $rows[] = [
                'account_number' => $accountNumber,
                'account_name' => $accountName,
                'description' => (string) ($line['description'] ?? ''),
                'gross_amount' => VoucherRepository::formatMoney($currentGross),
                'tax_rate' => $rate,
                'share_label' => $currentPercent . ' % ' . $currentFiscalYear,
            ];
            if ($nextGross > 0) {
                $rows[] = [
                    'account_number' => $accrualAccount,
                    'account_name' => $accrualName,
                    'description' => self::labelForType($voucherType),
                    'gross_amount' => VoucherRepository::formatMoney($nextGross),
                    'tax_rate' => $rate,
                    'share_label' => $nextPercent . ' % ' . $nextFiscalYear,
                ];
            }
        }

        return $rows;
    }

        /**
     * clientConfig
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @return array<string, mixed>
     */
    public static function clientConfig(string $skrType): array
    {
        $accounts = self::accrualAccounts($skrType);

        return [
            'accounts' => $accounts,
            'incomeTypes' => VoucherIncomePositions::voucherTypesWithItems(),
        ];
    }

    /**
     * sanitizePercent
     * @param mixed $value Eingabewert
     * @return int
     */
    private static function sanitizePercent(mixed $value): int
    {
        $percent = (int) round((float) str_replace(',', '.', (string) $value));

        return max(0, min(100, $percent));
    }
}
