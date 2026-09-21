<?php
declare(strict_types=1);

/**
 * Multi-Firma MF7b: Provision-Gates (Spec MF7a) vor KdvDeployService::provision.
 */
final class KdvProvisionGateService
{
    private const LOCK_KEY = 'kdv_provision_lock';
    private const LOCK_TTL = 1800; // 30 Min.

    /**
     * @param array<string, mixed> $customer KDV-Zeile
     * @param array{
     *   mode?: 'auto'|'manual',
     *   kas_login?: string,
     *   kas_pass?: string,
     *   confirm_dns?: bool
     * } $opts
     * @return array{
     *   ok: bool,
     *   failures: list<array{code: string, message: string}>,
     *   warnings: list<array{code: string, message: string}>
     * }
     */
    public static function evaluate(array $customer, array $opts = []): array
    {
        $mode = (($opts['mode'] ?? 'manual') === 'auto') ? 'auto' : 'manual';
        $failures = [];
        $warnings = [];

        $id = (int) ($customer['id'] ?? 0);
        $relation = (string) ($customer['firm_relation'] ?? 'standalone');
        $status = (string) ($customer['status'] ?? 'neu');
        $slot = (string) ($customer['firm_slot_status'] ?? 'active');
        $domain = FirmSwitcherService::normalizeHost((string) ($customer['domain'] ?? ''));
        $relatedId = (int) ($customer['related_customer_id'] ?? 0);

        // G1 — nur Auto-Modus (Nachfolger-Hook)
        if ($mode === 'auto') {
            if ($relation !== 'nachfolger' || $relatedId < 1) {
                $failures[] = [
                    'code' => 'G1',
                    'message' => 'Auto-Provision nur für Umfirmierungs-Nachfolger (firm_relation=nachfolger).',
                ];
            }
        }

        // G2
        if (!in_array($status, ['neu', 'dns_pending'], true)) {
            $failures[] = [
                'code' => 'G2',
                'message' => 'Status muss neu oder dns_pending sein (aktuell: ' . $status . ').',
            ];
        }

        // G3
        if ($slot !== 'active') {
            $failures[] = [
                'code' => 'G3',
                'message' => 'firm_slot_status muss active sein (aktuell: ' . $slot . ').',
            ];
        }

        // G4
        if ($domain === '' || !self::isValidFqdn($domain)) {
            $failures[] = [
                'code' => 'G4',
                'message' => 'Domain fehlt oder ist kein gültiger FQDN.',
            ];
        }

        $predecessor = null;
        if ($relatedId > 0) {
            $predecessor = KdvCustomerRepository::findById($relatedId);
        }

        if ($mode === 'auto' || $relation === 'nachfolger') {
            if ($predecessor !== null) {
                $predDomain = FirmSwitcherService::normalizeHost((string) ($predecessor['domain'] ?? ''));
                if ($domain !== '' && $predDomain !== '' && $domain === $predDomain) {
                    $failures[] = [
                        'code' => 'G4',
                        'message' => 'Nachfolger-Domain darf nicht der Vorgänger-Domain entsprechen.',
                    ];
                }
            }
        }

        // G5
        if ($domain !== '') {
            $other = KdvCustomerRepository::findByDomain($domain);
            if ($other !== null && (int) ($other['id'] ?? 0) !== $id) {
                $failures[] = [
                    'code' => 'G5',
                    'message' => 'Domain bereits bei anderem KDV-Kunden #' . (int) ($other['id'] ?? 0) . ' vergeben.',
                ];
            }
        }

        // G6 — Auto immer; Manual nur wenn Nachfolger
        if ($mode === 'auto' || $relation === 'nachfolger') {
            if ($relatedId < 1 || $predecessor === null) {
                $failures[] = [
                    'code' => 'G6',
                    'message' => 'Vorgänger (related_customer_id) fehlt oder nicht gefunden.',
                ];
            } else {
                $predRel = (string) ($predecessor['firm_relation'] ?? '');
                $predSlot = (string) ($predecessor['firm_slot_status'] ?? 'active');
                if ($predRel !== 'vorgaenger') {
                    $failures[] = [
                        'code' => 'G6',
                        'message' => 'Vorgänger muss firm_relation=vorgaenger haben (aktuell: ' . $predRel . ').',
                    ];
                }
                if (!in_array($predSlot, ['archive_readonly', 'closed'], true)) {
                    $failures[] = [
                        'code' => 'G6',
                        'message' => 'Vorgänger-Slot ist nicht archiviert/geschlossen (aktuell: ' . $predSlot . ').',
                    ];
                }
            }
        }

        // G7
        $kasLogin = trim((string) ($opts['kas_login'] ?? ''));
        $kasPass = (string) ($opts['kas_pass'] ?? '');
        if ($kasLogin === '') {
            $kasLogin = trim((string) ($customer['kas_login'] ?? ''));
        }
        if ($kasLogin === '' || $kasPass === '') {
            $failures[] = [
                'code' => 'G7',
                'message' => 'KAS-Zugangsdaten erforderlich (Login + Passwort).',
            ];
        }

        // G9
        if ($id > 0 && self::isLocked($id)) {
            $failures[] = [
                'code' => 'G9',
                'message' => 'Provision läuft bereits oder Lock aktiv (max. 30 Min.).',
            ];
        }

        // D1 Soft
        if ($domain !== '' && self::domainHasDns($domain)) {
            $warnings[] = [
                'code' => 'D1',
                'message' => 'Domain hat bereits DNS-Records (fremde Nutzung möglich).',
            ];
            if (empty($opts['confirm_dns'])) {
                $failures[] = [
                    'code' => 'D1',
                    'message' => 'DNS-Warnung bestätigen (confirm_dns), bevor provisioniert wird.',
                ];
            }
        }

        return [
            'ok' => $failures === [],
            'failures' => $failures,
            'warnings' => $warnings,
        ];
    }

