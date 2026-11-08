<?php
declare(strict_types=1);

/**
 * Fiskaly SIGN DE Adapter hinter TseProviderInterface (K3b).
 * Wickelt TseSignDeClient + Schema/VAT-Logik aus TseSignService.
 */
final class FiskalyTseProvider implements TseProviderInterface
{
    public const NAME = 'fiskaly';

    private TseSignDeClient $client;

    /** @var array<string, mixed> */
    private array $cfg;

    /**
     * @param array<string, mixed>|null $cfg wirksame Client-Config (Secrets + TSS/Client)
     * @param callable|null $transport injizierbar für Selftests
     */
    public function __construct(?array $cfg = null, ?callable $transport = null)
    {
        $this->cfg = $cfg ?? TseSettings::effective();
        $this->requireConfig($this->cfg);
        $this->client = new TseSignDeClient($this->cfg, $transport);
    }

    /**
     * Config aus Assignment + Instanz-Secrets (D9).
     *
     * @param array<string, mixed> $assignment
     * @return array<string, mixed>
     */
    public static function configFromAssignment(array $assignment): array
    {
        $cfg = TseSettings::effective();
        $tss = trim((string) ($assignment['tss_id'] ?? ''));
        $clientId = trim((string) ($assignment['client_id'] ?? ''));
        if ($tss !== '') {
            $cfg['tss_id'] = TseSettings::sanitizeUuid($tss) ?: $tss;
        }
        if ($clientId !== '') {
            $cfg['client_id'] = TseSettings::sanitizeUuid($clientId) ?: $clientId;
        }
        $env = strtolower(trim((string) ($assignment['environment'] ?? '')));
        if ($env === TseSettings::ENV_TEST || $env === TseSettings::ENV_LIVE) {
            $cfg['environment'] = $env;
            $cfg['base_url'] = $env === TseSettings::ENV_LIVE
                ? TseSettings::LIVE_BASE_URL
                : TseSettings::TEST_BASE_URL;
        }

        return $cfg;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /** Cloud-Fiskaly: keine Offline-Nachsignatur bis Herstellerfreigabe (K14c). */
    public function supportsDeferredSignature(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        try {
            $tss = $this->client->retrieveTss();
            $client = $this->client->retrieveClient();
            $state = trim((string) ($tss['state'] ?? $tss['tss_state'] ?? ''));
            $ok = $state === '' || strtoupper($state) === 'INITIALIZED' || strtoupper($state) === 'ACTIVE';
            $certUntil = null;
            foreach (['certificate_expiry_date', 'certificate_valid_until', 'valid_until'] as $key) {
                $raw = trim((string) ($tss[$key] ?? $client[$key] ?? ''));
                if ($raw !== '') {
                    $certUntil = substr($raw, 0, 10);
                    break;
                }
            }

            return [
                'ok' => $ok,
                'state' => $state !== '' ? $state : 'OK',
                'tss_id' => $this->client->tssId(),
                'client_id' => $this->client->clientId(),
                'tss_serial_number' => (string) ($tss['serial_number'] ?? $tss['tss_serial_number'] ?? ''),
                'certificate_valid_until' => $certUntil,
                'message' => $ok ? 'fiskaly SIGN DE erreichbar.' : ('TSS-Status: ' . $state),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'state' => 'ERROR',
                'tss_id' => (string) ($this->cfg['tss_id'] ?? ''),
                'client_id' => (string) ($this->cfg['client_id'] ?? ''),
                'tss_serial_number' => '',
                'certificate_valid_until' => null,
                'message' => mb_substr($e->getMessage(), 0, 500),
            ];
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function startTransaction(string $txId, array $input = []): array
    {
        $txId = $this->requireTxId($txId);
        $body = [
            'state' => 'ACTIVE',
            'client_id' => $this->client->clientId(),
        ];
        $raw = $this->client->upsertTransaction($txId, 1, $body);

        return $this->mapResult($txId, $raw, $input);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function updateTransaction(string $txId, int $revision, array $input = []): array
    {
        $txId = $this->requireTxId($txId);
        if ($revision < 1) {
            throw new RuntimeException('fiskaly: ungültige Revision.');
        }
        $body = [
            'state' => 'ACTIVE',
            'client_id' => $this->client->clientId(),
        ];
        $raw = $this->client->upsertTransaction($txId, $revision, $body);

        return $this->mapResult($txId, $raw, $input);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function finishTransaction(string $txId, int $revision, array $input = []): array
    {
        $txId = $this->requireTxId($txId);
        if ($revision < 1) {
            throw new RuntimeException('fiskaly: ungültige Revision.');
        }
        $body = [
            'state' => 'FINISHED',
            'client_id' => $this->client->clientId(),
            'schema' => self::buildSchemaFromInput($input),
        ];
        $raw = $this->client->upsertTransaction($txId, $revision, $body);

        return $this->mapResult($txId, $raw, $input);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelTransaction(string $txId, int $revision): array
    {
        $txId = $this->requireTxId($txId);
        if ($revision < 1) {
            throw new RuntimeException('fiskaly: ungültige Revision.');
        }
        $body = [
            'state' => 'CANCELLED',
            'client_id' => $this->client->clientId(),
        ];
        $raw = $this->client->upsertTransaction($txId, $revision, $body);

        return $this->mapResult($txId, $raw, []);
    }

    /**
     * @param array<string, mixed> $request
     * @return array{ok: bool, file_bytes: string|null, download_ref: string|null, checksum: string, message: string}
     */
    public function exportData(array $request = []): array
    {
        return [
            'ok' => false,
            'file_bytes' => null,
            'download_ref' => null,
            'checksum' => '',
            'message' => 'fiskaly TAR-Export folgt in K18b.',
        ];
    }

    /**
     * Schema aus Bon-Input (VAT + Zahlarten) für SIGN DE standard_v1.receipt.
     *
     * @param array<string, mixed> $input
     * @return array{standard_v1: array{receipt: array<string, mixed>}}
     */
    public static function buildSchemaFromInput(array $input): array
    {
        $receiptType = self::mapReceiptType((string) ($input['receipt_type'] ?? 'sale'));
        $gross = round((float) ($input['amount_gross'] ?? 0), 2);
        $vatLines = self::normalizeVatAmounts($input, $gross);
        $vatRates = [];
        foreach ($vatLines as $line) {
            $vatRates[] = [
                'vat_rate' => $line['vat_rate'],
                'amount' => TseSignService::money((float) $line['amount']),
            ];
        }
        $payments = self::normalizePaymentTypes($input, $gross);

        return [
            'standard_v1' => [
                'receipt' => [
                    'receipt_type' => $receiptType,
                    'amounts_per_vat_rate' => $vatRates,
                    'amounts_per_payment_type' => $payments,
                ],
            ],
        ];
    }

    public static function mapReceiptType(string $type): string
    {
        $type = strtolower(trim($type));
        return match ($type) {
            'storno', 'cancellation', 'cancel' => 'CANCELLATION',
            'receipt', 'sale', '' => 'RECEIPT',
            default => strtoupper($type) === 'CANCELLATION' ? 'CANCELLATION' : 'RECEIPT',
        };
    }

    /**
     * @param array<string, mixed> $input
     * @return list<array{vat_rate: string, amount: float}>
     */
    private static function normalizeVatAmounts(array $input, float $gross): array
    {
        $raw = $input['vat_amounts'] ?? null;
        $out = [];
        if (is_array($raw)) {
            foreach ($raw as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $code = trim((string) ($row['vat_rate'] ?? $row['vat_rate_code'] ?? ''));
                if ($code === '' && isset($row['rate'])) {
                    $code = TseSignService::vatRateCode((int) $row['rate']);
                }
                if ($code === '') {
                    $code = 'NORMAL';
                }
                $amount = round((float) ($row['amount'] ?? 0), 2);
                $out[] = ['vat_rate' => $code, 'amount' => $amount];
            }
        }
        if ($out === []) {
            $out[] = ['vat_rate' => 'NORMAL', 'amount' => abs($gross)];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $input
     * @return list<array{payment_type: string, amount: string, currency_code: string}>
     */
    private static function normalizePaymentTypes(array $input, float $gross): array
    {
        $raw = $input['payment_types'] ?? $input['payments'] ?? null;
        $out = [];
        if (is_array($raw)) {
            foreach ($raw as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $method = strtolower(trim((string) ($row['payment_method'] ?? $row['payment_type'] ?? 'cash')));
                $amount = abs(round((float) ($row['amount'] ?? 0), 2));
                if ($amount <= 0) {
                    continue;
                }
                $out[] = [
                    'payment_type' => self::mapPaymentType($method),
                    'amount' => TseSignService::money($amount),
                    'currency_code' => strtoupper(trim((string) ($row['currency'] ?? 'EUR'))) ?: 'EUR',
                ];
            }
        }
        if ($out === []) {
            $out[] = [
                'payment_type' => 'CASH',
                'amount' => TseSignService::money(abs($gross)),
                'currency_code' => 'EUR',
            ];
        }

        return $out;
    }

    private static function mapPaymentType(string $method): string
    {
        return match ($method) {
            'cash', 'CASH' => 'CASH',
            'ec', 'card', 'voucher', 'non_cash', 'NON_CASH' => 'NON_CASH',
            default => str_starts_with(strtoupper($method), 'CASH') ? 'CASH' : 'NON_CASH',
        };
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private function requireConfig(array $cfg): void
    {
        foreach (['api_key', 'api_secret', 'tss_id', 'client_id'] as $key) {
            if (trim((string) ($cfg[$key] ?? '')) === '') {
                throw new RuntimeException(
                    'fiskaly-Zugangsdaten unvollständig (' . $key . '). Einstellungen → TSE bzw. Kassen-Zuordnung prüfen.'
                );
            }
        }
    }

    private function requireTxId(string $txId): string
    {
        $txId = strtolower(trim($txId));
        if ($txId === '' || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $txId)) {
            throw new RuntimeException('fiskaly: ungültige Transaktions-ID.');
        }

        return $txId;
    }

    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function mapResult(string $txId, array $raw, array $input): array
    {
        $sig = is_array($raw['signature'] ?? null) ? $raw['signature'] : [];
        $counter = null;
        $qr = trim((string) ($raw['qr_code_data'] ?? ''));
        if ($qr !== '') {
            $parts = explode(';', $qr);
            if (isset($parts[4]) && $parts[4] !== '') {
                $counter = (string) $parts[4];
            }
        }
        if ($counter === null && isset($sig['counter'])) {
            $counter = (string) $sig['counter'];
        }
        $clientSerial = trim((string) ($input['client_serial_number'] ?? ''));
        if ($clientSerial === '') {
            $clientSerial = (string) ($raw['client_serial_number'] ?? '');
        }

        return [
            'tse_tx_id' => (string) ($raw['_id'] ?? $txId),
            'state' => (string) ($raw['state'] ?? ''),
            'tx_number' => isset($raw['number']) ? (int) $raw['number'] : null,
            'revision' => isset($raw['revision']) ? (int) $raw['revision'] : null,
            'signature_value' => isset($sig['value']) ? (string) $sig['value'] : null,
            'signature_algorithm' => (string) ($sig['algorithm'] ?? $raw['signature_algorithm'] ?? 'ecdsa-plain-SHA256'),
            'signature_counter' => $counter,
            'qr_code_data' => $qr !== '' ? $qr : null,
            'time_start' => self::unix($raw['time_start'] ?? null),
            'time_end' => self::unix($raw['time_end'] ?? null),
            'tss_serial_number' => (string) ($raw['tss_serial_number'] ?? ''),
            'client_serial_number' => $clientSerial,
            'raw' => $raw,
        ];
    }

    private static function unix(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        $ts = strtotime((string) $value);

        return $ts !== false ? $ts : null;
    }
}
