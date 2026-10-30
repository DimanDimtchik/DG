<?php
declare(strict_types=1);

/**
 * Lohn L5b: Monatsläufe — CRUD-light, Statusübergänge, Events, Calculate via L4c.
 */
final class PayrollRunRepository
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CALCULATED = 'calculated';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_LOCKED = 'locked';

    public const KIND_REGULAR = 'regular';
    public const KIND_CORRECTION = 'correction';
    public const KIND_STORNO = 'storno';

    public const EMP_PENDING = 'pending';
    public const EMP_OK = 'ok';
    public const EMP_BLOCKED = 'blocked';
    public const EMP_SKIPPED = 'skipped';

    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
        PayrollRateTableRepository::ensureReady();
        PayrollWageTypeRepository::ensureReady();
        if (class_exists('PayrollEmployeeWageAssignmentRepository')) {
            PayrollEmployeeWageAssignmentRepository::ensureReady();
        }
    }

    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            $pdo = Database::pdo();

            return $pdo->query("SHOW TABLES LIKE 'dg_payroll_runs'")->fetchColumn() !== false
                && $pdo->query("SHOW TABLES LIKE 'dg_payroll_run_employees'")->fetchColumn() !== false
                && $pdo->query("SHOW TABLES LIKE 'dg_payroll_run_lines'")->fetchColumn() !== false
                && $pdo->query("SHOW TABLES LIKE 'dg_payroll_run_events'")->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [self::STATUS_DRAFT, self::STATUS_CALCULATED, self::STATUS_APPROVED, self::STATUS_LOCKED];
    }

    public static function requireReason(string $action, string $reason): string
    {
        $reason = trim($reason);
        $need = in_array($action, ['approve', 'lock', 'storno', 'correction'], true);
        if ($need && mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('Begründung erforderlich (mind. 10 Zeichen) für „' . $action . '“.');
        }

        return mb_substr($reason, 0, 500);
    }

    /**
     * @return array<string, mixed>
     */
    public static function createRegular(int $year, int $month, ?int $userId, string $label = ''): array
    {
        self::assertPeriod($year, $month);
        self::ensureReady();
        if (!self::tableReady()) {
            throw new RuntimeException('Monatslauf-Tabellen fehlen (Migration 128).');
        }
        if (self::findActiveRegular($year, $month) !== null) {
            throw new InvalidArgumentException('Für diese Periode existiert bereits ein aktiver Regular-Lauf.');
        }

        $rate = PayrollRateTableRepository::findForPeriod(sprintf('%04d-%02d-01', $year, $month));
        $rateSetId = $rate !== null ? (int) ($rate['id'] ?? 0) : null;
        if ($rateSetId !== null && $rateSetId < 1) {
            $rateSetId = null;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO dg_payroll_runs
                (period_year, period_month, kind, status, rate_set_id, label, created_by_user_id)
             VALUES
                (:y, :m, :kind, :status, :rate_set_id, :label, :uid)'
        );
        $stmt->execute([
            'y' => $year,
            'm' => $month,
            'kind' => self::KIND_REGULAR,
            'status' => self::STATUS_DRAFT,
            'rate_set_id' => $rateSetId,
            'label' => mb_substr($label !== '' ? $label : sprintf('Lohn %02d/%d', $month, $year), 0, 120),
            'uid' => $userId,
        ]);
        $id = (int) $pdo->lastInsertId();
        self::addEvent($id, '', self::STATUS_DRAFT, $userId, 'Lauf angelegt', ['kind' => self::KIND_REGULAR]);

        $run = self::find($id);
        if ($run === null) {
            throw new RuntimeException('Lauf nach Anlegen nicht lesbar.');
        }

        return $run;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findActiveRegular(int $year, int $month): ?array
    {
        self::ensureReady();
        if (!self::tableReady()) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_payroll_runs
             WHERE period_year = :y AND period_month = :m
               AND kind = :kind AND is_superseded = 0
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['y' => $year, 'm' => $month, 'kind' => self::KIND_REGULAR]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        self::ensureReady();
        if ($id < 1 || !self::tableReady()) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_payroll_runs WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForPeriod(int $year, int $month): array
    {
        self::ensureReady();
        if (!self::tableReady()) {
            return [];
        }
        self::assertPeriod($year, $month);
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_payroll_runs
             WHERE period_year = :y AND period_month = :m
             ORDER BY id DESC'
        );
        $stmt->execute(['y' => $year, 'm' => $month]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public static function addEmployee(int $runId, int $contactId): void
    {
        $run = self::requireRun($runId, self::STATUS_DRAFT);
        if ($contactId < 1) {
            throw new InvalidArgumentException('contact_id ungültig.');
        }
        $stmt = Database::pdo()->prepare(
            'INSERT IGNORE INTO dg_payroll_run_employees (run_id, contact_id, status)
             VALUES (:run_id, :contact_id, :status)'
        );
        $stmt->execute([
            'run_id' => (int) $run['id'],
            'contact_id' => $contactId,
            'status' => self::EMP_PENDING,
        ]);
    }

    public static function removeEmployee(int $runId, int $contactId): void
    {
        self::requireRun($runId, self::STATUS_DRAFT);
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM dg_payroll_run_lines WHERE run_id = :r AND contact_id = :c')
            ->execute(['r' => $runId, 'c' => $contactId]);
        $pdo->prepare('DELETE FROM dg_payroll_run_employees WHERE run_id = :r AND contact_id = :c')
            ->execute(['r' => $runId, 'c' => $contactId]);
    }

    /**
     * Ersetzt Lines eines MA (nur draft).
     *
     * @param list<array<string, mixed>> $lines
     */
    public static function replaceLines(int $runId, int $contactId, array $lines): void
    {
        self::requireRun($runId, self::STATUS_DRAFT);
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM dg_payroll_run_lines WHERE run_id = :r AND contact_id = :c')
            ->execute(['r' => $runId, 'c' => $contactId]);
        $ins = $pdo->prepare(
            'INSERT INTO dg_payroll_run_lines
                (run_id, contact_id, source, wage_type_id, code, amount, hours, unit, direction, taxable, subject_to_sv, sort_order, notes)
             VALUES
                (:run_id, :contact_id, :source, :wage_type_id, :code, :amount, :hours, :unit, :direction, :taxable, :subject_to_sv, :sort_order, :notes)'
        );
        $sort = 0;
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $sort++;
            $ins->execute([
                'run_id' => $runId,
                'contact_id' => $contactId,
                'source' => self::normalizeSource((string) ($line['source'] ?? 'manual')),
                'wage_type_id' => isset($line['wage_type_id']) && (int) $line['wage_type_id'] > 0 ? (int) $line['wage_type_id'] : null,
                'code' => mb_substr((string) ($line['code'] ?? ''), 0, 32),
                'amount' => (string) ((float) ($line['amount'] ?? 0)),
                'hours' => isset($line['hours']) && $line['hours'] !== '' && $line['hours'] !== null
                    ? (string) ((float) $line['hours']) : null,
                'unit' => mb_substr((string) ($line['unit'] ?? 'amount'), 0, 16),
                'direction' => in_array(($line['direction'] ?? '') , ['earning', 'deduction'], true)
                    ? (string) $line['direction'] : 'earning',
                'taxable' => !empty($line['taxable']) ? 1 : 0,
                'subject_to_sv' => !empty($line['subject_to_sv']) ? 1 : 0,
                'sort_order' => (int) ($line['sort_order'] ?? $sort),
                'notes' => mb_substr((string) ($line['notes'] ?? ''), 0, 255),
            ]);
        }
    }

    /**
     * Lines aus L3c-Assignments für Periode (soft; nur draft).
     */
    public static function seedLinesFromAssignments(int $runId, int $contactId): int
    {
        $run = self::requireRun($runId, self::STATUS_DRAFT);
        $periodStart = sprintf('%04d-%02d-01', (int) $run['period_year'], (int) $run['period_month']);
        $dt = new DateTimeImmutable($periodStart);
        $periodEnd = $dt->modify('last day of this month')->format('Y-m-d');

        $assignments = PayrollEmployeeWageAssignmentRepository::forContact($contactId);
        $lines = [];
        foreach ($assignments as $a) {
            $from = (string) ($a['valid_from'] ?? '');
            $to = (string) ($a['valid_to'] ?? '');
            if ($from !== '' && $from > $periodEnd) {
                continue;
            }
            if ($to !== '' && $to < $periodStart) {
                continue;
            }
            $unit = (string) ($a['wage_unit'] ?? 'amount');
            $lines[] = [
                'source' => 'assignment',
                'wage_type_id' => (int) ($a['wage_type_id'] ?? 0),
                'code' => (string) ($a['wage_code'] ?? ''),
                'amount' => (float) ($a['amount'] ?? 0),
                'hours' => null,
                'unit' => $unit !== '' ? $unit : 'amount',
                'direction' => 'earning',
                'taxable' => 1,
                'subject_to_sv' => 1,
                'notes' => (string) ($a['notes'] ?? ''),
            ];
        }
        // Direction/flags from wage type if known
        foreach ($lines as &$line) {
            $wtId = (int) ($line['wage_type_id'] ?? 0);
            if ($wtId > 0) {
                $wt = PayrollWageTypeRepository::find($wtId);
                if ($wt !== null) {
                    $line['direction'] = (string) ($wt['direction'] ?? 'earning');
                    $line['taxable'] = !empty($wt['taxable']) ? 1 : 0;
                    $line['subject_to_sv'] = !empty($wt['subject_to_sv']) ? 1 : 0;
                    $line['unit'] = (string) ($wt['unit'] ?? $line['unit']);
                    if ($line['code'] === '') {
                        $line['code'] = (string) ($wt['code'] ?? '');
                    }
                }
            }
        }
        unset($line);

        self::replaceLines($runId, $contactId, $lines);

        return count($lines);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function employees(int $runId): array
    {
        self::ensureReady();
        if (!self::tableReady()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_payroll_run_employees WHERE run_id = :id ORDER BY contact_id ASC'
        );
        $stmt->execute(['id' => $runId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function linesForEmployee(int $runId, int $contactId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_payroll_run_lines
             WHERE run_id = :r AND contact_id = :c
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['r' => $runId, 'c' => $contactId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function events(int $runId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_payroll_run_events WHERE run_id = :id ORDER BY id ASC'
        );
        $stmt->execute(['id' => $runId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * draft → calculated: Engine je MA.
     *
     * @return array{ok_count: int, blocked_count: int, skipped_count: int}
     */
    public static function calculate(int $runId, ?int $userId): array
    {
        $run = self::requireRun($runId, self::STATUS_DRAFT);
        $rateSetId = (int) ($run['rate_set_id'] ?? 0);
        $params = [];
        if ($rateSetId > 0) {
            $params = PayrollRateTableRepository::paramsForSet($rateSetId);
        }
        if ($params === []) {
            $period = sprintf('%04d-%02d-01', (int) $run['period_year'], (int) $run['period_month']);
            $set = PayrollRateTableRepository::findForPeriod($period);
            if ($set === null) {
                throw new InvalidArgumentException('Keine aktive Beitragstabelle für die Periode.');
            }
            $rateSetId = (int) $set['id'];
            $params = PayrollRateTableRepository::paramsForSet($rateSetId);
            Database::pdo()->prepare('UPDATE dg_payroll_runs SET rate_set_id = :rs WHERE id = :id')
                ->execute(['rs' => $rateSetId, 'id' => $runId]);
        }

        $employerLohn = [];
        if (class_exists('PayrollEmployerSettings')) {
            $employerLohn = PayrollEmployerSettings::lohnConfig();
            $snap = PayrollEmployerSettings::employerSnapshot();
            $employerLohn['betriebsnummer'] = (string) ($snap['betriebsnummer'] ?? '');
        }

        $periodYmd = sprintf('%04d-%02d-01', (int) $run['period_year'], (int) $run['period_month']);
        $engine = new StandardPayrollEngine();
        $ok = 0;
        $blocked = 0;
        $skipped = 0;

        foreach (self::employees($runId) as $emp) {
            $contactId = (int) ($emp['contact_id'] ?? 0);
            if (($emp['status'] ?? '') === self::EMP_SKIPPED) {
                $skipped++;
                continue;
            }
            $employeeData = self::loadEmployeePayroll($contactId);
            $lines = self::linesForEmployee($runId, $contactId);
            $reqLines = [];
            foreach ($lines as $ln) {
                $reqLines[] = [
                    'code' => (string) ($ln['code'] ?? ''),
                    'amount' => (float) ($ln['amount'] ?? 0),
                    'hours' => $ln['hours'] !== null ? (float) $ln['hours'] : null,
                    'unit' => (string) ($ln['unit'] ?? 'amount'),
                    'direction' => (string) ($ln['direction'] ?? 'earning'),
                    'taxable' => !empty($ln['taxable']),
                    'subject_to_sv' => !empty($ln['subject_to_sv']),
                ];
            }
            // Soft: hourly/parttime without hours → blocked
            $empType = (string) ($employeeData['payroll_employment_type'] ?? '');
            $hasHourLineWithoutHours = false;
            foreach ($reqLines as $rl) {
                if (($rl['unit'] ?? '') === 'hour' && (float) ($rl['hours'] ?? 0) <= 0) {
                    $hasHourLineWithoutHours = true;
                }
            }
            $snapshot = [
                'employee' => $employeeData,
                'employer_lohn' => $employerLohn,
                'rate_set_id' => $rateSetId,
            ];
            if ($hasHourLineWithoutHours || ($empType === 'parttime' && $reqLines === [])) {
                self::saveEmployeeResult($runId, $contactId, self::EMP_BLOCKED, '', '', null, $snapshot, [
                    'Stundenlohn/Teilzeit ohne Stunden — soft blocked.',
                ], null, null);
                $blocked++;
                continue;
            }

            $request = new PayrollCalcRequest(
                $contactId,
                $periodYmd,
                $employerLohn,
                $employeeData,
                $reqLines,
                $params,
                $rateSetId
            );
            $result = $engine->calculate($request);
            if (!$result->ok) {
                self::saveEmployeeResult(
                    $runId,
                    $contactId,
                    self::EMP_BLOCKED,
                    $result->engine,
                    $result->path,
                    null,
                    $snapshot,
                    $result->warnings,
                    null,
                    null
                );
                $blocked++;
                continue;
            }
            self::saveEmployeeResult(
                $runId,
                $contactId,
                self::EMP_OK,
                $result->engine,
                $result->path,
                $result->amounts,
                $snapshot,
                $result->warnings,
                $result->amount('net_pay'),
                $result->amount('gross_total')
            );
            $ok++;
        }

        self::setStatus($runId, self::STATUS_DRAFT, self::STATUS_CALCULATED, $userId, 'Berechnung', [
            'ok' => $ok,
            'blocked' => $blocked,
            'skipped' => $skipped,
        ]);

        return ['ok_count' => $ok, 'blocked_count' => $blocked, 'skipped_count' => $skipped];
    }

    public static function reopen(int $runId, ?int $userId, string $reason = ''): void
    {
        self::requireRun($runId, self::STATUS_CALCULATED);
        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE dg_payroll_run_employees SET
                status = :st, engine = \'\', path = \'\', result_json = NULL,
                warnings_json = NULL, net_pay = NULL, gross_total = NULL
             WHERE run_id = :id AND status <> :skipped'
        )->execute([
            'st' => self::EMP_PENDING,
            'id' => $runId,
            'skipped' => self::EMP_SKIPPED,
        ]);
        self::setStatus($runId, self::STATUS_CALCULATED, self::STATUS_DRAFT, $userId, $reason !== '' ? $reason : 'Neugeböffnet', []);
    }

    public static function approve(int $runId, ?int $userId, string $reason): void
    {
        $run = self::requireRun($runId, self::STATUS_CALCULATED);
        if (!empty($run['is_superseded'])) {
            throw new InvalidArgumentException('Ersetzter Lauf kann nicht freigegeben werden.');
        }
        $reason = self::requireReason('approve', $reason);
        $blocked = 0;
        foreach (self::employees($runId) as $emp) {
            if (($emp['status'] ?? '') === self::EMP_BLOCKED) {
                $blocked++;
            }
        }
        if ($blocked > 0) {
            throw new InvalidArgumentException('Freigabe blockiert: ' . $blocked . ' MA mit Status blocked.');
        }
        $bn = '';
        if (class_exists('PayrollEmployerSettings')) {
            $snap = PayrollEmployerSettings::employerSnapshot();
            $bn = trim((string) ($snap['betriebsnummer'] ?? ''));
        }
        if ($bn === '') {
            throw new InvalidArgumentException('Freigabe blockiert: AG-Betriebsnummer fehlt.');
        }
        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE dg_payroll_runs SET approved_at = NOW(), approved_by_user_id = :uid WHERE id = :id'
        )->execute(['uid' => $userId, 'id' => $runId]);
        self::setStatus($runId, self::STATUS_CALCULATED, self::STATUS_APPROVED, $userId, $reason, []);
    }

    public static function lock(int $runId, ?int $userId, string $reason): void
    {
        self::requireRun($runId, self::STATUS_APPROVED);
        $reason = self::requireReason('lock', $reason);
        Database::pdo()->prepare(
            'UPDATE dg_payroll_runs SET locked_at = NOW(), locked_by_user_id = :uid WHERE id = :id'
        )->execute(['uid' => $userId, 'id' => $runId]);
        self::setStatus($runId, self::STATUS_APPROVED, self::STATUS_LOCKED, $userId, $reason, []);
    }

    /**
     * Storno oder Correction: neuer draft-Run, Vorgänger is_superseded=1.
     *
     * @return array<string, mixed> neuer Run
     */
    public static function createSuperseding(
        int $sourceRunId,
        string $kind,
        ?int $userId,
        string $reason
    ): array {
        if (!in_array($kind, [self::KIND_STORNO, self::KIND_CORRECTION], true)) {
            throw new InvalidArgumentException('kind muss storno oder correction sein.');
        }
        $reason = self::requireReason($kind, $reason);
        $source = self::find($sourceRunId);
        if ($source === null) {
            throw new InvalidArgumentException('Quell-Lauf nicht gefunden.');
        }
        $st = (string) ($source['status'] ?? '');
        if (!in_array($st, [self::STATUS_APPROVED, self::STATUS_LOCKED], true)) {
            throw new InvalidArgumentException('Storno/Correction nur aus approved/locked.');
        }
        if (!empty($source['is_superseded'])) {
            throw new InvalidArgumentException('Lauf ist bereits ersetzt.');
        }

        self::ensureReady();
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE dg_payroll_runs SET is_superseded = 1 WHERE id = :id')
                ->execute(['id' => $sourceRunId]);
            $stmt = $pdo->prepare(
                'INSERT INTO dg_payroll_runs
                    (period_year, period_month, kind, status, supersedes_run_id, rate_set_id, label, notes, created_by_user_id)
                 VALUES
                    (:y, :m, :kind, :status, :sup, :rate_set_id, :label, :notes, :uid)'
            );
            $label = sprintf(
                '%s zu Run #%d (%02d/%d)',
                $kind === self::KIND_STORNO ? 'Storno' : 'Korrektur',
                $sourceRunId,
                (int) $source['period_month'],
                (int) $source['period_year']
            );
            $stmt->execute([
                'y' => (int) $source['period_year'],
                'm' => (int) $source['period_month'],
                'kind' => $kind,
                'status' => self::STATUS_DRAFT,
                'sup' => $sourceRunId,
                'rate_set_id' => $source['rate_set_id'] ?? null,
                'label' => mb_substr($label, 0, 120),
                'notes' => $reason,
                'uid' => $userId,
            ]);
            $newId = (int) $pdo->lastInsertId();
            self::addEvent($newId, '', self::STATUS_DRAFT, $userId, $reason, [
                'kind' => $kind,
                'supersedes_run_id' => $sourceRunId,
            ]);
            self::addEvent($sourceRunId, $st, $st, $userId, 'Ersetzt durch Run #' . $newId . ': ' . $reason, [
                'superseded_by' => $newId,
                'kind' => $kind,
            ]);
            // MA + Lines vom Vorgänger kopieren (als Startpunkt)
            foreach (self::employees($sourceRunId) as $emp) {
                $cid = (int) $emp['contact_id'];
                $pdo->prepare(
                    'INSERT INTO dg_payroll_run_employees (run_id, contact_id, status)
                     VALUES (:r, :c, :st)'
                )->execute(['r' => $newId, 'c' => $cid, 'st' => self::EMP_PENDING]);
                $lines = self::linesForEmployee($sourceRunId, $cid);
                $mapped = [];
                foreach ($lines as $ln) {
                    $mapped[] = [
                        'source' => $ln['source'] ?? 'manual',
                        'wage_type_id' => $ln['wage_type_id'] ?? null,
                        'code' => $ln['code'] ?? '',
                        'amount' => $ln['amount'] ?? 0,
                        'hours' => $ln['hours'] ?? null,
                        'unit' => $ln['unit'] ?? 'amount',
                        'direction' => $ln['direction'] ?? 'earning',
                        'taxable' => $ln['taxable'] ?? 1,
                        'subject_to_sv' => $ln['subject_to_sv'] ?? 1,
                        'sort_order' => $ln['sort_order'] ?? 0,
                        'notes' => $ln['notes'] ?? '',
                    ];
                }
                // replaceLines checks draft — new run is draft; but we're in transaction and status is draft
                self::replaceLines($newId, $cid, $mapped);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $run = self::find($newId);
        if ($run === null) {
            throw new RuntimeException('Neuer Lauf nicht lesbar.');
        }

        return $run;
    }

    public static function deleteDraft(int $runId): void
    {
        $run = self::requireRun($runId, self::STATUS_DRAFT);
        if ((string) ($run['kind'] ?? '') !== self::KIND_REGULAR && (int) ($run['supersedes_run_id'] ?? 0) > 0) {
            // Correction/storno draft: beim Löschen Vorgänger wieder aktivieren
            Database::pdo()->prepare(
                'UPDATE dg_payroll_runs SET is_superseded = 0 WHERE id = :id'
            )->execute(['id' => (int) $run['supersedes_run_id']]);
        }
        Database::pdo()->prepare('DELETE FROM dg_payroll_runs WHERE id = :id AND status = :st')
            ->execute(['id' => $runId, 'st' => self::STATUS_DRAFT]);
    }

    private static function assertPeriod(int $year, int $month): void
    {
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            throw new InvalidArgumentException('Ungültige Periode.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function requireRun(int $runId, string $expectedStatus): array
    {
        $run = self::find($runId);
        if ($run === null) {
            throw new InvalidArgumentException('Lauf nicht gefunden.');
        }
        if ((string) ($run['status'] ?? '') !== $expectedStatus) {
            throw new InvalidArgumentException(
                'Lauf-Status ist „' . ($run['status'] ?? '') . '“, erwartet „' . $expectedStatus . '“.'
            );
        }

        return $run;
    }

    private static function normalizeSource(string $source): string
    {
        $allowed = ['assignment', 'time', 'manual', 'ot_payout'];

        return in_array($source, $allowed, true) ? $source : 'manual';
    }

    /**
     * @param array<string, mixed>|null $amounts
     * @param array<string, mixed> $snapshot
     * @param list<string> $warnings
     */
    private static function saveEmployeeResult(
        int $runId,
        int $contactId,
        string $status,
        string $engine,
        string $path,
        ?array $amounts,
        array $snapshot,
        array $warnings,
        ?float $netPay,
        ?float $grossTotal
    ): void {
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_payroll_run_employees SET
                status = :status,
                engine = :engine,
                path = :path,
                result_json = :result_json,
                snapshot_json = :snapshot_json,
                warnings_json = :warnings_json,
                net_pay = :net_pay,
                gross_total = :gross_total
             WHERE run_id = :run_id AND contact_id = :contact_id'
        );
        $stmt->execute([
            'status' => $status,
            'engine' => mb_substr($engine, 0, 32),
            'path' => mb_substr($path, 0, 16),
            'result_json' => $amounts !== null ? json_encode($amounts, JSON_UNESCAPED_UNICODE) : null,
            'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'warnings_json' => json_encode(array_values($warnings), JSON_UNESCAPED_UNICODE),
            'net_pay' => $netPay,
            'gross_total' => $grossTotal,
            'run_id' => $runId,
            'contact_id' => $contactId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadEmployeePayroll(int $contactId): array
    {
        if (!class_exists('ContactRepository')) {
            return [];
        }
        $contact = ContactRepository::findById($contactId);
        if ($contact === null) {
            return [];
        }

        return is_array($contact->employeeData) ? $contact->employeeData : [];
    }

    /**
     * @param array<string, mixed> $details
     */
    private static function setStatus(
        int $runId,
        string $from,
        string $to,
        ?int $userId,
        string $reason,
        array $details
    ): void {
        Database::pdo()->prepare('UPDATE dg_payroll_runs SET status = :st WHERE id = :id')
            ->execute(['st' => $to, 'id' => $runId]);
        self::addEvent($runId, $from, $to, $userId, $reason, $details);
    }

    /**
     * @param array<string, mixed> $details
     */
    private static function addEvent(
        int $runId,
        string $from,
        string $to,
        ?int $userId,
        string $reason,
        array $details
    ): void {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_payroll_run_events
                (run_id, from_status, to_status, user_id, reason, details_json)
             VALUES
                (:run_id, :from_status, :to_status, :user_id, :reason, :details_json)'
        );
        $stmt->execute([
            'run_id' => $runId,
            'from_status' => mb_substr($from, 0, 16),
            'to_status' => mb_substr($to, 0, 16),
            'user_id' => $userId,
            'reason' => mb_substr($reason, 0, 500),
            'details_json' => $details !== [] ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        ]);
        if (class_exists('AuditLog')) {
            AuditLog::record('payroll_run_status', $userId, json_encode([
                'run_id' => $runId,
                'from' => $from,
                'to' => $to,
                'reason' => $reason,
            ], JSON_UNESCAPED_UNICODE));
        }
    }
}
