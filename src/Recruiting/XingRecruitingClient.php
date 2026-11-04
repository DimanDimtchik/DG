<?php
declare(strict_types=1);

/** HTTP-Client für Xing Vendor Jobs API (OAuth 1.0a PLAINTEXT). */
final class XingRecruitingClient
{
    public const API_BASE = 'https://api.xing.com';
    public const ACCEPT = 'application/vnd.xing.jobs.v1+json';

    /**
     * @param array<string, mixed>|null $cfg
     * @return array<string, string>
     */
    public static function oauthQueryParams(?array $cfg = null): array
    {
        $cfg ??= XingRecruitingSettings::config();
        $consumerSecret = (string) ($cfg['consumer_secret'] ?? '');
        $tokenSecret = (string) ($cfg['access_token_secret'] ?? '');

        return [
            'oauth_token' => (string) ($cfg['access_token'] ?? ''),
            'oauth_consumer_key' => (string) ($cfg['consumer_key'] ?? ''),
            'oauth_signature_method' => 'PLAINTEXT',
            'oauth_signature' => $consumerSecret . '&' . $tokenSecret,
        ];
    }

    public static function mapJobType(string $employmentType): string
    {
        return match ($employmentType) {
            JobPostingRepository::TYPE_PART_TIME, JobPostingRepository::TYPE_MINI => 'PART_TIME',
            JobPostingRepository::TYPE_INTERNSHIP => 'INTERN',
            default => 'FULL_TIME',
        };
    }

    /**
     * @param array<string, mixed> $job Row aus dg_job_postings
     * @param array<string, mixed>|null $cfg
     * @return array<string, mixed>
     */
    public static function buildCreatePayload(array $job, ?array $cfg = null): array
    {
        $cfg ??= XingRecruitingSettings::config();
        $title = trim((string) ($job['title'] ?? ''));
        $summary = trim((string) ($job['summary'] ?? ''));
        $body = trim((string) ($job['body'] ?? ''));
        $location = trim((string) ($job['location'] ?? ''));
        $slug = trim((string) ($job['slug'] ?? ''));
        $applyUrl = JobPostingRepository::publicUrl($slug !== '' ? $slug : '');

        $html = '';
        if ($summary !== '') {
            $html .= '<p>' . self::escapeHtml($summary) . '</p>';
        }
        if ($body !== '') {
            $html .= self::plainTextToHtml($body);
        }
        if ($html === '') {
            $html = '<p>' . self::escapeHtml($title !== '' ? $title : 'Stellenanzeige') . '</p>';
        }
        $html = mb_substr($html, 0, 10000);

        $city = $location !== '' ? mb_substr($location, 0, 100) : 'Deutschland';
        $payload = [
            'organization_id' => (int) XingRecruitingSettings::effectiveOrganizationId($cfg),
            'order_id' => (int) XingRecruitingSettings::effectiveOrderId($cfg),
            'function' => mb_substr($title !== '' ? $title : 'Stelle', 0, 255),
            'company_name' => XingRecruitingSettings::resolveCompanyName($cfg),
            'description' => $html,
            'job_locations' => [
                [
                    'city' => $city,
                    'country' => 'DE',
                ],
            ],
            'reply_setting' => 'url',
            'reply_url' => $applyUrl,
            'language' => 'de',
            'job_type' => self::mapJobType((string) ($job['employment_type'] ?? JobPostingRepository::TYPE_FULL_TIME)),
            'job_code' => mb_substr('dg-' . (string) ((int) ($job['id'] ?? 0)), 0, 100),
        ];

        $profile = trim((string) ($cfg['company_profile_url'] ?? ''));
        if ($profile !== '') {
            $payload['point_of_contact_type'] = 'company';
            $payload['company_profile_url'] = mb_substr($profile, 0, 500);
            $payload['publish_to_company'] = true;
        } else {
            $payload['point_of_contact_type'] = 'none';
            $payload['user_role'] = 'HR_DEPARTMENT';
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, status: int, body: string, json: array<string, mixed>|null, error: string}
     */
    public static function createPosting(array $payload, ?array $cfg = null): array
    {
        return self::request('POST', '/vendor/jobs/postings.json', $payload, $cfg);
    }

    /**
     * @return array{ok: bool, status: int, body: string, json: array<string, mixed>|null, error: string}
     */
    public static function activatePosting(string $postingId, ?array $cfg = null): array
    {
        $id = preg_replace('/\D+/', '', $postingId) ?? '';
        if ($id === '') {
            return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => 'Xing-Posting-ID fehlt.'];
        }

        return self::request('PUT', '/vendor/jobs/postings/' . $id . '/activate', null, $cfg);
    }

