<?php
declare(strict_types=1);

/**
 * Erzeugt aus einem Beleg balancierte Journalbuchungen (Soll = Haben) im
 * dg_ledger_postings-Journal. Modell: Netto auf Aufwand/Ertrag, Steuer auf
 * Vorsteuer/Umsatzsteuer, Brutto auf Gegenkonto (Bank/Kasse/Personenkonto).
 */
final class LedgerPostingService
{
    public static function rebuildForVoucher(int $voucherId): void
    {
        if (!Database::isConfigured() || $voucherId < 1) {
            return;
        }
        MigrationRunner::runPending();

        $pdo = Database::pdo();
        $voucher = self::loadVoucher($voucherId);
        self::deleteForVoucher($voucherId);
        if ($voucher === null) {
            return;
        }

        if (!VoucherDocumentKind::isBookable(
            (string) ($voucher['document_kind'] ?? ''),
            (string) ($voucher['voucher_type'] ?? 'expense')
        )) {
            return;
        }

        $fiscalYear = (int) substr((string) ($voucher['voucher_date'] ?? ''), 0, 4);
        if ($fiscalYear >= 2000 && FiscalYearService::isClosed($fiscalYear)) {
            return;
        }

        $postings = self::buildPostings($voucher);
        if ($postings === []) {
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_ledger_postings
                (fiscal_year, posting_date, voucher_id, account_number, contra_account, person_account,
                 side, amount, tax_rate, tax_key, description, document_field1, document_field2, source)
             VALUES
                (:fiscal_year, :posting_date, :voucher_id, :account_number, :contra_account, :person_account,
                 :side, :amount, :tax_rate, :tax_key, :description, :document_field1, :document_field2, :source)'
        );
        foreach ($postings as $p) {
            $stmt->execute($p);
        }

        CashJournalRepository::syncForVoucher($voucherId);
    }

    public static function deleteForVoucher(int $voucherId): void
    {
        if (!Database::isConfigured() || $voucherId < 1) {
            return;
        }
        MigrationRunner::runPending();
        Database::pdo()
            ->prepare("DELETE FROM dg_ledger_postings WHERE voucher_id = :id AND source = 'voucher'")
            ->execute(['id' => $voucherId]);
    }

    /**
     * Vorschau aus Belegdaten (ohne DB-Schreiben).
     *
     * @param array<string, mixed> $voucher
     * @return list<array<string, mixed>>
     */
    public static function previewPostings(array $voucher): array
    {
        return self::buildPostings($voucher);
    }

