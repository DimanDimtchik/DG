<?php
declare(strict_types=1);

/**
 * Multi-Firma MF1: Firmen-Switcher (Variante A — Redirect zur Instanz-Domain).
 *
 * Quellen (Reihenfolge):
 * 1. KDV Phase 0 (dg_kdv_customers.org_id) — typisch Master
 * 2. config/firm-switcher.local.php — manuell auf Kundeninstanzen (Sync-Exclude)
 *
 * Kein SSO in MF1: Ziel ist /login auf der Zieldomain (gleiche Org-Benutzer später).
 */
final class FirmSwitcherService
{
    /**
     * @return array{
     *   enabled: bool,
     *   current_domain: string,
     *   current_label: string,
     *   firms: list<array{domain: string, label: string, status: string, is_current: bool, relation: string}>
     * }
     */
    public static function forCurrentRequest(): array
    {
        $currentHost = self::normalizeHost((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $firms = self::resolveFirms($currentHost);
        $currentLabel = CompanySettings::displayName();
        if ($currentLabel === '') {
            $currentLabel = (string) App::config('crm_name', 'CRM');
        }

        $out = [];
        $seen = [];
        foreach ($firms as $firm) {
            $domain = self::normalizeHost((string) ($firm['domain'] ?? ''));
            if ($domain === '' || isset($seen[$domain])) {
                continue;
            }
            $status = (string) ($firm['status'] ?? 'active');
            if ($status === 'closed') {
                continue;
            }
            $seen[$domain] = true;
            $isCurrent = self::hostsMatch($domain, $currentHost);
            $label = trim((string) ($firm['label'] ?? ''));
            if ($label === '') {
                $label = $domain;
            }
            if ($isCurrent) {
                $currentLabel = $label;
            }
            $out[] = [
                'domain' => $domain,
                'label' => $label,
                'status' => $status,
                'is_current' => $isCurrent,
                'relation' => (string) ($firm['relation'] ?? 'standalone'),
            ];
        }

        if ($out === []) {
            $out[] = [
                'domain' => $currentHost,
                'label' => $currentLabel,
                'status' => 'active',
                'is_current' => true,
                'relation' => 'standalone',
            ];
        } elseif (!self::listHasCurrent($out)) {
            array_unshift($out, [
                'domain' => $currentHost,
                'label' => $currentLabel,
                'status' => 'active',
                'is_current' => true,
                'relation' => 'standalone',
            ]);
        }

        return [
            'enabled' => count($out) >= 2,
            'current_domain' => $currentHost,
            'current_label' => $currentLabel,
            'firms' => $out,
        ];
    }

    /**
     * Erlaubt Redirect nur auf Domains aus der Switcher-Liste.
     */
    public static function redirectUrlForDomain(string $domain): ?string
    {
        $target = self::normalizeHost($domain);
        if ($target === '') {
            return null;
        }
        $state = self::forCurrentRequest();
        $allowed = false;
        foreach ($state['firms'] as $firm) {
            if (self::hostsMatch($firm['domain'], $target)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            return null;
        }
        if (self::hostsMatch($target, $state['current_domain'])) {
            return null;
        }

        return 'https://' . $target . '/login';
    }

    /**
     * Normalisierte Domains der Sibling-Liste inkl. aktueller Host (für SSO-Allowlist).
     *
     * @return list<string>
     */
    public static function siblingDomains(): array
    {
        $state = self::forCurrentRequest();
        $out = [];
        foreach ($state['firms'] as $firm) {
            $d = self::normalizeHost((string) ($firm['domain'] ?? ''));
            if ($d !== '' && !in_array($d, $out, true)) {
                $out[] = $d;
            }
        }
        $current = self::normalizeHost((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($current !== '' && !in_array($current, $out, true)) {
            $out[] = $current;
        }

        return $out;
    }

    /**
     * Redirect-URL inkl. optionalem SSO-Token (MF5).
     */
    public static function redirectUrlForDomainWithSso(string $domain, ?User $user): ?string
    {
        $base = self::redirectUrlForDomain($domain);
        if ($base === null) {
            return null;
        }
        if ($user === null || !FirmSsoService::isEnabled()) {
            return $base;
        }
        $withSso = FirmSsoService::issueRedirectUrl($user, $domain);

        return $withSso ?? $base;
    }

    /**
     * @return list<array{domain: string, label: string, status: string, relation: string}>
     */
    private static function resolveFirms(string $currentHost): array
    {
        $fromKdv = self::fromKdv($currentHost);
        if ($fromKdv !== []) {
            return $fromKdv;
        }

        return self::fromLocalConfig();
    }

    /**
     * @return list<array{domain: string, label: string, status: string, relation: string}>
     */
    private static function fromKdv(string $currentHost): array
    {
        if (!Database::isConfigured() || !KdvCustomerRepository::multiFirmaColumnsReady()) {
            return [];
        }
        $me = KdvCustomerRepository::findByDomain($currentHost);
        if ($me === null) {
            return [];
        }
        $orgId = (int) ($me['org_id'] ?? 0);
        if ($orgId < 1) {
            return [];
        }
        $rows = KdvCustomerRepository::listByOrgId($orgId);
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'domain' => (string) ($row['domain'] ?? ''),
                'label' => (string) ($row['company_name'] ?? ''),
                'status' => (string) ($row['firm_slot_status'] ?? 'active'),
                'relation' => (string) ($row['firm_relation'] ?? 'standalone'),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{domain: string, label: string, status: string, relation: string}>
     */
    private static function fromLocalConfig(): array
    {
        $file = DG_ROOT . '/config/firm-switcher.local.php';
        if (!is_readable($file)) {
            return [];
        }
        /** @var mixed $raw */
        $raw = require $file;
        if (!is_array($raw)) {
            return [];
        }
        $firms = $raw['firms'] ?? $raw;
        if (!is_array($firms)) {
            return [];
        }
        $out = [];
        foreach ($firms as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'domain' => (string) ($row['domain'] ?? ''),
                'label' => (string) ($row['company_name'] ?? $row['label'] ?? ''),
                'status' => (string) ($row['status'] ?? $row['firm_slot_status'] ?? 'active'),
                'relation' => (string) ($row['relation'] ?? $row['firm_relation'] ?? 'standalone'),
            ];
        }

        return $out;
    }

    public static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return '';
        }
        if (str_contains($host, '://')) {
            $parsed = parse_url($host, PHP_URL_HOST);
            $host = is_string($parsed) ? strtolower($parsed) : '';
        }
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }

    private static function hostsMatch(string $a, string $b): bool
    {
        return self::normalizeHost($a) !== '' && self::normalizeHost($a) === self::normalizeHost($b);
    }

    /**
     * @param list<array{is_current: bool}> $firms
     */
    private static function listHasCurrent(array $firms): bool
    {
        foreach ($firms as $firm) {
            if (!empty($firm['is_current'])) {
                return true;
            }
        }

        return false;
    }
}
