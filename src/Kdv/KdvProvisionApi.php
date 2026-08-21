<?php
declare(strict_types=1);

/**
 * REST-API for automated CRM provisioning.
 *
 * Called by shop.ganz-soft.de after a purchase is completed.
 *
 * POST /api/kdv/provision
 * Authorization: Bearer <API_KEY>
 * Content-Type: application/json
 *
 * Request body:
 * {
 *   "company_name": "Firma GmbH",
 *   "domain": "firma.de",
 *   "contact_name": "Max Mustermann",
 *   "contact_email": "max@firma.de",
 *   "contact_phone": "+49 123 456789",
 *   "tariff": "basic",           // basic|business|enterprise
 *   "billing_cycle": "monatlich", // monatlich|jaehrlich
 *   "monthly_price": 29.90
 * }
 *
 * Response: { "success": true, "customer_id": 1, "install_url": "...", "steps": [...] }
 */
final class KdvProvisionApi
{
    private const API_KEY_SETTING = 'api_key';

    /**
     * HTTP-Handler für POST /api/kdv/provision (JSON-Antwort, beendet mit exit).
     *
     * @return never
     */
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::error(405, 'Nur POST erlaubt.');
        }

        // Authenticate
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        $token = '';
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            $token = trim($m[1]);
        }

        $apiKey = self::getApiKey();
        if ($apiKey === '' || !hash_equals($apiKey, $token)) {
            self::error(401, 'Ungültiger API-Schlüssel.');
        }

        // Parse body
        $body = json_decode(file_get_contents('php://input') ?: '{}', true);
        if (!is_array($body)) {
            self::error(400, 'Ungültiger JSON-Body.');
        }

        $companyName  = trim((string) ($body['company_name'] ?? ''));
        $domain       = trim((string) ($body['domain'] ?? ''));
        $contactName  = trim((string) ($body['contact_name'] ?? ''));
        $contactEmail = trim((string) ($body['contact_email'] ?? ''));
        $contactPhone = trim((string) ($body['contact_phone'] ?? ''));
        $tariff       = (string) ($body['tariff'] ?? 'basic');
        $billingCycle = (string) ($body['billing_cycle'] ?? 'monatlich');
        $monthlyPrice = (float) ($body['monthly_price'] ?? 0);

        if ($companyName === '') self::error(422, 'company_name ist erforderlich.');
        if ($domain === '')     self::error(422, 'domain ist erforderlich.');
        if ($contactEmail === '' || !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            self::error(422, 'Gültige contact_email ist erforderlich.');
        }

        // 1. Create customer in KDV
        try {
            $customerId = KdvCustomerRepository::save([
                'company_name'  => $companyName,
                'domain'        => $domain,
                'contact_name'  => $contactName,
                'contact_email' => $contactEmail,
                'contact_phone' => $contactPhone,
                'tariff'        => $tariff,
                'billing_cycle' => $billingCycle,
                'monthly_price' => $monthlyPrice,
                'status'        => 'neu',
                'contract_start'=> date('Y-m-d'),
            ]);
        } catch (Throwable $e) {
            self::error(500, 'Kunde konnte nicht angelegt werden: ' . $e->getMessage());
        }

        // 2. Read KAS credentials from settings
        $kasLogin = KdvConfig::get('kas_login', '');
        $kasPass  = KdvConfig::get('kas_pass', '');

        if ($kasLogin === '' || $kasPass === '') {
            echo json_encode([
                'success'     => true,
                'customer_id' => $customerId,
                'install_url' => null,
                'message'     => 'Kunde angelegt, aber KAS-Zugangsdaten fehlen. Bereitstellung muss manuell über KDV erfolgen.',
                'steps'       => [],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 3. Provision
        $result = KdvDeployService::provision([
            'customer_id'   => $customerId,
            'kas_login'     => (string) $kasLogin,
            'kas_pass'      => (string) $kasPass,
            'domain'        => $domain,
            'company_name'  => $companyName,
            'contact_email' => $contactEmail,
            'contact_name'  => $contactName,
        ]);

        echo json_encode([
            'success'           => $result['success'],
            'customer_id'       => $customerId,
            'install_url'       => $result['install_url'] ?? null,
            'mailbox_email'     => $result['mailbox_email'] ?? null,
            'mailbox_password'  => $result['mailbox_password'] ?? null,
            'steps'             => $result['steps'],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Generate a new API key and store it.
     *
     * @return string 64-stelliger Hex-Schlüssel.
     */
    public static function generateApiKey(): string
    {
        $key = bin2hex(random_bytes(32));
        KdvConfig::set(self::API_KEY_SETTING, $key);
        return $key;
    }

    /** Gespeicherter Bearer-Token für Shop-Integration. */
    public static function getApiKey(): string
    {
        return KdvConfig::get(self::API_KEY_SETTING, '');
    }

    /** Ob ein API-Schlüssel konfiguriert ist. */
    public static function hasApiKey(): bool
    {
        return self::getApiKey() !== '';
    }

    /** @return never */
    private static function error(int $code, string $message): void
    {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
