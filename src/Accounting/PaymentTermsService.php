<?php
declare(strict_types=1);

/** Skonto-Stufen mit Zeitvorgaben und Fälligkeitsberechnung. */
final class PaymentTermsService
{
    /**
     * @param list<array<string, mixed>>|string|null $raw
     * @return list<array{days: int, adjustment_percent: float, label: string}>
     */
    public static function sanitizeTiers(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $tiers = [];
        foreach ($raw as $tier) {
            if (!is_array($tier)) {
                continue;
            }
            $days = max(1, (int) ($tier['days'] ?? 0));
            $percent = round((float) str_replace(',', '.', (string) ($tier['adjustment_percent'] ?? '0')), 2);
            $label = trim((string) ($tier['label'] ?? ''));
            $tiers[] = [
                'days' => $days,
                'adjustment_percent' => $percent,
                'label' => $label !== '' ? mb_substr($label, 0, 80) : self::defaultLabelForPercent($percent),
            ];
        }

        usort($tiers, static fn (array $a, array $b): int => (int) $a['days'] <=> (int) $b['days']);

        return $tiers;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{days: int, adjustment_percent: float, label: string}>
     */
    public static function parseTiersFromRequest(array $data): array
    {
        $raw = $data['payment_term_tiers'] ?? [];
        if (!is_array($raw)) {
            return AccountingPaymentSettings::defaultTiers();
        }

        $tiers = self::sanitizeTiers($raw);

        return $tiers !== [] ? $tiers : AccountingPaymentSettings::defaultTiers();
    }

    /**
     * @param list<array{days: int, adjustment_percent: float, label: string}> $tiers
     */
    public static function encodeTiers(array $tiers): ?string
    {
        $tiers = self::sanitizeTiers($tiers);
        if ($tiers === []) {
            return null;
        }

        return json_encode($tiers, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    public static function dueDateFromTiers(string $voucherDate, array $tiers): string
    {
        $tiers = self::sanitizeTiers($tiers);
        if ($tiers === []) {
            return $voucherDate;
        }
        $maxDays = max(array_map(static fn (array $t): int => (int) $t['days'], $tiers));
        $base = strtotime($voucherDate);
        if ($base === false) {
            return $voucherDate;
        }

        return date('Y-m-d', strtotime('+' . $maxDays . ' days', $base));
    }

    /**
     * @param list<array{days: int, adjustment_percent: float, label: string}> $tiers
     */
    public static function composeText(array $tiers, string $voucherDate, string $dueDate): string
    {
        $tiers = self::sanitizeTiers($tiers);
        if ($tiers === []) {
            return '';
        }

        $lines = ['Zahlungsbedingungen:'];
        foreach ($tiers as $tier) {
            $days = (int) $tier['days'];
            $percent = (float) $tier['adjustment_percent'];
            if ($percent < 0) {
                $lines[] = sprintf(
                    'Bei Zahlung innerhalb von %d Tagen gewähren wir %s %% Skonto.',
                    $days,
                    number_format(abs($percent), 2, ',', '.')
                );
            } elseif ($percent > 0) {
                $lines[] = sprintf(
                    'Bei Zahlung innerhalb von %d Tagen Verzugszinsen von %s %%.',
                    $days,
                    number_format($percent, 2, ',', '.')
                );
            } else {
                $lines[] = sprintf('Bei Zahlung innerhalb von %d Tagen ohne Abzug.', $days);
            }
        }
        if ($dueDate !== '') {
            $lines[] = 'Fälligkeitsdatum: ' . self::formatDateGerman($dueDate);
        }

        return implode("\n", $lines);
    }

    /**
     * Ermittelt die Stufe für ein Zahlungsdatum (Tage ab Belegdatum).
     *
     * @param list<array{days: int, adjustment_percent: float, label: string}> $tiers
     * @return array{days: int, adjustment_percent: float, label: string}|null
     */
    public static function tierForPaymentDate(string $voucherDate, string $paymentDate, array $tiers): ?array
    {
        $tiers = self::sanitizeTiers($tiers);
        if ($tiers === []) {
            return null;
        }

        $daysBetween = self::daysBetween($voucherDate, $paymentDate);
        $applicable = null;
        foreach ($tiers as $tier) {
            if ($daysBetween <= (int) $tier['days']) {
                $applicable = $tier;
            }
        }

        return $applicable ?? $tiers[count($tiers) - 1];
    }

    /**
     * Wendet Skonto/Zuschlag auf Zahlungsfelder an.
     *
     * @param list<array{days: int, adjustment_percent: float, label: string}> $tiers
     * @return array{discount_percent: float, discount_amount: float, paid_amount: float}
     */
    public static function settlementAmounts(float $gross, string $voucherDate, string $paymentDate, array $tiers): array
    {
        $gross = round($gross, 2);
        $tier = self::tierForPaymentDate($voucherDate, $paymentDate, $tiers);
        if ($tier === null || $gross <= 0) {
            return [
                'discount_percent' => 0.0,
                'discount_amount' => 0.0,
                'paid_amount' => $gross,
            ];
        }

        $percent = (float) $tier['adjustment_percent'];
        if ($percent < 0) {
            $discountAmount = round($gross * abs($percent) / 100, 2);

            return [
                'discount_percent' => abs($percent),
                'discount_amount' => $discountAmount,
                'paid_amount' => round(max(0, $gross - $discountAmount), 2),
            ];
        }
        if ($percent > 0) {
            $surcharge = round($gross * $percent / 100, 2);

            return [
                'discount_percent' => 0.0,
                'discount_amount' => 0.0,
                'paid_amount' => round($gross + $surcharge, 2),
            ];
        }

        return [
            'discount_percent' => 0.0,
            'discount_amount' => 0.0,
            'paid_amount' => $gross,
        ];
    }

    public static function daysOverdue(string $dueDate, ?string $referenceDate = null): int
    {
        $due = strtotime($dueDate);
        if ($due === false) {
            return 0;
        }
        $ref = $referenceDate !== null && $referenceDate !== '' ? strtotime($referenceDate) : time();
        if ($ref === false) {
            $ref = time();
        }
        if ($ref <= $due) {
            return 0;
        }

        return (int) floor(($ref - $due) / 86400);
    }

    public static function daysBetween(string $fromDate, string $toDate): int
    {
        $from = strtotime($fromDate);
        $to = strtotime($toDate);
        if ($from === false || $to === false) {
            return 0;
        }

        return max(0, (int) floor(($to - $from) / 86400));
    }

    private static function defaultLabelForPercent(float $percent): string
    {
        if ($percent < 0) {
            return 'Skonto';
        }
        if ($percent > 0) {
            return 'Verzug';
        }

        return 'Netto';
    }

    private static function formatDateGerman(string $date): string
    {
        $ts = strtotime($date);

        return $ts !== false ? date('d.m.Y', $ts) : $date;
    }
}
