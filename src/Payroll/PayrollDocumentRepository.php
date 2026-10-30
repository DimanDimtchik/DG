<?php
declare(strict_types=1);

/**
 * Lohn L6b: Persistenz + Erzeugung Abrechnungs-PDFs (Final nach Lock, Draft optional).
 */
final class PayrollDocumentRepository
{
    public const KIND_PAYSLIP = 'payslip';
    public const KIND_DRAFT = 'payslip_draft';

    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
        PayrollRunRepository::ensureReady();
    }

    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            return Database::pdo()->query("SHOW TABLES LIKE 'dg_payroll_documents'")->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        self::ensureReady();
        if (!self::tableReady()) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_payroll_documents WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByRunContact(int $runId, int $contactId, string $kind = self::KIND_PAYSLIP): ?array
    {
        self::ensureReady();
        if (!self::tableReady()) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_payroll_documents
             WHERE run_id = :r AND contact_id = :c AND kind = :k
             LIMIT 1'
        );
        $stmt->execute(['r' => $runId, 'c' => $contactId, 'k' => $kind]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForContact(int $contactId, ?int $year = null): array
    {
        self::ensureReady();
        if (!self::tableReady()) {
            return [];
        }
        $sql = 'SELECT * FROM dg_payroll_documents WHERE contact_id = :c';
        $params = ['c' => $contactId];
        if ($year !== null) {
            $sql .= ' AND period_year = :y';
            $params['y'] = $year;
        }
        $sql .= ' ORDER BY period_year ASC, period_month ASC, id ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForRun(int $runId): array
    {
        self::ensureReady();
        if (!self::tableReady()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_payroll_documents WHERE run_id = :r ORDER BY contact_id ASC, kind ASC'
        );
        $stmt->execute(['r' => $runId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Nach Lock: Final-PDF für alle MA mit Status ok. Idempotent (bestehende Finals bleiben).
     *
     * @return array{created: int, skipped: int, errors: list<string>}
     */
    public static function generateFinalsForRun(int $runId, ?int $userId): array
    {
        self::ensureReady();
        if (!self::tableReady()) {
            throw new RuntimeException('Tabelle dg_payroll_documents fehlt (Migration 129).');
        }
        $run = PayrollRunRepository::find($runId);
        if ($run === null) {
            throw new InvalidArgumentException('Lauf nicht gefunden.');
        }
        if ((string) ($run['status'] ?? '') !== PayrollRunRepository::STATUS_LOCKED) {
            throw new InvalidArgumentException('Final-PDF nur für locked Läufe.');
        }

        $created = 0;
        $skipped = 0;
        $errors = [];
        foreach (PayrollRunRepository::employees($runId) as $emp) {
            if (($emp['status'] ?? '') !== PayrollRunRepository::EMP_OK) {
                $skipped++;
                continue;
            }
            $contactId = (int) ($emp['contact_id'] ?? 0);
            if ($contactId <= 0) {
                $skipped++;
                continue;
            }
            if (self::findByRunContact($runId, $contactId, self::KIND_PAYSLIP) !== null) {
                $skipped++;
                continue;
            }
            try {
                self::generateOne($run, $emp, self::KIND_PAYSLIP, false, $userId);
                $created++;
            } catch (Throwable $e) {
                $errors[] = 'MA #' . $contactId . ': ' . $e->getMessage();
            }
        }

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Manuell Final für einen MA (Run muss locked + MA ok sein).
     *
     * @return array<string, mixed>
     */
    public static function generateFinalForEmployee(int $runId, int $contactId, ?int $userId): array
    {
        self::ensureReady();
        $run = PayrollRunRepository::find($runId);
        if ($run === null) {
            throw new InvalidArgumentException('Lauf nicht gefunden.');
        }
        if ((string) ($run['status'] ?? '') !== PayrollRunRepository::STATUS_LOCKED) {
            throw new InvalidArgumentException('Final-PDF nur für locked Läufe.');
        }
        $emp = self::requireEmployee($runId, $contactId);
        if (($emp['status'] ?? '') !== PayrollRunRepository::EMP_OK) {
            throw new InvalidArgumentException('Final-PDF nur für MA mit Status ok.');
        }
        $existing = self::findByRunContact($runId, $contactId, self::KIND_PAYSLIP);
        if ($existing !== null) {
            return $existing;
        }

        return self::generateOne($run, $emp, self::KIND_PAYSLIP, false, $userId);
    }

    /**
     * Entwurf aus calculated/approved (überschreibbar).
     *
     * @return array<string, mixed>
     */
    public static function generateDraftForEmployee(int $runId, int $contactId, ?int $userId): array
    {
        self::ensureReady();
        $run = PayrollRunRepository::find($runId);
        if ($run === null) {
            throw new InvalidArgumentException('Lauf nicht gefunden.');
        }
        $st = (string) ($run['status'] ?? '');
        if (!in_array($st, [
            PayrollRunRepository::STATUS_CALCULATED,
            PayrollRunRepository::STATUS_APPROVED,
            PayrollRunRepository::STATUS_LOCKED,
        ], true)) {
            throw new InvalidArgumentException('Entwurf-PDF erst nach Berechnung.');
        }
        $emp = self::requireEmployee($runId, $contactId);
        if (($emp['status'] ?? '') !== PayrollRunRepository::EMP_OK) {
            throw new InvalidArgumentException('Entwurf-PDF nur für MA mit Status ok.');
        }
        $existing = self::findByRunContact($runId, $contactId, self::KIND_DRAFT);
        if ($existing !== null) {
            $rel = (string) ($existing['storage_path'] ?? '');
            if ($rel !== '') {
                PayrollDocumentStorage::delete($rel);
            }
            Database::pdo()->prepare('DELETE FROM dg_payroll_documents WHERE id = :id')
                ->execute(['id' => (int) $existing['id']]);
        }

        return self::generateOne($run, $emp, self::KIND_DRAFT, true, $userId);
    }

    /**
     * PDF-Bytes lesen (nur wenn Datei + Meta existieren).
     */
    public static function readBinary(int $documentId): ?string
    {
        $doc = self::find($documentId);
        if ($doc === null) {
            return null;
        }
        $rel = (string) ($doc['storage_path'] ?? '');
        if ($rel === '') {
            return null;
        }
        $bin = PayrollDocumentStorage::read($rel);
        if ($bin === null) {
            return null;
        }
        $checksum = (string) ($doc['checksum'] ?? '');
        if ($checksum !== '' && !hash_equals($checksum, hash('sha256', $bin))) {
            throw new RuntimeException('PDF-Checksumme stimmt nicht — Datei möglicherweise verändert.');
        }

        return $bin;
    }

    /**
     * Kontext für HTML/PDF (auch Selftest ohne Dompdf).
     *
     * @param array<string, mixed> $run
     * @param array<string, mixed> $emp
     * @return array<string, mixed>
     */
    public static function buildContext(array $run, array $emp): array
    {
        $contactId = (int) ($emp['contact_id'] ?? 0);
        $snapshot = self::decodeJson($emp['snapshot_json'] ?? null);
        $amounts = self::decodeJson($emp['result_json'] ?? null);
        $warnings = self::decodeJsonList($emp['warnings_json'] ?? null);
        $employeeSnap = is_array($snapshot['employee'] ?? null) ? $snapshot['employee'] : [];

        $employer = [];
        if (class_exists('PayrollEmployerSettings')) {
            $employer = PayrollEmployerSettings::employerSnapshot();
        }

        $displayName = '';
        $addressLine = '';
        if (class_exists('ContactRepository')) {
            $contact = ContactRepository::findById($contactId);
            if ($contact !== null) {
                $displayName = $contact->listLabel();
                $parts = array_filter([
                    trim($contact->address1Street),
                    trim($contact->address1Postal . ' ' . $contact->address1City),
                ], static fn (string $p): bool => $p !== '');
                $addressLine = implode(', ', $parts);
            }
        }
        if ($displayName === '') {
            $displayName = 'Mitarbeiter #' . $contactId;
        }

        $lines = [];
        foreach (PayrollRunRepository::linesForEmployee((int) $run['id'], $contactId) as $ln) {
            $lines[] = [
                'code' => (string) ($ln['code'] ?? ''),
                'source' => (string) ($ln['source'] ?? ''),
                'amount' => (float) ($ln['amount'] ?? 0),
                'hours' => $ln['hours'] !== null && $ln['hours'] !== '' ? (float) $ln['hours'] : null,
            ];
        }

        $kind = (string) ($run['kind'] ?? PayrollRunRepository::KIND_REGULAR);
        $kindLabel = match ($kind) {
            PayrollRunRepository::KIND_CORRECTION => 'Korrektur',
            PayrollRunRepository::KIND_STORNO => 'Storno',
            default => 'Regulär',
        };

        return [
            'run_id' => (int) $run['id'],
            'period_year' => (int) $run['period_year'],
            'period_month' => (int) $run['period_month'],
            'run_status' => (string) ($run['status'] ?? ''),
            'kind' => $kind,
            'kind_label' => $kindLabel,
            'path' => (string) ($emp['path'] ?? ''),
            'generated_at' => date('d.m.Y H:i'),
            'employer' => $employer,
            'employee' => [
                'display_name' => $displayName,
                'address' => $addressLine,
                'tax_id' => (string) ($employeeSnap['tax_id'] ?? ''),
                'tax_class' => (string) ($employeeSnap['tax_class'] ?? ''),
                'religion_code' => (string) ($employeeSnap['religion_code'] ?? ''),
                'child_allowances' => (string) ($employeeSnap['child_allowances'] ?? ''),
                'social_security_number' => (string) ($employeeSnap['social_security_number'] ?? ''),
                'payroll_employment_type' => (string) ($employeeSnap['payroll_employment_type'] ?? ''),
                'entry_date' => (string) ($employeeSnap['entry_date'] ?? ''),
            ],
            'amounts' => $amounts,
            'lines' => $lines,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<string, mixed> $run
     * @param array<string, mixed> $emp
     * @return array<string, mixed>
     */
    private static function generateOne(
        array $run,
        array $emp,
        string $kind,
        bool $draft,
        ?int $userId
    ): array {
        if (!self::tableReady()) {
            throw new RuntimeException('Tabelle dg_payroll_documents fehlt (Migration 129).');
        }
        $contactId = (int) ($emp['contact_id'] ?? 0);
        $year = (int) $run['period_year'];
        $month = (int) $run['period_month'];
        $runId = (int) $run['id'];

        $ctx = self::buildContext($run, $emp);
        $binary = PayrollPayslipPdfService::renderPdf($ctx, $draft);
        $checksum = hash('sha256', $binary);
        $relative = PayrollDocumentStorage::relativePath($year, $month, $contactId, $runId, $draft);
        PayrollDocumentStorage::write($relative, $binary, $draft);

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_payroll_documents
                (run_id, contact_id, kind, period_year, period_month, storage_path, checksum, byte_size, is_final, created_by_user_id)
             VALUES
                (:run_id, :contact_id, :kind, :y, :m, :path, :checksum, :bytes, :final, :uid)'
        );
        $stmt->execute([
            'run_id' => $runId,
            'contact_id' => $contactId,
            'kind' => $kind,
            'y' => $year,
            'm' => $month,
            'path' => $relative,
            'checksum' => $checksum,
            'bytes' => strlen($binary),
            'final' => $draft ? 0 : 1,
            'uid' => $userId,
        ]);
        $id = (int) Database::pdo()->lastInsertId();
        $row = self::find($id);
        if ($row === null) {
            throw new RuntimeException('PDF-Metadaten konnten nicht gelesen werden.');
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private static function requireEmployee(int $runId, int $contactId): array
    {
        foreach (PayrollRunRepository::employees($runId) as $emp) {
            if ((int) ($emp['contact_id'] ?? 0) === $contactId) {
                return $emp;
            }
        }
        throw new InvalidArgumentException('Mitarbeiter nicht im Lauf.');
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeJson(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return list<string>
     */
    private static function decodeJsonList(mixed $raw): array
    {
        $arr = self::decodeJson($raw);
        $out = [];
        foreach ($arr as $v) {
            $out[] = (string) $v;
        }

        return $out;
    }
}
