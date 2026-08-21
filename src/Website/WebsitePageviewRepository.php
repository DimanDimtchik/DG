<?php
declare(strict_types=1);

/**
 * Records and aggregates public website pageviews (privacy-friendly, consent-gated).
 */
final class WebsitePageviewRepository
{
    public static function ensureTables(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
        Database::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS dg_website_pageviews (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                viewed_at DATETIME NOT NULL,
                path VARCHAR(255) NOT NULL DEFAULT \'/\',
                page_id INT UNSIGNED NULL,
                referrer_host VARCHAR(191) NOT NULL DEFAULT \'\',
                PRIMARY KEY (id),
                KEY idx_wpv_viewed (viewed_at),
                KEY idx_wpv_path_day (path, viewed_at),
                KEY idx_wpv_page (page_id, viewed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public static function record(string $path, ?int $pageId, string $referrerHost): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        self::ensureTables();
        $path = self::normalizePath($path);
        $referrerHost = mb_substr(strtolower(trim($referrerHost)), 0, 191);
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_website_pageviews (viewed_at, path, page_id, referrer_host)
             VALUES (NOW(), :path, :page_id, :ref)'
        );
        $stmt->execute([
            'path' => $path,
            'page_id' => $pageId !== null && $pageId > 0 ? $pageId : null,
            'ref' => $referrerHost,
        ]);
    }

    public static function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }
        $parsed = parse_url($path, PHP_URL_PATH);
        if (is_string($parsed) && $parsed !== '') {
            $path = $parsed;
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        $path = rawurldecode($path);
        $path = preg_replace('#/+#', '/', $path) ?: '/';

        return mb_substr($path, 0, 255);
    }

    /**
     * @return list<array{day: string, views: int}>
     */
    public static function viewsByDay(int $days = 30): array
    {
        self::ensureTables();
        $days = max(1, min(365, $days));
        $stmt = Database::pdo()->prepare(
            'SELECT DATE(viewed_at) AS day, COUNT(*) AS views
             FROM dg_website_pageviews
             WHERE viewed_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
             GROUP BY DATE(viewed_at)
             ORDER BY day ASC'
        );
        $stmt->bindValue('days', $days - 1, PDO::PARAM_INT);
        $stmt->execute();
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $map[(string) $row['day']] = (int) $row['views'];
        }

        $out = [];
        $start = new DateTimeImmutable('today');
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $start->modify('-' . $i . ' days')->format('Y-m-d');
            $out[] = ['day' => $day, 'views' => $map[$day] ?? 0];
        }

        return $out;
    }

    /**
     * @return list<array{path: string, views: int, page_id: ?int}>
     */
    public static function topPaths(int $days = 30, int $limit = 20): array
    {
        self::ensureTables();
        $days = max(1, min(365, $days));
        $limit = max(1, min(100, $limit));
        $stmt = Database::pdo()->prepare(
            'SELECT path, COUNT(*) AS views, MAX(page_id) AS page_id
             FROM dg_website_pageviews
             WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY path
             ORDER BY views DESC
             LIMIT ' . $limit
        );
        $stmt->bindValue('days', $days, PDO::PARAM_INT);
        $stmt->execute();
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[] = [
                'path' => (string) $row['path'],
                'views' => (int) $row['views'],
                'page_id' => isset($row['page_id']) && $row['page_id'] !== null ? (int) $row['page_id'] : null,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{host: string, views: int}>
     */
    public static function topReferrers(int $days = 30, int $limit = 15): array
    {
        self::ensureTables();
        $days = max(1, min(365, $days));
        $limit = max(1, min(50, $limit));
        $stmt = Database::pdo()->prepare(
            'SELECT referrer_host AS host, COUNT(*) AS views
             FROM dg_website_pageviews
             WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
               AND referrer_host <> \'\'
             GROUP BY referrer_host
             ORDER BY views DESC
             LIMIT ' . $limit
        );
        $stmt->bindValue('days', $days, PDO::PARAM_INT);
        $stmt->execute();
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[] = [
                'host' => (string) $row['host'],
                'views' => (int) $row['views'],
            ];
        }

        return $out;
    }

    /**
     * @return array{total: int, today: int, days7: int, days30: int}
     */
    public static function summary(): array
    {
        self::ensureTables();
        $pdo = Database::pdo();
        $total = (int) $pdo->query('SELECT COUNT(*) FROM dg_website_pageviews')->fetchColumn();
        $today = (int) $pdo->query(
            'SELECT COUNT(*) FROM dg_website_pageviews WHERE viewed_at >= CURDATE()'
        )->fetchColumn();
        $days7 = (int) $pdo->query(
            'SELECT COUNT(*) FROM dg_website_pageviews WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
        )->fetchColumn();
        $days30 = (int) $pdo->query(
            'SELECT COUNT(*) FROM dg_website_pageviews WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
        )->fetchColumn();

        return [
            'total' => $total,
            'today' => $today,
            'days7' => $days7,
            'days30' => $days30,
        ];
    }
}
