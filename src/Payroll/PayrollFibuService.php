<?php
declare(strict_types=1);

/**
 * Lohn L7b: Sammelbuchung locked Run → dg_ledger_postings (source=payroll).
 * Keine SEPA, keine USt auf Lohn.
 */
final class PayrollFibuService
{
    public const SOURCE = 'payroll';

    public const KIND_REGULAR = 'regular';
    public const KIND_STORNO_CONTRA = 'storno_contra';
    public const KIND_CORRECTION = 'correction';

    public const ROLE_EXPENSE_WAGES = 'expense_wages';
    public const ROLE_EXPENSE_SOCIAL = 'expense_social';
    public const ROLE_LIABILITY_NET = 'liability_net';
    public const ROLE_LIABILITY_TAX = 'liability_tax';
    public const ROLE_LIABILITY_SV = 'liability_sv';

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
            return Database::pdo()->query("SHOW TABLES LIKE 'dg_payroll_fibu_batches'")->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    public static function isLive(): bool
    {
        if (!class_exists('PayrollEmployerSettings')) {
            return false;
        }
        $lohn = PayrollEmployerSettings::lohnConfig();

        return !empty($lohn['payroll_fibu_live']);
    }

    public static function markerForRun(int $runId): string
    {
        return 'payroll:run:' . max(1, $runId);
    }

