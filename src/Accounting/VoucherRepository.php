<?php
declare(strict_types=1);

/**
 * Voucher Repository.
 */
final class VoucherRepository
{
    private const PER_PAGE = 25;

    /**
     * voucherTypeOptions.
     *
     * @return array<string, string>
     */
        public static function voucherTypeOptions(): array
    {
        return [
            'income' => 'Einnahmen',
            'income_reduction' => 'Einnahmenminderung',
            'expense' => 'Ausgaben',
            'expense_reduction' => 'Ausgabenminderung',
            'credit' => 'Kundengutschrift',
        ];
    }

        /**
     * Kurz erklärung je Belegart (Lexoffice / EÜR-Logik).
     * @param string $type
     * @return string
     */
    public static function voucherTypeHint(string $type): string
    {
        return match (self::sanitizeVoucherType($type)) {
            'income' => 'Geldzufluss bzw. Ertrag — z. B. Barverkauf, sonstige Betriebseinnahmen.',
            'income_reduction' => 'Mindert eine frühere Einnahme — z. B. Erlösschmälerung, Skonto von Kunden.',
            'expense' => 'Geldabfluss bzw. Aufwand — z. B. Eingangsrechnung, Quittung, Betriebsausgabe.',
            'expense_reduction' => 'Mindert eine frühere Ausgabe — z. B. Erstattung, Skonto vom Lieferanten.',
            'credit' => 'Kundengutschrift an einen Kunden — mindert einen früheren Umsatz (z. B. Korrektur zu Ihrer Ausgangsrechnung). Nicht für Lieferantengutschriften — dafür „Ausgabenminderung“.',
            default => '',
        };
    }

    /**
     * paymentStatusOptions.
     *
     * @return array<string, string>
     */
        public static function paymentStatusOptions(): array
    {
        return VoucherPaymentStatus::options();
    }

    /**
     * @param array{year?: int|null, type?: string, document_kind?: string, document_status?: string, search?: string, page?: int, draft?: string, date_from?: string, date_to?: string} $filters
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
        $type = self::sanitizeVoucherTypeFilter((string) ($filters['type'] ?? ''));
        $documentKind = VoucherDocumentKind::sanitize((string) ($filters['document_kind'] ?? ''));
        $documentStatus = VoucherDocumentStatus::sanitize((string) ($filters['document_status'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));
        $draft = (string) ($filters['draft'] ?? '');

        $where = [];
        $params = [];

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateFrom !== '' && $dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1
        ) {
            $where[] = 'v.voucher_date BETWEEN :date_from AND :date_to';
            $params['date_from'] = $dateFrom;
            $params['date_to'] = $dateTo;
        } elseif ($year !== null) {
            $where[] = 'YEAR(v.voucher_date) = :year';
            $params['year'] = $year;
        }

        if ($type !== '') {
            $where[] = 'v.voucher_type = :voucher_type';
            $params['voucher_type'] = $type;
        }

        if ($documentKind !== '') {
            $where[] = 'v.document_kind = :document_kind';
            $params['document_kind'] = $documentKind;
        }

        if ($documentStatus !== '') {
            $where[] = 'v.document_status = :document_status';
            $params['document_status'] = $documentStatus;
        }

        if ($draft === '1') {
            $where[] = 'v.is_draft = 1';
        } elseif ($draft === '0') {
            $where[] = 'v.is_draft = 0';
        }

        if ($search !== '') {
            $where[] = '(
                v.supplier_name LIKE :q1 OR v.invoice_number LIKE :q2
                OR v.description LIKE :q3 OR v.account_number LIKE :q4
                OR c.display_name LIKE :q5 OR c.company_name LIKE :q6
            )';
            $like = '%' . $search . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
            $params['q5'] = $like;
            $params['q6'] = $like;
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

    /**
     * Anzahl offener Beleg-Entwürfe.
     */
    public static function countDrafts(): int
    {
        if (!Database::isConfigured()) {
            return 0;
        }

        MigrationRunner::runPending();
        $stmt = Database::pdo()->query('SELECT COUNT(*) FROM dg_vouchers WHERE is_draft = 1');

        return (int) $stmt->fetchColumn();
    }

