<?php
declare(strict_types=1);

/**
 * Erzeugt aus einem Beleg balancierte Journalbuchungen (Soll = Haben) im
 * dg_ledger_postings-Journal. Modell: Netto auf Aufwand/Ertrag, Steuer auf
 * Vorsteuer/Umsatzsteuer, Brutto auf das Geldkonto/Gegenkonto (je Zahlungsart).
 *
 * Bewusst EÜR-orientiert und robust: Konten müssen nicht zwingend im Kontenrahmen
 * existieren; fehlende Steuerkonten werden in die Primärzeile eingerechnet.
 */
final class LedgerPostingService
{
    /** Journal für genau einen Beleg neu aufbauen (idempotent). */
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

        $postings = self::buildPostings($voucher);
        if ($postings === []) {
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_ledger_postings
                (fiscal_year, posting_date, voucher_id, account_number, contra_account, side, amount, tax_rate, description, source)
             VALUES
                (:fiscal_year, :posting_date, :voucher_id, :account_number, :contra_account, :side, :amount, :tax_rate, :description, :source)'
        );
        foreach ($postings as $p) {
            $stmt->execute($p);
        }
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
     * @param array<string, mixed> $voucher
     * @return list<array<string, mixed>>
     */
    private static function buildPostings(array $voucher): array
    {
        $voucherId = (int) ($voucher['id'] ?? 0);
        $voucherType = (string) ($voucher['voucher_type'] ?? 'expense');
        $voucherDate = (string) ($voucher['voucher_date'] ?? date('Y-m-d'));
        $paymentStatus = (string) ($voucher['payment_status'] ?? 'open');
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
        $contraAccount = LedgerAccounts::contraAccount($skrType, $voucherType, $paymentStatus);
        $accrualAccount = VoucherAccrual::accrualAccount($voucherType, $skrType);
        $description = self::describe($voucher);

        $lines = self::journalLines($voucherId, $voucher, $reverseCharge);
        if ($lines === []) {
            return [];
        }

        $distinctPrimary = array_unique(array_map(static fn (array $l): string => (string) $l['account_number'], $lines));
        $singleAccount = count($distinctPrimary) === 1 ? (string) reset($distinctPrimary) : 'Sammel';

        $postings = [];
        $totalGross = 0.0;
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
            $totalGross = round($totalGross + $gross, 2);

            $primaryAmount = $net;
            $taxAccount = LedgerAccounts::taxAccount($skrType, $voucherType, $rate);
            $bookTaxSeparately = !$reverseCharge && $tax > 0.0 && self::accountExists($taxAccount, $skrType);
            if (!$bookTaxSeparately) {
                $primaryAmount = $gross; // Steuer in Primärzeile einrechnen
            }

            if ($primaryAmount != 0.0) {
                $postings[] = self::row($fiscalYear, $voucherDate, $voucherId, $account, $contraAccount, $primarySide, $primaryAmount, $rate, $description);
            }
            if ($bookTaxSeparately) {
                $postings[] = self::row($fiscalYear, $voucherDate, $voucherId, $taxAccount, $contraAccount, $primarySide, $tax, $rate, $description);
            }

            if ($kind === VoucherReverseCharge::LINE_BOOKING) {
                $prevBookingAccount = $account;
                continue;
            }

            // Rechnungsabgrenzung: Folgejahr-Anteil (netto) im neuen Jahr auf den
            // ursprünglichen Aufwand umbuchen (Auflösung des ARAP-Bestandskontos).
            if ($kind === VoucherAccrual::LINE_ACCRUAL && $net != 0.0) {
                $target = $prevBookingAccount ?? $account;
                if ($target !== $accrualAccount) {
                    $releaseDesc = 'ARAP-Auflösung ' . $fiscalYear . ' → ' . $nextYear
                        . ($description !== '' ? ' · ' . $description : '');
                    $postings[] = self::row($nextYear, $nextDate, $voucherId, $target, $accrualAccount, $primarySide, $net, $rate, $releaseDesc);
                    $postings[] = self::row($nextYear, $nextDate, $voucherId, $accrualAccount, $target, $contraSide, $net, 0, $releaseDesc);
                }
            }
        }

        if ($totalGross != 0.0) {
            $postings[] = self::row($fiscalYear, $voucherDate, $voucherId, $contraAccount, $singleAccount, $contraSide, $totalGross, 0, $description);
        }

        return $postings;
    }

    /**
     * Journalrelevante Zeilen des Belegs (Aufwand/Ertrag + Rechnungsabgrenzung).
     * Bei Reverse Charge nur die Buchungszeilen (System-Steuerzeilen bleiben außen vor).
     * Fällt auf das Kopf-Konto zurück, wenn keine Zeilen existieren.
     *
     * @param array<string, mixed> $voucher
     * @return list<array<string, mixed>>
     */
    private static function journalLines(int $voucherId, array $voucher, bool $reverseCharge): array
    {
        $lines = VoucherRepository::linesForVoucher($voucherId, false);
        $normalized = [];
        foreach ($lines as $line) {
            $kind = (string) ($line['line_kind'] ?? VoucherReverseCharge::LINE_BOOKING);
            if ($reverseCharge) {
                if ($kind !== VoucherReverseCharge::LINE_BOOKING) {
                    continue; // 13b-System-Steuerzeilen nicht als Geldbewegung buchen
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

    /** Deutsche/englische Zahl robust in float (2 Nachkommastellen). */
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

    /** @param array<string, mixed> $voucher */
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

    /** @return array<string, mixed> */
    private static function row(int $year, string $date, int $voucherId, string $account, string $contra, string $side, float $amount, int $rate, string $description): array
    {
        return [
            'fiscal_year' => $year,
            'posting_date' => $date,
            'voucher_id' => $voucherId,
            'account_number' => $account,
            'contra_account' => mb_substr($contra, 0, 16),
            'side' => $side === 'credit' ? 'credit' : 'debit',
            'amount' => round(abs($amount), 2),
            'tax_rate' => max(0, $rate),
            'description' => $description,
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

    /** @return array<string, mixed>|null */
    private static function loadVoucher(int $voucherId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_vouchers WHERE id = :id');
        $stmt->execute(['id' => $voucherId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
