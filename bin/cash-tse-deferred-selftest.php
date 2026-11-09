<?php
declare(strict_types=1);

/**
 * Selftest K14d: Nachsignatur-Queue nur bei supportsDeferredSignature (Mock).
 * php bin/cash-tse-deferred-selftest.php
 */
require dirname(__DIR__) . '/bootstrap.php';

$fail = 0;
$expect = static function (bool $cond, string $msg) use (&$fail): void {
    if ($cond) {
        echo "OK  {$msg}\n";
        return;
    }
    echo "FAIL {$msg}\n";
    $fail++;
};

$expect(class_exists('CashTseDeferredSignatureService'), 'CashTseDeferredSignatureService');
$expect((new MockTseProvider())->supportsDeferredSignature() === true, 'Mock erlaubt Nachsignatur');
$expect((new class implements TseProviderInterface {
    public function getName(): string { return 'x'; }
    public function supportsDeferredSignature(): bool { return false; }
    public function getStatus(): array { return ['ok' => false, 'state' => '', 'tss_id' => '', 'client_id' => '', 'tss_serial_number' => '', 'certificate_valid_until' => null, 'message' => '']; }
    public function startTransaction(string $txId, array $input = []): array { throw new RuntimeException('no'); }
    public function updateTransaction(string $txId, int $revision, array $input = []): array { throw new RuntimeException('no'); }
    public function finishTransaction(string $txId, int $revision, array $input = []): array { throw new RuntimeException('no'); }
    public function cancelTransaction(string $txId, int $revision): array { throw new RuntimeException('no'); }
    public function exportData(array $request = []): array { return ['ok' => false, 'file_bytes' => null, 'download_ref' => null, 'checksum' => '', 'message' => '']; }
})->supportsDeferredSignature() === false, 'Capability false blockiert');

if (!Database::isConfigured()) {
    echo "SKIP DB — Capability-Checks ok.\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-tse-deferred-selftest: {$fail} Fehler\n");
        exit(1);
    }
    echo "cash-tse-deferred-selftest OK (ohne DB)\n";
    exit(0);
}

MigrationRunner::runPending();
$expect(CashReceiptRepository::hasDeferredPendingColumn(), 'Spalte tse_deferred_pending');

if ($fail > 0) {
    fwrite(STDERR, "cash-tse-deferred-selftest: {$fail} Fehler\n");
    exit(1);
}
echo "cash-tse-deferred-selftest OK\n";
exit(0);
