<?php
declare(strict_types=1);

/**
 * FL5c: Materialbedarf gegen Lagerbestand — nur lesen, kein Buchen.
 */
final class ProductionLineMaterialStockGate
{
    /**
     * @param list<array{
     *   article_id: int,
     *   required_qty: float|string,
     *   role?: 'raw'|'aux',
     *   station_order?: int,
     *   label?: string
     * }> $requirements
     * @param callable(int): (float|null) $availableQtyResolver article_id → verfügbare Menge; null = nicht prüfbar/ok
     * @return array{
     *   raw_material_ok: bool,
     *   missing_aux_from_station_order: int|null,
     *   details: list<array<string, mixed>>,
     *   booked: false
     * }
     */
    public static function evaluateRequirements(array $requirements, callable $availableQtyResolver): array
    {
        $rawOk = true;
        $minAuxFail = null;
        $details = [];

        foreach ($requirements as $i => $req) {
            if (!is_array($req)) {
                continue;
            }
            $articleId = (int) ($req['article_id'] ?? 0);
            $required = (float) str_replace(',', '.', (string) ($req['required_qty'] ?? '0'));
            $role = (string) ($req['role'] ?? '');
            $stationOrder = (int) ($req['station_order'] ?? 0);
            if ($role !== 'raw' && $role !== 'aux') {
                $role = $stationOrder > 1 ? 'aux' : 'raw';
            }
            if ($role === 'aux' && $stationOrder < 1) {
                $stationOrder = 1;
            }
            if ($role === 'raw') {
                $stationOrder = 0;
            }
            $label = trim((string) ($req['label'] ?? ''));

            $available = null;
            $ok = true;
            $skip = false;
            if ($articleId <= 0 || $required <= 0) {
                $skip = true;
                $ok = true;
            } else {
                $available = $availableQtyResolver($articleId);
                if ($available === null) {
                    // nicht trackbar / kein Snapshot → nicht blockieren
                    $skip = true;
                    $ok = true;
                } else {
                    $ok = (float) $available + 1e-9 >= $required;
                }
            }

            if (!$skip && !$ok) {
                if ($role === 'raw') {
                    $rawOk = false;
                } else {
                    if ($minAuxFail === null || $stationOrder < $minAuxFail) {
                        $minAuxFail = $stationOrder;
                    }
                }
            }

            $details[] = [
                'index' => $i,
                'article_id' => $articleId,
                'required_qty' => $required,
                'available_qty' => $available,
                'role' => $role,
                'station_order' => $role === 'raw' ? 1 : $stationOrder,
                'label' => $label,
                'ok' => $ok,
                'checked' => !$skip,
            ];
        }

        return [
            'raw_material_ok' => $rawOk,
            'missing_aux_from_station_order' => $minAuxFail,
            'details' => $details,
            'booked' => false,
        ];
    }

    /**
     * Standard-Resolver: StockAvailabilityService::snapshot (nur lesen).
     *
     * @return callable(int): (float|null)
     */
    public static function stockAvailabilityResolver(): callable
    {
        return static function (int $articleId): ?float {
            if ($articleId <= 0 || !class_exists('StockAvailabilityService')) {
                return null;
            }
            $snap = StockAvailabilityService::snapshot($articleId);
            if ($snap === null) {
                return null;
            }
            if (empty($snap['track_stock'])) {
                return null;
            }

            return (float) ($snap['available'] ?? 0);
        };
    }

    /**
     * @param list<array<string, mixed>> $requirements
     * @param array<string, mixed> $evaluateOptions
     * @param callable(int): (float|null)|null $resolver
     * @return array{options: array<string, mixed>, gate: array<string, mixed>}
     */
    public static function mergeIntoEvaluateOptions(
        array $requirements,
        array $evaluateOptions = [],
        ?callable $resolver = null
    ): array {
        $gate = self::evaluateRequirements(
            $requirements,
            $resolver ?? self::stockAvailabilityResolver()
        );
        $options = $evaluateOptions;
        if (!array_key_exists('raw_material_ok', $options)) {
            $options['raw_material_ok'] = $gate['raw_material_ok'];
        }
        if (!array_key_exists('missing_aux_from_station_order', $options)
            && $gate['missing_aux_from_station_order'] !== null
        ) {
            $options['missing_aux_from_station_order'] = $gate['missing_aux_from_station_order'];
        }

        return [
            'options' => $options,
            'gate' => $gate,
        ];
    }

    /**
     * Status + Gate in einem Schritt (Store-Pfad).
     *
     * @param list<array<string, mixed>> $requirements
     * @param array<string, mixed> $options
     * @return array<string, mixed>|null
     */
    public static function evaluateLineStatus(
        int $lineId,
        array $requirements,
        array $options = [],
        ?callable $resolver = null
    ): ?array {
        if (!class_exists('ProductionLineStatusService')) {
            return null;
        }
        $merged = self::mergeIntoEvaluateOptions($requirements, $options, $resolver);
        $status = ProductionLineStatusService::evaluate($lineId, $merged['options']);
        if ($status === null) {
            return null;
        }
        $status['material_stock_gate'] = $merged['gate'];

        return $status;
    }
}
