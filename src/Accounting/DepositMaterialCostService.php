<?php
declare(strict_types=1);

/** Materialkosten (EK) aus Belegpositionen für Anzahlung. */
final class DepositMaterialCostService
{
    /**
     * @param list<array<string, mixed>> $items
     * @return array{
     *   amount: float,
     *   lines_priced: int,
     *   lines_missing: int,
     *   details: list<array{title: string, qty: float, unit_cost: float, line_cost: float}>
     * }
     */
    public static function fromVoucherItems(array $items): array
    {
        $articleIds = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $aid = (int) ($item['article_id'] ?? 0);
            if ($aid > 0) {
                $articleIds[] = $aid;
            }
        }
        $sources = ArticlePurchaseSourceRepository::forArticles($articleIds);

        $amount = 0.0;
        $priced = 0;
        $missing = 0;
        $details = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = trim((string) ($item['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $qty = VoucherRepository::parseMoney($item['quantity'] ?? '1');
            if ($qty == 0.0) {
                continue;
            }
            $aid = (int) ($item['article_id'] ?? 0);
            $unitCost = 0.0;
            if ($aid > 0) {
                $list = $sources[$aid] ?? [];
                $preferred = null;
                foreach ($list as $src) {
                    if (!empty($src['is_preferred'])) {
                        $preferred = $src;
                        break;
                    }
                }
                if ($preferred === null && $list !== []) {
                    $preferred = $list[0];
                }
                if ($preferred !== null) {
                    $unitCost = round((float) ($preferred['purchase_price'] ?? 0), 4);
                }
            }

            if ($aid < 1 || $unitCost <= 0) {
                $missing++;
                continue;
            }

            $lineCost = round($qty * $unitCost, 2);
            $amount = round($amount + $lineCost, 2);
            $priced++;
            $details[] = [
                'title' => $title,
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'line_cost' => $lineCost,
            ];
        }

        return [
            'amount' => $amount,
            'lines_priced' => $priced,
            'lines_missing' => $missing,
            'details' => $details,
        ];
    }
}
