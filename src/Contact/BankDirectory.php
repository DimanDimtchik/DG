<?php
declare(strict_types=1);

/** Deutsche Banken (BLZ-Verzeichnis) – wie WG-App / dg-user-plugin. */
final class BankDirectory
{
    /** @var list<array{bankCode: string, bankName: string, bic: string}>|null */
    private static ?array $banks = null;

    /**
     * banks.
     *
     * @return list<array{bankCode: string, bankName: string, bic: string}>
     */
        public static function banks(): array
    {
        if (self::$banks !== null) {
            return self::$banks;
        }

        $path = DG_ROOT . '/assets/data/german-banks.json';
        if (!is_readable($path)) {
            self::$banks = [];

            return self::$banks;
        }

        $raw = json_decode((string) file_get_contents($path), true);
        self::$banks = is_array($raw) ? $raw : [];

        return self::$banks;
    }

        /**
     * suggestFromIban
     * @param string $iban IBAN
     * @return array{bankCode: string, bankName: string, bic: string}|null
     */
    public static function suggestFromIban(string $iban): ?array
    {
        $iban = self::normalizeIban($iban);
        if (!str_starts_with($iban, 'DE') || strlen($iban) < 12) {
            return null;
        }

        $bankCode = substr($iban, 4, 8);
        foreach (self::banks() as $bank) {
            if (($bank['bankCode'] ?? '') === $bankCode) {
                return $bank;
            }
        }

        return null;
    }

        /**
     * searchByName
     * @param string $input Formulardaten
     * @return list<array{bankCode: string, bankName: string, bic: string}>
     */
    public static function searchByName(string $input): array
    {
        $query = mb_strtolower(trim($input));
        if (mb_strlen($query) < 2) {
            return [];
        }

        $matches = [];
        foreach (self::banks() as $bank) {
            $name = mb_strtolower($bank['bankName'] ?? '');
            if (str_contains($name, $query) || preg_match('/\b' . preg_quote($query, '/') . '/u', $name)) {
                $matches[] = $bank;
            }
            if (count($matches) >= 8) {
                break;
            }
        }

        return $matches;
    }

        /**
     * searchByBic
     * @param string $input Formulardaten
     * @return list<array{bankCode: string, bankName: string, bic: string}>
     */
    public static function searchByBic(string $input): array
    {
        $query = strtoupper(trim($input));
        if (strlen($query) < 3) {
            return [];
        }

        $matches = [];
        foreach (self::banks() as $bank) {
            $bic = strtoupper($bank['bic'] ?? '');
            if ($bic !== '' && str_contains($bic, $query)) {
                $matches[] = $bank;
            }
            if (count($matches) >= 8) {
                break;
            }
        }

        return $matches;
    }

    /**
     * normalizeIban
     * @param string $iban IBAN
     * @return string
     */
    public static function normalizeIban(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
    }

    /**
     * validateIban
     * @param string $iban IBAN
     * @return bool
     */
    public static function validateIban(string $iban): bool
    {
        $iban = self::normalizeIban($iban);
        if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $numeric = '';
        $len = strlen($rearranged);
        for ($i = 0; $i < $len; $i++) {
            $char = $rearranged[$i];
            if ($char >= 'A' && $char <= 'Z') {
                $numeric .= (string) (ord($char) - 55);
            } else {
                $numeric .= $char;
            }
        }

        $remainder = 0;
        $numLen = strlen($numeric);
        for ($i = 0; $i < $numLen; $i++) {
            $remainder = ($remainder * 10 + (int) $numeric[$i]) % 97;
        }

        return $remainder === 1;
    }

        /**
     * suggestResponse
     * @param string $field
     * @param string $value Eingabewert
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    public static function suggestResponse(string $field, string $value): array
    {
        return match ($field) {
            'iban' => [
                'suggestion' => self::suggestFromIban($value),
                'valid' => self::validateIban($value),
            ],
            'bank_name' => ['matches' => self::searchByName($value)],
            'bic' => ['matches' => self::searchByBic($value)],
            default => throw new InvalidArgumentException('Unbekanntes Feld.'),
        };
    }
}
