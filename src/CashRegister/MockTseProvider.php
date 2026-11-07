<?php
declare(strict_types=1);

/**
 * Mock-TSE ohne Netz (K2b) — feste Testwerte laut Plan K2a.
 * PHP-Interface folgt in K3a; Methodensignaturen bereits kompatibel.
 *
 * @phpstan-type TseTxInput array{
 *   receipt_type?: string,
 *   amount_gross?: float|int|string,
 *   vat_amounts?: list<array{vat_rate_code?: string, vat_rate?: string, amount?: float|int|string}>,
 *   client_serial_number?: string,
 *   process_type?: string,
 *   process_data?: string
 * }
 * @phpstan-type TseTxResult array{
 *   tse_tx_id: string,
 *   state: string,
 *   tx_number: int|null,
 *   revision: int|null,
 *   signature_value: string|null,
 *   signature_algorithm: string|null,
 *   signature_counter: string|null,
 *   qr_code_data: string|null,
 *   time_start: int|null,
 *   time_end: int|null,
 *   tss_serial_number: string,
 *   client_serial_number: string,
 *   raw: array<string, mixed>|null
 * }
 */
final class MockTseProvider
{
    public const NAME = 'mock';

    public const TSS_ID = '00000000-0000-4000-8000-000000000001';
    public const CLIENT_ID = '00000000-0000-4000-8000-000000000002';
    public const TSS_SERIAL = 'MOCK-TSS-SERIAL';
    public const CLIENT_SERIAL = 'MOCK-CLIENT';

    /** @var array<string, array<string, mixed>> */
    private array $txs = [];

    private int $nextTxNumber = 1;

