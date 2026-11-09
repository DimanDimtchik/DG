<?php
declare(strict_types=1);

/**
 * Fiskaly SIGN DE HTTP-Client (TSE-Middleware).
 *
 * @phpstan-type TseHttpResult array{status: int, json: array<string, mixed>}
 */
final class TseSignDeClient
{
    /** @var callable(string,string,?array<string,mixed>,array<string,string>):array{status:int,json:array<string,mixed>}|null */
    private $transport;

    /** @var array<string, mixed> */
    private array $cfg;

    private string $accessToken = '';

    /**
     * @param array<string, mixed>|null $cfg
     * @param callable(string,string,?array<string,mixed>,array<string,string>):array{status:int,json:array<string,mixed>}|null $transport
     */
    public function __construct(?array $cfg = null, ?callable $transport = null)
    {
        $this->cfg = $cfg ?? TseSettings::effective();
        $this->transport = $transport;
    }

    /**
     * @return array<string, mixed>
     */
    public function authenticate(): array
    {
        $res = $this->request('POST', '/auth', [
            'api_key' => (string) $this->cfg['api_key'],
            'api_secret' => (string) $this->cfg['api_secret'],
        ], false);
        $token = trim((string) ($res['access_token'] ?? ''));
        if ($token === '') {
            throw new RuntimeException('TSE-Anmeldung ohne Zugriffstoken.');
        }
        $this->accessToken = $token;

        return $res;
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveTss(): array
    {
        $tssId = (string) $this->cfg['tss_id'];

        return $this->request('GET', '/tss/' . rawurlencode($tssId));
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveClient(): array
    {
        $tssId = (string) $this->cfg['tss_id'];
        $clientId = (string) $this->cfg['client_id'];

        return $this->request(
            'GET',
            '/tss/' . rawurlencode($tssId) . '/client/' . rawurlencode($clientId)
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function upsertTransaction(string $txId, int $revision, array $body): array
    {
        $tssId = (string) $this->cfg['tss_id'];
        $path = '/tss/' . rawurlencode($tssId) . '/tx/' . rawurlencode($txId)
            . '?tx_revision=' . $revision;

        return $this->request('PUT', $path, $body);
    }

    public function clientId(): string
    {
        return (string) $this->cfg['client_id'];
    }

    public function tssId(): string
    {
        return (string) $this->cfg['tss_id'];
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null, bool $auth = true): array
    {
        if ($auth && $this->accessToken === '') {
            $this->authenticate();
        }
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        if ($auth) {
            $headers['Authorization'] = 'Bearer ' . $this->accessToken;
        }

        $result = $this->transport !== null
            ? ($this->transport)($method, $path, $body, $headers)
            : $this->http($method, $path, $body, $headers);

        $status = (int) ($result['status'] ?? 0);
        $json = is_array($result['json'] ?? null) ? $result['json'] : [];
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(self::errorMessage($status, $json));
        }

        return $json;
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string> $headers
     * @return array{status: int, json: array<string, mixed>}
     */
    private function http(string $method, string $path, ?array $body, array $headers): array
    {
        $url = rtrim((string) ($this->cfg['base_url'] ?? TseSettings::TEST_BASE_URL), '/') . $path;
        $payload = $body !== null ? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        if ($payload === false) {
            throw new RuntimeException('TSE-Anfrage konnte nicht kodiert werden.');
        }

        if (function_exists('curl_init')) {
            return $this->httpCurl($method, $url, $payload, $headers, $body !== null);
        }

        return $this->httpStream($method, $url, $payload, $headers, $body !== null);
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, json: array<string, mixed>}
     */
    private function httpCurl(string $method, string $url, ?string $payload, array $headers, bool $hasBody): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('TSE-Verbindung fehlgeschlagen.');
        }
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        if ($hasBody) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('TSE nicht erreichbar' . ($err !== '' ? ': ' . $err : '.'));
        }

        return ['status' => $status, 'json' => self::decode((string) $raw)];
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, json: array<string, mixed>}
     */
    private function httpStream(string $method, string $url, ?string $payload, array $headers, bool $hasBody): array
    {
        $headerBlob = '';
        foreach ($headers as $name => $value) {
            $headerBlob .= $name . ': ' . $value . "\r\n";
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $headerBlob,
                'content' => $hasBody ? $payload : null,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
        if ($raw === false) {
            throw new RuntimeException('TSE nicht erreichbar.');
        }

        return ['status' => $status, 'json' => self::decode($raw)];
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : ['raw' => mb_substr($raw, 0, 400)];
    }

    /**
     * @param array<string, mixed> $json
     */
    public static function errorMessage(int $status, array $json): string
    {
        $msg = trim((string) ($json['message'] ?? $json['error'] ?? ''));
        if ($msg === '') {
            $msg = 'HTTP ' . $status;
        }

        return 'TSE-Fehler: ' . $msg;
    }
}
