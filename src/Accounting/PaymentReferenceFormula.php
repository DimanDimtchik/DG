<?php
declare(strict_types=1);

/**
 * Konfigurierbare Formel für den Verwendungszweck einer Überweisung.
 *
 * Die Formel wird im Nummernkreis-Bereich gepflegt und beim Vorbereiten einer
 * Überweisung mit den Beleg-, Kontakt- und Firmendaten aufgelöst.
 *
 * Platzhalter in eckigen Klammern [ … ] werden komplett entfernt, wenn ein darin
 * enthaltener Platzhalter leer ist (z. B. „[ · KdNr {KUNDENNR}]“).
 */
final class PaymentReferenceFormula
{
    public const STORE_KEY = 'payment_reference';

    public static function defaultFormula(): string
    {
        return 'RE {RENR} vom {REDATUM}[ / KdNr {KUNDENNR}] / {FIRMA}';
    }

    public static function formula(): string
    {
        $cfg = SettingsStore::get(self::STORE_KEY, ['formula' => self::defaultFormula()]);
        $formula = trim((string) ($cfg['formula'] ?? ''));

        return $formula !== '' ? $formula : self::defaultFormula();
    }

    public static function save(string $formula): void
    {
        $formula = trim($formula);
        if ($formula === '') {
            $formula = self::defaultFormula();
        }

        SettingsStore::set(self::STORE_KEY, ['formula' => $formula]);
    }

    /**
     * Verfügbare Platzhalter für die UI.
     *
     * @return array<string, string>
     */
    public static function tokens(): array
    {
        return [
            '{RENR}' => 'Rechnungsnummer des Belegs',
            '{REDATUM}' => 'Rechnungsdatum (TT.MM.JJJJ)',
            '{KUNDENNR}' => 'Kundennummer beim Lieferanten (falls vorhanden)',
            '{FIRMA}' => 'Eigener Firmenname',
            '{LIEFERANT}' => 'Name des Lieferanten',
            '{BETRAG}' => 'Betrag (z. B. 123,45)',
        ];
    }

    /**
     * Löst die Formel mit den übergebenen Werten auf.
     *
     * @param array{invoice_number?: string, invoice_date?: string, customer_number?: string, company_name?: string, supplier_name?: string, amount?: float|int|string} $context
     */
    public static function resolve(string $formula, array $context): string
    {
        $values = self::values($context);

        // Optionale Segmente [ … ] entfernen, wenn ein enthaltener Platzhalter leer ist.
        $formula = preg_replace_callback('/\[([^\[\]]*)\]/u', static function (array $m) use ($values): string {
            $segment = $m[1];
            if (preg_match_all('/\{[A-ZÄÖÜ]+\}/u', $segment, $found) > 0) {
                foreach ($found[0] as $token) {
                    if (($values[$token] ?? '') === '') {
                        return '';
                    }
                }
            }

            return $segment;
        }, $formula) ?? $formula;

        $result = strtr($formula, $values);

        return self::sanitizeSepa($result);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, string>
     */
    private static function values(array $context): array
    {
        $date = trim((string) ($context['invoice_date'] ?? ''));
        $dateFormatted = '';
        if ($date !== '') {
            $ts = strtotime($date);
            $dateFormatted = $ts !== false ? date('d.m.Y', $ts) : $date;
        }

        $amount = (float) ($context['amount'] ?? 0);

        return [
            '{RENR}' => trim((string) ($context['invoice_number'] ?? '')),
            '{REDATUM}' => $dateFormatted,
            '{KUNDENNR}' => trim((string) ($context['customer_number'] ?? '')),
            '{FIRMA}' => trim((string) ($context['company_name'] ?? '')),
            '{LIEFERANT}' => trim((string) ($context['supplier_name'] ?? '')),
            '{BETRAG}' => $amount > 0 ? number_format($amount, 2, ',', '.') : '',
        ];
    }

    /**
     * Reduziert den Text auf für SEPA-Überweisungen zulässige Zeichen.
     * Umlaute werden transliteriert, unzulässige Zeichen entfernt.
     */
    public static function sanitizeSepa(string $value): string
    {
        $map = [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'á' => 'a', 'à' => 'a', 'â' => 'a',
            '·' => '/', '–' => '-', '—' => '-', '&' => '+',
        ];
        $value = strtr($value, $map);
        $value = preg_replace('/[^A-Za-z0-9 \/\-?:().,\'+]/', ' ', $value) ?? $value;
        $value = preg_replace('/\s{2,}/', ' ', $value) ?? $value;

        return trim($value);
    }
}
