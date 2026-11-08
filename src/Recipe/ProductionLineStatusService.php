<?php
declare(strict_types=1);

/**
 * FL3–FL6: Offline-Status, Engpass, Material, down-Propagation — kein PLC.
 */
final class ProductionLineStatusService
{
    /**
     * @param array{
     *   down_station_orders?: list<int>|array<int, bool>,
     *   raw_material_ok?: bool,
     *   missing_aux_from_station_order?: int|null,
     *   recipe_id?: int|null,
     *   throughputs?: list<array{station_order: int, output_qty_per_hour: float|string, unit?: string, label?: string}>
     * } $options
     * @return array<string, mixed>|null
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
        if (!array_key_exists('recipe_id', $options)) {
            $rid = $bundle['line']['recipe_id'] ?? null;
            $options['recipe_id'] = $rid !== null && $rid !== '' ? (int) $rid : null;
        }
        if (!isset($options['throughputs'])) {
            $options['throughputs'] = self::loadThroughputsFromProfiles(
                $bundle['stations'],
                isset($options['recipe_id']) ? (int) $options['recipe_id'] : null
            );
        }

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
     *   raw_material_ok?: bool,
     *   missing_aux_from_station_order?: int|null,
     *   throughputs?: list<array{station_order: int, output_qty_per_hour: float|string, unit?: string, label?: string}>
     * } $options
     * @return array{
     *   line_id: int,
     *   line_status: string,
     *   stations: list<array<string, mixed>>,
     *   buffers: list<array<string, mixed>>,
     *   bottleneck: array<string, mixed>|null,
     *   line_throughput: array<string, mixed>|null,
     *   raw_material: array<string, mixed>,
     *   auxiliary_material: array<string, mixed>
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
        $auxFrom = isset($options['missing_aux_from_station_order'])
            ? (int) $options['missing_aux_from_station_order']
            : 0;
        if ($auxFrom < 0) {
            $auxFrom = 0;
        }

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
                // FL5a: fehlende Rohware am Eingang → Station 1 wie starved
                if ($status === 'running' && !$rawOk && $order === 1) {
                    $status = 'starved';
                    $reasons[] = 'raw_material_missing';
                }
                // FL5b: fehlender Hilfsstoff ab Station k → ab k starved
                if ($status === 'running' && $auxFrom > 0 && $order >= $auxFrom) {
                    $status = 'starved';
                    $reasons[] = 'aux_material_missing';
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

        $propagation = self::applyDownPropagation($stationOut, $bufferOut);
        $statusList = [];
        foreach ($stationOut as $s) {
            $statusList[] = (string) ($s['status'] ?? 'running');
        }

        $lineStatus = self::lineStatus($statusList, $rawOk);
        $throughputs = is_array($options['throughputs'] ?? null) ? $options['throughputs'] : [];
        $bottleneck = self::resolveBottleneck($stationOut, $throughputs);
        $lineThroughput = self::resolveLineThroughput($lineStatus, $bottleneck, $bufferOut);
        $rawMaterial = [
            'ok' => $rawOk,
            'hint' => $rawOk
                ? 'Rohware am Eingang ausreichend (Annahme).'
                : 'Rohware am Linieneingang fehlt — Linie gestoppt, erste Station steht.',
        ];
        $auxOk = $auxFrom <= 0;
        $auxiliaryMaterial = [
            'ok' => $auxOk,
            'from_station_order' => $auxOk ? null : $auxFrom,
            'hint' => $auxOk
                ? 'Hilfsstoffe an den Stationen ausreichend (Annahme).'
                : ('Hilfsstoff fehlt ab Station ' . $auxFrom
                    . ' — diese und folgende Stationen stehen.'),
        ];

        return [
            'line_id' => $lineId,
            'line_status' => $lineStatus,
            'stations' => $stationOut,
            'buffers' => $bufferOut,
            'bottleneck' => $bottleneck,
            'line_throughput' => $lineThroughput,
            'raw_material' => $rawMaterial,
            'auxiliary_material' => $auxiliaryMaterial,
            'propagation' => $propagation,
        ];
    }

    /**
     * FL6b: down bei i → Blocking-Risiko davor, Starving danach (Status/Flags, kein Mengen-Schreiben).
     *
     * @param list<array<string, mixed>> $stationOut
     * @param list<array<string, mixed>> $bufferOut
     * @return array{events: list<array<string, mixed>>, hint: string}
     */
    public static function applyDownPropagation(array &$stationOut, array &$bufferOut): array
    {
        $byOrder = [];
        foreach ($stationOut as $idx => $st) {
            $byOrder[(int) ($st['station_order'] ?? 0)] = $idx;
        }
        $bufIdx = [];
        foreach ($bufferOut as $idx => $b) {
            $bufIdx[(int) ($b['after_station_order'] ?? 0)] = $idx;
        }

        $events = [];
        $downOrders = [];
        foreach ($stationOut as $st) {
            if (($st['status'] ?? '') === 'down') {
                $downOrders[] = (int) ($st['station_order'] ?? 0);
            }
        }

        foreach ($downOrders as $i) {
            if ($i <= 0) {
                continue;
            }
            // Puffer vor down-Station (nach i-1): Füll-Risiko → Vorgänger Blocking-Risiko
            if ($i > 1 && isset($byOrder[$i - 1])) {
                $pIdx = $byOrder[$i - 1];
                if (($stationOut[$pIdx]['status'] ?? '') === 'running') {
                    $reasons = is_array($stationOut[$pIdx]['reasons'] ?? null)
                        ? $stationOut[$pIdx]['reasons']
                        : [];
                    if (!in_array('downstream_down_block_risk', $reasons, true)) {
                        $reasons[] = 'downstream_down_block_risk';
                    }
                    $stationOut[$pIdx]['reasons'] = $reasons;
                    $stationOut[$pIdx]['block_risk'] = true;
                    $events[] = [
                        'type' => 'block_risk',
                        'from_down_order' => $i,
                        'station_order' => $i - 1,
                    ];
                }
                if (isset($bufIdx[$i - 1])) {
                    $bufferOut[$bufIdx[$i - 1]]['fill_risk_due_to_downstream_down'] = true;
                }
            }
            // Nachfolger: Starving wegen upstream down
            if (isset($byOrder[$i + 1])) {
                $sIdx = $byOrder[$i + 1];
                $succStatus = (string) ($stationOut[$sIdx]['status'] ?? 'running');
                if ($succStatus === 'running' || $succStatus === 'blocked') {
                    $stationOut[$sIdx]['status'] = 'starved';
                    $reasons = is_array($stationOut[$sIdx]['reasons'] ?? null)
                        ? $stationOut[$sIdx]['reasons']
                        : [];
                    if (!in_array('upstream_down', $reasons, true)) {
                        $reasons[] = 'upstream_down';
                    }
                    $stationOut[$sIdx]['reasons'] = $reasons;
                    $events[] = [
                        'type' => 'starve_successor',
                        'from_down_order' => $i,
                        'station_order' => $i + 1,
                    ];
                }
                if (isset($bufIdx[$i])) {
                    $bufferOut[$bufIdx[$i]]['starve_risk_due_to_upstream_down'] = true;
                }
            }
        }

        $hint = $events === []
            ? 'Keine down-Propagation.'
            : 'Störung wirkt auf Nachbarn: Puffer davor mit Füll-Risiko, Folgestation kann stehen (kein Zulauf).';

        return [
            'events' => $events,
            'hint' => $hint,
        ];
    }

