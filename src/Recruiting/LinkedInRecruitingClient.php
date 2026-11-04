<?php
declare(strict_types=1);

/** HTTP-Client für LinkedIn Job Posting API (OAuth2 Client Credentials). */
final class LinkedInRecruitingClient
{
    public const TOKEN_URL = 'https://www.linkedin.com/oauth/v2/accessToken';
    public const JOBS_URL = 'https://api.linkedin.com/rest/simpleJobPostings';

    public static function mapEmploymentStatus(string $employmentType): string
    {
        return match ($employmentType) {
            JobPostingRepository::TYPE_PART_TIME, JobPostingRepository::TYPE_MINI => 'PART_TIME',
            JobPostingRepository::TYPE_INTERNSHIP => 'INTERNSHIP',
            default => 'FULL_TIME',
        };
    }

    public static function externalJobPostingId(int $jobId): string
    {
        return 'dg-' . max(0, $jobId);
    }

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed>|null $cfg
     * @return array<string, mixed>
     */
    public static function buildJobElement(array $job, string $operationType = 'CREATE', ?array $cfg = null): array
    {
        $cfg ??= LinkedInRecruitingSettings::config();
        $jobId = (int) ($job['id'] ?? 0);
        $title = trim((string) ($job['title'] ?? ''));
        $summary = trim((string) ($job['summary'] ?? ''));
        $body = trim((string) ($job['body'] ?? ''));
        $location = trim((string) ($job['location'] ?? ''));
        $slug = trim((string) ($job['slug'] ?? ''));
        $applyUrl = JobPostingRepository::publicUrl($slug !== '' ? $slug : '');

        $description = trim($summary . ($summary !== '' && $body !== '' ? "\n\n" : '') . $body);
        if ($description === '') {
            $description = $title !== '' ? $title : 'Stellenanzeige';
        }
        $description = mb_substr($description, 0, 25000);

        $element = [
            'company' => LinkedInRecruitingSettings::companyUrn($cfg),
            'companyApplyUrl' => $applyUrl,
            'description' => $description,
            'employmentStatus' => self::mapEmploymentStatus(
                (string) ($job['employment_type'] ?? JobPostingRepository::TYPE_FULL_TIME)
            ),
            'externalJobPostingId' => self::externalJobPostingId($jobId),
            'listedAt' => time() * 1000,
            'jobPostingOperationType' => $operationType,
            'title' => mb_substr($title !== '' ? $title : 'Stelle', 0, 200),
            'location' => $location !== '' ? mb_substr($location, 0, 200) : 'Deutschland',
            'listingType' => (string) ($cfg['listing_type'] ?? 'BASIC'),
        ];

        if ($operationType === 'CLOSE') {
            unset($element['description'], $element['employmentStatus'], $element['listedAt'], $element['location'], $element['listingType']);
        }

        return $element;
    }

    /**
     * @return array{ok: bool, access_token: string, error: string}
     */
    public static function fetchAccessToken(?array $cfg = null): array
    {
        $cfg ??= LinkedInRecruitingSettings::config();
        if (!LinkedInRecruitingSettings::hasCredentials($cfg)) {
            return ['ok' => false, 'access_token' => '', 'error' => 'LinkedIn-Credentials unvollständig.'];
        }

        $cached = trim((string) ($cfg['access_token'] ?? ''));
        $expires = (int) ($cfg['access_token_expires_at'] ?? 0);
        if ($cached !== '' && $expires > time() + 60) {
            return ['ok' => true, 'access_token' => $cached, 'error' => ''];
        }

        if (!function_exists('curl_init')) {
            return ['ok' => false, 'access_token' => '', 'error' => 'cURL fehlt auf dem Server.'];
        }

        $ch = curl_init(self::TOKEN_URL);
        if ($ch === false) {
            return ['ok' => false, 'access_token' => '', 'error' => 'cURL-Init fehlgeschlagen.'];
        }
        $body = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => (string) $cfg['client_id'],
            'client_secret' => (string) $cfg['client_secret'],
        ], '', '&');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return ['ok' => false, 'access_token' => '', 'error' => 'Token-Netzwerk: ' . $error];
        }
        $json = is_string($raw) ? json_decode($raw, true) : null;
        $token = is_array($json) ? trim((string) ($json['access_token'] ?? '')) : '';
        if ($status < 200 || $status >= 300 || $token === '') {
            $msg = is_array($json)
                ? (string) ($json['error_description'] ?? $json['error'] ?? '')
                : '';

            return [
                'ok' => false,
                'access_token' => '',
                'error' => 'Token HTTP ' . $status . ($msg !== '' ? ': ' . $msg : ''),
            ];
        }

        $ttl = max(60, (int) ($json['expires_in'] ?? 1800));
        LinkedInRecruitingSettings::storeAccessToken($token, time() + $ttl);

        return ['ok' => true, 'access_token' => $token, 'error' => ''];
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @return array{ok: bool, status: int, body: string, json: array<string, mixed>|null, error: string}
     */
    public static function batchJobPostings(array $elements, ?array $cfg = null): array
    {
        $cfg ??= LinkedInRecruitingSettings::config();
        $tokenRes = self::fetchAccessToken($cfg);
        if (!$tokenRes['ok']) {
            return [
                'ok' => false,
                'status' => 0,
                'body' => '',
                'json' => null,
                'error' => $tokenRes['error'],
            ];
        }

        return self::request(
            self::JOBS_URL,
            ['elements' => array_values($elements)],
            $tokenRes['access_token'],
            [
                'X-Restli-Method: batch_create',
                'LinkedIn-Version: ' . LinkedInRecruitingSettings::API_VERSION,
            ]
        );
    }

    /**
     * @param array<string, mixed> $jsonBody
     * @param list<string> $extraHeaders
     * @return array{ok: bool, status: int, body: string, json: array<string, mixed>|null, error: string}
     */
    public static function request(string $url, array $jsonBody, string $accessToken, array $extraHeaders = []): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => 'cURL fehlt auf dem Server.'];
        }
        $encoded = json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => 'JSON-Encoding fehlgeschlagen.'];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => 'cURL-Init fehlgeschlagen.'];
        }
        $headers = array_merge([
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: DG-CRM-LinkedInRecruiting/1.0',
        ], $extraHeaders);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encoded,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
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
            $errMsg = 'HTTP ' . $status;
            if (is_array($decoded)) {
                foreach (['message', 'error', 'errorDetailType', 'status'] as $key) {
                    if (!empty($decoded[$key]) && (is_string($decoded[$key]) || is_numeric($decoded[$key]))) {
                        $errMsg .= ': ' . (string) $decoded[$key];
                        break;
                    }
                }
            } elseif ($body !== '') {
                $errMsg .= ': ' . mb_substr($body, 0, 200);
            }
        }

        return [
            'ok' => $ok,
            'status' => $status,
            'body' => $body,
            'json' => $decoded,
            'error' => $errMsg,
        ];
    }
}
