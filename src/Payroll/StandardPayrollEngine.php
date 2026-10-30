<?php
declare(strict_types=1);

/**
 * Lohn L4c: Standard-Brutto→Netto (Stufe A, Fixture-/Import-Parameter).
 *
 * Pure Funktion — schreibt nicht in die DB. Rundung: kaufmännisch ROUND_HALF_UP, 2 Dezimalen.
 *
 * employer_cost = gross_total + KV/RV/AV/PV AG + Umlagen + flat_lst + flat_other
 *   (Minijob-Pauschalen sind AG-getragen → nicht in net_pay).
 */
final class StandardPayrollEngine implements PayrollEngineInterface
{
    public function calculate(PayrollCalcRequest $request): PayrollCalcResult
    {
        $warnings = [];
        $params = self::normalizeParams($request->rateParams);
        if ($params === []) {
            return self::fail($request, ['Beitragstabelle (rateParams) fehlt.'], 'none');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->periodYmd)) {
            return self::fail($request, ['period_ymd ungültig.'], 'none');
        }

        $empType = trim((string) ($request->employee['payroll_employment_type'] ?? ''));
        $path = match ($empType) {
            'minijob' => 'mini',
            'midijob' => 'midi',
            'managing_director' => 'gf',
            'fulltime', 'parttime' => 'standard',
            default => '',
        };
        if ($path === '') {
            return self::fail($request, ['payroll_employment_type fehlt oder unbekannt.'], 'none');
        }

        $built = self::buildLines($request->lines);
        if ($built['earnings'] === [] && $built['deductions'] === []) {
            return self::fail($request, ['Keine Lohnpositionen.'], $path);
        }

        $grossTotal = self::round2($built['gross_total']);
        $grossTaxable = self::round2($built['gross_taxable']);
        $grossSvRaw = self::round2($built['gross_sv']);
        $netDeductions = self::round2($built['net_deductions']);
        $resultLines = $built['lines'];

        // Pflichtfelder (vereinfachte Stufe-A-Matrix)
        $blockers = self::collectBlockers($request, $path, $grossTotal);
        if ($blockers !== []) {
            return self::fail($request, array_merge($warnings, $blockers), $path);
        }

        $svExempt = $path === 'gf'
            && !empty($request->employee['controlling_managing_director'])
            && !empty($request->employee['sv_exempt']);

        $flatLst = 0.0;
        $flatOther = 0.0;
        $lst = 0.0;
        $solz = 0.0;
        $kst = 0.0;
        $kvAn = $kvAg = $rvAn = $rvAg = $avAn = $avAg = $pvAn = $pvAg = 0.0;
        $u1 = $u2 = $u3 = 0.0;
        $grossSv = $grossSvRaw;