    /**
     * @return array{ok: bool, status: int, body: string, json: array<string, mixed>|null, error: string}
     */
    public static function deactivatePosting(string $postingId, ?array $cfg = null): array
    {
        $id = preg_replace('/\D+/', '', $postingId) ?? '';
        if ($id === '') {
            return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => 'Xing-Posting-ID fehlt.'];
        }

        return self::request('PUT', '/vendor/jobs/postings/' . $id . '/deactivate', null, $cfg);
    }

    /**
     * @param array<string, mixed>|null $jsonBody
     * @param array<string, mixed>|null $cfg
     * @return array{ok: bool, status: int, body: string, json: array<string, mixed>|null, error: string}
     */
    public static function request(string $method, string $path, ?array $jsonBody = null, ?array $cfg = null): array
    {
        $cfg ??= XingRecruitingSettings::config();
        if (!XingRecruitingSettings::hasOAuth($cfg)) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => 'Xing-OAuth unvollständig.'];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => 'cURL fehlt auf dem Server.'];
        }

        $query = http_build_query(self::oauthQueryParams($cfg), '', '&', PHP_QUERY_RFC3986);
        $url = self::API_BASE . $path . (str_contains($path, '?') ? '&' : '?') . $query;
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => 'cURL-Init fehlgeschlagen.'];
        }

        $headers = [
            'Accept: ' . self::ACCEPT,
            'User-Agent: DG-CRM-XingRecruiting/1.0',
        ];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($jsonBody !== null) {
            $encoded = json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                curl_close($ch);

                return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => 'JSON-Encoding fehlgeschlagen.'];
            }
            $headers[] = 'Content-Type: application/json; charset=utf-8';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = $encoded;
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return ['ok' => false, 'status' => $status, 'body' => '', 'json' => null, 'error' => 'Netzwerk: ' . $error];
        }

        $body = is_string($raw) ? $raw : '';
        $decoded = null;
        if ($body !== '') {
            $tmp = json_decode($body, true);
            $decoded = is_array($tmp) ? $tmp : null;
        }
        $ok = $status >= 200 && $status < 300;
        $errMsg = '';
        if (!$ok) {
            $errMsg = self::errorMessageFromResponse($status, $decoded, $body);
        }

        return [
            'ok' => $ok,
            'status' => $status,
            'body' => $body,
            'json' => $decoded,
            'error' => $errMsg,
        ];
    }

    /**
     * @param array<string, mixed>|null $json
     */
    private static function errorMessageFromResponse(int $status, ?array $json, string $body): string
    {
        if (is_array($json)) {
            foreach (['error_name', 'error', 'message', 'error_description'] as $key) {
                if (!empty($json[$key]) && is_string($json[$key])) {
                    return 'HTTP ' . $status . ': ' . $json[$key];
                }
            }
        }
        $snip = trim(mb_substr($body, 0, 200));

        return 'HTTP ' . $status . ($snip !== '' ? ': ' . $snip : '');
    }

    private static function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function plainTextToHtml(string $text): string
    {
        $parts = preg_split('/\R{2,}/u', trim($text)) ?: [];
        if ($parts === []) {
            return '<p>' . self::escapeHtml($text) . '</p>';
        }
        $html = '';
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $html .= '<p>' . nl2br(self::escapeHtml($part), false) . '</p>';
        }

        return $html !== '' ? $html : '<p>' . self::escapeHtml($text) . '</p>';
    }
}