    /**
     * Legt einen Beleg-Entwurf an (ohne Kontakt/Betrag/Konto-Pflicht).
     * Für Install-Import und sofortigen Datei-Upload vor dem Ausfüllen.
     *
     * @param array<string, mixed> $data
     */
    public static function createDraft(array $data = [], ?int $userId = null): int
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }

        MigrationRunner::runPending();

        $voucherType = self::sanitizeVoucherType((string) ($data['voucher_type'] ?? 'expense'));
        $voucherDate = trim((string) ($data['voucher_date'] ?? ''));
        if ($voucherDate === '' || strtotime($voucherDate) === false) {
            $voucherDate = date('Y-m-d');
        } else {
            $voucherDate = date('Y-m-d', strtotime($voucherDate));
        }

        $supplierName = trim((string) ($data['supplier_name'] ?? ''));
        if ($supplierName === '') {
            $supplierName = 'Import (offen)';
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_vouchers (
                voucher_type, is_draft, voucher_date, contact_id, supplier_name, invoice_number, description,
                gross_amount, net_amount, tax_amount, tax_rate, tax_key, account_number, payment_status, notes, created_by
            ) VALUES (
                :voucher_type, 1, :voucher_date, NULL, :supplier_name, :invoice_number, :description,
                0, 0, 0, 19, \'\', \'\', :payment_status, :notes, :created_by
            )'
        );
        $stmt->execute([
            'voucher_type' => $voucherType,
            'voucher_date' => $voucherDate,
            'supplier_name' => mb_substr($supplierName, 0, 191),
            'invoice_number' => mb_substr(trim((string) ($data['invoice_number'] ?? '')), 0, 100),
            'description' => mb_substr(trim((string) ($data['description'] ?? '')), 0, 500),
            'payment_status' => 'open',
            'notes' => trim((string) ($data['notes'] ?? '')),
            'created_by' => $userId !== null && $userId > 0 ? $userId : null,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * availableYears.
     *
     * @return list<int>
     */
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

    /**
     * Findet einen Datensatz anhand der ID
     * @param int $id Datensatz-ID
     * @return ?array
     */
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

        if (!$row) {
            return null;
        }

        $enriched = self::enrichRow($row);
        $enriched['lines'] = self::linesForVoucher($id, true);
        $enriched['system_lines'] = self::linesForVoucher($id, false, true);
        $enriched['items'] = self::itemsForVoucher($id);

        return $enriched;
    }

        /**
     * linesForVoucher
     * @param int $voucherId Beleg-ID
     * @param bool $bookingOnly
     * @param bool $systemOnly
     * @return list<array<string, mixed>>
     */
    public static function linesForVoucher(int $voucherId, bool $bookingOnly = false, bool $systemOnly = false): array
    {
        if (!Database::isConfigured() || $voucherId < 1) {
            return [];
        }

        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_voucher_lines WHERE voucher_id = :id ORDER BY line_no ASC, id ASC'
        );
        $stmt->execute(['id' => $voucherId]);
        $lines = [];
        $skrType = ChartOfAccountsSettings::activeSkrType();
        while ($row = $stmt->fetch()) {
            $lineKind = (string) ($row['line_kind'] ?? VoucherReverseCharge::LINE_BOOKING);
            if ($bookingOnly && $lineKind !== VoucherReverseCharge::LINE_BOOKING) {
                continue;
            }
            if ($systemOnly && $lineKind === VoucherReverseCharge::LINE_BOOKING) {
                continue;
            }
            $accountName = '';
            $accountNumber = (string) ($row['account_number'] ?? '');
            if ($accountNumber !== '') {
                $account = ChartAccountRepository::findByNumber($accountNumber, $skrType);
                if ($account !== null) {
                    $accountName = (string) ($account['name'] ?? '');
                }
            }
            $lines[] = [
                'line_kind' => $lineKind,
                'account_number' => $accountNumber,
                'account_name' => $accountName,
                'description' => (string) ($row['description'] ?? ''),
                'gross_amount' => self::formatMoney((float) ($row['gross_amount'] ?? 0)),
                'net_amount' => self::formatMoney((float) ($row['net_amount'] ?? 0)),
                'tax_amount' => self::formatMoney((float) ($row['tax_amount'] ?? 0)),
                'tax_rate' => (string) ($row['tax_rate'] ?? '19'),
                'ustva_kz' => (string) ($row['ustva_kz'] ?? ''),
                'posting_side' => (string) ($row['posting_side'] ?? ''),
            ];
        }

        return $lines;
    }

        /**
     * itemsForVoucher
     * @param int $voucherId Beleg-ID
     * @return list<array<string, mixed>>
     */
    public static function itemsForVoucher(int $voucherId): array
    {
        if (!Database::isConfigured() || $voucherId < 1) {
            return [];
        }

        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_voucher_items WHERE voucher_id = :id ORDER BY line_no ASC, id ASC'
        );
        $stmt->execute(['id' => $voucherId]);
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = [
                'article_id' => (string) ($row['article_id'] ?? ''),
                'catalog_kind' => (string) ($row['catalog_kind'] ?? ''),
                'article_number' => (string) ($row['article_number'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'area_id' => (string) ($row['area_id'] ?? ''),
                'area_name' => (string) ($row['area_name'] ?? ''),
                'unit' => (string) ($row['unit'] ?? 'Stück'),
                'quantity' => self::formatQuantity((float) ($row['quantity'] ?? 1)),
                'unit_price_gross' => self::formatMoney((float) ($row['unit_price_gross'] ?? 0)),
                'gross_amount' => self::formatMoney((float) ($row['gross_amount'] ?? 0)),
                'tax_rate' => (string) ($row['tax_rate'] ?? '19'),
                'tax_type' => (string) ($row['tax_type'] ?? 'ust19'),
            ];
        }

        return $items;
    }

        /**
     * replaceItems
     * @param int $voucherId Beleg-ID
     * @param array $itemRows
     * @return void
     */
    private static function replaceItems(int $voucherId, array $itemRows): void
    {
        MigrationRunner::runPending();
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM dg_voucher_items WHERE voucher_id = :id')->execute(['id' => $voucherId]);
        if ($itemRows === []) {
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_voucher_items (
                voucher_id, line_no, article_id, catalog_kind, article_number, title,
                area_id, area_name, unit, quantity, unit_price_gross, gross_amount, tax_rate, tax_type
            ) VALUES (
                :voucher_id, :line_no, :article_id, :catalog_kind, :article_number, :title,
                :area_id, :area_name, :unit, :quantity, :unit_price_gross, :gross_amount, :tax_rate, :tax_type
            )'
        );

        $lineNo = 1;
        foreach ($itemRows as $row) {
            $stmt->execute([
                'voucher_id' => $voucherId,
                'line_no' => $lineNo,
                'article_id' => max(0, (int) ($row['article_id'] ?? 0)),
                'catalog_kind' => (string) ($row['catalog_kind'] ?? CalendarArticleCatalog::KIND_SERVICE),
                'article_number' => (string) ($row['article_number'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'area_id' => max(0, (int) ($row['area_id'] ?? 0)),
                'area_name' => (string) ($row['area_name'] ?? ''),
                'unit' => (string) ($row['unit'] ?? 'Stück'),
                'quantity' => (float) ($row['quantity'] ?? 1),
                'unit_price_gross' => (float) ($row['unit_price_gross'] ?? 0),
                'gross_amount' => (float) ($row['gross_amount'] ?? 0),
                'tax_rate' => (int) ($row['tax_rate'] ?? 19),
                'tax_type' => (string) ($row['tax_type'] ?? 'ust19'),
            ]);
            $lineNo++;
        }
    }

    /**
     * formatQuantity
     * @param float $quantity
     * @return string
     */
    public static function formatQuantity(float $quantity): string
    {
        $formatted = number_format($quantity, 3, ',', '.');
        $formatted = rtrim(rtrim($formatted, '0'), ',');

        return $formatted === '' ? '0' : $formatted;
    }

        /**
     * previewReverseChargePostings
     * @param array $rawLines
     * @param string $reverseChargeType
     * @return array{lines: list<array<string, mixed>>, ustva_positions: list<array{kz: string, net: float, tax: float}>}
     */
    public static function previewReverseChargePostings(array $rawLines, string $reverseChargeType): array
    {
        $type = VoucherReverseCharge::sanitizeType($reverseChargeType);
        if ($type === '') {
            return ['lines' => [], 'ustva_positions' => []];
        }

        $bookingLines = self::parseLineRows(['lines' => $rawLines], true, $type);

        return VoucherReverseCharge::buildPostings($bookingLines, $type, ChartOfAccountsSettings::activeSkrType());
    }

        /**
     * previewAccrualPostings
     * @param array $rawLines
     * @param string $voucherType Belegtyp
     * @param int $currentPercent
     * @param int $nextPercent
     * @param string $voucherDate
     * @return list<array<string, mixed>>
     */
    public static function previewAccrualPostings(
        array $rawLines,
        string $voucherType,
        int $currentPercent,
        int $nextPercent,
        string $voucherDate,
    ): array {
        $bookingLines = self::parseLineRows(['lines' => $rawLines], false);
        $fiscalYear = (int) date('Y', strtotime($voucherDate !== '' ? $voucherDate : 'today'));

        return VoucherAccrual::previewRows(
            $bookingLines,
            $voucherType,
            ChartOfAccountsSettings::activeSkrType(),
            $currentPercent,
            $nextPercent,
            $fiscalYear,
            $fiscalYear + 1,
        );
    }

        /**
     * save
     * @param array $data
     * @param int|null $id Datensatz-ID
     * @param int|null $userId Benutzer-ID
     * @return int
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
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
        $deliveryDate = self::parseOptionalDate((string) ($data['delivery_date'] ?? ''));

        self::assertEditableFiscalYear($voucherDate);
        if ($id !== null && $id > 0) {
            $existing = self::findById($id);
            if ($existing !== null) {
                self::assertEditableFiscalYear((string) ($existing['voucher_date'] ?? ''));
            }
        }

        $contactId = max(0, (int) ($data['contact_id'] ?? 0));
        $contactId = $contactId > 0 ? $contactId : null;
        if ($contactId !== null && ContactRepository::findById($contactId) === null) {
            throw new InvalidArgumentException('Der gewählte Kontakt existiert nicht.');
        }

        $supplierName = trim((string) ($data['supplier_name'] ?? ''));
        if ($contactId === null) {
            throw new InvalidArgumentException('Bitte einen gespeicherten Kontakt aus dem CRM auswählen. Ein Freitext ohne Kontaktverknüpfung reicht nicht.');
        }
        if ($supplierName === '') {
            $contact = ContactRepository::findById($contactId);
            if ($contact !== null) {
                $supplierName = trim($contact->companyName);
                if ($supplierName === '') {
                    $supplierName = trim($contact->displayName);
                }
            }
        }
        if ($supplierName === '') {
            throw new InvalidArgumentException('Name des Kontakts / Lieferanten ist erforderlich.');
        }

        if (self::isExpenseType($voucherType) && trim((string) ($data['invoice_number'] ?? '')) === ''
            && $paymentStatus !== VoucherPaymentStatus::TIP) {
            throw new InvalidArgumentException('Bei Ausgaben ist die Rechnungsnummer erforderlich.');
        }

        $taxRate = VoucherTaxKeys::sanitizeTaxRate((int) ($data['tax_rate'] ?? 19));
        $reverseChargeType = VoucherReverseCharge::sanitizeType((string) ($data['reverse_charge_type'] ?? ''));
        if ($reverseChargeType === '' && (!empty($data['reverse_charge']) || VoucherTaxKeys::isReverseChargeKey((string) ($data['tax_key'] ?? '')))) {
            $reverseChargeType = VoucherReverseCharge::TYPE_EU;
        }
        $reverseCharge = VoucherReverseCharge::isActive($reverseChargeType);
        $taxKey = $reverseCharge ? VoucherTaxKeys::KEY_REVERSE_CHARGE : '';
        $paymentStatus = self::sanitizePaymentStatus((string) ($data['payment_status'] ?? 'open'));
        $documentKind = VoucherDocumentKind::sanitize((string) ($data['document_kind'] ?? ''));
        if ($voucherType === 'income' && $documentKind === '') {
            $documentKind = VoucherDocumentKind::INVOICE;
        }
        $documentStatus = VoucherDocumentStatus::sanitize((string) ($data['document_status'] ?? ''));
        if ($voucherType === 'income' && $documentKind !== '') {
            if ($documentStatus === '') {
                $documentStatus = VoucherDocumentStatus::defaultForKind($documentKind);
            }
            if (!VoucherDocumentStatus::isValidForKind($documentStatus, $documentKind)) {
                throw new InvalidArgumentException('Ungültiger Dokumentstatus für diese Dokumentart.');
            }
        } else {
            $documentStatus = '';
        }
        $documentIntroText = '';
        $documentFooterText = '';
        $documentLegalClauses = [];
        $paymentTermTiers = [];
        $paymentDueDate = '';
        if (VoucherDocumentKind::usesPositionTexts($documentKind, $voucherType)) {
            $documentIntroText = self::sanitizeDocumentText((string) ($data['document_intro_text'] ?? ''));
            $documentFooterText = self::sanitizeDocumentText((string) ($data['document_footer_text'] ?? ''));
            $documentLegalClauses = VoucherDocumentLegalClause::parseFromRequest($data);
        }
        if ($voucherType === 'income' && VoucherDocumentKind::isBookable($documentKind, $voucherType)) {
            $paymentTermTiers = PaymentTermsService::sanitizeTiers($data['payment_term_tiers'] ?? AccountingPaymentSettings::defaultTiers());
            $paymentDueDate = trim((string) ($data['payment_due_date'] ?? ''));
            if ($paymentDueDate === '' && $paymentTermTiers !== []) {
                $paymentDueDate = PaymentTermsService::dueDateFromTiers($voucherDate, $paymentTermTiers);
            }
            if ($paymentDueDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDueDate)) {
                throw new InvalidArgumentException('Ungültiges Fälligkeitsdatum.');
            }
        }
        $parentVoucherId = max(0, (int) ($data['parent_voucher_id'] ?? 0));
        $arap = VoucherAccrual::parseFromData($data);
        if (!VoucherAccrual::supportsAccrual($voucherType, $documentKind)) {
            $arap = [
                'enabled' => false,
                'current_year_percent' => 100,
                'next_year_percent' => 0,
            ];
        }
        if ($arap['enabled'] && $reverseCharge) {
            throw new InvalidArgumentException('Rechnungsabgrenzung und Reverse Charge können nicht gleichzeitig aktiv sein.');
        }
        $skrType = ChartOfAccountsSettings::activeSkrType();
        ChartAccountRepository::ensureSeeded($skrType);
        $usesInvoiceItems = VoucherIncomePositions::usesInvoiceItems($voucherType);
        /** @var list<array<string, mixed>> $itemRows */
        $itemRows = $usesInvoiceItems
            ? VoucherIncomePositions::parseItemRows($data, $voucherType)
            : [];
        $gross = round((float) str_replace(',', '.', (string) ($data['gross_amount'] ?? '0')), 2);
        /** @var list<array<string, mixed>> $lineRows */
        if ($usesInvoiceItems && $itemRows !== []) {
            $bookingLines = VoucherIncomePositions::bookingLinesFromItems($itemRows, $skrType, $voucherType);
            if ($bookingLines === []) {
                throw new InvalidArgumentException('Mindestens eine Rechnungsposition mit Betrag ist erforderlich.');
            }
        } else {
            $bookingLines = self::parseLineRows($data, $reverseCharge, $reverseChargeType);
        }
        if ($paymentStatus === VoucherPaymentStatus::TIP && !self::isExpenseType($voucherType)) {
            throw new InvalidArgumentException('Trinkgeld ist nur bei Ausgaben-Belegen möglich.');
        }
        if ($paymentStatus === VoucherPaymentStatus::TIP && self::isExpenseType($voucherType)) {
            $tipAccount = LedgerAccounts::tipPassThroughAccount($skrType);
            foreach ($bookingLines as &$tipLine) {
                if (trim((string) ($tipLine['account_number'] ?? '')) === '') {
                    $tipLine['account_number'] = $tipAccount;
                }
            }
            unset($tipLine);
        }
        $ustvaSnapshot = null;
        if ($reverseCharge) {
            $built = VoucherReverseCharge::buildPostings($bookingLines, $reverseChargeType, ChartOfAccountsSettings::activeSkrType());
            $lineRows = $built['lines'];
            $ustvaSnapshot = json_encode($built['ustva_positions'], JSON_THROW_ON_ERROR);
        } elseif ($arap['enabled']) {
            $fiscalYear = (int) date('Y', strtotime($voucherDate));
            $lineRows = VoucherAccrual::buildPostings(
                $bookingLines,
                $voucherType,
                $skrType,
                $arap['current_year_percent'],
                $arap['next_year_percent'],
                $fiscalYear + 1,
            );
        } else {
            $lineRows = $bookingLines;
        }

        if ($gross <= 0 && $bookingLines !== []) {
            $gross = round(array_sum(array_map(static fn (array $row): float => (float) $row['gross_amount'], $bookingLines)), 2);
        }

        $amounts = VoucherTaxKeys::calcTaxFromGross($gross, $taxRate);
        $accountNumber = preg_replace('/\D/', '', (string) ($data['account_number'] ?? '')) ?? '';
        if ($lineRows !== []) {
            $accountNumber = (string) ($bookingLines[0]['account_number'] ?? '');
            $amounts = self::sumLineAmounts($bookingLines);
            $gross = $amounts['gross_amount'];
            $taxRate = (int) ($bookingLines[0]['tax_rate'] ?? 19);
        } elseif ($gross <= 0) {
            if (self::allowsSignedItemAmounts($voucherType) && $gross < 0) {
                throw new InvalidArgumentException('Negativer Gesamtbetrag — bitte Belegart „Kundengutschrift“ oder „Einnahmenminderung“ verwenden.');
            }
            throw new InvalidArgumentException('Bruttobetrag muss größer als 0 sein.');
        }

        if ($accountNumber === '') {
            throw new InvalidArgumentException('Kontonummer ist erforderlich.');
        }
        if (ChartAccountRepository::findByNumber($accountNumber, $skrType) === null) {
            throw new InvalidArgumentException('Kontonummer nicht im aktiven Kontenrahmen gefunden.');
        }

        $discountPercent = max(0, min(100, (int) ($data['discount_percent'] ?? 0)));
        $discountAmount = round(max(0, (float) str_replace(',', '.', (string) ($data['discount_amount'] ?? '0'))), 2);
        if ($discountPercent > 0 && $discountAmount <= 0.0 && $gross > 0) {
            $discountAmount = round($gross * $discountPercent / 100, 2);
        }
        $paidAmount = round(max(0, (float) str_replace(',', '.', (string) ($data['paid_amount'] ?? '0'))), 2);
        $paidAt = trim((string) ($data['paid_at'] ?? ''));
        if (VoucherPaymentStatus::isSettled($paymentStatus) || $paymentStatus === VoucherPaymentStatus::BANK) {
            if ($paidAmount <= 0.0) {
                $paidAmount = round(max(0, $gross - $discountAmount), 2);
            }
            if ($paidAt === '') {
                $paidAt = date('Y-m-d');
            }
            if ($paymentTermTiers !== [] && $paidAt !== '' && empty($data['discount_manual'])) {
                $settlement = PaymentTermsService::settlementAmounts($gross, $voucherDate, $paidAt, $paymentTermTiers);
                $discountPercent = $settlement['discount_percent'];
                $discountAmount = $settlement['discount_amount'];
                $paidAmount = $settlement['paid_amount'];
            }
        } else {
            $paidAmount = 0.0;
            $paidAt = '';
        }

        foreach ($lineRows as $lineRow) {
            $lineAccount = (string) ($lineRow['account_number'] ?? '');
            if ($lineAccount === '' || ChartAccountRepository::findByNumber($lineAccount, $skrType) === null) {
                throw new InvalidArgumentException('Buchungszeile: Kontonummer nicht im Kontenrahmen gefunden.');
            }
        }

        $fields = [
            'voucher_type' => $voucherType,
            'document_kind' => $documentKind,
            'document_status' => $documentStatus,
            'parent_voucher_id' => $parentVoucherId > 0 ? $parentVoucherId : null,
            'voucher_date' => $voucherDate,
            'delivery_date' => $deliveryDate,
            'arap_enabled' => $arap['enabled'] ? 1 : 0,
            'arap_current_year_percent' => $arap['current_year_percent'],
            'arap_next_year_percent' => $arap['next_year_percent'],
            'contact_id' => $contactId,
            'supplier_name' => $supplierName,
            'invoice_number' => self::resolveInvoiceNumber($voucherType, $data, $id, $documentKind),
            'description' => trim((string) ($data['description'] ?? '')),
            'gross_amount' => $amounts['gross_amount'],
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'paid_amount' => $paidAmount,
            'paid_at' => $paidAt !== '' ? $paidAt : null,
            'net_amount' => $amounts['net_amount'],
            'tax_amount' => $amounts['tax_amount'],
            'tax_rate' => $taxRate,
            'tax_key' => $taxKey,
            'reverse_charge_type' => $reverseChargeType,
            'ustva_snapshot' => $ustvaSnapshot,
            'account_number' => $accountNumber,
            'payment_status' => $paymentStatus,
            'notes' => trim((string) ($data['notes'] ?? '')),
            'document_intro_text' => $documentIntroText !== '' ? $documentIntroText : null,
            'document_footer_text' => $documentFooterText !== '' ? $documentFooterText : null,
            'document_legal_clauses' => VoucherDocumentLegalClause::encodeSelection($documentLegalClauses),
            'payment_due_date' => $paymentDueDate !== '' ? $paymentDueDate : null,
            'payment_term_tiers' => PaymentTermsService::encodeTiers($paymentTermTiers),
        ];

        if ($contactId !== null) {
            PersonAccountService::ensureCreditorAccount($contactId);
            PersonAccountService::ensureDebtorAccount($contactId);
        }

        $pdo = Database::pdo();

        if ($id) {
            $stmt = $pdo->prepare(
                'UPDATE dg_vouchers SET
                    voucher_type = :voucher_type,
                    document_kind = :document_kind,
                    document_status = :document_status,
                    parent_voucher_id = :parent_voucher_id,
                    is_draft = 0,
                    voucher_date = :voucher_date,
                    delivery_date = :delivery_date,
                    arap_enabled = :arap_enabled,
                    arap_current_year_percent = :arap_current_year_percent,
                    arap_next_year_percent = :arap_next_year_percent,
                    contact_id = :contact_id,
                    supplier_name = :supplier_name,
                    invoice_number = :invoice_number,
                    description = :description,
                    gross_amount = :gross_amount,
                    discount_percent = :discount_percent,
                    discount_amount = :discount_amount,
                    paid_amount = :paid_amount,
                    paid_at = :paid_at,
                    net_amount = :net_amount,
                    tax_amount = :tax_amount,
                    tax_rate = :tax_rate,
                    tax_key = :tax_key,
                    reverse_charge_type = :reverse_charge_type,
                    ustva_snapshot = :ustva_snapshot,
                    account_number = :account_number,
                    payment_status = :payment_status,
                    notes = :notes,
                    document_intro_text = :document_intro_text,
                    document_footer_text = :document_footer_text,
                    document_legal_clauses = :document_legal_clauses,
                    payment_due_date = :payment_due_date,
                    payment_term_tiers = :payment_term_tiers
                 WHERE id = :id'
            );
            $fields['id'] = $id;
            $stmt->execute($fields);
            self::replaceLines($id, $lineRows);
            self::replaceItems($id, $itemRows);
            self::syncLedger($id);

            return $id;
        }

        $fields['created_by'] = $userId;
        $stmt = $pdo->prepare(
            'INSERT INTO dg_vouchers (
                voucher_type, document_kind, document_status, parent_voucher_id, voucher_date, delivery_date, arap_enabled, arap_current_year_percent, arap_next_year_percent,
                contact_id, supplier_name, invoice_number, description,
                gross_amount, discount_percent, discount_amount, paid_amount, paid_at,
                net_amount, tax_amount, tax_rate, tax_key, reverse_charge_type, ustva_snapshot,
                account_number, payment_status, notes, document_intro_text, document_footer_text, document_legal_clauses,
                payment_due_date, payment_term_tiers, created_by
            ) VALUES (
                :voucher_type, :document_kind, :document_status, :parent_voucher_id, :voucher_date, :delivery_date, :arap_enabled, :arap_current_year_percent, :arap_next_year_percent,
                :contact_id, :supplier_name, :invoice_number, :description,
                :gross_amount, :discount_percent, :discount_amount, :paid_amount, :paid_at,
                :net_amount, :tax_amount, :tax_rate, :tax_key, :reverse_charge_type, :ustva_snapshot,
                :account_number, :payment_status, :notes, :document_intro_text, :document_footer_text, :document_legal_clauses,
                :payment_due_date, :payment_term_tiers, :created_by
            )'
        );
        $stmt->execute($fields);
        $newId = (int) $pdo->lastInsertId();
        self::replaceLines($newId, $lineRows);
        self::replaceItems($newId, $itemRows);
        self::syncLedger($newId);

        return $newId;
    }

        /**
     * parseLineRows
     * @param array $data
     * @param bool $reverseCharge Reverse-Charge-Modus
     * @param string $reverseChargeType
     * @return list<array{account_number: string, description: string, gross_amount: float, net_amount: float, tax_amount: float, tax_rate: int}>
     * @throws InvalidArgumentException
     */
    private static function parseLineRows(array $data, bool $reverseCharge, string $reverseChargeType = ''): array
    {
        $rawLines = $data['lines'] ?? [];
        if (!is_array($rawLines)) {
            return [];
        }

        $rows = [];
        foreach ($rawLines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $accountNumber = preg_replace('/\D/', '', (string) ($line['account_number'] ?? '')) ?? '';
            $gross = round((float) str_replace(',', '.', (string) ($line['gross_amount'] ?? '0')), 2);
            if ($accountNumber === '' || $gross <= 0) {
                continue;
            }
            $rate = $reverseCharge
                ? VoucherReverseCharge::sanitizeLineTaxRate($reverseChargeType, (int) ($line['tax_rate'] ?? 19))
                : VoucherTaxKeys::sanitizeTaxRate((int) ($line['tax_rate'] ?? 19));
            $amounts = VoucherTaxKeys::calcLineAmounts($gross, $rate, $reverseCharge);
            $rows[] = [
                'line_kind' => VoucherReverseCharge::LINE_BOOKING,
                'account_number' => $accountNumber,
                'description' => trim((string) ($line['description'] ?? '')),
                'gross_amount' => $amounts['gross_amount'],
                'net_amount' => $amounts['net_amount'],
                'tax_amount' => $amounts['tax_amount'],
                'tax_rate' => $rate,
                'ustva_kz' => '',
                'posting_side' => 'debit',
            ];
        }

        if ($rows === []) {
            throw new InvalidArgumentException('Mindestens eine Buchungszeile mit Konto und Betrag ist erforderlich.');
        }

        return $rows;
    }

        /**
     * amountsFromBookingLines
     * @param array $bookingLines
     * @param bool $reverseCharge Reverse-Charge-Modus
     * @return array{gross_amount: float, net_amount: float, tax_amount: float}
     */
    private static function amountsFromBookingLines(array $bookingLines, bool $reverseCharge): array
    {
        $rows = [];
        foreach ($bookingLines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $gross = round((float) str_replace(',', '.', (string) ($line['gross_amount'] ?? '0')), 2);
            if ($gross <= 0) {
                continue;
            }
            $rate = (int) ($line['tax_rate'] ?? 19);
            $amounts = VoucherTaxKeys::calcLineAmounts($gross, $rate, $reverseCharge);
            $rows[] = $amounts;
        }

        return $rows === [] ? ['gross_amount' => 0.0, 'net_amount' => 0.0, 'tax_amount' => 0.0] : self::sumLineAmounts($rows);
    }

    /**
     * @param list<array{gross_amount: float, net_amount: float, tax_amount: float}> $lineRows
     * @return array{gross_amount: float, net_amount: float, tax_amount: float}
     */
    private static function sumLineAmounts(array $lineRows): array
    {
        $gross = 0.0;
        $net = 0.0;
        $tax = 0.0;
        foreach ($lineRows as $row) {
            $gross += (float) $row['gross_amount'];
            $net += (float) $row['net_amount'];
            $tax += (float) $row['tax_amount'];
        }

        return [
            'gross_amount' => round($gross, 2),
            'net_amount' => round($net, 2),
            'tax_amount' => round($tax, 2),
        ];
    }

        /**
     * replaceLines
     * @param int $voucherId Beleg-ID
     * @param array $lineRows
     * @return void
     */
    private static function replaceLines(int $voucherId, array $lineRows): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM dg_voucher_lines WHERE voucher_id = :id')->execute(['id' => $voucherId]);
        if ($lineRows === []) {
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_voucher_lines (
                voucher_id, line_no, line_kind, account_number, description,
                gross_amount, net_amount, tax_amount, tax_rate, ustva_kz, posting_side
            ) VALUES (
                :voucher_id, :line_no, :line_kind, :account_number, :description,
                :gross_amount, :net_amount, :tax_amount, :tax_rate, :ustva_kz, :posting_side
            )'
        );

        $lineNo = 1;
        foreach ($lineRows as $row) {
            $stmt->execute([
                'voucher_id' => $voucherId,
                'line_no' => $lineNo,
                'line_kind' => (string) ($row['line_kind'] ?? VoucherReverseCharge::LINE_BOOKING),
                'account_number' => $row['account_number'],
                'description' => $row['description'] ?? '',
                'gross_amount' => $row['gross_amount'],
                'net_amount' => $row['net_amount'],
                'tax_amount' => $row['tax_amount'],
                'tax_rate' => $row['tax_rate'],
                'ustva_kz' => (string) ($row['ustva_kz'] ?? ''),
                'posting_side' => ($row['posting_side'] ?? '') !== '' ? $row['posting_side'] : null,
            ]);
            $lineNo++;
        }
    }

    /**
     * Löscht einen Datensatz
     * @param int $id Datensatz-ID
     * @return void
     * @throws RuntimeException
     */
    public static function delete(int $id): void
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }

        $existing = self::findById($id);
        if ($existing !== null) {
            self::assertEditableFiscalYear((string) ($existing['voucher_date'] ?? ''));
        }

        LedgerPostingService::deleteForVoucher($id);
        $stmt = Database::pdo()->prepare('DELETE FROM dg_vouchers WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

        /**
     * Journalbuchungen des Belegs neu aufbauen (fehlerresistent).
     * @param int $voucherId Beleg-ID
     * @return void
     */
    private static function syncLedger(int $voucherId): void
    {
        try {
            LedgerPostingService::rebuildForVoucher($voucherId);
        } catch (Throwable) {
            // Journal darf das Speichern des Belegs nie blockieren.
        }
    }

    public static function refreshLedger(int $voucherId): void
    {
        self::syncLedger($voucherId);
    }

    /**
     * emptyForm.
     *
     * @return array<string, string>
     */
        public static function emptyForm(): array
    {
        return [
            'voucher_type' => 'expense',
            'document_kind' => '',
            'document_status' => '',
            'parent_voucher_id' => '',
            'voucher_date' => date('Y-m-d'),
            'delivery_date' => date('Y-m-d'),
            'arap_enabled' => '0',
            'arap_current_year_percent' => '100',
            'arap_next_year_percent' => '0',
            'contact_id' => '',
            'contact_label' => '',
            'supplier_name' => '',
            'invoice_number' => '',
            'description' => '',
            'gross_amount' => '',
            'discount_percent' => '0',
            'discount_amount' => '',
            'paid_amount' => '',
            'paid_at' => '',
            'net_amount' => '',
            'tax_amount' => '',
            'tax_rate' => '19',
            'tax_key' => '',
            'reverse_charge_type' => '',
            'ustva_snapshot' => '',
            'account_number' => '',
            'account_name' => '',
            'payment_status' => 'open',
            'notes' => '',
            'document_intro_text' => '',
            'document_footer_text' => '',
            'document_legal_clauses' => [],
            'payment_due_date' => '',
            'payment_term_tiers' => AccountingPaymentSettings::defaultTiers(),
            'dunning_level' => '0',
            'dunning_fee_total' => '0',
            'last_dunning_sent_at' => '',
            'lines' => [
                [
                    'account_number' => '',
                    'account_name' => '',
                    'gross_amount' => '',
                    'tax_rate' => '19',
                ],
            ],
            'items' => [
                [
                    'article_id' => '',
                    'catalog_kind' => '',
                    'article_number' => '',
                    'title' => '',
                    'area_id' => '',
                    'area_name' => '',
                    'unit' => 'Stück',
                    'quantity' => '1',
                    'unit_price_gross' => '',
                    'gross_amount' => '',
                    'tax_rate' => '19',
                    'tax_type' => 'ust19',
                ],
            ],
        ];
    }

        /**
     * toForm
     * @param array $row Datenbankzeile
     * @return array
     */
    public static function toForm(array $row): array
    {
        $lines = is_array($row['lines'] ?? null) ? $row['lines'] : self::linesForVoucher((int) ($row['id'] ?? 0), true);
        $items = is_array($row['items'] ?? null) ? $row['items'] : self::itemsForVoucher((int) ($row['id'] ?? 0));
        if ($items === [] && VoucherIncomePositions::usesInvoiceItems((string) ($row['voucher_type'] ?? ''))) {
            $items = [
                [
                    'article_id' => '',
                    'catalog_kind' => '',
                    'article_number' => '',
                    'title' => '',
                    'area_id' => '',
                    'area_name' => '',
                    'unit' => 'Stück',
                    'quantity' => '1',
                    'unit_price_gross' => '',
                    'gross_amount' => '',
                    'tax_rate' => '19',
                    'tax_type' => 'ust19',
                ],
            ];
        }
        if ($lines === [] && (string) ($row['account_number'] ?? '') !== '') {
            $lines = [[
                'account_number' => (string) ($row['account_number'] ?? ''),
                'account_name' => (string) ($row['account_name'] ?? ''),
                'gross_amount' => self::formatMoney((float) ($row['gross_amount'] ?? 0)),
                'tax_rate' => (string) ($row['tax_rate'] ?? '19'),
            ]];
        }

        $ustvaPositions = [];
        $snapshot = (string) ($row['ustva_snapshot'] ?? '');
        if ($snapshot !== '') {
            $decoded = json_decode($snapshot, true);
            if (is_array($decoded)) {
                $ustvaPositions = $decoded;
            }
        }

        return [
            'voucher_type' => self::sanitizeVoucherType((string) ($row['voucher_type'] ?? 'expense')),
            'document_kind' => (string) ($row['document_kind'] ?? ''),
            'document_status' => (string) ($row['document_status'] ?? ''),
            'parent_voucher_id' => $row['parent_voucher_id'] !== null ? (string) $row['parent_voucher_id'] : '',
            'voucher_date' => (string) ($row['voucher_date'] ?? ''),
            'delivery_date' => (string) ($row['delivery_date'] ?? ''),
            'arap_enabled' => !empty($row['arap_enabled']) ? '1' : '0',
            'arap_current_year_percent' => (string) ($row['arap_current_year_percent'] ?? '100'),
            'arap_next_year_percent' => (string) ($row['arap_next_year_percent'] ?? '0'),
            'contact_id' => $row['contact_id'] !== null ? (string) $row['contact_id'] : '',
            'contact_label' => (string) ($row['supplier_display'] ?? ''),
            'supplier_name' => (string) ($row['supplier_name'] ?? ''),
            'invoice_number' => (string) ($row['invoice_number'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'gross_amount' => self::formatMoney((float) ($row['gross_amount'] ?? 0)),
            'discount_percent' => (string) ($row['discount_percent'] ?? '0'),
            'discount_amount' => self::formatMoney((float) ($row['discount_amount'] ?? 0)),
            'paid_amount' => self::formatMoney((float) ($row['paid_amount'] ?? 0)),
            'paid_at' => (string) ($row['paid_at'] ?? ''),
            'net_amount' => self::formatMoney((float) ($row['net_amount'] ?? 0)),
            'tax_amount' => self::formatMoney((float) ($row['tax_amount'] ?? 0)),
            'tax_rate' => (string) ($row['tax_rate'] ?? '19'),
            'tax_key' => (string) ($row['tax_key'] ?? ''),
            'reverse_charge_type' => VoucherReverseCharge::sanitizeType((string) ($row['reverse_charge_type'] ?? '')),
            'ustva_snapshot' => (string) ($row['ustva_snapshot'] ?? ''),
            'account_number' => (string) ($row['account_number'] ?? ''),
            'account_name' => (string) ($row['account_name'] ?? ''),
            'payment_status' => (string) ($row['payment_status'] ?? 'open'),
            'notes' => (string) ($row['notes'] ?? ''),
            'document_intro_text' => (string) ($row['document_intro_text'] ?? ''),
            'document_footer_text' => (string) ($row['document_footer_text'] ?? ''),
            'document_legal_clauses' => VoucherDocumentLegalClause::sanitizeSelection($row['document_legal_clauses'] ?? ''),
            'payment_due_date' => (string) ($row['payment_due_date'] ?? ''),
            'payment_term_tiers' => PaymentTermsService::sanitizeTiers($row['payment_term_tiers'] ?? ''),
            'dunning_level' => (string) ($row['dunning_level'] ?? '0'),
            'dunning_fee_total' => self::formatMoney((float) ($row['dunning_fee_total'] ?? 0)),
            'last_dunning_sent_at' => (string) ($row['last_dunning_sent_at'] ?? ''),
            'lines' => $lines,
            'items' => $items,
            'system_lines' => is_array($row['system_lines'] ?? null) ? $row['system_lines'] : [],
            'ustva_positions' => $ustvaPositions,
        ];
    }

    /**
     * typeLabel
     * @param string $type
     * @return string
     */
    public static function typeLabel(string $type): string
    {
        return self::voucherTypeOptions()[self::sanitizeVoucherType($type)] ?? $type;
    }

    /**
     * normalizeVoucherType
     * @param string $type
     * @return string
     */
    public static function normalizeVoucherType(string $type): string
    {
        return self::sanitizeVoucherType($type);
    }

        /**
     * Ausgaben-Belegarten (Aufwand) – hier ist die Rechnungsnummer Pflicht.
     * @param string $voucherType Belegtyp
     * @return bool
     */
    public static function isExpenseType(string $voucherType): bool
    {
        return in_array(self::sanitizeVoucherType($voucherType), ['expense', 'expense_reduction'], true);
    }

    /**
     * Belegarten, bei denen Rechnungspositionen negative Beträge tragen können (Rabatt-/Gutschriftzeilen).
     */
    public static function allowsSignedItemAmounts(string $voucherType): bool
    {
        return in_array(
            self::sanitizeVoucherType($voucherType),
            ['income', 'credit', 'income_reduction', 'expense_reduction'],
            true
        );
    }

    /**
     * Nummernkreis je Ausgangsbeleg-Typ oder Belegart.
     */
    public static function numberRangeTypeForDocument(string $documentKind, string $voucherType): ?string
    {
        $fromKind = VoucherDocumentKind::numberRangeType(VoucherDocumentKind::sanitize($documentKind));
        if ($fromKind !== null) {
            return $fromKind;
        }

        return self::numberRangeTypeForVoucher($voucherType);
    }

    public static function peekDocumentNumber(string $voucherType, string $documentKind = ''): string
    {
        $rangeType = self::numberRangeTypeForDocument($documentKind, $voucherType);
        if ($rangeType === null) {
            throw new InvalidArgumentException('Für diese Dokumentart gibt es keinen Nummernkreis.');
        }

        return NumberRangeSettings::allocateNext($rangeType, false)['number'];
    }

    public static function usesAutoDocumentNumber(string $voucherType, string $documentKind = ''): bool
    {
        return self::numberRangeTypeForDocument($documentKind, $voucherType) !== null;
    }

    /**
     * numberRangeTypeForVoucher
     * @param string $voucherType Belegtyp
     * @return ?string
     */
    public static function numberRangeTypeForVoucher(string $voucherType): ?string
    {
        return match (self::sanitizeVoucherType($voucherType)) {
            'income' => 'invoice',
            'credit' => 'credit_note',
            default => null,
        };
    }

    /**
     * autoInvoiceNumberLabels.
     *
     * @return array<string, string> Belegart => Nummernkreis-Bezeichnung
     */
        public static function autoInvoiceNumberLabels(): array
    {
        $labels = [];
        foreach (self::voucherTypeOptions() as $voucherType => $_label) {
            $rangeType = self::numberRangeTypeForVoucher($voucherType);
            if ($rangeType !== null) {
                $labels[$voucherType] = NumberRangeSettings::documentTypes()[$rangeType] ?? $rangeType;
            }
        }

        return $labels;
    }

    /**
     * @return array<string, string> Dokumentart => Nummernkreis-Bezeichnung (Einnahmen-Kette)
     */
    public static function autoDocumentKindNumberLabels(): array
    {
        $labels = [];
        foreach (VoucherDocumentKind::options() as $kind => $_label) {
            $rangeType = VoucherDocumentKind::numberRangeType($kind);
            if ($rangeType !== null) {
                $labels[$kind] = NumberRangeSettings::documentTypes()[$rangeType] ?? $rangeType;
            }
        }

        return $labels;
    }

    /**
     * usesAutoInvoiceNumber
     * @param string $voucherType Belegtyp
     * @return bool
     */
    public static function usesAutoInvoiceNumber(string $voucherType): bool
    {
        return self::numberRangeTypeForVoucher($voucherType) !== null;
    }

    /**
     * peekInvoiceNumber
     * @param string $voucherType Belegtyp
     * @return string
     * @throws InvalidArgumentException
     */
    public static function peekInvoiceNumber(string $voucherType, string $documentKind = ''): string
    {
        return self::peekDocumentNumber($voucherType, $documentKind);
    }

    /**
     * Dokumentstatus setzen (Workflow, ohne vollständiges Formular).
     */
    public static function updateDocumentStatus(int $voucherId, string $status): void
    {
        if (!Database::isConfigured() || $voucherId < 1) {
            throw new InvalidArgumentException('Beleg nicht gefunden.');
        }

        MigrationRunner::runPending();
        $voucher = self::findById($voucherId);
        if ($voucher === null) {
            throw new InvalidArgumentException('Beleg nicht gefunden.');
        }

        $documentKind = (string) ($voucher['document_kind'] ?? '');
        if ($documentKind === '' || self::normalizeVoucherType((string) ($voucher['voucher_type'] ?? '')) !== 'income') {
            throw new InvalidArgumentException('Dokumentstatus ist nur für Ausgangsbelege verfügbar.');
        }

        $status = VoucherDocumentStatus::sanitize($status);
        if ($status === '' || !VoucherDocumentStatus::isValidForKind($status, $documentKind)) {
            throw new InvalidArgumentException('Ungültiger Dokumentstatus.');
        }

        $stmt = Database::pdo()->prepare(
            'UPDATE dg_vouchers SET document_status = :document_status WHERE id = :id'
        );
        $stmt->execute([
            'document_status' => $status,
            'id' => $voucherId,
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function assertEditableFiscalYear(string $voucherDate): void
    {
        $year = (int) date('Y', strtotime($voucherDate));
        if ($year >= 2000 && FiscalYearService::isClosed($year)) {
            throw new InvalidArgumentException(
                'Geschäftsjahr ' . $year . ' ist abgeschlossen — Belege können nicht mehr geändert werden.'
            );
        }
    }

    /**
     * parseOptionalDate
     * @param string $value Eingabewert
     * @return ?string
     */
    private static function parseOptionalDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || strtotime($value) === false) {
            return null;
        }

        return date('Y-m-d', strtotime($value));
    }

        /**
     * resolveInvoiceNumber
     * @param string $voucherType Belegtyp
     * @param array $data
     * @param int|null $id Datensatz-ID
     * @return string
     */
    private static function resolveInvoiceNumber(string $voucherType, array $data, ?int $id, string $documentKind = ''): string
    {
        $rangeType = self::numberRangeTypeForDocument($documentKind, $voucherType);
        if ($rangeType === null) {
            return trim((string) ($data['invoice_number'] ?? ''));
        }

        if ($id) {
            $existing = self::findById($id);
            $existingNumber = trim((string) ($existing['invoice_number'] ?? ''));
            if ($existingNumber !== '') {
                return $existingNumber;
            }
        }

        return NumberRangeSettings::allocateNext($rangeType, true)['number'];
    }

    /**
     * paymentLabel
     * @param string $status Statuswert
     * @return string
     */
    public static function paymentLabel(string $status): string
    {
        return VoucherPaymentStatus::label($status);
    }

    /**
     * formatMoney
     * @param float $amount Betrag
     * @return string
     */
    public static function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', '.');
    }

    /**
     * sanitizeVoucherType
     * @param string $type
     * @return string
     */
    private static function sanitizeVoucherType(string $type): string
    {
        $type = strtolower(trim($type));
        if ($type === 'receipt' || $type === 'invoice') {
            return 'expense';
        }

        return isset(self::voucherTypeOptions()[$type]) ? $type : 'expense';
    }

        /**
     * Leerer Filter = alle Belegarten (nicht auf „Ausgaben“ einschränken).
     * @param string $type
     * @return string
     */
    private static function sanitizeVoucherTypeFilter(string $type): string
    {
        $type = strtolower(trim($type));
        if ($type === 'receipt' || $type === 'invoice') {
            return 'expense';
        }

        return isset(self::voucherTypeOptions()[$type]) ? $type : '';
    }

    /**
     * sanitizePaymentStatus
     * @param string $status Statuswert
     * @return string
     */
    private static function sanitizePaymentStatus(string $status): string
    {
        return VoucherPaymentStatus::sanitize($status);
    }

    private static function sanitizeDocumentText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return mb_substr($text, 0, 4000);
    }

        /**
     * enrichRow
     * @param array $row Datenbankzeile
     * @return array
     */
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
        $row['is_draft'] = !empty($row['is_draft']);
        $row['type_label'] = self::typeLabel((string) ($row['voucher_type'] ?? ''));
        $documentKind = (string) ($row['document_kind'] ?? '');
        $row['document_kind_label'] = VoucherDocumentKind::label($documentKind);
        if ($documentKind !== '' && VoucherRepository::normalizeVoucherType((string) ($row['voucher_type'] ?? '')) === 'income') {
            $row['type_label'] = $row['document_kind_label'];
        }
        $documentStatus = (string) ($row['document_status'] ?? '');
        $row['document_status_label'] = VoucherDocumentStatus::label($documentStatus);
        $row['document_status_badge_class'] = VoucherDocumentStatus::badgeClass($documentStatus);
        $row['payment_label'] = self::paymentLabel((string) ($row['payment_status'] ?? ''));
        $row['payment_badge_class'] = VoucherPaymentStatus::badgeClass((string) ($row['payment_status'] ?? ''));
        $row['payment_settlement_kind'] = VoucherPaymentStatus::settlementKind((string) ($row['payment_status'] ?? ''));

        $voucherId = (int) ($row['id'] ?? 0);
        $reverseCharge = VoucherReverseCharge::isActive((string) ($row['reverse_charge_type'] ?? ''));
        if ($voucherId > 0 && Database::isConfigured()) {
            $bookingLines = self::linesForVoucher($voucherId, true);
            if ($bookingLines !== []) {
                $breakdown = VoucherTaxKeys::taxBreakdownFromLines($bookingLines, $reverseCharge);
                $amounts = self::amountsFromBookingLines($bookingLines, $reverseCharge);
                $row['gross_amount'] = $amounts['gross_amount'];
                $row['net_amount'] = $amounts['net_amount'];
                $row['tax_amount'] = $amounts['tax_amount'];
                $row['tax_display_lines'] = VoucherTaxKeys::taxBreakdownDisplayLines($breakdown);
            } else {
                $headerRate = VoucherTaxKeys::sanitizeTaxRate((int) ($row['tax_rate'] ?? 19));
                $headerTax = round((float) ($row['tax_amount'] ?? 0), 2);
                $fallback = array_fill_keys(VoucherTaxKeys::allowedTaxRates(), 0.0);
                $fallback[$headerRate] = $headerTax;
                $row['tax_display_lines'] = VoucherTaxKeys::taxBreakdownDisplayLines($fallback);
            }
        } else {
            $row['tax_display_lines'] = [];
        }

        return $row;
    }
}
