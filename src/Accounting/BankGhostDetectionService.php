<?php
declare(strict_types=1);

/** Erkennung von Geisterumsätzen: bereits verbuchte oder doppelte Bankzeilen. */
final class BankGhostDetectionService
{
    /**
     * @return array{open: list<array<string, mixed>>, ghosts: list<array<string, mixed>>}
     */
    public static function classifyOpenTransactions(): array
    {
        $open = BankTransactionRepository::list('open');
        $realOpen = [];
        $ghosts = [];

        foreach ($open as $tx) {
            $ghost = self::detectGhost($tx);
            if ($ghost === null) {
                $realOpen[] = $tx;
                continue;
            }

            $tx['ghost_reason'] = $ghost['reason'];
            $tx['ghost_label'] = $ghost['label'];
            $tx['ghost_detail'] = $ghost['detail'];
            $tx['ghost_voucher_id'] = $ghost['voucher_id'] ?? null;
            $tx['ghost_payment_id'] = $ghost['payment_id'] ?? null;
            $tx['ghost_duplicate_id'] = $ghost['duplicate_id'] ?? null;
            $ghosts[] = $tx;
        }

        return ['open' => $realOpen, 'ghosts' => $ghosts];
    }

    /**
     * @param array<string, mixed> $tx
     * @return array{reason: string, label: string, detail: string, voucher_id?: int, payment_id?: int, duplicate_id?: int}|null
     */
    public static function detectGhost(array $tx): ?array
    {
        $txId = (int) ($tx['id'] ?? 0);
        $fingerprint = BankTransactionRepository::fingerprintFor($tx);
        if ($fingerprint !== '') {
            $duplicate = BankTransactionRepository::findByFingerprint($fingerprint, $txId > 0 ? $txId : null);
            if ($duplicate !== null) {
                $status = (string) ($duplicate['match_status'] ?? '');
                $duplicateId = (int) ($duplicate['id'] ?? 0);
                if ($status === 'matched') {
                    $voucherId = (int) ($duplicate['matched_voucher_id'] ?? 0);

                    return [
                        'reason' => 'duplicate_matched',
                        'label' => 'Bereits zugeordnet',
                        'detail' => $voucherId > 0
                            ? 'Dieser Umsatz wurde bereits mit Beleg #' . $voucherId . ' abgeglichen.'
                            : 'Dieser Umsatz wurde bereits importiert und zugeordnet.',
                        'duplicate_id' => $duplicateId,
                        'voucher_id' => $voucherId > 0 ? $voucherId : null,
                    ];
                }
                if ($status === 'ignored') {
                    return [
                        'reason' => 'duplicate_ignored',
                        'label' => 'Bereits ausgeblendet',
                        'detail' => 'Dieser Umsatz wurde bereits importiert und ausgeblendet.',
                        'duplicate_id' => $duplicateId,
                    ];
                }
                if ($status === 'open' && $duplicateId !== $txId) {
                    return [
                        'reason' => 'duplicate_open',
                        'label' => 'Doppelter Import',
                        'detail' => 'Derselbe Umsatz liegt bereits als offener Posten vor.',
                        'duplicate_id' => $duplicateId,
                    ];
                }
            }
        }

        $paymentMatch = self::findMatchingPayment($tx);
        if ($paymentMatch !== null) {
            $voucherId = (int) ($paymentMatch['voucher_id'] ?? 0);
            $invoice = trim((string) ($paymentMatch['invoice_number'] ?? ''));

            return [
                'reason' => 'payment_exists',
                'label' => 'Zahlung bereits erfasst',
                'detail' => $invoice !== ''
                    ? 'Passende Zahlung zu Beleg ' . $invoice . ' ist bereits verbucht.'
                    : 'Passende Zahlung zu Beleg #' . $voucherId . ' ist bereits verbucht.',
                'voucher_id' => $voucherId > 0 ? $voucherId : null,
                'payment_id' => (int) ($paymentMatch['id'] ?? 0) ?: null,
            ];
        }

        $voucherMatch = self::findSettledVoucher($tx);
        if ($voucherMatch !== null) {
            $voucherId = (int) ($voucherMatch['id'] ?? 0);
            $invoice = trim((string) ($voucherMatch['invoice_number'] ?? ''));

            return [
                'reason' => 'voucher_settled',
                'label' => 'Beleg bereits ausgeglichen',
                'detail' => $invoice !== ''
                    ? 'Beleg ' . $invoice . ' ist bereits als bezahlt markiert.'
                    : 'Beleg #' . $voucherId . ' ist bereits als bezahlt markiert.',
                'voucher_id' => $voucherId > 0 ? $voucherId : null,
            ];
        }

        return null;
    }