        if ($path === 'mini') {
            $limit = self::param($params, 'minijob_month_limit_eur');
            if ($grossTotal > $limit + 0.00001) {
                return self::fail(
                    $request,
                    ['Brutto über Minijob-Grenze (' . self::fmt($limit) . ' EUR) — kein Mini-Pfad.'],
                    $path
                );
            }
            if ($request->minijobFlatPath) {
                $flatLst = self::round2($grossTotal * self::param($params, 'minijob_flat_lst_rate'));
                $flatOther = self::round2($grossTotal * self::param($params, 'minijob_flat_kv_rate'));
                // AG-getragen → flat_lst_employee_share = 0; keine individuelle LSt/SV-AN
                $warnings[] = 'Minijob-Pauschalen (AG); keine individuelle LSt/SV-AN im Default-Fixture.';
            } else {
                [$lst, $solz, $kst] = self::calcTax($grossTaxable, $request->employee, $params);
            }
            $grossSv = 0.0;
        } elseif ($svExempt) {
            [$lst, $solz, $kst] = self::calcTax($grossTaxable, $request->employee, $params);
            $warnings[] = 'SV-frei laut Stamm (beherrschender GF).';
            $grossSv = 0.0;
        } else {
            if ($path === 'midi') {
                $lo = self::param($params, 'midi_lower_eur');
                $hi = self::param($params, 'midi_upper_eur');
                if ($grossSvRaw + 0.00001 < $lo || $grossSvRaw - 0.00001 > $hi) {
                    $warnings[] = 'Brutto außerhalb Midi-Band — SV-Berechnung trotzdem mit midi_an_factor.';
                }
                if (trim((string) ($request->employee['annual_earnings_estimate_eur'] ?? '')) === '') {
                    $warnings[] = 'Jahresentgelt-Schätzer (Midi) fehlt — nur Hinweis.';
                }
            }
            $grossSv = self::applyBbg($grossSvRaw, $params);
            [$lst, $solz, $kst] = self::calcTax($grossTaxable, $request->employee, $params);
            $midiFactor = $path === 'midi' ? self::param($params, 'midi_an_factor', 1.0) : 1.0;
            $sv = self::calcSv($grossSv, $params, $midiFactor);
            $kvAn = $sv['kv_an'];
            $kvAg = $sv['kv_ag'];
            $rvAn = $sv['rv_an'];
            $rvAg = $sv['rv_ag'];
            $avAn = $sv['av_an'];
            $avAg = $sv['av_ag'];
            $pvAn = $sv['pv_an'];
            $pvAg = $sv['pv_ag'];
            $uml = self::calcUmlagen($grossSv, $params, $request->employerLohn);
            $u1 = $uml['u1'];
            $u2 = $uml['u2'];
            $u3 = $uml['u3'];
        }

        // flat_lst_employee_share: Stufe A Minijob-Default = 0 (AG trägt)
        $flatEmployeeShare = 0.0;
        $netPay = self::round2(
            $grossTotal - $lst - $solz - $kst - ($kvAn + $rvAn + $avAn + $pvAn)
            - $netDeductions - $flatEmployeeShare
        );
        $employerCost = self::round2(
            $grossTotal + $kvAg + $rvAg + $avAg + $pvAg + $u1 + $u2 + $u3 + $flatLst + $flatOther
        );

        $amounts = [
            'gross_taxable' => $grossTaxable,
            'gross_sv' => $grossSv,
            'gross_total' => $grossTotal,
            'lst' => $lst,
            'solz' => $solz,
            'kst' => $kst,
            'kv_an' => $kvAn,
            'kv_ag' => $kvAg,
            'rv_an' => $rvAn,
            'rv_ag' => $rvAg,
            'av_an' => $avAn,
            'av_ag' => $avAg,
            'pv_an' => $pvAn,
            'pv_ag' => $pvAg,
            'u1' => $u1,
            'u2' => $u2,
            'u3' => $u3,
            'flat_lst' => $flatLst,
            'flat_other' => $flatOther,
            'flat_lst_employee_share' => $flatEmployeeShare,
            'net_deductions' => $netDeductions,
            'net_pay' => $netPay,
            'employer_cost' => $employerCost,
        ];

