<?php
declare(strict_types=1);

/**
 * Soll-/Ist-Versteuerung (§ 20 UStG): Regime mit Stichtag und FA-Bescheid-Ablage.
 */
final class VatTaxationSettings
{
    public const METHOD_SOLL = 'soll';
    public const METHOD_IST = 'ist';

    public static function storageDir(): string
    {
        return DG_ROOT . '/storage/vat-taxation';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function history(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->query(
            'SELECT * FROM dg_vat_taxation_regimes
             ORDER BY valid_from DESC, id DESC'
        );
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (is_array($row)) {
                $rows[] = self::normalizeRow($row);
            }
        }

        return $rows;
    }

    /**
     * Aktives Regime am Stichtag (inklusive).
     *
     * @return array<string, mixed>
     */
    public static function regimeOn(string $dateYmd): array
    {
        $dateYmd = self::sanitizeDate($dateYmd) ?? date('Y-m-d');
        if (!Database::isConfigured()) {
            return self::defaultRegime($dateYmd);
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_vat_taxation_regimes
             WHERE valid_from <= :d
             ORDER BY valid_from DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute(['d' => $dateYmd]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return self::defaultRegime($dateYmd);
        }

        return self::normalizeRow($row);
    }

    public static function methodOn(string $dateYmd): string
    {
        $method = (string) (self::regimeOn($dateYmd)['method'] ?? self::METHOD_SOLL);

        return $method === self::METHOD_IST ? self::METHOD_IST : self::METHOD_SOLL;
    }

    public static function isIstOn(string $dateYmd): bool
    {
        return self::methodOn($dateYmd) === self::METHOD_IST;
    }

    /**
     * @return array{method: string, valid_from: string, label: string, has_bescheid: bool}
     */
    public static function summaryForPeriod(int $year, ?int $month): array
    {
        $end = ($month !== null && $month >= 1 && $month <= 12)
            ? sprintf('%04d-%02d-%02d', $year, $month, (int) date('t', mktime(0, 0, 0, $month, 1, $year)))
            : sprintf('%04d-12-31', $year);
        $regime = self::regimeOn($end);
        $method = (string) ($regime['method'] ?? self::METHOD_SOLL);
        $from = (string) ($regime['valid_from'] ?? '');
        $hasBescheid = trim((string) ($regime['file_stored_name'] ?? '')) !== ''
            || trim((string) ($regime['aktenzeichen'] ?? '')) !== '';

        return [
            'method' => $method === self::METHOD_IST ? self::METHOD_IST : self::METHOD_SOLL,
            'valid_from' => $from,
            'label' => $method === self::METHOD_IST
                ? ('Ist-Versteuerung' . ($from !== '' ? ' ab ' . $from : ''))
                : ('Soll-Versteuerung' . ($from !== '' ? ' ab ' . $from : '')),
            'has_bescheid' => $hasBescheid,
        ];
    }

    /**
     * Neues Regime ab Stichtag. Wechsel auf Ist erfordert Bescheid-Datei.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $file $_FILES['vat_taxation_bescheid'] o. ä.
     */
    public static function activate(array $input, ?array $file, ?int $userId): int
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        MigrationRunner::runPending();

        $method = strtolower(trim((string) ($input['method'] ?? self::METHOD_SOLL)));
        if ($method !== self::METHOD_IST) {
            $method = self::METHOD_SOLL;
        }
        $validFrom = self::sanitizeDate((string) ($input['valid_from'] ?? ''));
        if ($validFrom === null) {
            throw new InvalidArgumentException('Bitte „Gültig ab“ angeben.');
        }

        $bescheidDate = self::sanitizeDate((string) ($input['bescheid_date'] ?? ''));
        $aktenzeichen = mb_substr(trim((string) ($input['aktenzeichen'] ?? '')), 0, 120);
        $finanzamt = mb_substr(trim((string) ($input['finanzamt'] ?? '')), 0, 255);
        $note = mb_substr(trim((string) ($input['note'] ?? '')), 0, 500);

        $storedName = '';
        $originalName = '';
        $mime = '';
        $size = 0;

        if ($method === self::METHOD_IST) {
            $uploaded = self::storeBescheidFile($file);
            if ($uploaded === null) {
                throw new InvalidArgumentException(
                    'Für die Ist-Versteuerung muss der Finanzamt-Bescheid als Datei hinterlegt werden.'
                );
            }
            $storedName = $uploaded['stored_name'];
            $originalName = $uploaded['original_name'];
            $mime = $uploaded['mime'];
            $size = $uploaded['size'];
            if ($bescheidDate === null) {
                throw new InvalidArgumentException('Bitte das Bescheiddatum angeben.');
            }
        } elseif (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $uploaded = self::storeBescheidFile($file);
            if ($uploaded !== null) {
                $storedName = $uploaded['stored_name'];
                $originalName = $uploaded['original_name'];
                $mime = $uploaded['mime'];
                $size = $uploaded['size'];
            }
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE dg_vat_taxation_regimes
                 SET superseded_at = NOW()
                 WHERE superseded_at IS NULL AND valid_from <= :vf'
            )->execute(['vf' => $validFrom]);

            $stmt = $pdo->prepare(
                'INSERT INTO dg_vat_taxation_regimes
                    (method, valid_from, bescheid_date, aktenzeichen, finanzamt,
                     file_stored_name, file_original_name, file_mime, file_size,
                     note, created_by)
                 VALUES
                    (:method, :valid_from, :bescheid_date, :aktenzeichen, :finanzamt,
                     :file_stored_name, :file_original_name, :file_mime, :file_size,
                     :note, :created_by)'
            );
            $stmt->execute([
                'method' => $method,
                'valid_from' => $validFrom,
                'bescheid_date' => $bescheidDate,
                'aktenzeichen' => $aktenzeichen,
                'finanzamt' => $finanzamt,
                'file_stored_name' => $storedName,
                'file_original_name' => $originalName,
                'file_mime' => $mime,
                'file_size' => $size,
                'note' => $note,
                'created_by' => $userId !== null && $userId > 0 ? $userId : null,
            ]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $json = json_encode([
            'id' => $id,
            'method' => $method,
            'valid_from' => $validFrom,
            'bescheid_date' => $bescheidDate,
            'aktenzeichen' => $aktenzeichen,
            'has_file' => $storedName !== '',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        AuditLog::record('vat_taxation_regime_activated', $userId, is_string($json) ? $json : null);

        return $id;
    }

    public static function absoluteFilePath(array $regime): ?string
    {
        $name = trim((string) ($regime['file_stored_name'] ?? ''));
        if ($name === '' || preg_match('/[^a-zA-Z0-9._-]/', $name)) {
            return null;
        }
        $path = self::storageDir() . '/' . $name;
        return is_file($path) ? $path : null;
    }

    /**
     * @return array{method: string, valid_from: string, bescheid_date: string, aktenzeichen: string, finanzamt: string, file_stored_name: string, file_original_name: string, has_bescheid: bool, id: int}
     */
    private static function defaultRegime(string $dateYmd): array
    {
        return [
            'id' => 0,
            'method' => self::METHOD_SOLL,
            'valid_from' => $dateYmd,
            'bescheid_date' => '',
            'aktenzeichen' => '',
            'finanzamt' => '',
            'file_stored_name' => '',
            'file_original_name' => '',
            'has_bescheid' => false,
            'note' => '',
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function normalizeRow(array $row): array
    {
        $method = (string) ($row['method'] ?? self::METHOD_SOLL);
        $stored = trim((string) ($row['file_stored_name'] ?? ''));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'method' => $method === self::METHOD_IST ? self::METHOD_IST : self::METHOD_SOLL,
            'valid_from' => (string) ($row['valid_from'] ?? ''),
            'bescheid_date' => (string) ($row['bescheid_date'] ?? ''),
            'aktenzeichen' => (string) ($row['aktenzeichen'] ?? ''),
            'finanzamt' => (string) ($row['finanzamt'] ?? ''),
            'file_stored_name' => $stored,
            'file_original_name' => (string) ($row['file_original_name'] ?? ''),
            'file_mime' => (string) ($row['file_mime'] ?? ''),
            'file_size' => (int) ($row['file_size'] ?? 0),
            'note' => (string) ($row['note'] ?? ''),
            'created_by' => $row['created_by'] ?? null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'superseded_at' => (string) ($row['superseded_at'] ?? ''),
            'has_bescheid' => $stored !== '',
        ];
    }

    /**
     * @param array<string, mixed>|null $file
     * @return array{stored_name: string, original_name: string, mime: string, size: int}|null
     */
    private static function storeBescheidFile(?array $file): ?array
    {
        if ($file === null) {
            return null;
        }
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Bescheid-Upload fehlgeschlagen (Code ' . $error . ').');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $original = (string) ($file['name'] ?? 'bescheid.pdf');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new InvalidArgumentException('Ungültige Bescheid-Datei.');
        }
        if ($size < 1 || $size > 26_214_400) {
            throw new InvalidArgumentException('Bescheid-Datei zu groß (max. 25 MB).');
        }
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            throw new InvalidArgumentException('Bescheid nur als PDF oder Bild (JPG/PNG/WebP).');
        }
        $dir = self::storageDir();
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Ablageordner für Bescheide konnte nicht angelegt werden.');
        }
        $stored = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $target = $dir . '/' . $stored;
        if (!move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('Bescheid konnte nicht gespeichert werden.');
        }
        $mime = match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return [
            'stored_name' => $stored,
            'original_name' => mb_substr($original, 0, 255),
            'mime' => $mime,
            'size' => $size,
        ];
    }

    private static function sanitizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);

        return $ts !== false ? date('Y-m-d', $ts) : null;
    }
}
