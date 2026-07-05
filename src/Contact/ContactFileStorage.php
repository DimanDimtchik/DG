<?php
declare(strict_types=1);

final class ContactFileStorage
{
    private const MAX_BYTES = 10_485_760;

    /** @var array<string, string> */
    private const MIME_MAP = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    public static function baseDir(): string
    {
        return DG_ROOT . '/storage/contacts';
    }

    /** @return array<string, array<string, string>|list<array<string, string>>> */
    public static function emptyFiles(): array
    {
        $files = [];
        foreach (array_keys(EmployeeData::allDocumentTypes()) as $type) {
            $files[$type] = self::isMultiType($type) ? [] : [];
        }

        return $files;
    }

    public static function isMultiType(string $type): bool
    {
        return isset(EmployeeData::multiDocumentTypes()[$type]);
    }

    /** @param mixed $raw */
    public static function normalizeFiles(mixed $raw): array
    {
        if (!is_array($raw)) {
            return self::emptyFiles();
        }

        $out = self::emptyFiles();
        foreach (EmployeeData::documentTypes() + EmployeeData::disabilityDocumentTypes() as $type => $label) {
            $entry = $raw[$type] ?? [];
            if (!is_array($entry) || empty($entry['path'])) {
                continue;
            }
            $out[$type] = self::normalizeSingleEntry($entry);
        }

        foreach (EmployeeData::multiDocumentTypes() as $type => $label) {
            $entries = $raw[$type] ?? [];
            if (!is_array($entries)) {
                continue;
            }
            $list = [];
            foreach ($entries as $entry) {
                if (!is_array($entry) || empty($entry['path'])) {
                    continue;
                }
                $list[] = self::normalizeSingleEntry($entry);
            }
            $out[$type] = $list;
        }

        return $out;
    }

    /** @param array<string, mixed> $entry */
    private static function normalizeSingleEntry(array $entry): array
    {
        return [
            'path' => (string) $entry['path'],
            'original_name' => (string) ($entry['original_name'] ?? basename((string) $entry['path'])),
            'uploaded_at' => (string) ($entry['uploaded_at'] ?? ''),
            'mime' => (string) ($entry['mime'] ?? ''),
        ];
    }

    /**
     * @param array<string, array<string, string>|list<array<string, string>>> $existing
     * @param array<string, mixed> $uploads from $_FILES['employee_files']
     * @return array<string, array<string, string>|list<array<string, string>>>
     */
    public static function processUploads(int $contactId, array $uploads, array $existing): array
    {
        $files = self::normalizeFiles($existing);
        $dir = self::contactDir($contactId);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Upload-Verzeichnis konnte nicht erstellt werden.');
        }

        foreach (EmployeeData::documentTypes() + EmployeeData::disabilityDocumentTypes() as $type => $label) {
            if (!isset($uploads[$type]) || !is_array($uploads[$type])) {
                continue;
            }
            $files[$type] = self::processSingleUpload($contactId, $type, $uploads[$type], $label, $files[$type] ?? []);
        }

        foreach (EmployeeData::multiDocumentTypes() as $type => $label) {
            if (!isset($uploads[$type]) || !is_array($uploads[$type])) {
                continue;
            }
            $existingList = is_array($files[$type] ?? null) ? $files[$type] : [];
            $files[$type] = self::processMultiUpload($contactId, $type, $uploads[$type], $label, $existingList);
        }

        return $files;
    }

    /**
     * @param array<string, mixed> $file
     * @param array<string, string>|list<array<string, string>> $existingEntry
     * @return array<string, string>
     */
    private static function processSingleUpload(int $contactId, string $type, array $file, string $label, array $existingEntry): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return is_array($existingEntry) && isset($existingEntry['path']) ? $existingEntry : [];
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Upload fehlgeschlagen: ' . $label);
        }

        $stored = self::storeUploadedFile($contactId, $type, $file, $label);
        if (!empty($existingEntry['path'])) {
            self::deletePath((string) $existingEntry['path']);
        }

        return $stored;
    }

    /**
     * @param array<string, mixed> $fileGroup
     * @param list<array<string, string>> $existingList
     * @return list<array<string, string>>
     */
    private static function processMultiUpload(int $contactId, string $type, array $fileGroup, string $label, array $existingList): array
    {
        $names = $fileGroup['name'] ?? null;
        if (!is_array($names)) {
            return $existingList;
        }

        foreach ($names as $index => $originalName) {
            $error = (int) ($fileGroup['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $file = [
                'name' => $originalName,
                'type' => $fileGroup['type'][$index] ?? '',
                'tmp_name' => $fileGroup['tmp_name'][$index] ?? '',
                'error' => $error,
                'size' => $fileGroup['size'][$index] ?? 0,
            ];

            if ($error !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('Upload fehlgeschlagen: ' . $label);
            }

            $existingList[] = self::storeUploadedFile($contactId, $type, $file, $label);
        }

        return $existingList;
    }

    /** @param array<string, mixed> $file */
    private static function storeUploadedFile(int $contactId, string $type, array $file, string $label): array
    {
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('Datei zu groß (max. 10 MB): ' . $label);
        }

        $original = (string) ($file['name'] ?? 'upload');
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!isset(self::MIME_MAP[$ext])) {
            throw new InvalidArgumentException('Dateityp nicht erlaubt (PDF, JPG, PNG, WEBP): ' . $label);
        }

        $storedName = $type . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $relative = 'contacts/' . $contactId . '/' . $storedName;
        $target = self::baseDir() . '/' . $contactId . '/' . $storedName;

        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            throw new RuntimeException('Datei konnte nicht gespeichert werden: ' . $label);
        }

        return [
            'path' => $relative,
            'original_name' => $original,
            'uploaded_at' => date('c'),
            'mime' => self::MIME_MAP[$ext],
        ];
    }

    public static function resolveAbsolute(string $relativePath): ?string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (!preg_match('#^contacts/\d+/[a-z0-9_.-]+$#i', $relativePath)) {
            return null;
        }
        $absolute = DG_ROOT . '/storage/' . $relativePath;
        if (!is_file($absolute)) {
            return null;
        }

        return $absolute;
    }

    /** @param array<string, array<string, string>|list<array<string, string>>> $files */
    public static function encodeFiles(array $files): string
    {
        $out = [];
        foreach ($files as $type => $entry) {
            if (self::isMultiType($type)) {
                if (is_array($entry) && $entry !== [] && !isset($entry['path'])) {
                    $out[$type] = $entry;
                }
                continue;
            }
            if (is_array($entry) && !empty($entry['path'])) {
                $out[$type] = $entry;
            }
        }

        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string, array<string, string>|list<array<string, string>>> $files */
    public static function deleteAll(array $files): void
    {
        foreach ($files as $type => $entry) {
            if (self::isMultiType($type)) {
                if (!is_array($entry)) {
                    continue;
                }
                foreach ($entry as $item) {
                    if (!empty($item['path'])) {
                        self::deletePath((string) $item['path']);
                    }
                }
                continue;
            }
            if (is_array($entry) && !empty($entry['path'])) {
                self::deletePath((string) $entry['path']);
            }
        }
    }

    private static function contactDir(int $contactId): string
    {
        return self::baseDir() . '/' . $contactId;
    }

    private static function deletePath(string $relativePath): void
    {
        $absolute = self::resolveAbsolute($relativePath);
        if ($absolute !== null) {
            @unlink($absolute);
        }
    }
}
