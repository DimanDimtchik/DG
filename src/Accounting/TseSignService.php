<?php
declare(strict_types=1);

/**
 * Signiert Barzahlungen auf Ausgangsrechnungen (KassenSichV).
 *
 * Ohne eingeschaltete TSE bleibt das bisherige Kassenverhalten.
 * Ist die TSE an, darf eine Barzahlung nur mit Signatur gespeichert werden.
 */
final class TseSignService
{
    /**
     * @param array<string, mixed> $voucher
     */
    public static function shouldSign(array $voucher, string $method): bool
    {
        if (!TseSettings::isEnabled()) {
            return false;
        }
        if (VoucherPaymentRepository::sanitizeMethod($method) !== VoucherPaymentRepository::METHOD_CASH) {
            return false;
        }
        $type = VoucherRepository::normalizeVoucherType((string) ($voucher['voucher_type'] ?? ''));
        if ($type !== 'income') {
            return false;
        }

        $kind = (string) ($voucher['document_kind'] ?? '');
        if ($kind === '') {
            return true;
        }

        return VoucherDocumentKind::isBookable($kind, $type);
    }

    /**
     * Nach dem Anlegen einer Barzahlung: TSE-Transaktion ACTIVE → FINISHED.
     *
     * @throws RuntimeException|InvalidArgumentException
     */
    public static function signCashPayment(int $voucherId, int $paymentId, ?TseSignDeClient $client = null): void
    {
        if ($voucherId < 1 || $paymentId < 1) {
            return;
        }
        $voucher = VoucherRepository::findById($voucherId);
        if ($voucher === null) {
            return;
        }
        $payments = VoucherPaymentRepository::listForVoucher($voucherId);
        $payment = null;
        foreach ($payments as $row) {
            if ((int) ($row['id'] ?? 0) === $paymentId) {
                $payment = $row;
                break;
            }
        }
        if ($payment === null) {
            return;
        }
        if (!self::shouldSign($voucher, (string) ($payment['payment_method'] ?? ''))) {
            return;
        }
        // K9d: kein zweiter Signaturweg, wenn schon Kassenbon signiert
        if (class_exists('CashDualSignatureGuard')) {
            CashDualSignatureGuard::assertVoucherCashPaymentMaySign($voucherId);
        }
        if (!TseSettings::isReady()) {
            throw new InvalidArgumentException(
                'TSE ist eingeschaltet, aber Zugangsdaten fehlen. Bitte unter Einstellungen → TSE prüfen.'
            );
        }

        $amount = round((float) ($payment['amount'] ?? 0), 2);
        $schema = self::buildReceiptSchema($voucher, $amount);
        $client ??= new TseSignDeClient(self::clientConfigForPayment($payment));
        $txId = self::uuidV4();

        TseTransactionRepository::upsert([
            'voucher_id' => $voucherId,
            'voucher_payment_id' => $paymentId,
            'tse_tx_id' => $txId,
            'tss_id' => $client->tssId(),
            'tse_client_id' => $client->clientId(),
            'state' => TseTransactionRepository::STATE_ACTIVE,
            'receipt_type' => 'RECEIPT',
            'amount_gross' => $amount,
        ]);

        try {
            $started = $client->upsertTransaction($txId, 1, [
                'state' => 'ACTIVE',
                'client_id' => $client->clientId(),
            ]);
            $finished = $client->upsertTransaction($txId, 2, [
                'state' => 'FINISHED',
                'client_id' => $client->clientId(),
                'schema' => $schema,
            ]);
        } catch (Throwable $e) {
            TseTransactionRepository::upsert([
                'voucher_id' => $voucherId,
                'voucher_payment_id' => $paymentId,
                'tse_tx_id' => $txId,
                'tss_id' => $client->tssId(),
                'tse_client_id' => $client->clientId(),
                'state' => TseTransactionRepository::STATE_FAILED,
                'receipt_type' => 'RECEIPT',
                'amount_gross' => $amount,
                'error_message' => mb_substr($e->getMessage(), 0, 500),
            ]);
            throw $e;
        }

        $sig = is_array($finished['signature'] ?? null) ? $finished['signature'] : [];
        TseTransactionRepository::upsert([
            'voucher_id' => $voucherId,
            'voucher_payment_id' => $paymentId,
            'tse_tx_id' => (string) ($finished['_id'] ?? $txId),
            'tss_id' => $client->tssId(),
            'tse_client_id' => $client->clientId(),
            'state' => TseTransactionRepository::STATE_FINISHED,
            'tx_number' => isset($finished['number']) ? (int) $finished['number'] : null,
            'revision' => isset($finished['revision']) ? (int) $finished['revision'] : 2,
            'signature_value' => $sig['value'] ?? null,
            'signature_algorithm' => $sig['algorithm'] ?? ($finished['signature_algorithm'] ?? 'ecdsa-plain-SHA256'),
            'signature_counter' => self::signatureCounterFrom($finished),
            'qr_code_data' => $finished['qr_code_data'] ?? null,
            'time_start' => self::unix($finished['time_start'] ?? $started['time_start'] ?? null),
            'time_end' => self::unix($finished['time_end'] ?? null),
            'client_serial_number' => $finished['client_serial_number'] ?? null,
            'tss_serial_number' => $finished['tss_serial_number'] ?? null,
            'receipt_type' => 'RECEIPT',
            'amount_gross' => $amount,
            'error_message' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $voucher
     * @return array{standard_v1: array{receipt: array<string, mixed>}}
     */
    public static function buildReceiptSchema(array $voucher, float $paymentAmount): array
    {
        $paymentAmount = round(max(0.0, $paymentAmount), 2);
        $vatLines = self::allocateVatAmounts($voucher, $paymentAmount);
        $vatRates = [];
        foreach ($vatLines as $line) {
            $vatRates[] = [
                'vat_rate' => $line['vat_rate'],
                'amount' => self::money($line['amount']),
            ];
        }

        return [
            'standard_v1' => [
                'receipt' => [
                    'receipt_type' => 'RECEIPT',
                    'amounts_per_vat_rate' => $vatRates,
                    'amounts_per_payment_type' => [
                        [
                            'payment_type' => 'CASH',
                            'amount' => self::money($paymentAmount),
                            'currency_code' => 'EUR',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Bruttobetrag der Zahlung anteilig auf die Steuersätze des Belegs.
     *
     * @param array<string, mixed> $voucher
     * @return list<array{vat_rate: string, amount: float}>
     */
    public static function allocateVatAmounts(array $voucher, float $paymentAmount): array
    {
        $paymentAmount = round(max(0.0, $paymentAmount), 2);
        $items = is_array($voucher['items'] ?? null) ? $voucher['items'] : [];
        $voucherId = (int) ($voucher['id'] ?? 0);
        if ($items === [] && $voucherId > 0) {
            $items = VoucherRepository::itemsForVoucher($voucherId);
        }
        $breakdown = VoucherDocumentPrintService::totalsBreakdown($items, $voucher);
        $byRate = is_array($breakdown['by_rate'] ?? null) ? $breakdown['by_rate'] : [];

        $buckets = [];
        $totalGross = 0.0;
        foreach ($byRate as $row) {
            if (!is_array($row)) {
                continue;
            }
            $gross = round((float) ($row['gross'] ?? 0), 2);
            if ($gross <= 0.0) {
                continue;
            }
            $rate = (int) ($row['rate'] ?? 19);
            $code = self::vatRateCode($rate);
            if (!isset($buckets[$code])) {
                $buckets[$code] = 0.0;
            }
            $buckets[$code] = round($buckets[$code] + $gross, 2);
            $totalGross = round($totalGross + $gross, 2);
        }

        if ($buckets === [] || $totalGross <= 0.0) {
            return [['vat_rate' => self::vatRateCode((int) ($voucher['tax_rate'] ?? 19)), 'amount' => $paymentAmount]];
        }

        $out = [];
        $assigned = 0.0;
        $codes = array_keys($buckets);
        $last = array_key_last($codes);
        foreach ($codes as $i => $code) {
            if ($i === $last) {
                $share = round($paymentAmount - $assigned, 2);
            } else {
                $share = round($paymentAmount * ($buckets[$code] / $totalGross), 2);
                $assigned = round($assigned + $share, 2);
            }
            if ($share < 0) {
                $share = 0.0;
            }
            $out[] = ['vat_rate' => $code, 'amount' => $share];
        }

        return $out;
    }

    public static function vatRateCode(int $rate): string
    {
        return match (VoucherTaxKeys::sanitizeTaxRate($rate)) {
            0 => 'NULL',
            7 => 'REDUCED_1',
            default => 'NORMAL',
        };
    }

    public static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    public static function money(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }

    /**
     * Instanz-Secrets + optional TSS/Client aus Kassen-Zuordnung (K9b).
     *
     * @param array<string, mixed> $payment
     * @return array<string, mixed>
     */
    public static function clientConfigForPayment(array $payment): array
    {
        $cfg = TseSettings::effective();
        $registerId = (int) ($payment['cash_register_id'] ?? 0);
        if ($registerId <= 0 || !class_exists('CashTseAssignmentRepository')) {
            return $cfg;
        }
        if (!CashTseAssignmentRepository::tableExists()) {
            return $cfg;
        }
        $assignment = CashTseAssignmentRepository::findByRegisterId($registerId);
        if ($assignment === null || empty($assignment['is_active'])) {
            return $cfg;
        }
        $tss = trim((string) ($assignment['tss_id'] ?? ''));
        $clientId = trim((string) ($assignment['client_id'] ?? ''));
        if ($tss !== '') {
            $cfg['tss_id'] = $tss;
        }
        if ($clientId !== '') {
            $cfg['client_id'] = $clientId;
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

    /**
     * @param array<string, mixed> $tx
     */
    public static function printBlock(array $tx): ?array
    {
        if ((string) ($tx['state'] ?? '') !== TseTransactionRepository::STATE_FINISHED) {
            return null;
        }
        $qr = trim((string) ($tx['qr_code_data'] ?? ''));
        if ($qr === '') {
            return null;
        }
        $start = self::formatUnix((int) ($tx['time_start'] ?? 0));
        $end = self::formatUnix((int) ($tx['time_end'] ?? 0));
        $timeLabel = $start;
        if ($end !== '' && $end !== $start) {
            $timeLabel = $start . ' – ' . $end;
        }

        return [
            'qr_svg' => QrCodeSvg::render($qr),
            'qr_payload' => $qr,
            'client_serial' => (string) ($tx['client_serial_number'] ?? ''),
            'tss_serial' => (string) ($tx['tss_serial_number'] ?? ''),
            'signature_counter' => (string) ($tx['signature_counter'] ?? ''),
            'tx_number' => (string) ($tx['tx_number'] ?? ''),
            'algorithm' => (string) ($tx['signature_algorithm'] ?? 'ecdsa-plain-SHA256'),
            'time_label' => $timeLabel,
        ];
    }

    /**
     * @param array<string, mixed> $finished
     */
    private static function signatureCounterFrom(array $finished): ?string
    {
        $qr = (string) ($finished['qr_code_data'] ?? '');
        if ($qr !== '') {
            $parts = explode(';', $qr);
            if (isset($parts[4]) && $parts[4] !== '') {
                return $parts[4];
            }
        }
        $sig = is_array($finished['signature'] ?? null) ? $finished['signature'] : [];
        if (isset($sig['counter'])) {
            return (string) $sig['counter'];
        }

        return null;
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

    private static function formatUnix(int $ts): string
    {
        if ($ts < 1) {
            return '';
        }

        return (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i:s');
    }
}
