<?php
declare(strict_types=1);

/**
 * Selftest K1k: Registerkasse-Schema (Migrationen, Tabellen, Kernspalten, Repo-Klassen).
 * php bin/cash-register-schema-selftest.php
 */
require dirname(__DIR__) . '/bootstrap.php';

$fail = 0;

function expect_true(bool $cond, string $msg): void
{
    global $fail;
    if ($cond) {
        echo "OK  {$msg}\n";
    } else {
        echo "FAIL {$msg}\n";
        $fail++;
    }
}

/** @return list<string> */
function cash_reg_columns(string $table): array
{
    $stmt = Database::pdo()->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
    if ($stmt === false) {
        return [];
    }
    $cols = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cols[] = (string) ($row['Field'] ?? '');
    }

    return $cols;
}

function expect_columns(string $table, array $required): void
{
    $have = cash_reg_columns($table);
    foreach ($required as $col) {
        expect_true(in_array($col, $have, true), $table . '.' . $col);
    }
}

$migrations = [
    '143_cash_locations.sql',
    '144_cash_registers.sql',
    '145_cash_tse_assignments.sql',
    '146_cash_sessions.sql',
    '147_cash_receipts.sql',
    '148_cash_payments.sql',
    '149_cash_register_day_closings.sql',
    '150_cash_audit_exports.sql',
    '152_cash_tse_outage.sql',
    '153_cash_journal_receipt.sql',
    '154_cash_ers_melde.sql',
];
foreach ($migrations as $file) {
    $path = DG_ROOT . '/database/migrations/' . $file;
    expect_true(is_readable($path), 'Migration-Datei ' . $file);
}

$classes = [
    'CashRegisterTable',
    'CashLocationRepository',
    'CashRegisterRepository',
    'CashTseAssignmentRepository',
    'CashTseOutageService',
    'CashRegisterFiBuSettings',
    'CashRegisterFiBuPostingService',
    'CashReceiptJournalCsvExport',
    'CashDsfinvkStubExport',
    'CashPrueferpaketExport',
    'CashErsTaxpayerRepository',
    'CashErsClientRepository',
    'CashSessionRepository',
    'CashReceiptRepository',
    'CashPaymentRepository',
    'CashRegisterDayClosingRepository',
    'CashAuditLogRepository',
    'CashExportRepository',
];
foreach ($classes as $class) {
    expect_true(class_exists($class), 'Klasse ' . $class);
}

if (!Database::isConfigured()) {
    echo "SKIP DB — keine Instanz-DB konfiguriert (Dateien/Klassen geprüft).\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-register-schema-selftest: {$fail} Fehler\n");
        exit(1);
    }
    echo "cash-register-schema-selftest OK (ohne DB)\n";
    exit(0);
}

MigrationRunner::runPending();

$tables = [
    'dg_cash_locations' => ['id', 'code', 'name', 'street', 'zip', 'city', 'country', 'is_active', 'sort_order'],
    'dg_cash_registers' => ['id', 'location_id', 'code', 'register_type', 'next_receipt_number', 'receipt_number_prefix', 'is_active'],
    'dg_cash_tse_assignments' => ['id', 'register_id', 'provider', 'environment', 'tss_id', 'client_id', 'is_active', 'outage_active'],
    'dg_cash_sessions' => ['id', 'register_id', 'business_date', 'status', 'opening_balance', 'opened_by'],
    'dg_cash_receipts' => ['id', 'location_id', 'register_id', 'session_id', 'receipt_number', 'status', 'receipt_type', 'total_gross', 'tse_tx_id', 'tse_outage'],
    'dg_cash_receipt_lines' => ['id', 'receipt_id', 'line_no', 'title', 'quantity', 'gross_amount', 'tax_rate'],
    'dg_cash_payments' => ['id', 'receipt_id', 'line_no', 'payment_method', 'amount'],
    'dg_cash_register_day_closings' => ['id', 'location_id', 'register_id', 'business_date', 'total_cash', 'total_gross', 'receipt_count'],
    'dg_cash_audit_log' => ['id', 'event_type', 'severity', 'message', 'created_at'],
    'dg_cash_exports' => ['id', 'export_type', 'status', 'file_path', 'created_at'],
    'dg_cash_ers_taxpayer' => ['id', 'tax_number', 'tax_office_number', 'updated_at'],
    'dg_cash_ers_clients' => ['id', 'register_id', 'ers_type', 'manufacturer', 'model', 'serial_number', 'data_status'],
];

foreach ($tables as $table => $cols) {
    $stmt = Database::pdo()->query('SHOW TABLES LIKE ' . Database::pdo()->quote($table));
    $exists = $stmt !== false && $stmt->fetchColumn() !== false;
    expect_true($exists, 'Tabelle ' . $table);
    if ($exists) {
        expect_columns($table, $cols);
    }
}

expect_true(CashLocationRepository::tableExists(), 'CashLocationRepository::tableExists');
expect_true(CashRegisterRepository::tableExists(), 'CashRegisterRepository::tableExists');
expect_true(CashTseAssignmentRepository::tableExists(), 'CashTseAssignmentRepository::tableExists');
expect_true(CashSessionRepository::tableExists(), 'CashSessionRepository::tableExists');
expect_true(CashReceiptRepository::tableExists(), 'CashReceiptRepository::tableExists');
expect_true(CashPaymentRepository::tableExists(), 'CashPaymentRepository::tableExists');
expect_true(CashRegisterDayClosingRepository::tableExists(), 'CashRegisterDayClosingRepository::tableExists');
expect_true(CashAuditLogRepository::tableExists(), 'CashAuditLogRepository::tableExists');
expect_true(CashExportRepository::tableExists(), 'CashExportRepository::tableExists');

// Abgrenzung FiBu-Kassenbuch (anderer Tabellenname)
expect_true(
    'dg_cash_register_day_closings' !== 'dg_cash_day_closings',
    'Register-Tagesabschluss-Name ≠ FiBu dg_cash_day_closings'
);
$fibu = Database::pdo()->query("SHOW TABLES LIKE 'dg_cash_day_closings'");
if ($fibu !== false && $fibu->fetchColumn() !== false) {
    echo "OK  FiBu dg_cash_day_closings vorhanden (parallel, unberührt)\n";
} else {
    echo "SKIP FiBu dg_cash_day_closings (in dieser DB noch nicht migriert)\n";
}

if ($fail > 0) {
    fwrite(STDERR, "cash-register-schema-selftest: {$fail} Fehler\n");
    exit(1);
}

echo "cash-register-schema-selftest OK\n";
exit(0);
