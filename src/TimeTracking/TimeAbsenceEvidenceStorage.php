<?php
declare(strict_types=1);

/** Nachweisdateien zu Abwesenheiten (Attest/Scan bei Krankheit und Sonderurlaub). */
final class TimeAbsenceEvidenceStorage
{
    private const MAX_BYTES = 10_485_760;
    private const MAX_FILES = 5;

    /** Typen mit optionalem Upload. */
    public const EVIDENCE_TYPES = ['sick', 'special_leave'];

    /** @var array<string, string> */
    private const MIME_MAP = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    public static function allowsEvidence(string $type): bool
    {
        return in_array($type, self::EVIDENCE_TYPES, true);
    }

    public static function baseDir(): string
    {
        return DG_ROOT . '/storage/time-absences';
    }

    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            $r = Database::pdo()->query("SHOW TABLES LIKE 'dg_time_absence_attachments'");

            return $r !== false && $r->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $filesField $_FILES['evidence'] o.ä.
     * @return int Anzahl gespeicherter Dateien
     */
    public static function storeUploads(
        int $absenceId,
        int $contactId,
        array $filesField,
        ?int $createdBy,
    ): int {
        if ($absenceId < 1 || $contactId < 1) {
            return 0;
        }
        MigrationRunner::runPending();
        if (!self::tableReady()) {
            throw new RuntimeException('Anhang-Tabelle fehlt — Migration 101 ausführen.');
        }

        $items = self::normalizeMultiUpload($filesField);
        if ($items === []) {
            return 0;
        }
        if (count($items) > self::MAX_FILES) {
            throw new InvalidArgumentException('Maximal ' . self::MAX_FILES . ' Nachweisdateien.');
        }

        $dir = self::absenceDir($contactId, $absenceId);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Upload-Verzeichnis konnte nicht erstellt werden.');
        }

        $saved = 0;
        $ins = Database::pdo()->prepare(
            'INSERT INTO dg_time_absence_attachments
                (absence_id, contact_id, original_name, stored_name, mime, size_bytes, created_by)
             VALUES
                (:aid, :contact, :orig, :stored, :mime, :size, :by)'
        );

