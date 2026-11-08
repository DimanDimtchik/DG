<?php
declare(strict_types=1);

/**
 * Bonfluss — Draft (K7a), Positionen (K7b), Zahlungen → paying (K7c).
 * Storno ohne TSE-Finish (K13a Datenpfad); TSE-Storno → K13b.
 */
final class CashReceiptService
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PAYING = 'paying';
    public const STATUS_FINALIZED = 'finalized';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_SALE = 'sale';
    public const TYPE_STORNO = 'storno';

    /** @var list<int> */
    private const TAX_RATES = [19, 7, 0];

    /** @var list<string> */
    private const PAYMENT_METHODS = ['cash', 'ec', 'card', 'voucher'];

    /**
     * Neuen Verkaufsbon im Status draft anlegen (Nummer + Sitzung + Bediener).
     * K11b: ohne offene Kassensitzung kein neuer Bon.
     * K12b: nach Tagesabschluss keine neuen Bons auf dem Geschäftstag.
     *
     * @return int receipt id
     */
    public static function startDraft(int $registerId, int $operatorId): int
    {
        if ($registerId <= 0) {
            throw new RuntimeException('Kasse fehlt.');
        }
        if (!CashReceiptRepository::tableExists()) {
            throw new RuntimeException('Bon-Tabellen fehlen — bitte Migrationen ausführen.');
        }

        $register = CashRegisterRepository::findById($registerId);
        if ($register === null) {
            throw new RuntimeException('Kasse nicht gefunden.');
        }
        if (empty($register['is_active'])) {
            throw new RuntimeException('Kasse ist inaktiv.');
        }

        $locationId = (int) ($register['location_id'] ?? 0);
        if ($locationId <= 0) {
            throw new RuntimeException('Filiale der Kasse fehlt.');
        }

        $session = CashSessionRepository::findOpenByRegister($registerId);
        if ($session === null) {
            throw new RuntimeException('Keine offene Kassensitzung — bitte Kasse zuerst öffnen.');
        }
        $sessionId = (int) ($session['id'] ?? 0);
        if ($sessionId <= 0) {
            throw new RuntimeException('Keine offene Kassensitzung — bitte Kasse zuerst öffnen.');
        }

        $businessDate = trim((string) ($session['business_date'] ?? ''));
        if ($businessDate !== '' && CashRegisterDayClosingService::isClosed($registerId, $businessDate)) {
            throw new RuntimeException(
                'Geschäftstag ist abgeschlossen — keine neuen Bons für diesen Tag.'
            );
        }

        $tseAssignmentId = null;
        $tse = CashTseAssignmentRepository::findByRegisterId($registerId);
        if ($tse !== null && !empty($tse['is_active']) && (int) ($tse['id'] ?? 0) > 0) {
            $tseAssignmentId = (int) $tse['id'];
        }

        $number = CashRegisterRepository::allocateNextReceiptNumber($registerId);

        return CashReceiptRepository::insertReceipt([
            'location_id' => $locationId,
            'register_id' => $registerId,
            'session_id' => $sessionId,
            'tse_assignment_id' => $tseAssignmentId,
            'receipt_number' => $number['number'],
            'receipt_number_display' => $number['display'],
            'status' => 'draft',
            'receipt_type' => 'sale',
            'operator_id' => $operatorId > 0 ? $operatorId : null,
            'total_gross' => 0,
            'total_net' => 0,
            'started_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Position hinzufügen — nur Draft (K7b).
     *
     * @param array<string, mixed> $input
     * @return int line id
     */
    public static function addLine(int $receiptId, array $input): int
    {
        self::requireDraft($receiptId);
        $normalized = self::normalizeLineInput($input);
        $lineId = CashReceiptRepository::insertLine([
            'receipt_id' => $receiptId,
            'line_no' => CashReceiptRepository::nextLineNo($receiptId),
            'article_id' => (int) ($input['article_id'] ?? 0),
            'article_number' => trim((string) ($input['article_number'] ?? '')),
            'title' => $normalized['title'],
            'unit' => $normalized['unit'],
            'quantity' => $normalized['quantity'],
            'unit_price_gross' => $normalized['unit_price_gross'],
            'gross_amount' => $normalized['gross_amount'],
            'tax_rate' => $normalized['tax_rate'],
        ]);
        self::recalculateTotals($receiptId);

        return $lineId;
    }

    /**
     * Position ändern — nur Draft (K7b).
     *
     * @param array<string, mixed> $input
     */
    public static function updateLine(int $receiptId, int $lineId, array $input): void
    {
        self::requireDraft($receiptId);
        $line = self::requireLineOnReceipt($receiptId, $lineId);
        $normalized = self::normalizeLineInput($input, $line);
        CashReceiptRepository::updateLine($lineId, [
            'article_number' => array_key_exists('article_number', $input)
                ? trim((string) $input['article_number'])
                : (string) ($line['article_number'] ?? ''),
            'title' => $normalized['title'],
            'unit' => $normalized['unit'],
            'quantity' => $normalized['quantity'],
            'unit_price_gross' => $normalized['unit_price_gross'],
            'gross_amount' => $normalized['gross_amount'],
            'tax_rate' => $normalized['tax_rate'],
        ]);
        self::recalculateTotals($receiptId);
    }

    /** Position entfernen — nur Draft (K7b). */
    public static function removeLine(int $receiptId, int $lineId): void
    {
        self::requireDraft($receiptId);
        self::requireLineOnReceipt($receiptId, $lineId);
        CashReceiptRepository::deleteLine($lineId);
        self::recalculateTotals($receiptId);
    }

    /**
     * Zahlung erfassen — Draft oder zahlend; erste Zahlung setzt Status paying (K7c).
     *
     * @param array<string, mixed> $input
     * @return int payment id
     */
    public static function addPayment(int $receiptId, array $input): int
    {
        $receipt = self::requirePayable($receiptId);
        if (!CashPaymentRepository::tableExists()) {
            throw new RuntimeException('Zahlungs-Tabelle fehlt — bitte Migrationen ausführen.');
        }
        if (CashReceiptRepository::listLines($receiptId) === []) {
            throw new RuntimeException('Zuerst mindestens eine Position erfassen.');
        }
        $gross = round((float) ($receipt['total_gross'] ?? 0), 2);
        if ($gross <= 0) {
            throw new RuntimeException('Bon-Brutto ist 0 — keine Zahlung möglich.');
        }

        $method = strtolower(trim((string) ($input['payment_method'] ?? '')));
        if (!in_array($method, self::PAYMENT_METHODS, true)) {
            throw new RuntimeException('Ungültige Zahlungsart.');
        }
        $amount = self::parseDecimal($input['amount'] ?? 0, 0.0);
        if ($amount <= 0) {
            throw new RuntimeException('Zahlungsbetrag muss größer als 0 sein.');
        }

        $paid = self::paymentTotal($receiptId);
        $remaining = round($gross - $paid, 2);
        if ($amount - $remaining > 0.009) {
            throw new RuntimeException(
                'Zahlungsbetrag übersteigt den offenen Rest ('
                . number_format($remaining, 2, ',', '.') . ' €).'
            );
        }

        $giftVoucherId = isset($input['gift_voucher_id']) && (int) $input['gift_voucher_id'] > 0
            ? (int) $input['gift_voucher_id']
            : null;
        $externalRef = trim((string) ($input['external_ref'] ?? ''));
        if ($method === 'voucher') {
            $resolved = self::resolveGiftVoucherForPayment($input, $amount);
            $giftVoucherId = (int) $resolved['id'];
            $amount = (float) $resolved['amount'];
            if ($externalRef === '') {
                $externalRef = (string) $resolved['code'];
            }
            if ($amount - $remaining > 0.009) {
                throw new RuntimeException(
                    'Gutschein-Betrag übersteigt den offenen Rest ('
                    . number_format($remaining, 2, ',', '.') . ' €).'
                );
            }
        } elseif ($giftVoucherId !== null) {
            // gift_voucher_id nur bei Zahlart Gutschein
            $giftVoucherId = null;
        }

        $paymentId = CashPaymentRepository::insert([
            'receipt_id' => $receiptId,
            'line_no' => CashPaymentRepository::nextLineNo($receiptId),
            'payment_method' => $method,
            'amount' => round($amount, 2),
            'currency' => 'EUR',
            'gift_voucher_id' => $giftVoucherId,
            'external_ref' => $externalRef,
            'notes' => trim((string) ($input['notes'] ?? '')),
        ]);

        if ($method === 'voucher' && $giftVoucherId !== null && $giftVoucherId > 0
            && class_exists('GiftVoucherRepository')
        ) {
            GiftVoucherRepository::adjustRemaining($giftVoucherId, -1 * round($amount, 2));
        }

        self::refreshPaymentState($receiptId);

        return $paymentId;
    }

    /** Zahlung entfernen — nur Draft/zahlend (K7c). Ohne Zahlungen zurück auf Draft. */
    public static function removePayment(int $receiptId, int $paymentId): void
    {
        self::requirePayable($receiptId);
        if ($paymentId <= 0) {
            throw new RuntimeException('Zahlung fehlt.');
        }
        $row = CashPaymentRepository::findById($paymentId);
        if ($row === null || (int) ($row['receipt_id'] ?? 0) !== $receiptId) {
            throw new RuntimeException('Zahlung gehört nicht zu diesem Bon.');
        }
        if (($row['payment_method'] ?? '') === 'voucher'
            && (int) ($row['gift_voucher_id'] ?? 0) > 0
            && class_exists('GiftVoucherRepository')
        ) {
            GiftVoucherRepository::adjustRemaining(
                (int) $row['gift_voucher_id'],
                round((float) ($row['amount'] ?? 0), 2)
            );
        }
        CashPaymentRepository::delete($paymentId);
        self::refreshPaymentState($receiptId);
    }

    public static function paymentTotal(int $receiptId): float
    {
        $sum = 0.0;
        foreach (CashPaymentRepository::listByReceipt($receiptId) as $row) {
            $sum += round((float) ($row['amount'] ?? 0), 2);
        }

        return round($sum, 2);
    }

    /** Offener Restbetrag (K8d) — Brutto minus Summe Zahlungen. */
    public static function paymentOpenAmount(int $receiptId): float
    {
        $receipt = self::requireReceipt($receiptId);
        $gross = round((float) ($receipt['total_gross'] ?? 0), 2);

        return round($gross - self::paymentTotal($receiptId), 2);
    }

    /** Summe Zahlungen deckt Bon-Brutto (K8d). */
    public static function isFullyPaid(int $receiptId): bool
    {
        return abs(self::paymentOpenAmount($receiptId)) <= 0.009;
    }

    /**
     * Voll bezahlten Bon ohne TSE festschreiben (Test-/Zwischenpfad).
     * K8d: offener Rest blockiert Finish.
     */
    public static function finalizeWithoutTse(int $receiptId): void
    {
        self::assertReadyToFinalize($receiptId);
        CashReceiptRepository::updateReceipt($receiptId, [
            'status' => self::STATUS_FINALIZED,
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
        // K15b–d: Bar/EC/Karte/Gutschein → FiBu (Storno: K15e)
        CashRegisterFiBuPostingService::postForFinalizedReceipt($receiptId);
    }

    /**
     * Bon mit TSE abschließen und festschreiben (K7d/K7f).
     * Default-Provider: Mock bzw. Assignment über TseProviderFactory (inkl. Fiskaly K3b).
     *
     * @return array<string, mixed> Provider-Finish-Ergebnis
     */
    public static function finalizeWithTse(int $receiptId, ?TseProviderInterface $provider = null): array
    {
        $receipt = self::assertReadyToFinalize($receiptId);
        if (trim((string) ($receipt['tse_tx_id'] ?? '')) !== ''
            && ($receipt['status'] ?? '') === self::STATUS_FINALIZED
        ) {
            throw new RuntimeException('Bon ist bereits mit TSE festgeschrieben.');
        }

        // K9d: kein zweiter Signaturweg für denselben Beleg/Auftrag
        CashDualSignatureGuard::assertCashReceiptMaySign($receipt);

        $registerId = (int) ($receipt['register_id'] ?? 0);
        $register = $registerId > 0 ? CashRegisterRepository::findById($registerId) : null;
        $clientSerial = is_array($register)
            ? trim((string) ($register['code'] ?? ''))
            : '';
        if ($clientSerial === '') {
            $clientSerial = MockTseProvider::CLIENT_SERIAL;
        }

        $assignment = null;
        if ($provider === null) {
            $assignmentId = (int) ($receipt['tse_assignment_id'] ?? 0);
            if ($assignmentId > 0 && class_exists('CashTseAssignmentRepository')) {
                $assignment = CashTseAssignmentRepository::findById($assignmentId);
            } elseif ($registerId > 0 && class_exists('CashTseAssignmentRepository')) {
                $assignment = CashTseAssignmentRepository::findByRegisterId($registerId);
            }
            $provider = TseProviderFactory::fromAssignment(
                is_array($assignment) ? $assignment : null
            );
        }

        $input = self::buildTseInput($receiptId, $receipt, $clientSerial);

        $txId = class_exists('TseSignService')
            ? TseSignService::uuidV4()
            : self::uuidV4Fallback();

        try {
            $start = $provider->startTransaction($txId, $input);
            $rev = (int) ($start['revision'] ?? 1);
            $upd = $provider->updateTransaction($txId, $rev, $input);
            $rev = (int) ($upd['revision'] ?? $rev);
            $fin = $provider->finishTransaction($txId, $rev, $input);
        } catch (Throwable $e) {
            self::handleTseFailure($receiptId, $registerId, $provider, $e);
            throw $e;
        }

        if (($fin['state'] ?? '') !== 'FINISHED') {
            $err = new RuntimeException('TSE-Abschluss fehlgeschlagen (kein FINISHED).');
            self::handleTseFailure($receiptId, $registerId, $provider, $err);
            throw $err;
        }
        $qr = trim((string) ($fin['qr_code_data'] ?? ''));
        if ($qr === '') {
            $err = new RuntimeException('TSE-Abschluss ohne QR-Daten.');
            self::handleTseFailure($receiptId, $registerId, $provider, $err);
            throw $err;
        }

        $update = [
            'status' => self::STATUS_FINALIZED,
            'finished_at' => date('Y-m-d H:i:s'),
            'tse_tx_id' => (string) ($fin['tse_tx_id'] ?? $txId),
            'transaction_number' => isset($fin['tx_number']) ? (int) $fin['tx_number'] : null,
            'tse_serial' => (string) ($fin['tss_serial_number'] ?? ''),
            'signature_counter' => (string) ($fin['signature_counter'] ?? ''),
            'signature_value' => $fin['signature_value'] ?? null,
            'signature_algorithm' => (string) ($fin['signature_algorithm'] ?? ''),
            'qr_code_data' => $qr,
            'time_start' => isset($fin['time_start']) ? (int) $fin['time_start'] : null,
            'time_end' => isset($fin['time_end']) ? (int) $fin['time_end'] : null,
            'tse_outage' => 0,
        ];
        if (CashReceiptRepository::hasDeferredPendingColumn()) {
            $update['tse_deferred_pending'] = 0;
        }
        CashReceiptRepository::updateReceipt($receiptId, $update);
        CashRegisterFiBuPostingService::postForFinalizedReceipt($receiptId);

        return $fin;
    }

    /**
     * TSE-Input aus Bon-Positionen und Zahlungen (K7d).
     *
     * @param array<string, mixed> $receipt
     * @return array<string, mixed>
     */
    public static function buildTseInput(int $receiptId, array $receipt, string $clientSerial): array
    {
        $gross = round((float) ($receipt['total_gross'] ?? 0), 2);
        $lines = CashReceiptRepository::listLines($receiptId);
        $vatBuckets = [];
        foreach ($lines as $line) {
            $amount = abs(round((float) ($line['gross_amount'] ?? 0), 2));
            if ($amount <= 0) {
                continue;
            }
            $rate = (int) ($line['tax_rate'] ?? 19);
            $code = class_exists('TseSignService')
                ? TseSignService::vatRateCode($rate)
                : 'NORMAL';
            if (!isset($vatBuckets[$code])) {
                $vatBuckets[$code] = 0.0;
            }
            $vatBuckets[$code] = round($vatBuckets[$code] + $amount, 2);
        }
        $vatAmounts = [];
        foreach ($vatBuckets as $code => $amount) {
            $vatAmounts[] = ['vat_rate_code' => $code, 'vat_rate' => $code, 'amount' => $amount];
        }
        if ($vatAmounts === []) {
            $vatAmounts[] = [
                'vat_rate_code' => 'NORMAL',
                'vat_rate' => 'NORMAL',
                'amount' => abs($gross),
            ];
        }

        $paymentTypes = [];
        if (CashPaymentRepository::tableExists()) {
            foreach (CashPaymentRepository::listByReceipt($receiptId) as $pay) {
                $paymentTypes[] = [
                    'payment_method' => (string) ($pay['payment_method'] ?? 'cash'),
                    'amount' => abs(round((float) ($pay['amount'] ?? 0), 2)),
                    'currency' => (string) ($pay['currency'] ?? 'EUR'),
                ];
            }
        }

        return [
            'receipt_type' => (string) ($receipt['receipt_type'] ?? self::TYPE_SALE),
            'amount_gross' => $gross,
            'client_serial_number' => $clientSerial,
            'vat_amounts' => $vatAmounts,
            'payment_types' => $paymentTypes,
        ];
    }

    /**
     * Bei TSE-Ausfall: Flag + ggf. Nachsignatur-Queue (K14a/d).
     */
    private static function handleTseFailure(
        int $receiptId,
        int $registerId,
        TseProviderInterface $provider,
        Throwable $e
    ): void {
        if ($registerId <= 0 || !class_exists('CashTseOutageService')) {
            return;
        }
        try {
            if (CashTseAssignmentRepository::findByRegisterId($registerId) !== null) {
                CashTseOutageService::recordOutage([
                    'register_id' => $registerId,
                    'receipt_id' => $receiptId,
                    'message' => 'TSE-Fehler beim Festschreiben: ' . $e->getMessage(),
                    'details' => [
                        'phase' => 'finalize',
                        'provider' => $provider->getName(),
                    ],
                ]);
            }
        } catch (Throwable) {
            // Ausfall-Protokoll darf Finish-Fehler nicht überdecken
        }
        if ($provider->supportsDeferredSignature() && class_exists('CashTseDeferredSignatureService')) {
            try {
                CashTseDeferredSignatureService::enqueue($receiptId, $e->getMessage());
            } catch (Throwable) {
                // Queue optional
            }
        }
    }

    /** @return array<string, mixed> */
    private static function assertReadyToFinalize(int $receiptId): array
    {
        $receipt = self::requirePayable($receiptId);
        // Nachsignatur-Queue: Bon darf trotz deferred_pending erneut finalisiert werden
        if (
            ($receipt['status'] ?? '') === self::STATUS_PAYING
            && CashReceiptRepository::hasDeferredPendingColumn()
            && !empty($receipt['tse_deferred_pending'])
        ) {
            // ok — weiter
        } elseif (($receipt['status'] ?? '') === self::STATUS_FINALIZED) {
            throw new RuntimeException('Bon ist bereits festgeschrieben.');
        }

        if (CashReceiptRepository::listLines($receiptId) === []) {
            throw new RuntimeException('Bon ohne Positionen kann nicht festgeschrieben werden.');
        }
        $gross = round((float) ($receipt['total_gross'] ?? 0), 2);
        $type = (string) ($receipt['receipt_type'] ?? self::TYPE_SALE);
        if ($type === self::TYPE_STORNO) {
            if ($gross >= -0.009) {
                throw new RuntimeException('Storno-Brutto muss negativ sein.');
            }
        } elseif ($gross <= 0) {
            throw new RuntimeException('Bon-Brutto ist 0.');
        }
        $open = self::paymentOpenAmount($receiptId);
        if ($open > 0.009) {
            throw new RuntimeException(
                'Bon ist nicht vollständig bezahlt — noch offen: '
                . number_format($open, 2, ',', '.') . ' €.'
            );
        }
        if ($open < -0.009) {
            throw new RuntimeException('Zahlungen übersteigen den Bon-Brutto.');
        }

        return $receipt;
    }

    private static function uuidV4Fallback(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Storno-Bon zum festgeschriebenen Verkauf (K13a/b).
     * Original unverändert; neuer Bon receipt_type=storno + Referenz.
     * Mit Provider/Assignment → TSE CANCELLATION (K13b); sonst ohne TSE (Selftests).
     *
     * @return int storno receipt id
     */
    public static function stornoFromFinalized(
        int $originalReceiptId,
        int $operatorId,
        string $notes = '',
        ?TseProviderInterface $provider = null,
        bool $signWithTse = false
    ): int {
        $original = self::requireReceipt($originalReceiptId);
        if (($original['status'] ?? '') !== self::STATUS_FINALIZED) {
            throw new RuntimeException('Nur festgeschriebene Bons können storniert werden.');
        }
        if (($original['receipt_type'] ?? self::TYPE_SALE) !== self::TYPE_SALE) {
            throw new RuntimeException('Nur Verkaufsbons können storniert werden.');
        }
        if (CashReceiptRepository::findStornoOf($originalReceiptId) !== null) {
            throw new RuntimeException('Für diesen Bon existiert bereits ein Storno.');
        }

        $registerId = (int) ($original['register_id'] ?? 0);
        if ($registerId <= 0) {
            throw new RuntimeException('Kasse des Originalbons fehlt.');
        }

        $session = CashSessionRepository::findOpenByRegister($registerId);
        if ($session === null) {
            throw new RuntimeException('Keine offene Kassensitzung — bitte Kasse zuerst öffnen.');
        }
        $sessionId = (int) ($session['id'] ?? 0);
        $businessDate = trim((string) ($session['business_date'] ?? ''));
        if ($businessDate !== '' && CashRegisterDayClosingService::isClosed($registerId, $businessDate)) {
            throw new RuntimeException('Geschäftstag ist abgeschlossen — kein Storno an diesem Tag.');
        }

        $locationId = (int) ($original['location_id'] ?? 0);
        if ($locationId <= 0) {
            throw new RuntimeException('Filiale des Originalbons fehlt.');
        }

        $tseAssignmentId = null;
        $tse = CashTseAssignmentRepository::findByRegisterId($registerId);
        if ($tse !== null && !empty($tse['is_active']) && (int) ($tse['id'] ?? 0) > 0) {
            $tseAssignmentId = (int) $tse['id'];
        }

        $number = CashRegisterRepository::allocateNextReceiptNumber($registerId);
        $now = date('Y-m-d H:i:s');
        $origDisplay = trim((string) ($original['receipt_number_display'] ?? ''));
        if ($origDisplay === '') {
            $origDisplay = (string) (int) ($original['receipt_number'] ?? 0);
        }
        $note = trim($notes);
        if ($note === '') {
            $note = 'Storno zu Bon ' . $origDisplay;
        }

        $useTse = $signWithTse || $provider !== null || $tseAssignmentId !== null;

        $stornoId = CashReceiptRepository::insertReceipt([
            'location_id' => $locationId,
            'register_id' => $registerId,
            'session_id' => $sessionId,
            'tse_assignment_id' => $tseAssignmentId,
            'voucher_id' => isset($original['voucher_id']) && (int) $original['voucher_id'] > 0
                ? (int) $original['voucher_id']
                : null,
            'receipt_number' => $number['number'],
            'receipt_number_display' => $number['display'],
            'status' => self::STATUS_PAYING,
            'receipt_type' => self::TYPE_STORNO,
            'storno_of_receipt_id' => $originalReceiptId,
            'operator_id' => $operatorId > 0 ? $operatorId : null,
            'total_gross' => round(-1 * (float) ($original['total_gross'] ?? 0), 2),
            'total_net' => round(-1 * (float) ($original['total_net'] ?? 0), 2),
            'vat_summary_json' => self::negateVatSummaryJson($original['vat_summary_json'] ?? null),
            'payment_summary_json' => self::negatePaymentSummaryJson($original['payment_summary_json'] ?? null),
            'started_at' => $now,
            'finished_at' => null,
            'notes' => $note,
        ]);

        foreach (CashReceiptRepository::listLines($originalReceiptId) as $line) {
            $qty = round((float) ($line['quantity'] ?? 0), 3);
            $gross = round((float) ($line['gross_amount'] ?? 0), 2);
            CashReceiptRepository::insertLine([
                'receipt_id' => $stornoId,
                'line_no' => (int) ($line['line_no'] ?? 1),
                'article_id' => (int) ($line['article_id'] ?? 0),
                'article_number' => (string) ($line['article_number'] ?? ''),
                'title' => (string) ($line['title'] ?? ''),
                'unit' => (string) ($line['unit'] ?? 'Stück'),
                'quantity' => round(-1 * $qty, 3),
                'unit_price_gross' => round((float) ($line['unit_price_gross'] ?? 0), 2),
                'gross_amount' => round(-1 * $gross, 2),
                'tax_rate' => (int) ($line['tax_rate'] ?? 19),
            ]);
        }

        if (CashPaymentRepository::tableExists()) {
            foreach (CashPaymentRepository::listByReceipt($originalReceiptId) as $pay) {
                $payAmount = round((float) ($pay['amount'] ?? 0), 2);
                $giftId = isset($pay['gift_voucher_id']) && (int) $pay['gift_voucher_id'] > 0
                    ? (int) $pay['gift_voucher_id']
                    : null;
                CashPaymentRepository::insert([
                    'receipt_id' => $stornoId,
                    'line_no' => (int) ($pay['line_no'] ?? 1),
                    'payment_method' => (string) ($pay['payment_method'] ?? 'cash'),
                    'amount' => round(-1 * $payAmount, 2),
                    'currency' => (string) ($pay['currency'] ?? 'EUR'),
                    'gift_voucher_id' => $giftId,
                    'external_ref' => (string) ($pay['external_ref'] ?? ''),
                    'notes' => trim((string) ($pay['notes'] ?? '')),
                ]);
                // Gutschein-Restwert zurückbuchen (Einlösung am Original rückgängig)
                if (($pay['payment_method'] ?? '') === 'voucher'
                    && $giftId !== null
                    && $payAmount > 0
                    && class_exists('GiftVoucherRepository')
                ) {
                    GiftVoucherRepository::adjustRemaining($giftId, $payAmount);
                }
            }
        }

        if ($useTse) {
            // K13b: TSE finish mit receipt_type=storno → CANCELLATION
            self::finalizeWithTse($stornoId, $provider ?? new MockTseProvider());
        } else {
            self::finalizeWithoutTse($stornoId);
        }

        return $stornoId;
    }

    /**
     * Gutschein für Bon-Zahlung auflösen (K8c) — Code oder ID, Restwert prüfen.
     *
     * @param array<string, mixed> $input
     * @return array{id: int, code: string, amount: float}
     */
    private static function resolveGiftVoucherForPayment(array $input, float $amount): array
    {
        if (!class_exists('GiftVoucherRepository') || !Database::isConfigured()) {
            throw new RuntimeException('Gutschein-Stamm nicht verfügbar — bitte Migrationen prüfen.');
        }
        GiftVoucherRepository::ensureTables();
        $code = trim((string) ($input['gift_code'] ?? $input['voucher_code'] ?? ''));
        $giftId = max(0, (int) ($input['gift_voucher_id'] ?? 0));
        $gift = $giftId > 0
            ? GiftVoucherRepository::findById($giftId)
            : ($code !== '' ? GiftVoucherRepository::findByCode($code) : null);
        if ($gift === null) {
            throw new RuntimeException(
                'Gutschein nicht gefunden' . ($code !== '' ? ': ' . $code : '') . ' — Code prüfen.'
            );
        }
        if ((string) ($gift['status'] ?? '') !== GiftVoucherRepository::STATUS_ACTIVE) {
            throw new RuntimeException('Gutschein ist nicht aktiv: ' . (string) ($gift['code'] ?? ''));
        }
        $validUntil = trim((string) ($gift['valid_until'] ?? ''));
        if ($validUntil !== '' && $validUntil < date('Y-m-d')) {
            throw new RuntimeException('Gutschein ist abgelaufen: ' . (string) ($gift['code'] ?? ''));
        }
        $rest = round((float) ($gift['remaining_gross'] ?? $gift['remaining'] ?? 0), 2);
        if ($rest <= 0) {
            throw new RuntimeException('Gutschein hat keinen Restwert mehr.');
        }
        if ($amount - $rest > 0.009) {
            throw new RuntimeException(
                'Einlösebetrag übersteigt Gutschein-Restwert ('
                . number_format($rest, 2, ',', '.') . ' €).'
            );
        }

        return [
            'id' => (int) ($gift['id'] ?? 0),
            'code' => (string) ($gift['code'] ?? $code),
            'amount' => round($amount, 2),
        ];
    }

    /** Snapshot für Selftest K13c (Original unverändert). */
    public static function integritySnapshot(int $receiptId): array
    {
        $receipt = self::requireReceipt($receiptId);
        $lines = CashReceiptRepository::listLines($receiptId);
        $payments = CashPaymentRepository::tableExists()
            ? CashPaymentRepository::listByReceipt($receiptId)
            : [];

        return [
            'receipt' => $receipt,
            'lines' => $lines,
            'payments' => $payments,
            'line_count' => count($lines),
            'payment_count' => count($payments),
        ];
    }

    private static function negateVatSummaryJson(mixed $json): ?string
    {
        if ($json === null || $json === '') {
            return null;
        }
        $data = is_string($json) ? json_decode($json, true) : $json;
        if (!is_array($data)) {
            return is_string($json) ? $json : null;
        }
        foreach ($data as &$row) {
            if (!is_array($row)) {
                continue;
            }
            foreach (['gross', 'net', 'tax'] as $key) {
                if (isset($row[$key])) {
                    $row[$key] = round(-1 * (float) $row[$key], 2);
                }
            }
        }
        unset($row);
        $encoded = json_encode(array_values($data), JSON_UNESCAPED_UNICODE);

        return $encoded === false ? null : $encoded;
    }

    private static function negatePaymentSummaryJson(mixed $json): ?string
    {
        if ($json === null || $json === '') {
            return null;
        }
        $data = is_string($json) ? json_decode($json, true) : $json;
        if (!is_array($data)) {
            return is_string($json) ? $json : null;
        }
        foreach ($data as &$row) {
            if (!is_array($row)) {
                continue;
            }
            if (isset($row['amount'])) {
                $row['amount'] = round(-1 * (float) $row['amount'], 2);
            }
        }
        unset($row);
        $encoded = json_encode(array_values($data), JSON_UNESCAPED_UNICODE);

        return $encoded === false ? null : $encoded;
    }

    /** payment_summary_json + Status draft/paying. */
    public static function refreshPaymentState(int $receiptId): void
    {
        $payments = CashPaymentRepository::listByReceipt($receiptId);
        $summary = [];
        foreach ($payments as $row) {
            $method = (string) ($row['payment_method'] ?? '');
            $amount = round((float) ($row['amount'] ?? 0), 2);
            if (!isset($summary[$method])) {
                $summary[$method] = ['method' => $method, 'amount' => 0.0];
            }
            $summary[$method]['amount'] = round($summary[$method]['amount'] + $amount, 2);
        }
        $status = $payments === [] ? self::STATUS_DRAFT : self::STATUS_PAYING;
        CashReceiptRepository::updateReceipt($receiptId, [
            'status' => $status,
            'payment_summary_json' => $summary === []
                ? null
                : json_encode(array_values($summary), JSON_UNESCAPED_UNICODE),
        ]);
    }

    /** Summen + USt-Zusammenfassung aus Positionen. */
    public static function recalculateTotals(int $receiptId): void
    {
        $lines = CashReceiptRepository::listLines($receiptId);
        $gross = 0.0;
        $net = 0.0;
        /** @var array<int, array{rate: int, gross: float, net: float, tax: float}> $byRate */
        $byRate = [];
        foreach ($lines as $line) {
            $g = round((float) ($line['gross_amount'] ?? 0), 2);
            $rate = (int) ($line['tax_rate'] ?? 0);
            $n = self::netFromGross($g, $rate);
            $t = round($g - $n, 2);
            $gross += $g;
            $net += $n;
            if (!isset($byRate[$rate])) {
                $byRate[$rate] = ['rate' => $rate, 'gross' => 0.0, 'net' => 0.0, 'tax' => 0.0];
            }
            $byRate[$rate]['gross'] = round($byRate[$rate]['gross'] + $g, 2);
            $byRate[$rate]['net'] = round($byRate[$rate]['net'] + $n, 2);
            $byRate[$rate]['tax'] = round($byRate[$rate]['tax'] + $t, 2);
        }
        ksort($byRate);
        CashReceiptRepository::updateReceipt($receiptId, [
            'total_gross' => round($gross, 2),
            'total_net' => round($net, 2),
            'vat_summary_json' => $byRate === []
                ? null
                : json_encode(array_values($byRate), JSON_UNESCAPED_UNICODE),
        ]);
    }

    /** @return array<string, mixed> */
    private static function requireDraft(int $receiptId): array
    {
        $receipt = self::requireReceipt($receiptId);
        if (($receipt['status'] ?? '') !== self::STATUS_DRAFT) {
            throw new RuntimeException('Nur Entwürfe dürfen geändert werden.');
        }

        return $receipt;
    }

    /** Zahlungen: draft oder paying. */
    private static function requirePayable(int $receiptId): array
    {
        $receipt = self::requireReceipt($receiptId);
        $status = (string) ($receipt['status'] ?? '');
        if ($status !== self::STATUS_DRAFT && $status !== self::STATUS_PAYING) {
            throw new RuntimeException('Zahlungen nur bei Entwurf oder während der Zahlung.');
        }

        return $receipt;
    }

    /** @return array<string, mixed> */
    private static function requireReceipt(int $receiptId): array
    {
        if ($receiptId <= 0) {
            throw new RuntimeException('Bon fehlt.');
        }
        $receipt = CashReceiptRepository::findById($receiptId);
        if ($receipt === null) {
            throw new RuntimeException('Bon nicht gefunden.');
        }

        return $receipt;
    }

    /** @return array<string, mixed> */
    private static function requireLineOnReceipt(int $receiptId, int $lineId): array
    {
        if ($lineId <= 0) {
            throw new RuntimeException('Position fehlt.');
        }
        $line = CashReceiptRepository::findLineById($lineId);
        if ($line === null || (int) ($line['receipt_id'] ?? 0) !== $receiptId) {
            throw new RuntimeException('Position gehört nicht zu diesem Bon.');
        }

        return $line;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $existing
     * @return array{title: string, unit: string, quantity: float, unit_price_gross: float, gross_amount: float, tax_rate: int}
     */
    private static function normalizeLineInput(array $input, ?array $existing = null): array
    {
        $title = trim((string) ($input['title'] ?? ($existing['title'] ?? '')));
        if ($title === '') {
            throw new RuntimeException('Bezeichnung fehlt.');
        }
        $unit = trim((string) ($input['unit'] ?? ($existing['unit'] ?? 'Stück')));
        if ($unit === '') {
            $unit = 'Stück';
        }
        $qty = self::parseDecimal($input['quantity'] ?? ($existing['quantity'] ?? 1), 1.0);
        if ($qty <= 0) {
            throw new RuntimeException('Menge muss größer als 0 sein.');
        }
        $unitPrice = self::parseDecimal(
            $input['unit_price_gross'] ?? ($existing['unit_price_gross'] ?? 0),
            0.0
        );
        if ($unitPrice < 0) {
            throw new RuntimeException('Preis darf nicht negativ sein.');
        }
        $taxRate = (int) ($input['tax_rate'] ?? ($existing['tax_rate'] ?? 19));
        if (!in_array($taxRate, self::TAX_RATES, true)) {
            throw new RuntimeException('Ungültiger Steuersatz.');
        }
        $gross = round($qty * $unitPrice, 2);

        return [
            'title' => $title,
            'unit' => $unit,
            'quantity' => round($qty, 3),
            'unit_price_gross' => round($unitPrice, 2),
            'gross_amount' => $gross,
            'tax_rate' => $taxRate,
        ];
    }

    private static function parseDecimal(mixed $value, float $default): float
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return $default;
        }
        $raw = str_replace([' ', "\xc2\xa0"], '', $raw);
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } else {
            $raw = str_replace(',', '.', $raw);
        }
        if (!is_numeric($raw)) {
            throw new RuntimeException('Ungültige Zahl: ' . $value);
        }

        return (float) $raw;
    }

    private static function netFromGross(float $gross, int $taxRate): float
    {
        if ($taxRate <= 0) {
            return round($gross, 2);
        }

        return round($gross / (1 + ($taxRate / 100)), 2);
    }
}
