<?php
declare(strict_types=1);

/**
 * Entscheidet, ob ein Konto in der Belegsuche vorgeschlagen werden darf.
 *
 * Direkt bebuchbar = typische Zielkonten für Rechnungen/Belege (Lexoffice-Logik).
 * Nicht bebuchbar = AfA, reine Steuer-/Verrechnungskonten, Bilanz-Vorgänge, Statistik.
 */
final class ChartAccountBookingEligibility
{
  /** Explizit von der Textsuche ausgeschlossen. */
  private const EXCLUDED_NUMBERS = [
    '4832', '4842', '4852', '4862', '6222',
    '1584', '1585', '1586', '1784', '1785', '1786',
    '1570', '1571', '1576', '1577', '1770', '1771', '1776', '1777',
    '8611', '8921', '8924', '2310', '2315', '2316', '2317',
    '9000', '9001', '9008', '9009',
  ];

  /** Kontonummern, die trotz Aktiva/Passiva-Einordnung bebuchbar sind. */
  private const ALLOWED_BALANCE_SHEET = [
    '0320', '0520', '1000', '1200', '1210', '1400', '1600', '1800',
  ];

  /** @var list<string> */
  private const EXCLUDED_NAME_FRAGMENTS = [
    'abschreib',
    'afa ',
    ' vorsteuer',
    'vorsteuer ',
    'umsatzsteuer',
    ' ust ',
    'ausgleichsposten',
    'sonderposten',
    'rückstellung',
    'rueckstellung',
    'vortrag',
    'statistik',
    'verrechnet',
    'sachbezug',
    'offene posten aus',
    'anlagenabgang',
    'durchlaufende',
    'interimskonto',
    'neutral',
    'gewinnrücklage',
    'kapitalrücklage',
    'einlagen',
    'entnahme',
    'verrechnung',
    'latente steuer',
    'rechnungsabgrenzung',
    'gesamthänderisch',
    'sammelposten',
    'sonderbetriebseinnahmen',
    'statistisches konto',
  ];

  public static function isPotentiallySearchableNumber(string $skrType, string $accountNumber): bool
  {
    $number = str_pad(preg_replace('/\D/', '', $accountNumber) ?? '', 4, '0', STR_PAD_LEFT);
    if ($number === '' || in_array($number, self::EXCLUDED_NUMBERS, true)) {
      return false;
    }

    if (ChartOfAccountsSettings::sanitizeSkrType($skrType) === 'skr03') {
      $prefix3 = substr($number, 0, 3);
      if ($prefix3 === '483' || $prefix3 === '484' || $prefix3 === '485') {
        return false;
      }
      if (str_starts_with($number, '9')) {
        return false;
      }
    }

    return true;
  }

  public static function isSearchable(
    string $skrType,
    string $accountNumber,
    string $name,
    string $section
  ): bool {
    $number = str_pad(preg_replace('/\D/', '', $accountNumber) ?? '', 4, '0', STR_PAD_LEFT);
    if ($number === '') {
      return false;
    }

    if (in_array($number, self::EXCLUDED_NUMBERS, true)) {
      return false;
    }

    if (in_array($number, self::ALLOWED_BALANCE_SHEET, true)) {
      return true;
    }

    $nameLower = mb_strtolower(trim($name));
    foreach (self::EXCLUDED_NAME_FRAGMENTS as $fragment) {
      if ($fragment !== '' && str_contains($nameLower, $fragment)) {
        return false;
      }
    }

    // SKR03: 483x oft AfA (außer 4800–4829 laufende Aufwendungen)
    if (ChartOfAccountsSettings::sanitizeSkrType($skrType) === 'skr03') {
      $prefix3 = substr($number, 0, 3);
      if ($prefix3 === '483' || $prefix3 === '484' || $prefix3 === '485') {
        return false;
      }
      if (str_starts_with($number, '9')) {
        return false;
      }
    }

    return match ($section) {
      'aufwand', 'ertrag' => true,
      'aktiva', 'passiva' => false,
      default => false,
    };
  }

