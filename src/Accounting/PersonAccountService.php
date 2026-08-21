<?php
declare(strict_types=1);

/**
 * Personenkonten (Debitoren/Kreditoren) für DATEV-konforme OPOS-Buchungen.
 * Nummernkreise SKR03/SKR04: Debitoren 10000–69999, Kreditoren 70000–99999.
 */
final class PersonAccountService
{
    private const DEBTOR_MIN = 10000;
    private const DEBTOR_MAX = 69999;
    private const CREDITOR_MIN = 70000;
    private const CREDITOR_MAX = 99999;

    /**
     * @return array{debtor: string, creditor: string}
     */
    public static function accountsForContact(int $contactId): array
    {
        if (!Database::isConfigured() || $contactId < 1) {
            return ['debtor' => '', 'creditor' => ''];
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT debtor_account, creditor_account FROM dg_contacts WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $contactId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'debtor' => is_array($row) ? trim((string) ($row['debtor_account'] ?? '')) : '',
            'creditor' => is_array($row) ? trim((string) ($row['creditor_account'] ?? '')) : '',
        ];
    }

    public static function ensureDebtorAccount(int $contactId): string
    {
        return self::ensureAccount($contactId, 'debtor');
    }

    public static function ensureCreditorAccount(int $contactId): string
    {
        return self::ensureAccount($contactId, 'creditor');
    }

    /**
     * Gegenkonto für offenen Beleg: Personenkonto oder Sammelkonto.
     */
    public static function contraForVoucher(array $voucher, string $fallbackContra): string
    {
        $contactId = (int) ($voucher['contact_id'] ?? 0);
        if ($contactId < 1) {
            return $fallbackContra;
        }

        $paymentStatus = (string) ($voucher['payment_status'] ?? VoucherPaymentStatus::OPEN);
        if (!VoucherPaymentStatus::isOpen($paymentStatus) && !VoucherPaymentStatus::expectsBankDebit($paymentStatus)) {
            return $fallbackContra;
        }

        $isIncome = LedgerAccounts::isIncomeDirection((string) ($voucher['voucher_type'] ?? 'expense'));
        $account = $isIncome
            ? self::ensureDebtorAccount($contactId)
            : self::ensureCreditorAccount($contactId);

        return $account !== '' ? $account : $fallbackContra;
    }

    private static function ensureAccount(int $contactId, string $kind): string
    {
        if (!Database::isConfigured() || $contactId < 1) {
            return '';
        }
        MigrationRunner::runPending();

        $column = $kind === 'debtor' ? 'debtor_account' : 'creditor_account';
        $existing = self::accountsForContact($contactId)[$kind];
        if ($existing !== '') {
            return $existing;
        }

        $next = self::nextFreeNumber($kind === 'debtor' ? self::DEBTOR_MIN : self::CREDITOR_MIN, $kind === 'debtor' ? self::DEBTOR_MAX : self::CREDITOR_MAX, $column);
        if ($next === '') {
            return '';
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare("UPDATE dg_contacts SET {$column} = :acc WHERE id = :id AND ({$column} = '' OR {$column} IS NULL)");
        $stmt->execute(['acc' => $next, 'id' => $contactId]);

        return $next;
    }

    private static function nextFreeNumber(int $min, int $max, string $column): string
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query("SELECT {$column} FROM dg_contacts WHERE {$column} <> ''");
        $used = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $num = (int) preg_replace('/\D/', '', (string) ($row[$column] ?? ''));
            if ($num >= $min && $num <= $max) {
                $used[$num] = true;
            }
        }

        for ($n = $min; $n <= $max; $n++) {
            if (!isset($used[$n])) {
                return (string) $n;
            }
        }

        return '';
    }
}
