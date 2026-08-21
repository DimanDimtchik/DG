<?php

declare(strict_types=1);

/**
 * Soft domain availability check (DNS + RDAP).
 * Taken domains block checkout (user must choose another).
 */
final class ShopDomainCheck
{
    /**
     * @return array{ok: bool, domain: string, status: string, message: string, blocks: bool}
     */
    public static function check(string $input): array
    {
        $domain = ShopCheckout::normalizeDomain($input);
        if ($domain === '' || !ShopCheckout::isValidDomain($domain)) {
            return [
                'ok' => false,
                'domain' => $domain,
                'status' => 'invalid',
                'message' => 'Bitte eine gültige Domain eingeben (z. B. meine-firma.de).',
                'blocks' => true,
            ];
        }

        $hasDns = self::hasDns($domain);
        $rdap = self::rdapStatus($domain);

        if ($hasDns || $rdap === 'registered') {
            return [
                'ok' => false,
                'domain' => $domain,
                'status' => $hasDns ? 'in_use' : 'registered',
                'message' => 'Diese Domain ist bereits vergeben. Bitte tragen Sie eine andere Wunsch-Domain ein '
                    . '(oder eine freie Subdomain wie crm.ihre-firma.de, falls Ihre Hauptseite so bleiben soll).',
                'blocks' => true,
            ];
        }

        if ($rdap === 'available') {
            return [
                'ok' => true,
                'domain' => $domain,
                'status' => 'likely_free',
                'message' => 'Die Domain wirkt derzeit frei. Endgültige Sicherheit gibt es erst bei der Registrierung.',
                'blocks' => false,
            ];
        }

        return [
            'ok' => true,
            'domain' => $domain,
            'status' => 'unknown',
            'message' => 'Automatische Prüfung unklar — wir klären die Verfügbarkeit bei der Einrichtung mit Ihnen.',
            'blocks' => false,
        ];
    }

    private static function hasDns(string $domain): bool
    {
        $records = @dns_get_record($domain, DNS_A + DNS_AAAA + DNS_NS);
        if (is_array($records) && $records !== []) {
            return true;
        }
        $ip = @gethostbyname($domain);

        return is_string($ip) && $ip !== '' && $ip !== $domain;
    }

    /** @return 'registered'|'available'|'unknown' */
    private static function rdapStatus(string $domain): string
    {
        $url = 'https://rdap.org/domain/' . rawurlencode($domain);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 4,
                'ignore_errors' => true,
                'header' => "Accept: application/rdap+json, application/json\r\nUser-Agent: DG-Shop/1.0\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
        if ($code === 404) {
            return 'available';
        }
        if ($code >= 200 && $code < 300 && is_string($body) && $body !== '') {
            return 'registered';
        }

        return 'unknown';
    }
}
