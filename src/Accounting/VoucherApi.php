<?php
declare(strict_types=1);

/**
 * Voucher Api.
 */
final class VoucherApi
{
    /**
     * HTTP-API-Einstieg
     * @return void
     */
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $user = AuthService::user();
        if (!$user || !MenuRegistry::canAccess($user, 'buchhaltung-belege')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Keine Berechtigung.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $action = trim((string) ($_GET['action'] ?? ''));
            if ($action === 'reverse_charge_preview') {
                self::handleReverseChargePreview();
                return;
            }
            if ($action === 'file_upload') {
                self::handleFileUpload();
                return;
            }

            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Unbekannte POST-Aktion.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Nur GET oder POST erlaubt.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $action = trim((string) ($_GET['action'] ?? ''));

        try {
            if ($action === 'contacts') {
                self::handleContactSearch();
                return;
            }

            if ($action === 'account') {
                self::handleAccountLookup();
                return;
            }

            if ($action === 'account_search') {
                self::handleAccountSearch();
                return;
            }

            if ($action === 'invoice_number_preview') {
                self::handleInvoiceNumberPreview();
                return;
            }

            if ($action === 'article_search') {
                self::handleArticleSearch();
                return;
            }

            if ($action === 'ledger_preview') {
                self::handleLedgerPreview();
                return;
            }

            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Parameter action erforderlich (contacts, account, account_search, invoice_number_preview, article_search, ledger_preview).',
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * handleContactSearch
     * @return void
     */
    private static function handleContactSearch(): void
    {
        $user = AuthService::user();
        $query = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($query) < 1) {
            echo json_encode([
                'success' => true,
                'data' => ['items' => []],
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!Database::isConfigured()) {
            echo json_encode([
                'success' => true,
                'data' => ['items' => []],
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $items = [];
        foreach (ContactRepository::searchPicker($query, 15, $user) as $contact) {
            $label = trim($contact->companyName);
            if ($label === '') {
                $label = trim($contact->displayName);
            }
            if ($label === '') {
                $label = trim($contact->firstName . ' ' . $contact->lastName);
            }
            if ($label === '') {
                $label = trim($contact->email);
            }

            $meta = [];
            if ($contact->email !== '') {
                $meta[] = $contact->email;
            }
            if ($contact->companyName !== '' && $label !== $contact->companyName) {
                $meta[] = $contact->companyName;
            }
            if ($contact->supplierNumber !== '') {
                $meta[] = 'Lief.-Nr. ' . $contact->supplierNumber;
            }
            if ($contact->customerNumber !== '') {
                $meta[] = 'Kd.-Nr. ' . $contact->customerNumber;
            }
            if ($contact->login !== '') {
                $meta[] = 'Login ' . $contact->login;
            }

            $items[] = [
                'id' => $contact->id,
                'label' => $label,
                'meta' => implode(' · ', $meta),
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => ['items' => $items],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * voucherTypeFromRequest
     * @param string $raw Rohdaten
     * @return string
     */
    private static function voucherTypeFromRequest(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        return VoucherRepository::normalizeVoucherType($raw);
    }

    /**
     * handleAccountLookup
     * @return void
     */
    private static function handleAccountLookup(): void
    {
        $number = preg_replace('/\D/', '', (string) ($_GET['number'] ?? '')) ?? '';
        if ($number === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Parameter number erforderlich.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $voucherType = self::voucherTypeFromRequest((string) ($_GET['voucher_type'] ?? ''));
        $skrType = ChartOfAccountsSettings::activeSkrType();
        ChartAccountRepository::ensureSeeded($skrType);
        $account = ChartAccountRepository::findByNumber($number, $skrType);
        if ($account === null
            || ($voucherType !== '' && !ChartAccountBookingEligibility::isSearchableRowForVoucherType($account, $voucherType))) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Konto nicht gefunden oder für diese Belegart nicht buchbar.',
                'valid' => false,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $suggestedTaxRate = ChartAccountBookingEligibility::inferTaxRateFromAccountName((string) ($account['name'] ?? ''));

        echo json_encode([
            'success' => true,
            'valid' => true,
            'data' => [
                'account_number' => (string) ($account['account_number'] ?? ''),
                'name' => (string) ($account['name'] ?? ''),
                'section_label' => (string) ($account['section_label'] ?? ''),
                'suggested_tax_rate' => $suggestedTaxRate,
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * handleAccountSearch
     * @return void
     */
    private static function handleAccountSearch(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($query) < 2) {
            echo json_encode([
                'success' => true,
                'data' => ['items' => []],
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $skrType = ChartOfAccountsSettings::activeSkrType();
        ChartAccountRepository::ensureSeeded($skrType);
        $voucherType = self::voucherTypeFromRequest((string) ($_GET['voucher_type'] ?? ''));
        $taxRate = isset($_GET['tax_rate']) && $_GET['tax_rate'] !== '' ? (int) $_GET['tax_rate'] : null;
        $results = ChartAccountRepository::search($query, $skrType, 20, $voucherType, $taxRate);
        $items = [];
        foreach ($results as $account) {
            $suggestedTaxRate = ChartAccountBookingEligibility::inferTaxRateFromAccountName((string) ($account['name'] ?? ''));
            $items[] = [
                'account_number' => (string) ($account['account_number'] ?? ''),
                'name' => (string) ($account['name'] ?? ''),
                'section_label' => (string) ($account['section_label'] ?? ''),
                'suggested_tax_rate' => $suggestedTaxRate,
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => ['items' => $items],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * handleInvoiceNumberPreview
     * @return void
     */
    private static function handleInvoiceNumberPreview(): void
    {
        $voucherType = VoucherRepository::normalizeVoucherType((string) ($_GET['voucher_type'] ?? ''));
        if (!VoucherRepository::usesAutoInvoiceNumber($voucherType)) {
            echo json_encode([
                'success' => true,
                'data' => ['number' => '', 'auto' => false],
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $rangeType = VoucherRepository::numberRangeTypeForVoucher($voucherType);
        echo json_encode([
            'success' => true,
            'data' => [
                'number' => VoucherRepository::peekInvoiceNumber($voucherType),
                'auto' => true,
                'range_type' => $rangeType,
                'range_label' => NumberRangeSettings::documentTypes()[$rangeType ?? ''] ?? '',
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * handleArticleSearch
     * @return void
     */
    private static function handleArticleSearch(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));

        echo json_encode([
            'success' => true,
            'data' => ['items' => VoucherIncomePositions::searchArticles($query, 15)],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Sofortiger Datei-Upload: legt bei Bedarf einen Entwurf an und hängt die Datei an.
     */
    private static function handleFileUpload(): void
    {
        $user = AuthService::user();
        if ($user === null || !RoleResolver::canEdit($user)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Keine Berechtigung.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF ungültig.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!Database::isConfigured()) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Datenbank nicht verbunden.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Keine Datei hochgeladen.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $voucherId = max(0, (int) ($_POST['voucher_id'] ?? 0));
            if ($voucherId > 0) {
                if (VoucherRepository::findById($voucherId) === null) {
                    throw new InvalidArgumentException('Beleg nicht gefunden.');
                }
            } else {
                $original = (string) ($file['name'] ?? 'beleg');
                $voucherId = VoucherRepository::createDraft([
                    'supplier_name' => pathinfo($original, PATHINFO_FILENAME) ?: 'Neuer Beleg',
                    'description' => 'Entwurf aus Datei-Upload: ' . $original,
                    'notes' => 'Datei hochgeladen — bitte Kontakt und Beträge ergänzen.',
                ], $user->id);
            }

            VoucherFileStorage::processUploads($voucherId, [
                'name' => $file['name'] ?? 'beleg',
                'type' => $file['type'] ?? '',
                'tmp_name' => $file['tmp_name'] ?? '',
                'error' => $file['error'] ?? UPLOAD_ERR_NO_FILE,
                'size' => $file['size'] ?? 0,
            ], $user->id);

            $files = VoucherFileStorage::listForVoucher($voucherId);
            echo json_encode([
                'success' => true,
                'data' => [
                    'voucher_id' => $voucherId,
                    'files' => $files,
                    'file' => $files !== [] ? $files[array_key_last($files)] : null,
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * handleReverseChargePreview
     * @return void
     */
    private static function handleReverseChargePreview(): void
    {
        $raw = file_get_contents('php://input');
        $body = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($body)) {
            $body = [];
        }

        $type = VoucherReverseCharge::sanitizeType((string) ($body['type'] ?? ''));
        $lines = is_array($body['lines'] ?? null) ? $body['lines'] : [];
        $built = VoucherRepository::previewReverseChargePostings($lines, $type);
        $skrType = ChartOfAccountsSettings::activeSkrType();

        foreach ($built['lines'] as &$line) {
            $accountNumber = (string) ($line['account_number'] ?? '');
            $line['account_name'] = '';
            if ($accountNumber !== '') {
                $account = ChartAccountRepository::findByNumber($accountNumber, $skrType);
                if ($account !== null) {
                    $line['account_name'] = (string) ($account['name'] ?? '');
                }
            }
            $line['gross_amount'] = VoucherRepository::formatMoney((float) ($line['gross_amount'] ?? 0));
        }
        unset($line);

        foreach ($built['ustva_positions'] as &$pos) {
            $pos['net'] = round((float) ($pos['net'] ?? 0), 2);
            $pos['tax'] = round((float) ($pos['tax'] ?? 0), 2);
        }
        unset($pos);

        echo json_encode([
            'success' => true,
            'data' => $built,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Vorschau der Buchungssätze aus Formulardaten (ohne Speichern).
     */
    private static function handleLedgerPreview(): void
    {
        $voucherId = max(0, (int) ($_GET['voucher_id'] ?? 0));
        $voucher = [
            'id' => $voucherId,
            'voucher_type' => VoucherRepository::normalizeVoucherType((string) ($_GET['voucher_type'] ?? 'expense')),
            'voucher_date' => trim((string) ($_GET['voucher_date'] ?? date('Y-m-d'))),
            'payment_status' => VoucherPaymentStatus::sanitize((string) ($_GET['payment_status'] ?? 'open')),
            'contact_id' => max(0, (int) ($_GET['contact_id'] ?? 0)),
            'supplier_name' => trim((string) ($_GET['supplier_name'] ?? '')),
            'invoice_number' => trim((string) ($_GET['invoice_number'] ?? '')),
            'description' => trim((string) ($_GET['description'] ?? '')),
            'tax_key' => VoucherTaxKeys::sanitizeTaxKey((string) ($_GET['tax_key'] ?? '')),
            'reverse_charge_type' => VoucherReverseCharge::sanitizeType((string) ($_GET['reverse_charge_type'] ?? '')),
            'gross_amount' => (float) str_replace(',', '.', (string) ($_GET['gross_amount'] ?? '0')),
            'net_amount' => (float) str_replace(',', '.', (string) ($_GET['net_amount'] ?? '0')),
            'tax_amount' => (float) str_replace(',', '.', (string) ($_GET['tax_amount'] ?? '0')),
            'tax_rate' => VoucherTaxKeys::sanitizeTaxRate((int) ($_GET['tax_rate'] ?? 19)),
            'account_number' => preg_replace('/\D/', '', (string) ($_GET['account_number'] ?? '')) ?? '',
            'discount_amount' => (float) str_replace(',', '.', (string) ($_GET['discount_amount'] ?? '0')),
            'paid_amount' => (float) str_replace(',', '.', (string) ($_GET['paid_amount'] ?? '0')),
        ];

        $linesJson = trim((string) ($_GET['lines_json'] ?? ''));
        if ($linesJson !== '') {
            $decoded = json_decode($linesJson, true);
            if (is_array($decoded)) {
                $voucher['lines'] = $decoded;
            }
        }

        $raw = LedgerPostingService::previewPostings($voucher);
        $meta = LedgerRepository::accountMeta();
        $items = [];
        foreach ($raw as $row) {
            $acc = (string) ($row['account_number'] ?? '');
            $contra = (string) ($row['contra_account'] ?? '');
            $items[] = [
                'side' => (string) ($row['side'] ?? 'debit'),
                'side_label' => (string) ($row['side'] ?? 'debit') === 'credit' ? 'H' : 'S',
                'account_number' => $acc,
                'account_name' => (string) ($meta[$acc]['name'] ?? ''),
                'contra_account' => $contra,
                'contra_name' => (string) ($meta[$contra]['name'] ?? ''),
                'amount' => VoucherRepository::formatMoney((float) ($row['amount'] ?? 0)),
                'tax_key' => (string) ($row['tax_key'] ?? ''),
                'tax_key_label' => VoucherTaxKeys::label((string) ($row['tax_key'] ?? '')),
                'document_field1' => (string) ($row['document_field1'] ?? ''),
                'document_field2' => (string) ($row['document_field2'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => ['items' => $items],
        ], JSON_UNESCAPED_UNICODE);
    }
}
