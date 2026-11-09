<?php

declare(strict_types=1);

/**
 * Einheitliche Listen-Pagination für Ops-Hauptlisten (Media-Muster).
 */
final class ListPagination
{
    public const OPTIONS = ['10', '50', '100', 'all'];

    public const DEFAULT = '10';

    /** Steuerung (Dropdown/Nav) erst ab dieser Gesamtzahl. */
    public const UI_MIN_TOTAL = 10;

    public static function parsePerPageKey(?string $raw): string
    {
        $key = strtolower(trim((string) $raw));
        if (!in_array($key, self::OPTIONS, true)) {
            return self::DEFAULT;
        }

        return $key;
    }

    public static function parsePage(?string $raw): int
    {
        return max(1, (int) $raw);
    }

    /**
     * @return array{
     *   page: int,
     *   per_page: int,
     *   total_pages: int,
     *   per_page_key: string,
     *   offset: int,
     *   limit: int,
     *   show_all: bool
     * }
     */
    public static function sliceMeta(int $total, int $page, string $perPageKey): array
    {
        $total = max(0, $total);
        $perPageKey = self::parsePerPageKey($perPageKey);
        $page = max(1, $page);
        $showAll = $perPageKey === 'all';
        $perPage = $showAll ? max(1, $total) : (int) $perPageKey;
        $totalPages = $showAll ? 1 : max(1, (int) ceil($total / max(1, $perPage)));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = $showAll ? 0 : ($page - 1) * $perPage;
        $limit = $showAll ? max(1, $total) : $perPage;

        return [
            'page' => $page,
            'per_page' => $showAll ? $total : $perPage,
            'total_pages' => $totalPages,
            'per_page_key' => $perPageKey,
            'offset' => $offset,
            'limit' => max(1, $limit),
            'show_all' => $showAll,
        ];
    }

    /**
     * @param list<mixed> $items
     * @return array{items: list<mixed>, total: int, page: int, per_page: int, total_pages: int, per_page_key: string}
     */
    public static function result(array $items, int $total, array $meta): array
    {
        return [
            'items' => $items,
            'total' => $total,
            'page' => (int) ($meta['page'] ?? 1),
            'per_page' => (int) ($meta['per_page'] ?? 10),
            'total_pages' => (int) ($meta['total_pages'] ?? 1),
            'per_page_key' => (string) ($meta['per_page_key'] ?? self::DEFAULT),
        ];
    }

    /**
     * @return array{items: list<mixed>, total: int, page: int, per_page: int, total_pages: int, per_page_key: string}
     */
    public static function emptyResult(?string $perPageKey = null): array
    {
        $key = self::parsePerPageKey($perPageKey);

        return [
            'items' => [],
            'total' => 0,
            'page' => 1,
            'per_page' => $key === 'all' ? 0 : (int) $key,
            'total_pages' => 1,
            'per_page_key' => $key,
        ];
    }

    public static function showControls(int $total): bool
    {
        return $total >= self::UI_MIN_TOTAL;
    }

    /**
     * In-Memory-Slice für Repos ohne SQL-LIMIT (kleine/mittlere Listen).
     *
     * @param list<mixed> $all
     * @return array{items: list<mixed>, total: int, page: int, per_page: int, total_pages: int, per_page_key: string}
     */
    public static function paginateArray(array $all, int $page = 1, string $perPageKey = self::DEFAULT): array
    {
        $total = count($all);
        $meta = self::sliceMeta($total, $page, $perPageKey);
        if ($total === 0 || $meta['show_all']) {
            return self::result($all, $total, $meta);
        }
        $slice = array_slice($all, $meta['offset'], $meta['limit']);

        return self::result(array_values($slice), $total, $meta);
    }
}
