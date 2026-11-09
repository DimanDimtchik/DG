<?php
declare(strict_types=1);

/**
 * FL6a: Offene Wartung / Stör-Flag → Station down (nur Status, kein PLC).
 */
final class ProductionLineStationDownGate
{
    /**
     * @param list<array{station_order?: int, work_center_id?: int}> $stations
     * @param list<int> $manualFaultStationOrders
     * @param list<int> $downWorkCenterIds offene Wartung/Sperre je Work Center
     * @return list<int> station_order-Werte (unique, sortiert)
     */
    public static function resolveDownStationOrders(
        array $stations,
        array $manualFaultStationOrders = [],
        array $downWorkCenterIds = []
    ): array {
        $downWc = [];
        foreach ($downWorkCenterIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $downWc[$id] = true;
            }
        }
        $manual = [];
        foreach ($manualFaultStationOrders as $order) {
            $order = (int) $order;
            if ($order > 0) {
                $manual[$order] = true;
            }
        }

        $out = [];
        foreach ($stations as $st) {
            if (!is_array($st)) {
                continue;
            }
            $order = (int) ($st['station_order'] ?? 0);
            $wcId = (int) ($st['work_center_id'] ?? 0);
            if ($order <= 0) {
                continue;
            }
            if (!empty($manual[$order]) || ($wcId > 0 && !empty($downWc[$wcId]))) {
                $out[$order] = true;
            }
        }
        $orders = array_map('intval', array_keys($out));
        sort($orders);

        return $orders;
    }

    /**
     * Offene Belegungsfenster Wartung/Sperre zum Zeitpunkt (nur lesen).
     *
     * @return list<int> work_center_id
     */
    public static function openDowntimeWorkCenterIds(?string $at = null): array
    {
        if (!class_exists('MachinePlanningRepository') || !class_exists('Database') || !Database::isConfigured()) {
            return [];
        }
        $at = $at !== null && trim($at) !== '' ? trim($at) : date('Y-m-d H:i:s');
        $rows = MachinePlanningRepository::listBookingWindows(null, null, null, true);
        $ids = [];
        $ts = strtotime($at);
        if ($ts === false) {
            return [];
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = (string) ($row['booking_type'] ?? '');
            if ($type !== 'maintenance' && $type !== 'blocked') {
                continue;
            }
            $status = (string) ($row['booking_status'] ?? '');
            if ($status === 'cancelled' || $status === 'done') {
                continue;
            }
            $start = strtotime((string) ($row['starts_at'] ?? ''));
            $end = strtotime((string) ($row['ends_at'] ?? ''));
            if ($start === false || $end === false) {
                continue;
            }
            if ($ts >= $start && $ts < $end) {
                $wc = (int) ($row['work_center_id'] ?? 0);
                if ($wc > 0) {
                    $ids[$wc] = true;
                }
            }
        }

        return array_map('intval', array_keys($ids));
    }

    /**
     * @param list<array<string, mixed>> $stations
     * @param array<string, mixed> $evaluateOptions
     * @param list<int> $manualFaultStationOrders
     * @param list<int>|null $downWorkCenterIds null = aus Belegungen laden
     * @return array{options: array<string, mixed>, gate: array<string, mixed>}
     */
    public static function mergeIntoEvaluateOptions(
        array $stations,
        array $evaluateOptions = [],
        array $manualFaultStationOrders = [],
        ?array $downWorkCenterIds = null
    ): array {
        $wcDown = $downWorkCenterIds ?? self::openDowntimeWorkCenterIds(
            isset($evaluateOptions['at']) ? (string) $evaluateOptions['at'] : null
        );
        $orders = self::resolveDownStationOrders($stations, $manualFaultStationOrders, $wcDown);
        $options = $evaluateOptions;
        unset($options['at']);
        if (!array_key_exists('down_station_orders', $options) && $orders !== []) {
            $options['down_station_orders'] = $orders;
        } elseif (array_key_exists('down_station_orders', $options) && $orders !== []) {
            $existing = is_array($options['down_station_orders']) ? $options['down_station_orders'] : [];
            $options['down_station_orders'] = self::resolveDownStationOrders(
                $stations,
                array_merge(
                    array_is_list($existing) ? $existing : array_keys(array_filter($existing)),
                    $orders
                ),
                []
            );
        }

        return [
            'options' => $options,
            'gate' => [
                'down_station_orders' => $orders,
                'manual_fault_station_orders' => array_values(array_unique(array_map(
                    'intval',
                    $manualFaultStationOrders
                ))),
                'downtime_work_center_ids' => array_values(array_unique(array_map('intval', $wcDown))),
                'hint' => $orders === []
                    ? 'Keine offene Wartung/Störung an den Stationen (Annahme).'
                    : ('Außer Betrieb: Station(en) ' . implode(', ', $orders) . '.'),
            ],
        ];
    }
}