    /**
     * Engpass = Station mit geringstem positivem Output/h (Kapazitätsprofil).
     *
     * @param list<array<string, mixed>> $stationOut
     * @param list<array{station_order?: int, output_qty_per_hour?: float|string, unit?: string, label?: string}> $throughputs
     * @return array{
     *   station_order: int,
     *   station_id: int,
     *   work_center_id: int,
     *   output_qty_per_hour: float,
     *   unit: string,
     *   hint: string
     * }|null
     */
    public static function resolveBottleneck(array $stationOut, array $throughputs): ?array
    {
        $byOrder = [];
        foreach ($throughputs as $row) {
            if (!is_array($row)) {
                continue;
            }
            $order = (int) ($row['station_order'] ?? 0);
            $qty = (float) str_replace(',', '.', (string) ($row['output_qty_per_hour'] ?? '0'));
            if ($order <= 0 || $qty <= 0) {
                continue;
            }
            $byOrder[$order] = [
                'output_qty_per_hour' => $qty,
                'unit' => trim((string) ($row['unit'] ?? '')),
                'label' => trim((string) ($row['label'] ?? '')),
            ];
        }
        if ($byOrder === []) {
            return null;
        }

        $bestOrder = null;
        $bestQty = null;
        foreach ($byOrder as $order => $t) {
            if ($bestQty === null || $t['output_qty_per_hour'] < $bestQty) {
                $bestQty = $t['output_qty_per_hour'];
                $bestOrder = $order;
            }
        }
        if ($bestOrder === null || $bestQty === null) {
            return null;
        }

        $stationId = 0;
        $wcId = 0;
        $stationLabel = '';
        foreach ($stationOut as $st) {
            if ((int) ($st['station_order'] ?? 0) === $bestOrder) {
                $stationId = (int) ($st['station_id'] ?? 0);
                $wcId = (int) ($st['work_center_id'] ?? 0);
                $stationLabel = (string) ($st['label'] ?? '');
                break;
            }
        }
        $name = $byOrder[$bestOrder]['label'] !== ''
            ? $byOrder[$bestOrder]['label']
            : ($stationLabel !== '' ? $stationLabel : ('Station ' . $bestOrder));
        $unit = $byOrder[$bestOrder]['unit'];
        $qtyFmt = rtrim(rtrim(number_format($bestQty, 4, ',', ''), '0'), ',');
        $hint = 'Engpass: ' . $name . ' begrenzt die Linie'
            . ' (geringster Output/h: ' . $qtyFmt
            . ($unit !== '' ? ' ' . $unit : '')
            . '/h).';

        return [
            'station_order' => $bestOrder,
            'station_id' => $stationId,
            'work_center_id' => $wcId,
            'output_qty_per_hour' => $bestQty,
            'unit' => $unit,
            'hint' => $hint,
        ];
    }

