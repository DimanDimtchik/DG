<?php
declare(strict_types=1);

/**
 * Lohn L8b: SEPA pain.001.001.03 Sammler (Nettoauszahlung). Keine Bank-API.
 */
final class PayrollSepaService
{
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
            return Database::pdo()->query("SHOW TABLES LIKE 'dg_payroll_sepa_exports'")->fetchColumn() !== false;
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

        return !empty($lohn['payroll_sepa_live']);
    }

    /**
     * @return list<string>
     */
    public static function auditorChecklist(): array
    {
        return [
            'Lauf status=locked, nicht superseded; Storno-Run ohne Auszahlungs-SEPA.',
            'Nur ok-MA mit net_pay > 0 und gültiger Empfänger-IBAN in der Sammlerdatei.',
            'Absender-IBAN aus Settings Lohn (payroll_bank_account_index) / Firmendaten Bankkonten.',
            'Flag payroll_sepa_live nur nach Gate G3 setzen — ohne Flag nur …-PROBE.xml.',
            'XML-Checksumme (SHA-256) und Meta dg_payroll_sepa_exports gegen Bankupload prüfen.',
            'FiBu (L7) empfohlen vor SEPA; Bankausgleich der Verb. Nettolöhne bleibt manuell.',
            'Kein automatischer Bankversand — Upload nur im Online-Banking / EBICS des Mandanten.',
        ];
    }

    public static function maskIban(string $iban): string
    {
        $iban = BankDirectory::normalizeIban($iban);
        $len = strlen($iban);
        if ($len < 8) {
            return $iban !== '' ? '••••' : '';
        }

        return substr($iban, 0, 4) . str_repeat('•', max(0, $len - 8)) . substr($iban, -4);
    }

    public static function storageDir(): string
    {
        return DG_ROOT . '/storage/payroll/sepa';
    }

    public static function endToEndId(int $runId, int $contactId): string
    {
        return mb_substr(sprintf('E2E-R%d-C%d', $runId, $contactId), 0, 35);
    }

    public static function nextBusinessDay(?DateTimeInterface $from = null): string
    {
        $d = $from !== null
            ? DateTimeImmutable::createFromInterface($from)
            : new DateTimeImmutable('today');
        $d = $d->modify('+1 day');
        while ((int) $d->format('N') >= 6) {
            $d = $d->modify('+1 day');
        }

        return $d->format('Y-m-d');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listExportsForRun(int $runId): array
    {
        self::ensureReady();
        if (!self::tableReady() || $runId < 1) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_payroll_sepa_exports WHERE run_id = :r ORDER BY id DESC LIMIT 50'
        );
        $stmt->execute(['r' => $runId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Vorschau ohne Schreiben.
     *
     * @return array<string, mixed>
     */
    public static function preview(int $runId, ?string $requestedExecDate = null): array
    {
        self::ensureReady();
        $run = self::requireExportableRun($runId);
        $built = self::buildPayload($run, $requestedExecDate, self::isLive());

        return $built + [
            'live' => self::isLive(),
            'prior_exports' => self::listExportsForRun($runId),
        ];
    }

    /**
     * Erzeugt XML, speichert Meta (+ Datei), liefert Bytes zum Download.
     *
     * @return array{xml: string, meta: array<string, mixed>, filename: string, warnings: list<string>}
     */
    public static function export(
        int $runId,
        ?int $userId,
        string $reason,
        ?string $requestedExecDate = null
    ): array {
        self::ensureReady();
        if (!self::tableReady()) {
            throw new RuntimeException('Tabelle dg_payroll_sepa_exports fehlt (Migration 131).');
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('Begründung mindestens 10 Zeichen.');
        }

        $live = self::isLive();
        $run = self::requireExportableRun($runId);
        $payload = self::buildPayload($run, $requestedExecDate, $live);
        $xml = self::renderXml($payload);
        $checksum = hash('sha256', $xml);
        $filename = (string) $payload['filename'];
        $rel = sprintf(
            '%04d/%02d/%s',
            (int) $run['period_year'],
            (int) $run['period_month'],
            $filename
        );
        self::writeStorage($rel, $xml);

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_payroll_sepa_exports
                (run_id, created_by_user_id, is_live, is_probe, tx_count, ctrl_sum, msg_id, checksum, filename, storage_path, reason)
             VALUES
                (:run_id, :uid, :live, :probe, :tx, :sum, :msg, :checksum, :filename, :path, :reason)'
        );
        $stmt->execute([
            'run_id' => $runId,
            'uid' => $userId,
            'live' => $live ? 1 : 0,
            'probe' => $live ? 0 : 1,
            'tx' => (int) $payload['tx_count'],
            'sum' => number_format((float) $payload['ctrl_sum'], 2, '.', ''),
            'msg' => mb_substr((string) $payload['msg_id'], 0, 35),
            'checksum' => $checksum,
            'filename' => mb_substr($filename, 0, 200),
            'path' => mb_substr($rel, 0, 500),
            'reason' => mb_substr($reason, 0, 500),
        ]);
        $id = (int) Database::pdo()->lastInsertId();
        $meta = self::findExport($id);
        if ($meta === null) {
            throw new RuntimeException('SEPA-Meta nicht lesbar.');
        }

        return [
            'xml' => $xml,
            'meta' => $meta,
            'filename' => $filename,
            'warnings' => $payload['warnings'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findExport(int $id): ?array
    {
        self::ensureReady();
        if (!self::tableReady() || $id < 1) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_payroll_sepa_exports WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public static function readStoredXml(int $exportId): ?string
    {
        $meta = self::findExport($exportId);
        if ($meta === null) {
            return null;
        }
        $rel = ltrim(str_replace(['..', '\\'], ['', '/'], (string) ($meta['storage_path'] ?? '')), '/');
        if ($rel === '') {
            return null;
        }
        $abs = self::storageDir() . '/' . $rel;
        if (!is_file($abs)) {
            return null;
        }
        $data = file_get_contents($abs);
        if (!is_string($data) || $data === '') {
            return null;
        }
        $checksum = (string) ($meta['checksum'] ?? '');
        if ($checksum !== '' && !hash_equals($checksum, hash('sha256', $data))) {
            throw new RuntimeException('SEPA-Datei Checksumme stimmt nicht.');
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function renderXml(array $payload): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $msgId = $e((string) ($payload['msg_id'] ?? ''));
        $cre = $e((string) ($payload['cre_dt_tm'] ?? ''));
        $nb = (int) ($payload['tx_count'] ?? 0);
        $ctrl = number_format((float) ($payload['ctrl_sum'] ?? 0), 2, '.', '');
        $initName = $e((string) ($payload['initiating_name'] ?? ''));
        $pmtInfId = $e((string) ($payload['pmt_inf_id'] ?? ''));
        $reqd = $e((string) ($payload['reqd_exec_date'] ?? ''));
        $dbtrName = $e((string) ($payload['debtor_name'] ?? ''));
        $dbtrIban = $e((string) ($payload['debtor_iban'] ?? ''));
        $dbtrBic = trim((string) ($payload['debtor_bic'] ?? ''));

        $dbtrAgt = $dbtrBic !== ''
            ? '<DbtrAgt><FinInstnId><BIC>' . $e($dbtrBic) . '</BIC></FinInstnId></DbtrAgt>'
            : '';

        $txs = '';
        foreach (is_array($payload['transactions'] ?? null) ? $payload['transactions'] : [] as $tx) {
            if (!is_array($tx)) {
                continue;
            }
            $amt = number_format((float) ($tx['amount'] ?? 0), 2, '.', '');
            $cdtrBic = trim((string) ($tx['bic'] ?? ''));
            $cdtrAgt = $cdtrBic !== ''
                ? '<CdtrAgt><FinInstnId><BIC>' . $e($cdtrBic) . '</BIC></FinInstnId></CdtrAgt>'
                : '';
            $txs .= '<CdtTrfTxInf>'
                . '<PmtId><EndToEndId>' . $e((string) ($tx['end_to_end_id'] ?? '')) . '</EndToEndId></PmtId>'
                . '<Amt><InstdAmt Ccy="EUR">' . $amt . '</InstdAmt></Amt>'
                . $cdtrAgt
                . '<Cdtr><Nm>' . $e((string) ($tx['name'] ?? '')) . '</Nm></Cdtr>'
                . '<CdtrAcct><Id><IBAN>' . $e((string) ($tx['iban'] ?? '')) . '</IBAN></Id></CdtrAcct>'
                . '<RmtInf><Ustrd>' . $e((string) ($tx['purpose'] ?? '')) . '</Ustrd></RmtInf>'
                . '</CdtTrfTxInf>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03"'
            . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<CstmrCdtTrfInitn>'
            . '<GrpHdr>'
            . '<MsgId>' . $msgId . '</MsgId>'
            . '<CreDtTm>' . $cre . '</CreDtTm>'
            . '<NbOfTxs>' . $nb . '</NbOfTxs>'
            . '<CtrlSum>' . $ctrl . '</CtrlSum>'
            . '<InitgPty><Nm>' . $initName . '</Nm></InitgPty>'
            . '</GrpHdr>'
            . '<PmtInf>'
            . '<PmtInfId>' . $pmtInfId . '</PmtInfId>'
            . '<PmtMtd>TRF</PmtMtd>'
            . '<BtchBookg>true</BtchBookg>'
            . '<NbOfTxs>' . $nb . '</NbOfTxs>'
            . '<CtrlSum>' . $ctrl . '</CtrlSum>'
            . '<PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl></PmtTpInf>'
            . '<ReqdExctnDt>' . $reqd . '</ReqdExctnDt>'
            . '<Dbtr><Nm>' . $dbtrName . '</Nm></Dbtr>'
            . '<DbtrAcct><Id><IBAN>' . $dbtrIban . '</IBAN></Id></DbtrAcct>'
            . $dbtrAgt
            . '<ChrgBr>SLEV</ChrgBr>'
            . $txs
            . '</PmtInf>'
            . '</CstmrCdtTrfInitn>'
            . '</Document>';
    }

    /**
     * @param array{
     *   debtor_name: string, debtor_iban: string, debtor_bic: string,
     *   transactions: list<array{contact_id: int, name: string, iban: string, bic: string, amount: float, end_to_end_id: string, purpose: string}>,
     *   initiating_name: string, msg_id: string, pmt_inf_id: string,
     *   reqd_exec_date: string, cre_dt_tm: string, probe: bool
     * } $parts
     * @return array<string, mixed>
     */
    public static function assemblePayload(array $parts): array
    {
        $txs = is_array($parts['transactions'] ?? null) ? $parts['transactions'] : [];
        $sum = 0.0;
        foreach ($txs as $tx) {
            $sum = round($sum + (float) ($tx['amount'] ?? 0), 2);
        }
        $count = count($txs);
        if ($count < 1 || $sum <= 0) {
            throw new InvalidArgumentException('Keine gültigen SEPA-Transaktionen (Netto/IBAN).');
        }
        $probe = !empty($parts['probe']);
        $runId = (int) ($parts['run_id'] ?? 0);
        $filename = sprintf(
            'lohn-sepa-run%d-%s%s.xml',
            $runId,
            date('Ymd-His'),
            $probe ? '-PROBE' : ''
        );

        return [
            'run_id' => $runId,
            'msg_id' => (string) $parts['msg_id'],
            'cre_dt_tm' => (string) $parts['cre_dt_tm'],
            'tx_count' => $count,
            'ctrl_sum' => $sum,
            'initiating_name' => (string) $parts['initiating_name'],
            'pmt_inf_id' => (string) $parts['pmt_inf_id'],
            'reqd_exec_date' => (string) $parts['reqd_exec_date'],
            'debtor_name' => (string) $parts['debtor_name'],
            'debtor_iban' => (string) $parts['debtor_iban'],
            'debtor_bic' => (string) $parts['debtor_bic'],
            'transactions' => $txs,
            'probe' => $probe,
            'filename' => $filename,
            'warnings' => is_array($parts['warnings'] ?? null) ? $parts['warnings'] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function requireExportableRun(int $runId): array
    {
        $run = PayrollRunRepository::find($runId);
        if ($run === null) {
            throw new InvalidArgumentException('Lauf nicht gefunden.');
        }
        if ((string) ($run['status'] ?? '') !== PayrollRunRepository::STATUS_LOCKED) {
            throw new InvalidArgumentException('SEPA nur für locked Läufe.');
        }
        if (!empty($run['is_superseded'])) {
            throw new InvalidArgumentException('Ersetzter Lauf: keine SEPA-Auszahlung.');
        }
        $kind = (string) ($run['kind'] ?? '');
        if ($kind === PayrollRunRepository::KIND_STORNO) {
            throw new InvalidArgumentException('Storno-Run erzeugt keine Auszahlungs-SEPA.');
        }
        if (!in_array($kind, [
            PayrollRunRepository::KIND_REGULAR,
            PayrollRunRepository::KIND_CORRECTION,
            '',
        ], true)) {
            throw new InvalidArgumentException('SEPA nur für regular/correction.');
        }

        return $run;
    }

    /**
     * @param array<string, mixed> $run
     * @return array<string, mixed>
     */
    private static function buildPayload(array $run, ?string $requestedExecDate, bool $live): array
    {
        $runId = (int) $run['id'];
        $year = (int) $run['period_year'];
        $month = (int) $run['period_month'];
        $debtor = self::resolveDebtor();
        $warnings = [];
        $transactions = [];

        foreach (PayrollRunRepository::employees($runId) as $emp) {
            if (($emp['status'] ?? '') !== PayrollRunRepository::EMP_OK) {
                continue;
            }
            $contactId = (int) ($emp['contact_id'] ?? 0);
            $net = (float) ($emp['net_pay'] ?? 0);
            if ($net <= 0) {
                $warnings[] = 'MA #' . $contactId . ': Netto ≤ 0 — übersprungen.';
                continue;
            }
            $creditor = self::resolveCreditor($contactId);
            if ($creditor === null) {
                $warnings[] = 'MA #' . $contactId . ': keine gültige Empfänger-IBAN — übersprungen.';
                continue;
            }
            $purpose = PaymentReferenceFormula::sanitizeSepa(
                sprintf('Lohn %02d/%04d Run#%d', $month, $year, $runId)
            );
            $transactions[] = [
                'contact_id' => $contactId,
                'name' => PaymentReferenceFormula::sanitizeSepa($creditor['name']),
                'iban' => $creditor['iban'],
                'bic' => $creditor['bic'],
                'amount' => round($net, 2),
                'end_to_end_id' => self::endToEndId($runId, $contactId),
                'purpose' => mb_substr($purpose, 0, 140),
            ];
        }

        $exec = self::normalizeExecDate($requestedExecDate);
        $now = new DateTimeImmutable('now');
        $msgId = mb_substr(sprintf('DG-LOHN-%d-%s', $runId, $now->format('YmdHis')), 0, 35);
        $initName = PaymentReferenceFormula::sanitizeSepa($debtor['name']);
        if (!$live) {
            $initName = mb_substr($initName . ' (PROBE)', 0, 70);
        }

        return self::assemblePayload([
            'run_id' => $runId,
            'msg_id' => $msgId,
            'cre_dt_tm' => $now->format('Y-m-d\TH:i:s'),
            'initiating_name' => $initName,
            'pmt_inf_id' => mb_substr('PMT-RUN-' . $runId, 0, 35),
            'reqd_exec_date' => $exec,
            'debtor_name' => PaymentReferenceFormula::sanitizeSepa($debtor['name']),
            'debtor_iban' => $debtor['iban'],
            'debtor_bic' => $debtor['bic'],
            'transactions' => $transactions,
            'probe' => !$live,
            'warnings' => $warnings,
        ]);
    }

    /**
     * @return array{name: string, iban: string, bic: string}
     */
    private static function resolveDebtor(): array
    {
        $name = 'Arbeitgeber';
        $iban = '';
        $bic = '';
        $index = 0;
        if (class_exists('PayrollEmployerSettings')) {
            $snap = PayrollEmployerSettings::employerSnapshot();
            $name = trim((string) ($snap['employer_name'] ?? $name));
            $lohn = is_array($snap['lohn'] ?? null) ? $snap['lohn'] : PayrollEmployerSettings::lohnConfig();
            $index = (int) ($lohn['payroll_bank_account_index'] ?? 0);
            $bank = is_array($snap['payroll_bank'] ?? null) ? $snap['payroll_bank'] : null;
            if ($bank === null && class_exists('CompanyExtendedSettings')) {
                $ext = CompanyExtendedSettings::config();
                $banks = is_array($ext['bank_accounts'] ?? null) ? $ext['bank_accounts'] : [];
                $bank = isset($banks[$index]) && is_array($banks[$index]) ? $banks[$index] : ($banks[0] ?? null);
            }
            if (is_array($bank)) {
                $iban = BankDirectory::normalizeIban((string) ($bank['iban'] ?? ''));
                $bic = strtoupper(trim(str_replace(' ', '', (string) ($bank['bic'] ?? ''))));
                $holder = trim((string) ($bank['account_holder'] ?? ''));
                if ($holder !== '') {
                    $name = $holder;
                }
            }
        }
        if ($iban === '' || !BankDirectory::validateIban($iban)) {
            throw new InvalidArgumentException(
                'SEPA-Absenderkonto ohne gültige IBAN (Einstellungen → Lohn / Firmendaten).'
            );
        }
        if ($bic === '') {
            $bic = self::resolveBic('', $iban);
        }

        return ['name' => $name !== '' ? $name : 'Arbeitgeber', 'iban' => $iban, 'bic' => $bic];
    }

    /**
     * @return array{name: string, iban: string, bic: string}|null
     */
    private static function resolveCreditor(int $contactId): ?array
    {
        if ($contactId < 1 || !class_exists('ContactRepository')) {
            return null;
        }
        $contact = ContactRepository::findById($contactId);
        if ($contact === null) {
            return null;
        }
        $index = 0;
        $emp = is_array($contact->employeeData) ? $contact->employeeData : [];
        if (isset($emp['payroll_payout_bank_index'])) {
            $index = max(0, (int) $emp['payroll_payout_bank_index']);
        }
        $accounts = is_array($contact->bankAccounts) ? $contact->bankAccounts : [];
        $chosen = null;
        if (isset($accounts[$index]) && is_array($accounts[$index])) {
            $chosen = $accounts[$index];
        }
        if ($chosen === null) {
            foreach ($accounts as $acc) {
                if (!is_array($acc)) {
                    continue;
                }
                if (!empty($acc['is_primary'])) {
                    $chosen = $acc;
                    break;
                }
            }
        }
        if ($chosen === null && isset($accounts[0]) && is_array($accounts[0])) {
            $chosen = $accounts[0];
        }
        if ($chosen === null) {
            return null;
        }
        $iban = BankDirectory::normalizeIban((string) ($chosen['iban'] ?? ''));
        if ($iban === '' || !BankDirectory::validateIban($iban)) {
            return null;
        }
        $bic = strtoupper(trim(str_replace(' ', '', (string) ($chosen['bic'] ?? ''))));
        if ($bic === '') {
            $bic = self::resolveBic('', $iban);
        }
        $name = $contact->listLabel();
        $holder = trim((string) ($chosen['account_holder'] ?? ''));
        if ($holder !== '') {
            $name = $holder;
        }

        return ['name' => $name, 'iban' => $iban, 'bic' => $bic];
    }

    private static function resolveBic(string $bic, string $iban): string
    {
        $bic = strtoupper(trim(str_replace(' ', '', $bic)));
        if ($bic !== '') {
            return $bic;
        }
        $suggestion = BankDirectory::suggestFromIban($iban);

        return $suggestion !== null
            ? strtoupper(trim((string) ($suggestion['bic'] ?? '')))
            : '';
    }

    private static function normalizeExecDate(?string $requested): string
    {
        $default = self::nextBusinessDay();
        if ($requested === null || $requested === '') {
            return $default;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requested)) {
            throw new InvalidArgumentException('Ausführungsdatum ungültig.');
        }
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        if ($requested < $today) {
            throw new InvalidArgumentException('Ausführungsdatum darf nicht in der Vergangenheit liegen.');
        }

        return $requested;
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
            throw new RuntimeException('SEPA-XML konnte nicht gespeichert werden.');
        }
    }
}
