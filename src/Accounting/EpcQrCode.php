<?php
declare(strict_types=1);

/**
 * Erzeugt den EPC-QR-Code-Text (GiroCode) für eine SEPA-Überweisung.
 *
 * Der resultierende Text wird clientseitig als QR-Code gerendert. Banking-Apps
 * lesen daraus Empfänger, IBAN, Betrag und Verwendungszweck aus.
 *
 * @see https://www.europeanpaymentscouncil.eu (EPC069-12, Version 002)
 */
final class EpcQrCode
{
        /**
     * Erzeugt den EPC-QR-Code-Text (GiroCode)
     * @param array $transfer Überweisungsdaten
     * @return string
     */
    public static function payload(array $transfer): string
    {
        $name = self::truncate(self::clean((string) ($transfer['recipient_name'] ?? '')), 70);
        $iban = strtoupper(preg_replace('/\s+/', '', (string) ($transfer['recipient_iban'] ?? '')) ?? '');
        $bic = strtoupper(preg_replace('/\s+/', '', (string) ($transfer['recipient_bic'] ?? '')) ?? '');
        $currency = strtoupper(trim((string) ($transfer['currency'] ?? 'EUR'))) ?: 'EUR';
        $amount = (float) ($transfer['amount'] ?? 0);
        $purpose = self::truncate(self::clean((string) ($transfer['purpose'] ?? '')), 140);

        $amountLine = '';
        if ($amount > 0) {
            $amountLine = $currency . number_format($amount, 2, '.', '');
        }

        $lines = [
            'BCD',
            '002',
            '1',
            'SCT',
            $bic,
            $name,
            $iban,
            $amountLine,
            '',        // Purpose code (4)
            '',        // Structured remittance (35)
            $purpose,  // Unstructured remittance (140)
        ];

        return rtrim(implode("\n", $lines), "\n");
    }

        /**
     * Prüft, ob genügend Daten für einen gültigen GiroCode vorhanden sind.
     * @param array $transfer Überweisungsdaten
     * @return bool
     */
    public static function isPayable(array $transfer): bool
    {
        $name = self::clean((string) ($transfer['recipient_name'] ?? ''));
        $iban = preg_replace('/\s+/', '', (string) ($transfer['recipient_iban'] ?? '')) ?? '';

        return $name !== '' && self::isValidIban($iban);
    }

    /**
     * Prüft eine IBAN per Modulo-97
     * @param string $iban IBAN
     * @return bool
     */
    public static function isValidIban(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        // Modulo 97 über die (potenziell lange) Ziffernfolge stückweise berechnen.
        $remainder = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) (($remainder . $chunk) % 97);
        }

        return $remainder === 1;
    }

    /**
     * clean
     * @param string $value Eingabewert
     * @return string
     */
    private static function clean(string $value): string
    {
        $value = preg_replace('/[\r\n\t]+/', ' ', $value) ?? '';

        return trim((string) preg_replace('/\s{2,}/', ' ', $value));
    }

    /**
     * truncate
     * @param string $value Eingabewert
     * @param int $max Maximale Länge
     * @return string
     */
    private static function truncate(string $value, int $max): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }

        return substr($value, 0, $max);
    }
}
