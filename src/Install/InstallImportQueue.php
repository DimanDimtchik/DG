<?php
declare(strict_types=1);

/** Verwaltet die Import-Warteschlange während der Installation. */
final class InstallImportQueue
{
    public const TYPE_CONTACTS = 'contacts';
    public const TYPE_EMPLOYEES = 'employees';
    public const TYPE_BOOKINGS = 'bookings';
    public const TYPE_ARTICLES = 'articles';
    public const TYPE_VOUCHERS = 'vouchers';

    /** @var array<string, array{label: string, description: string}> */
    public const TYPES = [
        self::TYPE_CONTACTS => [
            'label' => 'Kontakte',
            'description' => 'Kunden, Lieferanten — Excel oder Export aus Ihrem Programm',
        ],
        self::TYPE_EMPLOYEES => [
            'label' => 'Mitarbeiter',
            'description' => 'Personal — z. B. aus ShiftBase, Excel oder DATEV',
        ],
        self::TYPE_BOOKINGS => [
            'label' => 'Termine',
            'description' => 'Termine/Schichten — Excel oder Export aus Kalender/ShiftBase',
        ],
        self::TYPE_ARTICLES => [
            'label' => 'Artikel & Leistungen',
            'description' => 'Excel, CSV, XML, JSON oder PDF',
        ],
        self::TYPE_VOUCHERS => [
            'label' => 'Belege & Rechnungen',
            'description' => 'PDF oder Bilder — werden als Entwürfe angelegt (OCR im CRM)',
        ],
    ];

    public static function storageDir(): string
    {
        return DG_ROOT . '/storage/install-import';
    }

    public static function manifestPath(): string
    {
        return self::storageDir() . '/manifest.json';
    }

    public static function statePath(): string
    {
        return self::storageDir() . '/state.json';
    }

