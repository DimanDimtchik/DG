<?php
declare(strict_types=1);

/**
 * Orchestriert Lizenz-Zuweisung und Sperre/Entsperre für KDV-SaaS-Kunden.
 */
final class KdvLicenseService
{
    /**
     * Erzeugt einen neuen Key am Lizenzserver und speichert ihn in der Akte.
     *
     * @return array{ok: bool, license_key?: string, error?: string}
     */
    public static function issueNew(int $customerId, ?string $validTo = null): array
    {
        $customer = KdvCustomerRepository::findById($customerId);
        if ($customer === null) {
            return ['ok' => false, 'error' => 'SaaS-Kunde nicht gefunden.'];
        }
        $plan = match ((string) ($customer['tariff'] ?? 'basic')) {
            'enterprise' => 'enterprise',
            'business' => 'business',
            default => 'starter',
        };
        $note = 'KDV #' . $customerId . ' ' . ($customer['company_name'] ?? '');
        $res = KdvLicenseClient::createLicense(
            (string) $customer['domain'],
            $plan,
            $validTo,
            $note
        );
        if (!$res['ok'] || ($res['license_key'] ?? '') === '') {
            return ['ok' => false, 'error' => $res['error'] ?? 'Lizenz konnte nicht angelegt werden.'];
        }
        KdvCustomerRepository::setLicense($customerId, $res['license_key'], $res['id'] ?? null);
        return ['ok' => true, 'license_key' => $res['license_key']];
    }

    /**
     * Bestehenden Key der Akte zuweisen (optional am Lizenzserver anlegen/finden).
     *
     * @return array{ok: bool, error?: string}
     */
    public static function assignExisting(int $customerId, string $licenseKey, bool $ensureOnServer = true): array
    {
        $customer = KdvCustomerRepository::findById($customerId);
        if ($customer === null) {
            return ['ok' => false, 'error' => 'SaaS-Kunde nicht gefunden.'];
        }
        $licenseKey = strtoupper(trim($licenseKey));
        if ($licenseKey === '' || !preg_match('/^GS-[A-Z0-9]{4}(-[A-Z0-9]{4}){3}$/', $licenseKey)) {
            return ['ok' => false, 'error' => 'Lizenzschlüssel ungültig (Format GS-XXXX-XXXX-XXXX-XXXX).'];
        }

        $remoteId = null;
        if ($ensureOnServer && KdvLicenseClient::isConfigured()) {
            $found = KdvLicenseClient::findLicenses(null, $licenseKey);
            if ($found['ok'] && !empty($found['licenses'][0])) {
                $remoteId = (int) ($found['licenses'][0]['id'] ?? 0);
            } else {
                $plan = match ((string) ($customer['tariff'] ?? 'basic')) {
                    'enterprise' => 'enterprise',
                    'business' => 'business',
                    default => 'starter',
                };
                $created = KdvLicenseClient::createLicense(
                    (string) $customer['domain'],
                    $plan,
                    null,
                    'KDV #' . $customerId . ' assigned',
                    $licenseKey
                );
                if (!$created['ok']) {
                    return ['ok' => false, 'error' => $created['error'] ?? 'Key konnte am Lizenzserver nicht hinterlegt werden.'];
                }
                $remoteId = $created['id'] ?? null;
            }
        }

        KdvCustomerRepository::setLicense($customerId, $licenseKey, $remoteId);
        return ['ok' => true];
    }

    /**
     * Sperrt SaaS-Kunde + Lizenz (optional).
     *
     * @return array{ok: bool, error?: string}
     */
    public static function suspend(int $customerId, string $blockReason, ?string $blockNote = null, bool $suspendLicense = true): array
    {
        if (!KdvBlockReasons::isValid($blockReason)) {
            return ['ok' => false, 'error' => 'Ungültiger Sperrgrund.'];
        }
        $customer = KdvCustomerRepository::findById($customerId);
        if ($customer === null) {
            return ['ok' => false, 'error' => 'SaaS-Kunde nicht gefunden.'];
        }

        if ($suspendLicense) {
            $key = trim((string) ($customer['license_key'] ?? ''));
            $remoteId = (int) ($customer['license_remote_id'] ?? 0);
            if ($key !== '' && KdvLicenseClient::isConfigured()) {
                $res = $remoteId > 0
                    ? KdvLicenseClient::setStatusById($remoteId, 'suspended')
                    : KdvLicenseClient::setStatusByKey($key, 'suspended');
                if (!$res['ok']) {
                    return ['ok' => false, 'error' => 'Lizenzsperre fehlgeschlagen: ' . ($res['error'] ?? '')];
                }
            }
        }

        KdvCustomerRepository::setBlocked($customerId, $blockReason, $blockNote);
        return ['ok' => true];
    }

    /**
     * Entsperrt SaaS-Kunde + Lizenz.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function unsuspend(int $customerId, bool $activateLicense = true): array
    {
        $customer = KdvCustomerRepository::findById($customerId);
        if ($customer === null) {
            return ['ok' => false, 'error' => 'SaaS-Kunde nicht gefunden.'];
        }

        if ($activateLicense) {
            $key = trim((string) ($customer['license_key'] ?? ''));
            $remoteId = (int) ($customer['license_remote_id'] ?? 0);
            if ($key !== '' && KdvLicenseClient::isConfigured()) {
                $res = $remoteId > 0
                    ? KdvLicenseClient::setStatusById($remoteId, 'active')
                    : KdvLicenseClient::setStatusByKey($key, 'active');
                if (!$res['ok']) {
                    return ['ok' => false, 'error' => 'Lizenz-Entsperrung fehlgeschlagen: ' . ($res['error'] ?? '')];
                }
            }
        }

        KdvCustomerRepository::clearBlocked($customerId);
        return ['ok' => true];
    }
}
