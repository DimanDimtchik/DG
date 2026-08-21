<?php
declare(strict_types=1);

/**
 * Belege/Rechnungen beim Installationsimport:
 * legt Entwürfe an und hängt die Dateien an (OCR erfolgt später im Browser).
 */
final class InstallVoucherImporter
{
    private const BATCH_SIZE = 8;

    /** @var list<string> */
    private const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'xml'];

    /**
     * @return array{done: bool, progress: int, imported: int, skipped: int, errors: list<string>, message: string, next_offset?: int}
     */
    public static function importBatch(string $stagingDir, int $offset = -1): array
    {
        $files = self::listImportFiles($stagingDir);
        $pending = SettingsStore::get('install_voucher_import_pending', []);
        if (!is_array($pending)) {
            $pending = [];
        }

        $alreadyProcessed = (int) ($pending['processed'] ?? 0);
        $remaining = count($files);

        // Erster Lauf: Gesamtzahl festhalten
        if ($offset < 0 && $alreadyProcessed === 0) {
            $total = $remaining;
        } else {
            $total = max((int) ($pending['file_count'] ?? 0), $alreadyProcessed + $remaining);
        }

        if ($total === 0) {
            self::updatePendingSettings(0, 0, [], [], 'done', 'Keine Belegdateien hochgeladen.');

            return [
                'done' => true,
                'progress' => 100,
                'imported' => 0,
                'skipped' => 0,
                'errors' => [],
                'message' => 'Keine Belegdateien hochgeladen.',
                'next_offset' => max(-1, $offset),
            ];
        }

        if ($remaining === 0) {
            $ids = array_map('intval', is_array($pending['voucher_ids'] ?? null) ? $pending['voucher_ids'] : []);
            self::updatePendingSettings(
                $total,
                $alreadyProcessed,
                $ids,
                is_array($pending['errors'] ?? null) ? $pending['errors'] : [],
                'done',
                sprintf('%d Beleg-Entwürfe angelegt — bitte unter Belege prüfen.', count($ids))
            );

            return [
                'done' => true,
                'progress' => 100,
                'imported' => 0,
                'skipped' => 0,
                'errors' => [],
                'message' => sprintf('%d Beleg-Entwürfe angelegt — bitte unter Belege prüfen.', count($ids)),
                'next_offset' => $alreadyProcessed - 1,
            ];
        }

        $batch = array_slice($files, 0, self::BATCH_SIZE);
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $voucherIds = [];

        foreach ($batch as $path) {
            $name = basename($path);
            try {
                $voucherId = VoucherRepository::createDraft([
                    'voucher_type' => 'expense',
                    'supplier_name' => self::labelFromFilename($name),
                    'description' => 'Import aus Installation: ' . $name,
                    'notes' => "Belegdatei aus Installationsimport.\nDateiname: {$name}\nBitte Kontakt, Betrag und Konto ergänzen (OCR im Belegformular).",
                ]);
                VoucherFileStorage::attachFromPath($voucherId, $path, $name, null, 'install_import');
                $voucherIds[] = $voucherId;
                @unlink($path);
                $imported++;
            } catch (Throwable $e) {
                $skipped++;
                $errors[] = $name . ': ' . $e->getMessage();
                // Fehlerhafte Datei beiseite legen, damit der Batch nicht hängen bleibt
                $failedDir = rtrim($stagingDir, '/\\') . '/failed';
                if (!is_dir($failedDir)) {
                    @mkdir($failedDir, 0755, true);
                }
                @rename($path, $failedDir . '/' . $name);
            }
        }

        $processed = $alreadyProcessed + $imported + $skipped;
        $allIds = array_values(array_unique(array_merge(
            array_map('intval', is_array($pending['voucher_ids'] ?? null) ? $pending['voucher_ids'] : []),
            $voucherIds
        )));
        $allErrors = array_merge(
            is_array($pending['errors'] ?? null) ? $pending['errors'] : [],
            $errors
        );

        $stillRemaining = count(self::listImportFiles($stagingDir));
        $done = $stillRemaining === 0;
        $progress = $total > 0 ? (int) min(100, round(($processed / $total) * 100)) : 100;

        self::updatePendingSettings(
            $total,
            $processed,
            $allIds,
            array_values(array_slice($allErrors, -20)),
            $done ? ($allIds === [] && $allErrors !== [] ? 'partial' : 'done') : 'processing',
            $done
                ? sprintf('%d Beleg-Entwürfe angelegt — bitte unter Belege prüfen und vervollständigen.', count($allIds))
                : sprintf('Belege werden importiert (%d/%d) …', $processed, $total)
        );

        return [
            'done' => $done,
            'progress' => $progress,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => $done
                ? sprintf('%d Beleg-Entwürfe angelegt — bitte unter Belege prüfen.', count($allIds))
                : sprintf('Belege %d von %d …', $processed, $total),
            'next_offset' => $processed - 1,
        ];
    }

    /**
     * @return list<string>
     */
    private static function listImportFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new DirectoryIterator($dir);
        foreach ($iterator as $file) {
            if ($file->isDot() || !$file->isFile()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, self::ALLOWED_EXT, true)) {
                continue;
            }
            $files[] = $file->getPathname();
        }

        sort($files, SORT_STRING);

        return $files;
    }

    private static function labelFromFilename(string $name): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = preg_replace('/[_\-]+/', ' ', $base) ?? $base;
        $base = trim($base);

        return $base !== '' ? mb_substr($base, 0, 191) : 'Import (offen)';
    }

    /**
     * @param list<int> $voucherIds
     * @param list<string> $errors
     */
    private static function updatePendingSettings(
        int $fileCount,
        int $processed,
        array $voucherIds,
        array $errors,
        string $status,
        string $note
    ): void {
        SettingsStore::set('install_voucher_import_pending', [
            'staged_at' => date('c'),
            'path' => 'storage/install-import/vouchers',
            'status' => $status,
            'file_count' => $fileCount,
            'processed' => $processed,
            'voucher_ids' => array_values(array_map('intval', $voucherIds)),
            'errors' => array_values($errors),
            'note' => $note,
        ]);
    }
}