    /**
     * Verknüpft einen Geisterumsatz mit bestehender Zahlung/Beleg — ohne Doppelbuchung.
     */
    public static function linkExistingSettlement(int $transactionId): void
    {
        $tx = BankTransactionRepository::findById($transactionId);
        if ($tx === null) {
            throw new RuntimeException('Bankumsatz nicht gefunden.');
        }
        if ((string) ($tx['match_status'] ?? '') !== 'open') {
            throw new RuntimeException('Nur offene Umsätze können verknüpft werden.');
        }

        $ghost = self::detectGhost($tx);
        if ($ghost === null) {
            throw new RuntimeException('Kein passender Geisterumsatz erkannt.');
        }

        $voucherId = (int) ($ghost['voucher_id'] ?? 0);
        $paymentId = (int) ($ghost['payment_id'] ?? 0);
        if ($voucherId < 1 && $paymentId > 0) {
            $payment = self::findPaymentById($paymentId);
            $voucherId = (int) ($payment['voucher_id'] ?? 0);
        }
        if ($voucherId < 1 && !empty($ghost['duplicate_id'])) {
            $duplicate = BankTransactionRepository::findById((int) $ghost['duplicate_id']);
            $voucherId = (int) ($duplicate['matched_voucher_id'] ?? 0);
        }
        if ($voucherId < 1) {
            throw new RuntimeException('Kein passender Beleg für die Verknüpfung gefunden.');
        }

        if ($paymentId < 1) {
            $paymentMatch = self::findMatchingPayment($tx);
            if ($paymentMatch !== null && (int) ($paymentMatch['voucher_id'] ?? 0) === $voucherId) {
                $paymentId = (int) ($paymentMatch['id'] ?? 0);
            }
        }

        MigrationRunner::runPending();
        $pdo = Database::pdo();

        if ($paymentId > 0) {
            $stmt = $pdo->prepare(
                'UPDATE dg_voucher_payments
                 SET bank_transaction_id = :tx_id
                 WHERE id = :payment_id AND (bank_transaction_id IS NULL OR bank_transaction_id = 0)'
            );
            $stmt->execute(['tx_id' => $transactionId, 'payment_id' => $paymentId]);
        }

        BankTransactionRepository::markMatched($transactionId, $voucherId);
    }

    public static function hideGhost(int $transactionId): void
    {
        $tx = BankTransactionRepository::findById($transactionId);
        if ($tx === null) {
            throw new RuntimeException('Bankumsatz nicht gefunden.');
        }
        if (self::detectGhost($tx) === null) {
            throw new RuntimeException('Dieser Umsatz ist kein Geisterumsatz.');
        }
        BankTransactionRepository::markIgnored($transactionId);
    }