    /**
     * Führt Gates + Lock + KdvDeployService::provision aus.
     *
     * @param array{
     *   mode?: 'auto'|'manual',
     *   kas_login?: string,
     *   kas_pass?: string,
     *   confirm_dns?: bool
     * } $opts
     * @return array{
     *   ok: bool,
     *   gate: array{ok: bool, failures: list<array{code: string, message: string}>, warnings: list<array{code: string, message: string}>},
     *   result: array<string, mixed>|null,
     *   message: string
     * }
     */
    public static function run(int $customerId, array $opts = []): array
    {
        $customer = KdvCustomerRepository::findById($customerId);
        if ($customer === null) {
            return [
                'ok' => false,
                'gate' => [
                    'ok' => false,
                    'failures' => [['code' => 'G0', 'message' => 'KDV-Kunde nicht gefunden.']],
                    'warnings' => [],
                ],
                'result' => null,
                'message' => 'KDV-Kunde nicht gefunden.',
            ];
        }

        $gate = self::evaluate($customer, $opts);
        if (!$gate['ok']) {
            $codes = array_map(static fn (array $f): string => $f['code'] . ': ' . $f['message'], $gate['failures']);

            return [
                'ok' => false,
                'gate' => $gate,
                'result' => null,
                'message' => 'Provision abgelehnt — ' . implode(' · ', $codes),
            ];
        }

        $kasLogin = trim((string) ($opts['kas_login'] ?? ''));
        if ($kasLogin === '') {
            $kasLogin = trim((string) ($customer['kas_login'] ?? ''));
        }
        $kasPass = (string) ($opts['kas_pass'] ?? '');

        if (!self::acquireLock($customerId)) {
            return [
                'ok' => false,
                'gate' => [
                    'ok' => false,
                    'failures' => [['code' => 'G9', 'message' => 'Lock konnte nicht gesetzt werden.']],
                    'warnings' => $gate['warnings'],
                ],
                'result' => null,
                'message' => 'Provision-Lock aktiv — bitte warten.',
            ];
        }

        try {
            $result = KdvDeployService::provision([
                'customer_id' => $customerId,
                'kas_login' => $kasLogin,
                'kas_pass' => $kasPass,
                'domain' => FirmSwitcherService::normalizeHost((string) ($customer['domain'] ?? '')),
                'company_name' => (string) ($customer['company_name'] ?? ''),
                'contact_email' => (string) ($customer['contact_email'] ?? ''),
                'contact_name' => (string) ($customer['contact_name'] ?? ''),
            ]);
            $ok = !empty($result['success']);
            // Bei Teilfehler Domain ok → dns_pending belassen/setzen (MF7a)
            if (!$ok) {
                self::markDnsPendingIfDomainStepOk($customerId, $result['steps'] ?? []);
            }

            return [
                'ok' => $ok,
                'gate' => $gate,
                'result' => $result,
                'message' => $ok
                    ? 'Provision erfolgreich.'
                    : 'Provision mit Fehlern — siehe Schritte (kein stilles Löschen).',
            ];
        } finally {
            self::releaseLock($customerId);
        }
    }

