<?php
declare(strict_types=1);

/**
 * Prüferpaket-ZIP (K18c): dsfinvk/ (Stub K17b) + tse/ Stub-Status + Journal + Manifest.
 * Echte Provider-TAR = K18b. Spec: docs/CASH-PRUEFERPAKET-SPEC.md
 */
final class CashPrueferpaketExport
{
    public const EXPORT_TYPE = 'pruefer';
    public const SPEC_ID = 'CASH-PRUEFERPAKET-SPEC K18a';

    /**
     * Erwartete ZIP-Einträge (flach mit Slash-Pfaden).
     *
     * @return list<string>
     */
    public static function expectedArchiveNames(): array
    {
        $names = [
            'README.txt',
            'manifest.json',
            'tse/EXPORT_STATUS.txt',
            'journal/cash_receipts_journal.csv',
        ];
        foreach (array_keys(CashDsfinvkStubExport::csvCatalog()) as $csv) {
            $names[] = 'dsfinvk/' . $csv;
        }
        $names[] = 'dsfinvk/index.xml';
        $names[] = 'dsfinvk/gdpdu-01-09-2004.dtd';
        // DSFinV-Stub hat eigenes README — im Unterordner behalten wir die Spec-Dateien ohne Root-README
        sort($names);

        return $names;
    }

    /**
     * @return array{
     *   path: string,
     *   filename: string,
     *   sha256: string,
     *   files: int,
     *   date_from: string,
     *   date_to: string,
     *   register_id: int
     * }
     */
    public static function buildZip(
        string $dateFrom,
        string $dateTo,
        int $registerId = 0,
        ?array $journalRows = null
    ): array {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP-ZipArchive fehlt — Prüferpaket nicht möglich.');
        }

        $filter = CashReceiptJournalCsvExport::sanitizeRequest([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'register_id' => $registerId,
        ]);
        $from = $filter['date_from'];
        $to = $filter['date_to'];
        $regId = $filter['register_id'];

        if ($journalRows === null) {
            $journalRows = [];
            if (class_exists('Database') && Database::isConfigured()
                && class_exists('CashReceiptRepository')
                && CashReceiptRepository::tableExists()
            ) {
                $journalRows = CashReceiptRepository::listJournalRange(
                    $regId > 0 ? $regId : null,
                    $from,
                    $to,
                    5000
                );
            }
        }

        $outagePresent = false;
        foreach ($journalRows as $row) {
            if (!empty($row['tse_outage'])) {
                $outagePresent = true;
                break;
            }
        }

        // K17c: befüllte DSFinV-K-Dateien im Prüferpaket (Fallback Stub wenn Klasse fehlt)
        $dsfinvkMap = class_exists('CashDsfinvkExport')
            ? CashDsfinvkExport::buildFileMap($from, $to, $regId > 0 ? $regId : null)
            : CashDsfinvkStubExport::buildFileMap();
        // Root-README kommt vom Prüferpaket; DSFinV-README nicht doppelt
        unset($dsfinvkMap['README.txt']);

        $journalCsv = CashReceiptJournalCsvExport::buildCsv($journalRows);
        $tseStatus = self::buildTseExportStatus($from, $to, $outagePresent);
        $companyLabel = self::companyLabel();

        $fileEntries = [];
        foreach ($dsfinvkMap as $name => $content) {
            $fileEntries['dsfinvk/' . $name] = $content;
        }
        $fileEntries['tse/EXPORT_STATUS.txt'] = $tseStatus;
        $fileEntries['journal/cash_receipts_journal.csv'] = $journalCsv;

        $dsfinvkFilesMeta = [];
        foreach ($dsfinvkMap as $name => $content) {
            $dsfinvkFilesMeta[] = [
                'name' => 'dsfinvk/' . $name,
                'sha256' => hash('sha256', $content),
            ];
        }

        $manifestFiles = [];
        foreach ($fileEntries as $name => $content) {
            $manifestFiles[] = [
                'name' => $name,
                'sha256' => hash('sha256', $content),
            ];
        }

        $readme = self::buildReadme($from, $to, $regId, $outagePresent, count($journalRows));
        $fileEntries['README.txt'] = $readme;
        $manifestFiles[] = [
            'name' => 'README.txt',
            'sha256' => hash('sha256', $readme),
        ];

        $manifest = [
            'product' => 'DG CRM',
            'export_type' => self::EXPORT_TYPE,
            'spec' => self::SPEC_ID,
            'generated_at' => date('c'),
            'date_from' => $from,
            'date_to' => $to,
            'location_id' => null,
            'register_ids' => $regId > 0 ? [$regId] : [],
            'company_label' => $companyLabel,
            'dsfinvk' => [
                'spec_version' => CashDsfinvkStubExport::SPEC_VERSION,
                'status' => class_exists('CashDsfinvkExport') ? 'filled' : 'stub',
                'files' => $dsfinvkFilesMeta,
            ],
            'tse' => [
                'status' => 'stub',
                'provider' => 'none',
                'files' => [
                    [
                        'name' => 'tse/EXPORT_STATUS.txt',
                        'sha256' => hash('sha256', $tseStatus),
                    ],
                ],
            ],
            'files' => $manifestFiles,
        ];