    /**
     * @return int Anzahl ausgeblendeter Geisterumsätze
     */
    public static function hideAllGhosts(): int
    {
        $classified = self::classifyOpenTransactions();
        $count = 0;
        foreach ($classified['ghosts'] as $ghost) {
            $id = (int) ($ghost['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            BankTransactionRepository::markIgnored($id);
            $count++;
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $tx
     * @return array<string, mixed>|null
     */
    private static function findMatchingPayment(array $tx): ?array
    {
        if (!Database::isConfigured()) {
            return null;
        }

        $amount = round(abs((float) ($tx['amount'] ?? 0)), 2);
        if ($amount <= 0.0) {
            return null;
        }

        $txDate = (string) ($tx['transaction_date'] ?? '');
        if ($txDate === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $txDate) !== 1) {
            return null;
        }

        $dateFrom = date('Y-m-d', strtotime($txDate . ' -21 days'));
        $dateTo = date('Y-m-d', strtotime($txDate . ' +21 days'));
        $reference = mb_strtolower((string) ($tx['reference_text'] ?? '') . ' ' . (string) ($tx['end_to_end_id'] ?? ''));

        $stmt = Database::pdo()->prepare(
            "SELECT vp.*, v.invoice_number, v.payment_status
             FROM dg_voucher_payments vp
             INNER JOIN dg_vouchers v ON v.id = vp.voucher_id
             WHERE ABS(vp.amount - :amount) <= 0.02
               AND vp.payment_date BETWEEN :date_from AND :date_to
               AND (vp.bank_transaction_id IS NULL OR vp.bank_transaction_id = 0)
             ORDER BY ABS(DATEDIFF(vp.payment_date, :tx_date)) ASC, vp.id DESC"
        );
        $stmt->execute([
            'amount' => $amount,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'tx_date' => $txDate,
        ]);

        $best = null;
        $bestScore = -1;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $score = 1;
            $invoice = mb_strtolower(trim((string) ($row['invoice_number'] ?? '')));
            if ($invoice !== '' && str_contains($reference, $invoice)) {
                $score += 3;
            }
            if ($best === null || $score > $bestScore) {
                $best = $row;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * @param array<string, mixed> $tx
     * @return array<string, mixed>|null
     */
    private static function findSettledVoucher(array $tx): ?array
    {
        if (!Database::isConfigured()) {
            return null;
        }

        $amount = round(abs((float) ($tx['amount'] ?? 0)), 2);
        if ($amount <= 0.0) {
            return null;
        }

        $reference = mb_strtolower((string) ($tx['reference_text'] ?? '') . ' ' . (string) ($tx['end_to_end_id'] ?? ''));
        $iban = strtoupper(str_replace(' ', '', (string) ($tx['counterparty_iban'] ?? '')));
        $txDate = (string) ($tx['transaction_date'] ?? '');
        $dateClause = '';
        $dateParams = [];
        if ($txDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $txDate) === 1) {
            $dateClause = ' AND v.voucher_date BETWEEN :date_from AND :date_to';
            $dateParams = [
                'date_from' => date('Y-m-d', strtotime($txDate . ' -120 days')),
                'date_to' => date('Y-m-d', strtotime($txDate . ' +30 days')),
            ];
        }

        $pdo = Database::pdo();
        $settledStatuses = [
            VoucherPaymentStatus::BANK,
            VoucherPaymentStatus::CASH,
            VoucherPaymentStatus::PRIVATE,
            VoucherPaymentStatus::DIRECT_DEBIT,
            VoucherPaymentStatus::TIP,
        ];
        $statusPlaceholders = implode(',', array_fill(0, count($settledStatuses), '?'));

        if ($iban !== '') {
            $ibanStmt = $pdo->prepare(
                "SELECT v.id, v.invoice_number, v.gross_amount, v.paid_amount, v.discount_amount, v.payment_status
                 FROM dg_vouchers v
                 INNER JOIN dg_contacts c ON c.id = v.contact_id
                 WHERE v.is_draft = 0
                   AND v.payment_status IN ({$statusPlaceholders})
                   AND REPLACE(UPPER(c.bank_accounts), ' ', '') LIKE ?
                   {$dateClause}
                 ORDER BY v.voucher_date DESC, v.id DESC"
            );
            $ibanStmt->execute([...$settledStatuses, '%' . $iban . '%', ...array_values($dateParams)]);
            while ($row = $ibanStmt->fetch(PDO::FETCH_ASSOC)) {
                if (is_array($row) && self::amountMatchesSettledVoucher($row, $amount)) {
                    return $row;
                }
            }
        }

        $stmt = $pdo->prepare(
            "SELECT v.id, v.invoice_number, v.gross_amount, v.paid_amount, v.discount_amount, v.payment_status
             FROM dg_vouchers v
             WHERE v.is_draft = 0
               AND v.payment_status IN ({$statusPlaceholders})
               {$dateClause}
             ORDER BY v.voucher_date DESC, v.id DESC"
        );
        $stmt->execute([...$settledStatuses, ...array_values($dateParams)]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            if (!self::amountMatchesSettledVoucher($row, $amount)) {
                continue;
            }
            $invoice = mb_strtolower(trim((string) ($row['invoice_number'] ?? '')));
            if ($invoice !== '' && str_contains($reference, $invoice)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $voucher
     */
    private static function amountMatchesSettledVoucher(array $voucher, float $amount): bool
    {
        $gross = round((float) ($voucher['gross_amount'] ?? 0), 2);
        $paid = round((float) ($voucher['paid_amount'] ?? 0), 2);
        $discount = round((float) ($voucher['discount_amount'] ?? 0), 2);
        $expected = round(max(0.0, $gross - $discount), 2);

        return abs($paid - $amount) <= 0.02
            || abs($gross - $amount) <= 0.02
            || abs($expected - $amount) <= 0.02;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function findPaymentById(int $paymentId): ?array
    {
        if ($paymentId < 1 || !Database::isConfigured()) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_voucher_payments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $paymentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
