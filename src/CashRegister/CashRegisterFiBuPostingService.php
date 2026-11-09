<?php
declare(strict_types=1);

/**
 * FiBu-Buchungen aus festgeschriebenen Registerkassen-Bons (K15).
 *
 * K15b: Bar (+ FiBu-Kassenbuch) · K15c: EC/Karte · K15d: Gutschein · Storno → K15e.
 */
final class CashRegisterFiBuPostingService
{
    public const SOURCE = 'cash_register';

    /** @var list<string> */
    public const METHODS_CASH = ['cash'];

    /** @var list<string> */
    public const METHODS_EC_CARD = ['ec', 'card'];

    /** @var list<string> */
    public const METHODS_VOUCHER = ['voucher'];

    /**
     * Aktuell buchbare Zahlarten (Mini-IDs schalten nach und nach dazu).
     *
     * @return list<string>
     */
    public static function bookableMethods(): array
    {
        return array_values(array_merge(
            self::METHODS_CASH,
            self::METHODS_EC_CARD,
            self::METHODS_VOUCHER
        ));
    }

    public static function documentMarker(int $receiptId): string
    {
        return 'cr:' . max(0, $receiptId);
    }

    /**
     * Nach Bon-Finish: buchbare Zahlarten ins Journal (+ Bar → FiBu-Kassenbuch).
     *
     * @return int|null manual_batch_id oder null wenn nichts zu buchen
     */
    public static function postForFinalizedReceipt(int $receiptId, ?int $userId = null): ?int
    {
        if ($receiptId <= 0 || !Database::isConfigured()) {
            return null;
        }
        if (!CashReceiptRepository::tableExists() || !CashPaymentRepository::tableExists()) {
            return null;
        }

        $receipt = CashReceiptRepository::findById($receiptId);
        if ($receipt === null) {
            throw new RuntimeException('Bon für FiBu-Buchung nicht gefunden.');
        }
        if (($receipt['status'] ?? '') !== CashReceiptService::STATUS_FINALIZED) {
            throw new RuntimeException('Nur festgeschriebene Bons werden gebucht.');
        }

        $receiptType = (string) ($receipt['receipt_type'] ?? CashReceiptService::TYPE_SALE);
        if ($receiptType === CashReceiptService::TYPE_STORNO) {
            return self::postStornoForFinalizedReceipt($receiptId, $receipt, $userId);
        }
        if ($receiptType !== CashReceiptService::TYPE_SALE) {
            return null;
        }

        $lines = CashReceiptRepository::listLines($receiptId);
        $payments = CashPaymentRepository::listByReceipt($receiptId);
        $marker = self::documentMarker($receiptId);
        $existing = ManualLedgerService::findBatchIdBySourceMarker(self::SOURCE, $marker);

        $batchId = $existing;
        if ($batchId === null) {
            $skr = ChartOfAccountsSettings::activeSkrType();
            $journalLines = self::buildJournalLines($receipt, $lines, $payments, $skr, $marker);
            if ($journalLines !== []) {
                $postingDate = self::postingDate($receipt);
                $display = trim((string) ($receipt['receipt_number_display'] ?? ''));
                if ($display === '') {
                    $display = (string) (int) ($receipt['receipt_number'] ?? $receiptId);
                }
                $description = 'Registerkasse Bon ' . $display . ' (' . self::describeBookedMethods($payments) . ')';
                $batchId = ManualLedgerService::createBatch(
                    $postingDate,
                    $description,
                    $journalLines,
                    $userId,
                    self::SOURCE
                );
            }
        }

        // K15b: Bar zusätzlich ins FiBu-Kassenbuch (nicht Register-Tagesabschluss)
        CashJournalRepository::syncForCashReceipt($receiptId);

        return $batchId;
    }

