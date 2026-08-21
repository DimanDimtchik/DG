<?php
declare(strict_types=1);

/** Normalisiert Paragraphen- und Reverse-Charge-Suchbegriffe (Lexoffice: „§13“, „13b“, „Reverse Charge“). */
final class ChartAccountSearchNormalizer
{
  /**
   * normalizeLegal
   * @param string $text
   * @return string
   */
  public static function normalizeLegal(string $text): string
  {
    $lower = mb_strtolower(trim($text));
    if ($lower === '') {
      return '';
    }

    $lower = str_replace(["\u{00A7}", 'paragraph'], '§', $lower);
    $lower = preg_replace('/§\s*/u', '§', $lower) ?? $lower;
    $lower = preg_replace('/\s+/u', '', $lower) ?? $lower;

    return $lower;
  }

  /**
   * isReverseChargeQuery
   * @param string $query
   * @return bool
   */
  public static function isReverseChargeQuery(string $query): bool
  {
    $raw = mb_strtolower(trim($query));
    if ($raw === '') {
      return false;
    }

    if (preg_match('/reverse\s*charge|steuerschuldnerschaft|steuerschuld/iu', $raw) === 1) {
      return true;
    }

    $normalized = self::normalizeLegal($query);
    if ($normalized === '') {
      return false;
    }

    if (str_contains($normalized, '13b') || str_contains($normalized, '13a')) {
      return true;
    }

    if (preg_match('/§13/u', $normalized) === 1) {
      return true;
    }

    return str_contains($raw, '§') && preg_match('/\b13\b/u', $raw) === 1;
  }

  /**
   * nameMatchesQuery
   * @param string $query
   * @param string $name Name
   * @return bool
   */
  public static function nameMatchesQuery(string $query, string $name): bool
  {
    $needle = self::normalizeLegal($query);
    if ($needle === '' || mb_strlen($needle) < 2) {
      return false;
    }

    $haystack = self::normalizeLegal($name);

    return str_contains($haystack, $needle);
  }

    /**
   * sqlNamePatterns
   * @param string $query
   * @return list<string>
   */
  public static function sqlNamePatterns(string $query): array
  {
    $patterns = [];
    $trimmed = mb_strtolower(trim($query));
    if ($trimmed !== '') {
      $patterns[] = '%' . $trimmed . '%';
    }

    $normalized = self::normalizeLegal($query);
    if ($normalized !== '' && $normalized !== $trimmed) {
      $patterns[] = '%' . $normalized . '%';
    }

    if (self::isReverseChargeQuery($query)) {
      foreach (['13b', '§ 13b', '§13b', 'reverse charge'] as $fragment) {
        $patterns[] = '%' . mb_strtolower($fragment) . '%';
      }
    }

    return array_values(array_unique($patterns));
  }
}
