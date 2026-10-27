<?php
declare(strict_types=1);

/** Dateianhänge zu Reklamationen (Fotos, PDFs). Ablage: storage/complaints/{id}/ */
final class ComplaintFileStorage
{
    private const MAX_BYTES = 10_485_760;
    private const MAX_FILES = 10;

    /** @var array<string, string> */
    private const MIME_MAP = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];

    public static function baseDir(): string
    {
        return DG_ROOT . '/storage/complaints';
    }

    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            $r = Database::pdo()->query("SHOW TABLES LIKE 'dg_complaint_attachments'");

            return $r !== false && $r->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $filesField $_FILES['complaint_files']
     * @return int Anzahl gespeicherter Dateien
     */
    public static function storeUploads(int $complaintId, array $filesField, ?int $createdBy): int
    {
        if ($complaintId < 1) {
            return 0;
        }
        MigrationRunner::runPending();
        if (!self::tableReady()) {
            throw new RuntimeException('Anhang-Tabelle fehlt — Migration 120 ausführen.');
        }

        $items = self::normalizeMultiUpload($filesField);
        if ($items === []) {
            return 0;
        }
        if (count($items) > self::MAX_FILES) {
            throw new InvalidArgumentException('Maximal ' . self::MAX_FILES . ' Dateien pro Upload.');
        }

        $dir = self::complaintDir($complaintId);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Upload-Verzeichnis konnte nicht erstellt werden.');
        }

        $saved = 0;
        $ins = Database::pdo()->prepare(
            'INSERT INTO dg_complaint_attachments
                (complaint_id, original_name, stored_name, mime, size_bytes, created_by)
             VALUES
                (:cid, :orig, :stored, :mime, :size, :by)'
        );

        foreach ($items as $item) {
            $ext = self::extensionForUpload($item['name'], $item['tmp']);
            if ($ext === null) {
                throw new InvalidArgumentException(
                    'Ungültiger Dateityp („' . $item['name'] . '“). Erlaubt: JPG, PNG, WebP, GIF, PDF.'
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
                throw new RuntimeException('Upload von „' . $item['name'] . '“ fehlgeschlagen.');
            }
            @chmod($dest, 0640);
            $mime = self::MIME_MAP[$ext] ?? (string) ($item['type'] ?: 'application/octet-stream');
            $ins->execute([
                'cid' => $complaintId,
                'orig' => self::safeOriginalName($item['name']),
                'stored' => $stored,
                'mime' => $mime,
                'size' => $item['size'],
                'by' => ($createdBy !== null && $createdBy > 0) ? $createdBy : null,
            ]);
            $saved++;
        }

        return $saved;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForComplaint(int $complaintId): array
    {
        if ($complaintId < 1 || !Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();
        if (!self::tableReady()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT id, complaint_id, original_name, stored_name, mime, size_bytes, created_at
             FROM dg_complaint_attachments
             WHERE complaint_id = :cid
             ORDER BY id ASC'
        );
        $stmt->execute(['cid' => $complaintId]);
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
            'SELECT * FROM dg_complaint_attachments WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $fileId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public static function absolutePath(array $row): string
    {
        $cid = (int) ($row['complaint_id'] ?? 0);
        $stored = (string) ($row['stored_name'] ?? '');

        return self::complaintDir($cid) . DIRECTORY_SEPARATOR . $stored;
    }

    public static function delete(int $fileId): bool
    {
        $row = self::find($fileId);
        if ($row === null) {
            return false;
        }
        $path = self::absolutePath($row);
        if (is_file($path)) {
            @unlink($path);
        }
        Database::pdo()->prepare('DELETE FROM dg_complaint_attachments WHERE id = :id')->execute(['id' => $fileId]);

        return true;
    }

    private static function complaintDir(int $complaintId): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . $complaintId;
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
