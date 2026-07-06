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
}
