<?php
declare(strict_types=1);

/** Rechnungspositionen für Erlös-Belege (Artikel/Leistungen → Buchungszeilen). */
final class VoucherIncomePositions
{
    /**
     * voucherTypesWithItems.
     *
     * @return list<string>
     */
        public static function voucherTypesWithItems(): array
    {
        return ['income', 'income_reduction', 'credit'];
    }

    /**
     * usesInvoiceItems
     * @param string $voucherType Belegtyp
     * @return bool
     */
    public static function usesInvoiceItems(string $voucherType): bool
    {
        return in_array(VoucherRepository::normalizeVoucherType($voucherType), self::voucherTypesWithItems(), true);
    }

    /**
     * taxRateFromTaxType
     * @param string $taxType
     * @return int
     */
    public static function taxRateFromTaxType(string $taxType): int
    {
        $taxType = trim($taxType);

        return match ($taxType) {
            'ust7' => 7,
            'ust19' => 19,
            default => 0,
        };
    }

    /**
     * taxTypeFromRate
     * @param int $taxRate Steuersatz in Prozent
     * @return string
     */
    public static function taxTypeFromRate(int $taxRate): string
    {
        return match (VoucherTaxKeys::sanitizeTaxRate($taxRate)) {
            7 => 'ust7',
            0 => 'ust0',
            default => 'ust19',
        };
    }

        /**
     * defaultRevenueAccounts
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @return array<int, string>
     */
    public static function defaultRevenueAccounts(string $skrType): array
    {
        unset($skrType);

        return [
            19 => '8410',
            7 => '8334',
            0 => '8192',
        ];
    }

    /**
     * defaultRevenueAccount
     * @param int $taxRate Steuersatz in Prozent
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @return string
     */
    public static function defaultRevenueAccount(int $taxRate, string $skrType): string
    {
        $rate = VoucherTaxKeys::sanitizeTaxRate($taxRate);
        $accounts = self::defaultRevenueAccounts($skrType);

        return $accounts[$rate] ?? $accounts[19];
    }

        /**
     * searchArticles
     * @param string $query
     * @param int $limit
     * @return list<array<string, mixed>>
     */
    public static function searchArticles(string $query, int $limit = 15): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        MigrationRunner::runPending();

        $query = trim($query);
        $limit = max(1, min(30, $limit));

