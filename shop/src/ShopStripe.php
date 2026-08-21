<?php

declare(strict_types=1);

/**
 * Stripe Checkout + Webhook (ohne Composer, per Stripe REST API).
 * Secrets: config/stripe.local.php (siehe stripe.example.php).
 */
final class ShopStripe
{
    /** @return array<string, mixed> */
    public static function config(): array
    {
        static $cfg;
        if ($cfg !== null) {
            return $cfg;
        }
        $example = SHOP_ROOT . '/config/stripe.example.php';
        $local = SHOP_ROOT . '/config/stripe.local.php';
        $base = is_readable($example) ? (require $example) : [];
        $override = is_readable($local) ? (require $local) : [];
        $cfg = array_merge(is_array($base) ? $base : [], is_array($override) ? $override : []);

        return $cfg;
    }

    public static function isConfigured(): bool
    {
        $c = self::config();
        $secret = (string) ($c['secret_key'] ?? '');

        return $secret !== '' && !str_contains($secret, 'CHANGE_ME') && str_starts_with($secret, 'sk_');
    }

    /**
     * @param array<string, mixed> $draft  shop_checkout_draft
     * @return array{ok: bool, url?: string, error?: string, session_id?: string}
     */
    public static function createCheckoutSession(array $draft): array
    {
        if (!self::isConfigured()) {
            return ['ok' => false, 'error' => 'Stripe ist noch nicht konfiguriert (stripe.local.php).'];
        }

        $plan = $draft['plan'] ?? null;
        if (!is_array($plan) || empty($plan['id'])) {
            return ['ok' => false, 'error' => 'Kein Tarif in der Bestellung.'];
        }

        $c = self::config();
        $isYearly = ($draft['billing_cycle'] ?? '') === ShopCheckout::BILLING_YEARLY;
        $gross = $isYearly ? (float) ($draft['gross'] ?? 0) : (float) ($draft['gross'] ?? 0);
        if ($gross <= 0) {
            return ['ok' => false, 'error' => 'Ungültiger Betrag.'];
        }

        $baseUrl = rtrim((string) ($c['public_base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            $baseUrl = self::detectBaseUrl();
        }

        $payload = $draft['kdv_payload'] ?? [];
        $interval = $isYearly ? 'year' : 'month';
        $unitAmount = (int) round($gross * 100); // Stripe expects cents, brutto

        $params = [
            'mode' => 'subscription',
            'success_url' => $baseUrl . '/checkout/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $baseUrl . '/checkout/cancel',
            'customer_email' => (string) ($payload['contact_email'] ?? ''),
            'client_reference_id' => substr(hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE) ?: ''), 0, 64),
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => 'eur',
            'line_items[0][price_data][unit_amount]' => $unitAmount,
            'line_items[0][price_data][recurring][interval]' => $interval,
            'line_items[0][price_data][product_data][name]' => 'DG CRM ' . (string) ($plan['name'] ?? $plan['id']),
            'line_items[0][price_data][product_data][description]' => $isYearly
                ? 'Jahresabo (11 × Monatspreis, 1 Monat gratis), inkl. 19% MwSt.'
                : 'Monatsabo, inkl. 19% MwSt.',
            'metadata[plan]' => (string) $plan['id'],
            'metadata[billing_cycle]' => (string) ($draft['billing_cycle'] ?? ''),
            'metadata[company_name]' => (string) ($payload['company_name'] ?? ''),
            'metadata[domain]' => (string) ($payload['domain'] ?? ''),
            'metadata[contact_name]' => (string) ($payload['contact_name'] ?? ''),
            'metadata[contact_email]' => (string) ($payload['contact_email'] ?? ''),
            'metadata[contact_phone]' => (string) ($payload['contact_phone'] ?? ''),
            'metadata[tariff]' => (string) ($payload['tariff'] ?? ''),
            'metadata[monthly_price]' => (string) ($payload['monthly_price'] ?? ''),
            'metadata[business_profile]' => (string) ($payload['business_profile'] ?? ''),
            'subscription_data[metadata][plan]' => (string) $plan['id'],
            'subscription_data[metadata][tariff]' => (string) ($payload['tariff'] ?? ''),
            'locale' => 'de',
            'allow_promotion_codes' => 'false',
        ];

        $res = self::api('POST', 'checkout/sessions', $params);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error'] ?? 'Stripe-Sitzung fehlgeschlagen.'];
        }

