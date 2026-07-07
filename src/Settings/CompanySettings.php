<?php
declare(strict_types=1);

final class CompanySettings
{
  public const STORE_KEY = 'company';

  /** @return array<string, string> */
  public static function defaults(): array
  {
    return [
      'name' => '',
      'company_id' => '',
      'email' => '',
      'phone' => '',
      'website' => '',
      'street' => '',
      'postal' => '',
      'city' => '',
      'country' => 'DE',
      'tax_number' => '',
      'vat_id' => '',
    ];
  }

  /** @return array<string, string> */
  public static function config(): array
  {
    $cfg = SettingsStore::get(self::STORE_KEY, self::defaults());

    return array_map(static fn(mixed $v): string => trim((string) $v), $cfg);
  }

  /** @return array<string, string> */
  public static function forForm(): array
  {
    return self::config();
  }

  public static function displayName(): string
  {
    return trim(self::config()['name'] ?? '');
  }

  public static function numberRangeCompanyId(): string
  {
    $explicit = self::sanitizeCompanyId(self::config()['company_id'] ?? '');
    if ($explicit !== '') {
      return $explicit;
    }

    return self::deriveCompanyIdFromName(self::displayName());
  }

  public static function deriveCompanyIdFromName(string $name): string
  {
    $name = trim($name);
    if ($name === '') {
      return '';
    }

    $parts = preg_split('/\s+/u', $name) ?: [];
    $acronym = '';
    foreach ($parts as $part) {
      $token = strtoupper((string) preg_replace('/[^A-Za-z0-9ÄÖÜäöüß]/u', '', $part));
      if ($token === '') {
        continue;
      }
      $acronym .= strlen($token) <= 3 ? $token : $token[0];
    }

    return (string) preg_replace('/[^A-Z0-9_-]/', '', strtoupper($acronym));
  }

  public static function mailEmail(): string
  {
    $email = trim(self::config()['email'] ?? '');

    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
  }

  public static function isConfiguredForMail(): bool
  {
    return self::displayName() !== '' && self::mailEmail() !== '';
  }

  /** @param array<string, mixed> $input */
  public static function save(array $input): void
  {
    $data = [
      'name' => trim((string) ($input['name'] ?? '')),
      'company_id' => self::sanitizeCompanyId((string) ($input['company_id'] ?? '')),
      'email' => trim((string) ($input['email'] ?? '')),
      'phone' => trim((string) ($input['phone'] ?? '')),
      'website' => self::normalizeWebsite(trim((string) ($input['website'] ?? ''))),
      'street' => trim((string) ($input['street'] ?? '')),
      'postal' => trim((string) ($input['postal'] ?? '')),
      'city' => trim((string) ($input['city'] ?? '')),
      'country' => trim((string) ($input['country'] ?? 'DE')) ?: 'DE',
      'tax_number' => trim((string) ($input['tax_number'] ?? '')),
      'vat_id' => trim((string) ($input['vat_id'] ?? '')),
    ];

    if ($data['name'] === '') {
      throw new InvalidArgumentException('Firmenname ist erforderlich.');
    }
    if ($data['email'] === '' || filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
      throw new InvalidArgumentException('Gültige Firmen-E-Mail ist erforderlich.');
    }

    SettingsStore::set(self::STORE_KEY, $data);
  }

  private static function sanitizeCompanyId(string $value): string
  {
    $value = strtoupper(trim($value));
    if ($value === '') {
      return '';
    }

    return (string) preg_replace('/[^A-Z0-9_-]/', '', $value);
  }

  private static function normalizeWebsite(string $url): string
  {
    if ($url === '') {
      return '';
    }
    if (preg_match('#^https?://#i', $url) === 1) {
      return $url;
    }

    return 'https://' . $url;
  }
}