        $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($manifestJson)) {
            $manifestJson = '{}';
        }
        $fileEntries['manifest.json'] = $manifestJson . "\n";

        $tmp = tempnam(sys_get_temp_dir(), 'dg_pruefer_');
        if ($tmp === false) {
            throw new RuntimeException('Temporäre Datei konnte nicht angelegt werden.');
        }
        $zipPath = $tmp . '.zip';
        @unlink($tmp);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ZIP konnte nicht erstellt werden.');
        }
        foreach ($fileEntries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        $sha = hash_file('sha256', $zipPath);
        if (!is_string($sha)) {
            $sha = '';
        }

        $filename = sprintf('DG_Cash_Prueferpaket_%s_%s', $from, $to);
        if ($regId > 0) {
            $code = self::registerCodeSuffix($regId);
            if ($code !== '') {
                $filename .= '_' . $code;
            }
        }
        $filename .= '.zip';

        return [
            'path' => $zipPath,
            'filename' => $filename,
            'sha256' => $sha,
            'files' => count($fileEntries),
            'date_from' => $from,
            'date_to' => $to,
            'register_id' => $regId,
        ];
    }

    public static function downloadAndExit(
        string $dateFrom,
        string $dateTo,
        int $registerId = 0,
        ?int $userId = null
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
                    'register_id' => $pack['register_id'] > 0 ? $pack['register_id'] : null,
                    'export_type' => self::EXPORT_TYPE,
                    'date_from' => $pack['date_from'],
                    'date_to' => $pack['date_to'],
                    'status' => 'done',
                    'file_path' => $pack['filename'],
                    'file_checksum' => $pack['sha256'],
                    'created_by' => $userId !== null && $userId > 0 ? $userId : null,
                    'finished_at' => date('Y-m-d H:i:s'),
                    'meta_json' => json_encode([
                        'spec' => self::SPEC_ID,
                        'tse_status' => 'stub',
                        'dsfinvk_status' => class_exists('CashDsfinvkExport') ? 'filled' : 'stub',
                        'files' => $pack['files'],
                    ], JSON_UNESCAPED_UNICODE),
                ]);
                if (CashAuditLogRepository::tableExists()) {
                    CashAuditLogRepository::insert([
                        'event_type' => 'export.pruefer',
                        'register_id' => $pack['register_id'] > 0 ? $pack['register_id'] : null,
                        'user_id' => $userId,
                        'message' => 'Prüferpaket ' . $pack['filename'],
                        'details_json' => json_encode([
                            'export_id' => $exportId,
                            'sha256' => $pack['sha256'],
                            'tse_status' => 'stub',
                            'dsfinvk_status' => class_exists('CashDsfinvkExport') ? 'filled' : 'stub',
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

    private static function buildTseExportStatus(string $from, string $to, bool $outagePresent): string
    {
        return "status=stub\n"
            . "reason=provider_not_implemented\n"
            . "date_from={$from}\n"
            . "date_to={$to}\n"
            . 'outage_receipts_present=' . ($outagePresent ? 'yes' : 'no') . "\n"
            . "note=Echter TSE-TAR folgt mit K18b (TseProviderInterface::exportData).\n"
            . "note2=DSFinV-K Signaturfelder siehe dsfinvk/transactions_tse.csv (derzeit Stub-Köpfe).\n"
            . 'generated_at=' . date('c') . "\n";
    }

    private static function buildReadme(
        string $from,
        string $to,
        int $registerId,
        bool $outagePresent,
        int $journalRows
    ): string {
        $lines = [
            'DG CRM — Kassen-Prüferpaket (K18c)',
            'Spec: ' . self::SPEC_ID,
            'Erzeugt: ' . date('c'),
            'Zeitraum: ' . $from . ' bis ' . $to,
            'Kasse-Filter: ' . ($registerId > 0 ? (string) $registerId : 'alle'),
            'Firma (Instanz): ' . self::companyLabel(),
            '',
            'Inhalt:',
            '- dsfinvk/  DSFinV-K Pflichtstruktur (derzeit Stub ohne Bon-Daten, K17b/c)',
            '- tse/      EXPORT_STATUS.txt — noch kein Provider-TAR (K18b)',
            '- journal/  Bon-Journal CSV (festgeschriebene Bons)',
            '- manifest.json  Dateiliste + SHA-256',
            '',
            'Journal-Zeilen: ' . (string) $journalRows,
            'TSE-Ausfall-Bons im Zeitraum: ' . ($outagePresent ? 'ja' : 'nein'),
            '',
            'Nicht enthalten: FiBu-GoBD-Export, Secrets, Entwürfe.',
            'Dokumentation: docs/CASH-PRUEFERPAKET-SPEC.md',
        ];

        return implode("\n", $lines) . "\n";
    }

    private static function companyLabel(): string
    {
        if (class_exists('CompanySettings') && method_exists('CompanySettings', 'displayName')) {
            try {
                $name = trim((string) CompanySettings::displayName());
                if ($name !== '') {
                    return $name;
                }
            } catch (Throwable) {
                // Fallback
            }
        }

        return '';
    }

    private static function registerCodeSuffix(int $registerId): string
    {
        if ($registerId <= 0 || !class_exists('CashRegisterRepository') || !CashRegisterRepository::tableExists()) {
            return 'r' . (string) $registerId;
        }
        try {
            $row = CashRegisterRepository::findById($registerId);
            $code = preg_replace('/[^A-Za-z0-9_-]+/', '', (string) ($row['code'] ?? ''));
            if (is_string($code) && $code !== '') {
                return $code;
            }
        } catch (Throwable) {
            // Fallback
        }

        return 'r' . (string) $registerId;
    }
}