    /**
     * Grober Linien-Durchsatz = min(Station-Output/h) = Engpass-Rate.
     * Puffer-Annahme: bei ausreichenden Puffern dauerhaft haltbar; bei Linienstopp effektiv 0.
     *
     * @param array<string, mixed>|null $bottleneck
     * @param list<array<string, mixed>> $buffers
     * @return array{
     *   qty_per_hour: float,
     *   unit: string,
     *   assumption: string,
     *   hint: string
     * }|null
     */
    public static function resolveLineThroughput(
        string $lineStatus,
        ?array $bottleneck,
        array $buffers = []
    ): ?array {
        if ($bottleneck === null) {
            return null;
        }
        $unit = (string) ($bottleneck['unit'] ?? '');
        $bnQty = (float) ($bottleneck['output_qty_per_hour'] ?? 0);
        if ($bnQty <= 0) {
            return null;
        }

        if ($lineStatus === 'stopped' || $lineStatus === 'stopped_material') {
            return [
                'qty_per_hour' => 0.0,
                'unit' => $unit,
                'assumption' => 'zero_while_line_stopped',
                'hint' => 'Linien-Durchsatz derzeit 0 — Linie steht (Material oder keine lauffähige Station).',
            ];
        }

        $anyBlock = false;
        $anyStarve = false;
        foreach ($buffers as $b) {
            if (!empty($b['blocks_predecessor'])) {
                $anyBlock = true;
            }
            if (!empty($b['starves_successor'])) {
                $anyStarve = true;
            }
        }

        $qtyFmt = rtrim(rtrim(number_format($bnQty, 4, ',', ''), '0'), ',');
        $unitPart = $unit !== '' ? ' ' . $unit : '';

        if ($anyBlock || $anyStarve || $lineStatus === 'constrained' || $lineStatus === 'degraded') {
            return [
                'qty_per_hour' => $bnQty,
                'unit' => $unit,
                'assumption' => 'bottleneck_rate_if_buffers_recover',
                'hint' => 'Grober Linien-Durchsatz ca. ' . $qtyFmt . $unitPart
                    . '/h (Engpass), sobald Puffer Blocking/Starving auflösen — Annahme: Puffer reichen für Dauerlauf am Engpass.',
            ];
        }

        return [
            'qty_per_hour' => $bnQty,
            'unit' => $unit,
            'assumption' => 'bottleneck_with_adequate_buffers',
            'hint' => 'Grober Linien-Durchsatz ca. ' . $qtyFmt . $unitPart
                . '/h — Minimum der Stations-Outputs (Engpass), Annahme ausreichende Puffer.',
        ];
    }

