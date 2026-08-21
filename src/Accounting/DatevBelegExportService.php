<?php
declare(strict_types=1);

/** ZIP-Export: DATEV Buchungsstapel + Belegdateien + Index. */
final class DatevBelegExportService
{
    /**
     * @return array{filename: string, path: string, count: int}
     */
    public static function buildZip(int $year): array
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        MigrationRunner::runPending();

        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP ZipArchive nicht verfügbar.');
        }

        $booking = DatevExtfExporter::export($year, includeManual: true);
        $accounts = DatevStammdatenExporter::exportAccounts($year);
        $persons = DatevStammdatenExporter::exportPersonAccounts();

        $tmpDir = sys_get_temp_dir() . '/dg-datev-' . bin2hex(random_bytes(8));
        if (!mkdir($tmpDir, 0700, true) && !is_dir($tmpDir)) {
            throw new RuntimeException('Temporäres Verzeichnis konnte nicht erstellt werden.');
        }

        file_put_contents($tmpDir . '/' . $booking['filename'], $booking['content']);
        file_put_contents($tmpDir . '/' . $accounts['filename'], $accounts['content']);
        file_put_contents($tmpDir . '/' . $persons['filename'], $persons['content']);

        $docLines = ['<?xml version="1.0" encoding="UTF-8"?>', '<documents>'];
        $fileCount = 0;

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            "SELECT vf.*, v.invoice_number, p.id AS posting_id
             FROM dg_voucher_files vf
             INNER JOIN dg_vouchers v ON v.id = vf.voucher_id
             INNER JOIN dg_ledger_postings p ON p.voucher_id = v.id AND p.source = 'voucher'
             WHERE p.fiscal_year = :y
             GROUP BY vf.id
             ORDER BY vf.id ASC"
        );
        $stmt->execute(['y' => $year]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $voucherId = (int) ($row['voucher_id'] ?? 0);
            $fileId = (int) ($row['id'] ?? 0);
            $resolved = VoucherFileStorage::resolveForDownload($fileId);
            if ($resolved === null) {
                continue;
            }
            $sourcePath = $resolved['path'];
            $ext = pathinfo((string) ($row['original_name'] ?? ''), PATHINFO_EXTENSION) ?: 'pdf';
            $zipName = sprintf('belege/BELEG_%d_%d.%s', $voucherId, $fileId, strtolower($ext));
            $target = $tmpDir . '/' . basename($zipName);
            $belegeDir = $tmpDir . '/belege';
            if (!is_dir($belegeDir)) {
                mkdir($belegeDir, 0700, true);
            }
            $target = $belegeDir . '/' . sprintf('BELEG_%d_%d.%s', $voucherId, $fileId, strtolower($ext));
            copy($sourcePath, $target);
            $docLines[] = sprintf(
                '<document posting="%d" invoice="%s" file="%s"/>',
                (int) ($row['posting_id'] ?? 0),
                htmlspecialchars((string) ($row['invoice_number'] ?? ''), ENT_XML1),
                htmlspecialchars(basename($target), ENT_XML1)
            );
            $fileCount++;
        }
        $docLines[] = '</documents>';
        file_put_contents($tmpDir . '/document.xml', implode("\n", $docLines));

        $zipPath = $tmpDir . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ZIP konnte nicht erstellt werden.');
        }
        foreach (glob($tmpDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                $zip->addFile($file, basename($file));
            }
        }
        foreach (glob($tmpDir . '/belege/*') ?: [] as $file) {
            if (is_file($file)) {
                $zip->addFile($file, 'belege/' . basename($file));
            }
        }
        $zip->close();

        return [
            'filename' => sprintf('DATEV_Belege_%d_%s.zip', $year, date('Ymd')),
            'path' => $zipPath,
            'count' => $booking['count'] + $fileCount,
        ];
    }
}
