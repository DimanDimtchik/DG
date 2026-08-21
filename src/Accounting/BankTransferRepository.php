<?php
declare(strict_types=1);

/**
 * Verwaltet vorbereitete und ausgeführte Überweisungen (SEPA / GiroCode).
 */
final class BankTransferRepository
{
        /**
     * Erstellt aus einem (offenen Lieferanten-)Beleg eine vorbereitete Überweisung.
     * @param int $voucherId Beleg-ID
     * @param int|null $userId Benutzer-ID
     * @return int
     * @throws RuntimeException
     */
    public static function prepareFromVoucher(int $voucherId, ?int $userId): int
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }

        MigrationRunner::runPending();

        $voucher = VoucherRepository::findById($voucherId);
        if ($voucher === null) {
            throw new RuntimeException('Beleg nicht gefunden.');
        }

        if (!self::voucherIsTransferable($voucher)) {
            throw new RuntimeException('Nur offene Lieferanten-/Ausgabenbelege können als Überweisung vorbereitet werden.');
        }

        $contactId = (int) ($voucher['contact_id'] ?? 0);
        $bank = self::bankAccountForContact($contactId);

        $recipientName = trim((string) ($voucher['supplier_name'] ?? ''));
        if ($recipientName === '') {
            $recipientName = trim((string) ($voucher['supplier_display'] ?? ''));
        }
        if ($recipientName === '' && $bank !== null) {
            $recipientName = trim((string) ($bank['account_holder'] ?? ''));
        }

        $amount = round((float) ($voucher['gross_amount'] ?? 0), 2);

        $iban = $bank !== null ? strtoupper(str_replace(' ', '', (string) ($bank['iban'] ?? ''))) : '';
        $bic = $bank !== null ? strtoupper(trim((string) ($bank['bic'] ?? ''))) : '';
        $bic = self::resolveBic($bic, $iban);

        $purpose = PaymentReferenceFormula::resolve(PaymentReferenceFormula::formula(), [
            'invoice_number' => (string) ($voucher['invoice_number'] ?? ''),
            'invoice_date' => (string) ($voucher['voucher_date'] ?? ''),
            'customer_number' => self::customerNumberForContact($contactId),
            'company_name' => CompanySettings::displayName(),
            'supplier_name' => $recipientName,
            'amount' => $amount,
        ]);

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO dg_bank_transfers
                (voucher_id, contact_id, recipient_name, recipient_iban, recipient_bic, amount, currency, purpose, invoice_number, status, created_by)
             VALUES
                (:voucher_id, :contact_id, :recipient_name, :recipient_iban, :recipient_bic, :amount, :currency, :purpose, :invoice_number, :status, :created_by)'
        );
        $stmt->execute([
            'voucher_id' => $voucherId,
            'contact_id' => $contactId > 0 ? $contactId : null,
            'recipient_name' => $recipientName,
            'recipient_iban' => $iban,
            'recipient_bic' => $bic,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => 'EUR',
            'purpose' => $purpose,
            'invoice_number' => (string) ($voucher['invoice_number'] ?? ''),
            'status' => 'prepared',
            'created_by' => $userId !== null && $userId > 0 ? $userId : null,
        ]);

        return (int) $pdo->lastInsertId();
    }

        /**
     * Prüft, ob ein Beleg als Überweisung vorbereitet werden kann
     * @param array $voucher Belegdaten
     * @return bool
     */
    public static function voucherIsTransferable(array $voucher): bool
    {
        $type = (string) ($voucher['voucher_type'] ?? '');
        $status = (string) ($voucher['payment_status'] ?? '');

        return in_array($type, ['expense', 'expense_reduction'], true) && $status === 'open';
    }

        /**
     * Liefert eine bereits vorbereitete Überweisung zu einem Beleg (falls vorhanden).
     * @param int $voucherId Beleg-ID
     * @return ?array
     */
    public static function findByVoucher(int $voucherId): ?array
    {
        if (!Database::isConfigured() || $voucherId < 1) {
            return null;
        }

        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_bank_transfers WHERE voucher_id = :id ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['id' => $voucherId]);
        $row = $stmt->fetch();

        return $row ? self::enrich($row) : null;
    }

    /**
     * Findet einen Datensatz anhand der ID
     * @param int $id Datensatz-ID
     * @return ?array
     */
    public static function findById(int $id): ?array
    {
        if (!Database::isConfigured() || $id < 1) {
            return null;
        }

        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare('SELECT * FROM dg_bank_transfers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? self::enrich($row) : null;
    }

    /**
     * @param string $status 'prepared' | 'executed' | '' (alle)
     * @return list<array<string, mixed>>
     */
    public static function list(string $status = ''): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        MigrationRunner::runPending();

        $sql = 'SELECT * FROM dg_bank_transfers';
        $params = [];
        if ($status === 'prepared' || $status === 'executed') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= " ORDER BY (status = 'executed') ASC, created_at DESC, id DESC";

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map([self::class, 'enrich'], is_array($rows) ? $rows : []);
    }

    /**
     * Markiert eine Überweisung als ausgeführt
     * @param int $id Datensatz-ID
     * @return void
     */
    public static function markExecuted(int $id): void
    {
        if (!Database::isConfigured() || $id < 1) {
            return;
        }

        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            "UPDATE dg_bank_transfers SET status = 'executed', executed_at = NOW() WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }

    /**
     * Markiert eine Überweisung als vorbereitet
     * @param int $id Datensatz-ID
     * @return void
     */
    public static function markPrepared(int $id): void
    {
        if (!Database::isConfigured() || $id < 1) {
            return;
        }

        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            "UPDATE dg_bank_transfers SET status = 'prepared', executed_at = NULL WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }

    /**
     * Löscht einen Datensatz
     * @param int $id Datensatz-ID
     * @return void
     */
    public static function delete(int $id): void
    {
        if (!Database::isConfigured() || $id < 1) {
            return;
        }

        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare('DELETE FROM dg_bank_transfers WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

        /**
     * enrich
     * @param array $row Datenbankzeile
     * @return array
     */
    private static function enrich(array $row): array
    {
        $amount = (float) ($row['amount'] ?? 0);
        $iban = (string) ($row['recipient_iban'] ?? '');

        // BIC ggf. aus der deutschen IBAN (Bankleitzahl) ergänzen, falls nicht gespeichert.
        $row['recipient_bic'] = self::resolveBic((string) ($row['recipient_bic'] ?? ''), $iban);

        $row['amount_display'] = number_format($amount, 2, ',', '.') . ' €';
        $row['iban_display'] = self::formatIban($iban);
        $row['is_payable'] = EpcQrCode::isPayable([
            'recipient_name' => (string) ($row['recipient_name'] ?? ''),
            'recipient_iban' => $iban,
            'amount' => $amount,
        ]);
        $row['qr_payload'] = $row['is_payable'] ? EpcQrCode::payload([
            'recipient_name' => (string) ($row['recipient_name'] ?? ''),
            'recipient_iban' => $iban,
            'recipient_bic' => (string) ($row['recipient_bic'] ?? ''),
            'amount' => $amount,
            'currency' => (string) ($row['currency'] ?? 'EUR'),
            'purpose' => (string) ($row['purpose'] ?? ''),
        ]) : '';

        return $row;
    }

        /**
     * Liefert die BIC – entweder die übergebene oder, falls leer, aus der
     * @param string $bic BIC
     * @param string $iban IBAN
     * @return string
     */
    private static function resolveBic(string $bic, string $iban): string
    {
        $bic = strtoupper(trim(str_replace(' ', '', $bic)));
        if ($bic !== '') {
            return $bic;
        }

        $suggestion = BankDirectory::suggestFromIban($iban);
        if ($suggestion !== null) {
            return strtoupper(trim((string) ($suggestion['bic'] ?? '')));
        }

        return '';
    }

    /**
     * bankAccountForContact
     * @param int $contactId Kontakt-ID
     * @return ?array
     */
    private static function bankAccountForContact(int $contactId): ?array
    {
        if ($contactId < 1) {
            return null;
        }

        $contact = ContactRepository::findById($contactId);
        if ($contact === null) {
            return null;
        }

        $fallback = null;
        foreach ($contact->bankAccounts as $account) {
            $iban = strtoupper(str_replace(' ', '', (string) ($account['iban'] ?? '')));
            if ($iban === '') {
                continue;
            }
            if (($account['type'] ?? '') === 'giro' && EpcQrCode::isValidIban($iban)) {
                return $account;
            }
            if ($fallback === null && EpcQrCode::isValidIban($iban)) {
                $fallback = $account;
            }
        }

        return $fallback;
    }

    /**
     * customerNumberForContact
     * @param int $contactId Kontakt-ID
     * @return string
     */
    private static function customerNumberForContact(int $contactId): string
    {
        if ($contactId < 1) {
            return '';
        }

        $contact = ContactRepository::findById($contactId);

        return $contact !== null ? trim($contact->customerNumber) : '';
    }

    /**
     * formatIban
     * @param string $iban IBAN
     * @return string
     */
    private static function formatIban(string $iban): string
    {
        $iban = strtoupper(str_replace(' ', '', $iban));

        return trim((string) chunk_split($iban, 4, ' '));
    }
}