    public static function formatFailures(array $gate): string
    {
        $parts = [];
        foreach ($gate['failures'] ?? [] as $f) {
            if (!is_array($f)) {
                continue;
            }
            $parts[] = ($f['code'] ?? '?') . ': ' . ($f['message'] ?? '');
        }

        return implode(' · ', $parts);
    }

    private static function isValidFqdn(string $domain): bool
    {
        if ($domain === '' || !str_contains($domain, '.')) {
            return false;
        }
        if (strlen($domain) > 253) {
            return false;
        }

        return (bool) preg_match(
            '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i',
            $domain
        );
    }

    private static function domainHasDns(string $domain): bool
    {
        if (!function_exists('dns_get_record')) {
            return false;
        }
        try {
            $records = @dns_get_record($domain, DNS_A + DNS_AAAA + DNS_NS);
            return is_array($records) && $records !== [];
        } catch (Throwable) {
            return false;
        }
    }

    private static function isLocked(int $customerId): bool
    {
        if ($customerId < 1 || !class_exists('SettingsStore') || !Database::isConfigured()) {
            return false;
        }
        $store = SettingsStore::get(self::LOCK_KEY, []);
        $until = (int) ($store['locks'][(string) $customerId] ?? 0);

        return $until > time();
    }

    private static function acquireLock(int $customerId): bool
    {
        if ($customerId < 1 || !class_exists('SettingsStore') || !Database::isConfigured()) {
            return true;
        }
        if (self::isLocked($customerId)) {
            return false;
        }
        $store = SettingsStore::get(self::LOCK_KEY, ['locks' => []]);
        $locks = is_array($store['locks'] ?? null) ? $store['locks'] : [];
        $now = time();
        foreach ($locks as $cid => $until) {
            if ((int) $until < $now) {
                unset($locks[$cid]);
            }
        }
        $locks[(string) $customerId] = $now + self::LOCK_TTL;
        SettingsStore::set(self::LOCK_KEY, ['locks' => $locks]);

        return true;
    }

    private static function releaseLock(int $customerId): void
    {
        if ($customerId < 1 || !class_exists('SettingsStore') || !Database::isConfigured()) {
            return;
        }
        $store = SettingsStore::get(self::LOCK_KEY, ['locks' => []]);
        $locks = is_array($store['locks'] ?? null) ? $store['locks'] : [];
        unset($locks[(string) $customerId]);
        SettingsStore::set(self::LOCK_KEY, ['locks' => $locks]);
    }

    /**
     * @param list<array{step: string, ok: bool, detail: string}> $steps
     */
    private static function markDnsPendingIfDomainStepOk(int $customerId, array $steps): void
    {
        $domainOk = false;
        foreach ($steps as $s) {
            if (($s['step'] ?? '') === 'Domain anlegen' && !empty($s['ok'])) {
                $domainOk = true;
                break;
            }
        }
        if (!$domainOk) {
            return;
        }
        $existing = KdvCustomerRepository::findById($customerId);
        if ($existing === null) {
            return;
        }
        $status = (string) ($existing['status'] ?? 'neu');
        if (!in_array($status, ['neu', 'dns_pending'], true)) {
            return;
        }
        try {
            KdvCustomerRepository::save(array_merge($existing, ['status' => 'dns_pending']), $customerId);
        } catch (Throwable) {
            // best effort
        }
    }
}
