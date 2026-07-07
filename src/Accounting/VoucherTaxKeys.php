<?php
declare(strict_types=1);

/**
 * Häufige DATEV-Steuerschlüssel für die Belegerfassung (UI-Auswahl).
 */
final class VoucherTaxKeys
{
    public const KEY_NONE = '';
    public const KEY_VST_19 = '9';
    public const KEY_VST_7 = '8';
    public const KEY_ZERO = '0';
    public const KEY_UST_19 = '3';
    public const KEY_UST_7 = '2';
    public const KEY_REVERSE_CHARGE = '94';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::KEY_NONE => '— Kein Steuerschlüssel —',
            self::KEY_VST_19 => '9 — Vorsteuer 19 %',
            self::KEY_VST_7 => '8 — Vorsteuer 7 %',
            self::KEY_ZERO => '0 — Steuerfrei / keine Vorsteuer',
            self::KEY_UST_19 => '3 — Umsatzsteuer 19 %',
            self::KEY_UST_7 => '2 — Umsatzsteuer 7 %',
            self::KEY_REVERSE_CHARGE => '94 — Reverse Charge §13b',
        ];
    }

    public static function label(string $key): string
    {
        return self::options()[$key] ?? ($key !== '' ? 'Schlüssel ' . $key : '—');
    }

    /** @return list<int> */
    public static function allowedTaxRates(): array
    {
        return [0, 7, 19];
    }

    public static function sanitizeTaxRate(int $rate): int
    {
        return in_array($rate, self::allowedTaxRates(), true) ? $rate : 19;
    }

    public static function sanitizeTaxKey(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return '';
        }
        if (!isset(self::options()[$key])) {
            return preg_match('/^\d{1,3}$/', $key) === 1 ? $key : '';
        }

        return $key;
    }

    /** Steuerbetrag aus Brutto und Satz berechnen (inklusive MwSt.). */
    public static function calcTaxFromGross(float $gross, int $taxRate): array
    {
        $gross = round(max(0, $gross), 2);
        if ($taxRate <= 0) {
            return [
                'gross_amount' => $gross,
                'net_amount' => $gross,
                'tax_amount' => 0.0,
            ];
        }

        $net = round($gross / (1 + $taxRate / 100), 2);
        $tax = round($gross - $net, 2);

        return [
            'gross_amount' => $gross,
            'net_amount' => $net,
            'tax_amount' => $tax,
        ];
    }

    public static function isReverseChargeKey(string $taxKey): bool
    {
        return self::sanitizeTaxKey($taxKey) === self::KEY_REVERSE_CHARGE;
    }

    /**
     * Zeilenbetrag aus Buchungszeile: normal inkl. USt., bei §13b netto (Rechnungsbetrag ohne USt.).
     *
     * @return array{gross_amount: float, net_amount: float, tax_amount: float}
     */
    public static function calcLineAmounts(float $amount, int $taxRate, bool $reverseCharge): array
    {
        $amount = round(max(0, $amount), 2);
        if ($reverseCharge) {
            $tax = $taxRate > 0 ? round($amount * $taxRate / 100, 2) : 0.0;

            return [
                'gross_amount' => $amount,
                'net_amount' => $amount,
                'tax_amount' => $tax,
            ];
        }

        return self::calcTaxFromGross($amount, $taxRate);
    }

    /**
     * MwSt.-Summen je Steuersatz aus Buchungszeilen (Formular-Anzeige).
     *
     * @param list<array<string, mixed>> $lines
     * @return array<int, float>
     */
    public static function taxBreakdownFromLines(array $lines, bool $reverseCharge): array
    {
        $breakdown = array_fill_keys(self::allowedTaxRates(), 0.0);
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $gross = round((float) str_replace(',', '.', (string) ($line['gross_amount'] ?? '0')), 2);
            if ($gross <= 0) {
                continue;
            }
            $rate = self::sanitizeTaxRate((int) ($line['tax_rate'] ?? 19));
            $amounts = self::calcLineAmounts($gross, $rate, $reverseCharge);
            $breakdown[$rate] += (float) $amounts['tax_amount'];
        }

        foreach ($breakdown as $rate => $amount) {
            $breakdown[$rate] = round($amount, 2);
        }

        return $breakdown;
    }

    /**
     * @param array<int, float> $breakdown
     * @return list<string>
     */
    public static function taxBreakdownDisplayLines(array $breakdown): array
    {
        $lines = [];
        foreach (self::allowedTaxRates() as $rate) {
            $amount = round((float) ($breakdown[$rate] ?? 0), 2);
            if ($amount > 0) {
                $lines[] = (int) $rate . ' %: ' . number_format($amount, 2, ',', '.') . ' €';
            }
        }

        return $lines;
    }

    /**
     * @param array<int, float> $breakdown
     */
    public static function formatTaxBreakdownDisplay(array $breakdown): string
    {
        $lines = self::taxBreakdownDisplayLines($breakdown);

        return $lines !== [] ? implode(' · ', $lines) : '—';
    }
}
