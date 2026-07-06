<?php
declare(strict_types=1);

final class ChartAccountRepository
{
    /** @var array<string, bool> */
    private static array $seedChecked = [];

    public static function ensureSeeded(?string $skrType = null): void
    {
        if (!Database::isConfigured()) {
            return;
        }

        MigrationRunner::runPending();

        $skrType = ChartOfAccountsSettings::sanitizeSkrType($skrType ?? ChartOfAccountsSettings::activeSkrType());
        if (isset(self::$seedChecked[$skrType])) {
            return;
        }

        self::syncCatalogAccounts($skrType);
        self::syncHintAccounts($skrType);

        self::$seedChecked[$skrType] = true;
    }

    private static function syncCatalogAccounts(string $skrType): void
    {
        $syncKey = 'chart_catalog_' . $skrType;
        $stored = SettingsStore::get('chart_catalog_sync', []);
        $currentVersion = ChartAccountCatalog::catalogVersion($skrType);
        if (($stored[$syncKey] ?? '') === $currentVersion) {
            return;
        }

        $accounts = ChartAccountCatalog::accountsForSkr($skrType);
        if ($accounts === []) {
            return;
        }

        $pdo = Database::pdo();
        $upsert = $pdo->prepare(
            'INSERT INTO dg_chart_accounts (skr_type, account_number, name, account_class, section, is_active, hints_json)
             VALUES (:skr_type, :account_number, :name, :account_class, :section, 1, :hints_json)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                account_class = VALUES(account_class),
                section = VALUES(section),
                is_active = 1'
        );

        foreach ($accounts as $account) {
            $hintsJson = json_encode($account['hints'], JSON_UNESCAPED_UNICODE);
            if ($hintsJson === false) {
                throw new RuntimeException('Katalog-Konto konnte nicht serialisiert werden.');
            }
            $upsert->execute([
                'skr_type' => $skrType,
                'account_number' => $account['account_number'],
                'name' => $account['name'],
                'account_class' => $account['account_class'],
                'section' => $account['section'],
                'hints_json' => $hintsJson,
            ]);
        }

        $stored[$syncKey] = $currentVersion;
        SettingsStore::set('chart_catalog_sync', $stored);
    }

    private static function syncHintAccounts(string $skrType): void
    {
        $pdo = Database::pdo();
        $upsert = $pdo->prepare(
            'INSERT INTO dg_chart_accounts (skr_type, account_number, name, account_class, section, is_active, hints_json)
             VALUES (:skr_type, :account_number, :name, :account_class, :section, 1, :hints_json)
             ON DUPLICATE KEY UPDATE
                hints_json = VALUES(hints_json),
                is_active = 1'
        );

        foreach (ChartAccountSeedData::accountsForSkr($skrType) as $account) {
            $hintsJson = json_encode($account['hints'], JSON_UNESCAPED_UNICODE);
            if ($hintsJson === false) {
                throw new RuntimeException('Konten-Hinweise konnten nicht serialisiert werden.');
            }
            $upsert->execute([
                'skr_type' => $skrType,
                'account_number' => $account['account_number'],
                'name' => $account['name'],
                'account_class' => $account['account_class'],
                'section' => $account['section'],
                'hints_json' => $hintsJson,
            ]);
        }
    }

    public static function countForSkr(?string $skrType = null): int
    {
        if (!Database::isConfigured()) {
            return 0;
        }

        self::ensureSeeded($skrType);

        $skrType = ChartOfAccountsSettings::sanitizeSkrType($skrType ?? ChartOfAccountsSettings::activeSkrType());
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM dg_chart_accounts WHERE skr_type = :skr_type AND is_active = 1');
        $stmt->execute(['skr_type' => $skrType]);

        return (int) $stmt->fetchColumn();
    }

