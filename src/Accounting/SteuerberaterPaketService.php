<?php
declare(strict_types=1);

/** Ein-Klick-Paket für Steuerberater: alle Exporte in einer ZIP. */
final class SteuerberaterPaketService
{
    /**
     * @return array{filename: string, path: string}
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

        $tmpDir = sys_get_temp_dir() . '/dg-stb-' . bin2hex(random_bytes(8));
        if (!mkdir($tmpDir, 0700, true) && !is_dir($tmpDir)) {
            throw new RuntimeException('Temporäres Verzeichnis konnte nicht erstellt werden.');
        }

        $exports = [
            DatevExtfExporter::export($year, includeManual: true),
            DatevStammdatenExporter::exportAccounts($year),
            DatevStammdatenExporter::exportPersonAccounts(),
            AgendaExporter::export($year),
            ElsterExportService::exportUstva($year),
            ElsterExportService::exportEuer($year),
        ];

        foreach ($exports as $export) {
            file_put_contents($tmpDir . '/' . $export['filename'], $export['content']);
        }

        $period = AccountingPeriodFilter::fromRequest(['year' => $year]);
        $susa = SusaReportService::report($period);
        $susaLines = ["Konto;Bezeichnung;Soll;Haben;Anfang;Saldo"];
        foreach ($susa['accounts'] as $row) {
            $susaLines[] = sprintf(
                '%s;%s;%.2f;%.2f;%.2f;%.2f',
                $row['account_number'] ?? '',
                str_replace(';', ',', (string) ($row['name'] ?? '')),
                (float) ($row['debit'] ?? 0),
                (float) ($row['credit'] ?? 0),
                (float) ($row['opening'] ?? 0),
                (float) ($row['balance'] ?? 0)
            );
        }
        file_put_contents($tmpDir . '/SuSa_' . $year . '.csv', "\xEF\xBB\xBF" . implode("\n", $susaLines));

        $belege = DatevBelegExportService::buildZip($year);
        copy($belege['path'], $tmpDir . '/' . $belege['filename']);
        @unlink($belege['path']);

        $readme = "Steuerberater-Paket {$year}\n"
            . 'Erstellt: ' . date('d.m.Y H:i') . "\n\n"
            . "Enthalten: DATEV EXTF, Stammdaten, Personenkonten, Agenda, UStVA, EÜR, SuSa, Beleg-ZIP\n";
        file_put_contents($tmpDir . '/README.txt', $readme);

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
        $zip->close();

        return [
            'filename' => sprintf('Steuerberater_Paket_%d_%s.zip', $year, date('Ymd')),
            'path' => $zipPath,
        ];
    }
}