    /**
     * @return array<string, string> role => account_number
     */
    public static function defaultAccounts(string $skrType): array
    {
        $skr = class_exists('ChartOfAccountsSettings')
            ? ChartOfAccountsSettings::sanitizeSkrType($skrType)
            : (str_contains(strtolower($skrType), '04') ? 'skr04' : 'skr03');

        if ($skr === 'skr04') {
            return [
                self::ROLE_EXPENSE_WAGES => '6200',
                self::ROLE_EXPENSE_SOCIAL => '6220',
                self::ROLE_LIABILITY_NET => '3500',
                self::ROLE_LIABILITY_TAX => '3510',
                self::ROLE_LIABILITY_SV => '3520',
            ];
        }

        return [
            self::ROLE_EXPENSE_WAGES => '4120',
            self::ROLE_EXPENSE_SOCIAL => '4130',
            self::ROLE_LIABILITY_NET => '1740',
            self::ROLE_LIABILITY_TAX => '1741',
            self::ROLE_LIABILITY_SV => '1742',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function resolveAccounts(?array $overrides = null, ?string $skrType = null): array
    {
        $skr = $skrType;
        if ($skr === null || $skr === '') {
            $skr = class_exists('ChartOfAccountsSettings')
                ? ChartOfAccountsSettings::activeSkrType()
                : 'skr03';
        }
        $accounts = self::defaultAccounts($skr);
        if ($overrides === null && class_exists('PayrollEmployerSettings')) {
            $lohn = PayrollEmployerSettings::lohnConfig();
            $overrides = is_array($lohn['fibu_accounts'] ?? null) ? $lohn['fibu_accounts'] : [];
        }
        if (is_array($overrides)) {
            foreach ($accounts as $role => $default) {
                $ov = preg_replace('/\D/', '', (string) ($overrides[$role] ?? '')) ?? '';
                if ($ov !== '') {
                    $accounts[$role] = $ov;
                }
            }
        }

        return $accounts;
    }

    /**
     * @param list<array<string, mixed>> $employees dg_payroll_run_employees rows
     * @return array{
     *   gross_total: float, lst: float, solz: float, kst: float,
     *   sv_an: float, sv_ag: float, umlagen: float, flat: float,
     *   net_deductions: float, net_pay: float, employer_cost: float,
     *   fa: float, employee_count: int, warnings: list<string>
     * }
     */
    public static function aggregateAmounts(array $employees): array
    {
        $sum = [
            'gross_total' => 0.0,
            'lst' => 0.0,
            'solz' => 0.0,
            'kst' => 0.0,
            'sv_an' => 0.0,
            'sv_ag' => 0.0,
            'umlagen' => 0.0,
            'flat' => 0.0,
            'net_deductions' => 0.0,
            'net_pay' => 0.0,
            'employer_cost' => 0.0,
            'employee_count' => 0,
            'warnings' => [],
        ];
        foreach ($employees as $emp) {
            if (!is_array($emp)) {
                continue;
            }
            if (($emp['status'] ?? '') !== PayrollRunRepository::EMP_OK) {
                continue;
            }
            $amounts = self::decodeAmounts($emp['result_json'] ?? null);
            if ($amounts === []) {
                throw new InvalidArgumentException(
                    'MA #' . (int) ($emp['contact_id'] ?? 0) . ': result_json fehlt — nicht buchbar.'
                );
            }
            $b = (float) ($amounts['gross_total'] ?? $emp['gross_total'] ?? 0);
            $lst = (float) ($amounts['lst'] ?? 0);
            $solz = (float) ($amounts['solz'] ?? 0);
            $kst = (float) ($amounts['kst'] ?? 0);
            $svAn = (float) ($amounts['kv_an'] ?? 0)
                + (float) ($amounts['rv_an'] ?? 0)
                + (float) ($amounts['av_an'] ?? 0)
                + (float) ($amounts['pv_an'] ?? 0);
            $svAg = (float) ($amounts['kv_ag'] ?? 0)
                + (float) ($amounts['rv_ag'] ?? 0)
                + (float) ($amounts['av_ag'] ?? 0)
                + (float) ($amounts['pv_ag'] ?? 0);
            $u = (float) ($amounts['u1'] ?? 0)
                + (float) ($amounts['u2'] ?? 0)
                + (float) ($amounts['u3'] ?? 0);
            $flat = (float) ($amounts['flat_lst'] ?? 0) + (float) ($amounts['flat_other'] ?? 0);
            $netAbz = (float) ($amounts['net_deductions'] ?? 0)
                + (float) ($amounts['flat_lst_employee_share'] ?? 0);
            $n = (float) ($amounts['net_pay'] ?? $emp['net_pay'] ?? 0);
            $empCost = (float) ($amounts['employer_cost'] ?? 0);

            $recon = round($b - ($lst + $solz + $kst + $svAn + $netAbz + $n), 2);
            if (abs($recon) > 0.02) {
                throw new InvalidArgumentException(
                    sprintf(
                        'MA #%d: Brutto-Zerlegung weicht ab (Δ %.2f) — nicht buchbar.',
                        (int) ($emp['contact_id'] ?? 0),
                        $recon
                    )
                );
            }
            $expectedEmp = round($b + $svAg + $u + $flat, 2);
            if ($empCost > 0 && abs(round($empCost - $expectedEmp, 2)) > 0.02) {
                $sum['warnings'][] = sprintf(
                    'MA #%d: employer_cost weicht von B+SV_AG+U+Flat ab.',
                    (int) ($emp['contact_id'] ?? 0)
                );
            }

            $sum['gross_total'] = round($sum['gross_total'] + $b, 2);
            $sum['lst'] = round($sum['lst'] + $lst, 2);
            $sum['solz'] = round($sum['solz'] + $solz, 2);
            $sum['kst'] = round($sum['kst'] + $kst, 2);
            $sum['sv_an'] = round($sum['sv_an'] + $svAn, 2);
            $sum['sv_ag'] = round($sum['sv_ag'] + $svAg, 2);
            $sum['umlagen'] = round($sum['umlagen'] + $u, 2);
            $sum['flat'] = round($sum['flat'] + $flat, 2);
            $sum['net_deductions'] = round($sum['net_deductions'] + $netAbz, 2);
            $sum['net_pay'] = round($sum['net_pay'] + $n, 2);
            $sum['employer_cost'] = round($sum['employer_cost'] + $empCost, 2);
            $sum['employee_count']++;
        }
        $sum['fa'] = round($sum['lst'] + $sum['solz'] + $sum['kst'], 2);

        return $sum;
    }

    /**
     * @param array<string, string> $accounts
     * @param array<string, float|int|list> $agg
     * @return list<array{account_number: string, side: string, amount: float, description: string, document_field1: string, contra_account?: string}>
     */
    public static function buildLines(
        array $accounts,
        array $agg,
        string $description,
        string $marker
    ): array {
        $lines = [];
        $add = static function (string $role, string $side, float $amount) use (&$lines, $accounts, $description, $marker): void {
            $amount = round($amount, 2);
            if ($amount <= 0.0) {
                return;
            }
            $acc = (string) ($accounts[$role] ?? '');
            if ($acc === '') {
                throw new InvalidArgumentException('Konto für Rolle ' . $role . ' fehlt.');
            }
            $lines[] = [
                'account_number' => $acc,
                'side' => $side,
                'amount' => $amount,
                'description' => $description,
                'document_field1' => $marker,
                'tax_key' => '',
            ];
        };

        $b = (float) ($agg['gross_total'] ?? 0);
        $fa = (float) ($agg['fa'] ?? 0);
        $svAn = (float) ($agg['sv_an'] ?? 0);
        $netBucket = round((float) ($agg['net_pay'] ?? 0) + (float) ($agg['net_deductions'] ?? 0), 2);
        $ag = round(
            (float) ($agg['sv_ag'] ?? 0) + (float) ($agg['umlagen'] ?? 0) + (float) ($agg['flat'] ?? 0),
            2
        );

        // Satz 1
        $add(self::ROLE_EXPENSE_WAGES, 'debit', $b);
        $add(self::ROLE_LIABILITY_TAX, 'credit', $fa);
        $add(self::ROLE_LIABILITY_SV, 'credit', $svAn);
        $add(self::ROLE_LIABILITY_NET, 'credit', $netBucket);
        // Satz 2
        $add(self::ROLE_EXPENSE_SOCIAL, 'debit', $ag);
        $add(self::ROLE_LIABILITY_SV, 'credit', $ag);

        if (count($lines) < 2) {
            throw new InvalidArgumentException('Keine buchbaren Beträge (alle Null).');
        }
        self::assertBalanced($lines);

        return $lines;
    }

    /**
     * @param list<array<string, mixed>> $lines
     * @return list<array<string, mixed>>
     */
    public static function invertLines(array $lines, string $description, string $marker): array
    {
        $out = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $amount = round(abs((float) ($line['amount'] ?? 0)), 2);
            if ($amount <= 0.0) {
                continue;
            }
            $side = (string) ($line['side'] ?? 'debit') === 'credit' ? 'debit' : 'credit';
            $out[] = [
                'account_number' => preg_replace('/\D/', '', (string) ($line['account_number'] ?? '')) ?? '',
                'side' => $side,
                'amount' => $amount,
                'description' => $description,
                'document_field1' => $marker,
                'tax_key' => '',
                'contra_account' => (string) ($line['contra_account'] ?? ''),
            ];
        }
        if (count($out) < 2) {
            throw new InvalidArgumentException('Storno: keine Zeilen zum Invertieren.');
        }
        self::assertBalanced($out);

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    public static function linesChecksum(array $lines): string
    {
        $norm = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $norm[] = [
                (string) ($line['account_number'] ?? ''),
                (string) ($line['side'] ?? ''),
                number_format((float) ($line['amount'] ?? 0), 2, '.', ''),
            ];
        }
        usort($norm, static fn (array $a, array $b): int => $a <=> $b);

        return hash('sha256', json_encode($norm, JSON_UNESCAPED_UNICODE) ?: '');
    }

    public static function defaultPostingDate(int $year, int $month): string
    {
        $month = max(1, min(12, $month));
        $ts = mktime(12, 0, 0, $month + 1, 0, $year);

        return date('Y-m-d', $ts !== false ? $ts : time());
    }

    /**
     * Preview ohne Schreiben — approved oder locked.
     *
     * @return array<string, mixed>
     */
    public static function preview(int $runId): array
    {
        self::ensureReady();
        $run = PayrollRunRepository::find($runId);
        if ($run === null) {
            throw new InvalidArgumentException('Lauf nicht gefunden.');
        }
        $st = (string) ($run['status'] ?? '');
        if (!in_array($st, [
            PayrollRunRepository::STATUS_APPROVED,
            PayrollRunRepository::STATUS_LOCKED,
        ], true)) {
            throw new InvalidArgumentException('Preview nur aus approved/locked.');
        }

        return self::buildPreviewPayload($run);
    }

    /**
     * Verbindliche Buchung — locked, nicht superseded.
     *
     * @return array<string, mixed>
     */
    public static function book(
        int $runId,
        ?int $userId,
        string $reason,
        ?string $postingDate = null,
        bool $forceProbe = false
    ): array {
        self::ensureReady();
        if (!self::tableReady()) {
            throw new RuntimeException('Tabelle dg_payroll_fibu_batches fehlt (Migration 130).');
        }
        if (!self::isLive() && !$forceProbe) {
            throw new RuntimeException(
                'FiBu-Lohnbuchung ist nicht freigeschaltet (payroll_fibu_live). Gate G1 / L7c.'
            );
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('Begründung mindestens 10 Zeichen.');
        }

        $existing = self::findMetaByRunId($runId);
        if ($existing !== null) {
            return self::metaResult($existing, true);
        }

        $run = PayrollRunRepository::find($runId);
        if ($run === null) {
            throw new InvalidArgumentException('Lauf nicht gefunden.');
        }
        if ((string) ($run['status'] ?? '') !== PayrollRunRepository::STATUS_LOCKED) {
            throw new InvalidArgumentException('FiBu-Buchung nur für locked Läufe.');
        }
        if (!empty($run['is_superseded'])) {
            throw new InvalidArgumentException('Ersetzter Lauf kann nicht gebucht werden.');
        }

        $marker = self::markerForRun($runId);
        $byMarker = ManualLedgerService::findBatchIdBySourceMarker(self::SOURCE, $marker);
        if ($byMarker !== null) {
            // Marker ohne Meta → Meta nachziehen (Reparatur)
            $repaired = self::insertMetaRow(
                $runId,
                $byMarker,
                (string) ($postingDate ?? self::defaultPostingDate(
                    (int) $run['period_year'],
                    (int) $run['period_month']
                )),
                '',
                self::KIND_REGULAR,
                $reason,
                $userId
            );

            return self::metaResult($repaired, true);
        }

        $payload = self::buildPreviewPayload($run);
        $date = $postingDate !== null && $postingDate !== ''
            ? $postingDate
            : (string) $payload['posting_date'];
        self::assertPostingDateAllowed(
            $date,
            (int) $run['period_year'],
            (int) $run['period_month']
        );
        if (FiscalYearService::isClosed((int) substr($date, 0, 4))) {
            throw new InvalidArgumentException('Geschäftsjahr ist abgeschlossen.');
        }

        $kind = (string) $payload['fibu_kind'];
        $lines = $payload['lines'];
        $checksum = self::linesChecksum($lines);
        $desc = (string) $payload['description'];
        if ($forceProbe && !self::isLive()) {
            $desc = '[PROBE] ' . $desc;
        }

        $batchId = ManualLedgerService::createBatch(
            $date,
            $desc,
            $lines,
            $userId,
            self::SOURCE
        );
        $meta = self::insertMetaRow($runId, $batchId, $date, $checksum, $kind, $reason, $userId);

        return self::metaResult($meta, false) + [
            'warnings' => $payload['warnings'] ?? [],
            'amounts' => $payload['amounts'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findMetaByRunId(int $runId): ?array
    {
        self::ensureReady();
        if (!self::tableReady() || $runId < 1) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_payroll_fibu_batches WHERE run_id = :r LIMIT 1'
        );
        $stmt->execute(['r' => $runId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $run
     * @return array<string, mixed>
     */
    private static function buildPreviewPayload(array $run): array
    {
        $runId = (int) $run['id'];
        $year = (int) $run['period_year'];
        $month = (int) $run['period_month'];
        $runKind = (string) ($run['kind'] ?? PayrollRunRepository::KIND_REGULAR);
        $marker = self::markerForRun($runId);
        $accounts = self::resolveAccounts();
        $postingDate = self::defaultPostingDate($year, $month);
        $labelKind = $runKind === PayrollRunRepository::KIND_STORNO
            ? 'Storno'
            : ($runKind === PayrollRunRepository::KIND_CORRECTION ? 'Korrektur' : 'Regulär');
        $description = sprintf('Lohn %02d/%04d Run #%d (%s)', $month, $year, $runId, $labelKind);

        if ($runKind === PayrollRunRepository::KIND_STORNO) {
            $sourceRunId = (int) ($run['supersedes_run_id'] ?? 0);
            $prevMeta = $sourceRunId > 0 ? self::findMetaByRunId($sourceRunId) : null;
            if ($prevMeta === null) {
                throw new InvalidArgumentException(
                    'Storno-FiBu: Vorgänger-Run wurde nicht gebucht — nichts zu stornieren.'
                );
            }
            $prevLines = self::loadBatchLines((int) $prevMeta['manual_batch_id']);
            $lines = self::invertLines($prevLines, $description, $marker);
            $fibuKind = self::KIND_STORNO_CONTRA;
            $agg = [
                'gross_total' => 0.0,
                'employee_count' => 0,
                'warnings' => [],
                'note' => 'Invertierung Batch #' . (int) $prevMeta['manual_batch_id'],
            ];
        } else {
            $employees = PayrollRunRepository::employees($runId);
            $agg = self::aggregateAmounts($employees);
            if ((int) $agg['employee_count'] < 1) {
                throw new InvalidArgumentException('Keine ok-MA mit Ergebnis — nichts zu buchen.');
            }
            $lines = self::buildLines($accounts, $agg, $description, $marker);
            $fibuKind = $runKind === PayrollRunRepository::KIND_CORRECTION
                ? self::KIND_CORRECTION
                : self::KIND_REGULAR;
        }

        return [
            'run_id' => $runId,
            'fibu_kind' => $fibuKind,
            'run_kind' => $runKind,
            'posting_date' => $postingDate,
            'marker' => $marker,
            'description' => $description,
            'accounts' => $accounts,
            'amounts' => $agg,
            'lines' => $lines,
            'checksum' => self::linesChecksum($lines),
            'warnings' => $agg['warnings'] ?? [],
            'already_booked' => self::findMetaByRunId($runId) !== null,
            'live' => self::isLive(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function loadBatchLines(int $manualBatchId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT account_number, side, amount, contra_account, description
             FROM dg_ledger_postings
             WHERE manual_batch_id = :id
             ORDER BY id ASC'
        );
        $stmt->execute(['id' => $manualBatchId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function insertMetaRow(
        int $runId,
        int $manualBatchId,
        string $postingDate,
        string $checksum,
        string $kind,
        string $reason,
        ?int $userId
    ): array {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_payroll_fibu_batches
                (run_id, manual_batch_id, posting_date, checksum, kind, reason, created_by_user_id)
             VALUES
                (:run_id, :batch, :d, :checksum, :kind, :reason, :uid)'
        );
        $stmt->execute([
            'run_id' => $runId,
            'batch' => $manualBatchId,
            'd' => $postingDate,
            'checksum' => mb_substr($checksum, 0, 64),
            'kind' => mb_substr($kind, 0, 32),
            'reason' => mb_substr($reason, 0, 500),
            'uid' => $userId,
        ]);
        $meta = self::findMetaByRunId($runId);
        if ($meta === null) {
            throw new RuntimeException('FiBu-Meta konnte nicht gelesen werden.');
        }

        return $meta;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private static function metaResult(array $meta, bool $idempotent): array
    {
        return [
            'id' => (int) ($meta['id'] ?? 0),
            'run_id' => (int) ($meta['run_id'] ?? 0),
            'manual_batch_id' => (int) ($meta['manual_batch_id'] ?? 0),
            'posting_date' => (string) ($meta['posting_date'] ?? ''),
            'kind' => (string) ($meta['kind'] ?? ''),
            'checksum' => (string) ($meta['checksum'] ?? ''),
            'idempotent' => $idempotent,
        ];
    }

    private static function assertPostingDateAllowed(string $date, int $periodYear, int $periodMonth): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException('Buchungsdatum ungültig.');
        }
        $y = (int) substr($date, 0, 4);
        $m = (int) substr($date, 5, 2);
        $nextY = $periodMonth === 12 ? $periodYear + 1 : $periodYear;
        $nextM = $periodMonth === 12 ? 1 : $periodMonth + 1;
        $ok = ($y === $periodYear && $m === $periodMonth)
            || ($y === $nextY && $m === $nextM);
        if (!$ok) {
            throw new InvalidArgumentException(
                'Buchungsdatum nur im Abrechnungsmonat oder Folgemonat erlaubt.'
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private static function assertBalanced(array $lines): void
    {
        $debit = 0.0;
        $credit = 0.0;
        foreach ($lines as $line) {
            $amt = (float) ($line['amount'] ?? 0);
            if (($line['side'] ?? '') === 'credit') {
                $credit = round($credit + $amt, 2);
            } else {
                $debit = round($debit + $amt, 2);
            }
        }
        if (abs($debit - $credit) > 0.009) {
            throw new InvalidArgumentException(
                sprintf('Buchung nicht ausgeglichen (Soll %.2f ≠ Haben %.2f).', $debit, $credit)
            );
        }
    }

    /**
     * @return array<string, float|int>
     */
    private static function decodeAmounts(mixed $raw): array
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
}
