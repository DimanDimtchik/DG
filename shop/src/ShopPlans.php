<?php

declare(strict_types=1);

final class ShopPlans
{
    /** @var array<string, mixed>|null */
    private static ?array $data = null;

    /** @return array<string, mixed> */
    private static function data(): array
    {
        if (self::$data === null) {
            self::$data = require SHOP_ROOT . '/config/plans.php';
        }

        return self::$data;
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        $out = [];
        foreach (self::data()['plans'] as $id => $plan) {
            $out[] = self::enrich((string) $id, $plan);
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public static function get(string $id): ?array
    {
        $plans = self::data()['plans'];
        if (!isset($plans[$id]) || !is_array($plans[$id])) {
            return null;
        }

        return self::enrich($id, $plans[$id]);
    }

    public static function vatRate(): float
    {
        return (float) (self::data()['vat_rate'] ?? 0.19);
    }

    public static function vatNote(): string
    {
        return (string) (self::data()['vat_note'] ?? '');
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private static function enrich(string $id, array $plan): array
    {
        $monthlyNet = (float) ($plan['monthly_net'] ?? 0);
        $yearlyNet = round($monthlyNet * 11, 2); // 1 Monat gratis
        $vat = self::vatRate();

        return array_merge($plan, [
            'id' => $id,
            'monthly_net' => $monthlyNet,
            'yearly_net' => $yearlyNet,
            'monthly_gross' => round($monthlyNet * (1 + $vat), 2),
            'yearly_gross' => round($yearlyNet * (1 + $vat), 2),
            'yearly_months_paid' => 11,
            'yearly_label' => '1 Monat gratis',
        ]);
    }

    public static function formatMoney(float $amount): string
    {
        return number_format($amount, 0, ',', '.') . ' €';
    }

    public static function formatMoneyExact(float $amount): string
    {
        return number_format($amount, 2, ',', '.') . ' €';
    }

    /** Multi-Firma MF2: Rabatt auf Listenpreis für Zusatzfirma. */
    public static function additionalFirmDiscount(): float
    {
        return 0.20;
    }

    /**
     * @return array{monthly_net: float, yearly_net: float, monthly_gross: float, yearly_gross: float, discount_pct: float}
     */
    public static function priceWithAdditionalFirmDiscount(array $plan): array
    {
        $vat = self::vatRate();
        $disc = self::additionalFirmDiscount();
        $monthly = round((float) ($plan['monthly_net'] ?? 0) * (1.0 - $disc), 2);
        $yearly = round($monthly * 11, 2);

        return [
            'monthly_net' => $monthly,
            'yearly_net' => $yearly,
            'monthly_gross' => round($monthly * (1 + $vat), 2),
            'yearly_gross' => round($yearly * (1 + $vat), 2),
            'discount_pct' => $disc,
        ];
    }
}
