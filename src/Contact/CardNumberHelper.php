<?php
declare(strict_types=1);

/**
 * Kreditkarten-Hilfen: Schema/BIN ableiten, maskieren — keine Voll-PAN speichern.
 */
final class CardNumberHelper
{
    /**
     * Nur Ziffern.
     */
    public static function digitsOnly(string $value): string
    {
        return (string) preg_replace('/\D+/', '', $value);
    }

    /**
     * Luhn-Prüfung (gültige Kartennummer-Struktur).
     */
    public static function isValidLuhn(string $digits): bool
    {
        $digits = self::digitsOnly($digits);
        $len = strlen($digits);
        if ($len < 12 || $len > 19) {
            return false;
        }

        $sum = 0;
        $alt = false;
        for ($i = $len - 1; $i >= 0; $i--) {
            $n = (int) $digits[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = !$alt;
        }

        return $sum % 10 === 0;
    }

    /**
     * Erkennt, ob der Wert wie eine Voll-PAN aussieht (zum Ablehnen beim Speichern).
     */
    public static function looksLikeFullPan(string $value): bool
    {
        $digits = self::digitsOnly($value);
        $len = strlen($digits);
        if ($len < 12 || $len > 19) {
            return false;
        }
        // Bereits maskiert (enthält *) → keine Voll-PAN
        if (str_contains($value, '*') || str_contains($value, '•') || str_contains($value, 'x') || str_contains($value, 'X')) {
            return false;
        }

        return self::isValidLuhn($digits) || ($len >= 15 && $len <= 16);
    }

    /**
     * Maskierte Darstellung + Metadaten aus einer (temporären) Eingabe.
     *
     * @return array{
     *   card_number_masked: string,
     *   card_last4: string,
     *   card_bin: string,
     *   card_brand: string,
     *   card_brand_label: string,
     *   provider: string,
     *   valid_luhn: bool
     * }
     */
    public static function analyze(string $raw): array
    {
        $digits = self::digitsOnly($raw);
        $brand = self::detectBrand($digits);
        $bin = strlen($digits) >= 6 ? substr($digits, 0, 8 <= strlen($digits) ? 8 : 6) : '';
        if (strlen($bin) > 8) {
            $bin = substr($bin, 0, 8);
        }
        $last4 = strlen($digits) >= 4 ? substr($digits, -4) : '';
        $masked = $last4 !== '' ? self::maskFromDigits($digits) : '';

        return [
            'card_number_masked' => $masked,
            'card_last4' => $last4,
            'card_bin' => $bin,
            'card_brand' => $brand['code'],
            'card_brand_label' => $brand['label'],
            'provider' => $brand['label'],
            'valid_luhn' => self::isValidLuhn($digits),
        ];
    }

    public static function maskFromDigits(string $digits): string
    {
        $digits = self::digitsOnly($digits);
        if ($digits === '') {
            return '';
        }
        $last4 = substr($digits, -4);
        $len = strlen($digits);
        if ($len <= 4) {
            return $digits;
        }
        // Gruppen à 4, letzte Gruppe sichtbar
        $maskedDigits = str_repeat('*', max(0, $len - 4)) . $last4;
        $parts = str_split($maskedDigits, 4);

        return implode(' ', $parts);
    }

    /**
     * Wenn jemand versehentlich eine Voll-PAN speichert: auf Maske reduzieren.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function sanitizeStoredCardFields(array $row): array
    {
        $masked = trim((string) ($row['card_number_masked'] ?? ''));
        $entry = trim((string) ($row['card_number_entry'] ?? ''));

        if ($entry !== '' && self::looksLikeFullPan($entry)) {
            $info = self::analyze($entry);
            $masked = $info['card_number_masked'];
            if (trim((string) ($row['card_brand'] ?? '')) === '') {
                $row['card_brand'] = $info['card_brand'];
            }
            if (trim((string) ($row['provider'] ?? '')) === '') {
                $row['provider'] = $info['provider'];
            }
            if (trim((string) ($row['card_last4'] ?? '')) === '') {
                $row['card_last4'] = $info['card_last4'];
            }
            if (trim((string) ($row['card_bin'] ?? '')) === '') {
                $row['card_bin'] = $info['card_bin'];
            }
        } elseif (self::looksLikeFullPan($masked)) {
            $info = self::analyze($masked);
            $masked = $info['card_number_masked'];
            if (trim((string) ($row['card_last4'] ?? '')) === '') {
                $row['card_last4'] = $info['card_last4'];
            }
            if (trim((string) ($row['card_bin'] ?? '')) === '') {
                $row['card_bin'] = $info['card_bin'];
            }
            if (trim((string) ($row['card_brand'] ?? '')) === '') {
                $row['card_brand'] = $info['card_brand'];
            }
            if (trim((string) ($row['provider'] ?? '')) === '') {
                $row['provider'] = $info['provider'];
            }
        }

        $row['card_number_masked'] = $masked;
        unset($row['card_number_entry']); // nie speichern

        // BIN allein (6–8 Ziffern) darf bleiben — keine Voll-PAN
        $bin = self::digitsOnly((string) ($row['card_bin'] ?? ''));
        if (strlen($bin) > 8) {
            $bin = substr($bin, 0, 8);
        }
        $row['card_bin'] = $bin;

        $last4 = self::digitsOnly((string) ($row['card_last4'] ?? ''));
        if (strlen($last4) > 4) {
            $last4 = substr($last4, -4);
        }
        if ($last4 === '' && $masked !== '') {
            $md = self::digitsOnly(str_replace('*', '', $masked));
            // nur wenn Maske Ziffern am Ende hat
            if (preg_match('/(\d{4})\s*$/', $masked, $m)) {
                $last4 = $m[1];
            }
        }
        $row['card_last4'] = $last4;

        return $row;
    }

    /**
     * @return array{code: string, label: string}
     */
    public static function detectBrand(string $digits): array
    {
        $digits = self::digitsOnly($digits);
        if ($digits === '') {
            return ['code' => '', 'label' => ''];
        }

        $first = (int) substr($digits, 0, 1);
        $two = (int) substr($digits, 0, 2);
        $four = (int) substr($digits, 0, 4);
        $six = (int) substr($digits, 0, 6);

        if ($first === 4) {
            return ['code' => 'visa', 'label' => 'Visa'];
        }
        if (($two >= 51 && $two <= 55) || ($four >= 2221 && $four <= 2720)) {
            return ['code' => 'mastercard', 'label' => 'Mastercard'];
        }
        if ($two === 34 || $two === 37) {
            return ['code' => 'amex', 'label' => 'American Express'];
        }
        if ($four === 6011 || ($six >= 622126 && $six <= 622925) || ($two >= 64 && $two <= 65)) {
            return ['code' => 'discover', 'label' => 'Discover'];
        }
        if ($four >= 3528 && $four <= 3589) {
            return ['code' => 'jcb', 'label' => 'JCB'];
        }
        if ($two === 36 || ($two >= 38 && $two <= 39) || ($two >= 30 && $two <= 35 && in_array($two, [30, 36, 38], true))) {
            // Diners: 36, 38–39, 300–305 …
            if ($two === 36 || $two === 38 || $two === 39 || ($six >= 300000 && $six <= 305999)) {
                return ['code' => 'diners', 'label' => 'Diners Club'];
            }
        }
        if ($four === 5019 || $four === 4571) {
            return ['code' => 'dankort', 'label' => 'Dankort'];
        }
        // UnionPay
        if ($two === 62) {
            return ['code' => 'unionpay', 'label' => 'UnionPay'];
        }

        return ['code' => 'unknown', 'label' => 'Unbekanntes Kartennetz'];
    }

    /**
     * Grobe Issuer-Hinweise aus bekannten DE-BIN-Präfixen (ohne Vollverzeichnis).
     */
    public static function suggestIssuer(string $binOrPan): string
    {
        $digits = self::digitsOnly($binOrPan);
        if (strlen($digits) < 6) {
            return '';
        }
        $prefix6 = substr($digits, 0, 6);
        $prefix8 = substr($digits, 0, min(8, strlen($digits)));

        // Häufige deutsche Issuer-Präfixe (Auswahl, erweiterbar)
        $map = [
            '454618' => 'Deutsche Bank',
            '454613' => 'Deutsche Bank',
            '490762' => 'Commerzbank',
            '540699' => 'Commerzbank',
            '557361' => 'N26',
            '535522' => 'N26',
            '535456' => 'N26',
            '518834' => 'Consorsbank',
            '552213' => 'DKB',
            '519773' => 'DKB',
            '545708' => 'ING',
            '492910' => 'Barclays',
            '454312' => 'American Express',
            '375987' => 'American Express',
            '540410' => 'Sparkasse / Landesbank (Visa/MC)',
            '545230' => 'Sparkasse',
            '547651' => 'VR-Bank / genossenschaftlich',
        ];

        if (isset($map[$prefix6])) {
            return $map[$prefix6];
        }
        if (isset($map[$prefix8])) {
            return $map[$prefix8];
        }

        return '';
    }
}
