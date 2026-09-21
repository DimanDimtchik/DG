<?php
declare(strict_types=1);

/**
 * Amazon Business API-Client (Stufe 2 — Ordering).
 *
 * @see https://docs.business.amazon.com/docs/ordering-api
 */
final class AmazonBusinessClient
{
    /**
     * Bestellung platzieren.
     *
     * @param list<array{asin: string, quantity: float, external_line_id: string}> $lineItems
     * @return array<string, mixed> API-Antwort (decoded)
     * @throws RuntimeException|InvalidArgumentException
     */
    public static function placeOrder(string $externalOrderId, array $lineItems, ?string $userEmail = null): array
    {
        $cfg = AmazonBusinessSettings::config();
        if (!AmazonBusinessSettings::isReady($cfg)) {
            throw new RuntimeException('Amazon Business ist nicht konfiguriert.');
        }

        $externalOrderId = trim($externalOrderId);
        if ($externalOrderId === '') {
            throw new InvalidArgumentException('externalOrderId fehlt.');
        }
        if ($lineItems === []) {
            throw new InvalidArgumentException('Keine Positionen für Amazon-Bestellung.');
        }

        $apiLines = [];
        foreach ($lineItems as $i => $item) {
            if (!is_array($item)) {
                continue;
            }
            $asin = strtoupper(trim((string) ($item['asin'] ?? '')));
            $qty = (int) round((float) ($item['quantity'] ?? 0));
            $lineId = trim((string) ($item['external_line_id'] ?? (string) ($i + 1)));
            if ($asin === '' || $qty < 1) {
                throw new InvalidArgumentException('Jede Position braucht ASIN und Menge ≥ 1.');
            }
            $apiLines[] = [
                'externalId' => $lineId,
                'quantity' => $qty,
                'attributes' => [
                    [
                        'attributeType' => 'SelectedProductReference',
                        'productReference' => [
                            'productReferenceType' => 'ProductIdentifier',
                            'id' => $asin,
                        ],
                    ],
                ],
                'expectations' => [],
            ];
        }

        if ($apiLines === []) {
            throw new InvalidArgumentException('Keine gültigen Positionen.');
        }

        $email = trim((string) ($userEmail ?? $cfg['user_email'] ?? ''));
        $payload = [
            'externalId' => $externalOrderId,
            'lineItems' => $apiLines,
            'attributes' => [
                [
                    'attributeType' => 'Region',
                    'region' => (string) $cfg['region'],
                ],
            ],
            'expectations' => [],
        ];

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'x-amz-access-token: ' . AmazonBusinessAuth::accessToken(),
        ];
        if ($email !== '') {
            $headers[] = 'x-amz-user-email: ' . $email;
        }

        $url = rtrim(AmazonBusinessSettings::apiBaseUrl(), '/') . '/ordering/2022-10-30/orders';

        return self::requestJson('POST', $url, $payload, $headers);
    }

    /**
     * Bestellstatus abfragen.
     *
     * @return array<string, mixed>
     * @throws RuntimeException
     */
    public static function orderDetails(string $externalOrderId, ?string $userEmail = null): array
    {
        $cfg = AmazonBusinessSettings::config();
        if (!AmazonBusinessSettings::isReady($cfg)) {
            throw new RuntimeException('Amazon Business ist nicht konfiguriert.');
        }

        $externalOrderId = rawurlencode(trim($externalOrderId));
        if ($externalOrderId === '') {
            throw new InvalidArgumentException('externalOrderId fehlt.');
        }

        $email = trim((string) ($userEmail ?? $cfg['user_email'] ?? ''));
        $headers = [
            'Accept: application/json',
            'x-amz-access-token: ' . AmazonBusinessAuth::accessToken(),
        ];
        if ($email !== '') {
            $headers[] = 'x-amz-user-email: ' . $email;
        }

        $url = rtrim(AmazonBusinessSettings::apiBaseUrl(), '/')
            . '/ordering/2022-10-30/orders/' . $externalOrderId;

        return self::requestJson('GET', $url, null, $headers);
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param list<string> $headers
     * @return array<string, mixed>
     */
    private static function requestJson(string $method, string $url, ?array $payload, array $headers): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL ist auf dem Server nicht verfügbar.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('cURL-Init fehlgeschlagen.');
        }

        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
        ];
        if ($payload !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        curl_setopt_array($ch, $opts);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || !is_string($raw)) {
            throw new RuntimeException('Amazon-API-HTTP-Fehler: ' . ($error !== '' ? $error : 'unbekannt'));
        }

        $decoded = json_decode($raw, true);
        if ($status < 200 || $status >= 300) {
            $msg = is_array($decoded)
                ? (string) ($decoded['message'] ?? $decoded['error_description'] ?? $decoded['error'] ?? '')
                : '';
            throw new RuntimeException(
                'Amazon-API HTTP ' . $status
                . ($msg !== '' ? ': ' . $msg : ': ' . mb_substr($raw, 0, 400))
            );
        }

        return is_array($decoded) ? $decoded : ['raw' => $raw];
    }
}
