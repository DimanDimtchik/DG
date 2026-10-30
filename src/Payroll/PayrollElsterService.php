<?php
declare(strict_types=1);

/**
 * Lohn L10b: LSt-Anmeldung / Bescheinigung — Vorschau, Kontroll-CSV, Test-Payload/Audit.
 * Kein Live-Senden (L10c). Kein UStVA-Mix. Kein ELStAM.
 */
final class PayrollElsterService
{
    public const FORM_LSTA = 'lsta';
    public const FORM_LSTB = 'lstb';
    public const PAYLOAD_SCHEMA = 'DG-LSTA-PREP-1';

    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
        PayrollRunRepository::ensureReady();
    }

    public static function storageDir(): string
    {
        return DG_ROOT . '/storage/payroll/elster';
    }

    public static function isLstaLiveFlag(): bool
    {
        if (!class_exists('PayrollEmployerSettings')) {
            return false;
        }
        $lohn = PayrollEmployerSettings::lohnConfig();

        return !empty($lohn['payroll_lsta_live']);
    }

    /**
     * @return list<string>
     */
    public static function auditorChecklist(): array
    {
        return [
            'Nur locked, nicht superseded; Storno-Runs nicht in LStA-Summen.',
            'LSt / SolZ / KiSt getrennt; flat_lst nicht still zur LSt addieren.',
            'Kein UStVA-XML / keine buchhaltung-ustva-Buttons für Lohnsteuer.',
            'Live-Senden erst L10c nach Gate G2 — L10b nur Test/Vorschau.',
            'Kasserver: kein ERiC — nur Kontroll-CSV und Payload-Datei lokal.',
            'Test-Zertifikat ≠ Prod-Zertifikat; eric_test_mode prüfen.',
            'Steuernummer/Finanzamt aus AG-Stamm; IdNr. für Bescheinigung 11-stellig.',
            'Payload + Checksumme in dg_elster_submissions (form_type=lsta/lstb) prüfen.',
        ];
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    public static function decodeAmounts(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Pure Aggregation LStA (ohne DB).
     *
     * @param list<array<string, mixed>> $runs
     * @param array<int, list<array<string, mixed>>> $employeesByRunId
     * @param array<string, mixed> $employerSnapshot
     * @return array<string, mixed>
     */
    public static function aggregateLsta(
        int $year,
        int $month,
        array $runs,
        array $employeesByRunId,
        array $employerSnapshot = []
    ): array {
        $warnings = [];
        $sourceRunIds = [];
        $totals = [
            'lst' => 0.0,
            'solz' => 0.0,
            'kst' => 0.0,
            'flat_lst' => 0.0,
            'flat_other' => 0.0,
            'employee_count' => 0,
            'gross_taxable' => 0.0,
        ];
        $skippedStorno = 0;

        foreach ($runs as $run) {
            if (!is_array($run)) {
                continue;
            }
            if ((string) ($run['status'] ?? '') !== PayrollRunRepository::STATUS_LOCKED) {
                continue;
            }
            if (!empty($run['is_superseded'])) {
                continue;
            }
            $kind = (string) ($run['kind'] ?? PayrollRunRepository::KIND_REGULAR);
            if ($kind === PayrollRunRepository::KIND_STORNO) {
                $skippedStorno++;
                continue;
            }
            if (!in_array($kind, [
                PayrollRunRepository::KIND_REGULAR,
                PayrollRunRepository::KIND_CORRECTION,
                '',
            ], true)) {
                continue;
            }
            if ((int) ($run['period_year'] ?? 0) !== $year || (int) ($run['period_month'] ?? 0) !== $month) {
                continue;
            }
            $runId = (int) ($run['id'] ?? 0);
            $sourceRunIds[] = $runId;
            $emps = is_array($employeesByRunId[$runId] ?? null) ? $employeesByRunId[$runId] : [];
            foreach ($emps as $emp) {
                if (!is_array($emp) || ($emp['status'] ?? '') !== PayrollRunRepository::EMP_OK) {
                    continue;
                }
                $amounts = self::decodeAmounts($emp['result_json'] ?? null);
                if ($amounts === []) {
                    $warnings[] = 'MA #' . (int) ($emp['contact_id'] ?? 0)
                        . ' Run #' . $runId . ': result_json fehlt.';
                    continue;
                }
                $totals['lst'] = round($totals['lst'] + (float) ($amounts['lst'] ?? 0), 2);
                $totals['solz'] = round($totals['solz'] + (float) ($amounts['solz'] ?? 0), 2);
                $totals['kst'] = round($totals['kst'] + (float) ($amounts['kst'] ?? 0), 2);
                $totals['flat_lst'] = round($totals['flat_lst'] + (float) ($amounts['flat_lst'] ?? 0), 2);
                $totals['flat_other'] = round($totals['flat_other'] + (float) ($amounts['flat_other'] ?? 0), 2);
                $totals['gross_taxable'] = round(
                    $totals['gross_taxable'] + (float) ($amounts['gross_taxable'] ?? $amounts['gross_total'] ?? 0),
                    2
                );
                $totals['employee_count']++;
            }
        }

        if ($skippedStorno > 0) {
            $warnings[] = $skippedStorno . ' Storno-Run(s) nicht in LStA-Summen.';
        }
        $taxNumber = trim((string) ($employerSnapshot['tax_number_est'] ?? ''));
        $fa = is_array($employerSnapshot['finanzamt'] ?? null) ? $employerSnapshot['finanzamt'] : [];
        $faName = trim((string) ($fa['name'] ?? $fa['label'] ?? ''));
        $faBufo = trim((string) ($fa['bufo'] ?? $fa['bufa_nr'] ?? $fa['number'] ?? ''));
        if ($taxNumber === '') {
            $warnings[] = 'AG-Steuernummer fehlt — Senden blockiert.';
        }
        if ($faName === '' && $faBufo === '') {
            $warnings[] = 'Finanzamt/BuFö fehlt — Senden blockiert.';
        }
        if ($sourceRunIds === []) {
            $warnings[] = 'Kein geeigneter locked Run in der Periode.';
        }

        $canSendStructurally = $taxNumber !== '' && ($faName !== '' || $faBufo !== '') && $sourceRunIds !== [];

        return [
            'year' => $year,
            'month' => $month,
            'form_type' => self::FORM_LSTA,
            'amounts' => $totals,
            'source_run_ids' => $sourceRunIds,
            'warnings' => array_values(array_unique($warnings)),
            'employer_name' => (string) ($employerSnapshot['employer_name'] ?? ''),
            'tax_number_est' => $taxNumber,
            'finanzamt_name' => $faName,
            'finanzamt_bufo' => $faBufo,
            'can_send_structurally' => $canSendStructurally,
            'lsta_live_flag' => self::isLstaLiveFlag(),
            'schema' => self::PAYLOAD_SCHEMA,
        ];
    }

    /**
     * Pure LStB-Zeilen (ohne DB).
     *
     * @param list<array<string, mixed>> $employeeMonthRows
     *   Jede Zeile: contact_id, label?, snapshot employee fields, amounts from one month
     * @return array<string, mixed>
     */
    public static function aggregateLstb(int $year, array $employeeMonthRows): array
    {
        $byContact = [];
        $warnings = [];
        foreach ($employeeMonthRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cid = (int) ($row['contact_id'] ?? 0);
            if ($cid < 1) {
                continue;
            }
            if (!isset($byContact[$cid])) {
                $byContact[$cid] = [
                    'contact_id' => $cid,
                    'label' => (string) ($row['label'] ?? ('MA #' . $cid)),
                    'tax_id' => (string) ($row['tax_id'] ?? ''),
                    'tax_class' => (string) ($row['tax_class'] ?? ''),
                    'religion_code' => (string) ($row['religion_code'] ?? ''),
                    'entry_date' => (string) ($row['entry_date'] ?? ''),
                    'exit_date' => (string) ($row['exit_date'] ?? ''),
                    'lst' => 0.0,
                    'solz' => 0.0,
                    'kst' => 0.0,
                    'gross_taxable' => 0.0,
                    'months' => 0,
                ];
            }
            $byContact[$cid]['lst'] = round($byContact[$cid]['lst'] + (float) ($row['lst'] ?? 0), 2);
            $byContact[$cid]['solz'] = round($byContact[$cid]['solz'] + (float) ($row['solz'] ?? 0), 2);
            $byContact[$cid]['kst'] = round($byContact[$cid]['kst'] + (float) ($row['kst'] ?? 0), 2);
            $byContact[$cid]['gross_taxable'] = round(
                $byContact[$cid]['gross_taxable'] + (float) ($row['gross_taxable'] ?? 0),
                2
            );
            $byContact[$cid]['months']++;
            if (trim((string) ($row['tax_id'] ?? '')) !== '') {
                $byContact[$cid]['tax_id'] = preg_replace('/\D+/', '', (string) $row['tax_id']) ?? '';
            }
        }
        $rows = [];
        $excluded = [];
        foreach ($byContact as $row) {
            $idNr = preg_replace('/\D+/', '', (string) ($row['tax_id'] ?? '')) ?? '';
            $ok = strlen($idNr) === 11;
            $row['tax_id'] = $idNr;
            $row['eligible'] = $ok;
            if (!$ok) {
                $excluded[] = 'MA #' . $row['contact_id'] . ': IdNr. fehlt/ungültig — von Bescheinigung ausgeschlossen.';
            }
            $rows[] = $row;
        }
        usort($rows, static fn (array $a, array $b): int => $a['contact_id'] <=> $b['contact_id']);

        return [
            'year' => $year,
            'form_type' => self::FORM_LSTB,
            'rows' => $rows,
            'warnings' => array_merge($warnings, $excluded),
            'eligible_count' => count(array_filter($rows, static fn (array $r): bool => !empty($r['eligible']))),
        ];
    }

    /**
     * @param array<string, mixed> $preview
     */
    public static function buildLstaPayloadXml(array $preview): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $a = is_array($preview['amounts'] ?? null) ? $preview['amounts'] : [];
        $runs = implode(',', array_map('intval', is_array($preview['source_run_ids'] ?? null) ? $preview['source_run_ids'] : []));

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<DgPayrollLsta schema="' . $e(self::PAYLOAD_SCHEMA) . '" note="Vorbereitungspayload — kein amtliches ELSTER-Schema">'
            . '<Period year="' . (int) ($preview['year'] ?? 0) . '" month="' . (int) ($preview['month'] ?? 0) . '"/>'
            . '<Employer name="' . $e((string) ($preview['employer_name'] ?? '')) . '"'
            . ' taxNumber="' . $e((string) ($preview['tax_number_est'] ?? '')) . '"'
            . ' finanzamt="' . $e((string) ($preview['finanzamt_name'] ?? '')) . '"'
            . ' bufo="' . $e((string) ($preview['finanzamt_bufo'] ?? '')) . '"/>'
            . '<SourceRuns>' . $e($runs) . '</SourceRuns>'
            . '<Amounts>'
            . '<Lst>' . number_format((float) ($a['lst'] ?? 0), 2, '.', '') . '</Lst>'
            . '<SolZ>' . number_format((float) ($a['solz'] ?? 0), 2, '.', '') . '</SolZ>'
            . '<KiSt>' . number_format((float) ($a['kst'] ?? 0), 2, '.', '') . '</KiSt>'
            . '<FlatLst separate="true">' . number_format((float) ($a['flat_lst'] ?? 0), 2, '.', '') . '</FlatLst>'
            . '<FlatOther separate="true">' . number_format((float) ($a['flat_other'] ?? 0), 2, '.', '') . '</FlatOther>'
            . '<GrossTaxable>' . number_format((float) ($a['gross_taxable'] ?? 0), 2, '.', '') . '</GrossTaxable>'
            . '<EmployeeCount>' . (int) ($a['employee_count'] ?? 0) . '</EmployeeCount>'
            . '</Amounts>'
            . '</DgPayrollLsta>';
    }

    /**
     * @param array<string, mixed> $preview
     */
    public static function toCsvLsta(array $preview): string
    {
        $a = is_array($preview['amounts'] ?? null) ? $preview['amounts'] : [];
        $lines = [];
        $lines[] = self::csvLine([
            'Jahr', 'Monat', 'Steuernummer', 'Finanzamt', 'BuFö', 'Run-IDs',
            'LSt', 'SolZ', 'KiSt', 'Flat-LSt', 'Flat-Sonstig', 'Stpfl.Brutto', 'MA',
        ]);
        $lines[] = self::csvLine([
            (string) (int) ($preview['year'] ?? 0),
            sprintf('%02d', (int) ($preview['month'] ?? 0)),
            (string) ($preview['tax_number_est'] ?? ''),
            (string) ($preview['finanzamt_name'] ?? ''),
            (string) ($preview['finanzamt_bufo'] ?? ''),
            implode('|', is_array($preview['source_run_ids'] ?? null) ? $preview['source_run_ids'] : []),
            self::csvMoney((float) ($a['lst'] ?? 0)),
            self::csvMoney((float) ($a['solz'] ?? 0)),
            self::csvMoney((float) ($a['kst'] ?? 0)),
            self::csvMoney((float) ($a['flat_lst'] ?? 0)),
            self::csvMoney((float) ($a['flat_other'] ?? 0)),
            self::csvMoney((float) ($a['gross_taxable'] ?? 0)),
            (string) (int) ($a['employee_count'] ?? 0),
        ]);

        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    /**
     * @param array<string, mixed> $preview
     */
    public static function toCsvLstb(array $preview): string
    {
        $lines = [];
        $lines[] = self::csvLine([
            'Jahr', 'Kontakt-ID', 'Name', 'IdNr', 'StKl', 'Religion',
            'LSt', 'SolZ', 'KiSt', 'Stpfl.Brutto', 'Monate', 'Eligibel',
        ]);
        foreach (is_array($preview['rows'] ?? null) ? $preview['rows'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $lines[] = self::csvLine([
                (string) (int) ($preview['year'] ?? 0),
                (string) (int) ($row['contact_id'] ?? 0),
                (string) ($row['label'] ?? ''),
                (string) ($row['tax_id'] ?? ''),
                (string) ($row['tax_class'] ?? ''),
                (string) ($row['religion_code'] ?? ''),
                self::csvMoney((float) ($row['lst'] ?? 0)),
                self::csvMoney((float) ($row['solz'] ?? 0)),
                self::csvMoney((float) ($row['kst'] ?? 0)),
                self::csvMoney((float) ($row['gross_taxable'] ?? 0)),
                (string) (int) ($row['months'] ?? 0),
                !empty($row['eligible']) ? 'ja' : 'nein',
            ]);
        }

        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    /**
     * @return array<string, mixed>
     */
    public static function previewLsta(int $year, int $month): array
    {
        self::ensureReady();
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            throw new InvalidArgumentException('Periode ungültig.');
        }
        $runs = PayrollRunRepository::listForPeriod($year, $month);
        $byRun = [];
        foreach ($runs as $run) {
            $rid = (int) ($run['id'] ?? 0);
            if ($rid > 0) {
                $byRun[$rid] = PayrollRunRepository::employees($rid);
            }
        }
        $snap = class_exists('PayrollEmployerSettings')
            ? PayrollEmployerSettings::employerSnapshot()
            : [];

        return self::aggregateLsta($year, $month, $runs, $byRun, $snap);
    }

    /**
     * @return array<string, mixed>
     */
    public static function previewLstb(int $year, ?int $onlyContactId = null): array
    {
        self::ensureReady();
        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException('Jahr ungültig.');
        }
        $monthRows = [];
        for ($m = 1; $m <= 12; $m++) {
            $runs = PayrollRunRepository::listForPeriod($year, $m);
            foreach ($runs as $run) {
                if ((string) ($run['status'] ?? '') !== PayrollRunRepository::STATUS_LOCKED) {
                    continue;
                }
                if (!empty($run['is_superseded'])) {
                    continue;
                }
                $kind = (string) ($run['kind'] ?? '');
                if ($kind === PayrollRunRepository::KIND_STORNO) {
                    continue;
                }
                $runId = (int) ($run['id'] ?? 0);
                foreach (PayrollRunRepository::employees($runId) as $emp) {
                    if (($emp['status'] ?? '') !== PayrollRunRepository::EMP_OK) {
                        continue;
                    }
                    $cid = (int) ($emp['contact_id'] ?? 0);
                    if ($onlyContactId !== null && $cid !== $onlyContactId) {
                        continue;
                    }
                    $amounts = self::decodeAmounts($emp['result_json'] ?? null);
                    $snap = [];
                    if (is_string($emp['snapshot_json'] ?? null) && $emp['snapshot_json'] !== '') {
                        $decoded = json_decode((string) $emp['snapshot_json'], true);
                        $snap = is_array($decoded['employee'] ?? null) ? $decoded['employee'] : [];
                    }
                    $monthRows[] = [
                        'contact_id' => $cid,
                        'label' => PayrollRunRepository::contactLabel($cid),
                        'tax_id' => (string) ($snap['tax_id'] ?? ''),
                        'tax_class' => (string) ($snap['tax_class'] ?? ''),
                        'religion_code' => (string) ($snap['religion_code'] ?? ''),
                        'entry_date' => (string) ($snap['entry_date'] ?? ''),
                        'exit_date' => (string) ($snap['exit_date'] ?? ''),
                        'lst' => (float) ($amounts['lst'] ?? 0),
                        'solz' => (float) ($amounts['solz'] ?? 0),
                        'kst' => (float) ($amounts['kst'] ?? 0),
                        'gross_taxable' => (float) ($amounts['gross_taxable'] ?? $amounts['gross_total'] ?? 0),
                    ];
                }
            }
        }

        return self::aggregateLstb($year, $monthRows);
    }

    public static function canAttemptEricTest(): bool
    {
        if (!class_exists('ElsterSettings') || !class_exists('ElsterEricClient')) {
            return false;
        }
        if (!ElsterSettings::serverSupportsEric()) {
            return false;
        }
        $cfg = ElsterSettings::forForm();
        if (empty($cfg['eric_test_mode'])) {
            return false;
        }

        return ElsterEricClient::isReady();
    }

    /**
     * Test-Abgabe L10b: speichert Payload + Meta; ruft Worker nur wenn ready.
     * Live immer verboten.
     *
     * @return array{submission_id: int|null, filename: string, checksum: string, xml: string, worker: string}
     */
    public static function submitTestLsta(int $year, int $month, ?int $userId, string $reason = ''): array
    {
        self::ensureReady();
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('Begründung mindestens 10 Zeichen.');
        }

        $preview = self::previewLsta($year, $month);
        if (empty($preview['can_send_structurally'])) {
            throw new InvalidArgumentException(
                'LStA strukturell unvollständig: ' . implode(' ', $preview['warnings'] ?? [])
            );
        }
        $xml = self::buildLstaPayloadXml($preview);
        $checksum = hash('sha256', $xml);
        $filename = sprintf('lohn-lsta-%04d-%02d-%s.xml', $year, $month, date('Ymd-His'));
        $rel = sprintf('%04d/%02d/%s', $year, $month, $filename);
        self::writeStorage($rel, $xml);

        $workerNote = 'lokal gespeichert (kein Worker-Call)';
        if (self::canAttemptEricTest()) {
            try {
                $workerNote = self::callEricTestStub($xml);
            } catch (Throwable $e) {
                $workerNote = 'Worker-Fehler: ' . $e->getMessage();
            }
        } elseif (!ElsterSettings::serverSupportsEric()) {
            $workerNote = 'Kasserver/kein server_ready — nur lokale Payload (erwartet).';
        } else {
            $workerNote = 'ERiC-Test nicht ready — Payload lokal, kein Versand.';
        }

        $submissionId = self::insertSubmission([
            'form_type' => self::FORM_LSTA,
            'period_year' => $year,
            'period_month' => $month,
            'status' => 'test_prepared',
            'test_mode' => 1,
            'transfer_ticket' => '',
            'response_summary' => mb_substr($workerNote, 0, 2000),
            'payload_checksum' => $checksum,
            'detail_json' => json_encode([
                'reason' => $reason,
                'schema' => self::PAYLOAD_SCHEMA,
                'amounts' => $preview['amounts'],
                'source_run_ids' => $preview['source_run_ids'],
                'storage_path' => $rel,
                'filename' => $filename,
                'warnings' => $preview['warnings'],
            ], JSON_UNESCAPED_UNICODE),
            'submitted_by' => $userId,
        ]);

        if (class_exists('AuditLog')) {
            AuditLog::record(
                'payroll_lsta_test_prepared',
                $userId,
                json_encode([
                    'year' => $year,
                    'month' => $month,
                    'checksum' => $checksum,
                    'submission_id' => $submissionId,
                ], JSON_UNESCAPED_UNICODE)
            );
        }

        return [
            'submission_id' => $submissionId,
            'filename' => $filename,
            'checksum' => $checksum,
            'xml' => $xml,
            'worker' => $workerNote,
        ];
    }

    /**
     * Live immer blockiert bis L10c.
     */
    public static function submitLiveLsta(int $year, int $month, ?int $userId, string $reason = ''): never
    {
        throw new RuntimeException(
            'Live-Lohnsteuer-Anmeldung ist in L10b deaktiviert. Erst L10c nach Gate G2 / Nutzerbefehl.'
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listRecentSubmissions(int $limit = 20): array
    {
        if (!Database::isConfigured() || !self::submissionsTableReady()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM dg_elster_submissions
             WHERE form_type IN ('lsta','lstb')
             ORDER BY id DESC LIMIT :lim"
        );
        $stmt->bindValue('lim', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    private static function submissionsTableReady(): bool
    {
        try {
            return Database::pdo()->query("SHOW TABLES LIKE 'dg_elster_submissions'")->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function insertSubmission(array $row): ?int
    {
        if (!self::submissionsTableReady()) {
            return null;
        }
        $hasExtra = self::hasPayrollColumns();
        if ($hasExtra) {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO dg_elster_submissions
                    (form_type, period_year, period_month, status, test_mode, transfer_ticket,
                     response_summary, payload_checksum, detail_json, submitted_by, submitted_at)
                 VALUES
                    (:form_type, :y, :m, :status, :test_mode, :ticket,
                     :summary, :checksum, :detail, :uid, NOW())'
            );
            $stmt->execute([
                'form_type' => (string) ($row['form_type'] ?? self::FORM_LSTA),
                'y' => (int) ($row['period_year'] ?? 0),
                'm' => (int) ($row['period_month'] ?? 0),
                'status' => (string) ($row['status'] ?? 'draft'),
                'test_mode' => !empty($row['test_mode']) ? 1 : 0,
                'ticket' => (string) ($row['transfer_ticket'] ?? ''),
                'summary' => (string) ($row['response_summary'] ?? ''),
                'checksum' => (string) ($row['payload_checksum'] ?? ''),
                'detail' => (string) ($row['detail_json'] ?? ''),
                'uid' => $row['submitted_by'] ?? null,
            ]);
        } else {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO dg_elster_submissions
                    (form_type, period_year, period_month, status, test_mode, transfer_ticket,
                     response_summary, submitted_by, submitted_at)
                 VALUES
                    (:form_type, :y, :m, :status, :test_mode, :ticket,
                     :summary, :uid, NOW())'
            );
            $stmt->execute([
                'form_type' => (string) ($row['form_type'] ?? self::FORM_LSTA),
                'y' => (int) ($row['period_year'] ?? 0),
                'm' => (int) ($row['period_month'] ?? 0),
                'status' => (string) ($row['status'] ?? 'draft'),
                'test_mode' => !empty($row['test_mode']) ? 1 : 0,
                'ticket' => (string) ($row['transfer_ticket'] ?? ''),
                'summary' => mb_substr(
                    (string) ($row['response_summary'] ?? '') . ' checksum=' . ($row['payload_checksum'] ?? ''),
                    0,
                    2000
                ),
                'uid' => $row['submitted_by'] ?? null,
            ]);
        }

        return (int) Database::pdo()->lastInsertId();
    }

    private static function hasPayrollColumns(): bool
    {
        try {
            $pdo = Database::pdo();
            $stmt = $pdo->query("SHOW COLUMNS FROM dg_elster_submissions LIKE 'payload_checksum'");

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    private static function callEricTestStub(string $xml): string
    {
        // Bis Worker LStA kennt: lokale Validierung + Hinweis (analog UStVA-Stub).
        if (strlen($xml) < 50 || !str_contains($xml, 'DgPayrollLsta')) {
            throw new RuntimeException('Payload ungültig.');
        }

        return 'ERiC-Test bereit: Payload validiert lokal (amtliches LStA-Schema folgt mit Worker-Anbindung).';
    }

    private static function writeStorage(string $relative, string $xml): void
    {
        $dir = self::storageDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $abs = $dir . '/' . ltrim($relative, '/');
        $parent = dirname($abs);
        if (!is_dir($parent)) {
            mkdir($parent, 0750, true);
        }
        if (file_put_contents($abs, $xml) === false) {
            throw new RuntimeException('LStA-Payload konnte nicht gespeichert werden.');
        }
    }

    /**
     * @param list<string|int|float> $cols
     */
    private static function csvLine(array $cols): string
    {
        $out = [];
        foreach ($cols as $c) {
            $s = (string) $c;
            if (str_contains($s, '"') || str_contains($s, ';') || str_contains($s, "\n") || str_contains($s, "\r")) {
                $s = '"' . str_replace('"', '""', $s) . '"';
            }
            $out[] = $s;
        }

        return implode(';', $out);
    }

    private static function csvMoney(float $v): string
    {
        return number_format($v, 2, ',', '');
    }
}