    /**
     * K15e — Storno-Gegenbuchung (Seiten tauschen) + Kassenbuch-Ausgang.
     *
     * @param array<string, mixed> $receipt
     */
    public static function postStornoForFinalizedReceipt(int $receiptId, array $receipt, ?int $userId = null): ?int
    {
        $originalId = (int) ($receipt['storno_of_receipt_id'] ?? 0);
        $lines = CashReceiptRepository::listLines($receiptId);
        $payments = CashPaymentRepository::listByReceipt($receiptId);
        $marker = self::documentMarker($receiptId);
        $existing = ManualLedgerService::findBatchIdBySourceMarker(self::SOURCE, $marker);
        $batchId = $existing;

        if ($batchId === null) {
            $skr = ChartOfAccountsSettings::activeSkrType();
            // Absolute Beträge wie Verkauf, dann Seiten tauschen
            $absReceipt = $receipt;
            $absReceipt['total_gross'] = abs(round((float) ($receipt['total_gross'] ?? 0), 2));
            $absLines = [];
            foreach ($lines as $line) {
                $copy = $line;
                $copy['gross_amount'] = abs(round((float) ($line['gross_amount'] ?? 0), 2));
                $copy['quantity'] = abs(round((float) ($line['quantity'] ?? 0), 3));
                $absLines[] = $copy;
            }
            $absPayments = [];
            foreach ($payments as $pay) {
                $copy = $pay;
                $copy['amount'] = abs(round((float) ($pay['amount'] ?? 0), 2));
                $absPayments[] = $copy;
            }
            $saleLines = self::buildJournalLines($absReceipt, $absLines, $absPayments, $skr, $marker);
            $journalLines = [];
            foreach ($saleLines as $jl) {
                $side = strtolower((string) ($jl['side'] ?? ''));
                $jl['side'] = $side === 'debit' ? 'credit' : 'debit';
                $journalLines[] = $jl;
            }
            if ($journalLines !== []) {
                $postingDate = self::postingDate($receipt);
                $display = trim((string) ($receipt['receipt_number_display'] ?? ''));
                if ($display === '') {
                    $display = (string) (int) ($receipt['receipt_number'] ?? $receiptId);
                }
                $origLabel = $originalId > 0 ? (' zu Bon-ID ' . $originalId) : '';
                $description = 'Registerkasse Storno ' . $display . $origLabel;
                $batchId = ManualLedgerService::createBatch(
                    $postingDate,
                    $description,
                    $journalLines,
                    $userId,
                    self::SOURCE
                );
            }
        }

        self::syncCashJournalForStorno($receiptId, $receipt);

        return $batchId;
    }

    /**
     * @param array<string, mixed> $receipt
     */
    /**
     * @param array<string, mixed> $receipt
     */
    private static function syncCashJournalForStorno(int $receiptId, array $receipt): void
    {
        unset($receipt);
        if (!class_exists('CashJournalRepository')) {
            return;
        }
        try {
            CashJournalRepository::syncForCashStornoReceipt($receiptId);
        } catch (Throwable) {
            // Spalte ggf. noch nicht da
        }
    }