  /** @return list<string> */
  public static function allowedSectionsForVoucherType(string $voucherType): array
  {
    $type = strtolower(trim($voucherType));
    if ($type === 'receipt' || $type === 'invoice') {
      $type = 'expense';
    }

    return match ($type) {
      'income', 'income_reduction', 'credit' => ['ertrag'],
      'expense', 'expense_reduction' => ['aufwand'],
      default => ['aufwand', 'ertrag'],
    };
  }

  public static function isSearchableForVoucherType(
    string $voucherType,
    string $skrType,
    string $accountNumber,
    string $name,
    string $section
  ): bool {
    if (!self::isSearchable($skrType, $accountNumber, $name, $section)) {
      return false;
    }

    return in_array($section, self::allowedSectionsForVoucherType($voucherType), true);
  }

  /** @param array<string, mixed> $row */
  public static function isSearchableRowForVoucherType(array $row, string $voucherType): bool
  {
    if (trim($voucherType) === '') {
      return self::isSearchableRow($row);
    }

    if (!self::isSearchableForVoucherType(
      $voucherType,
      (string) ($row['skr_type'] ?? 'skr03'),
      (string) ($row['account_number'] ?? ''),
      (string) ($row['name'] ?? ''),
      (string) ($row['section'] ?? ''),
    )) {
      return false;
    }

    if (in_array(self::sanitizeVoucherTypeForFilter($voucherType), ['income', 'income_reduction', 'credit'], true)) {
      $nameLower = mb_strtolower((string) ($row['name'] ?? ''));
      foreach (['sonderbetriebseinnahmen', 'statistisches konto'] as $fragment) {
        if (str_contains($nameLower, $fragment)) {
          return false;
        }
      }
    }

    return true;
  }

  private static function sanitizeVoucherTypeForFilter(string $voucherType): string
  {
    $type = strtolower(trim($voucherType));
    if ($type === 'receipt' || $type === 'invoice') {
      return 'expense';
    }

    return in_array($type, ['income', 'income_reduction', 'expense', 'expense_reduction', 'credit'], true)
      ? $type
      : 'expense';
  }

  public static function matchesVoucherTaxRate(string $accountName, int $taxRate): bool
  {
    $markers = self::taxMarkersFromAccountName($accountName);

    return match ($taxRate) {
      19 => $markers['has19'],
      7 => $markers['has7'] && !$markers['has19'],
      0 => ($markers['has0'] || $markers['isTaxFree']) && !$markers['has19'] && !$markers['has7'],
      default => true,
    };
  }

  public static function inferTaxRateFromAccountName(string $accountName): ?int
  {
    $markers = self::taxMarkersFromAccountName($accountName);
    if ($markers['has19']) {
      return 19;
    }
    if ($markers['has7']) {
      return 7;
    }
    if ($markers['has0'] || $markers['isTaxFree']) {
      return 0;
    }

    return null;
  }

  /** @return array{has19: bool, has7: bool, has0: bool, isTaxFree: bool} */
  private static function taxMarkersFromAccountName(string $accountName): array
  {
    $name = mb_strtolower(trim($accountName));

    return [
      'has19' => str_contains($name, '19 %') || str_contains($name, '19%'),
      'has7' => preg_match('/(?<!\d)7\s*%|7%/u', $name) === 1,
      'has0' => str_contains($name, '0 %') || str_contains($name, '0%'),
      'isTaxFree' => str_contains($name, 'steuerfrei') || str_contains($name, 'ohne ust'),
    ];
  }

  /** @param array<string, mixed> $row */
  public static function isSearchableRow(array $row): bool
  {
    return self::isSearchable(
      (string) ($row['skr_type'] ?? 'skr03'),
      (string) ($row['account_number'] ?? ''),
      (string) ($row['name'] ?? ''),
      (string) ($row['section'] ?? ''),
    );
  }
}
