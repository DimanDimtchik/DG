<?php
declare(strict_types=1);

/** Zahlungshistorie und Teilzahlungen pro Beleg. */
final class VoucherPaymentRepository
{
    public const METHOD_BANK = 'bank';
    public const METHOD_CASH = 'cash';
    public const METHOD_PRIVATE = 'private';
    public const METHOD_DIRECT_DEBIT = 'direct_debit';
    public const METHOD_OTHER = 'other';

    /**
     * @return array<string, string>
     */
    public static function methodOptions(): array
    {
        return [
            self::METHOD_BANK => 'Überweisung',
            self::METHOD_CASH => 'Kasse',
            self::METHOD_PRIVATE => 'Privat',
            self::METHOD_DIRECT_DEBIT => 'Lastschrift',
            self::METHOD_OTHER => 'Sonstiges',
        ];
    }

    public static function sanitizeMethod(string $method): string
    {
        $method = strtolower(trim($method));

        return isset(self::methodOptions()[$method]) ? $method : self::METHOD_BANK;
    }

    public static function methodLabel(string $method): string
    {
        return self::methodOptions()[self::sanitizeMethod($method)] ?? $method;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForVoucher(int $voucherId): array
    {
        if (!Database::isConfigured() || $voucherId < 1) {
            return [];
        }
        MigrationRunner::runPending();
        self::migrateLegacyPaidAmount($voucherId);

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_voucher_payments WHERE voucher_id = :id ORDER BY payment_date ASC, id ASC'
        );
        $stmt->execute(['id' => $voucherId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = self::mapRow($row);
        }

        return $out;
    }

    public static function totalPaid(int $voucherId): float
    {
        if (!Database::isConfigured() || $voucherId < 1) {
            return 0.0;
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(SUM(amount), 0) FROM dg_voucher_payments WHERE voucher_id = :id'
        );
        $stmt->execute(['id' => $voucherId]);

        return round((float) $stmt->fetchColumn(), 2);
    }

    /**
     * Zielbetrag nach Skonto-Abzug.
     *
     * @param array<string, mixed> $voucher
     */
    public static function amountDue(array $voucher): float
    {
        $gross = round((float) ($voucher['gross_amount'] ?? 0), 2);
        $discount = round((float) ($voucher['discount_amount'] ?? 0), 2);

        return round(max(0.0, $gross - $discount), 2);
    }

    /**
     * @param array<string, mixed> $voucher
     */
    public static function openAmount(array $voucher, ?float $totalPaid = null): float
    {
        $gross = round((float) ($voucher['gross_amount'] ?? 0), 2);
        $voucherId = (int) ($voucher['id'] ?? 0);
        if ($totalPaid === null) {
            $totalPaid = $voucherId > 0 ? self::totalPaid($voucherId) : round((float) ($voucher['paid_amount'] ?? 0), 2);
        }
        $totalPaid = round(max(0.0, $totalPaid), 2);
        if ($totalPaid <= 0.0) {
            return $gross;
        }

        return round(max(0.0, $gross - $totalPaid), 2);
    }

    public static function isPartiallyPaid(array $voucher, ?float $totalPaid = null): bool
    {
        $due = self::amountDue($voucher);
        if ($due <= 0.0) {
            return false;
        }
        $voucherId = (int) ($voucher['id'] ?? 0);
        if ($totalPaid === null) {
            $totalPaid = $voucherId > 0
                ? self::totalPaid($voucherId)
                : round((float) ($voucher['paid_amount'] ?? 0), 2);
        }
        $totalPaid = round(max(0.0, $totalPaid), 2);

        return $totalPaid > 0.01 && $totalPaid < $due - 0.01;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function addPayment(
        int $voucherId,
        float $amount,
        string $paymentDate,
        string $method = self::METHOD_BANK,
        ?string $reference = null,
        ?int $bankTransactionId = null,
        ?int $bankTransferId = null,
        ?int $createdBy = null,
    ): int {
        if ($voucherId < 1) {
            throw new InvalidArgumentException('Beleg nicht gefunden.');
        }
        $amount = round($amount, 2);
        if ($amount <= 0.0) {
            throw new InvalidArgumentException('Zahlungsbetrag muss größer als 0 sein.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
            throw new InvalidArgumentException('Ungültiges Zahlungsdatum.');
        }

        MigrationRunner::runPending();
        $voucher = VoucherRepository::findById($voucherId);
        if ($voucher === null) {
            throw new InvalidArgumentException('Beleg nicht gefunden.');
        }

        $open = self::openAmount($voucher);
        if ($amount > $open + 0.02) {
            throw new InvalidArgumentException(
                'Zahlungsbetrag übersteigt den offenen Betrag (' . VoucherRepository::formatMoney($open) . ' €).'
            );
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_voucher_payments (
                voucher_id, amount, payment_date, payment_method, reference_text,
                bank_transaction_id, bank_transfer_id, created_by
            ) VALUES (
                :voucher_id, :amount, :payment_date, :payment_method, :reference_text,
                :bank_transaction_id, :bank_transfer_id, :created_by
            )'
        );
        $stmt->execute([
            'voucher_id' => $voucherId,
            'amount' => $amount,
            'payment_date' => $paymentDate,
            'payment_method' => self::sanitizeMethod($method),
            'reference_text' => $reference !== null && trim($reference) !== '' ? mb_substr(trim($reference), 0, 255) : null,
            'bank_transaction_id' => $bankTransactionId > 0 ? $bankTransactionId : null,
            'bank_transfer_id' => $bankTransferId > 0 ? $bankTransferId : null,
            'created_by' => $createdBy,
        ]);

        $paymentId = (int) Database::pdo()->lastInsertId();
        self::syncVoucherSettlement($voucherId);

        return $paymentId;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function deletePayment(int $paymentId, int $voucherId): void
    {
        if ($paymentId < 1 || $voucherId < 1) {
            throw new InvalidArgumentException('Zahlung nicht gefunden.');
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'DELETE FROM dg_voucher_payments WHERE id = :id AND voucher_id = :voucher_id'
        );
        $stmt->execute(['id' => $paymentId, 'voucher_id' => $voucherId]);
        if ($stmt->rowCount() < 1) {
            throw new InvalidArgumentException('Zahlung nicht gefunden.');
        }

        self::syncVoucherSettlement($voucherId);
    }

    public static function syncVoucherSettlement(int $voucherId): void
    {
        if (!Database::isConfigured() || $voucherId < 1) {
            return;
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare('SELECT * FROM dg_vouchers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $voucherId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return;
        }

        $totalPaid = self::totalPaid($voucherId);
        $payments = self::listForVoucher($voucherId);
        $lastPayment = $payments !== [] ? $payments[count($payments) - 1] : null;
        $due = self::amountDue($row);
        $currentStatus = VoucherPaymentStatus::sanitize((string) ($row['payment_status'] ?? VoucherPaymentStatus::OPEN));

        $paidAt = $lastPayment !== null ? (string) ($lastPayment['payment_date'] ?? '') : null;
        $newStatus = $currentStatus;

        if ($totalPaid <= 0.0) {
            if (in_array($currentStatus, [VoucherPaymentStatus::PARTIAL, VoucherPaymentStatus::BANK, VoucherPaymentStatus::CASH, VoucherPaymentStatus::PRIVATE], true)) {
                $newStatus = VoucherPaymentStatus::OPEN;
            }
        } elseif ($due > 0.0 && $totalPaid < $due - 0.01) {
            $newStatus = VoucherPaymentStatus::PARTIAL;
        } else {
            $newStatus = self::statusFromMethod((string) ($lastPayment['payment_method'] ?? self::METHOD_BANK));
        }

        $update = Database::pdo()->prepare(
            'UPDATE dg_vouchers SET payment_status = :status, paid_amount = :paid_amount, paid_at = :paid_at WHERE id = :id'
        );
        $update->execute([
            'status' => $newStatus,
            'paid_amount' => $totalPaid,
            'paid_at' => $paidAt !== '' ? $paidAt : null,
            'id' => $voucherId,
        ]);

        try {
            LedgerPostingService::rebuildForVoucher($voucherId);
        } catch (Throwable) {
        }
        CashJournalRepository::syncForVoucher($voucherId);
    }

    public static function statusFromMethod(string $method): string
    {
        return match (self::sanitizeMethod($method)) {
            self::METHOD_CASH => VoucherPaymentStatus::CASH,
            self::METHOD_PRIVATE => VoucherPaymentStatus::PRIVATE,
            self::METHOD_DIRECT_DEBIT => VoucherPaymentStatus::DIRECT_DEBIT,
            default => VoucherPaymentStatus::BANK,
        };
    }

    public static function migrateLegacyPaidAmount(int $voucherId): void
    {
        if ($voucherId < 1 || !Database::isConfigured()) {
            return;
        }
        if (!self::tableExists()) {
            return;
        }

        $pdo = Database::pdo();
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM dg_voucher_payments WHERE voucher_id = :id');
        $countStmt->execute(['id' => $voucherId]);
        if ((int) $countStmt->fetchColumn() > 0) {
            return;
        }

        $stmt = $pdo->prepare('SELECT paid_amount, paid_at, payment_status FROM dg_vouchers WHERE id = :id');
        $stmt->execute(['id' => $voucherId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return;
        }

        $paid = round((float) ($row['paid_amount'] ?? 0), 2);
        if ($paid <= 0.0) {
            return;
        }

        $paidAt = (string) ($row['paid_at'] ?? '');
        if ($paidAt === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidAt)) {
            $paidAt = date('Y-m-d');
        }

        $method = match (VoucherPaymentStatus::sanitize((string) ($row['payment_status'] ?? ''))) {
            VoucherPaymentStatus::CASH, VoucherPaymentStatus::TIP => self::METHOD_CASH,
            VoucherPaymentStatus::PRIVATE => self::METHOD_PRIVATE,
            VoucherPaymentStatus::DIRECT_DEBIT => self::METHOD_DIRECT_DEBIT,
            default => self::METHOD_BANK,
        };

        $insert = $pdo->prepare(
            'INSERT INTO dg_voucher_payments (voucher_id, amount, payment_date, payment_method, reference_text)
             VALUES (:voucher_id, :amount, :payment_date, :payment_method, :reference_text)'
        );
        $insert->execute([
            'voucher_id' => $voucherId,
            'amount' => $paid,
            'payment_date' => $paidAt,
            'payment_method' => $method,
            'reference_text' => 'Übernommen aus Beleg (Migration)',
        ]);
    }

    private static function tableExists(): bool
    {
        $stmt = Database::pdo()->query("SHOW TABLES LIKE 'dg_voucher_payments'");

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function mapRow(array $row): array
    {
        $method = self::sanitizeMethod((string) ($row['payment_method'] ?? ''));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'voucher_id' => (int) ($row['voucher_id'] ?? 0),
            'amount' => round((float) ($row['amount'] ?? 0), 2),
            'amount_display' => VoucherRepository::formatMoney((float) ($row['amount'] ?? 0)),
            'payment_date' => (string) ($row['payment_date'] ?? ''),
            'payment_method' => $method,
            'payment_method_label' => self::methodLabel($method),
            'reference_text' => (string) ($row['reference_text'] ?? ''),
            'bank_transaction_id' => (int) ($row['bank_transaction_id'] ?? 0),
            'bank_transfer_id' => (int) ($row['bank_transfer_id'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }
}