    /**
     * @param list<array<string, mixed>> $stations
     * @return list<array{station_order: int, output_qty_per_hour: float, unit: string, label: string}>
     */
    private static function loadThroughputsFromProfiles(array $stations, ?int $recipeId): array
    {
        if (!class_exists('MachinePlanningRepository')) {
            return [];
        }
        $out = [];
        foreach ($stations as $st) {
            $wcId = (int) ($st['work_center_id'] ?? 0);
            $order = (int) ($st['station_order'] ?? 0);
            if ($wcId <= 0 || $order <= 0) {
                continue;
            }
            $profile = MachinePlanningRepository::findBestCapacityProfile(
                $wcId,
                $recipeId !== null && $recipeId > 0 ? $recipeId : null
            );
            if ($profile === null) {
                continue;
            }
            $qty = (float) ($profile['output_qty_per_hour'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $out[] = [
                'station_order' => $order,
                'output_qty_per_hour' => $qty,
                'unit' => (string) ($profile['unit'] ?? ''),
                'label' => (string) ($st['label'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Kurze Endnutzer-Texte (UI) — keine Plan-Codes.
     */
    public static function stationStatusLabel(string $status): string
    {
        return match ($status) {
            'running' => 'läuft',
            'blocked' => 'steht (Puffer voll)',
            'starved' => 'steht (kein Material vom Vorgänger)',
            'down' => 'außer Betrieb',
            default => 'unbekannt',
        };
    }

    public static function lineStatusLabel(string $status): string
    {
        return match ($status) {
            'running' => 'Linie läuft',
            'constrained' => 'Linie gestaut',
            'degraded' => 'Linie eingeschränkt (Störung)',
            'stopped' => 'Linie steht',
            'stopped_material' => 'Linie steht (Material fehlt)',
            default => 'Linie: Status unklar',
        };
    }

    /**
     * @param list<string> $reasons
     * @return list<string>
     */
    public static function reasonLabels(array $reasons): array
    {
        $map = [
            'buffer_full' => 'Ausgangspuffer voll',
            'buffer_empty' => 'Eingangspuffer leer',
            'raw_material_missing' => 'Rohware am Eingang fehlt',
            'aux_material_missing' => 'Hilfsstoff fehlt',
            'station_down' => 'Störung oder Wartung',
            'downstream_down_block_risk' => 'Risiko: Nachfolger außer Betrieb',
            'upstream_down' => 'Vorgänger außer Betrieb',
        ];
        $out = [];
        foreach ($reasons as $r) {
            $key = (string) $r;
            if ($key === '') {
                continue;
            }
            $out[] = $map[$key] ?? 'Hinweis';
        }

        return $out;
    }

    public static function stationStatusBadgeClass(string $status): string
    {
        return match ($status) {
            'running' => 'dg-badge--ok',
            'blocked', 'starved' => 'dg-badge--pending',
            'down' => 'dg-badge--error',
            default => 'dg-badge--muted',
        };
    }

    public static function lineStatusBadgeClass(string $status): string
    {
        return match ($status) {
            'running' => 'dg-badge--ok',
            'constrained', 'degraded' => 'dg-badge--pending',
            'stopped', 'stopped_material' => 'dg-badge--error',
            default => 'dg-badge--muted',
        };
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
