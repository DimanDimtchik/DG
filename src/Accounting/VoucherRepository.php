<?php
declare(strict_types=1);

final class VoucherRepository
{
    private const PER_PAGE = 25;

    /** @return array<string, string> */
    public static function voucherTypeOptions(): array
    {
        return [
            'receipt' => 'Quittung',
            'expense' => 'Ausgabe / Beleg',
            'invoice' => 'Eingangsrechnung',
            'credit' => 'Gutschrift',
        ];
    }

    /** @return array<string, string> */
    public static function paymentStatusOptions(): array
    {
        return [
            'open' => 'Offen',
            'paid' => 'Bezahlt',
        ];
    }

    /**
     * @param array{year?: int|null, type?: string, search?: string, page?: int} $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public static function list(array $filters = []): array
    {
        if (!Database::isConfigured()) {
            return [
                'items' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => self::PER_PAGE,
                'total_pages' => 1,
            ];
        }

        MigrationRunner::runPending();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $year = isset($filters['year']) && (int) $filters['year'] > 0 ? (int) $filters['year'] : null;
        $type = self::sanitizeVoucherType((string) ($filters['type'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

        $where = [];
        $params = [];

        if ($year !== null) {
            $where[] = 'YEAR(v.voucher_date) = :year';
            $params['year'] = $year;
        }

        if ($type !== '') {
            $where[] = 'v.voucher_type = :voucher_type';
            $params['voucher_type'] = $type;
        }

        if ($search !== '') {
            $where[] = '(
                v.supplier_name LIKE :q OR v.invoice_number LIKE :q
                OR v.description LIKE :q OR v.account_number LIKE :q
                OR c.display_name LIKE :q OR c.company_name LIKE :q
            )';
            $params['q'] = '%' . $search . '%';
        }

        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = 'SELECT COUNT(*) FROM dg_vouchers v
            LEFT JOIN dg_contacts c ON c.id = v.contact_id ' . $whereSql;
        $stmt = Database::pdo()->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $sql = 'SELECT v.*, c.display_name AS contact_display_name, c.company_name AS contact_company_name
            FROM dg_vouchers v
            LEFT JOIN dg_contacts c ON c.id = v.contact_id
            ' . $whereSql . '
            ORDER BY v.voucher_date DESC, v.id DESC
            LIMIT :limit OFFSET :offset';

        $stmt = Database::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', self::PER_PAGE, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * self::PER_PAGE, PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        while ($row = $stmt->fetch()) {
            $items[] = self::enrichRow($row);
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => self::PER_PAGE,
            'total_pages' => max(1, (int) ceil($total / self::PER_PAGE)),
        ];
    }

    /** @return list<int> */
    public static function availableYears(): array
    {
        if (!Database::isConfigured()) {
            return [(int) date('Y')];
        }

        MigrationRunner::runPending();

        $stmt = Database::pdo()->query(
            'SELECT DISTINCT YEAR(voucher_date) AS y FROM dg_vouchers ORDER BY y DESC'
        );
        $years = [];
        while ($row = $stmt->fetch()) {
            $years[] = (int) $row['y'];
        }

        if ($years === []) {
            $years[] = (int) date('Y');
        }

        return $years;
    }

    public static function findById(int $id): ?array
    {
        if (!Database::isConfigured()) {
            return null;
        }

        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT v.*, c.display_name AS contact_display_name, c.company_name AS contact_company_name
             FROM dg_vouchers v
             LEFT JOIN dg_contacts c ON c.id = v.contact_id
             WHERE v.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? self::enrichRow($row) : null;
    }