    /**
     * Baut ausgeglichene Journalzeilen für buchbare Zahlarten (ohne DB-Schreiben).
     *
     * @param array<string, mixed> $receipt
     * @param list<array<string, mixed>> $lines
     * @param list<array<string, mixed>> $payments
     * @return list<array{account_number: string, side: string, amount: float, tax_key?: string, description?: string, contra_account?: string, document_field1?: string}>
     */
    public static function buildJournalLines(
        array $receipt,
        array $lines,
        array $payments,
        string $skrType,
        string $marker = ''
    ): array {
        $skr = ChartOfAccountsSettings::sanitizeSkrType($skrType);
        $bookable = self::bookableMethods();
        $byMethod = [];
        foreach ($payments as $pay) {
            $method = strtolower(trim((string) ($pay['payment_method'] ?? '')));
            if (!in_array($method, $bookable, true)) {
                continue;
            }
            $amount = round((float) ($pay['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }
            if (!isset($byMethod[$method])) {
                $byMethod[$method] = 0.0;
            }
            $byMethod[$method] = round($byMethod[$method] + $amount, 2);
        }
        if ($byMethod === []) {
            return [];
        }

        $bookGross = 0.0;
        foreach ($byMethod as $amount) {
            $bookGross = round($bookGross + $amount, 2);
        }
        if ($bookGross <= 0) {
            return [];
        }

        $totalGross = round((float) ($receipt['total_gross'] ?? 0), 2);
        if ($totalGross <= 0) {
            $totalGross = 0.0;
            foreach ($lines as $line) {
                $totalGross = round($totalGross + (float) ($line['gross_amount'] ?? 0), 2);
            }
        }
        if ($totalGross <= 0) {
            return [];
        }

        $ratio = $bookGross / $totalGross;
        if ($ratio > 1.0001) {
            throw new RuntimeException('Buchbare Zahlungen übersteigen den Bon-Bruttobetrag.');
        }

        /** @var array<int, array{rate: int, gross: float, net: float, tax: float}> $buckets */
        $buckets = [];
        foreach ($lines as $line) {
            $g = round((float) ($line['gross_amount'] ?? 0), 2);
            if ($g <= 0) {
                continue;
            }
            $rate = (int) ($line['tax_rate'] ?? 19);
            $net = self::netFromGross($g, $rate);
            $tax = round($g - $net, 2);
            if (!isset($buckets[$rate])) {
                $buckets[$rate] = ['rate' => $rate, 'gross' => 0.0, 'net' => 0.0, 'tax' => 0.0];
            }
            $buckets[$rate]['gross'] = round($buckets[$rate]['gross'] + $g, 2);
            $buckets[$rate]['net'] = round($buckets[$rate]['net'] + $net, 2);
            $buckets[$rate]['tax'] = round($buckets[$rate]['tax'] + $tax, 2);
        }
        if ($buckets === []) {
            return [];
        }

        $labels = CashPaymentRepository::methodLabels();
        $out = [];
        $moneyAccounts = [];
        foreach ($byMethod as $method => $amount) {
            $account = CashRegisterFiBuSettings::accountForMethod($method, $skr);
            $moneyAccounts[$method] = $account;
            $out[] = [
                'account_number' => $account,
                'side' => 'debit',
                'amount' => $amount,
                'description' => (string) ($labels[$method] ?? $method),
                'document_field1' => $marker,
            ];
        }

        // Haben: anteilige Erlöse + USt je Steuersatz
        $creditTarget = $bookGross;
        $creditSum = 0.0;
        $bucketList = array_values($buckets);
        $lastIdx = count($bucketList) - 1;
        foreach ($bucketList as $idx => $bucket) {
            $rate = (int) $bucket['rate'];
            $shareNet = round((float) $bucket['net'] * $ratio, 2);
            $shareTax = round((float) $bucket['tax'] * $ratio, 2);
            if ($idx === $lastIdx) {
                $remain = round($creditTarget - $creditSum, 2);
                $shareNet = round($remain - $shareTax, 2);
                if ($shareNet < 0) {
                    $shareTax = round($shareTax + $shareNet, 2);
                    $shareNet = 0.0;
                }
            }
            $revenue = LedgerAccounts::revenueAccount($skr, $rate);
            $primaryMoney = reset($moneyAccounts) ?: '';
            if ($shareNet > 0) {
                $out[] = [
                    'account_number' => $revenue,
                    'side' => 'credit',
                    'amount' => $shareNet,
                    'contra_account' => $primaryMoney,
                    'description' => 'Erlöse ' . $rate . ' %',
                    'document_field1' => $marker,
                ];
                $creditSum = round($creditSum + $shareNet, 2);
            }
            if ($shareTax > 0) {
                $taxAccount = LedgerAccounts::taxAccount($skr, 'income', $rate);
                $out[] = [
                    'account_number' => $taxAccount,
                    'side' => 'credit',
                    'amount' => $shareTax,
                    'contra_account' => $primaryMoney,
                    'description' => 'USt ' . $rate . ' %',
                    'document_field1' => $marker,
                ];
                $creditSum = round($creditSum + $shareTax, 2);
            }
        }

        return $out;
    }

    private static function postingDate(array $receipt): string
    {
        $finished = trim((string) ($receipt['finished_at'] ?? ''));
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $finished, $m) === 1) {
            return $m[1];
        }
        $sessionId = (int) ($receipt['session_id'] ?? 0);
        if ($sessionId > 0 && CashSessionRepository::tableExists()) {
            $session = CashSessionRepository::findById($sessionId);
            $bd = trim((string) ($session['business_date'] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $bd) === 1) {
                return $bd;
            }
        }

        return date('Y-m-d');
    }

    private static function netFromGross(float $gross, int $taxRate): float
    {
        if ($taxRate <= 0) {
            return round($gross, 2);
        }

        return round($gross / (1 + $taxRate / 100), 2);
    }

    /**
     * @param list<array<string, mixed>> $payments
     */
    private static function describeBookedMethods(array $payments): string
    {
        $bookable = self::bookableMethods();
        $labels = CashPaymentRepository::methodLabels();
        $used = [];
        foreach ($payments as $pay) {
            $method = strtolower(trim((string) ($pay['payment_method'] ?? '')));
            if (!in_array($method, $bookable, true)) {
                continue;
            }
            if (round((float) ($pay['amount'] ?? 0), 2) <= 0) {
                continue;
            }
            $used[$method] = (string) ($labels[$method] ?? $method);
        }
        if ($used === []) {
            return 'Zahlung';
        }

        return implode('/', array_values($used));
    }
}