        return new PayrollCalcResult(
            true,
            'standard',
            $request->rateSetId,
            $amounts,
            $warnings,
            $resultLines,
            $path
        );
    }

    /**
     * Optional: Request mit Params aus DB füllen (wenn rateParams leer und rateSetId/Periode gesetzt).
     */
    public static function hydrateRates(PayrollCalcRequest $request): PayrollCalcRequest
    {
        if ($request->rateParams !== []) {
            return $request;
        }
        if (!class_exists('PayrollRateTableRepository') || !PayrollRateTableRepository::tableReady()) {
            return $request;
        }
        $set = null;
        if ($request->rateSetId !== null && $request->rateSetId > 0) {
            $set = PayrollRateTableRepository::find($request->rateSetId);
        }
        if ($set === null) {
            $set = PayrollRateTableRepository::findForPeriod($request->periodYmd);
        }
        if ($set === null) {
            return $request;
        }
        $id = (int) ($set['id'] ?? 0);
        $params = PayrollRateTableRepository::paramsForSet($id);

        return new PayrollCalcRequest(
            $request->contactId,
            $request->periodYmd,
            $request->employerLohn,
            $request->employee,
            $request->lines,
            $params,
            $id,
            $request->minijobFlatPath
        );
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, float>
     */
    private static function normalizeParams(array $raw): array
    {
        $out = [];
        foreach ($raw as $k => $v) {
            $key = (string) $k;
            if ($key === '') {
                continue;
            }
            if (is_numeric($v)) {
                $out[$key] = (float) $v;
            }
        }

        return $out;
    }

    private static function param(array $params, string $key, float $default = 0.0): float
    {
        return isset($params[$key]) ? (float) $params[$key] : $default;
    }

    /**
     * @param list<array<string, mixed>> $lines
     * @return array{gross_total: float, gross_taxable: float, gross_sv: float, net_deductions: float, earnings: list, deductions: list, lines: list}
     */
    private static function buildLines(array $lines): array
    {
        $grossTotal = 0.0;
        $grossTaxable = 0.0;
        $grossSv = 0.0;
        $netDeductions = 0.0;
        $earnings = [];
        $deductions = [];
        $outLines = [];

        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $direction = (string) ($line['direction'] ?? 'earning');
            $unit = (string) ($line['unit'] ?? 'amount');
            $amount = (float) ($line['amount'] ?? 0);
            if ($unit === 'hour') {
                $hours = (float) ($line['hours'] ?? 0);
                $amount = $amount * $hours;
            }
            $amount = self::round2($amount);
            $taxable = !empty($line['taxable']);
            $subjectSv = !empty($line['subject_to_sv']);
            $row = [
                'code' => (string) ($line['code'] ?? ''),
                'amount' => $amount,
                'direction' => $direction,
                'taxable' => $taxable ? 1 : 0,
                'subject_to_sv' => $subjectSv ? 1 : 0,
                'unit' => $unit,
            ];
            $outLines[] = $row;
            if ($direction === 'deduction') {
                $netDeductions += $amount;
                $deductions[] = $row;
                continue;
            }
            $grossTotal += $amount;
            if ($taxable) {
                $grossTaxable += $amount;
            }
            if ($subjectSv) {
                $grossSv += $amount;
            }
            $earnings[] = $row;
        }

        return [
            'gross_total' => $grossTotal,
            'gross_taxable' => $grossTaxable,
            'gross_sv' => $grossSv,
            'net_deductions' => $netDeductions,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'lines' => $outLines,
        ];
    }

    /**
     * @return list<string>
     */
    private static function collectBlockers(PayrollCalcRequest $request, string $path, float $grossTotal): array
    {
        $e = $request->employee;
        $out = [];
        if ($grossTotal <= 0 && $path !== 'mini') {
            // Abzüge allein ohne Brutto: erlaubt nur wenn earnings existieren; buildLines already checked
        }
        if ($path === 'mini') {
            return $out;
        }
        if ($path === 'gf' && !empty($e['controlling_managing_director']) && !empty($e['sv_exempt'])) {
            if (trim((string) ($e['tax_class'] ?? '')) === '') {
                $out[] = 'Steuerklasse fehlt.';
            }

            return $out;
        }
        if (trim((string) ($e['tax_class'] ?? '')) === '') {
            $out[] = 'Steuerklasse fehlt.';
        }
        if (trim((string) ($e['contribution_group_key'] ?? '')) === '') {
            $out[] = 'Beitragsgruppenschlüssel fehlt.';
        }
        if (trim((string) ($e['person_group_key'] ?? '')) === '') {
            $out[] = 'Personengruppenschlüssel fehlt.';
        }

        return $out;
    }

    /**
     * @return array{0: float, 1: float, 2: float} lst, solz, kst
     */
    private static function calcTax(float $grossTaxable, array $employee, array $params): array
    {
        $factor = self::param($params, 'lst_fixture_factor');
        $lst = self::round2($grossTaxable * $factor);
        $solz = self::round2($lst * self::param($params, 'solz_rate'));
        $religion = strtolower(trim((string) ($employee['religion_code'] ?? 'none')));
        $kstRate = 0.0;
        if ($religion === 'ek') {
            $kstRate = self::param($params, 'kst_ek_rate');
        } elseif ($religion === 'rk') {
            $kstRate = self::param($params, 'kst_rk_rate');
        }
        $kst = self::round2($lst * $kstRate);

        return [$lst, $solz, $kst];
    }

    /**
     * @return array{kv_an: float, kv_ag: float, rv_an: float, rv_ag: float, av_an: float, av_ag: float, pv_an: float, pv_ag: float}
     */
    private static function calcSv(float $grossSv, array $params, float $midiAnFactor): array
    {
        $kvAnBase = self::param($params, 'kv_an_rate') + self::param($params, 'kv_additional_an_rate');
        $f = $midiAnFactor;

        return [
            'kv_an' => self::round2($grossSv * $kvAnBase * $f),
            'kv_ag' => self::round2($grossSv * self::param($params, 'kv_ag_rate')),
            'rv_an' => self::round2($grossSv * self::param($params, 'rv_an_rate') * $f),
            'rv_ag' => self::round2($grossSv * self::param($params, 'rv_ag_rate')),
            'av_an' => self::round2($grossSv * self::param($params, 'av_an_rate') * $f),
            'av_ag' => self::round2($grossSv * self::param($params, 'av_ag_rate')),
            'pv_an' => self::round2($grossSv * (self::param($params, 'pv_an_rate') + self::param($params, 'pv_childless_surcharge_an')) * $f),
            'pv_ag' => self::round2($grossSv * self::param($params, 'pv_ag_rate')),
        ];
    }

    /**
     * @param array<string, mixed> $employerLohn
     * @return array{u1: float, u2: float, u3: float}
     */
    private static function calcUmlagen(float $grossSv, array $params, array $employerLohn): array
    {
        $u1 = !empty($employerLohn['umlage_u1'])
            ? self::round2($grossSv * self::param($params, 'u1_rate')) : 0.0;
        $u2 = !empty($employerLohn['umlage_u2'])
            ? self::round2($grossSv * self::param($params, 'u2_rate')) : 0.0;
        $u3 = !empty($employerLohn['umlage_u3'])
            ? self::round2($grossSv * self::param($params, 'u3_rate')) : 0.0;

        return ['u1' => $u1, 'u2' => $u2, 'u3' => $u3];
    }

    private static function applyBbg(float $grossSv, array $params): float
    {
        // Vereinfachung Stufe A: ein SV-Brutto, Cap am niedrigeren relevanten BBG-Hinweis (KV)
        $bbgKv = self::param($params, 'bbg_kv_month_eur', 999999.0);
        $bbgRv = self::param($params, 'bbg_rv_month_eur', 999999.0);
        // Für Beitragsteile später differenzierbar; hier Cap auf max für Rohwert-Dokumentation:
        // Engine nutzt gross_sv ungecappt je Zweig nicht getrennt — Cap auf bbg_rv als Obergrenze Summe.
        return self::round2(min($grossSv, max($bbgKv, $bbgRv)));
    }

    public static function round2(float $value): float
    {
        return round($value, 2, PHP_ROUND_HALF_UP);
    }

    private static function fmt(float $v): string
    {
        return number_format($v, 2, ',', '.');
    }

    /**
     * @param list<string> $warnings
     */
    private static function fail(PayrollCalcRequest $request, array $warnings, string $path): PayrollCalcResult
    {
        return new PayrollCalcResult(
            false,
            'standard',
            $request->rateSetId,
            [],
            $warnings,
            [],
            $path
        );
    }
}