    /**
     * @param array<string, mixed> $voucher
     * @return list<array<string, mixed>>
     */
    private static function buildPostings(array $voucher): array
    {
        if (!VoucherDocumentKind::isBookable(
            (string) ($voucher['document_kind'] ?? ''),
            (string) ($voucher['voucher_type'] ?? 'expense')
        )) {
            return [];
        }

        $voucherId = (int) ($voucher['id'] ?? 0);
        $voucherType = (string) ($voucher['voucher_type'] ?? 'expense');
        $voucherDate = (string) ($voucher['voucher_date'] ?? date('Y-m-d'));
        $reverseCharge = trim((string) ($voucher['reverse_charge_type'] ?? '')) !== '';
        $skrType = ChartOfAccountsSettings::activeSkrType();
        $fiscalYear = (int) substr($voucherDate, 0, 4);
        if ($fiscalYear < 2000) {
            $fiscalYear = (int) date('Y');
        }

        $nextYear = $fiscalYear + 1;
        $nextDate = sprintf('%04d-01-01', $nextYear);

        $primarySide = LedgerAccounts::primarySide($voucherType);
        $contraSide = LedgerAccounts::oppositeSide($primarySide);
        $paymentStatus = (string) ($voucher['payment_status'] ?? 'open');
        $fallbackContra = LedgerAccounts::contraAccount($skrType, $voucherType, $paymentStatus);
        $contraAccount = PersonAccountService::contraForVoucher($voucher, $fallbackContra);
        $personAccount = $contraAccount !== $fallbackContra ? $contraAccount : '';
        $accrualAccount = VoucherAccrual::accrualAccount($voucherType, $skrType);
        $description = self::describe($voucher);
        $docFields = self::documentFields($voucher);

        $lines = self::journalLines($voucherId, $voucher, $reverseCharge);
        if ($lines === []) {
            return [];
        }

        $postings = [];
        $prevBookingAccount = null;

        foreach ($lines as $line) {
            $account = (string) $line['account_number'];
            if ($account === '') {
                continue;
            }
            $net = round((float) $line['net_amount'], 2);
            $tax = round((float) $line['tax_amount'], 2);
            $gross = round((float) $line['gross_amount'], 2);
            $rate = (int) $line['tax_rate'];
            $kind = (string) $line['kind'];
            $taxKey = self::taxKeyForLine($voucher, $rate, $reverseCharge);

            $primaryAmount = $net;
            $taxAccount = LedgerAccounts::taxAccount($skrType, $voucherType, $rate);
            $bookTaxSeparately = !$reverseCharge && $tax != 0.0 && self::accountExists($taxAccount, $skrType);
            if (!$bookTaxSeparately) {
                $primaryAmount = $gross;
            }

            [$primaryAmount, $linePrimarySide] = self::normalizeSignedAmount($primaryAmount, $primarySide);

            if ($primaryAmount != 0.0) {
                $postings[] = self::row(
                    $fiscalYear,
                    $voucherDate,
                    $voucherId,
                    $account,
                    $contraAccount,
                    $personAccount,
                    $linePrimarySide,
                    $primaryAmount,
                    $rate,
                    $taxKey,
                    $description,
                    $docFields
                );
            }
            if ($bookTaxSeparately) {
                [$taxAmount, $taxSide] = self::normalizeSignedAmount($tax, $primarySide);
                $postings[] = self::row(
                    $fiscalYear,
                    $voucherDate,
                    $voucherId,
                    $taxAccount,
                    $contraAccount,
                    $personAccount,
                    $taxSide,
                    $taxAmount,
                    $rate,
                    $taxKey,
                    $description,
                    $docFields
                );
            }

            if ($gross != 0.0) {
                [$grossAmount, $grossSide] = self::normalizeSignedAmount($gross, $contraSide);
                $postings[] = self::row(
                    $fiscalYear,
                    $voucherDate,
                    $voucherId,
                    $contraAccount,
                    $account,
                    $personAccount,
                    $grossSide,
                    $grossAmount,
                    0,
                    '',
                    $description,
                    $docFields
                );
            }

            if ($kind === VoucherReverseCharge::LINE_BOOKING) {
                $prevBookingAccount = $account;
                continue;
            }

            if ($kind === VoucherAccrual::LINE_ACCRUAL && $net != 0.0) {
                $target = $prevBookingAccount ?? $account;
                if ($target !== $accrualAccount) {
                    $releaseDesc = 'ARAP-Auflösung ' . $fiscalYear . ' → ' . $nextYear
                        . ($description !== '' ? ' · ' . $description : '');
                    $postings[] = self::row($nextYear, $nextDate, $voucherId, $target, $accrualAccount, '', $primarySide, $net, $rate, $taxKey, $releaseDesc, $docFields);
                    $postings[] = self::row($nextYear, $nextDate, $voucherId, $accrualAccount, $target, '', $contraSide, $net, 0, '', $releaseDesc, $docFields);
                }
            }
        }

        $postings = array_merge($postings, self::skontoPostings($voucher, $fiscalYear, $voucherDate, $voucherId, $contraAccount, $personAccount, $docFields, $description, $skrType));

        return $postings;
    }

