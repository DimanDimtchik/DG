<?php
declare(strict_types=1);

/**
 * Unterschriften-Historie (dg_user_signatures) — ES1c.
 * Wechsel = valid_to schließen + neue Zeile. Kein Löschen.
 */
final class UserSignatureStore
{
    public const SOURCES = ['upload', 'canvas', 'app', 'import'];

    /**
     * ensureTable via pending migrations (idempotent).
     */
    public static function ensureTable(): void
    {
        if (!Database::isConfigured()) {
            return;
        }

        MigrationRunner::runPending();
    }

    /**
     * Chronologisch neueste zuerst (Anzeige Kontakt-Tabelle).
     *
     * @return list<array<string, mixed>>
     */
    public static function listForContact(int $contactId): array
    {
        self::ensureTable();
        if (!Database::isConfigured() || $contactId <= 0) {
            return [];
        }

        $stmt = Database::pdo()->prepare(
            'SELECT id, user_id, contact_id, media_id, source, source_ref,
                    valid_from, valid_to, created_at, created_by_user_id
             FROM dg_user_signatures
             WHERE contact_id = :contact_id
             ORDER BY valid_from DESC, id DESC'
        );
        $stmt->execute(['contact_id' => $contactId]);

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = self::mapRow($row);
        }

        return $rows;
    }

    /**
     * Aktive Signatur des Users (valid_to IS NULL) oder null.
     *
     * @return array<string, mixed>|null
     */
    public static function activeForUser(int $userId): ?array
    {
        self::ensureTable();
        if (!Database::isConfigured() || $userId <= 0) {
            return null;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT id, user_id, contact_id, media_id, source, source_ref,
                    valid_from, valid_to, created_at, created_by_user_id
             FROM dg_user_signatures
             WHERE user_id = :user_id AND valid_to IS NULL
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::mapRow($row) : null;
    }

    /**
     * Bild-Upload (PNG/WebP) → Mediathek + appendNew (source=upload|app). Kein Löschen.
     *
     * @param array<string, mixed> $file $_FILES-Eintrag
     * @return int Neue Signatur-ID
     */
    public static function appendFromUpload(
        int $ownerUserId,
        int $contactId,
        array $file,
        ?int $createdByUserId = null,
        string $source = 'upload'
    ): int {
        if ($ownerUserId <= 0) {
            throw new InvalidArgumentException('Kein Benutzer für diese Unterschrift verknüpft.');
        }
        if ($contactId <= 0) {
            throw new InvalidArgumentException('contact_id ungültig.');
        }
        $source = strtolower(trim($source));
        if (!in_array($source, ['upload', 'app'], true)) {
            throw new InvalidArgumentException('Upload-source ungültig.');
        }

        $original = (string) ($file['name'] ?? '');
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'webp'], true)) {
            throw new InvalidArgumentException('Nur PNG- oder WebP-Dateien als Unterschrift.');
        }

        MediaRepository::ensureTables();
        self::ensureTable();

        $mediaId = MediaId::generate();
        $stored = MediaStorage::storeUpload($mediaId, $file);
        $stored['source_note'] = $source === 'app' ? 'Unterschrift App' : 'Unterschrift';
        $stored['title'] = 'Unterschrift';
        $stored['alt_text'] = 'Unterschrift';

        try {
            MediaRepository::insert($mediaId, $stored, $createdByUserId);
            $ref = $original !== '' ? $original : '';
            if ($source === 'app') {
                $ref = $ref !== '' ? ('app:' . $ref) : 'app-upload';
            }

            return self::appendNew([
                'user_id' => $ownerUserId,
                'contact_id' => $contactId,
                'media_id' => $mediaId,
                'source' => $source,
                'source_ref' => $ref,
                'created_by_user_id' => $createdByUserId,
            ]);
        } catch (Throwable $e) {
            try {
                MediaRepository::delete($mediaId);
            } catch (Throwable) {
                // Best effort cleanup
            }
            throw $e;
        }
    }

    /**
     * Mitarbeiter-App Upload → appendNew source=app.
     *
     * @param array<string, mixed> $file $_FILES-Eintrag
     */
    public static function appendFromAppUpload(
        int $ownerUserId,
        int $contactId,
        array $file,
        ?int $createdByUserId = null
    ): int {
        return self::appendFromUpload($ownerUserId, $contactId, $file, $createdByUserId, 'app');
    }

    /**
     * Canvas-/App-PNG → Mediathek + appendNew (source=canvas|app). Kein Löschen.
     *
     * @return int Neue Signatur-ID
     */
    public static function appendFromCanvasPng(
        int $ownerUserId,
        int $contactId,
        string $pngBinary,
        ?int $createdByUserId = null,
        string $source = 'canvas'
    ): int {
        if ($ownerUserId <= 0) {
            throw new InvalidArgumentException('Kein Benutzer für diese Unterschrift verknüpft.');
        }
        if ($contactId <= 0) {
            throw new InvalidArgumentException('contact_id ungültig.');
        }
        $source = strtolower(trim($source));
        if (!in_array($source, ['canvas', 'app'], true)) {
            throw new InvalidArgumentException('PNG-source ungültig.');
        }

        $len = strlen($pngBinary);
        if ($len < 64) {
            throw new InvalidArgumentException('Zeichnung fehlt oder ist leer.');
        }
        if ($len > MediaStorage::MAX_BYTES) {
            throw new InvalidArgumentException('Zeichnung zu groß.');
        }
        if (!str_starts_with($pngBinary, "\x89PNG\r\n\x1a\n")) {
            throw new InvalidArgumentException('Nur PNG von der Zeichenfläche erlaubt.');
        }

        MediaRepository::ensureTables();
        self::ensureTable();

        $mediaId = MediaId::generate();
        $stored = MediaStorage::storeBinary($mediaId, 'original.png', $pngBinary, 'image/png');
        $stored['original_name'] = $source === 'app' ? 'signature-app.png' : 'signature-canvas.png';
        $stored['source_note'] = $source === 'app' ? 'Unterschrift App' : 'Unterschrift Zeichenfläche';
        $stored['title'] = 'Unterschrift';
        $stored['alt_text'] = 'Unterschrift';

        try {
            MediaRepository::insert($mediaId, $stored, $createdByUserId);

            return self::appendNew([
                'user_id' => $ownerUserId,
                'contact_id' => $contactId,
                'media_id' => $mediaId,
                'source' => $source,
                'source_ref' => $source === 'app' ? 'app' : 'canvas',
                'created_by_user_id' => $createdByUserId,
            ]);
        } catch (Throwable $e) {
            try {
                MediaRepository::delete($mediaId);
            } catch (Throwable) {
                // Best effort cleanup
            }
            throw $e;
        }
    }

    /**
     * Mitarbeiter-App PNG-Bytes → appendNew source=app.
     */
    public static function appendFromAppPng(
        int $ownerUserId,
        int $contactId,
        string $pngBinary,
        ?int $createdByUserId = null
    ): int {
        return self::appendFromCanvasPng($ownerUserId, $contactId, $pngBinary, $createdByUserId, 'app');
    }

    /**
     * Aktives Unterschriftsbild des Users für Brief/Druck (ES4a).
     * Nur session-gebundener Aufrufer — kein fremder Kontakt.
     *
     * @return string Öffentliche URL oder data:-URI (für PDF), sonst ''
     */
    public static function activeImageSrcForUser(int $userId, bool $preferDataUri = false): string
    {
        if ($userId <= 0) {
            return '';
        }
        $row = self::activeForUser($userId);
        if ($row === null) {
            return '';
        }
        $mediaId = (string) ($row['media_id'] ?? '');
        if (!MediaId::isValid($mediaId)) {
            return '';
        }

        if ($preferDataUri && class_exists('MediaRepository') && class_exists('MediaStorage')) {
            MediaRepository::ensureTables();
            $item = MediaRepository::find($mediaId);
            if (is_array($item)) {
                $stored = (string) ($item['stored_name'] ?? '');
                $mime = trim((string) ($item['mime_type'] ?? ''));
                if ($mime === '') {
                    $mime = 'image/png';
                }
                if ($stored !== '') {
                    try {
                        $path = MediaStorage::absolutePath($mediaId, $stored);
                        if (is_file($path)) {
                            $bin = file_get_contents($path);
                            if (is_string($bin) && $bin !== '') {
                                return 'data:' . $mime . ';base64,' . base64_encode($bin);
                            }
                        }
                    } catch (Throwable) {
                        // Fallback URL
                    }
                }
            }
        }

        return class_exists('MediaStorage') ? MediaStorage::publicUrl($mediaId) : '';
    }

    /**
     * data:image/png;base64,… → appendFromCanvasPng (oder App source=app).
     */
    public static function appendFromCanvasDataUrl(
        int $ownerUserId,
        int $contactId,
        string $dataUrl,
        ?int $createdByUserId = null,
        string $source = 'canvas'
    ): int {
        $dataUrl = trim($dataUrl);
        if (!preg_match('#^data:image/png;base64,#i', $dataUrl)) {
            throw new InvalidArgumentException('Ungültige Zeichenflächen-Daten.');
        }
        $raw = substr($dataUrl, (int) strpos($dataUrl, ',') + 1);
        $binary = base64_decode($raw, true);
        if ($binary === false || $binary === '') {
            throw new InvalidArgumentException('Zeichnung konnte nicht gelesen werden.');
        }

        return self::appendFromCanvasPng($ownerUserId, $contactId, $binary, $createdByUserId, $source);
    }

    /**
     * Mitarbeiter-App data-URL → appendNew source=app.
     */
    public static function appendFromAppDataUrl(
        int $ownerUserId,
        int $contactId,
        string $dataUrl,
        ?int $createdByUserId = null
    ): int {
        return self::appendFromCanvasDataUrl($ownerUserId, $contactId, $dataUrl, $createdByUserId, 'app');
    }

    /**
     * Schließt die aktive Signatur des Users und legt eine neue an.
     *
     * @param array{
     *   user_id: int,
     *   contact_id: int,
     *   media_id: string,
     *   source: string,
     *   source_ref?: string,
     *   created_by_user_id?: int|null,
     *   valid_from?: string|null
     * } $input
     * @return int Neue Signatur-ID
     */
    public static function appendNew(array $input): int
    {
        self::ensureTable();
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht konfiguriert.');
        }

        $userId = (int) ($input['user_id'] ?? 0);
        $contactId = (int) ($input['contact_id'] ?? 0);
        $mediaId = trim((string) ($input['media_id'] ?? ''));
        $source = strtolower(trim((string) ($input['source'] ?? '')));
        $sourceRef = trim((string) ($input['source_ref'] ?? ''));
        if (strlen($sourceRef) > 191) {
            $sourceRef = substr($sourceRef, 0, 191);
        }
        $createdBy = array_key_exists('created_by_user_id', $input)
            ? (($input['created_by_user_id'] === null || $input['created_by_user_id'] === '')
                ? null
                : (int) $input['created_by_user_id'])
            : $userId;
        if ($createdBy !== null && $createdBy <= 0) {
            $createdBy = null;
        }

        if ($userId <= 0) {
            throw new InvalidArgumentException('user_id ungültig.');
        }
        if ($contactId <= 0) {
            throw new InvalidArgumentException('contact_id ungültig.');
        }
        if (!MediaId::isValid($mediaId)) {
            throw new InvalidArgumentException('media_id ungültig.');
        }
        if (!in_array($source, self::SOURCES, true)) {
            throw new InvalidArgumentException('source ungültig.');
        }

        $validFromRaw = trim((string) ($input['valid_from'] ?? ''));
        $validFrom = $validFromRaw !== '' ? $validFromRaw : date('Y-m-d H:i:s');
        if (strtotime($validFrom) === false) {
            throw new InvalidArgumentException('valid_from ungültig.');
        }
        $validFrom = date('Y-m-d H:i:s', strtotime($validFrom));

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $close = $pdo->prepare(
                'UPDATE dg_user_signatures
                 SET valid_to = :valid_to
                 WHERE user_id = :user_id AND valid_to IS NULL'
            );
            $close->execute([
                'valid_to' => $validFrom,
                'user_id' => $userId,
            ]);

            $insert = $pdo->prepare(
                'INSERT INTO dg_user_signatures (
                    user_id, contact_id, media_id, source, source_ref,
                    valid_from, valid_to, created_by_user_id
                 ) VALUES (
                    :user_id, :contact_id, :media_id, :source, :source_ref,
                    :valid_from, NULL, :created_by_user_id
                 )'
            );
            $insert->execute([
                'user_id' => $userId,
                'contact_id' => $contactId,
                'media_id' => $mediaId,
                'source' => $source,
                'source_ref' => $sourceRef,
                'valid_from' => $validFrom,
                'created_by_user_id' => $createdBy,
            ]);

            $newId = (int) $pdo->lastInsertId();
            $pdo->commit();

            return $newId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function mapRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'contact_id' => (int) ($row['contact_id'] ?? 0),
            'media_id' => (string) ($row['media_id'] ?? ''),
            'source' => (string) ($row['source'] ?? ''),
            'source_ref' => (string) ($row['source_ref'] ?? ''),
            'valid_from' => (string) ($row['valid_from'] ?? ''),
            'valid_to' => $row['valid_to'] !== null && $row['valid_to'] !== ''
                ? (string) $row['valid_to']
                : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'created_by_user_id' => isset($row['created_by_user_id']) && $row['created_by_user_id'] !== null && $row['created_by_user_id'] !== ''
                ? (int) $row['created_by_user_id']
                : null,
            'is_active' => ($row['valid_to'] === null || $row['valid_to'] === ''),
        ];
    }
}