    public static function ensureStorageDir(): void
    {
        $dir = self::storageDir();
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Import-Verzeichnis konnte nicht angelegt werden.');
        }
    }

    /**
     * @param array<string, mixed> $selection
     * @param array<string, array{name?: string, tmp_name?: string, error?: int}> $uploads
     * @param array<string, string> $sources
     * @return list<array<string, mixed>>
     */
    public static function buildJobsFromUploads(array $selection, array $uploads, array $sources = []): array
    {
        self::ensureStorageDir();
        $jobs = [];

        foreach (self::TYPES as $type => $meta) {
            if (empty($selection[$type])) {
                continue;
            }

            $fileKey = $type === self::TYPE_VOUCHERS ? 'file_vouchers' : 'file_' . $type;
            if ($type === self::TYPE_VOUCHERS) {
                $job = self::buildVoucherJob($uploads[$fileKey] ?? null);
                if ($job !== null) {
                    $jobs[] = $job;
                }
                continue;
            }

            $upload = $uploads[$fileKey] ?? null;
            if (!is_array($upload)) {
                throw new InvalidArgumentException('Bitte laden Sie eine Datei für „' . $meta['label'] . '“ hoch.');
            }

            $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('Upload für „' . $meta['label'] . '“ fehlgeschlagen.');
            }

            $originalName = (string) ($upload['name'] ?? 'import.dat');
            $safeName = self::safeFilename($type, $originalName);
            $dest = self::storageDir() . '/' . $safeName;

            if (!move_uploaded_file((string) $upload['tmp_name'], $dest)) {
                throw new RuntimeException('Datei für „' . $meta['label'] . '“ konnte nicht gespeichert werden.');
            }

            $jobs[] = [
                'type' => $type,
                'label' => $meta['label'],
                'file' => $safeName,
                'original_name' => $originalName,
                'source' => InstallImportSourcePresets::normalize($sources[$type] ?? 'other'),
                'status' => 'pending',
                'progress' => 0,
                'imported' => 0,
                'skipped' => 0,
                'offset' => -1,
                'message' => '',
                'errors' => [],
            ];
        }

        return $jobs;
    }

    /**
     * @param list<array<string, mixed>> $jobs
     */
    public static function saveManifest(array $jobs): void
    {
        self::ensureStorageDir();
        file_put_contents(self::manifestPath(), json_encode([
            'created_at' => date('c'),
            'jobs' => $jobs,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array{jobs: list<array<string, mixed>>, current_index: int, phase: string}|null
     */
    public static function loadState(): ?array
    {
        if (!is_readable(self::statePath())) {
            if (!is_readable(self::manifestPath())) {
                return null;
            }
            $manifest = json_decode((string) file_get_contents(self::manifestPath()), true);
            if (!is_array($manifest) || empty($manifest['jobs'])) {
                return null;
            }

            return [
                'jobs' => $manifest['jobs'],
                'current_index' => 0,
                'phase' => 'pending',
            ];
        }

        $state = json_decode((string) file_get_contents(self::statePath()), true);

        return is_array($state) ? $state : null;
    }

    /**
     * @param array{jobs: list<array<string, mixed>>, current_index: int, phase: string} $state
     */
    public static function saveState(array $state): void
    {
        self::ensureStorageDir();
        file_put_contents(self::statePath(), json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public static function hasPendingJobs(): bool
    {
        $state = self::loadState();
        if ($state === null) {
            return false;
        }

        foreach ($state['jobs'] as $job) {
            if (($job['status'] ?? '') !== 'done') {
                return true;
            }
        }

        return false;
    }

    public static function overallProgress(array $state): int
    {
        $jobs = $state['jobs'] ?? [];
        if ($jobs === []) {
            return 100;
        }

        $sum = 0;
        foreach ($jobs as $job) {
            $sum += (int) ($job['progress'] ?? 0);
        }

        return (int) round($sum / count($jobs));
    }

    private static function safeFilename(string $type, string $originalName): string
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'dat';
        }

        return $type . '-' . date('Ymd-His') . '.' . preg_replace('/[^a-z0-9]/', '', $ext);
    }

    /**
     * @param array<string, mixed>|null $upload
     * @return array<string, mixed>|null
     */
    private static function buildVoucherJob(?array $upload): ?array
    {
        if ($upload === null) {
            throw new InvalidArgumentException('Bitte laden Sie mindestens eine Belegdatei hoch.');
        }

        $names = $upload['name'] ?? [];
        $tmpNames = $upload['tmp_name'] ?? [];
        $errors = $upload['error'] ?? [];
        if (!is_array($names)) {
            $names = [$names];
            $tmpNames = [$tmpNames];
            $errors = [$errors];
        }

        $targetDir = self::storageDir() . '/vouchers';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Beleg-Verzeichnis konnte nicht angelegt werden.');
        }

        $saved = 0;
        foreach ($names as $i => $originalName) {
            $error = (int) ($errors[$i] ?? UPLOAD_ERR_NO_FILE);
            if ($error !== UPLOAD_ERR_OK) {
                continue;
            }
            $tmp = (string) ($tmpNames[$i] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                continue;
            }
            $safeName = self::safeFilename('voucher', (string) $originalName);
            if (move_uploaded_file($tmp, $targetDir . '/' . $safeName)) {
                $saved++;
            }
        }

        if ($saved === 0) {
            throw new InvalidArgumentException('Keine gültigen Belegdateien hochgeladen.');
        }

        return [
            'type' => self::TYPE_VOUCHERS,
            'label' => self::TYPES[self::TYPE_VOUCHERS]['label'],
            'file' => 'vouchers',
            'original_name' => $saved . ' Datei(en)',
            'source' => 'other',
            'status' => 'pending',
            'progress' => 0,
            'imported' => 0,
            'skipped' => 0,
            'offset' => -1,
            'message' => '',
            'errors' => [],
        ];
    }

    public static function templateDownload(string $type): ?string
    {
        return match ($type) {
            self::TYPE_CONTACTS => InstallContactImporter::templateCsv(),
            self::TYPE_EMPLOYEES => InstallEmployeeImporter::templateCsv(),
            self::TYPE_BOOKINGS => InstallBookingImporter::templateCsv(),
            self::TYPE_ARTICLES => CalendarArticleImporter::templateCsv(),
            default => null,
        };
    }
}
