<?php
declare(strict_types=1);

/**
 * Multi-Firma MF4: stichtagsbezogene Firmendaten-Historie (prüfrelevant, Instanz-DB).
 */
final class CompanyMasterHistoryRepository
{
    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
    }

    public static function tableReady(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            $ready = Database::pdo()->query("SHOW TABLES LIKE 'dg_company_master_history'")->fetchColumn() !== false;
        } catch (Throwable) {
            $ready = false;
        }

        return $ready;
    }

    /**
     * Snapshot aus aktuellen Firmenstammdaten; nur speichern wenn Fingerprint neu.
     *
     * @return int|null neue History-ID oder null wenn unverändert/übersprungen
     */
    public static function recordFromCurrentSettings(?int $userId = null, ?string $validFrom = null, string $note = ''): ?int
    {
        self::ensureReady();
        if (!self::tableReady()) {
            return null;
        }

        $basic = CompanySettings::config();
        $ext = CompanyExtendedSettings::config();
        $row = [
            'valid_from' => $validFrom !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $validFrom)
                ? $validFrom
                : (new DateTimeImmutable('today'))->format('Y-m-d'),
            'legal_name' => trim((string) ($ext['legal_name'] ?? '')),
            'company_type' => trim((string) ($ext['company_type'] ?? '')),
            'gewinnermittlung' => trim((string) ($ext['gewinnermittlung'] ?? '')),
            'tax_number' => trim((string) (($ext['tax_numbers']['est'] ?? null) ?: ($basic['tax_number'] ?? ''))),
            'vat_id' => trim((string) (($ext['tax_numbers']['ust'] ?? null) ?: ($basic['vat_id'] ?? ''))),
            'display_name' => trim((string) ($basic['name'] ?? '')),
        ];
        $fingerprint = hash('sha256', json_encode([
            $row['legal_name'],
            $row['company_type'],
            $row['gewinnermittlung'],
            $row['tax_number'],
            $row['vat_id'],
            $row['display_name'],
        ], JSON_UNESCAPED_UNICODE) ?: '');

        $pdo = Database::pdo();
        $chk = $pdo->prepare('SELECT id FROM dg_company_master_history WHERE fingerprint = :fp ORDER BY id DESC LIMIT 1');
        $chk->execute(['fp' => $fingerprint]);
        if ($chk->fetchColumn()) {
            return null;
        }

        $snapshot = [
            'basic' => $basic,
            'extended_subset' => [
                'legal_name' => $row['legal_name'],
                'company_type' => $row['company_type'],
                'gewinnermittlung' => $row['gewinnermittlung'],
                'tax_numbers' => $ext['tax_numbers'] ?? [],
            ],
        ];
        $stmt = $pdo->prepare(
            'INSERT INTO dg_company_master_history
                (valid_from, legal_name, company_type, gewinnermittlung, tax_number, vat_id, display_name,
                 fingerprint, snapshot_json, note, created_by)
             VALUES
                (:valid_from, :legal_name, :company_type, :gewinnermittlung, :tax_number, :vat_id, :display_name,
                 :fingerprint, :snapshot_json, :note, :created_by)'
        );
        $stmt->execute([
            'valid_from' => $row['valid_from'],
            'legal_name' => $row['legal_name'],
            'company_type' => $row['company_type'],
            'gewinnermittlung' => $row['gewinnermittlung'],
            'tax_number' => $row['tax_number'],
            'vat_id' => $row['vat_id'],
            'display_name' => $row['display_name'],
            'fingerprint' => $fingerprint,
            'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'note' => mb_substr(trim($note), 0, 500),
            'created_by' => $userId !== null && $userId > 0 ? $userId : null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listRecent(int $limit = 25): array
    {
        self::ensureReady();
        if (!self::tableReady()) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $stmt = Database::pdo()->query(
            'SELECT id, valid_from, legal_name, company_type, gewinnermittlung, tax_number, vat_id,
                    display_name, note, created_by, created_at
             FROM dg_company_master_history
             ORDER BY valid_from DESC, id DESC
             LIMIT ' . $limit
        );

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }
}
