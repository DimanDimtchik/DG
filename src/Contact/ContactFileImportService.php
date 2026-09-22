<?php
declare(strict_types=1);

/**
 * CSV/Excel-Kontaktimport im laufenden CRM — nutzt InstallContactImporter.
 */
final class ContactFileImportService
{
    private const MAX_BYTES = 5_242_880; // 5 MB
    private const MAX_BATCHES = 80; // ~3200 Zeilen bei BATCH_SIZE 40

    public static function isAllowed(?User $user): bool
    {
        if ($user === null || !MenuRegistry::canAccess($user, 'kontakte')) {
            return false;
        }
        if (!Database::isConfigured()) {
            return false;
        }

        return ContactAccessResolver::canEditContact($user);
    }

    /**
     * @param array<string, mixed> $file $_FILES[…]
     * @return array{
     *   imported: int,
     *   updated: int,
     *   duplicates: int,
     *   skipped: int,
     *   errors: list<string>,
     *   message: string
     * }
     */
    public static function importUpload(array $file, User $user, array $post): array
    {
        if (!self::isAllowed($user)) {
            throw new RuntimeException('Kein Recht zum Kontakt-Import.');
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Bitte eine Excel- (.xlsx) oder CSV-Datei hochladen.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $orig = (string) ($file['name'] ?? 'import.csv');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new InvalidArgumentException('Upload ungültig.');
        }
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('Datei zu groß (max. 5 MB).');
        }

        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, InstallImportSourcePresets::tabularExtensions(), true)) {
            throw new InvalidArgumentException('Nur Excel (.xlsx), CSV, XML oder JSON.');
        }

        $source = InstallImportSourcePresets::normalize((string) ($post['import_source'] ?? 'other'));
        $defaultRole = trim((string) ($post['default_role'] ?? 'kunde'));
        if ($defaultRole === '') {
            $defaultRole = 'kunde';
        }
        $forceRole = !empty($post['force_role']) ? $defaultRole : null;
        $createCalendar = !empty($post['create_calendar']);
        $areaId = max(0, (int) ($post['calendar_area_id'] ?? 0));
        $onDuplicate = (string) ($post['on_duplicate'] ?? 'update_empty');
        if (!in_array($onDuplicate, ['skip', 'update_empty', 'update_all'], true)) {
            $onDuplicate = 'update_empty';
        }

        $dir = DG_ROOT . '/storage/contact-import';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Import-Verzeichnis nicht anlegbar.');
        }
        $stored = $dir . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($tmp, $stored)) {
            throw new RuntimeException('Datei konnte nicht gespeichert werden.');
        }

        $seen = ['email' => [], 'login' => []];
        $options = [
            'default_role' => $defaultRole,
            'force_role' => $forceRole,
            'create_calendar' => $createCalendar,
            'calendar_area_id' => $areaId,
            'on_duplicate' => $onDuplicate,
            'seen' => $seen,
        ];

        $imported = 0;
        $updated = 0;
        $duplicates = 0;
        $skipped = 0;
        $errors = [];
        $offset = 0;
        $batches = 0;
        $done = false;

        try {
            do {
                $options['seen'] = $seen;
                $batch = InstallContactImporter::importBatch($stored, $offset, $source, $options);
                $imported += (int) ($batch['imported'] ?? 0);
                $updated += (int) ($batch['updated'] ?? 0);
                $duplicates += (int) ($batch['duplicates'] ?? 0);
                $skipped += (int) ($batch['skipped'] ?? 0);
                if (isset($batch['seen']) && is_array($batch['seen'])) {
                    $seen = $batch['seen'];
                }
                foreach ($batch['errors'] ?? [] as $err) {
                    if (count($errors) < 40) {
                        $errors[] = (string) $err;
                    }
                }
                $offset = (int) ($batch['next_offset'] ?? $offset);
                $batches++;
                $done = !empty($batch['done']);
            } while (!$done && $batches < self::MAX_BATCHES);

            if (!$done) {
                $errors[] = 'Import abgebrochen: Datei zu groß (Limit erreicht). Bitte in Teile splitten.';
            }
        } finally {
            @unlink($stored);
        }

        $parts = [];
        if ($imported > 0) {
            $parts[] = $imported . ' neu';
        }
        if ($updated > 0) {
            $parts[] = $updated . ' aktualisiert';
        }
        if ($duplicates > 0) {
            $parts[] = $duplicates . ' Duplikate';
        }
        $msg = 'Import: ' . ($parts !== [] ? implode(', ', $parts) : 'keine Änderungen') . '.';

        return [
            'imported' => $imported,
            'updated' => $updated,
            'duplicates' => $duplicates,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => $msg,
        ];
    }

    public static function sendTemplateDownload(): never
    {
        $csv = InstallContactImporter::templateCsv();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="kontakte-import-vorlage.csv"');
        echo $csv;
        exit;
    }
}