    /** @param array<string, mixed> $data */
    public static function save(array $data, ?int $id = null, ?int $userId = null): int
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }

        MigrationRunner::runPending();

        $voucherType = self::sanitizeVoucherType((string) ($data['voucher_type'] ?? 'expense'));
        $voucherDate = trim((string) ($data['voucher_date'] ?? ''));
        if ($voucherDate === '' || strtotime($voucherDate) === false) {
            throw new InvalidArgumentException('Belegdatum ist erforderlich.');
        }
        $voucherDate = date('Y-m-d', strtotime($voucherDate));

        $contactId = max(0, (int) ($data['contact_id'] ?? 0));
        $contactId = $contactId > 0 ? $contactId : null;
        if ($contactId !== null && ContactRepository::findById($contactId) === null) {
            throw new InvalidArgumentException('Der gewählte Kontakt existiert nicht.');
        }

        $supplierName = trim((string) ($data['supplier_name'] ?? ''));
        if ($supplierName === '' && $contactId === null) {
            throw new InvalidArgumentException('Lieferant / Kontakt oder Name ist erforderlich.');
        }

        $taxRate = VoucherTaxKeys::sanitizeTaxRate((int) ($data['tax_rate'] ?? 19));
        $gross = round((float) str_replace(',', '.', (string) ($data['gross_amount'] ?? '0')), 2);
        if ($gross <= 0) {
            throw new InvalidArgumentException('Bruttobetrag muss größer als 0 sein.');
        }

        $amounts = VoucherTaxKeys::calcTaxFromGross($gross, $taxRate);
        if (isset($data['net_amount']) && (string) $data['net_amount'] !== '') {
            $amounts['net_amount'] = round((float) str_replace(',', '.', (string) $data['net_amount']), 2);
            $amounts['tax_amount'] = round($gross - $amounts['net_amount'], 2);
        }

        $accountNumber = preg_replace('/\D/', '', (string) ($data['account_number'] ?? '')) ?? '';
        if ($accountNumber === '') {
            throw new InvalidArgumentException('Kontonummer ist erforderlich.');
        }

        $skrType = ChartOfAccountsSettings::activeSkrType();
        ChartAccountRepository::ensureSeeded($skrType);
        if (ChartAccountRepository::findByNumber($accountNumber, $skrType) === null) {
            throw new InvalidArgumentException('Kontonummer nicht im aktiven Kontenrahmen gefunden.');
        }

        $fields = [
            'voucher_type' => $voucherType,
            'voucher_date' => $voucherDate,
            'contact_id' => $contactId,
            'supplier_name' => $supplierName,
            'invoice_number' => trim((string) ($data['invoice_number'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'gross_amount' => $amounts['gross_amount'],
            'net_amount' => $amounts['net_amount'],
            'tax_amount' => $amounts['tax_amount'],
            'tax_rate' => $taxRate,
            'tax_key' => VoucherTaxKeys::sanitizeTaxKey((string) ($data['tax_key'] ?? '')),
            'account_number' => $accountNumber,
            'payment_status' => self::sanitizePaymentStatus((string) ($data['payment_status'] ?? 'open')),
            'notes' => trim((string) ($data['notes'] ?? '')),
        ];

        $pdo = Database::pdo();

        if ($id) {
            $stmt = $pdo->prepare(
                'UPDATE dg_vouchers SET
                    voucher_type = :voucher_type,
                    voucher_date = :voucher_date,
                    contact_id = :contact_id,
                    supplier_name = :supplier_name,
                    invoice_number = :invoice_number,
                    description = :description,
                    gross_amount = :gross_amount,
                    net_amount = :net_amount,
                    tax_amount = :tax_amount,
                    tax_rate = :tax_rate,
                    tax_key = :tax_key,
                    account_number = :account_number,
                    payment_status = :payment_status,
                    notes = :notes
                 WHERE id = :id'
            );
            $fields['id'] = $id;
            $stmt->execute($fields);

            return $id;
        }

        $fields['created_by'] = $userId;
        $stmt = $pdo->prepare(
            'INSERT INTO dg_vouchers (
                voucher_type, voucher_date, contact_id, supplier_name, invoice_number, description,
                gross_amount, net_amount, tax_amount, tax_rate, tax_key, account_number,
                payment_status, notes, created_by
            ) VALUES (
                :voucher_type, :voucher_date, :contact_id, :supplier_name, :invoice_number, :description,
                :gross_amount, :net_amount, :tax_amount, :tax_rate, :tax_key, :account_number,
                :payment_status, :notes, :created_by
            )'
        );
        $stmt->execute($fields);

        return (int) $pdo->lastInsertId();
    }

    public static function delete(int $id): void
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }

        $stmt = Database::pdo()->prepare('DELETE FROM dg_vouchers WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** @return array<string, string> */
    public static function emptyForm(): array
    {
        return [
            'voucher_type' => 'expense',
            'voucher_date' => date('Y-m-d'),
            'contact_id' => '',
            'contact_label' => '',
            'supplier_name' => '',
            'invoice_number' => '',
            'description' => '',
            'gross_amount' => '',
            'net_amount' => '',
            'tax_amount' => '',
            'tax_rate' => '19',
            'tax_key' => VoucherTaxKeys::KEY_VST_19,
            'account_number' => '',
            'account_name' => '',
            'payment_status' => 'open',
            'notes' => '',
        ];
    }

    /** @param array<string, mixed> $row */
    public static function toForm(array $row): array
    {
        return [
            'voucher_type' => (string) ($row['voucher_type'] ?? 'expense'),
            'voucher_date' => (string) ($row['voucher_date'] ?? ''),
            'contact_id' => $row['contact_id'] !== null ? (string) $row['contact_id'] : '',
            'contact_label' => (string) ($row['supplier_display'] ?? ''),
            'supplier_name' => (string) ($row['supplier_name'] ?? ''),
            'invoice_number' => (string) ($row['invoice_number'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'gross_amount' => self::formatMoney((float) ($row['gross_amount'] ?? 0)),
            'net_amount' => self::formatMoney((float) ($row['net_amount'] ?? 0)),
            'tax_amount' => self::formatMoney((float) ($row['tax_amount'] ?? 0)),
            'tax_rate' => (string) ($row['tax_rate'] ?? '19'),
            'tax_key' => (string) ($row['tax_key'] ?? ''),
            'account_number' => (string) ($row['account_number'] ?? ''),
            'account_name' => (string) ($row['account_name'] ?? ''),
            'payment_status' => (string) ($row['payment_status'] ?? 'open'),
            'notes' => (string) ($row['notes'] ?? ''),
        ];
    }

    public static function typeLabel(string $type): string
    {
        return self::voucherTypeOptions()[self::sanitizeVoucherType($type)] ?? $type;
    }

    public static function paymentLabel(string $status): string
    {
        return self::paymentStatusOptions()[self::sanitizePaymentStatus($status)] ?? $status;
    }

    public static function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', '.');
    }

    private static function sanitizeVoucherType(string $type): string
    {
        $type = strtolower(trim($type));

        return isset(self::voucherTypeOptions()[$type]) ? $type : 'expense';
    }

    private static function sanitizePaymentStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return isset(self::paymentStatusOptions()[$status]) ? $status : 'open';
    }

    /** @param array<string, mixed> $row */
    private static function enrichRow(array $row): array
    {
        $contactLabel = trim((string) ($row['contact_company_name'] ?? ''));
        if ($contactLabel === '') {
            $contactLabel = trim((string) ($row['contact_display_name'] ?? ''));
        }

        $supplierDisplay = trim((string) ($row['supplier_name'] ?? ''));
        if ($supplierDisplay === '' && $contactLabel !== '') {
            $supplierDisplay = $contactLabel;
        }

        $accountName = '';
        $accountNumber = (string) ($row['account_number'] ?? '');
        if ($accountNumber !== '' && Database::isConfigured()) {
            $account = ChartAccountRepository::findByNumber($accountNumber, ChartOfAccountsSettings::activeSkrType());
            if ($account !== null) {
                $accountName = (string) ($account['name'] ?? '');
            }
        }

        $row['supplier_display'] = $supplierDisplay;
        $row['account_name'] = $accountName;
        $row['type_label'] = self::typeLabel((string) ($row['voucher_type'] ?? ''));
        $row['payment_label'] = self::paymentLabel((string) ($row['payment_status'] ?? ''));

        return $row;
    }
}
