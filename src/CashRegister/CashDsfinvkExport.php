<?php
declare(strict_types=1);

/**
 * DSFinV-K Befüllung aus dg_cash_* (K17c).
 * Nutzt Katalog/Index aus CashDsfinvkStubExport; füllt Datenzeilen.
 */
final class CashDsfinvkExport
{
    public const EXPORT_TYPE = 'dsfinvk';

    /**
     * @return array<string, string> Dateiname → Inhalt
     */
    public static function buildFileMap(?string $dateFrom = null, ?string $dateTo = null, ?int $registerId = null): array
    {
        $from = self::sanitizeDate($dateFrom) ?? date('Y-m-d', strtotime('-30 days'));
        $to = self::sanitizeDate($dateTo) ?? date('Y-m-d');
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $ctx = self::loadContext($from, $to, $registerId);
        $catalog = CashDsfinvkStubExport::csvCatalog();
        $files = [];
        foreach ($catalog as $filename => $meta) {
            $rows = self::rowsFor($filename, $ctx);
            $files[$filename] = self::csvWithRows($meta['columns'], $rows);
        }
        $files['index.xml'] = self::buildIndexXml($catalog, $from, $to);
        $files['gdpdu-01-09-2004.dtd'] = self::minimalDtd();
        $files['README.txt'] = self::readme($from, $to);

        return $files;
    }

