<?php
declare(strict_types=1);

/**
 * Orientierungsvergleich Barkauf / Ratenkauf / Leasing / Miete inkl. vereinfachter Steuerwirkung.
 * Keine Steuerberatung, keine Buchungsanlage.
 */
final class AcquisitionCompareService
{
    /** @var list<string> */
    private const KAPG_TYPES = ['gmbh', 'gmbh_igr', 'ug', 'ug_igr', 'ltd', 'eg'];

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $companyCfg CompanyExtendedSettings::config() oder Override für Tests
     * @return array<string, mixed>
     */
    public static function compare(array $input, ?array $companyCfg = null): array
    {
        $cfg = $companyCfg ?? CompanyExtendedSettings::config();
        $rates = self::normalizeTaxRates(is_array($cfg['tax_rates'] ?? null) ? $cfg['tax_rates'] : []);
        $companyType = (string) ($cfg['company_type'] ?? '');
        $isKapG = in_array($companyType, self::KAPG_TYPES, true);
        if (array_key_exists('_vat_deductible', $cfg)) {
            $vatDeductible = (bool) $cfg['_vat_deductible'];
        } else {
            $vatDeductible = !CompanyExtendedSettings::isKleinunternehmerActiveOn(date('Y-m-d'));
        }

        $net = max(0.0, (float) ($input['net'] ?? 0));
        $vatRate = max(0.0, min(19.0, (float) ($input['vat_rate'] ?? 19)));
        $usefulLife = max(1, min(50, (int) ($input['useful_life_years'] ?? 3)));
        $nonDeductible = max(0.0, min(1.0, (float) ($input['non_deductible_share'] ?? 0)));
        $downPayment = max(0.0, (float) ($input['down_payment'] ?? 0));
        $termMonths = max(1, min(120, (int) ($input['term_months'] ?? 36)));
        $interestPa = max(0.0, min(30.0, (float) ($input['interest_pa'] ?? 6)));
        $leaseRate = max(0.0, (float) ($input['lease_rate'] ?? 0));
        $rentRate = max(0.0, (float) ($input['rent_rate'] ?? 0));
        $residual = max(0.0, (float) ($input['residual'] ?? 0));

        $vatAmount = round($net * $vatRate / 100.0, 2);
        $gross = round($net + $vatAmount, 2);
        $isGwg = $net > 0 && $net <= AfaCatalog::GWG_NETTO_LIMIT;
        $horizonYears = max($usefulLife, (int) ceil($termMonths / 12));

        $eff = self::effectiveRates($rates, $isKapG);
        $deductibleFactor = 1.0 - $nonDeductible;

        // Default leasing/rent rates if not set: rough orientation
        if ($leaseRate <= 0 && $net > 0) {
            $leaseRate = round(($net * 1.05) / max(1, $termMonths), 2);
        }
        if ($rentRate <= 0 && $net > 0) {
            $rentRate = round(($net * 1.15) / max(1, $termMonths), 2);
        }

        $models = [
            'barkauf' => self::modelBarkauf($net, $gross, $vatAmount, $usefulLife, $isGwg, $horizonYears, $vatDeductible, $eff, $deductibleFactor),
            'ratenkauf' => self::modelRatenkauf($net, $gross, $vatAmount, $usefulLife, $isGwg, $horizonYears, $vatDeductible, $eff, $deductibleFactor, $downPayment, $termMonths, $interestPa),
            'leasing' => self::modelPeriodic($leaseRate, $termMonths, $horizonYears, $vatRate, $vatDeductible, $eff, $deductibleFactor, $residual, 'Leasing'),
            'miete' => self::modelPeriodic($rentRate, $termMonths, $horizonYears, $vatRate, $vatDeductible, $eff, $deductibleFactor, 0.0, 'Miete'),
        ];

        return [
            'meta' => [
                'net' => $net,
                'vat_rate' => $vatRate,
                'vat_amount' => $vatAmount,
                'gross' => $gross,
                'useful_life_years' => $usefulLife,
                'is_gwg' => $isGwg,
                'horizon_years' => $horizonYears,
                'company_type' => $companyType,
                'is_kapg' => $isKapG,
                'vat_deductible' => $vatDeductible,
                'non_deductible_share' => $nonDeductible,
                'tax_regime_label' => $isKapG ? 'Körperschaftsteuer + SolZ + Gewerbesteuer' : 'ESt (Grenzsatz) + Gewerbesteuer',
                'effective' => $eff,
                'disclaimer' => 'Orientierungswerte — keine Steuerberatung. Vereinfachte lineare AfA, keine Sonder-AfA, keine 1%-Regelung, keine HGB-Leasingbilanzierung. GewSt/KSt/ESt additiv ohne Wechselwirkungen.',
            ],
            'models' => $models,
        ];
    }