        foreach ($items as $item) {
            $ext = self::extensionForUpload($item['name'], $item['tmp']);
            if ($ext === null) {
                throw new InvalidArgumentException(
                    'Ungültiger Dateityp („' . $item['name'] . '“). Erlaubt: JPG, PNG, WebP, PDF.'
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
            $orig = self::safeOriginalName($item['name']);
            $ins->execute([
                'aid' => $absenceId,
                'contact' => $contactId,
                'orig' => $orig,
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
     * @param list<int> $absenceIds
     * @return array<int, list<array{id: int, original_name: string, mime: string, size_bytes: int}>>
     */
    public static function mapForAbsences(array $absenceIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $absenceIds), static fn (int $i): bool => $i > 0)));
        if ($ids === [] || !self::tableReady()) {
            return [];
        }
        MigrationRunner::runPending();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT id, absence_id, original_name, mime, size_bytes
             FROM dg_time_absence_attachments
             WHERE absence_id IN ({$placeholders})
             ORDER BY id ASC"
        );
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $aid = (int) ($row['absence_id'] ?? 0);
            if ($aid < 1) {
                continue;
            }
            $out[$aid][] = [
                'id' => (int) ($row['id'] ?? 0),
                'original_name' => (string) ($row['original_name'] ?? ''),
                'mime' => (string) ($row['mime'] ?? ''),
                'size_bytes' => (int) ($row['size_bytes'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array{id: int, absence_id: int, contact_id: int, original_name: string, stored_name: string, mime: string}|null
     */
    public static function findById(int $attachmentId): ?array
    {
        if ($attachmentId < 1 || !self::tableReady()) {
            return null;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'SELECT id, absence_id, contact_id, original_name, stored_name, mime
             FROM dg_time_absence_attachments WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $attachmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'absence_id' => (int) ($row['absence_id'] ?? 0),
            'contact_id' => (int) ($row['contact_id'] ?? 0),
            'original_name' => (string) ($row['original_name'] ?? ''),
            'stored_name' => (string) ($row['stored_name'] ?? ''),
            'mime' => (string) ($row['mime'] ?? ''),
        ];
    }

    public static function absolutePath(array $attachment): string
    {
        $contactId = (int) ($attachment['contact_id'] ?? 0);
        $absenceId = (int) ($attachment['absence_id'] ?? 0);
        $stored = (string) ($attachment['stored_name'] ?? '');
        if ($contactId < 1 || $absenceId < 1 || $stored === '' || str_contains($stored, '..')) {
            return '';
        }

        return self::absenceDir($contactId, $absenceId) . DIRECTORY_SEPARATOR . $stored;
    }

    public static function sendDownload(User $user, int $attachmentId): void
    {
        $att = self::findById($attachmentId);
        if ($att === null) {
            throw new RuntimeException('Anhang nicht gefunden.');
        }
        $ownId = ContactRepository::findStaffContactIdForUser($user);
        $canTeam = TimeClockService::canViewTeam($user);
        if (!$canTeam && ($ownId === null || $ownId !== (int) $att['contact_id'])) {
            throw new RuntimeException('Keine Berechtigung für diesen Nachweis.');
        }
        $path = self::absolutePath($att);
        if ($path === '' || !is_file($path)) {
            throw new RuntimeException('Datei fehlt auf dem Server.');
        }
        $name = self::safeOriginalName($att['original_name'] !== '' ? $att['original_name'] : 'nachweis.bin');
        $mime = $att['mime'] !== '' ? $att['mime'] : 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    private static function absenceDir(int $contactId, int $absenceId): string
    {
        return self::baseDir()
            . DIRECTORY_SEPARATOR . $contactId
            . DIRECTORY_SEPARATOR . $absenceId;
    }

    /**
     * @param array<string, mixed> $filesField
     * @return list<array{name: string, tmp: string, size: int, type: string}>
     */
    private static function normalizeMultiUpload(array $filesField): array
    {
        if (!isset($filesField['tmp_name'])) {
            return [];
        }
        $names = $filesField['name'] ?? [];
        $tmps = $filesField['tmp_name'] ?? [];
        $sizes = $filesField['size'] ?? [];
        $types = $filesField['type'] ?? [];
        $errors = $filesField['error'] ?? [];

        if (!is_array($tmps)) {
            $tmps = [$tmps];
            $names = [$names];
            $sizes = [$sizes];
            $types = [$types];
            $errors = [$errors];
        }

        $out = [];
        foreach ($tmps as $i => $tmp) {
            $err = (int) ($errors[$i] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($err !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Upload-Fehler (Code ' . $err . ').');
            }
            $tmp = (string) $tmp;
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                continue;
            }
            $out[] = [
                'name' => (string) ($names[$i] ?? 'upload'),
                'tmp' => $tmp,
                'size' => (int) ($sizes[$i] ?? 0),
                'type' => (string) ($types[$i] ?? ''),
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
        if (!isset(self::MIME_MAP[$ext])) {
            return null;
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = (string) $finfo->file($tmpPath);
        $expected = self::MIME_MAP[$ext];
        if ($detected !== '' && $detected !== $expected) {
            if (!($ext === 'jpg' && in_array($detected, ['image/jpeg', 'image/pjpeg'], true))) {
                return null;
            }
        }

        return $ext;
    }

    private static function safeOriginalName(string $name): string
    {
        $name = basename(str_replace(["\0", '\\', '/'], '', $name));
        $name = trim($name);
        if ($name === '') {
            return 'nachweis.bin';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($name, 0, 200);
        }

        return substr($name, 0, 200);
    }
}