    /**
     * @param array<string, mixed> $voucher
     * @return list<array<string, mixed>>
     */
    private static function skontoPostings(
        array $voucher,
        int $fiscalYear,
        string $voucherDate,
        int $voucherId,
        string $contraAccount,
        string $personAccount,
        array $docFields,
        string $description,
        string $skrType
    ): array {
        $discount = round((float) ($voucher['discount_amount'] ?? 0), 2);
        if ($discount <= 0.0) {
            $gross = round((float) ($voucher['gross_amount'] ?? 0), 2);
            $paid = round((float) ($voucher['paid_amount'] ?? 0), 2);
            if ($paid > 0.0 && $paid < $gross) {
                $discount = round($gross - $paid, 2);
            }
        }
        if ($discount <= 0.0) {
            return [];
        }

        $paymentStatus = VoucherPaymentStatus::sanitize((string) ($voucher['payment_status'] ?? ''));
        if (VoucherPaymentStatus::isOpen($paymentStatus)) {
            return [];
        }

        $voucherType = (string) ($voucher['voucher_type'] ?? 'expense');
        $skontoAccount = LedgerAccounts::skontoAccount($skrType, $voucherType);
        $isIncome = LedgerAccounts::isIncomeDirection($voucherType);
        $rate = (int) ($voucher['tax_rate'] ?? 19);
        if ($rate <= 0) {
            $rate = 19;
        }
        $taxKey = self::taxKeyForLine($voucher, $rate, false);
        $taxAccount = LedgerAccounts::taxAccount($skrType, $voucherType, $rate);
        $hasTax = $rate > 0 && self::accountExists($taxAccount, $skrType);

        $netDiscount = $discount;
        $taxDiscount = 0.0;
        if ($hasTax) {
            $netDiscount = round($discount / (1 + ($rate / 100)), 2);
            $taxDiscount = round($discount - $netDiscount, 2);
        }

        $skontoDesc = 'Skonto ' . $description;
        $postings = [];

        if ($isIncome) {
            $postings[] = self::row($fiscalYear, $voucherDate, $voucherId, $contraAccount, $skontoAccount, $personAccount, 'debit', $discount, 0, '', $skontoDesc, $docFields);
            $postings[] = self::row($fiscalYear, $voucherDate, $voucherId, $skontoAccount, $contraAccount, $personAccount, 'credit', $netDiscount, $rate, $taxKey, $skontoDesc, $docFields);
            if ($taxDiscount > 0.0) {
                $postings[] = self::row($fiscalYear, $voucherDate, $voucherId, $taxAccount, $contraAccount, $personAccount, 'debit', $taxDiscount, $rate, $taxKey, $skontoDesc, $docFields);
            }
        } else {
            $postings[] = self::row($fiscalYear, $voucherDate, $voucherId, $contraAccount, $skontoAccount, $personAccount, 'credit', $discount, 0, '', $skontoDesc, $docFields);
            $postings[] = self::row($fiscalYear, $voucherDate, $voucherId, $skontoAccount, $contraAccount, $personAccount, 'debit', $netDiscount, $rate, $taxKey, $skontoDesc, $docFields);
            if ($taxDiscount > 0.0) {
                $postings[] = self::row($fiscalYear, $voucherDate, $voucherId, $taxAccount, $contraAccount, $personAccount, 'credit', $taxDiscount, $rate, $taxKey, $skontoDesc, $docFields);
            }
        }

        return $postings;
    }

    /**
     * @param array<string, mixed> $voucher
     */
    private static function taxKeyForLine(array $voucher, int $rate, bool $reverseCharge): string
    {
        $key = VoucherTaxKeys::sanitizeTaxKey((string) ($voucher['tax_key'] ?? ''));
        if ($key !== '') {
            return $key;
        }
        if ($reverseCharge) {
            return VoucherTaxKeys::KEY_REVERSE_CHARGE;
        }
        $isIncome = LedgerAccounts::isIncomeDirection((string) ($voucher['voucher_type'] ?? 'expense'));
        if ($rate === 0) {
            return VoucherTaxKeys::KEY_ZERO;
        }
        if ($isIncome) {
            return $rate === 7 ? VoucherTaxKeys::KEY_UST_7 : VoucherTaxKeys::KEY_UST_19;
        }

        return $rate === 7 ? VoucherTaxKeys::KEY_VST_7 : VoucherTaxKeys::KEY_VST_19;
    }

    /**
     * @param array<string, mixed> $voucher
     * @return array{field1: string, field2: string}
     */
    private static function documentFields(array $voucher): array
    {
        $field1 = trim((string) ($voucher['invoice_number'] ?? ''));
        $field2 = trim((string) ($voucher['supplier_name'] ?? ''));
        $contactId = (int) ($voucher['contact_id'] ?? 0);
        if ($contactId > 0) {
            $contact = ContactRepository::findById($contactId);
            if ($contact !== null) {
                if ($field2 === '') {
                    $field2 = trim($contact->companyName) !== '' ? trim($contact->companyName) : trim($contact->displayName);
                }
                $isIncome = LedgerAccounts::isIncomeDirection((string) ($voucher['voucher_type'] ?? 'expense'));
                $number = $isIncome ? trim($contact->customerNumber) : trim($contact->supplierNumber);
                if ($number !== '' && $field2 === '') {
                    $field2 = $number;
                }
            }
        }

        return [
            'field1' => mb_substr($field1, 0, 36),
            'field2' => mb_substr($field2, 0, 36),
        ];
    }

