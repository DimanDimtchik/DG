<?php
declare(strict_types=1);

/**
 * Lohn L9b: SV-Meldeportal-Vorbereitung (Beitragsnachweis-/Stamm-Kontrolllisten + CSV).
 * Kein ITSG, kein DEÜV-Versand.
 */
final class PayrollSvPortalService
{
    public const KIND_BEITRAG = 'beitragsnachweis';
    public const KIND_STAMM = 'stammkontrolle';
    public const KIND_COMBINED = 'combined';

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
            return Database::pdo()->query("SHOW TABLES LIKE 'dg_payroll_sv_exports'")->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    public static function storageDir(): string
    {
        return DG_ROOT . '/storage/payroll/sv';
    }

    /**
     * @return list<string>
     */
    public static function auditorChecklist(): array
    {
        return [
            'Nur locked, nicht superseded; Storno-Run ohne Beitragsnachweis-Summen.',
            'CSV ist Hilfe für das SV-Portal — kein automatischer Meldeversand aus dem CRM.',
            'Minijob-Pauschale getrennt von der SV-Pflicht ausweisen.',
            'Gruppierung primär nach KK-Betriebsnummer; ohne Nummer = eigene Gruppe.',
            'SV-Nummer in der Oberfläche maskieren; vollständig in der CSV nur für Berechtigte.',
            'SV-Beiträge nicht über die Lohn-SEPA-Auszahlung zahlen.',
            'Export-Eintrag und Prüfsumme gegen den Download prüfen.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function kindLabels(): array
    {
        return [
            self::KIND_BEITRAG => 'Beitragsnachweis',
            self::KIND_STAMM => 'Stammkontrolle',
            self::KIND_COMBINED => 'Kombiniert',
        ];
    }

    public static function maskSvNumber(string $sv): string
    {
        $sv = preg_replace('/\s+/', '', trim($sv)) ?? '';
        $len = strlen($sv);
        if ($len < 6) {
            return $sv !== '' ? '••••' : '';
        }

        return substr($sv, 0, 2) . str_repeat('•', max(0, $len - 5)) . substr($sv, -3);
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
     * @param mixed $raw
     * @return array<string, mixed>
     */
    public static function decodeSnapshot(mixed $raw): array
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
     * Pure Aggregation laut L9a (ohne DB).
     *
     * @param array<string, mixed> $run
     * @param list<array<string, mixed>> $employees
     * @return array{
     *   groups: list<array<string, mixed>>,
     *   totals: array<string, mixed>,
     *   warnings: list<string>,
     *   run_id: int,
     *   period_year: int,
     *   period_month: int,
     *   kind: string,
     *   blocked: bool
     * }
     */
    public static function aggregateBeitragsnachweis(array $run, array $employees, string $agBetriebsnummer = ''): array
    {
        $warnings = [];
        $kind = (string) ($run['kind'] ?? PayrollRunRepository::KIND_REGULAR);
        $blocked = $kind === PayrollRunRepository::KIND_STORNO;
        if ($blocked) {
            $warnings[] = 'Storno-Run: keine Beitragsnachweis-Summen (Portal-Korrektur manuell).';
        }
        if ($kind === PayrollRunRepository::KIND_CORRECTION) {
            $warnings[] = 'Korrektur-Lauf: Vorgänger ggf. im Portal stornieren/korrigieren.';
        }
        $agBn = preg_replace('/\D+/', '', $agBetriebsnummer) ?? '';
        if ($agBn === '') {
            $warnings[] = 'Portal-Absender fehlt: AG-Betriebsnummer leer.';
        }

        $groups = [];
        if (!$blocked) {
            foreach ($employees as $emp) {
                if (!is_array($emp) || ($emp['status'] ?? '') !== PayrollRunRepository::EMP_OK) {
                    continue;
                }
                $amounts = self::decodeAmounts($emp['result_json'] ?? null);
                if ($amounts === []) {
                    $warnings[] = 'MA #' . (int) ($emp['contact_id'] ?? 0) . ': result_json fehlt — übersprungen.';
                    continue;
                }
                $snap = self::decodeSnapshot($emp['snapshot_json'] ?? null);
                $ed = is_array($snap['employee'] ?? null) ? $snap['employee'] : [];
                $kkBn = preg_replace('/\D+/', '', (string) ($ed['health_insurance_company_number'] ?? '')) ?? '';
                $kkBn = mb_substr($kkBn, 0, 8);
                $key = $kkBn !== '' ? $kkBn : 'UNKNOWN';
                if ($kkBn === '') {
                    $warnings[] = 'MA #' . (int) ($emp['contact_id'] ?? 0) . ': ohne KK-Betriebsnummer → Gruppe UNKNOWN.';
                }
                if (!isset($groups[$key])) {
                    $groups[$key] = self::emptyGroup($key, (string) ($ed['health_insurance'] ?? ''));
                }
                $kkName = trim((string) ($ed['health_insurance'] ?? ''));
                if ($kkName !== '' && ($groups[$key]['kk_name'] ?? '') === '') {
                    $groups[$key]['kk_name'] = $kkName;
                }
                $office = trim((string) ($ed['social_filing_office'] ?? ''));
                if ($office !== '' && !in_array($office, $groups[$key]['filing_offices'], true)) {
                    $groups[$key]['filing_offices'][] = $office;
                }
                $cg = trim((string) ($ed['contribution_group_key'] ?? ''));
                if ($cg !== '' && !in_array($cg, $groups[$key]['contribution_groups'], true)) {
                    $groups[$key]['contribution_groups'][] = $cg;
                }

                $path = (string) ($emp['path'] ?? '');
                $svExempt = !empty($ed['sv_exempt']);
                if ($path === 'mini' || $svExempt) {
                    $groups[$key]['path_notes'][] = 'MA #' . (int) ($emp['contact_id'] ?? 0)
                        . ': Pfad=' . ($path !== '' ? $path : '—')
                        . ($svExempt ? '/exempt' : '');
                }

                $kvAn = (float) ($amounts['kv_an'] ?? 0);
                $kvAg = (float) ($amounts['kv_ag'] ?? 0);
                $rvAn = (float) ($amounts['rv_an'] ?? 0);
                $rvAg = (float) ($amounts['rv_ag'] ?? 0);
                $avAn = (float) ($amounts['av_an'] ?? 0);
                $avAg = (float) ($amounts['av_ag'] ?? 0);
                $pvAn = (float) ($amounts['pv_an'] ?? 0);
                $pvAg = (float) ($amounts['pv_ag'] ?? 0);
                $u1 = (float) ($amounts['u1'] ?? 0);
                $u2 = (float) ($amounts['u2'] ?? 0);
                $u3 = (float) ($amounts['u3'] ?? 0);
                $flat = (float) ($amounts['flat_lst'] ?? 0) + (float) ($amounts['flat_other'] ?? 0);
                $grossSv = (float) ($amounts['gross_sv'] ?? 0);

                $svAn = round($kvAn + $rvAn + $avAn + $pvAn, 2);
                $svAg = round($kvAg + $rvAg + $avAg + $pvAg, 2);
                $branches = round($kvAn + $kvAg + $rvAn + $rvAg + $avAn + $avAg + $pvAn + $pvAg, 2);
                if (abs(round($svAn + $svAg - $branches, 2)) > 0.01) {
                    $warnings[] = 'MA #' . (int) ($emp['contact_id'] ?? 0) . ': SV-Zerlegung inkonsistent.';
                }

                $g = &$groups[$key];
                $g['kv'] = round($g['kv'] + $kvAn + $kvAg, 2);
                $g['rv'] = round($g['rv'] + $rvAn + $rvAg, 2);
                $g['av'] = round($g['av'] + $avAn + $avAg, 2);
                $g['pv'] = round($g['pv'] + $pvAn + $pvAg, 2);
                $g['sv_an'] = round($g['sv_an'] + $svAn, 2);
                $g['sv_ag'] = round($g['sv_ag'] + $svAg, 2);
                $g['u1'] = round($g['u1'] + $u1, 2);
                $g['u2'] = round($g['u2'] + $u2, 2);
                $g['u3'] = round($g['u3'] + $u3, 2);
                $g['flat'] = round($g['flat'] + $flat, 2);
                $g['gross_sv'] = round($g['gross_sv'] + $grossSv, 2);
                $g['employee_count']++;
                unset($g);
            }
        }

        $list = array_values($groups);
        usort($list, static fn (array $a, array $b): int => strcmp((string) $a['kk_bn'], (string) $b['kk_bn']));

        $totals = self::emptyGroup('TOTAL', '');
        $totals['kk_bn'] = 'TOTAL';
        foreach ($list as $g) {
            foreach (['kv', 'rv', 'av', 'pv', 'sv_an', 'sv_ag', 'u1', 'u2', 'u3', 'flat', 'gross_sv'] as $f) {
                $totals[$f] = round((float) $totals[$f] + (float) $g[$f], 2);
            }
            $totals['employee_count'] += (int) $g['employee_count'];
            $branchSum = round((float) $g['kv'] + (float) $g['rv'] + (float) $g['av'] + (float) $g['pv'], 2);
            $svSum = round((float) $g['sv_an'] + (float) $g['sv_ag'], 2);
            if (abs($branchSum - $svSum) > 0.01) {
                $warnings[] = 'Gruppe ' . $g['kk_bn'] . ': SV_AN+SV_AG weicht von Zweigen ab.';
            }
        }

        $warnings = array_values(array_unique($warnings));

        return [
            'run_id' => (int) ($run['id'] ?? 0),
            'period_year' => (int) ($run['period_year'] ?? 0),
            'period_month' => (int) ($run['period_month'] ?? 0),
            'kind' => $kind,
            'blocked' => $blocked,
            'ag_betriebsnummer' => $agBn,
            'groups' => $list,
            'totals' => $totals,
            'warnings' => $warnings,
        ];
    }

    /**
     * Pure Stammkontrolle laut L9a.
     *
     * @param array<string, mixed> $run
     * @param list<array<string, mixed>> $employees
     * @return array{
     *   rows: list<array<string, mixed>>,
     *   gaps: list<string>,
     *   warnings: list<string>,
     *   run_id: int,
     *   period_year: int,
     *   period_month: int
     * }
     */
    public static function buildStammkontrolle(array $run, array $employees, string $agBetriebsnummer = ''): array
    {
        $year = (int) ($run['period_year'] ?? 0);
        $month = (int) ($run['period_month'] ?? 0);
        $agBn = preg_replace('/\D+/', '', $agBetriebsnummer) ?? '';
        $gaps = [];
        $warnings = [];
        if ($agBn === '') {
            $gaps[] = 'AG-Betriebsnummer fehlt (Portal-Absender).';
        }
        $rows = [];
        foreach ($employees as $emp) {
            if (!is_array($emp) || ($emp['status'] ?? '') !== PayrollRunRepository::EMP_OK) {
                continue;
            }
            $snap = self::decodeSnapshot($emp['snapshot_json'] ?? null);
            $ed = is_array($snap['employee'] ?? null) ? $snap['employee'] : [];
            $contactId = (int) ($emp['contact_id'] ?? 0);
            $empType = (string) ($ed['payroll_employment_type'] ?? '');
            $path = (string) ($emp['path'] ?? '');
            $svExempt = !empty($ed['sv_exempt']);
            $pathMini = $path === 'mini' || $empType === 'minijob';
            // Mini/exempt: SV-Pflicht-Felder gelockert; sonst Stufe-A-Default = pflichtig
            $svPflicht = !$svExempt && !$pathMini;

            $rowGaps = [];
            $soft = [];
            $personGroup = trim((string) ($ed['person_group_key'] ?? ''));
            $contrib = trim((string) ($ed['contribution_group_key'] ?? ''));
            $kkName = trim((string) ($ed['health_insurance'] ?? ''));
            $kkBn = preg_replace('/\D+/', '', (string) ($ed['health_insurance_company_number'] ?? '')) ?? '';
            $svNr = trim((string) ($ed['social_security_number'] ?? ''));
            $svStatus = (string) ($ed['social_security_status'] ?? '');
            $entry = trim((string) ($ed['entry_date'] ?? ''));
            $exit = trim((string) ($ed['exit_date'] ?? ''));
            $taxId = preg_replace('/\D+/', '', (string) ($ed['tax_id'] ?? '')) ?? '';
            $regStatus = (string) ($ed['social_registration_status'] ?? SocialSecurityRegistrationDraft::STATUS_NONE);

            $needPersonGroup = in_array($empType, ['fulltime', 'parttime', 'midijob', 'minijob'], true) || $empType === '';
            if ($needPersonGroup && $personGroup === '') {
                $rowGaps[] = 'Personengruppe';
            }
            if ($svPflicht && $contrib === '') {
                $rowGaps[] = 'Beitragsgruppe';
            }
            if ($svPflicht && $kkName === '') {
                $rowGaps[] = 'Krankenkasse';
            }
            if ($kkBn === '') {
                $soft[] = 'KK-Betriebsnummer';
            }
            if (($svPflicht || $svStatus === 'received') && $svNr === '') {
                $rowGaps[] = 'SV-Nummer';
            }
            if ($entry === '') {
                $rowGaps[] = 'Eintritt';
            }
            if ($taxId === '' || strlen($taxId) !== 11) {
                $soft[] = 'Steuer-ID';
            }
            if (
                $regStatus !== SocialSecurityRegistrationDraft::STATUS_NONE
                && $regStatus !== SocialSecurityRegistrationDraft::STATUS_READY
            ) {
                $soft[] = 'Anmeldungs-Draft unvollständig';
            }

            $meldehinweis = 'Stamm gegen Vormonat prüfen';
            if ($entry !== '' && self::dateInPeriod($entry, $year, $month)) {
                $meldehinweis = 'Anmeldung DEÜV im Portal';
            }
            if ($exit !== '' && self::dateInPeriod($exit, $year, $month)) {
                $meldehinweis = 'Abmeldung prüfen';
                $soft[] = 'Austritt in Periode';
            }

            foreach ($rowGaps as $g) {
                $gaps[] = 'MA #' . $contactId . ': ' . $g;
            }

            $rows[] = [
                'contact_id' => $contactId,
                'label' => trim((string) ($ed['display_name'] ?? '')) !== ''
                    ? (string) $ed['display_name']
                    : ('MA #' . $contactId),
                'employment_type' => $empType,
                'path' => $path,
                'sv_exempt' => $svExempt,
                'person_group_key' => $personGroup,
                'contribution_group_key' => $contrib,
                'health_insurance' => $kkName,
                'health_insurance_company_number' => $kkBn,
                'social_security_number' => $svNr,
                'social_security_number_masked' => self::maskSvNumber($svNr),
                'entry_date' => $entry,
                'exit_date' => $exit,
                'tax_id' => $taxId,
                'social_registration_status' => $regStatus,
                'gaps' => $rowGaps,
                'soft' => $soft,
                'ampel' => $rowGaps === [] ? ($soft === [] ? 'ok' : 'warn') : 'fail',
                'meldehinweis' => $meldehinweis,
            ];
        }

        return [
            'run_id' => (int) ($run['id'] ?? 0),
            'period_year' => $year,
            'period_month' => $month,
            'ag_betriebsnummer' => $agBn,
            'rows' => $rows,
            'gaps' => array_values(array_unique($gaps)),
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<string, mixed> $beitrag
     */
    public static function toCsvBeitragsnachweis(array $beitrag): string
    {
        $lines = [];
        $lines[] = self::csvLine([
            'Run-ID', 'Jahr', 'Monat', 'Art', 'KK-BN', 'KK-Name', 'MA',
            'KV', 'RV', 'AV', 'PV', 'SV-AN', 'SV-AG', 'U1', 'U2', 'U3', 'Flat', 'SV-Brutto',
        ]);
        $runId = (int) ($beitrag['run_id'] ?? 0);
        $y = (int) ($beitrag['period_year'] ?? 0);
        $m = (int) ($beitrag['period_month'] ?? 0);
        $kind = (string) ($beitrag['kind'] ?? '');
        foreach (is_array($beitrag['groups'] ?? null) ? $beitrag['groups'] : [] as $g) {
            if (!is_array($g)) {
                continue;
            }
            $lines[] = self::csvLine([
                (string) $runId,
                (string) $y,
                sprintf('%02d', $m),
                $kind,
                (string) ($g['kk_bn'] ?? ''),
                (string) ($g['kk_name'] ?? ''),
                (string) (int) ($g['employee_count'] ?? 0),
                self::csvMoney((float) ($g['kv'] ?? 0)),
                self::csvMoney((float) ($g['rv'] ?? 0)),
                self::csvMoney((float) ($g['av'] ?? 0)),
                self::csvMoney((float) ($g['pv'] ?? 0)),
                self::csvMoney((float) ($g['sv_an'] ?? 0)),
                self::csvMoney((float) ($g['sv_ag'] ?? 0)),
                self::csvMoney((float) ($g['u1'] ?? 0)),
                self::csvMoney((float) ($g['u2'] ?? 0)),
                self::csvMoney((float) ($g['u3'] ?? 0)),
                self::csvMoney((float) ($g['flat'] ?? 0)),
                self::csvMoney((float) ($g['gross_sv'] ?? 0)),
            ]);
        }
        $t = is_array($beitrag['totals'] ?? null) ? $beitrag['totals'] : [];
        $lines[] = self::csvLine([
            (string) $runId, (string) $y, sprintf('%02d', $m), $kind, 'TOTAL', '',
            (string) (int) ($t['employee_count'] ?? 0),
            self::csvMoney((float) ($t['kv'] ?? 0)),
            self::csvMoney((float) ($t['rv'] ?? 0)),
            self::csvMoney((float) ($t['av'] ?? 0)),
            self::csvMoney((float) ($t['pv'] ?? 0)),
            self::csvMoney((float) ($t['sv_an'] ?? 0)),
            self::csvMoney((float) ($t['sv_ag'] ?? 0)),
            self::csvMoney((float) ($t['u1'] ?? 0)),
            self::csvMoney((float) ($t['u2'] ?? 0)),
            self::csvMoney((float) ($t['u3'] ?? 0)),
            self::csvMoney((float) ($t['flat'] ?? 0)),
            self::csvMoney((float) ($t['gross_sv'] ?? 0)),
        ]);

        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    /**
     * @param array<string, mixed> $stamm
     */
    public static function toCsvStammkontrolle(array $stamm): string
    {
        $lines = [];
        $lines[] = self::csvLine([
            'Run-ID', 'Jahr', 'Monat', 'Kontakt-ID', 'Name', 'Beschäftigung', 'Pfad',
            'Personengruppe', 'Beitragsgruppe', 'KK', 'KK-BN', 'SV-Nr', 'Eintritt', 'Austritt',
            'Ampel', 'Lücken', 'Soft', 'Meldehinweis',
        ]);
        $runId = (int) ($stamm['run_id'] ?? 0);
        $y = (int) ($stamm['period_year'] ?? 0);
        $m = (int) ($stamm['period_month'] ?? 0);
        foreach (is_array($stamm['rows'] ?? null) ? $stamm['rows'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $lines[] = self::csvLine([
                (string) $runId,
                (string) $y,
                sprintf('%02d', $m),
                (string) (int) ($row['contact_id'] ?? 0),
                (string) ($row['label'] ?? ''),
                (string) ($row['employment_type'] ?? ''),
                (string) ($row['path'] ?? ''),
                (string) ($row['person_group_key'] ?? ''),
                (string) ($row['contribution_group_key'] ?? ''),
                (string) ($row['health_insurance'] ?? ''),
                (string) ($row['health_insurance_company_number'] ?? ''),
                (string) ($row['social_security_number'] ?? ''),
                (string) ($row['entry_date'] ?? ''),
                (string) ($row['exit_date'] ?? ''),
                (string) ($row['ampel'] ?? ''),
                implode('|', is_array($row['gaps'] ?? null) ? $row['gaps'] : []),
                implode('|', is_array($row['soft'] ?? null) ? $row['soft'] : []),
                (string) ($row['meldehinweis'] ?? ''),
            ]);
        }

        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    /**
     * @param array<string, mixed> $beitrag
     * @param array<string, mixed> $stamm
     */
    public static function toCsvCombined(array $beitrag, array $stamm): string
    {
        $a = self::toCsvBeitragsnachweis($beitrag);
        $b = self::toCsvStammkontrolle($stamm);
        // Strip second BOM
        if (str_starts_with($b, "\xEF\xBB\xBF")) {
            $b = substr($b, 3);
        }

        return $a . "\r\n" . $b;
    }

    /**
     * @return array<string, mixed>
     */
    public static function previewBeitragsnachweis(int $runId): array
    {
        [$run, $employees, $agBn] = self::loadRunContext($runId);

        return self::aggregateBeitragsnachweis($run, $employees, $agBn);
    }

    /**
     * @return array<string, mixed>
     */
    public static function previewStammkontrolle(int $runId): array
    {
        [$run, $employees, $agBn] = self::loadRunContext($runId);
        $stamm = self::buildStammkontrolle($run, $employees, $agBn);
        foreach ($stamm['rows'] as &$row) {
            $cid = (int) ($row['contact_id'] ?? 0);
            if ($cid > 0 && ($row['label'] ?? '') === 'MA #' . $cid) {
                $row['label'] = PayrollRunRepository::contactLabel($cid);
            }
        }
        unset($row);

        return $stamm;
    }

    /**
     * @return array{beitrag: array<string, mixed>, stamm: array<string, mixed>}
     */
    public static function previewCombined(int $runId): array
    {
        return [
            'beitrag' => self::previewBeitragsnachweis($runId),
            'stamm' => self::previewStammkontrolle($runId),
        ];
    }

    /**
     * @return array{csv: string, meta: array<string, mixed>|null, filename: string}
     */
    public static function exportCsv(
        int $runId,
        string $kind,
        ?int $userId,
        string $reason = ''
    ): array {
        self::ensureReady();
        if (!in_array($kind, [self::KIND_BEITRAG, self::KIND_STAMM, self::KIND_COMBINED], true)) {
            throw new InvalidArgumentException('Unbekannte Export-Art.');
        }
        $reason = trim($reason);
        $preview = self::previewCombined($runId);
        if ($kind === self::KIND_BEITRAG) {
            if (!empty($preview['beitrag']['blocked'])) {
                throw new InvalidArgumentException('Storno-Run: kein Beitragsnachweis-Export.');
            }
            $csv = self::toCsvBeitragsnachweis($preview['beitrag']);
        } elseif ($kind === self::KIND_STAMM) {
            $csv = self::toCsvStammkontrolle($preview['stamm']);
        } else {
            if (!empty($preview['beitrag']['blocked'])) {
                $csv = self::toCsvStammkontrolle($preview['stamm']);
                $kind = self::KIND_STAMM;
            } else {
                $csv = self::toCsvCombined($preview['beitrag'], $preview['stamm']);
            }
        }
        $filename = sprintf('lohn-sv-%d-%s-%s.csv', $runId, $kind, date('Ymd-His'));
        $checksum = hash('sha256', $csv);
        $rel = '';
        $meta = null;
        if (self::tableReady()) {
            $run = PayrollRunRepository::find($runId);
            $y = (int) ($run['period_year'] ?? date('Y'));
            $m = (int) ($run['period_month'] ?? date('n'));
            $rel = sprintf('%04d/%02d/%s', $y, $m, $filename);
            self::writeStorage($rel, $csv);
            $stmt = Database::pdo()->prepare(
                'INSERT INTO dg_payroll_sv_exports
                    (run_id, created_by_user_id, kind, checksum, filename, storage_path, reason)
                 VALUES
                    (:run_id, :uid, :kind, :checksum, :filename, :path, :reason)'
            );
            $stmt->execute([
                'run_id' => $runId,
                'uid' => $userId,
                'kind' => $kind,
                'checksum' => $checksum,
                'filename' => mb_substr($filename, 0, 200),
                'path' => mb_substr($rel, 0, 500),
                'reason' => mb_substr($reason !== '' ? $reason : 'SV-Portal-Kontroll-CSV', 0, 500),
            ]);
            $id = (int) Database::pdo()->lastInsertId();
            $meta = self::findExport($id);
        }

        return ['csv' => $csv, 'meta' => $meta, 'filename' => $filename];
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
            'SELECT * FROM dg_payroll_sv_exports WHERE run_id = :r ORDER BY id DESC LIMIT 50'
        );
        $stmt->execute(['r' => $runId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
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
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_payroll_sv_exports WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public static function readStoredCsv(int $exportId): ?string
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
            throw new RuntimeException('SV-CSV Checksumme stimmt nicht.');
        }

        return $data;
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>, 2: string}
     */
    private static function loadRunContext(int $runId): array
    {
        self::ensureReady();
        $run = PayrollRunRepository::find($runId);
        if ($run === null) {
            throw new InvalidArgumentException('Lauf nicht gefunden.');
        }
        if ((string) ($run['status'] ?? '') !== PayrollRunRepository::STATUS_LOCKED) {
            throw new InvalidArgumentException('SV-Portal-Export nur für locked Läufe.');
        }
        if (!empty($run['is_superseded'])) {
            throw new InvalidArgumentException('Ersetzter Lauf: keine SV-Portal-Auswertung.');
        }
        $employees = PayrollRunRepository::employees($runId);
        $agBn = '';
        if (class_exists('PayrollEmployerSettings')) {
            $snap = PayrollEmployerSettings::employerSnapshot();
            $agBn = (string) ($snap['betriebsnummer'] ?? '');
        }
        // Fallback: from first employee snapshot employer_lohn
        if ($agBn === '') {
            foreach ($employees as $emp) {
                $s = self::decodeSnapshot($emp['snapshot_json'] ?? null);
                $lohn = is_array($s['employer_lohn'] ?? null) ? $s['employer_lohn'] : [];
                $agBn = (string) ($lohn['betriebsnummer'] ?? '');
                if ($agBn !== '') {
                    break;
                }
            }
        }

        return [$run, $employees, $agBn];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyGroup(string $kkBn, string $kkName): array
    {
        return [
            'kk_bn' => $kkBn,
            'kk_name' => $kkName,
            'filing_offices' => [],
            'contribution_groups' => [],
            'path_notes' => [],
            'kv' => 0.0,
            'rv' => 0.0,
            'av' => 0.0,
            'pv' => 0.0,
            'sv_an' => 0.0,
            'sv_ag' => 0.0,
            'u1' => 0.0,
            'u2' => 0.0,
            'u3' => 0.0,
            'flat' => 0.0,
            'gross_sv' => 0.0,
            'employee_count' => 0,
        ];
    }

    private static function dateInPeriod(string $ymd, int $year, int $month): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-\d{2}/', $ymd, $m)) {
            return false;
        }

        return (int) $m[1] === $year && (int) $m[2] === $month;
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

    private static function writeStorage(string $relative, string $csv): void
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
        if (file_put_contents($abs, $csv) === false) {
            throw new RuntimeException('SV-CSV konnte nicht gespeichert werden.');
        }
    }
}
