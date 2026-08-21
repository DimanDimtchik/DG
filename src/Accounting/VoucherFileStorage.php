<?php
declare(strict_types=1);

/**
 * Speichert Beleg-Dateianhänge (Original-PDF/-Bild) je Beleg.
 * Ablage unter storage/vouchers/{voucherId}/, Metadaten in dg_voucher_files.
 */
final class VoucherFileStorage
{
    public const MAX_BYTES = 26_214_400; // 25 MB

    /** @var array<string, string> */
    private const MIME_MAP = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'xml' => 'application/xml',
    ];

    /**
     * Liefert das Basisverzeichnis für Belegdateien.
     *
     * @return string
     */
    public static function baseDir(): string
    {
        return DG_ROOT . '/storage/vouchers';
    }

    /**
     * Liefert erlaubte Dateiendungen.
     *
     * @return list<string>
     */
    public static function allowedExtensions(): array
    {
        return array_keys(self::MIME_MAP);
    }

    /**
     * Liefert das HTML-accept-Attribut für Uploads.
     *
     * @return string
     */
    public static function acceptAttribute(): string
    {
        return '.pdf,.jpg,.jpeg,.png,.webp,.gif,.xml';
    }

    /**
     * Verarbeitet einen $_FILES-Eintrag (multiple) und legt die Dateien am Beleg ab.
     *
     * @param int $voucherId Beleg-ID
     * @param array<string, mixed> $fileGroup z. B. $_FILES['voucher_files']
     * @param int|null $userId Benutzer-ID
     * @return int Anzahl gespeicherter Dateien
     */
    public static function processUploads(int $voucherId, array $fileGroup, ?int $userId = null): int
    {
        if ($voucherId < 1 || !Database::isConfigured()) {
            return 0;
        }
        MigrationRunner::runPending();

        $names = $fileGroup['name'] ?? null;
        $stored = 0;

        if (is_array($names)) {
            foreach ($names as $index => $originalName) {
                $error = (int) ($fileGroup['error'][$index] ?? UPLOAD_ERR_NO_FILE);
                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $file = [
                    'name' => (string) $originalName,
                    'type' => (string) ($fileGroup['type'][$index] ?? ''),
                    'tmp_name' => (string) ($fileGroup['tmp_name'][$index] ?? ''),
                    'error' => $error,
                    'size' => (int) ($fileGroup['size'][$index] ?? 0),
                ];
                self::storeOne($voucherId, $file, $userId);
                $stored++;
            }
        } elseif (isset($fileGroup['name'])) {
            if ((int) ($fileGroup['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                self::storeOne($voucherId, $fileGroup, $userId);
                $stored++;
            }
        }

        return $stored;
    }

    /**
     * Hängt eine bereits vorhandene Datei (z. B. Install-Import) an einen Beleg an.
     *
     * @return int Datei-ID
     */
    public static function attachFromPath(
        int $voucherId,
        string $absolutePath,
        string $originalName,
        ?int $userId = null,
        string $source = 'install_import'
    ): int {
        if ($voucherId < 1 || !Database::isConfigured()) {
            throw new InvalidArgumentException('Ungültige Beleg-ID.');
        }
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            throw new InvalidArgumentException('Quelldatei nicht lesbar.');
        }

        MigrationRunner::runPending();

        $size = (int) filesize($absolutePath);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('Datei zu groß (max. 25 MB).');
        }

        $original = $originalName !== '' ? $originalName : basename($absolutePath);
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($ext === '' || !isset(self::MIME_MAP[$ext])) {
            $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        }
        if (!isset(self::MIME_MAP[$ext])) {
            throw new InvalidArgumentException('Dateityp nicht erlaubt (PDF, JPG, PNG, WEBP, GIF, XML).');
        }

        $dir = self::baseDir() . '/' . $voucherId;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Upload-Verzeichnis konnte nicht erstellt werden.');
        }

        $storedName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $relative = 'vouchers/' . $voucherId . '/' . $storedName;
        $target = self::baseDir() . '/' . $voucherId . '/' . $storedName;

        if (!copy($absolutePath, $target)) {
            throw new RuntimeException('Datei konnte nicht gespeichert werden.');
        }
        @chmod($target, 0644);

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_voucher_files (voucher_id, stored_path, original_name, mime, size_bytes, source, uploaded_by)
             VALUES (:vid, :path, :name, :mime, :size, :source, :uid)'
        );
        $stmt->execute([
            'vid' => $voucherId,
            'path' => $relative,
            'name' => mb_substr($original, 0, 255),
            'mime' => self::MIME_MAP[$ext],
            'size' => $size,
            'source' => mb_substr($source !== '' ? $source : 'install_import', 0, 24),
            'uid' => $userId,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * Speichert eine einzelne Upload-Datei am Beleg.
     *
     * @param int $voucherId Beleg-ID
     * @param array<string, mixed> $file PHP-Upload-Eintrag
     * @param int|null $userId Benutzer-ID
     * @return void
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    private static function storeOne(int $voucherId, array $file, ?int $userId): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Upload fehlgeschlagen (Code ' . $error . ').');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('Datei zu groß (max. 25 MB).');
        }

        $original = (string) ($file['name'] ?? 'beleg');
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!isset(self::MIME_MAP[$ext])) {
            throw new InvalidArgumentException('Dateityp nicht erlaubt (PDF, JPG, PNG, WEBP, GIF, XML).');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Ungültiger Upload.');
        }

        self::attachFromPath($voucherId, $tmp, $original, $userId, 'upload');
        @unlink($tmp);
    }

    /**
     * Listet Dateianhänge eines Belegs.
     *
     * @param int $voucherId Beleg-ID
     * @return list<array<string, mixed>>
     */
    public static function listForVoucher(int $voucherId): array
    {
        if ($voucherId < 1 || !Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_voucher_files WHERE voucher_id = :vid ORDER BY id ASC'
        );
        $stmt->execute(['vid' => $voucherId]);

        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $mime = (string) ($row['mime'] ?? '');
            $out[] = [
                'id' => (int) $row['id'],
                'voucher_id' => (int) $row['voucher_id'],
                'original_name' => (string) ($row['original_name'] ?? ''),
                'mime' => $mime,
                'size_bytes' => (int) ($row['size_bytes'] ?? 0),
                'size_label' => self::formatSize((int) ($row['size_bytes'] ?? 0)),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'is_image' => str_starts_with($mime, 'image/'),
                'is_pdf' => $mime === 'application/pdf',
                'view_url' => '/app?page=buchhaltung-beleg-form&action=beleg-file&file=' . (int) $row['id'] . '&disp=inline',
                'download_url' => '/app?page=buchhaltung-beleg-form&action=beleg-file&file=' . (int) $row['id'] . '&disp=download',
            ];
        }

        return $out;
    }

    /**
     * Anzahl Dateien je Beleg-ID (für Listen-Icon).
     *
     * @param list<int> $voucherIds
     * @return array<int, int>
     */
    public static function countsForVouchers(array $voucherIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $voucherIds), static fn (int $v): bool => $v > 0)));
        if ($ids === [] || !Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare(
            'SELECT voucher_id, COUNT(*) AS n FROM dg_voucher_files WHERE voucher_id IN (' . $placeholders . ') GROUP BY voucher_id'
        );
        $stmt->execute($ids);

        $counts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $counts[(int) $row['voucher_id']] = (int) $row['n'];
        }

        return $counts;
    }

    /**
     * Löst eine Datei für den Download auf.
     *
     * @param int $fileId Datei-ID
     * @return array{path: string, mime: string, name: string, voucher_id: int}|null
     */
    public static function resolveForDownload(int $fileId): ?array
    {
        if ($fileId < 1 || !Database::isConfigured()) {
            return null;
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare('SELECT * FROM dg_voucher_files WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $fileId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $absolute = self::resolveAbsolute((string) $row['stored_path']);
        if ($absolute === null) {
            return null;
        }

        return [
            'path' => $absolute,
            'mime' => (string) ($row['mime'] ?? 'application/octet-stream'),
            'name' => (string) ($row['original_name'] ?? basename($absolute)),
            'voucher_id' => (int) $row['voucher_id'],
        ];
    }

    /**
     * Löscht eine Datei.
     *
     * @param int $fileId Datei-ID
     * @return void
     */
    public static function deleteFile(int $fileId): void
    {
        if ($fileId < 1 || !Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare('SELECT stored_path FROM dg_voucher_files WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $fileId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $absolute = self::resolveAbsolute((string) $row['stored_path']);
            if ($absolute !== null) {
                @unlink($absolute);
            }
            Database::pdo()->prepare('DELETE FROM dg_voucher_files WHERE id = :id')->execute(['id' => $fileId]);
        }
    }

    /**
     * Löscht alle Dateien eines Belegs.
     *
     * @param int $voucherId Beleg-ID
     * @return void
     */
    public static function deleteAllForVoucher(int $voucherId): void
    {
        if ($voucherId < 1 || !Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare('SELECT stored_path FROM dg_voucher_files WHERE voucher_id = :vid');
        $stmt->execute(['vid' => $voucherId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $absolute = self::resolveAbsolute((string) $row['stored_path']);
            if ($absolute !== null) {
                @unlink($absolute);
            }
        }
        $dir = self::baseDir() . '/' . $voucherId;
        if (is_dir($dir)) {
            @rmdir($dir);
        }
        // DB-Zeilen werden per ON DELETE CASCADE mit dem Beleg entfernt;
        // beim reinen Datei-Löschen (ohne Beleg-Löschung) hier zusätzlich:
        Database::pdo()->prepare('DELETE FROM dg_voucher_files WHERE voucher_id = :vid')->execute(['vid' => $voucherId]);
    }

    /**
     * Löst einen relativen Speicherpfad in einen absoluten Dateipfad auf.
     *
     * @param string $relativePath Relativer Pfad unter storage/
     * @return string|null
     */
    private static function resolveAbsolute(string $relativePath): ?string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (!preg_match('#^vouchers/\d+/[A-Za-z0-9_.-]+$#', $relativePath)) {
            return null;
        }
        $absolute = DG_ROOT . '/storage/' . $relativePath;

        return is_file($absolute) ? $absolute : null;
    }

    /**
     * Formatiert eine Dateigröße lesbar.
     *
     * @param int $bytes Größe in Bytes
     * @return string
     */
    private static function formatSize(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return number_format($bytes / 1_048_576, 1, ',', '.') . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0, ',', '.') . ' KB';
        }

        return $bytes . ' B';
    }
}

