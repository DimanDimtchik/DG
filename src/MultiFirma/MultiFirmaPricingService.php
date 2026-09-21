<?php
declare(strict_types=1);

/**
 * Multi-Firma MF2 — Preislogik Zweitfirma / Archiv-Slot (KDV + Shop-Spiegel).
 *
 * Festlegungen MF2:
 * - Listenpreise wie Shop: basic 29 / business 49 / enterprise 89 (€ netto/Monat)
 * - 2.+ aktive Firma derselben Org: −20 % auf Listenpreis der Zusatzfirma
 * - 3.+ Firma: ebenfalls −20 % (Staffel später)
 * - Archiv-Slot (Umfirmierung Vorgänger): 0 €, Standarddauer 12 Monate
 */
final class MultiFirmaPricingService
{
    public const ADDITIONAL_FIRM_DISCOUNT = 0.20;
    public const ARCHIVE_MONTHLY_NET = 0.0;
    public const ARCHIVE_DEFAULT_MONTHS = 12;

    /** @var array<string, float> */
    public const LIST_MONTHLY_NET = [
        'basic' => 29.0,
        'business' => 49.0,
        'enterprise' => 89.0,
    ];

    public static function listPriceForTariff(string $tariff): float
    {
        return self::LIST_MONTHLY_NET[$tariff] ?? self::LIST_MONTHLY_NET['basic'];
    }

    /**
     * @param array{
     *   tariff?: string,
     *   org_id?: int,
     *   customer_id?: int,
     *   firm_slot_status?: string,
     *   firm_relation?: string
     * } $ctx
     * @return array{
     *   code: string,
     *   label: string,
     *   list_monthly_net: float,
     *   discount_pct: float,
     *   monthly_net: float,
     *   active_siblings: int,
     *   is_archive: bool
     * }
     */
    public static function quote(array $ctx): array
    {
        $tariff = (string) ($ctx['tariff'] ?? 'basic');
        if (!isset(self::LIST_MONTHLY_NET[$tariff])) {
            $tariff = 'basic';
        }
        $list = self::listPriceForTariff($tariff);
        $slot = (string) ($ctx['firm_slot_status'] ?? 'active');

        if ($slot === 'archive_readonly' || $slot === 'closed') {
            return [
                'code' => 'archive',
                'label' => 'Archiv-Slot (Umfirmierung) — kostenlos',
                'list_monthly_net' => $list,
                'discount_pct' => 1.0,
                'monthly_net' => self::ARCHIVE_MONTHLY_NET,
                'active_siblings' => 0,
                'is_archive' => true,
            ];
        }

        $orgId = (int) ($ctx['org_id'] ?? 0);
        $customerId = (int) ($ctx['customer_id'] ?? 0);
        $activeSiblings = 0;
        if ($orgId > 0 && KdvCustomerRepository::multiFirmaColumnsReady()) {
            foreach (KdvCustomerRepository::listByOrgId($orgId) as $row) {
                if ((int) ($row['id'] ?? 0) === $customerId) {
                    continue;
                }
                if ((string) ($row['firm_slot_status'] ?? 'active') !== 'active') {
                    continue;
                }
                $activeSiblings++;
            }
        }

        if ($activeSiblings >= 1) {
            $net = round($list * (1.0 - self::ADDITIONAL_FIRM_DISCOUNT), 2);

            return [
                'code' => 'additional_firm',
                'label' => 'Zusatzfirma −' . (int) (self::ADDITIONAL_FIRM_DISCOUNT * 100) . ' % (Org hat bereits '
                    . $activeSiblings . ' aktive Firma' . ($activeSiblings === 1 ? '' : 'en') . ')',
                'list_monthly_net' => $list,
                'discount_pct' => self::ADDITIONAL_FIRM_DISCOUNT,
                'monthly_net' => $net,
                'active_siblings' => $activeSiblings,
                'is_archive' => false,
            ];
        }

        return [
            'code' => 'primary',
            'label' => 'Erste aktive Firma — Listenpreis',
            'list_monthly_net' => $list,
            'discount_pct' => 0.0,
            'monthly_net' => $list,
            'active_siblings' => 0,
            'is_archive' => false,
        ];
    }

    /**
     * Vorgänger → Archiv-Slot (gratis, optional Frist).
     *
     * @return array{effective_to: string, monthly_price: float, firm_slot_status: string}
     */
    public static function archiveSlotDefaults(?string $fromDate = null, ?int $months = null): array
    {
        $months = $months ?? self::ARCHIVE_DEFAULT_MONTHS;
        $months = max(1, min(24, $months));
        $base = $fromDate !== null && $fromDate !== ''
            ? DateTimeImmutable::createFromFormat('Y-m-d', $fromDate)
            : new DateTimeImmutable('today');
        if (!$base instanceof DateTimeImmutable) {
            $base = new DateTimeImmutable('today');
        }
        $to = $base->modify('+' . $months . ' months');

        return [
            'firm_slot_status' => 'archive_readonly',
            'monthly_price' => self::ARCHIVE_MONTHLY_NET,
            'effective_to' => $to->format('Y-m-d'),
        ];
    }
}
