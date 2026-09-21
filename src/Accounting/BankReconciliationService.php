<?php
declare(strict_types=1);

/** Automatischer Bankabgleich: CAMT-Umsätze → Belege/OPOS. */
final class BankReconciliationService
{
    public static function autoMatchBatch(string $batch): int
    {
        if (!Database::isConfigured() || $batch === '') {
            return 0;
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            "SELECT * FROM dg_bank_transactions WHERE import_batch = :batch AND match_status = 'open'"
        );
        $stmt->execute(['batch' => $batch]);
        $matched = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $voucherId = self::findVoucherForTransaction($row);
            if ($voucherId > 0) {
                self::applyMatch((int) $row['id'], $voucherId, $row);
                $matched++;
            }
        }

        return $matched;
    }

    public static function matchManually(int $transactionId, int $voucherId): void
    {
        $tx = BankTransactionRepository::findById($transactionId);
        if ($tx === null) {
            throw new RuntimeException('Bankumsatz nicht gefunden.');
        }
        $voucher = VoucherRepository::findById($voucherId);
        if ($voucher === null) {
            throw new RuntimeException('Beleg nicht gefunden.');
        }
        $voucherType = VoucherRepository::normalizeVoucherType((string) ($voucher['voucher_type'] ?? 'expense'));
        if (!VoucherDocumentKind::allowsOpenItemTracking((string) ($voucher['document_kind'] ?? ''), $voucherType)) {
            throw new RuntimeException(
                'Diesem Belegtyp (z. B. Lieferschein) können keine Zahlungen zugeordnet werden.'
            );
        }
        self::applyMatch($transactionId, $voucherId, $tx);
    }

    /**
     * Offene Belege für manuelle Zuordnung suchen (Kunde / Rechnungsnr. / Beleg-ID).
     *
     * @return list<array{
     *   id: int,
     *   label: string,
     *   invoice_number: string,
     *   contact_label: string,
     *   voucher_date: string,
     *   open_amount: float,
     *   open_amount_display: string,
     *   payment_status: string
     * }>
     */
    public static function searchOpenVouchers(string $query, int $limit = 15): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $query = trim($query);
        if ($query === '' || mb_strlen($query) < 1) {
            return [];
        }

        $limit = max(1, min(30, $limit));
        $like = '%' . $query . '%';
        $pdo = Database::pdo();

        $sql = "SELECT v.id, v.invoice_number, v.voucher_date, v.gross_amount, v.paid_amount,
                       v.discount_amount, v.payment_status, v.voucher_type, v.document_kind, v.supplier_name,
                       c.display_name AS contact_display_name, c.company_name AS contact_company_name
                FROM dg_vouchers v
                LEFT JOIN dg_contacts c ON c.id = v.contact_id
                WHERE v.is_draft = 0
                  AND v.payment_status IN ('open', 'partial', 'direct_debit')
                  AND (
                    v.invoice_number LIKE :q1
                    OR v.supplier_name LIKE :q2
                    OR c.display_name LIKE :q3
                    OR c.company_name LIKE :q4
                    OR CAST(v.id AS CHAR) LIKE :q5
                  )
                ORDER BY
                  CASE
                    WHEN v.invoice_number = :exact1 THEN 0
                    WHEN CAST(v.id AS CHAR) = :exact2 THEN 1
                    WHEN v.invoice_number LIKE :prefix1 THEN 2
                    ELSE 3
                  END,
                  v.voucher_date DESC,
                  v.id DESC
                LIMIT {$limit}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'q1' => $like,
            'q2' => $like,
            'q3' => $like,
            'q4' => $like,
            'q5' => $like,
            'exact1' => $query,
            'exact2' => $query,
            'prefix1' => $query . '%',
        ]);

        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $voucherType = VoucherRepository::normalizeVoucherType((string) ($row['voucher_type'] ?? 'expense'));
            // Bankabgleich: Rechnungen + Angebot/AB (Anzahlung); kein Lieferschein.
            if (!VoucherDocumentKind::allowsOpenItemTracking((string) ($row['document_kind'] ?? ''), $voucherType)) {
                continue;
            }
            $contact = trim((string) ($row['contact_company_name'] ?? ''));
            if ($contact === '') {
                $contact = trim((string) ($row['contact_display_name'] ?? ''));
            }
            if ($contact === '') {
                $contact = trim((string) ($row['supplier_name'] ?? ''));
            }
            $invoice = trim((string) ($row['invoice_number'] ?? ''));
            $open = VoucherPaymentRepository::openAmount($row);
            if ($open <= 0.0) {
                continue;
            }
            $kindLabel = VoucherDocumentKind::label((string) ($row['document_kind'] ?? ''));
            $parts = array_values(array_filter([
                $kindLabel !== '' ? $kindLabel : null,
                $invoice !== '' ? $invoice : '#' . (int) $row['id'],
                $contact !== '' ? $contact : null,
                VoucherRepository::formatMoney($open) . ' € offen',
            ]));
            $items[] = [
                'id' => (int) $row['id'],
                'label' => implode(' · ', $parts),
                'invoice_number' => $invoice,
                'contact_label' => $contact,
                'voucher_date' => (string) ($row['voucher_date'] ?? ''),
                'open_amount' => $open,
                'open_amount_display' => VoucherRepository::formatMoney($open),
                'payment_status' => (string) ($row['payment_status'] ?? ''),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $tx
     */
    private static function applyMatch(int $transactionId, int $voucherId, array $tx): void
    {
        $voucher = VoucherRepository::findById($voucherId);
        if ($voucher === null) {
            throw new RuntimeException('Beleg nicht gefunden.');
        }
        $txAmount = round(abs((float) ($tx['amount'] ?? 0)), 2);
        $open = VoucherPaymentRepository::openAmount($voucher);
        if ($open <= 0.0) {
            throw new RuntimeException('Beleg hat keinen offenen Betrag mehr.');
        }
        // Teilabgleich: nur bis zum offenen Betrag verbuchen (Bankumsatz wird trotzdem zugeordnet).
        $amount = min($txAmount, $open);
        if ($amount <= 0.0) {
            throw new RuntimeException('Kein zuordenbarer Betrag.');
        }
        VoucherSettlementService::recordPayment(
            $voucherId,
            VoucherPaymentStatus::BANK,
            $amount,
            (string) ($tx['transaction_date'] ?? date('Y-m-d')),
            (int) $transactionId,
            (string) ($tx['reference_text'] ?? ''),
        );
        BankTransactionRepository::markMatched($transactionId, $voucherId);
    }

    /**
     * @param array<string, mixed> $tx
     */
    private static function findVoucherForTransaction(array $tx): int
    {
        $amount = round(abs((float) ($tx['amount'] ?? 0)), 2);
        if ($amount <= 0.0) {
            return 0;
        }

        $reference = mb_strtolower((string) ($tx['reference_text'] ?? '') . ' ' . (string) ($tx['end_to_end_id'] ?? ''));
        $iban = strtoupper(str_replace(' ', '', (string) ($tx['counterparty_iban'] ?? '')));
        $txDate = (string) ($tx['transaction_date'] ?? '');
        $pdo = Database::pdo();

        $dateClause = '';
        $dateParams = [];
        if ($txDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $txDate) === 1) {
            $dateClause = ' AND v.voucher_date BETWEEN :date_from AND :date_to';
            $dateParams = [
                'date_from' => date('Y-m-d', strtotime($txDate . ' -120 days')),
                'date_to' => date('Y-m-d', strtotime($txDate . ' +30 days')),
            ];
        }

        if ($iban !== '') {
            $ibanStmt = $pdo->prepare(
                "SELECT v.id, v.gross_amount, v.paid_amount, v.discount_amount, v.invoice_number,
                        v.voucher_type, v.document_kind
                 FROM dg_vouchers v
                 INNER JOIN dg_contacts c ON c.id = v.contact_id
                 WHERE v.is_draft = 0 AND v.payment_status IN ('open', 'partial', 'direct_debit')
                   AND REPLACE(UPPER(c.bank_accounts), ' ', '') LIKE :iban
                   {$dateClause}
                 ORDER BY v.voucher_date DESC, v.id DESC"
            );
            $ibanStmt->execute(['iban' => '%' . $iban . '%'] + $dateParams);
            while ($row = $ibanStmt->fetch(PDO::FETCH_ASSOC)) {
                if (!is_array($row) || !self::isMatchableVoucher($row)) {
                    continue;
                }
                if (self::amountMatches($row, $amount)) {
                    return (int) $row['id'];
                }
            }
        }

        $stmt = $pdo->prepare(
            "SELECT v.id, v.gross_amount, v.paid_amount, v.discount_amount, v.invoice_number, v.payment_status,
                    v.voucher_type, v.document_kind
             FROM dg_vouchers v
             WHERE v.is_draft = 0 AND v.payment_status IN ('open', 'partial', 'direct_debit')
               {$dateClause}
             ORDER BY v.voucher_date DESC, v.id DESC"
        );
        $stmt->execute($dateParams);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row) || !self::isMatchableVoucher($row)) {
                continue;
            }
            if (!self::amountMatches($row, $amount)) {
                continue;
            }
            $invoice = mb_strtolower(trim((string) ($row['invoice_number'] ?? '')));
            if ($invoice !== '' && str_contains($reference, $invoice)) {
                return (int) $row['id'];
            }
        }

        $stmt2 = $pdo->prepare(
            "SELECT v.id, v.gross_amount, v.paid_amount, v.discount_amount, v.voucher_type, v.document_kind
             FROM dg_vouchers v
             WHERE v.is_draft = 0 AND v.payment_status IN ('open', 'partial', 'direct_debit')
               {$dateClause}
             ORDER BY v.voucher_date DESC, v.id DESC"
        );
        $stmt2->execute($dateParams);
        while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            if (is_array($row) && self::isMatchableVoucher($row) && self::amountMatches($row, $amount)) {
                return (int) $row['id'];
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $voucher
     */
    private static function isMatchableVoucher(array $voucher): bool
    {
        $type = VoucherRepository::normalizeVoucherType((string) ($voucher['voucher_type'] ?? 'expense'));

        return VoucherDocumentKind::allowsOpenItemTracking((string) ($voucher['document_kind'] ?? ''), $type);
    }

    /**
     * @param array<string, mixed> $voucher
     */
    private static function amountMatches(array $voucher, float $amount): bool
    {
        $gross = VoucherRepository::parseMoney($voucher['gross_amount'] ?? 0);
        $discount = VoucherRepository::parseMoney($voucher['discount_amount'] ?? 0);
        $open = VoucherPaymentRepository::openAmount($voucher);
        $expected = round(max(0.0, $gross - $discount), 2);

        return abs($open - $amount) <= 0.02
            || abs($gross - $amount) <= 0.02
            || abs($expected - $amount) <= 0.02;
    }
}
