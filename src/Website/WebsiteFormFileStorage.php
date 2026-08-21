<?php
declare(strict_types=1);

/**
 * File uploads for website form submissions (under storage/website-forms/).
 */
final class WebsiteFormFileStorage
{
    private const MAX_BYTES_DEFAULT = 5_242_880; // 5 MB

    /** @var array<string, string> */
    private const MIME_MAP = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public static function baseDir(): string
    {
        return DG_ROOT . '/storage/website-forms';
    }

    public static function submissionDir(int $formId, int $submissionId): string
    {
        return self::baseDir() . '/' . $formId . '/' . $submissionId;
    }

    /**
     * Store one uploaded file; returns metadata for files_json.
     *
     * @param array<string, mixed> $file $_FILES entry
     * @return array{field: string, original_name: string, stored_name: string, mime: string, size: int, path: string}
     */
    public static function storeUpload(int $formId, int $submissionId, string $fieldName, array $file, int $maxMb = 5): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('Keine Datei hochgeladen.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Upload fehlgeschlagen.');
        }

        $maxBytes = max(1, $maxMb) * 1_048_576;
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            throw new InvalidArgumentException('Datei zu groß (max. ' . $maxMb . ' MB).');
        }

        $original = (string) ($file['name'] ?? 'upload');
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!isset(self::MIME_MAP[$ext])) {
            throw new InvalidArgumentException('Dateityp nicht erlaubt.');
        }

        $dir = self::submissionDir($formId, $submissionId);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Upload-Verzeichnis konnte nicht angelegt werden.');
        }

        $stored = bin2hex(random_bytes(8)) . '.' . $ext;
        $target = $dir . '/' . $stored;
        if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $target)) {
            throw new RuntimeException('Datei konnte nicht gespeichert werden.');
        }
        @chmod($target, 0644);

        return [
            'field' => $fieldName,
            'original_name' => $original,
            'stored_name' => $stored,
            'mime' => self::MIME_MAP[$ext],
            'size' => $size,
            'path' => $formId . '/' . $submissionId . '/' . $stored,
        ];
    }

    public static function absolutePath(string $relativePath): ?string
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        $relativePath = ltrim($relativePath, '/');
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return null;
        }
        $abs = self::baseDir() . '/' . $relativePath;
        if (!is_file($abs)) {
            return null;
        }

        return $abs;
    }

    public static function deleteSubmissionDir(int $formId, int $submissionId): void
    {
        $dir = self::submissionDir($formId, $submissionId);
        self::rmTree($dir);
    }

    public static function deleteFormDir(int $formId): void
    {
        self::rmTree(self::baseDir() . '/' . $formId);
    }

    private static function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }
}
