<?php

declare(strict_types=1);

final class ShopCheckout
{
    public const BILLING_MONTHLY = 'monatlich';
    public const BILLING_YEARLY = 'jaehrlich';

    /**
     * @return array{ok: bool, errors: list<string>, data: array<string, string>}
     */
    public static function validate(array $input): array
    {
        $errors = [];
        $rawDomain = trim((string) ($input['domain'] ?? ''));
        $normalized = $rawDomain !== '' ? self::normalizeDomain($rawDomain) : '';

        $data = [
            'plan' => trim((string) ($input['plan'] ?? '')),
            'billing_cycle' => trim((string) ($input['billing_cycle'] ?? self::BILLING_MONTHLY)),
            'company_name' => trim((string) ($input['company_name'] ?? '')),
            'domain' => $normalized,
            'domain_raw' => $rawDomain,
            'contact_name' => trim((string) ($input['contact_name'] ?? '')),
            'contact_email' => trim((string) ($input['contact_email'] ?? '')),
            'contact_phone' => trim((string) ($input['contact_phone'] ?? '')),
            'privacy' => !empty($input['privacy']) ? '1' : '',
        ];

        if (ShopPlans::get($data['plan']) === null) {
            $errors[] = 'Bitte einen gültigen Tarif wählen.';
        }
        if (!in_array($data['billing_cycle'], [self::BILLING_MONTHLY, self::BILLING_YEARLY], true)) {
            $errors[] = 'Ungültige Laufzeit.';
        }
        if ($data['company_name'] === '') {
            $errors[] = 'Bitte den Firmennamen eintragen.';
        }
        if ($rawDomain !== '' && ($normalized === '' || !self::isValidDomain($normalized))) {
            $errors[] = 'Die Domain ist so nicht erkennbar. Erlaubt sind z. B. meine-firma.de, crm.meine-firma.de oder https://meine-firma.de.';
        }
        if ($data['contact_name'] === '') {
            $errors[] = 'Bitte den Namen des Ansprechpartners eintragen.';
        }
        if ($data['contact_email'] === '' || !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Bitte eine gültige E-Mail-Adresse eintragen.';
        }
        if ($data['privacy'] !== '1') {
            $errors[] = 'Bitte bestätigen Sie die Datenschutzerklärung.';
        }

        return ['ok' => $errors === [], 'errors' => $errors, 'data' => $data];
    }

    public static function normalizeDomain(string $input): string
    {
        $input = trim($input);
        if ($input === '') {
            return '';
        }
        $input = preg_replace('/\s+/', '', $input) ?? $input;
        if (!preg_match('#^https?://#i', $input)) {
            $input = 'https://' . $input;
        }
        $parts = parse_url($input);
        $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';
        $host = strtolower($host);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }

    public static function isValidDomain(string $domain): bool
    {
        $domain = strtolower(trim($domain));
        if ($domain === '' || str_contains($domain, '/') || str_contains($domain, ' ')) {
            return false;
        }

        return (bool) preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain);
    }
}