        $url = (string) ($res['data']['url'] ?? '');
        $id = (string) ($res['data']['id'] ?? '');
        if ($url === '') {
            return ['ok' => false, 'error' => 'Stripe lieferte keine Checkout-URL.'];
        }

        return ['ok' => true, 'url' => $url, 'session_id' => $id];
    }

    /**
     * @return array{ok: bool, data?: array<string, mixed>, error?: string}
     */
    public static function retrieveSession(string $sessionId): array
    {
        if ($sessionId === '' || !preg_match('/^cs_[A-Za-z0-9_]+$/', $sessionId)) {
            return ['ok' => false, 'error' => 'Ungültige Session-ID.'];
        }

        return self::api('GET', 'checkout/sessions/' . rawurlencode($sessionId));
    }

    /**
     * Stripe Webhook: checkout.session.completed → KDV-Provision.
     */
    public static function handleWebhook(): void
    {
        $payload = file_get_contents('php://input') ?: '';
        $sigHeader = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
        $c = self::config();
        $secret = (string) ($c['webhook_secret'] ?? '');

        if ($secret !== '' && !str_contains($secret, 'CHANGE_ME')) {
            if (!self::verifySignature($payload, $sigHeader, $secret)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'invalid signature']);
                return;
            }
        }

        $event = json_decode($payload, true);
        if (!is_array($event)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'invalid json']);
            return;
        }

        $type = (string) ($event['type'] ?? '');
        if ($type === 'checkout.session.completed') {
            $session = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];
            $result = self::provisionFromSession($session);
            self::logEvent('checkout.session.completed', $session['id'] ?? '', $result);
            if (!$result['ok']) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['error' => $result['error'] ?? 'provision failed']);
                return;
            }
        }

        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['received' => true]);
    }

    /**
     * @param array<string, mixed> $session
     * @return array{ok: bool, error?: string, response?: array<string, mixed>}
     */
    public static function provisionFromSession(array $session): array
    {
        $meta = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $sessionId = (string) ($session['id'] ?? '');

        if ($sessionId !== '' && self::alreadyProvisioned($sessionId)) {
            return ['ok' => true, 'response' => ['idempotent' => true]];
        }

        $body = [
            'company_name' => (string) ($meta['company_name'] ?? ''),
            'domain' => (string) ($meta['domain'] ?? ''),
            'contact_name' => (string) ($meta['contact_name'] ?? ''),
            'contact_email' => (string) ($meta['contact_email'] ?? ($session['customer_details']['email'] ?? $session['customer_email'] ?? '')),
            'contact_phone' => (string) ($meta['contact_phone'] ?? ''),
            'tariff' => (string) ($meta['tariff'] ?? 'basic'),
            'billing_cycle' => (string) ($meta['billing_cycle'] ?? 'monatlich'),
            'monthly_price' => (float) ($meta['monthly_price'] ?? 0),
            'stripe_session_id' => $sessionId,
            'stripe_customer' => (string) ($session['customer'] ?? ''),
            'stripe_subscription' => (string) ($session['subscription'] ?? ''),
            'business_profile' => (string) ($meta['business_profile'] ?? ''),
        ];

        if ($body['company_name'] === '' || $body['contact_email'] === '') {
            return ['ok' => false, 'error' => 'Unvollständige Metadaten für Provision.'];
        }

        $provision = self::callKdvProvision($body);
        if ($provision['ok'] && $sessionId !== '') {
            self::markProvisioned($sessionId, $provision['response'] ?? []);
        }

        return $provision;
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, error?: string, response?: array<string, mixed>}
     */
    private static function callKdvProvision(array $body): array
    {
        $url = (string) ShopApp::config('kdv_provision_url');
        $apiKey = (string) (self::config()['kdv_api_key'] ?? ShopApp::config('kdv_api_key') ?? '');
        if ($url === '') {
            return ['ok' => false, 'error' => 'kdv_provision_url fehlt.'];
        }
        if ($apiKey === '' || str_contains($apiKey, 'CHANGE_ME')) {
            return ['ok' => false, 'error' => 'KDV API-Key fehlt (stripe.local.php → kdv_api_key).'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 120,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'error' => 'KDV-API: ' . $err];
        }
        $json = json_decode((string) $raw, true);
        if ($code >= 200 && $code < 300) {
            return ['ok' => true, 'response' => is_array($json) ? $json : ['raw' => $raw]];
        }

        $msg = is_array($json) ? (string) ($json['message'] ?? $json['error'] ?? $raw) : (string) $raw;

        return ['ok' => false, 'error' => 'KDV-API HTTP ' . $code . ': ' . $msg];
    }

    private static function alreadyProvisioned(string $sessionId): bool
    {
        $path = self::provisionLogPath();
        if (!is_readable($path)) {
            return false;
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) && isset($data[$sessionId]);
    }

    /** @param array<string, mixed> $response */
    private static function markProvisioned(string $sessionId, array $response): void
    {
        $path = self::provisionLogPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $data = [];
        if (is_readable($path)) {
            $existing = json_decode((string) file_get_contents($path), true);
            if (is_array($existing)) {
                $data = $existing;
            }
        }
        $data[$sessionId] = [
            'at' => date('c'),
            'response' => $response,
        ];
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private static function provisionLogPath(): string
    {
        return SHOP_ROOT . '/storage/stripe-provisions.json';
    }

    /** @param array<string, mixed>|string $detail */
    private static function logEvent(string $type, string $id, array $detail): void
    {
        $dir = SHOP_ROOT . '/storage';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $line = json_encode([
            'at' => date('c'),
            'type' => $type,
            'id' => $id,
            'detail' => $detail,
        ], JSON_UNESCAPED_UNICODE) . "\n";
        @file_put_contents($dir . '/stripe-webhook.log', $line, FILE_APPEND);
    }

    private static function verifySignature(string $payload, string $header, string $secret): bool
    {
        if ($header === '') {
            return false;
        }
        $parts = [];
        foreach (explode(',', $header) as $item) {
            [$k, $v] = array_pad(explode('=', trim($item), 2), 2, '');
            $parts[$k] = $v;
        }
        $timestamp = (string) ($parts['t'] ?? '');
        $signature = (string) ($parts['v1'] ?? '');
        if ($timestamp === '' || $signature === '') {
            return false;
        }
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }
        $signed = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        return hash_equals($signed, $signature);
    }

    /**
     * @param array<string, scalar> $params
     * @return array{ok: bool, data?: array<string, mixed>, error?: string}
     */
    private static function api(string $method, string $path, array $params = []): array
    {
        $secret = (string) (self::config()['secret_key'] ?? '');
        $url = 'https://api.stripe.com/v1/' . ltrim($path, '/');
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $secret,
            'Stripe-Version: 2024-11-20.acacia',
        ];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
        } elseif ($method === 'GET' && $params !== []) {
            $url .= '?' . http_build_query($params);
            curl_setopt($ch, CURLOPT_URL, $url);
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'error' => $err];
        }
        $json = json_decode((string) $raw, true);
        if ($code >= 200 && $code < 300 && is_array($json)) {
            return ['ok' => true, 'data' => $json];
        }
        $msg = is_array($json) ? (string) (($json['error']['message'] ?? null) ?: $raw) : (string) $raw;

        return ['ok' => false, 'error' => 'Stripe HTTP ' . $code . ': ' . $msg];
    }

    private static function detectBaseUrl(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'shop.ganz-soft.de');

        return ($https ? 'https://' : 'http://') . $host;
    }
}