        if ($query === '') {
            $stmt = Database::pdo()->prepare(
                'SELECT a.*, ar.name AS area_name
                 FROM dg_calendar_articles a
                 LEFT JOIN dg_calendar_areas ar ON ar.id = a.area_id
                 WHERE a.is_active = 1
                 ORDER BY a.sort_order ASC, a.title ASC
                 LIMIT :limit'
            );
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $like = '%' . $query . '%';
            $stmt = Database::pdo()->prepare(
                'SELECT a.*, ar.name AS area_name
                 FROM dg_calendar_articles a
                 LEFT JOIN dg_calendar_areas ar ON ar.id = a.area_id
                 WHERE a.is_active = 1
                   AND (
                        a.title LIKE :q1 OR a.article_number LIKE :q2 OR a.description LIKE :q3
                   )
                 ORDER BY a.sort_order ASC, a.title ASC
                 LIMIT :limit'
            );
            $stmt->bindValue(':q1', $like);
            $stmt->bindValue(':q2', $like);
            $stmt->bindValue(':q3', $like);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
        }

        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::articlePayload($row);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function articlePayload(array $row): array
    {
        $taxType = (string) ($row['tax_type'] ?? 'ust19');
        $taxRate = self::taxRateFromTaxType($taxType);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'catalog_kind' => (string) ($row['catalog_kind'] ?? CalendarArticleCatalog::KIND_SERVICE),
            'kind_label' => CalendarArticleCatalog::kindLabel((string) ($row['catalog_kind'] ?? CalendarArticleCatalog::KIND_SERVICE)),
            'article_number' => (string) ($row['article_number'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'unit' => (string) ($row['unit'] ?? 'Stück'),
            'tax_type' => $taxType,
            'tax_rate' => $taxRate,
            'tax_label' => CalendarArticleValidator::taxLabel($taxType),
            'price_gross' => round((float) ($row['price_gross'] ?? 0), 2),
            'price_label' => CalendarArticleValidator::formatPrice((float) ($row['price_gross'] ?? 0)),
            'area_id' => (int) ($row['area_id'] ?? 0),
            'area_name' => (string) ($row['area_name'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array<string, mixed>>
     */
    public static function parseItemRows(array $data): array
    {
        $raw = $data['items'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $rows = [];
        foreach ($raw as $line) {
            if (!is_array($line)) {
                continue;
            }

            $title = trim((string) ($line['title'] ?? ''));
            $articleId = max(0, (int) ($line['article_id'] ?? 0));
            if ($title === '' && $articleId < 1) {
                continue;
            }

            $quantity = self::parseQuantity($line['quantity'] ?? 1);
            if ($quantity <= 0) {
                continue;
            }

            $unitPrice = round((float) str_replace(',', '.', (string) ($line['unit_price_gross'] ?? '0')), 2);
            $gross = round($quantity * $unitPrice, 2);
            if ($gross <= 0) {
                $gross = round((float) str_replace(',', '.', (string) ($line['gross_amount'] ?? '0')), 2);
            }
            if ($gross <= 0) {
                continue;
            }

            $taxRate = VoucherTaxKeys::sanitizeTaxRate((int) ($line['tax_rate'] ?? 19));
            $taxType = trim((string) ($line['tax_type'] ?? ''));
            if ($taxType === '') {
                $taxType = self::taxTypeFromRate($taxRate);
            }

            $areaId = max(0, (int) ($line['area_id'] ?? 0));
            $areaName = trim((string) ($line['area_name'] ?? ''));
            if ($areaName === '' && $areaId > 0 && Database::isConfigured()) {
                $stmt = Database::pdo()->prepare('SELECT name FROM dg_calendar_areas WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $areaId]);
                $areaName = trim((string) ($stmt->fetchColumn() ?: ''));
            }

            $rows[] = [
                'article_id' => $articleId,
                'catalog_kind' => CalendarArticleCatalog::normalizeKind((string) ($line['catalog_kind'] ?? CalendarArticleCatalog::KIND_SERVICE)),
                'article_number' => trim((string) ($line['article_number'] ?? '')),
                'title' => $title,
                'area_id' => $areaId,
                'area_name' => $areaName,
                'unit' => trim((string) ($line['unit'] ?? 'Stück')) ?: 'Stück',
                'quantity' => $quantity,
                'unit_price_gross' => $unitPrice > 0 ? $unitPrice : round($gross / $quantity, 2),
                'gross_amount' => $gross,
                'tax_rate' => $taxRate,
                'tax_type' => $taxType,
            ];
        }

        return $rows;
    }

        /**
     * bookingLinesFromItems
     * @param array $items
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @return list<array{account_number: string, description: string, gross_amount: float, net_amount: float, tax_amount: float, tax_rate: int, line_kind: string, ustva_kz: string, posting_side: string}>
     */
    public static function bookingLinesFromItems(array $items, string $skrType): array
    {
        if ($items === []) {
            return [];
        }

        ChartAccountRepository::ensureSeeded($skrType);

        /** @var array<string, array{account_number: string, tax_rate: int, gross_amount: float, titles: list<string>}> $groups */
        $groups = [];
        foreach ($items as $item) {
            $taxRate = VoucherTaxKeys::sanitizeTaxRate((int) ($item['tax_rate'] ?? 19));
            $accountNumber = self::defaultRevenueAccount($taxRate, $skrType);
            $key = $accountNumber . ':' . $taxRate;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'account_number' => $accountNumber,
                    'tax_rate' => $taxRate,
                    'gross_amount' => 0.0,
                    'titles' => [],
                ];
            }
            $groups[$key]['gross_amount'] += (float) ($item['gross_amount'] ?? 0);
            $title = trim((string) ($item['title'] ?? ''));
            if ($title !== '') {
                $groups[$key]['titles'][] = $title;
            }
        }

        $rows = [];
        foreach ($groups as $group) {
            $gross = round($group['gross_amount'], 2);
            if ($gross <= 0) {
                continue;
            }
            $amounts = VoucherTaxKeys::calcLineAmounts($gross, $group['tax_rate'], false);
            $description = $group['titles'] !== []
                ? mb_substr(implode(', ', array_unique($group['titles'])), 0, 500)
                : '';
            $rows[] = [
                'line_kind' => VoucherReverseCharge::LINE_BOOKING,
                'account_number' => $group['account_number'],
                'description' => $description,
                'gross_amount' => $amounts['gross_amount'],
                'net_amount' => $amounts['net_amount'],
                'tax_amount' => $amounts['tax_amount'],
                'tax_rate' => $group['tax_rate'],
                'ustva_kz' => '',
                'posting_side' => 'debit',
            ];
        }

        return $rows;
    }

    /**
     * parseQuantity
     * @param mixed $value Eingabewert
     * @return float
     */
    private static function parseQuantity(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }
        $quantity = round((float) $value, 3);

        return $quantity > 0 ? $quantity : 0.0;
    }
}
