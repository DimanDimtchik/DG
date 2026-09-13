<?php
declare(strict_types=1);

/**
 * Geldbeträge für Kichel — ausschließlich PHP, nie Ollama.
 */
final class KichelMoneyLogic
{
    /**
     * @return array{
     *   kind: string,
     *   answer: string,
     *   facts: array<string, string|float|int>
     * }|null
     */
    public static function tryAnswer(string $query): ?array
    {
        $skonto = self::trySkontoCalculation($query);
        if ($skonto !== null) {
            return $skonto;
        }

        return null;
    }

    /**
     * @return array{kind: string, answer: string, facts: array<string, string|float|int>}|null
     */
    private static function trySkontoCalculation(string $query): ?array
    {
        $normalized = mb_strtolower($query, 'UTF-8');
        if (!str_contains($normalized, 'skonto') && !preg_match('/\d+\s*(?:%|prozent)/u', $normalized)) {
            return null;
        }

        if (!preg_match('/(\d+(?:[.,]\d+)?)\s*(?:€|euro)/u', $normalized, $amountMatch)) {
            return null;
        }

        $net = self::parseAmount($amountMatch[1]);
        if ($net <= 0) {
            return null;
        }

        $percent = null;
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:%|prozent)/u', $normalized, $pctMatch)) {
            $percent = self::parseAmount($pctMatch[1]);
        }
        if ($percent === null || $percent <= 0) {
            return null;
        }

        $days = 0;
        if (preg_match('/(\d+)\s*tag/u', $normalized, $dayMatch)) {
            $days = (int) $dayMatch[1];
        }

        $calc = self::skontoPayment($net, $percent);
        $answer = sprintf(
            "Skonto ist ein Preisnachlass bei früher Zahlung (Abzug vom Rechnungsbetrag).\n\n"
            . "Rechnung netto: %s €\n"
            . "Skonto %s %%: − %s €\n"
            . "Zahlbetrag mit Skonto: %s €",
            self::formatMoney($net),
            self::formatMoney($percent),
            self::formatMoney($calc['discount']),
            self::formatMoney($calc['payable'])
        );
        if ($days > 0) {
            $answer .= sprintf("\n\nZahlungsziel für Skonto: innerhalb von %d Tagen.", $days);
        }

        return [
            'kind' => 'skonto_calculation',
            'answer' => $answer,
            'facts' => [
                'net_amount' => $calc['net'],
                'skonto_percent' => $calc['percent'],
                'discount_amount' => $calc['discount'],
                'payable_amount' => $calc['payable'],
                'payment_days' => $days,
                'rule' => 'Skonto wird vom Rechnungsbetrag abgezogen (Preisnachlass).',
            ],
        ];
    }

    /**
     * @return array{net: float, percent: float, discount: float, payable: float}
     */
    public static function skontoPayment(float $net, float $percent): array
    {
        $net = round(max(0.0, $net), 2);
        $percent = round(max(0.0, $percent), 4);
        $discount = round($net * ($percent / 100), 2);
        $payable = round(max(0.0, $net - $discount), 2);

        return [
            'net' => $net,
            'percent' => $percent,
            'discount' => $discount,
            'payable' => $payable,
        ];
    }

    private static function parseAmount(string $raw): float
    {
        $raw = str_replace(',', '.', trim($raw));

        return (float) $raw;
    }

    private static function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', '.');
    }
}