    /**
     * @param int $voucherId Beleg-ID
     * @param array<string, mixed> $voucher Belegdaten
     */
    private static function journalLines(int $voucherId, array $voucher, bool $reverseCharge): array
    {
        if ($voucherId > 0) {
            $lines = VoucherRepository::linesForVoucher($voucherId, false);
        } else {
            $lines = is_array($voucher['lines'] ?? null) ? $voucher['lines'] : [];
        }

        $normalized = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $kind = (string) ($line['line_kind'] ?? VoucherReverseCharge::LINE_BOOKING);
            if ($reverseCharge) {
                if ($kind !== VoucherReverseCharge::LINE_BOOKING) {
                    continue;
                }
            } elseif (!in_array($kind, [VoucherReverseCharge::LINE_BOOKING, VoucherAccrual::LINE_ACCRUAL], true)) {
                continue;
            }
            $account = preg_replace('/\s+/', '', (string) ($line['account_number'] ?? '')) ?? '';
            if ($account === '') {
                continue;
            }
            $normalized[] = [
                'account_number' => $account,
                'net_amount' => self::money($line['net_amount'] ?? 0),
                'tax_amount' => self::money($line['tax_amount'] ?? 0),
                'gross_amount' => self::money($line['gross_amount'] ?? 0),
                'tax_rate' => (int) ($line['tax_rate'] ?? 0),
                'kind' => $kind,
            ];
        }

        if ($normalized !== []) {
            return $normalized;
        }

        $account = preg_replace('/\D/', '', (string) ($voucher['account_number'] ?? '')) ?? '';
        if ($account === '') {
            return [];
        }

        return [[
            'account_number' => $account,
            'net_amount' => round((float) ($voucher['net_amount'] ?? 0), 2),
            'tax_amount' => round((float) ($voucher['tax_amount'] ?? 0), 2),
            'gross_amount' => round((float) ($voucher['gross_amount'] ?? 0), 2),
            'tax_rate' => (int) ($voucher['tax_rate'] ?? 0),
            'kind' => VoucherReverseCharge::LINE_BOOKING,
        ]];
    }

    private static function money(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }
        $str = trim((string) $value);
        if ($str === '') {
            return 0.0;
        }
        if (str_contains($str, ',')) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        }

        return round((float) $str, 2);
    }

    /**
     * @param array<string, mixed> $voucher
     */
    private static function describe(array $voucher): string
    {
        $parts = [];
        $invoice = trim((string) ($voucher['invoice_number'] ?? ''));
        if ($invoice !== '') {
            $parts[] = 'RE ' . $invoice;
        }
        $desc = trim((string) ($voucher['description'] ?? ''));
        if ($desc === '') {
            $desc = trim((string) ($voucher['supplier_name'] ?? ''));
        }
        if ($desc !== '') {
            $parts[] = $desc;
        }

        return mb_substr(implode(' · ', $parts), 0, 500);
    }

    /**
     * Negative Beträge (Rabattzeilen) → Betrag positiv, Buchungsseite invertiert.
     *
     * @return array{0: float, 1: string}
     */
    private static function normalizeSignedAmount(float $amount, string $side): array
    {
        if ($amount < 0) {
            return [abs($amount), LedgerAccounts::oppositeSide($side)];
        }

        return [$amount, $side];
    }

    /**
     * @param array{field1: string, field2: string} $docFields
     * @return array<string, mixed>
     */
    private static function row(
        int $year,
        string $date,
        int $voucherId,
        string $account,
        string $contra,
        string $personAccount,
        string $side,
        float $amount,
        int $rate,
        string $taxKey,
        string $description,
        array $docFields
    ): array {
        return [
            'fiscal_year' => $year,
            'posting_date' => $date,
            'voucher_id' => $voucherId > 0 ? $voucherId : null,
            'account_number' => $account,
            'contra_account' => mb_substr($contra, 0, 16),
            'person_account' => mb_substr($personAccount, 0, 8),
            'side' => $side === 'credit' ? 'credit' : 'debit',
            'amount' => round(abs($amount), 2),
            'tax_rate' => max(0, $rate),
            'tax_key' => VoucherTaxKeys::sanitizeTaxKey($taxKey),
            'description' => $description,
            'document_field1' => $docFields['field1'],
            'document_field2' => $docFields['field2'],
            'source' => 'voucher',
        ];
    }

    private static function accountExists(string $accountNumber, string $skrType): bool
    {
        if ($accountNumber === '') {
            return false;
        }
        try {
            return ChartAccountRepository::findByNumber($accountNumber, $skrType) !== null;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function loadVoucher(int $voucherId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_vouchers WHERE id = :id');
        $stmt->execute(['id' => $voucherId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
