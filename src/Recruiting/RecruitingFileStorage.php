<?php
declare(strict_types=1);

/** Bewerbungsunterlagen. Ablage: storage/recruiting/{application_id}/ */
final class RecruitingFileStorage
{
    private const MAX_BYTES = 10_485_760;
    private const MAX_FILES = 10;

    public const KIND_CV = 'cv';
    public const KIND_COVER = 'cover_letter';
    public const KIND_CERTIFICATE = 'certificate';
    public const KIND_OTHER = 'other';

    /** @var array<string, string> */
    private const MIME_MAP = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public static function baseDir(): string
    {
        return DG_ROOT . '/storage/recruiting';
    }

    /**
     * @return array<string, string>
     */
    public static function kindOptions(): array
    {
        return [
            self::KIND_CV => 'Lebenslauf',
            self::KIND_COVER => 'Anschreiben',
            self::KIND_CERTIFICATE => 'Zeugnis / Nachweis',
            self::KIND_OTHER => 'Sonstiges',
        ];
    }

    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            Database::pdo()->query('SELECT 1 FROM dg_job_application_files LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function sanitizeKind(string $kind): string
    {
        $opts = self::kindOptions();

        return isset($opts[$kind]) ? $kind : self::KIND_OTHER;
    }

    /**
     * @param array<string, mixed> $filesField $_FILES['recruiting_files']
     */
    public static function storeUploads(int $applicationId, array $filesField, ?int $createdBy, string $kind = self::KIND_OTHER): int
    {
        if ($applicationId < 1) {
            return 0;
        }
        MigrationRunner::runPending();
        if (!self::tableReady()) {
            throw new RuntimeException('Datei-Tabelle fehlt — Migration 136 ausführen.');
        }
        if (JobApplicationRepository::find($applicationId) === null) {
            throw new InvalidArgumentException('Bewerbung nicht gefunden.');
        }

        $items = self::normalizeMultiUpload($filesField);
        if ($items === []) {
            return 0;
        }
        if (count($items) > self::MAX_FILES) {
            throw new InvalidArgumentException('Maximal ' . self::MAX_FILES . ' Dateien pro Upload.');
        }

        $kind = self::sanitizeKind($kind);
        $dir = self::applicationDir($applicationId);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Upload-Verzeichnis konnte nicht erstellt werden.');
        }

        $saved = 0;
        $ins = Database::pdo()->prepare(
            'INSERT INTO dg_job_application_files
                (application_id, file_kind, original_name, stored_name, mime, size_bytes, created_by)
             VALUES
                (:aid, :kind, :orig, :stored, :mime, :size, :by)'
        );

        foreach ($items as $item) {
            $ext = self::extensionForUpload($item['name'], $item['tmp']);
            if ($ext === null) {
                throw new InvalidArgumentException(
                    'Ungültiger Dateityp („' . $item['name'] . '“). Erlaubt: PDF, JPG, PNG, WebP, DOC, DOCX.'
                );
            }
            if ($item['size'] < 1 || $item['size'] > self::MAX_BYTES) {
                throw new InvalidArgumentException(
                    'Datei „' . $item['name'] . '“: Größe 1 Byte–10 MB.'
                );
            }
            $stored = bin2hex(random_bytes(16)) . '.' . $ext;
            $dest = $dir . DIRECTORY_SEPARATOR . $stored;
            if (!move_uploaded_file($item['tmp'], $dest)) {
                // Selftest / non-HTTP: rename copy
                if (!is_uploaded_file($item['tmp']) && is_file($item['tmp'])) {
                    if (!@copy($item['tmp'], $dest)) {
                        throw new RuntimeException('Upload von „' . $item['name'] . '“ fehlgeschlagen.');
                    }
                } else {
                    throw new RuntimeException('Upload von „' . $item['name'] . '“ fehlgeschlagen.');
                }
            }
            @chmod($dest, 0640);
            $mime = self::MIME_MAP[$ext] ?? (string) ($item['type'] ?: 'application/octet-stream');
            $ins->execute([
                'aid' => $applicationId,
                'kind' => $kind,
                'orig' => self::safeOriginalName($item['name']),
                'stored' => $stored,
                'mime' => $mime,
                'size' => $item['size'],
                'by' => ($createdBy !== null && $createdBy > 0) ? $createdBy : null,
            ]);
            $saved++;
        }

        if ($saved > 0) {
            JobApplicationRepository::addEvent(
                $applicationId,
                'file',
                null,
                null,
                $saved === 1 ? '1 Datei hochgeladen' : $saved . ' Dateien hochgeladen',
                $createdBy
            );
        }

        return $saved;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForApplication(int $applicationId): array
    {
        if ($applicationId < 1 || !Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();
        if (!self::tableReady()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT id, application_id, file_kind, original_name, stored_name, mime, size_bytes, created_at
             FROM dg_job_application_files
             WHERE application_id = :aid
             ORDER BY id ASC'
        );
        $stmt->execute(['aid' => $applicationId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $fileId): ?array
    {
        if ($fileId < 1 || !Database::isConfigured() || !self::tableReady()) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_job_application_files WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $fileId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public static function absolutePath(array $row): string
    {
        $aid = (int) ($row['application_id'] ?? 0);
        $stored = (string) ($row['stored_name'] ?? '');

        return self::applicationDir($aid) . DIRECTORY_SEPARATOR . $stored;
    }

    public static function delete(int $fileId, ?int $userId = null): bool
    {
        $row = self::find($fileId);
        if ($row === null) {
            return false;
        }
        $path = self::absolutePath($row);
        if (is_file($path)) {
            @unlink($path);
        }
        Database::pdo()->prepare('DELETE FROM dg_job_application_files WHERE id = :id')->execute(['id' => $fileId]);
        JobApplicationRepository::addEvent(
            (int) ($row['application_id'] ?? 0),
            'file',
            null,
            null,
            'Anhang gelöscht: ' . (string) ($row['original_name'] ?? ''),
            $userId
        );

        return true;
    }

    /** Alle Dateien einer Bewerbung (DB + Disk + Ordner). */
    public static function deleteAllForApplication(int $applicationId): int
    {
        if ($applicationId < 1 || !self::tableReady()) {
            return 0;
        }
        $files = self::listForApplication($applicationId);
        $n = 0;
        foreach ($files as $file) {
            $path = self::absolutePath($file);
            if (is_file($path)) {
                @unlink($path);
            }
            $n++;
        }
        Database::pdo()->prepare(
            'DELETE FROM dg_job_application_files WHERE application_id = :aid'
        )->execute(['aid' => $applicationId]);

        $dir = self::applicationDir($applicationId);
        if (is_dir($dir)) {
            @rmdir($dir);
        }

        return $n;
    }

    private static function applicationDir(int $applicationId): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . $applicationId;
    }

    /**
     * @param array<string, mixed> $filesField
     * @return list<array{name: string, tmp: string, size: int, type: string}>
     */
    private static function normalizeMultiUpload(array $filesField): array
    {
        $names = $filesField['name'] ?? null;
        $out = [];
        if (is_array($names)) {
            foreach ($names as $i => $name) {
                $err = (int) ($filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                if ($err === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($err !== UPLOAD_ERR_OK) {
                    throw new InvalidArgumentException('Upload fehlgeschlagen („' . (string) $name . '“).');
                }
                $out[] = [
                    'name' => (string) $name,
                    'tmp' => (string) ($filesField['tmp_name'][$i] ?? ''),
                    'size' => (int) ($filesField['size'][$i] ?? 0),
                    'type' => (string) ($filesField['type'][$i] ?? ''),
                ];
            }
        } elseif (isset($filesField['name'])) {
            $err = (int) ($filesField['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE) {
                return [];
            }
            if ($err !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('Upload fehlgeschlagen.');
            }
            $out[] = [
                'name' => (string) $filesField['name'],
                'tmp' => (string) ($filesField['tmp_name'] ?? ''),
                'size' => (int) ($filesField['size'] ?? 0),
                'type' => (string) ($filesField['type'] ?? ''),
            ];
        }

        return $out;
    }

    private static function extensionForUpload(string $originalName, string $tmpPath): ?string
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if ($ext !== '' && isset(self::MIME_MAP[$ext])) {
            return $ext;
        }
        if (!is_file($tmpPath)) {
            return null;
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = (string) $finfo->file($tmpPath);
        foreach (self::MIME_MAP as $e => $mime) {
            if ($mime === $detected) {
                return $e === 'jpeg' ? 'jpg' : $e;
            }
        }

        return null;
    }

    private static function safeOriginalName(string $name): string
    {
        $base = basename(str_replace(["\0", '\\'], '', $name));
        $base = preg_replace('/[^\p{L}\p{N}\.\-_ ()]+/u', '_', $base) ?? 'datei';

        return mb_substr($base !== '' ? $base : 'datei', 0, 255);
    }
}
