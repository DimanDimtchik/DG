<?php
declare(strict_types=1);

/**
 * Chart Account Repository.
 */
final class ChartAccountRepository
{
    /** @var array<string, bool> */
    private static array $seedChecked = [];

    /**
     * Stellt Standarddaten in der Datenbank sicher
     * @param string|null $skrType Kontenrahmen (skr03/skr04)
     * @return void
     */
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
        self::syncGeneratedHints($skrType);

        self::$seedChecked[$skrType] = true;
    }

    /**
     * syncCatalogAccounts
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @return void
     * @throws RuntimeException
     */
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

    /**
     * syncHintAccounts
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @return void
     * @throws RuntimeException
     */
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
            $hints = $account['hints'];
            $hints['dg_hint_level'] = 'manual';
            $hintsJson = json_encode($hints, JSON_UNESCAPED_UNICODE);
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

    /**
     * syncGeneratedHints
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @return void
     */
    private static function syncGeneratedHints(string $skrType): void
    {
        $version = ChartAccountHintBuilder::generatorVersion();
        $key = 'chart_hints_gen_' . $skrType;
        $stored = SettingsStore::get('chart_catalog_sync', []);
        if (($stored[$key] ?? '') === $version) {
            return;
        }

        $manualNumbers = [];
        foreach (ChartAccountSeedData::accountsForSkr($skrType) as $account) {
            $manualNumbers[(string) $account['account_number']] = true;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT account_number, name, section, hints_json
             FROM dg_chart_accounts
             WHERE skr_type = :skr_type AND is_active = 1'
        );
        $stmt->execute(['skr_type' => $skrType]);

        $update = $pdo->prepare(
            'UPDATE dg_chart_accounts
             SET hints_json = :hints_json
             WHERE skr_type = :skr_type AND account_number = :account_number'
        );

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $number = (string) ($row['account_number'] ?? '');
            if ($number === '' || isset($manualNumbers[$number])) {
                continue;
            }

            $existing = [];
            $rawHints = $row['hints_json'] ?? '';
            if (is_string($rawHints) && $rawHints !== '') {
                $decoded = json_decode($rawHints, true);
                if (is_array($decoded)) {
                    $existing = $decoded;
                }
            }

            if (!ChartAccountHintBuilder::needsEnhancement($existing)) {
                continue;
            }

            $hints = ChartAccountHintBuilder::build(
                $skrType,
                $number,
                (string) ($row['name'] ?? ''),
                (string) ($row['section'] ?? ''),
                $existing
            );

            $hintsJson = json_encode($hints, JSON_UNESCAPED_UNICODE);
            if ($hintsJson === false) {
                continue;
            }

            $update->execute([
                'hints_json' => $hintsJson,
                'skr_type' => $skrType,
                'account_number' => $number,
            ]);
        }

        $stored[$key] = $version;
        SettingsStore::set('chart_catalog_sync', $stored);
    }

    /**
     * countWithDetailedHints
     * @param string|null $skrType Kontenrahmen (skr03/skr04)
     * @return int
     */
    public static function countWithDetailedHints(?string $skrType = null): int
    {
        if (!Database::isConfigured()) {
            return ChartAccountSeedData::seedCount($skrType ?? ChartOfAccountsSettings::activeSkrType());
        }

        self::ensureSeeded($skrType);
        $skrType = ChartOfAccountsSettings::sanitizeSkrType($skrType ?? ChartOfAccountsSettings::activeSkrType());
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM dg_chart_accounts
             WHERE skr_type = :skr_type AND is_active = 1
               AND hints_json LIKE :has_digits'
        );
        $stmt->execute([
            'skr_type' => $skrType,
            'has_digits' => '%digit_explanations%',
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * countForSkr
     * @param string|null $skrType Kontenrahmen (skr03/skr04)
     * @return int
     */
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

    /**
     * findByNumber
     * @param string $number
     * @param string|null $skrType Kontenrahmen (skr03/skr04)
     * @return ?array
     */
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
     * updateSearchTerms
     * @param string $accountNumber Kontonummer
     * @param array $terms
     * @param string|null $skrType Kontenrahmen (skr03/skr04)
     * @return array<string, mixed>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public static function updateSearchTerms(string $accountNumber, array $terms, ?string $skrType = null): array
    {
        self::ensureSeeded($skrType);

        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }

        $skrType = ChartOfAccountsSettings::sanitizeSkrType($skrType ?? ChartOfAccountsSettings::activeSkrType());
        $number = self::normalizeAccountNumber($accountNumber);
        $normalizedTerms = ChartAccountHintTerms::normalizeList($terms);

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
        if (!$row) {
            throw new InvalidArgumentException('Konto nicht gefunden.');
        }

        $hints = [];
        $rawHints = $row['hints_json'] ?? '';
        if (is_string($rawHints) && $rawHints !== '') {
            $decoded = json_decode($rawHints, true);
            if (is_array($decoded)) {
                $hints = $decoded;
            }
        }

        $hints['search_terms'] = $normalizedTerms;
        $hints['search_terms_edited'] = true;
        if (($hints['dg_hint_level'] ?? '') !== 'manual') {
            $hints['dg_hint_level'] = 'generated';
        }

        $hintsJson = json_encode($hints, JSON_UNESCAPED_UNICODE);
        if ($hintsJson === false) {
            throw new RuntimeException('Suchbegriffe konnten nicht gespeichert werden.');
        }

        $update = Database::pdo()->prepare(
            'UPDATE dg_chart_accounts SET hints_json = :hints_json WHERE id = :id'
        );
        $update->execute([
            'hints_json' => $hintsJson,
            'id' => (int) ($row['id'] ?? 0),
        ]);

        $row['hints_json'] = $hintsJson;

        return self::hydrateRow($row);
    }

        /**
     * search
     * @param string $query
     * @param string|null $skrType Kontenrahmen (skr03/skr04)
     * @param int $limit
     * @param string $voucherType Belegtyp
     * @param int|null $taxRate Steuersatz in Prozent
     * @return list<array<string, mixed>>
     */
    public static function search(string $query, ?string $skrType = null, int $limit = 20, string $voucherType = '', ?int $taxRate = null): array
    {
        self::ensureSeeded($skrType);

        if (!Database::isConfigured()) {
            return [];
        }

        $skrType = ChartOfAccountsSettings::sanitizeSkrType($skrType ?? ChartOfAccountsSettings::activeSkrType());
        $voucherType = trim($voucherType);
        if ($taxRate !== null) {
            $taxRate = VoucherTaxKeys::sanitizeTaxRate($taxRate);
        }
        $query = trim($query);
        $limit = max(1, min(50, $limit));

        if ($query === '') {
            return [];
        }

        $effectiveTaxRate = $taxRate;
        if ($effectiveTaxRate !== null
            && VoucherIncomePositions::usesInvoiceItems($voucherType)
            && in_array(mb_strtolower($query), ['erlös', 'erloes', 'umsatz', 'einnahme', 'einnahmen', 'gutschrift'], true)) {
            $effectiveTaxRate = null;
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
            $rows = self::filterRowsForVoucherBooking($rows, $voucherType, $effectiveTaxRate);
        } else {
            $rows = self::searchByText($query, $skrType, $limit, $voucherType, $effectiveTaxRate);
        }

        return array_map(self::hydrateRow(...), $rows);
    }

        /**
     * filterRowsForVoucherBooking
     * @param array $rows
     * @param string $voucherType Belegtyp
     * @param int|null $taxRate Steuersatz in Prozent
     * @return list<array<string, mixed>>
     */
    private static function filterRowsForVoucherBooking(array $rows, string $voucherType, ?int $taxRate = null): array
    {
        $filtered = [];
        foreach ($rows as $row) {
            if (!ChartAccountBookingEligibility::isSearchableRowForVoucherType($row, $voucherType)) {
                continue;
            }
            if ($taxRate !== null && VoucherIncomePositions::usesInvoiceItems($voucherType)
                && !ChartAccountBookingEligibility::matchesVoucherTaxRate((string) ($row['name'] ?? ''), $taxRate)) {
                continue;
            }
            $filtered[] = $row;
        }

        return $filtered;
    }

        /**
     * searchByText
     * @param string $query
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @param int $limit
     * @param string $voucherType Belegtyp
     * @param int|null $taxRate Steuersatz in Prozent
     * @return list<array<string, mixed>>
     */
    private static function searchByText(string $query, string $skrType, int $limit, string $voucherType = '', ?int $taxRate = null): array
    {
        $pdo = Database::pdo();
        $needle = mb_strtolower(trim($query));
        $targets = ChartAccountSearchLexicon::resolveBookingTargets($query, $skrType);
        $scoreByNumber = [];
        foreach ($targets as $target) {
            $scoreByNumber[(string) $target['account_number']] = (int) $target['score'];
        }

        $rows = [];

        if ($scoreByNumber !== []) {
            $placeholders = [];
            $params = ['skr_type' => $skrType];
            $index = 0;
            foreach (array_keys($scoreByNumber) as $accountNumber) {
                $key = 'num_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $accountNumber;
                ++$index;
            }

            $stmt = $pdo->prepare(
                'SELECT id, skr_type, account_number, name, account_class, section, is_active, hints_json
                 FROM dg_chart_accounts
                 WHERE skr_type = :skr_type AND is_active = 1
                   AND account_number IN (' . implode(', ', $placeholders) . ')'
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $effectiveLimit = ChartAccountSearchNormalizer::isReverseChargeQuery($query)
            ? max($limit, min(50, count($targets) + 5))
            : $limit;

        if (count($rows) < $effectiveLimit && $scoreByNumber === []
            && (mb_strlen($needle) >= 3 || ChartAccountSearchNormalizer::isReverseChargeQuery($query))) {
            $patterns = ChartAccountSearchNormalizer::sqlNamePatterns($query);
            $conditions = [];
            $params = ['skr_type' => $skrType, 'q_exact' => $needle, 'name_prefix' => $needle . '%'];
            foreach ($patterns as $index => $pattern) {
                $key = 'name_like_' . $index;
                $conditions[] = 'LOWER(name) LIKE :' . $key;
                $params[$key] = $pattern;
            }

            $stmt = $pdo->prepare(
                'SELECT id, skr_type, account_number, name, account_class, section, is_active, hints_json
                 FROM dg_chart_accounts
                 WHERE skr_type = :skr_type AND is_active = 1
                   AND (' . implode(' OR ', $conditions) . ')
                 ORDER BY
                   CASE
                     WHEN LOWER(name) = :q_exact THEN 0
                     WHEN LOWER(name) LIKE :name_prefix THEN 1
                     ELSE 2
                   END,
                   account_number ASC
                 LIMIT ' . max($effectiveLimit * 2, 30)
            );
            $stmt->execute($params);

            $existing = [];
            foreach ($rows as $row) {
                $existing[(string) ($row['account_number'] ?? '')] = true;
            }
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                if (!ChartAccountBookingEligibility::isSearchableRowForVoucherType($row, $voucherType)) {
                    continue;
                }
                if ($taxRate !== null && VoucherIncomePositions::usesInvoiceItems($voucherType)
                    && !ChartAccountBookingEligibility::matchesVoucherTaxRate((string) ($row['name'] ?? ''), $taxRate)) {
                    continue;
                }
                $key = (string) ($row['account_number'] ?? '');
                if ($key !== '' && !isset($existing[$key])) {
                    $rows[] = $row;
                    $existing[$key] = true;
                }
            }
        }

        foreach (self::searchByStoredTerms($query, $skrType, $voucherType, $taxRate) as $item) {
            $key = (string) ($item['row']['account_number'] ?? '');
            if ($key === '') {
                continue;
            }
            if (!isset($scoreByNumber[$key]) || $item['score'] < $scoreByNumber[$key]) {
                $scoreByNumber[$key] = $item['score'];
            }
            $found = false;
            foreach ($rows as $row) {
                if ((string) ($row['account_number'] ?? '') === $key) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $rows[] = $item['row'];
            }
        }

        $scored = [];
        foreach ($rows as $row) {
            if (!ChartAccountBookingEligibility::isSearchableRowForVoucherType($row, $voucherType)) {
                continue;
            }
            if ($taxRate !== null && VoucherIncomePositions::usesInvoiceItems($voucherType)
                && !ChartAccountBookingEligibility::matchesVoucherTaxRate((string) ($row['name'] ?? ''), $taxRate)) {
                continue;
            }
            $score = self::searchScore($query, $row, $skrType, $scoreByNumber);
            if ($score === null) {
                continue;
            }
            $scored[] = ['score' => $score, 'row' => $row];
        }

        usort($scored, static function (array $a, array $b): int {
            $cmp = $a['score'] <=> $b['score'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($a['row']['account_number'] ?? ''), (string) ($b['row']['account_number'] ?? ''));
        });

        $merged = [];
        $seen = [];
        foreach ($scored as $item) {
            $key = (string) ($item['row']['account_number'] ?? '');
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $merged[] = $item['row'];
            if (count($merged) >= $effectiveLimit) {
                break;
            }
        }

        return $merged;
    }

        /**
     * searchScore
     * @param string $query
     * @param array $row Datenbankzeile
     * @param string|null $skrType Kontenrahmen (skr03/skr04)
     * @param array $bookingScores
     * @return ?int
     */
    private static function searchScore(string $query, array $row, ?string $skrType = null, array $bookingScores = []): ?int
    {
        $needle = mb_strtolower(trim($query));
        if ($needle === '') {
            return null;
        }

        $accountNumber = str_pad(preg_replace('/\D/', '', (string) ($row['account_number'] ?? '')) ?? '', 4, '0', STR_PAD_LEFT);
        $skrType = ChartOfAccountsSettings::sanitizeSkrType($skrType ?? (string) ($row['skr_type'] ?? 'skr03'));

        if (isset($bookingScores[$accountNumber])) {
            return $bookingScores[$accountNumber];
        }

        $bookingScore = ChartAccountSearchLexicon::synonymScore($query, $skrType, $accountNumber, (string) ($row['name'] ?? ''));
        if ($bookingScore !== null) {
            return $bookingScore;
        }

        $hintScore = ChartAccountHintTerms::scoreQuery($query, ChartAccountHintTerms::fromRow($row));
        if ($hintScore !== null) {
            return $hintScore;
        }

        $name = (string) ($row['name'] ?? '');
        if (ChartAccountSearchNormalizer::nameMatchesQuery($query, $name)) {
            return 18;
        }

        $nameLower = mb_strtolower($name);
        if ($nameLower === $needle) {
            return 0;
        }
        if (str_starts_with($nameLower, $needle)) {
            return 10;
        }
        if (mb_strlen($needle) >= 3 && str_contains($nameLower, $needle)) {
            return 20;
        }

        return null;
    }

        /**
     * searchByStoredTerms
     * @param string $query
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @param string $voucherType Belegtyp
     * @param int|null $taxRate Steuersatz in Prozent
     * @return list<array{score: int, row: array<string, mixed>}>
     */
    private static function searchByStoredTerms(string $query, string $skrType, string $voucherType = '', ?int $taxRate = null): array
    {
        $needle = mb_strtolower(trim($query));
        if ($needle === '' || mb_strlen($needle) < 2) {
            return [];
        }

        $stmt = Database::pdo()->prepare(
            'SELECT id, skr_type, account_number, name, account_class, section, is_active, hints_json
             FROM dg_chart_accounts
             WHERE skr_type = :skr_type AND is_active = 1
               AND hints_json IS NOT NULL
               AND hints_json <> \'\'
               AND hints_json LIKE :has_terms'
        );
        $stmt->execute([
            'skr_type' => $skrType,
            'has_terms' => '%"search_terms"%',
        ]);

        $hits = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (!ChartAccountBookingEligibility::isSearchableRowForVoucherType($row, $voucherType)) {
                continue;
            }
            if ($taxRate !== null && VoucherIncomePositions::usesInvoiceItems($voucherType)
                && !ChartAccountBookingEligibility::matchesVoucherTaxRate((string) ($row['name'] ?? ''), $taxRate)) {
                continue;
            }
            $score = ChartAccountHintTerms::scoreQuery($query, ChartAccountHintTerms::fromRow($row));
            if ($score === null) {
                continue;
            }
            $hits[] = ['score' => $score, 'row' => $row];
        }

        usort($hits, static function (array $a, array $b): int {
            $cmp = $a['score'] <=> $b['score'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($a['row']['account_number'] ?? ''), (string) ($b['row']['account_number'] ?? ''));
        });

        return $hits;
    }

    /**
     * Liefert Kontenabschnitte.
     *
     * @return list<string>
     */
        public static function sections(): array
    {
        return ['aktiva', 'passiva', 'aufwand', 'ertrag'];
    }

    /**
     * sectionLabel
     * @param string $section Kontenabschnitt
     * @return string
     */
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

        /**
     * hydrateRow
     * @param array $row Datenbankzeile
     * @return array
     */
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
     * digitBreakdown
     * @param string $accountNumber Kontonummer
     * @param array $legend
     * @param array $hints Kontenhinweise
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

    /**
     * normalizeAccountNumber
     * @param string $number
     * @return string
     */
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
