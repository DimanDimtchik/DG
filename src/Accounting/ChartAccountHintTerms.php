<?php
declare(strict_types=1);

/** Normalisierung und Abgleich gespeicherter Suchbegriffe (hints.search_terms). */
final class ChartAccountHintTerms
{
  /**
   * @param mixed $terms
   * @return list<string>
   */
  public static function normalizeList(mixed $terms): array
  {
    if (!is_array($terms)) {
      return [];
    }

    $normalized = [];
    foreach ($terms as $term) {
      if (!is_string($term) && !is_numeric($term)) {
        continue;
      }
      $value = mb_strtolower(trim((string) $term));
      if ($value === '' || mb_strlen($value) < 2) {
        continue;
      }
      $normalized[$value] = $value;
    }

    return array_values($normalized);
  }

  /** @param array<string, mixed> $row */
  public static function fromRow(array $row): array
  {
    $hints = [];
    $rawHints = $row['hints_json'] ?? '';
    if (is_string($rawHints) && $rawHints !== '') {
      $decoded = json_decode($rawHints, true);
      if (is_array($decoded)) {
        $hints = $decoded;
      }
    } elseif (is_array($row['hints'] ?? null)) {
      $hints = $row['hints'];
    }

    return self::normalizeList($hints['search_terms'] ?? []);
  }

  /**
   * @param list<string> $terms
   */
  public static function scoreQuery(string $query, array $terms): ?int
  {
    $needle = mb_strtolower(trim($query));
    if ($needle === '' || $terms === []) {
      return null;
    }

    $best = null;
    foreach ($terms as $term) {
      $termLower = mb_strtolower(trim($term));
      if ($termLower === '') {
        continue;
      }
      if ($termLower === $needle) {
        return 8;
      }
      if (str_starts_with($termLower, $needle)) {
        $best = min($best ?? 18, 18);
        continue;
      }
      if (mb_strlen($needle) >= 2 && str_starts_with($needle, $termLower)) {
        $best = min($best ?? 22, 22);
        continue;
      }
      if (mb_strlen($needle) >= 3 && str_contains($termLower, $needle)) {
        $best = min($best ?? 24, 24);
        continue;
      }
      if (ChartAccountSearchNormalizer::isReverseChargeQuery($query)
        && ChartAccountSearchNormalizer::isReverseChargeQuery($termLower)) {
        $best = min($best ?? 14, 14);
      }
    }

    return $best;
  }
}
