<?php
declare(strict_types=1);

/** Validierung für Kalender-Leistungen (Artikelnummer, GTIN, Preis). */
final class CalendarArticleValidator
{
    /** @return array<string, string> */
    public static function taxTypes(): array
    {
        return [
            'ust19' => 'USt 19%',
            'ust7' => 'USt 7%',
            'ust0' => 'USt 0%',
            'ust0_13b' => 'USt 0% §13b (z. B. Photovoltaik)',
            'ust0_19' => 'USt 0% § 19 UStG',
            'ust0_4' => 'USt 0% § 4 UStG',
        ];
    }

    /** @return list<string> */
    public static function units(): array
    {
        return ['Einheit', 'Stück', 'Stunde'];
    }

    public static function validateArticleNumber(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException('Artikelnummer ist erforderlich.');
        }
        if (!preg_match('/^[A-Za-z0-9+\-]+$/', $value)) {
            throw new InvalidArgumentException('Artikelnummer darf nur Buchstaben, Ziffern sowie + und - enthalten.');
        }

        return $value;
    }

    public static function validateGtin(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';
        if ($digits === '') {
            throw new InvalidArgumentException('GTIN/EAN darf nur Ziffern enthalten.');
        }

        $length = strlen($digits);
        if (!in_array($length, [8, 12, 13, 14], true)) {
            throw new InvalidArgumentException('GTIN/EAN muss 8, 12, 13 oder 14 Ziffern haben.');
        }

        if (!self::isValidGs1CheckDigit($digits)) {
            throw new InvalidArgumentException('Die GTIN/EAN-Prüfziffer ist ungültig.');
        }

        return $digits;
    }

    public static function validateTaxType(string $value): string
    {
        $value = trim($value);
        if (!isset(self::taxTypes()[$value])) {
            throw new InvalidArgumentException('Ungültige Steuerart.');
        }

        return $value;
    }

    public static function validateUnit(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'Stück';
        }
        if (!in_array($value, self::units(), true)) {
            throw new InvalidArgumentException('Ungültige Einheit.');
        }

        return $value;
    }

    public static function validatePriceGross(mixed $value, bool $allowNegative = false): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Gültiger Bruttopreis erforderlich.');
        }

        $price = round((float) $value, 2);
        if (!$allowNegative && $price < 0) {
            throw new InvalidArgumentException('Der Preis darf nicht negativ sein.');
        }

        return $price;
    }

    public static function validateImportPrice(mixed $value): float
    {
        return self::validatePriceGross($value, true);
    }

    public static function formatPrice(float $price): string
    {
        return number_format($price, 2, ',', '.') . ' €';
    }

    public static function taxLabel(string $taxType): string
    {
        return self::taxTypes()[$taxType] ?? $taxType;
    }

    public static function normalizeImportTitle(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_strlen') && mb_strlen($value) > 255) {
            return mb_substr($value, 0, 255);
        }
        if (strlen($value) > 255) {
            return substr($value, 0, 255);
        }

        return $value;
    }

    public static function normalizeUnitForImport(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'Stück';
        }

        $replacements = [
            'Stόck' => 'Stück',
            'St?ck' => 'Stück',
            'Stck' => 'Stück',
            'Stueck' => 'Stück',
            'Pauschal' => 'Pauschal',
            'Gesamt' => 'Gesamt',
        ];
        $value = strtr($value, $replacements);
        if (preg_match('/^st.ck$/iu', $value)) {
            $value = 'Stück';
        }

        if (function_exists('mb_strlen') && mb_strlen($value) > 64) {
            $value = mb_substr($value, 0, 64);
        } elseif (strlen($value) > 64) {
            $value = substr($value, 0, 64);
        }

        return $value;
    }

    public static function normalizeTaxTypeForImport(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace([' ', '%'], '', $value);

        if (isset(self::taxTypes()[$value])) {
            return $value;
        }

        $aliases = [
            'ust19' => 'ust19',
            'ust7' => 'ust7',
            'ust0' => 'ust0',
            'ust0§13b' => 'ust0_13b',
            'ust013b' => 'ust0_13b',
            '§13b' => 'ust0_13b',
            '13b' => 'ust0_13b',
            'photovoltaik' => 'ust0_13b',
            'ust0§19' => 'ust0_19',
            'ust019' => 'ust0_19',
            '§19' => 'ust0_19',
            'ustg19' => 'ust0_19',
            'kleinunternehmer' => 'ust0_19',
            'ust0§4' => 'ust0_4',
            'ust04' => 'ust0_4',
            '§4' => 'ust0_4',
            'ustg4' => 'ust0_4',
            'none' => 'ust0',
            'keine' => 'ust0',
        ];

        if (isset($aliases[$value])) {
            return $aliases[$value];
        }

        if ($value === '') {
            return 'ust19';
        }

        throw new InvalidArgumentException('Unbekannte Steuerart: ' . $value);
    }

    public static function grossFromNet(float $net, string $taxType): float
    {
        $multiplier = match ($taxType) {
            'ust7' => 1.07,
            'ust0', 'ust0_13b', 'ust0_19', 'ust0_4' => 1.0,
            default => 1.19,
        };

        return round($net * $multiplier, 2);
    }

    private static function isValidGs1CheckDigit(string $digits): bool
    {
        $check = (int) substr($digits, -1);
        $body = substr($digits, 0, -1);
        $sum = 0;
        $len = strlen($body);

        for ($i = 0; $i < $len; $i++) {
            $digit = (int) $body[$len - 1 - $i];
            $sum += $digit * (($i % 2 === 0) ? 3 : 1);
        }

        $expected = (10 - ($sum % 10)) % 10;

        return $check === $expected;
    }
}