    public static function findByNumber(string $number, ?string $skrType = null): ?array
    {
        self::ensureSeeded($skrType);

        if (!Database::isConfigured()) {
            return null;
        }

        $skrType = ChartOfAccountsSettings::sanitizeSkrType($skrType ?? ChartOfAccountsSettings::activeSkrType());
        $number = self::normalizeAccountNumber($number);

        $stmt = Database::pdo()->prepare(
            'SELECT id, skr_type, account_number, name, account_class, section, is_active, hints_json
             FROM dg_chart_accounts
             WHERE skr_type = :skr_type AND account_number = :account_number AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute([
            'skr_type' => $skrType,
            'account_number' => $number,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::hydrateRow($row) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function search(string $query, ?string $skrType = null, int $limit = 20): array
    {
        self::ensureSeeded($skrType);

        if (!Database::isConfigured()) {
            return [];
        }

        $skrType = ChartOfAccountsSettings::sanitizeSkrType($skrType ?? ChartOfAccountsSettings::activeSkrType());
        $query = trim($query);
        $limit = max(1, min(50, $limit));

        if ($query === '') {
            return [];
        }

        $pdo = Database::pdo();
        if (preg_match('/^\d+$/', $query) === 1) {
            $stmt = $pdo->prepare(
                'SELECT id, skr_type, account_number, name, account_class, section, is_active, hints_json
                 FROM dg_chart_accounts
                 WHERE skr_type = :skr_type AND is_active = 1
                   AND account_number LIKE :number_prefix
                 ORDER BY account_number ASC
                 LIMIT ' . $limit
            );
            $stmt->execute([
                'skr_type' => $skrType,
                'number_prefix' => $query . '%',
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $like = '%' . $query . '%';
            $prefix = $query . '%';
            $stmt = $pdo->prepare(
                'SELECT id, skr_type, account_number, name, account_class, section, is_active, hints_json
                 FROM dg_chart_accounts
                 WHERE skr_type = :skr_type AND is_active = 1
                   AND (name LIKE :name_like OR account_number LIKE :num_prefix)
                 ORDER BY
                   CASE
                     WHEN LOWER(name) = LOWER(:q_exact) THEN 0
                     WHEN LOWER(name) LIKE LOWER(:name_prefix) THEN 1
                     WHEN name LIKE :name_like_ord THEN 2
                     WHEN account_number LIKE :num_prefix_ord THEN 3
                     ELSE 4
                   END,
                   account_number ASC
                 LIMIT ' . $limit
            );
            $stmt->execute([
                'skr_type' => $skrType,
                'name_like' => $like,
                'name_like_ord' => $like,
                'num_prefix' => $prefix,
                'num_prefix_ord' => $prefix,
                'q_exact' => $query,
                'name_prefix' => $prefix,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if (count($rows) < $limit) {
                $rows = self::mergeSearchRows($rows, self::searchByHintTerms($query, $skrType, $limit), $limit);
            }
        }

        return array_map(self::hydrateRow(...), $rows);
    }

    /** @param list<array<string, mixed>> $rows */
    private static function searchByHintTerms(string $query, string $skrType, int $limit): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, skr_type, account_number, name, account_class, section, is_active, hints_json
             FROM dg_chart_accounts
             WHERE skr_type = :skr_type AND is_active = 1
               AND hints_json LIKE :has_terms
             ORDER BY account_number ASC'
        );
        $stmt->execute([
            'skr_type' => $skrType,
            'has_terms' => '%"search_terms"%',
        ]);

        $needle = mb_strtolower(trim($query));
        $matches = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $score = self::searchScore($query, $row);
            if ($score !== null && $score >= 30) {
                $matches[] = ['score' => $score, 'row' => $row];
            }
        }

        usort($matches, static fn (array $a, array $b): int => $a['score'] <=> $b['score']);

        return array_map(
            static fn (array $item): array => $item['row'],
            array_slice($matches, 0, $limit)
        );
    }

    /**
     * @param list<array<string, mixed>> $primary
     * @param list<array<string, mixed>> $secondary
     * @return list<array<string, mixed>>
     */
    private static function mergeSearchRows(array $primary, array $secondary, int $limit): array
    {
        $seen = [];
        $merged = [];
        foreach ([$primary, $secondary] as $group) {
            foreach ($group as $row) {
                $key = (string) ($row['account_number'] ?? '');
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $merged[] = $row;
                if (count($merged) >= $limit) {
                    return $merged;
                }
            }
        }

        return $merged;
    }

    /** @param array<string, mixed> $row */
    private static function searchScore(string $query, array $row): ?int
    {
        $needle = mb_strtolower(trim($query));
        if ($needle === '') {
            return null;
        }

        $name = mb_strtolower((string) ($row['name'] ?? ''));

        if ($name === $needle) {
            return 0;
        }
        if (str_starts_with($name, $needle)) {
            return 10;
        }
        if (str_contains($name, $needle)) {
            return 20;
        }

        $hints = [];
        $rawHints = $row['hints_json'] ?? '';
        if (is_string($rawHints) && $rawHints !== '') {
            $decoded = json_decode($rawHints, true);
            if (is_array($decoded)) {
                $hints = $decoded;
            }
        }

        $terms = is_array($hints['search_terms'] ?? null) ? $hints['search_terms'] : [];
        foreach ($terms as $term) {
            $termLower = mb_strtolower(trim((string) $term));
            if ($termLower === '') {
                continue;
            }
            if ($termLower === $needle) {
                return 30;
            }
            if (str_starts_with($termLower, $needle) || str_starts_with($needle, $termLower)) {
                return 40;
            }
            if (str_contains($termLower, $needle) || str_contains($needle, $termLower)) {
                return 50;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function sections(): array
    {
        return ['aktiva', 'passiva', 'aufwand', 'ertrag'];
    }

    public static function sectionLabel(string $section): string
    {
        return match ($section) {
            'aktiva' => 'Aktiva',
            'passiva' => 'Passiva',
            'aufwand' => 'Aufwand',
            'ertrag' => 'Ertrag',
            default => $section,
        };
    }

    /** @param array<string, mixed> $row */
    private static function hydrateRow(array $row): array
    {
        $hints = [];
        $rawHints = $row['hints_json'] ?? '';
        if (is_string($rawHints) && $rawHints !== '') {
            $decoded = json_decode($rawHints, true);
            if (is_array($decoded)) {
                $hints = $decoded;
            }
        }

        $skrType = (string) ($row['skr_type'] ?? 'skr03');
        $digitLegend = SkrDigitLegend::forSkr($skrType);
        $accountNumber = (string) ($row['account_number'] ?? '');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'skr_type' => $skrType,
            'account_number' => $accountNumber,
            'name' => (string) ($row['name'] ?? ''),
            'account_class' => (string) ($row['account_class'] ?? '0'),
            'section' => (string) ($row['section'] ?? ''),
            'section_label' => self::sectionLabel((string) ($row['section'] ?? '')),
            'is_active' => (bool) ($row['is_active'] ?? true),
            'hints' => $hints,
            'digit_legend' => $digitLegend,
            'digit_breakdown' => self::digitBreakdown($accountNumber, $digitLegend, $hints),
        ];
    }

    /**
     * @param array<int, string> $legend
     * @param array<string, mixed> $hints
     * @return list<array{digit: int, value: string, meaning: string, detail: string}>
     */
    private static function digitBreakdown(string $accountNumber, array $legend, array $hints): array
    {
        $digits = str_pad(preg_replace('/\D/', '', $accountNumber) ?? '', 4, '0', STR_PAD_LEFT);
        $explanations = is_array($hints['digit_explanations'] ?? null) ? $hints['digit_explanations'] : [];
        $breakdown = [];

        for ($i = 0; $i < 4; ++$i) {
            $digit = $i + 1;
            $value = substr($digits, $i, 1);
            $classDigit = (int) substr($digits, 0, 1);
            $meaning = $legend[$classDigit] ?? '';
            $detail = (string) ($explanations[$digit] ?? $explanations[(string) $digit] ?? '');

            $breakdown[] = [
                'digit' => $digit,
                'value' => $value,
                'meaning' => $meaning,
                'detail' => $detail,
            ];
        }

        return $breakdown;
    }

    private static function normalizeAccountNumber(string $number): string
    {
        $digits = ChartOfAccountsSettings::accountDigits();
        $clean = preg_replace('/\D/', '', $number) ?? '';
        if ($clean === '') {
            return '';
        }

        return str_pad(substr($clean, 0, $digits), $digits, '0', STR_PAD_LEFT);
    }
}