    /**
     * @return array{path: string, filename: string, sha256: string, files: int}
     */
    public static function buildZip(?string $dateFrom = null, ?string $dateTo = null, ?int $registerId = null): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP-ZipArchive fehlt — DSFinV-K-Export nicht möglich.');
        }
        $from = self::sanitizeDate($dateFrom) ?? date('Y-m-d', strtotime('-30 days'));
        $to = self::sanitizeDate($dateTo) ?? date('Y-m-d');
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'dg_dsfinvk_');
        if ($tmp === false) {
            throw new RuntimeException('Temporäre Datei konnte nicht angelegt werden.');
        }
        $zipPath = $tmp . '.zip';
        @unlink($tmp);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ZIP konnte nicht erstellt werden.');
        }
        $map = self::buildFileMap($from, $to, $registerId);
        foreach ($map as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        $sha = hash_file('sha256', $zipPath);
        if (!is_string($sha)) {
            $sha = '';
        }

        return [
            'path' => $zipPath,
            'filename' => sprintf('DSFinVK_%s_%s.zip', $from, $to),
            'sha256' => $sha,
            'files' => count($map),
        ];
    }

    public static function downloadAndExit(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $userId = null,
        ?int $registerId = null
    ): void {
        $pack = self::buildZip($dateFrom, $dateTo, $registerId);
        $binary = file_get_contents($pack['path']);
        @unlink($pack['path']);
        if ($binary === false) {
            throw new RuntimeException('ZIP konnte nicht gelesen werden.');
        }

        if (class_exists('CashExportRepository') && CashExportRepository::tableExists()) {
            try {
                $exportId = CashExportRepository::insert([
                    'export_type' => self::EXPORT_TYPE,
                    'register_id' => $registerId !== null && $registerId > 0 ? $registerId : null,
                    'date_from' => self::sanitizeDate($dateFrom) ?? date('Y-m-d'),
                    'date_to' => self::sanitizeDate($dateTo) ?? date('Y-m-d'),
                    'status' => 'done',
                    'file_path' => $pack['filename'],
                    'file_checksum' => $pack['sha256'],
                    'created_by' => $userId !== null && $userId > 0 ? $userId : null,
                    'finished_at' => date('Y-m-d H:i:s'),
                    'meta_json' => json_encode([
                        'spec' => CashDsfinvkStubExport::SPEC_VERSION,
                        'stub' => false,
                        'files' => $pack['files'],
                    ], JSON_UNESCAPED_UNICODE),
                ]);
                if (CashAuditLogRepository::tableExists()) {
                    CashAuditLogRepository::insert([
                        'event_type' => 'export.dsfinvk',
                        'user_id' => $userId,
                        'register_id' => $registerId,
                        'message' => 'DSFinV-K ZIP ' . $pack['filename'],
                        'details_json' => json_encode([
                            'export_id' => $exportId,
                            'sha256' => $pack['sha256'],
                            'stub' => false,
                        ], JSON_UNESCAPED_UNICODE),
                    ]);
                }
            } catch (Throwable) {
                // Download trotzdem
            }
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $pack['filename'] . '"');
        header('Content-Length: ' . (string) strlen($binary));
        header('Cache-Control: no-store');
        echo $binary;
        exit;
    }

    /**
     * @return array{
     *   from: string,
     *   to: string,
     *   locations: array<int, array<string, mixed>>,
     *   registers: array<int, array<string, mixed>>,
     *   assignments: array<int, array<string, mixed>>,
     *   closings: list<array<string, mixed>>,
     *   receipts: list<array<string, mixed>>,
     *   lines_by_receipt: array<int, list<array<string, mixed>>>,
     *   payments_by_receipt: array<int, list<array<string, mixed>>>,
     *   z_nr_by_register_date: array<string, int>
     * }
     */
    private static function loadContext(string $from, string $to, ?int $registerId): array
    {
        $locations = [];
        $registers = [];
        $assignments = [];
        $closings = [];
        $receipts = [];
        $linesBy = [];
        $paymentsBy = [];

        if (CashLocationRepository::tableExists()) {
            foreach (CashLocationRepository::listAll() as $loc) {
                $locations[(int) $loc['id']] = $loc;
            }
        }
        if (CashRegisterRepository::tableExists()) {
            foreach (CashRegisterRepository::listAll() as $reg) {
                $id = (int) ($reg['id'] ?? 0);
                if ($registerId !== null && $registerId > 0 && $id !== $registerId) {
                    continue;
                }
                $registers[$id] = $reg;
            }
        }
        if (CashTseAssignmentRepository::tableExists()) {
            foreach ($registers as $rid => $_) {
                $a = CashTseAssignmentRepository::findByRegisterId($rid);
                if ($a !== null) {
                    $assignments[$rid] = $a;
                }
            }
        }

        if (CashRegisterDayClosingRepository::tableExists() && $registers !== []) {
            $placeholders = [];
            $params = ['from' => $from, 'to' => $to];
            $i = 0;
            foreach (array_keys($registers) as $rid) {
                $key = 'r' . $i++;
                $placeholders[] = ':' . $key;
                $params[$key] = $rid;
            }
            $sql = 'SELECT * FROM dg_cash_register_day_closings
                    WHERE business_date BETWEEN :from AND :to
                      AND register_id IN (' . implode(',', $placeholders) . ')
                    ORDER BY business_date ASC, register_id ASC';
            $stmt = Database::pdo()->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $closings[] = $row;
            }
        }

        if (CashReceiptRepository::tableExists() && $registers !== []) {
            $placeholders = [];
            $params = ['from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59'];
            $i = 0;
            foreach (array_keys($registers) as $rid) {
                $key = 'r' . $i++;
                $placeholders[] = ':' . $key;
                $params[$key] = $rid;
            }
            $sql = "SELECT * FROM dg_cash_receipts
                    WHERE status = 'finalized'
                      AND finished_at BETWEEN :from AND :to
                      AND register_id IN (" . implode(',', $placeholders) . ')
                    ORDER BY register_id ASC, receipt_number ASC, id ASC';
            $stmt = Database::pdo()->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $receipts[] = $row;
                $rid = (int) ($row['id'] ?? 0);
                $linesBy[$rid] = CashReceiptRepository::listLines($rid);
                $paymentsBy[$rid] = CashPaymentRepository::tableExists()
                    ? CashPaymentRepository::listByReceipt($rid)
                    : [];
            }
        }

        $zNr = [];
        foreach ($closings as $c) {
            $key = (int) ($c['register_id'] ?? 0) . '|' . (string) ($c['business_date'] ?? '');
            $zNr[$key] = (int) ($c['id'] ?? 0);
        }

        return [
            'from' => $from,
            'to' => $to,
            'locations' => $locations,
            'registers' => $registers,
            'assignments' => $assignments,
            'closings' => $closings,
            'receipts' => $receipts,
            'lines_by_receipt' => $linesBy,
            'payments_by_receipt' => $paymentsBy,
            'z_nr_by_register_date' => $zNr,
        ];
    }

    /**
     * @param array<string, mixed> $ctx
     * @return list<list<string|int|float>>
     */
    private static function rowsFor(string $filename, array $ctx): array
    {
        return match ($filename) {
            'location.csv' => self::rowsLocation($ctx),
            'cashregister.csv' => self::rowsCashregister($ctx),
            'vat.csv' => self::rowsVat($ctx),
            'tse.csv' => self::rowsTse($ctx),
            'cashpointclosing.csv' => self::rowsClosing($ctx),
            'businesscases.csv' => self::rowsBusinesscases($ctx),
            'payment.csv' => self::rowsPaymentAgg($ctx),
            'cash_per_currency.csv' => self::rowsCashCurrency($ctx),
            'transactions.csv' => self::rowsTransactions($ctx),
            'transactions_vat.csv' => self::rowsTransactionsVat($ctx),
            'datapayment.csv' => self::rowsDatapayment($ctx),
            'lines.csv' => self::rowsLines($ctx),
            'lines_vat.csv' => self::rowsLinesVat($ctx),
            'references.csv' => self::rowsReferences($ctx),
            'transactions_tse.csv' => self::rowsTransactionsTse($ctx),
            default => [], // slaves, pa, allocation_groups, itemamounts, subitems oft leer
        };
    }

    /** @param array<string, mixed> $ctx @return list<list<string|int|float>> */
    private static function rowsLocation(array $ctx): array
    {
        $rows = [];
        foreach ($ctx['locations'] as $loc) {
            $rows[] = [
                (string) ($loc['code'] ?? $loc['id']),
                (string) ($loc['name'] ?? ''),
                (string) ($loc['street'] ?? ''),
                (string) ($loc['zip'] ?? ''),
                (string) ($loc['city'] ?? ''),
                (string) ($loc['country'] ?? 'DE'),
            ];
        }

        return $rows;
    }

    /** @param array<string, mixed> $ctx @return list<list<string|int|float>> */
    private static function rowsCashregister(array $ctx): array
    {
        $rows = [];
        foreach ($ctx['registers'] as $reg) {
            $loc = $ctx['locations'][(int) ($reg['location_id'] ?? 0)] ?? null;
            $rows[] = [
                (string) ($reg['code'] ?? ''),
                (string) ($reg['name'] ?? ''),
                (string) ($reg['register_type'] ?? 'store'),
                'DG CRM Registerkasse',
                is_array($loc) ? (string) ($loc['code'] ?? $loc['id']) : '',
            ];
        }

        return $rows;
    }

    /** @param array<string, mixed> $ctx @return list<list<string|int|float>> */
    private static function rowsVat(array $ctx): array
    {
        $rates = [19 => '1', 7 => '2', 0 => '5'];
        $used = [];
        foreach ($ctx['lines_by_receipt'] as $lines) {
            foreach ($lines as $line) {
                $r = (int) ($line['tax_rate'] ?? 19);
                $used[$r] = true;
            }
        }
        if ($used === []) {
            $used = [19 => true, 7 => true, 0 => true];
        }
        $rows = [];
        foreach ($rates as $rate => $id) {
            if (!isset($used[$rate])) {
                continue;
            }
            $rows[] = [$id, number_format($rate, 2, '.', ''), $rate . ' %'];
        }

        return $rows;
    }

    /** @param array<string, mixed> $ctx @return list<list<string|int|float>> */
    private static function rowsTse(array $ctx): array
    {
        $rows = [];
        $seen = [];
        foreach ($ctx['assignments'] as $rid => $a) {
            $serial = trim((string) ($a['tss_serial_number'] ?? ''));
            if ($serial === '') {
                $serial = 'REG-' . (string) ($ctx['registers'][$rid]['code'] ?? $rid);
            }
            if (isset($seen[$serial])) {
                continue;
            }
            $seen[$serial] = true;
            $rows[] = [
                $serial,
                $serial,
                'ecdsa-plain-SHA256',
                'unixTime',
            ];
        }

        return $rows;
    }

    /** @param array<string, mixed> $ctx @return list<list<string|int|float>> */
    private static function rowsClosing(array $ctx): array
    {
        $rows = [];
        foreach ($ctx['closings'] as $c) {
            $reg = $ctx['registers'][(int) ($c['register_id'] ?? 0)] ?? null;
            $kasse = is_array($reg) ? (string) ($reg['code'] ?? '') : '';
            $date = (string) ($c['business_date'] ?? '');
            $startEnd = self::bonRangeForClosing($ctx, (int) ($c['register_id'] ?? 0), $date);
            $rows[] = [
                $kasse,
                self::iso($c['closed_at'] ?? $date),
                (string) (int) ($c['id'] ?? 0),
                $startEnd[0],
                $startEnd[1],
            ];
        }

        return $rows;
    }

    /** @param array<string, mixed> $ctx @return array{0: string, 1: string} */
    private static function bonRangeForClosing(array $ctx, int $registerId, string $date): array
    {
        $first = '';
        $last = '';
        foreach ($ctx['receipts'] as $r) {
            if ((int) ($r['register_id'] ?? 0) !== $registerId) {
                continue;
            }
            $finished = substr((string) ($r['finished_at'] ?? ''), 0, 10);
            if ($finished !== $date) {
                continue;
            }
            $bon = self::bonId($r);
            if ($first === '') {
                $first = $bon;
            }
            $last = $bon;
        }

        return [$first, $last];
    }

    /** @param array<string, mixed> $ctx @return list<list<string|int|float>> */
    private static function rowsBusinesscases(array $ctx): array
    {
        $rows = [];
        foreach ($ctx['closings'] as $c) {
            $reg = $ctx['registers'][(int) ($c['register_id'] ?? 0)] ?? null;
            $kasse = is_array($reg) ? (string) ($reg['code'] ?? '') : '';
            $rows[] = [
                $kasse,
                (string) (int) ($c['id'] ?? 0),
                'Umsatz',
                'Verkauf',
                number_format((float) ($c['total_gross'] ?? 0), 2, '.', ''),
            ];
        }

        return $rows;
    }

    /** @param array<string, mixed> $ctx @return list<list<string|int|float>> */
    private static function rowsPaymentAgg(array $ctx): array
    {
        $rows = [];
        $labels = ['cash' => 'Bar', 'ec' => 'EC', 'card' => 'Karte', 'voucher' => 'Gutschein'];
        foreach ($ctx['closings'] as $c) {
            $reg = $ctx['registers'][(int) ($c['register_id'] ?? 0)] ?? null;
            $kasse = is_array($reg) ? (string) ($reg['code'] ?? '') : '';
            $z = (string) (int) ($c['id'] ?? 0);
            foreach ($labels as $method => $name) {
                $col = 'total_' . $method;
                $amount = round((float) ($c[$col] ?? 0), 2);
                if ($amount == 0.0) {
                    continue;
                }
                $rows[] = [
                    $kasse,
                    $z,
                    strtoupper($method),
                    $name,
                    number_format($amount, 2, '.', ''),
                ];
            }
        }

        return $rows;
    }

    /** @param array<string, mixed> $ctx @return list<list<string|int|float>> */
    private static function rowsCashCurrency(array $ctx): array
    {
        $rows = [];
        foreach ($ctx['closings'] as $c) {
            $cash = round((float) ($c['total_cash'] ?? 0), 2);
            if ($cash == 0.0) {
                continue;
            }
            $reg = $ctx['registers'][(int) ($c['register_id'] ?? 0)] ?? null;
            $rows[] = [
                is_array($reg) ? (string) ($reg['code'] ?? '') : '',
                (string) (int) ($c['id'] ?? 0),
                'EUR',
                number_format($cash, 2, '.', ''),
            ];
        }

        return $rows;
    }

    /** @param array<string, mixed> $ctx @return list<list<string|int|float>> */
    private static function rowsTransactions(array $ctx): array
    {
        $rows = [];
        foreach ($ctx['receipts'] as $r) {
            $reg = $ctx['registers'][(int) ($r['register_id'] ?? 0)] ?? null;
            $kasse = is_array($reg) ? (string) ($reg['code'] ?? '') : '';
            $z = self::zNrForReceipt($ctx, $r);
            $gross = round((float) ($r['total_gross'] ?? 0), 2);
            $net = round((float) ($r['total_net'] ?? 0), 2);
            $ust = round($gross - $net, 2);
            $type = (($r['receipt_type'] ?? '') === 'storno') ? 'AVBelegStorno' : 'Beleg';
            $rows[] = [
                $kasse,
                $z,
                self::bonId($r),
                $type,
                self::bonId($r),
                self::iso($r['started_at'] ?? null),
                self::iso($r['finished_at'] ?? null),
                number_format($gross, 2, '.', ''),
                number_format($net, 2, '.', ''),
                number_format($ust, 2, '.', ''),
            ];
        }

        return $rows;
    }

    /** @param array<string, mixed> $ctx @return list<list<string|int|float>> */
    private static function rowsTransactionsVat(array $ctx): array
    {
        $rows = [];
        foreach ($ctx['receipts'] as $r) {
            $rid = (int) ($r['id'] ?? 0);
            $reg = $ctx['registers'][(int) ($r['register_id'] ?? 0)] ?? null;
            $kasse = is_array($reg) ? (string) ($reg['code'] ?? '') : '';
            $z = self::zNrForReceipt($ctx, $r);
            $bon = self::bonId($r);
            $byRate = [];
            foreach ($ctx['lines_by_receipt'][$rid] ?? [] as $line) {
                $rate = (int) ($line['tax_rate'] ?? 19);
                $g = round((float) ($line['gross_amount'] ?? 0), 2);
                if (!isset($byRate[$rate])) {
                    $byRate[$rate] = 0.0;
                }
                $byRate[$rate] = round($byRate[$rate] + $g, 2);
            }
            foreach ($byRate as $rate => $gross) {
                $net = $rate > 0 ? round($gross / (1 + $rate / 100), 2) : $gross;
                $ust = round($gross - $net, 2);
                $rows[] = [
                    $kasse,
                    $z,
                    $bon,
                    self::vatId($rate),
                    number_format($gross, 2, '.', ''),
                    number_format($net, 2, '.', ''),
                    number_format($ust, 2, '.', ''),
                ];
            }
        }

        return $rows;
    }

    /** @param array<string, mixed> $ctx @return list<list<string|int|float>> */
    private static function rowsDatapayment(array $ctx): array
    {
        $labels = ['cash' => 'Bar', 'ec' => 'EC', 'card' => 'Karte', 'voucher' => 'Gutschein'];
        $rows = [];
        foreach ($ctx['receipts'] as $r) {
            $rid = (int) ($r['id'] ?? 0);
            $reg = $ctx['registers'][(int) ($r['register_id'] ?? 0)] ?? null;
            $kasse = is_array($reg) ? (string) ($reg['code'] ?? '') : '';
            $z = self::zNrForReceipt($ctx, $r);
            $bon = self::bonId($r);
            foreach ($ctx['payments_by_receipt'][$rid] ?? [] as $pay) {
                $method = strtolower(trim((string) ($pay['payment_method'] ?? 'cash')));
                $rows[] = [
                    $kasse,
                    $z,
                    $bon,
                    strtoupper($method),
                    $labels[$method] ?? $method,
                    number_format((float) ($pay['amount'] ?? 0), 2, '.', ''),
                    (string) ($pay['currency'] ?? 'EUR'),
                ];
            }
        }

        return $rows;
    }

    /** @param array<string, mixed> $ctx @return list<list<string|int|float>> */
    private static function rowsLines(array $ctx): array
    {
        $rows = [];
        foreach ($ctx['receipts'] as $r) {
            $rid = (int) ($r['id'] ?? 0);
            $reg = $ctx['registers'][(int) ($r['register_id'] ?? 0)] ?? null;
            $kasse = is_array($reg) ? (string) ($reg['code'] ?? '') : '';
            $z = self::zNrForReceipt($ctx, $r);
            $bon = self::bonId($r);
            foreach ($ctx['lines_by_receipt'][$rid] ?? [] as $line) {
                $rows[] = [
                    $kasse,
                    $z,
                    $bon,
                    (int) ($line['line_no'] ?? 1),
                    (string) ($line['title'] ?? ''),
                    number_format((float) ($line['quantity'] ?? 0), 3, '.', ''),
                    number_format((float) ($line['gross_amount'] ?? 0), 2, '.', ''),
                ];
            }
        }

        return $rows;
    }

    /** @param array<string, mixed> $ctx @return list<list<string|int|float>> */
    private static function rowsLinesVat(array $ctx): array
    {
        $rows = [];
        foreach ($ctx['receipts'] as $r) {
            $rid = (int) ($r['id'] ?? 0);
            $reg = $ctx['registers'][(int) ($r['register_id'] ?? 0)] ?? null;
            $kasse = is_array($reg) ? (string) ($reg['code'] ?? '') : '';
            $z = self::zNrForReceipt($ctx, $r);
            $bon = self::bonId($r);
            foreach ($ctx['lines_by_receipt'][$rid] ?? [] as $line) {
                $rate = (int) ($line['tax_rate'] ?? 19);
                $gross = round((float) ($line['gross_amount'] ?? 0), 2);
                $net = $rate > 0 ? round($gross / (1 + $rate / 100), 2) : $gross;
                $ust = round($gross - $net, 2);
                $rows[] = [
                    $kasse,
                    $z,
                    $bon,
                    (int) ($line['line_no'] ?? 1),
                    self::vatId($rate),
                    number_format($gross, 2, '.', ''),
                    number_format($net, 2, '.', ''),
                    number_format($ust, 2, '.', ''),
                ];
            }
        }

        return $rows;
    }

    /** @param array<string, mixed> $ctx @return list<list<string|int|float>> */
    private static function rowsReferences(array $ctx): array
    {
        $byId = [];
        foreach ($ctx['receipts'] as $r) {
            $byId[(int) ($r['id'] ?? 0)] = $r;
        }
        $rows = [];
        foreach ($ctx['receipts'] as $r) {
            if (($r['receipt_type'] ?? '') !== 'storno') {
                continue;
            }
            $origId = (int) ($r['storno_of_receipt_id'] ?? 0);
            $orig = $byId[$origId] ?? CashReceiptRepository::findById($origId);
            if (!is_array($orig)) {
                continue;
            }
            $reg = $ctx['registers'][(int) ($r['register_id'] ?? 0)] ?? null;
            $origReg = $ctx['registers'][(int) ($orig['register_id'] ?? 0)] ?? null;
            $rows[] = [
                is_array($reg) ? (string) ($reg['code'] ?? '') : '',
                self::zNrForReceipt($ctx, $r),
                self::bonId($r),
                'Storno',
                is_array($origReg) ? (string) ($origReg['code'] ?? '') : '',
                self::bonId($orig),
            ];
        }

        return $rows;
    }

    /** @param array<string, mixed> $ctx @return list<list<string|int|float>> */
    private static function rowsTransactionsTse(array $ctx): array
    {
        $rows = [];
        foreach ($ctx['receipts'] as $r) {
            $tx = trim((string) ($r['tse_tx_id'] ?? ''));
            $sig = trim((string) ($r['signature_value'] ?? ''));
            if ($tx === '' && $sig === '') {
                continue; // keine erfundenen Signaturen (K14c)
            }
            $reg = $ctx['registers'][(int) ($r['register_id'] ?? 0)] ?? null;
            $kasse = is_array($reg) ? (string) ($reg['code'] ?? '') : '';
            $a = $ctx['assignments'][(int) ($r['register_id'] ?? 0)] ?? null;
            $tseId = is_array($a) ? trim((string) ($a['tss_serial_number'] ?? '')) : '';
            if ($tseId === '') {
                $tseId = (string) ($r['tse_serial'] ?? '');
            }
            $rows[] = [
                $kasse,
                self::zNrForReceipt($ctx, $r),
                self::bonId($r),
                $tseId,
                (string) (int) ($r['transaction_number'] ?? 0),
                (string) ($r['signature_counter'] ?? ''),
                (string) ($r['qr_code_data'] ?? ''),
                $sig,
            ];
        }

        return $rows;
    }

    /** @param array<string, mixed> $ctx @param array<string, mixed> $r */
    private static function zNrForReceipt(array $ctx, array $r): string
    {
        $date = substr((string) ($r['finished_at'] ?? ''), 0, 10);
        $key = (int) ($r['register_id'] ?? 0) . '|' . $date;
        if (isset($ctx['z_nr_by_register_date'][$key])) {
            return (string) $ctx['z_nr_by_register_date'][$key];
        }

        return '0';
    }

    /** @param array<string, mixed> $r */
    private static function bonId(array $r): string
    {
        $display = trim((string) ($r['receipt_number_display'] ?? ''));
        if ($display !== '') {
            return $display;
        }

        return (string) (int) ($r['receipt_number'] ?? $r['id'] ?? 0);
    }

    private static function vatId(int $rate): string
    {
        return match ($rate) {
            0 => '5',
            7 => '2',
            default => '1',
        };
    }

    private static function iso(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return $raw;
        }

        return (new DateTimeImmutable('@' . $ts))
            ->setTimezone(new DateTimeZone('Europe/Berlin'))
            ->format('Y-m-d\TH:i:sP');
    }

    /**
     * @param list<string> $columns
     * @param list<list<string|int|float>> $rows
     */
    private static function csvWithRows(array $columns, array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            throw new RuntimeException('CSV-Puffer fehlgeschlagen.');
        }
        fputcsv($fh, $columns, ';', '"', '\\');
        foreach ($rows as $row) {
            $cells = [];
            foreach ($columns as $i => $_) {
                $cells[] = $row[$i] ?? '';
            }
            fputcsv($fh, $cells, ';', '"', '\\');
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        if ($csv === false) {
            return "\xEF\xBB\xBF";
        }

        return "\xEF\xBB\xBF" . $csv;
    }

    /**
     * @param array<string, array{logical: string, columns: list<string>}> $catalog
     */
    private static function buildIndexXml(array $catalog, string $from, string $to): string
    {
        $tables = '';
        foreach ($catalog as $filename => $meta) {
            $cols = '';
            foreach ($meta['columns'] as $col) {
                $safe = htmlspecialchars($col, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cols .= "        <VariableColumn name=\"{$safe}\">\n"
                    . "          <Name>{$safe}</Name>\n"
                    . "          <Description>{$safe}</Description>\n"
                    . "          <AlphaNumeric/>\n"
                    . "        </VariableColumn>\n";
            }
            $url = htmlspecialchars($filename, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $logical = htmlspecialchars($meta['logical'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $tables .= "    <Table>\n"
                . "      <URL>{$url}</URL>\n"
                . "      <Name>{$logical}</Name>\n"
                . "      <Description>DSFinV-K {$logical}</Description>\n"
                . "      <UTF8>true</UTF8>\n"
                . "      <DecimalSymbol>.</DecimalSymbol>\n"
                . "      <DigitGroupingSymbol></DigitGroupingSymbol>\n"
                . "      <Range>\n"
                . "        <From>2</From>\n"
                . "      </Range>\n"
                . "      <VariableLength>\n"
                . "        <ColumnDelimiter>;</ColumnDelimiter>\n"
                . "        <RecordDelimiter>\\n</RecordDelimiter>\n"
                . "        <TextEncapsulator>\"</TextEncapsulator>\n"
                . $cols
                . "      </VariableLength>\n"
                . "    </Table>\n";
        }

        $period = htmlspecialchars($from . ' … ' . $to, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<!DOCTYPE DataSet SYSTEM "gdpdu-01-09-2004.dtd">' . "\n"
            . "<DataSet>\n"
            . "  <Version>1</Version>\n"
            . "  <DataSupplier>\n"
            . "    <Name>DG CRM Registerkasse</Name>\n"
            . "    <Location>DSFinV-K " . CashDsfinvkStubExport::SPEC_VERSION . "</Location>\n"
            . "    <Comment>Befüllt K17c · Zeitraum {$period}</Comment>\n"
            . "  </DataSupplier>\n"
            . "  <Media>\n"
            . "    <Name>DSFinV-K</Name>\n"
            . $tables
            . "  </Media>\n"
            . "</DataSet>\n";
    }

    private static function minimalDtd(): string
    {
        return CashDsfinvkStubExport::buildFileMap()['gdpdu-01-09-2004.dtd']
            ?? "<!-- DTD -->\n";
    }

    private static function readme(string $from, string $to): string
    {
        return "DG CRM — DSFinV-K Export (K17c)\n"
            . "Spec-Version: " . CashDsfinvkStubExport::SPEC_VERSION . "\n"
            . "Zeitraum: {$from} – {$to}\n"
            . "Stand: " . date('c') . "\n\n"
            . "Befüllt aus dg_cash_* (finalized Bons). Ohne TSE-Signatur keine erfundenen TSE-Felder.\n"
            . "Dokumentation: docs/DSFINVK-SPEC.md\n";
    }

    private static function sanitizeDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