    private int $signatureCounter = 1;

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return array{
     *   ok: bool,
     *   state: string,
     *   tss_id: string,
     *   client_id: string,
     *   tss_serial_number: string,
     *   certificate_valid_until: string|null,
     *   message: string
     * }
     */
    public function getStatus(): array
    {
        return [
            'ok' => true,
            'state' => 'INITIALIZED',
            'tss_id' => self::TSS_ID,
            'client_id' => self::CLIENT_ID,
            'tss_serial_number' => self::TSS_SERIAL,
            'certificate_valid_until' => '2099-12-31',
            'message' => 'Mock-TSE bereit (kein Netz).',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function startTransaction(string $txId, array $input = []): array
    {
        $txId = $this->requireTxId($txId);
        if (isset($this->txs[$txId])) {
            throw new RuntimeException('Mock-TSE: Transaktion existiert bereits.');
        }
        $now = time();
        $this->txs[$txId] = [
            'tse_tx_id' => $txId,
            'state' => 'ACTIVE',
            'tx_number' => $this->nextTxNumber++,
            'revision' => 1,
            'signature_value' => null,
            'signature_algorithm' => null,
            'signature_counter' => null,
            'qr_code_data' => null,
            'time_start' => $now,
            'time_end' => null,
            'tss_serial_number' => self::TSS_SERIAL,
            'client_serial_number' => $this->clientSerial($input),
            'input' => $input,
            'raw' => ['mock' => true, 'phase' => 'start'],
        ];

        return $this->publicResult($this->txs[$txId]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function updateTransaction(string $txId, int $revision, array $input = []): array
    {
        $tx = &$this->requireActive($txId, $revision);
        $tx['revision'] = $revision + 1;
        $tx['input'] = $input;
        $tx['client_serial_number'] = $this->clientSerial($input, (string) $tx['client_serial_number']);
        $tx['raw'] = ['mock' => true, 'phase' => 'update'];

        return $this->publicResult($tx);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function finishTransaction(string $txId, int $revision, array $input = []): array
    {
        $tx = &$this->requireActive($txId, $revision);
        $tx['input'] = $input !== [] ? $input : $tx['input'];
        $tx['client_serial_number'] = $this->clientSerial($tx['input'], (string) $tx['client_serial_number']);
        $tx['state'] = 'FINISHED';
        $tx['revision'] = $revision + 1;
        $tx['time_end'] = time();
        $counter = (string) $this->signatureCounter++;
        $tx['signature_counter'] = $counter;
        $tx['signature_algorithm'] = 'MOCK-ecdsa';
        $tx['signature_value'] = 'MOCK-SIGNATURE-' . $counter;
        $gross = round((float) ($tx['input']['amount_gross'] ?? 0), 2);
        $tx['qr_code_data'] = 'V0;' . self::TSS_SERIAL . ';' . $tx['tse_tx_id'] . ';' . $counter . ';' . number_format($gross, 2, '.', '');
        $tx['raw'] = ['mock' => true, 'phase' => 'finish', 'amount_gross' => $gross];

        return $this->publicResult($tx);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelTransaction(string $txId, int $revision): array
    {
        $tx = &$this->requireActive($txId, $revision);
        $tx['state'] = 'CANCELLED';
        $tx['revision'] = $revision + 1;
        $tx['time_end'] = time();
        $tx['signature_value'] = null;
        $tx['signature_algorithm'] = null;
        $tx['signature_counter'] = null;
        $tx['qr_code_data'] = null;
        $tx['raw'] = ['mock' => true, 'phase' => 'cancel'];

        return $this->publicResult($tx);
    }

    /**
     * @param array<string, mixed> $request
     * @return array{ok: bool, file_bytes: string|null, download_ref: string|null, checksum: string, message: string}
     */
    public function exportData(array $request = []): array
    {
        $payload = "MOCK-TSE-EXPORT\nfrom=" . ($request['date_from'] ?? '')
            . "\nto=" . ($request['date_to'] ?? '') . "\n";

        return [
            'ok' => true,
            'file_bytes' => $payload,
            'download_ref' => null,
            'checksum' => hash('sha256', $payload),
            'message' => 'Mock-Export (kein echtes TAR).',
        ];
    }

    private function requireTxId(string $txId): string
    {
        $txId = strtolower(trim($txId));
        if ($txId === '' || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $txId)) {
            throw new RuntimeException('Mock-TSE: ungültige Transaktions-ID.');
        }

        return $txId;
    }

    /**
     * @return array<string, mixed>
     */
    private function &requireActive(string $txId, int $revision): array
    {
        $txId = $this->requireTxId($txId);
        if (!isset($this->txs[$txId])) {
            throw new RuntimeException('Mock-TSE: Transaktion unbekannt.');
        }
        $tx = &$this->txs[$txId];
        if (($tx['state'] ?? '') !== 'ACTIVE') {
            throw new RuntimeException('Mock-TSE: Transaktion ist nicht ACTIVE.');
        }
        if ((int) ($tx['revision'] ?? 0) !== $revision) {
            throw new RuntimeException('Mock-TSE: Revisionskonflikt.');
        }

        return $tx;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function clientSerial(array $input, string $fallback = self::CLIENT_SERIAL): string
    {
        $serial = trim((string) ($input['client_serial_number'] ?? ''));

        return $serial !== '' ? $serial : $fallback;
    }

    /**
     * @param array<string, mixed> $tx
     * @return array<string, mixed>
     */
    private function publicResult(array $tx): array
    {
        return [
            'tse_tx_id' => (string) $tx['tse_tx_id'],
            'state' => (string) $tx['state'],
            'tx_number' => isset($tx['tx_number']) ? (int) $tx['tx_number'] : null,
            'revision' => isset($tx['revision']) ? (int) $tx['revision'] : null,
            'signature_value' => $tx['signature_value'] ?? null,
            'signature_algorithm' => $tx['signature_algorithm'] ?? null,
            'signature_counter' => $tx['signature_counter'] ?? null,
            'qr_code_data' => $tx['qr_code_data'] ?? null,
            'time_start' => isset($tx['time_start']) ? (int) $tx['time_start'] : null,
            'time_end' => isset($tx['time_end']) ? (int) $tx['time_end'] : null,
            'tss_serial_number' => (string) ($tx['tss_serial_number'] ?? self::TSS_SERIAL),
            'client_serial_number' => (string) ($tx['client_serial_number'] ?? self::CLIENT_SERIAL),
            'raw' => is_array($tx['raw'] ?? null) ? $tx['raw'] : null,
        ];
    }
}
