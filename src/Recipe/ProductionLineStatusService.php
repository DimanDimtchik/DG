<?php
declare(strict_types=1);

/**
 * FL3b: Offline-Status Blocking/Starving laut docs/FLIESSLINIE-REGELN.md — kein PLC.
 */
final class ProductionLineStatusService
{
    /**
     * @param array{
     *   down_station_orders?: list<int>|array<int, bool>,
     *   raw_material_ok?: bool
     * } $options
     * @return array{
     *   line_id: int,
     *   line_status: string,
     *   stations: list<array<string, mixed>>,
     *   buffers: list<array<string, mixed>>
     * }|null
     */
    public static function evaluate(int $lineId, array $options = []): ?array
    {
        if ($lineId <= 0 || !class_exists('ProductionLineStore')) {
            return null;
        }
        $bundle = ProductionLineStore::getLineWithStations($lineId);
        if ($bundle === null) {
            return null;
        }
        $buffers = ProductionLineStore::listBuffers($lineId);

        return self::evaluateFromState(
            $lineId,
            $bundle['stations'],
            $buffers,
            $options
        );
    }

    /**
     * Reine Regelauswertung (Selftest / ohne DB-Reload).
     *
     * @param list<array<string, mixed>> $stations
     * @param list<array<string, mixed>> $buffers listBuffers-Form oder Rohzeilen mit after_station_order
     * @param array{
     *   down_station_orders?: list<int>|array<int, bool>,
     *   raw_material_ok?: bool
     * } $options
     * @return array{
     *   line_id: int,
     *   line_status: string,
     *   stations: list<array<string, mixed>>,
     *   buffers: list<array<string, mixed>>
     * }
     */
    public static function evaluateFromState(
        int $lineId,
        array $stations,
        array $buffers,
        array $options = []
    ): array {
        usort(
            $stations,
            static fn(array $a, array $b): int => ((int) ($a['station_order'] ?? 0)) <=> ((int) ($b['station_order'] ?? 0))
        );
        $n = count($stations);
        $down = self::normalizeDownMap($options['down_station_orders'] ?? []);
        $rawOk = array_key_exists('raw_material_ok', $options)
            ? (bool) $options['raw_material_ok']
            : true;

        $bufByAfterOrder = [];
        foreach ($buffers as $b) {
            $order = (int) ($b['after_station_order'] ?? 0);
            if ($order <= 0 && isset($b['after_station_id'])) {
                // Fallback: Order aus Stationen ableiten
                foreach ($stations as $st) {
                    if ((int) ($st['id'] ?? 0) === (int) $b['after_station_id']) {
                        $order = (int) ($st['station_order'] ?? 0);
                        break;
                    }
                }
            }
            if ($order > 0) {
                $bufByAfterOrder[$order] = $b;
            }
        }

        $bufferOut = [];
        foreach ($bufByAfterOrder as $order => $b) {
            $cap = (float) ($b['capacity_qty'] ?? 0);
            $min = (float) ($b['min_qty'] ?? 0);
            $cur = (float) ($b['current_qty'] ?? 0);
            $bufferOut[] = [
                'after_station_order' => $order,
                'current_qty' => $cur,
                'capacity_qty' => $cap,
                'min_qty' => $min,
                'unit' => (string) ($b['unit'] ?? ''),
                'blocks_predecessor' => $cur >= $cap && $cap > 0,
                'starves_successor' => $cur <= $min,
            ];
        }
        usort(
            $bufferOut,
            static fn(array $a, array $b): int => $a['after_station_order'] <=> $b['after_station_order']
        );

        $stationOut = [];
        $statusList = [];
        for ($i = 0; $i < $n; $i++) {
            $st = $stations[$i];
            $order = (int) ($st['station_order'] ?? ($i + 1));
            $reasons = [];
            $status = 'running';

            if (!empty($down[$order])) {
                $status = 'down';
                $reasons[] = 'station_down';
            } else {
                if ($order < $n) {
                    $bufOut = $bufByAfterOrder[$order] ?? null;
                    if ($bufOut !== null) {
                        $cap = (float) ($bufOut['capacity_qty'] ?? 0);
                        $cur = (float) ($bufOut['current_qty'] ?? 0);
                        if ($cap > 0 && $cur >= $cap) {
                            $status = 'blocked';
                            $reasons[] = 'buffer_full';
                        }
                    }
                }
                if ($status === 'running' && $order > 1) {
                    $bufIn = $bufByAfterOrder[$order - 1] ?? null;
                    if ($bufIn !== null) {
                        $min = (float) ($bufIn['min_qty'] ?? 0);
                        $cur = (float) ($bufIn['current_qty'] ?? 0);
                        if ($cur <= $min) {
                            $status = 'starved';
                            $reasons[] = 'buffer_empty';
                        }
                    }
                }
            }

            $statusList[] = $status;
            $stationOut[] = [
                'station_id' => (int) ($st['id'] ?? 0),
                'station_order' => $order,
                'work_center_id' => (int) ($st['work_center_id'] ?? 0),
                'label' => (string) ($st['label'] ?? ''),
                'status' => $status,
                'reasons' => $reasons,
            ];
        }

        $lineStatus = self::lineStatus($statusList, $rawOk);

        return [
            'line_id' => $lineId,
            'line_status' => $lineStatus,
            'stations' => $stationOut,
            'buffers' => $bufferOut,
        ];
    }

    /**
     * @param list<string> $stationStatuses
     */
    private static function lineStatus(array $stationStatuses, bool $rawMaterialOk): string
    {
        if (!$rawMaterialOk) {
            return 'stopped_material';
        }
        if ($stationStatuses === []) {
            return 'stopped';
        }
        $nonRunning = true;
        $anyDown = false;
        $anyConstraint = false;
        foreach ($stationStatuses as $s) {
            if ($s === 'running') {
                $nonRunning = false;
            }
            if ($s === 'down') {
                $anyDown = true;
            }
            if ($s === 'blocked' || $s === 'starved') {
                $anyConstraint = true;
            }
        }
        if ($nonRunning) {
            return 'stopped';
        }
        if ($anyDown) {
            return 'degraded';
        }
        if ($anyConstraint) {
            return 'constrained';
        }

        return 'running';
    }

    /**
     * @param list<int>|array<int, bool> $raw
     * @return array<int, bool>
     */
    private static function normalizeDownMap(array $raw): array
    {
        $out = [];
        $isList = array_is_list($raw);
        foreach ($raw as $k => $v) {
            if ($isList) {
                $order = (int) $v;
                if ($order > 0) {
                    $out[$order] = true;
                }
            } else {
                if ($v) {
                    $out[(int) $k] = true;
                }
            }
        }

        return $out;
    }
}
