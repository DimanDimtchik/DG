<?php
declare(strict_types=1);

/** Offene Posten (OPOS) aus Belegen mit offenem Zahlungsstatus. */
final class OpenItemsRepository
{
    /**
     * @param array{direction?: string, contact_id?: int, search?: string} $opts
     * @return array{items: list<array<string, mixed>>, totals: array{receivable: float, payable: float}}
     */
    public static function list(array $opts = []): array
    {
        $result = ['items' => [], 'totals' => ['receivable' => 0.0, 'payable' => 0.0]];
        if (!Database::isConfigured()) {
            return $result;
        }
        MigrationRunner::runPending();

        $direction = trim((string) ($opts['direction'] ?? ''));
        $contactId = max(0, (int) ($opts['contact_id'] ?? 0));
        $search = mb_strtolower(trim((string) ($opts['search'] ?? '')));

        $sql = "SELECT v.*, c.display_name AS contact_display, c.company_name AS contact_company,
                       c.debtor_account, c.creditor_account
                FROM dg_vouchers v
                LEFT JOIN dg_contacts c ON c.id = v.contact_id
                WHERE v.is_draft = 0
                  AND v.payment_status IN ('open', 'direct_debit')
                ORDER BY v.voucher_date ASC, v.id ASC";
        $rows = Database::pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = VoucherRepository::normalizeVoucherType((string) ($row['voucher_type'] ?? 'expense'));
            if (!VoucherDocumentKind::isBookable((string) ($row['document_kind'] ?? ''), $type)) {
                continue;
            }
            $isReceivable = LedgerAccounts::isIncomeDirection($type);
            $itemDirection = $isReceivable ? 'receivable' : 'payable';

            if ($direction === 'receivable' && !$isReceivable) {
                continue;
            }
            if ($direction === 'payable' && $isReceivable) {
                continue;
            }
            if ($contactId > 0 && (int) ($row['contact_id'] ?? 0) !== $contactId) {
                continue;
            }

            $openAmount = self::openAmount($row);
            if ($openAmount <= 0.0) {
                continue;
            }

            $contactLabel = trim((string) ($row['supplier_name'] ?? ''));
            if ($contactLabel === '') {
                $contactLabel = trim((string) ($row['contact_company'] ?? ''));
                if ($contactLabel === '') {
                    $contactLabel = trim((string) ($row['contact_display'] ?? ''));
                }
            }

            $personAccount = $isReceivable
                ? trim((string) ($row['debtor_account'] ?? ''))
                : trim((string) ($row['creditor_account'] ?? ''));

            if ($search !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    (string) ($row['invoice_number'] ?? ''),
                    $contactLabel,
                    $personAccount,
                    (string) ($row['description'] ?? ''),
                ]));
                if (!str_contains($haystack, $search)) {
                    continue;
                }
            }

            $item = [
                'voucher_id' => (int) $row['id'],
                'voucher_type' => $type,
                'direction' => $itemDirection,
                'voucher_date' => (string) ($row['voucher_date'] ?? ''),
                'payment_due_date' => (string) ($row['payment_due_date'] ?? ''),
                'days_overdue' => PaymentTermsService::daysOverdue(
                    (string) ($row['payment_due_date'] ?? ''),
                    date('Y-m-d')
                ),
                'dunning_level' => (int) ($row['dunning_level'] ?? 0),
                'invoice_number' => (string) ($row['invoice_number'] ?? ''),
                'contact_id' => (int) ($row['contact_id'] ?? 0),
                'contact_label' => $contactLabel,
                'person_account' => $personAccount,
                'gross_amount' => round((float) ($row['gross_amount'] ?? 0), 2),
                'open_amount' => $openAmount,
                'payment_status' => (string) ($row['payment_status'] ?? 'open'),
                'description' => (string) ($row['description'] ?? ''),
            ];
            $result['items'][] = $item;
            $result['totals'][$itemDirection] = round($result['totals'][$itemDirection] + $openAmount, 2);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $voucher
     */
    private static function openAmount(array $voucher): float
    {
        return DunningService::openAmount($voucher);
    }
}
