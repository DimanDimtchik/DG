<?php
declare(strict_types=1);

/**
 * CSV-Export festgeschriebener Bons für einen Zeitraum (K16b).
 */
final class CashReceiptJournalCsvExport
{
    public const EXPORT_TYPE = 'journal_csv';

    /**
     * @return array{date_from: string, date_to: string, register_id: int}
     */
    public static function sanitizeRequest(array $input): array
    {
        $from = trim((string) ($input['date_from'] ?? ''));
        $to = trim((string) ($input['date_to'] ?? ''));
        if ($from === '' && isset($input['business_date'])) {
            $from = trim((string) $input['business_date']);
        }
        if ($to === '' && $from !== '') {
            $to = $from;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) !== 1) {
            throw new InvalidArgumentException('Zeitraum ungültig — bitte Von und Bis setzen.');
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $span = (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days;
        if ($span > 366) {
            throw new InvalidArgumentException('Zeitraum maximal 366 Tage.');
        }

        return [
            'date_from' => $from,
            'date_to' => $to,
            'register_id' => max(0, (int) ($input['register_id'] ?? 0)),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public static function buildCsv(array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            throw new RuntimeException('CSV-Puffer konnte nicht geöffnet werden.');
        }
        fputcsv($fh, [
            'Bon',
            'Typ',
            'Kassen-ID',
            'Kasse',
            'Filiale',
            'Geschäftstag',
            'Fertig',
            'Brutto',
            'Netto',
            'Bar',
            'EC',
            'Karte',
            'Gutschein',
            'TSE-Ausfall',
            'TSE-Tx',
            'Storno-von',
            'Notiz',
        ], ';', '"', '\\');

        foreach ($rows as $row) {
            $pays = self::paymentAmounts($row['payment_summary_json'] ?? null);
            $display = trim((string) ($row['receipt_number_display'] ?? ''));
            if ($display === '') {
                $display = (string) (int) ($row['receipt_number'] ?? 0);
            }
            $type = (string) ($row['receipt_type'] ?? 'sale');
            $typeLabel = $type === 'storno' ? 'Storno' : 'Verkauf';
            fputcsv($fh, [
                $display,
                $typeLabel,
                (string) ($row['register_code'] ?? ''),
                (string) ($row['register_name'] ?? ''),
                trim((string) ($row['location_code'] ?? '') . ' ' . (string) ($row['location_name'] ?? '')),
                (string) ($row['business_date'] ?? ''),
                (string) ($row['finished_at'] ?? ''),
                self::num((float) ($row['total_gross'] ?? 0)),
                self::num((float) ($row['total_net'] ?? 0)),
                self::num($pays['cash']),
                self::num($pays['ec']),
                self::num($pays['card']),
                self::num($pays['voucher']),
                !empty($row['tse_outage']) ? '1' : '0',
                (string) ($row['tse_tx_id'] ?? ''),
                (string) (int) ($row['storno_of_receipt_id'] ?? 0),
                (string) ($row['notes'] ?? ''),
            ], ';', '"', '\\');
        }

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        if ($csv === false) {
            throw new RuntimeException('CSV konnte nicht gelesen werden.');
        }

        return "\xEF\xBB\xBF" . $csv;
    }

    /**
     * Lädt Daten, optional Export-Metadaten, sendet Download-Header und beendet.
     */
    public static function downloadAndExit(
        string $dateFrom,
        string $dateTo,
        int $registerId = 0,
        ?int $userId = null
    ): void {
        $filter = self::sanitizeRequest([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'register_id' => $registerId,
        ]);
        $rows = CashReceiptRepository::listJournalRange(
            $filter['register_id'] > 0 ? $filter['register_id'] : null,
            $filter['date_from'],
            $filter['date_to'],
            5000
        );
        $csv = self::buildCsv($rows);
        $checksum = hash('sha256', $csv);
        $filename = 'bon-journal-' . $filter['date_from'] . '_' . $filter['date_to'] . '.csv';

        if (CashExportRepository::tableExists()) {
            try {
                $exportId = CashExportRepository::insert([
                    'register_id' => $filter['register_id'] > 0 ? $filter['register_id'] : null,
                    'export_type' => self::EXPORT_TYPE,
                    'date_from' => $filter['date_from'],
                    'date_to' => $filter['date_to'],
                    'status' => 'done',
                    'file_path' => $filename,
                    'file_checksum' => $checksum,
                    'created_by' => $userId !== null && $userId > 0 ? $userId : null,
                    'finished_at' => date('Y-m-d H:i:s'),
                    'meta_json' => json_encode([
                        'row_count' => count($rows),
                        'bytes' => strlen($csv),
                    ], JSON_UNESCAPED_UNICODE),
                ]);
                if (CashAuditLogRepository::tableExists()) {
                    CashAuditLogRepository::insert([
                        'event_type' => 'export.journal_csv',
                        'register_id' => $filter['register_id'] > 0 ? $filter['register_id'] : null,
                        'user_id' => $userId,
                        'message' => 'Bon-Journal CSV ' . $filter['date_from'] . '–' . $filter['date_to'],
                        'details_json' => json_encode([
                            'export_id' => $exportId,
                            'rows' => count($rows),
                            'checksum' => $checksum,
                        ], JSON_UNESCAPED_UNICODE),
                    ]);
                }
            } catch (Throwable) {
                // Download trotzdem ausliefern
            }
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) strlen($csv));
        header('Cache-Control: no-store');
        echo $csv;
        exit;
    }

    /**
     * @return array{cash: float, ec: float, card: float, voucher: float}
     */
    private static function paymentAmounts(mixed $json): array
    {
        $out = ['cash' => 0.0, 'ec' => 0.0, 'card' => 0.0, 'voucher' => 0.0];
        $data = is_string($json) ? json_decode($json, true) : $json;
        if (!is_array($data)) {
            return $out;
        }
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $method = strtolower(trim((string) ($row['method'] ?? '')));
            if (!isset($out[$method])) {
                continue;
            }
            $out[$method] = round($out[$method] + (float) ($row['amount'] ?? 0), 2);
        }

        return $out;
    }

    private static function num(float $v): string
    {
        return number_format($v, 2, ',', '');
    }
}
