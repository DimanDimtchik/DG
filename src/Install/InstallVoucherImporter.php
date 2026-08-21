<?php
declare(strict_types=1);

/**
 * Belege/Rechnungen — Import-Verarbeitung ist noch offen (TODO).
 * Dateien werden beim Installationsimport zwischengespeichert.
 */
final class InstallVoucherImporter
{
    /**
     * @return array{done: bool, progress: int, imported: int, skipped: int, errors: list<string>, message: string}
     */
    public static function stageFiles(string $sourceDir, string $targetDir): array
    {
        if (!is_dir($sourceDir)) {
            return [
                'done' => true,
                'progress' => 100,
                'imported' => 0,
                'skipped' => 0,
                'errors' => [],
                'message' => 'Keine Belegdateien vorhanden.',
            ];
        }

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Zielverzeichnis für Belege konnte nicht angelegt werden.');
        }

        $count = 0;
        $iterator = new DirectoryIterator($sourceDir);
        foreach ($iterator as $file) {
            if ($file->isDot() || !$file->isFile()) {
                continue;
            }
            $dest = $targetDir . '/' . $file->getFilename();
            if (!is_file($dest)) {
                copy($file->getPathname(), $dest);
            }
            $count++;
        }

        SettingsStore::set('install_voucher_import_pending', [
            'staged_at' => date('c'),
            'file_count' => $count,
            'path' => 'storage/vouchers/import-pending',
            'status' => 'todo',
            'note' => 'Beleg-Import-Verarbeitung folgt in einer späteren Version.',
        ]);

        return [
            'done' => true,
            'progress' => 100,
            'imported' => $count,
            'skipped' => 0,
            'errors' => [],
            'message' => $count > 0
                ? sprintf('%d Belegdateien zwischengespeichert (Verarbeitung folgt).', $count)
                : 'Keine Belegdateien hochgeladen.',
        ];
    }

    /**
     * @return array{done: bool, progress: int, imported: int, skipped: int, errors: list<string>, message: string}
     */
    public static function importBatch(string $stagingDir): array
    {
        $voucherDir = DG_ROOT . '/storage/vouchers/import-pending';
        $result = self::stageFiles($stagingDir, $voucherDir);

        return $result;
    }
}