    /**
     * @param array{gewst_hebesatz: float, kst_satz: float, solz_satz: float, est_marginal: float} $rates
     * @return array{gewst: float, income_tax: float, total: float, breakdown: array<string, float>}
     */
    public static function effectiveRates(array $rates, bool $isKapG): array
    {
        $gewst = 0.035 * ($rates['gewst_hebesatz'] / 100.0);
        if ($isKapG) {
            $kst = $rates['kst_satz'] / 100.0;
            $kstWithSolz = $kst * (1.0 + $rates['solz_satz'] / 100.0);

            return [
                'gewst' => $gewst,
                'income_tax' => $kstWithSolz,
                'total' => $gewst + $kstWithSolz,
                'breakdown' => [
                    'gewst' => $gewst,
                    'kst' => $kst,
                    'solz_on_kst' => $kst * ($rates['solz_satz'] / 100.0),
                    'kst_with_solz' => $kstWithSolz,
                    'est' => 0.0,
                ],
            ];
        }

        $est = $rates['est_marginal'] / 100.0;

        return [
            'gewst' => $gewst,
            'income_tax' => $est,
            'total' => $gewst + $est,
            'breakdown' => [
                'gewst' => $gewst,
                'kst' => 0.0,
                'solz_on_kst' => 0.0,
                'kst_with_solz' => 0.0,
                'est' => $est,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{gewst_hebesatz: float, kst_satz: float, solz_satz: float, est_marginal: float}
     */
    private static function normalizeTaxRates(array $raw): array
    {
        return [
            'gewst_hebesatz' => max(0.0, min(900.0, (float) ($raw['gewst_hebesatz'] ?? 400))),
            'kst_satz' => max(0.0, min(30.0, (float) ($raw['kst_satz'] ?? 15))),
            'solz_satz' => max(0.0, min(10.0, (float) ($raw['solz_satz'] ?? 5.5))),
            'est_marginal' => max(0.0, min(55.0, (float) ($raw['est_marginal'] ?? 42))),
        ];
    }

    /**
     * @param array{gewst: float, income_tax: float, total: float, breakdown: array<string, float>} $eff
     * @return array<string, mixed>
     */
    private static function modelBarkauf(
        float $net,
        float $gross,
        float $vatAmount,
        int $usefulLife,
        bool $isGwg,
        int $horizonYears,
        bool $vatDeductible,
        array $eff,
        float $deductibleFactor,
    ): array {
        $years = [];
        for ($y = 0; $y < $horizonYears; $y++) {
            $cash = 0.0;
            $expense = 0.0;
            $vatCash = 0.0;
            if ($y === 0) {
                $cash = -$gross;
                if ($vatDeductible) {
                    $vatCash = $vatAmount; // Vorsteuererstattung / Verrechnung (positiv = Entlastung)
                }
                if ($isGwg) {
                    $expense = $net * $deductibleFactor;
                }
            }
            if (!$isGwg && $y < $usefulLife) {
                $expense = ($net / $usefulLife) * $deductibleFactor;
            }
            $years[] = self::yearRow($y, $cash, $expense, $vatCash, $eff);
        }

        return self::summarize('Barkauf', $years, [
            'note' => $isGwg ? 'GWG: Sofortabschreibung (Netto ≤ 800 €).' : 'Lineare AfA, ' . $usefulLife . ' Jahre.',
        ]);
    }

    /**
     * @param array{gewst: float, income_tax: float, total: float, breakdown: array<string, float>} $eff
     * @return array<string, mixed>
     */
    private static function modelRatenkauf(
        float $net,
        float $gross,
        float $vatAmount,
        int $usefulLife,
        bool $isGwg,
        int $horizonYears,
        bool $vatDeductible,
        array $eff,
        float $deductibleFactor,
        float $downPayment,
        int $termMonths,
        float $interestPa,
    ): array {
        $down = min($downPayment, $gross);
        $financed = max(0.0, $gross - $down);
        $monthly = self::annuityPayment($financed, $interestPa, $termMonths);
        $totalPaid = $down + $monthly * $termMonths;
        $totalInterest = max(0.0, $totalPaid - $gross);

        $years = [];
        $monthCursor = 0;
        for ($y = 0; $y < $horizonYears; $y++) {
            $cash = 0.0;
            $expense = 0.0;
            $vatCash = 0.0;
            if ($y === 0) {
                $cash -= $down;
                if ($vatDeductible) {
                    $vatCash = $vatAmount;
                }
                if ($isGwg) {
                    $expense += $net * $deductibleFactor;
                }
            }
            $monthsThisYear = 0;
            while ($monthCursor < $termMonths && $monthsThisYear < 12) {
                $cash -= $monthly;
                $monthCursor++;
                $monthsThisYear++;
            }
            // Zinsanteil grob proportional zu den Monaten dieses Jahres
            if ($termMonths > 0 && $monthsThisYear > 0) {
                $expense += ($totalInterest * ($monthsThisYear / $termMonths)) * $deductibleFactor;
            }
            if (!$isGwg && $y < $usefulLife) {
                $expense += ($net / $usefulLife) * $deductibleFactor;
            }
            $years[] = self::yearRow($y, $cash, $expense, $vatCash, $eff);
        }

        return self::summarize('Ratenkauf', $years, [
            'monthly_rate' => round($monthly, 2),
            'total_interest' => round($totalInterest, 2),
            'note' => 'Annuität vereinfacht; AfA + Zinsen.',
        ]);
    }

    /**
     * @param array{gewst: float, income_tax: float, total: float, breakdown: array<string, float>} $eff
     * @return array<string, mixed>
     */
    private static function modelPeriodic(
        float $monthlyRate,
        int $termMonths,
        int $horizonYears,
        float $vatRate,
        bool $vatDeductible,
        array $eff,
        float $deductibleFactor,
        float $residual,
        string $label,
    ): array {
        $rateNet = $monthlyRate;
        // Rate als Brutto interpretieren wenn USt > 0 und Nutzer Bruttorate meint — Plan: VSt auf Rate
        $rateVat = $vatDeductible ? round($rateNet * $vatRate / (100.0 + $vatRate), 2) : 0.0;
        $rateExpenseBase = $vatDeductible ? round($rateNet - $rateVat, 2) : $rateNet;

        $years = [];
        $monthCursor = 0;
        for ($y = 0; $y < $horizonYears; $y++) {
            $cash = 0.0;
            $expense = 0.0;
            $vatCash = 0.0;
            $monthsThisYear = 0;
            while ($monthCursor < $termMonths && $monthsThisYear < 12) {
                $cash -= $rateNet;
                $expense += $rateExpenseBase * $deductibleFactor;
                $vatCash += $rateVat;
                $monthCursor++;
                $monthsThisYear++;
            }
            if ($y === $horizonYears - 1 && $residual > 0) {
                $cash -= $residual;
            }
            $years[] = self::yearRow($y, $cash, $expense, $vatCash, $eff);
        }

        return self::summarize($label, $years, [
            'monthly_rate' => round($monthlyRate, 2),
            'note' => $label . '-Raten als Betriebsausgabe.',
        ]);
    }

    private static function annuityPayment(float $principal, float $interestPa, int $months): float
    {
        if ($principal <= 0 || $months < 1) {
            return 0.0;
        }
        $r = ($interestPa / 100.0) / 12.0;
        if ($r <= 0) {
            return round($principal / $months, 2);
        }
        $factor = pow(1 + $r, $months);

        return round($principal * $r * $factor / ($factor - 1), 2);
    }

    /**
     * @param array{gewst: float, income_tax: float, total: float, breakdown: array<string, float>} $eff
     * @return array<string, float|int>
     */
    private static function yearRow(int $yearIndex, float $cash, float $expense, float $vatCash, array $eff): array
    {
        $expense = round($expense, 2);
        $taxReliefTotal = round($expense * $eff['total'], 2);
        $taxGewst = round($expense * $eff['gewst'], 2);
        $taxIncome = round($expense * $eff['income_tax'], 2);
        $cashNet = round($cash + $vatCash + $taxReliefTotal, 2);

        return [
            'year' => $yearIndex + 1,
            'cash' => round($cash, 2),
            'vat_cash' => round($vatCash, 2),
            'expense' => $expense,
            'tax_gewst' => $taxGewst,
            'tax_income' => $taxIncome,
            'tax_relief' => $taxReliefTotal,
            'net_burden' => $cashNet,
        ];
    }

    /**
     * @param list<array<string, float|int>> $years
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private static function summarize(string $label, array $years, array $extra = []): array
    {
        $sumCash = 0.0;
        $sumVat = 0.0;
        $sumExpense = 0.0;
        $sumTax = 0.0;
        $sumNet = 0.0;
        foreach ($years as $row) {
            $sumCash += (float) $row['cash'];
            $sumVat += (float) $row['vat_cash'];
            $sumExpense += (float) $row['expense'];
            $sumTax += (float) $row['tax_relief'];
            $sumNet += (float) $row['net_burden'];
        }

        return array_merge([
            'label' => $label,
            'years' => $years,
            'totals' => [
                'cash' => round($sumCash, 2),
                'vat_cash' => round($sumVat, 2),
                'expense' => round($sumExpense, 2),
                'tax_relief' => round($sumTax, 2),
                'tax_gewst' => round(array_sum(array_map(static fn ($r) => (float) $r['tax_gewst'], $years)), 2),
                'tax_income' => round(array_sum(array_map(static fn ($r) => (float) $r['tax_income'], $years)), 2),
                'net_burden' => round($sumNet, 2),
            ],
        ], $extra);
    }
}
