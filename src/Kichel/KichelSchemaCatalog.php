<?php
declare(strict_types=1);

/**
 * Schema aus Migrationen + sichere Live-DB-Hinweise (keine Secrets).
 */
final class KichelSchemaCatalog
{
    /** @var array<string, list<string>>|null */
    private static ?array $tables = null;

    /**
     * @return array<string, list<string>>
     */
    public static function tables(): array
    {
        if (self::$tables !== null) {
            return self::$tables;
        }

        $tables = [];
        $dir = DG_ROOT . '/database/migrations';
        if (!is_dir($dir)) {
            self::$tables = [];

            return self::$tables;
        }

        $files = scandir($dir) ?: [];
        sort($files);
        foreach ($files as $file) {
            if (!str_ends_with($file, '.sql')) {
                continue;
            }
            $sql = file_get_contents($dir . '/' . $file);
            if ($sql === false) {
                continue;
            }
            if (preg_match_all('/CREATE TABLE IF NOT EXISTS\s+`?([a-z0-9_]+)`?\s*\((.*?)\)\s*ENGINE/is', $sql, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $table = strtolower($match[1]);
                    $body = $match[2];
                    $columns = [];
                    foreach (preg_split('/,\s*\n/', $body) ?: [] as $line) {
                        $line = trim($line);
                        if ($line === '' || preg_match('/^(PRIMARY|UNIQUE|KEY|CONSTRAINT|FOREIGN)/i', $line)) {
                            continue;
                        }
                        if (preg_match('/^`?([a-z0-9_]+)`?\s+/i', $line, $colMatch)) {
                            $columns[] = strtolower($colMatch[1]);
                        }
                    }
                    $tables[$table] = array_values(array_unique($columns));
                }
            }
        }

        self::$tables = $tables;

        return self::$tables;
    }

    /**
     * @param list<string> $tokens
     * @return list<array{table: string, columns: list<string>, score: int, row_count?: int|null}>
     */
    public static function searchSchema(array $tokens, int $limit = 6): array
    {
        if ($tokens === []) {
            return [];
        }

        $hits = [];
        foreach (self::tables() as $table => $columns) {
            $score = 0;
            foreach ($tokens as $token) {
                if (str_contains($table, $token)) {
                    $score += 4;
                }
                foreach ($columns as $column) {
                    if (str_contains($column, $token)) {
                        $score += 2;
                    }
                }
            }
            if ($score <= 0) {
                continue;
            }
            $hits[] = [
                'table' => $table,
                'columns' => array_slice($columns, 0, 12),
                'score' => $score,
                'row_count' => self::safeRowCount($table),
            ];
        }

        usort($hits, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($hits, 0, $limit);
    }

    /**
     * @return array<string, string|int|bool|null>
     */
    public static function companySnapshot(): array
    {
        if (!class_exists('CompanySettings')) {
            return [];
        }

        $cfg = CompanySettings::config();

        return [
            'firma' => $cfg['name'] ?? '',
            'stadt' => $cfg['city'] ?? '',
            'ust_id' => $cfg['vat_id'] ?? '',
            'steuernummer' => $cfg['tax_number'] ?? '',
            'email' => $cfg['email'] ?? '',
            'website' => $cfg['website'] ?? '',
        ];
    }

    /**
     * @param list<string> $tokens
     * @return list<array{key: string, label: string, value: string}>
     */
    public static function matchCompanyFields(array $tokens): array
    {
        $map = [
            'firma' => ['firma', 'firmenname', 'unternehmen', 'name'],
            'ust_id' => ['ust', 'ust-id', 'umsatzsteuer', 'vat'],
            'steuernummer' => ['steuernummer', 'steuer'],
            'email' => ['email', 'e-mail', 'mail'],
            'website' => ['website', 'webseite'],
            'stadt' => ['stadt', 'ort', 'anschrift'],
        ];

        $snapshot = self::companySnapshot();
        $out = [];
        foreach ($map as $key => $keywords) {
            foreach ($tokens as $token) {
                foreach ($keywords as $kw) {
                    if ($token === $kw || str_contains($kw, $token)) {
                        $value = trim((string) ($snapshot[$key] ?? ''));
                        $out[] = [
                            'key' => $key,
                            'label' => $key,
                            'value' => $value !== '' ? $value : '(noch nicht gesetzt)',
                        ];
                        break 2;
                    }
                }
            }
        }

        return $out;
    }

    private static function safeRowCount(string $table): ?int
    {
        if (!Database::isConfigured() || !preg_match('/^dg_[a-z0-9_]+$/', $table)) {
            return null;
        }

        try {
            $pdo = Database::pdo();
            $stmt = $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '', $table) . '`');

            return $stmt ? (int) $stmt->fetchColumn() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
