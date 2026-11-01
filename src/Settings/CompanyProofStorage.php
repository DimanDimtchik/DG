<?php
declare(strict_types=1);

/**
 * Empfohlene/optionale Firmen-Nachweise (PDF/Bild). Ist-Versteuerung: eigene Tabelle.
 * @see docs/FIRMEN-NACHWEIS-MATRIX.md
 */
final class CompanyProofStorage
{
    public const LEVEL_RECOMMENDED = 'recommended';
    public const LEVEL_OPTIONAL = 'optional';

    public static function storageDir(): string
    {
        return DG_ROOT . '/storage/company-proofs';
    }

    /**
     * @return array<string, array{label: string, level: string, hint: string}>
     */
    public static function catalog(): array
    {
        return [
            'tax_number' => [
                'label' => 'Steuernummer (FA-Schreiben)',
                'level' => self::LEVEL_RECOMMENDED,
                'hint' => 'Zuordnung / Mitteilung Finanzamt',
            ],
            'vat_id' => [
                'label' => 'USt-IdNr. (BZSt)',
                'level' => self::LEVEL_RECOMMENDED,
                'hint' => 'Vergabeschreiben',
            ],
            'steuer_id' => [
                'label' => 'Steuer-ID (Person)',
                'level' => self::LEVEL_RECOMMENDED,
                'hint' => 'Inhaber/GF — natürliche Person',
            ],
            'wirtschafts_id' => [
                'label' => 'Wirtschafts-Identifikationsnummer',
                'level' => self::LEVEL_RECOMMENDED,
                'hint' => 'Sobald zugeteilt',
            ],
            'trade_register' => [
                'label' => 'Handelsregisterauszug',
                'level' => self::LEVEL_RECOMMENDED,
                'hint' => 'Aktueller Auszug',
            ],
            'bg_membership' => [
                'label' => 'BG / Unfallversicherung',
                'level' => self::LEVEL_RECOMMENDED,
                'hint' => 'Mitgliedschaft / Unternehmensnummer',
            ],
            'ihk' => [
                'label' => 'IHK-Mitgliedschaft',
                'level' => self::LEVEL_RECOMMENDED,
                'hint' => 'Mitgliedsnummer / Bescheinigung',
            ],
            'hwk' => [
                'label' => 'HWK-Mitgliedschaft',
                'level' => self::LEVEL_RECOMMENDED,
                'hint' => 'Mitgliedsnummer / Handwerksrolle',
            ],
            'employment_agency_bn' => [
                'label' => 'Betriebsnummer Agentur für Arbeit',
                'level' => self::LEVEL_RECOMMENDED,
                'hint' => 'Zuteilungsbescheid',
            ],
            'kleinunternehmer' => [
                'label' => 'Kleinunternehmer § 19 (Korrespondenz)',
                'level' => self::LEVEL_OPTIONAL,
                'hint' => 'Optional — oft kein eigener Bescheid',
            ],
            'photovoltaik' => [
                'label' => 'Photovoltaik § 12 Abs. 3 (Unterlage)',
                'level' => self::LEVEL_OPTIONAL,
                'hint' => 'Optional',
            ],
        ];
    }

    public static function isValidKey(string $key): bool
    {
        return isset(self::catalog()[$key]);
    }

    /**
     * Aktive Nachweise je Key (neuester nicht supersedeter).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function activeByKey(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->query(
            'SELECT * FROM dg_company_proofs
             WHERE superseded_at IS NULL
             ORDER BY id DESC'
        );
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $key = (string) ($row['proof_key'] ?? '');
            if ($key === '' || isset($out[$key])) {
                continue;
            }
            $out[$key] = self::normalize($row);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $file
     */
    public static function store(string $proofKey, array $input, ?array $file, ?int $userId): int
    {
        if (!self::isValidKey($proofKey)) {
            throw new InvalidArgumentException('Unbekannter Nachweis-Typ.');
        }
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        MigrationRunner::runPending();

        $uploaded = self::storeFile($file);
        if ($uploaded === null) {
            throw new InvalidArgumentException('Bitte eine PDF- oder Bilddatei wählen.');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE dg_company_proofs SET superseded_at = NOW()
                 WHERE proof_key = :k AND superseded_at IS NULL'
            )->execute(['k' => $proofKey]);

            $stmt = $pdo->prepare(
                'INSERT INTO dg_company_proofs
                    (proof_key, issuer, document_date, reference_no, note,
                     file_stored_name, file_original_name, file_mime, file_size, created_by)
                 VALUES
                    (:proof_key, :issuer, :document_date, :reference_no, :note,
                     :file_stored_name, :file_original_name, :file_mime, :file_size, :created_by)'
            );
            $docDate = self::sanitizeDate((string) ($input['document_date'] ?? ''));
            $stmt->execute([
                'proof_key' => $proofKey,
                'issuer' => mb_substr(trim((string) ($input['issuer'] ?? '')), 0, 255),
                'document_date' => $docDate,
                'reference_no' => mb_substr(trim((string) ($input['reference_no'] ?? '')), 0, 120),
                'note' => mb_substr(trim((string) ($input['note'] ?? '')), 0, 500),
                'file_stored_name' => $uploaded['stored_name'],
                'file_original_name' => $uploaded['original_name'],
                'file_mime' => $uploaded['mime'],
                'file_size' => $uploaded['size'],
                'created_by' => $userId !== null && $userId > 0 ? $userId : null,
            ]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        AuditLog::record('company_proof_stored', $userId, json_encode([
            'id' => $id,
            'proof_key' => $proofKey,
        ], JSON_UNESCAPED_UNICODE));

        return $id;
    }

    public static function findById(int $id): ?array
    {
        if ($id < 1 || !Database::isConfigured()) {
            return null;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_company_proofs WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::normalize($row) : null;
    }

    public static function absolutePath(array $proof): ?string
    {
        $name = trim((string) ($proof['file_stored_name'] ?? ''));
        if ($name === '' || preg_match('/[^a-zA-Z0-9._-]/', $name)) {
            return null;
        }
        $path = self::storageDir() . '/' . $name;

        return is_file($path) ? $path : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function normalize(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'proof_key' => (string) ($row['proof_key'] ?? ''),
            'issuer' => (string) ($row['issuer'] ?? ''),
            'document_date' => (string) ($row['document_date'] ?? ''),
            'reference_no' => (string) ($row['reference_no'] ?? ''),
            'note' => (string) ($row['note'] ?? ''),
            'file_stored_name' => (string) ($row['file_stored_name'] ?? ''),
            'file_original_name' => (string) ($row['file_original_name'] ?? ''),
            'file_mime' => (string) ($row['file_mime'] ?? ''),
            'file_size' => (int) ($row['file_size'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed>|null $file
     * @return array{stored_name: string, original_name: string, mime: string, size: int}|null
     */
    private static function storeFile(?array $file): ?array
    {
        if ($file === null) {
            return null;
        }
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Upload fehlgeschlagen.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $original = (string) ($file['name'] ?? 'nachweis.pdf');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp) || $size < 1 || $size > 26_214_400) {
            throw new InvalidArgumentException('Ungültige Datei (max. 25 MB).');
        }
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new InvalidArgumentException('Nur PDF oder Bild (JPG/PNG/WebP).');
        }
        $dir = self::storageDir();
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Ablageordner fehlgeschlagen.');
        }
        $stored = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!move_uploaded_file($tmp, $dir . '/' . $stored)) {
            throw new RuntimeException('Datei konnte nicht gespeichert werden.');
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
