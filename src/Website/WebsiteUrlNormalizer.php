<?php

declare(strict_types=1);

/**
 * Normalisiert Website-URLs für Formulareingaben und prüft TLD/DNS weich.
 *
 * TLD-Liste: offizielle IANA-Datei (assets/data/tlds-alpha-by-domain.txt),
 * Quelle https://data.iana.org/TLD/tlds-alpha-by-domain.txt — frei nutzbar.
 */
final class WebsiteUrlNormalizer
{
    /** @var array<string, true>|null */
    private static ?array $tldSet = null;

    public static function clearTldCache(): void
    {
        self::$tldSet = null;
    }

    /**
     * Häufige Tippfehler nur bei *ungültiger* Endung vorschlagen.
     * .co bleibt gültig und wird nicht zu .com umgebogen.
     *
     * @var array<string, string>
     */
    private const TLD_TYPOS = [
        'd' => 'de',
        'ed' => 'de',
        'dew' => 'de',
        'dee' => 'de',
        'cm' => 'com',
        'con' => 'com',
        'cmo' => 'com',
        'comm' => 'com',
        'coom' => 'com',
        'nett' => 'net',
        'nte' => 'net',
        'orgg' => 'org',
        'ogr' => 'org',
        'inf' => 'info',
        'ino' => 'info',
        'euu' => 'eu',
        'ue' => 'eu',
        'att' => 'at',
        'chh' => 'ch',
    ];

    /**
     * Leerzeichen entfernen, https:// ergänzen, Host normalisieren.
     */
    public static function normalize(string $raw): string
    {
        $url = self::cleanRaw($raw);
        if ($url === '') {
            return '';
        }

        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            if (str_starts_with($url, '//')) {
                $url = 'https:' . $url;
            } elseif (self::looksLikeHostOrUrl($url)) {
                $url = 'https://' . ltrim($url, '/');
            } else {
                return $url;
            }
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return $url;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        if ($scheme !== 'http' && $scheme !== 'https') {
            return $url;
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        $host = preg_replace('/\s+/', '', $host) ?? $host;
        if ($host === '' || !self::isValidHostLabel($host)) {
            return $url;
        }

        $out = $scheme . '://' . $host;
        if (isset($parts['port'])) {
            $out .= ':' . (int) $parts['port'];
        }
        $path = (string) ($parts['path'] ?? '');
        if ($path !== '' && $path !== '/') {
            $out .= $path;
        } elseif ($path === '/' && (isset($parts['query']) || isset($parts['fragment']))) {
            $out .= '/';
        }
        if (isset($parts['query']) && $parts['query'] !== '') {
            $out .= '?' . $parts['query'];
        }
        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $out .= '#' . $parts['fragment'];
        }

        return $out;
    }

    /**
     * @return array{
     *   ok: bool,
     *   normalized: string,
     *   host: string,
     *   tld: string,
     *   tld_ok: bool|null,
     *   dns_ok: bool|null,
     *   suggestion: string|null,
     *   message: string|null,
     *   severity: 'none'|'info'|'warn'|'error'
     * }
     */
    public static function analyze(string $raw, bool $checkDns = true): array
    {
        $normalized = self::normalize($raw);
        $empty = [
            'ok' => true,
            'normalized' => '',
            'host' => '',
            'tld' => '',
            'tld_ok' => null,
            'dns_ok' => null,
            'suggestion' => null,
            'message' => null,
            'severity' => 'none',
        ];
        if ($normalized === '') {
            return $empty;
        }

        $parts = parse_url($normalized);
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        if ($host === '') {
            return [
                'ok' => false,
                'normalized' => $normalized,
                'host' => '',
                'tld' => '',
                'tld_ok' => false,
                'dns_ok' => null,
                'suggestion' => null,
                'message' => 'Keine gültige Domain erkannt.',
                'severity' => 'error',
            ];
        }

        $tld = self::extractTld($host);
        $tldOk = $tld !== '' && self::isKnownTld($tld);
        $suggestion = null;
        $message = null;
        $severity = 'none';
        $dnsOk = null;

        if (!$tldOk) {
            $fix = self::suggestTldFix($host, $tld);
            if ($fix !== null) {
                $suggestion = self::rebuildWithHost($normalized, $fix['host']);
                $message = 'Endung „.' . $tld . '“ ist unbekannt. Meinten Sie „.' . $fix['tld'] . '“?';
                $severity = 'warn';
            } else {
                $message = 'Die Domain-Endung „.' . $tld . '“ ist nicht in der IANA-TLD-Liste.';
                $severity = 'warn';
            }
        } elseif ($checkDns) {
            $dnsOk = self::hostResolves($host);
            if ($dnsOk === false) {
                // Optional: bekannte Vertipper trotz gültiger TLD (z. B. example.cm existiert als TLD)
                $fix = self::suggestTldFix($host, $tld, true);
                if ($fix !== null && self::hostResolves($fix['host'])) {
                    $suggestion = self::rebuildWithHost($normalized, $fix['host']);
                    $message = 'Domain nicht gefunden. Meinten Sie „' . $fix['host'] . '“?';
                    $severity = 'warn';
                } else {
                    $message = 'Domain scheint nicht zu existieren (kein DNS-Eintrag).';
                    $severity = 'warn';
                }
            }
        }

        return [
            'ok' => $tldOk && ($dnsOk !== false),
            'normalized' => $normalized,
            'host' => $host,
            'tld' => $tld,
            'tld_ok' => $tldOk,
            'dns_ok' => $dnsOk,
            'suggestion' => $suggestion,
            'message' => $message,
            'severity' => $severity,
        ];
    }

    public static function isKnownTld(string $tld): bool
    {
        $tld = strtolower(ltrim(trim($tld), '.'));
        if ($tld === '') {
            return false;
        }

        return isset(self::tldSet()[$tld]);
    }

    /**
     * @return array<string, true>
     */
    public static function tldSet(): array
    {
        if (self::$tldSet !== null) {
            return self::$tldSet;
        }

        $path = WebsiteTldListUpdater::activeListPath();
        $set = [];
        if (is_readable($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $set[strtolower($line)] = true;
            }
        }
        // Fallback, falls Datei fehlt (selten)
        if ($set === []) {
            foreach (['com', 'de', 'net', 'org', 'eu', 'at', 'ch', 'info', 'io', 'co', 'uk', 'nl', 'fr', 'it', 'es'] as $t) {
                $set[$t] = true;
            }
        }

        self::$tldSet = $set;

        return self::$tldSet;
    }

    private static function cleanRaw(string $raw): string
    {
        $url = trim($raw);
        if ($url === '') {
            return '';
        }
        // Unsichtbare / typische Tipp-Leerzeichen (auch nach dem Punkt)
        $url = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $url) ?? $url;
        $url = preg_replace('/\s+/u', '', $url) ?? $url;

        return trim($url);
    }

    private static function looksLikeHostOrUrl(string $value): bool
    {
        if (str_contains($value, '@') && !str_contains($value, '/')) {
            return false;
        }

        return (bool) preg_match('#^(www\.)?[a-z0-9]([a-z0-9.-]*[a-z0-9])?\.[a-z0-9-]{1,}(/.*)?$#i', $value)
            || (bool) preg_match('#^[a-z0-9.-]+\.[a-z]{1,}(:\d+)?(/|$)#i', $value);
    }

    private static function isValidHostLabel(string $host): bool
    {
        if (strlen($host) > 253 || !str_contains($host, '.')) {
            return false;
        }

        return (bool) preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{1,63}$/i', $host);
    }

    private static function extractTld(string $host): string
    {
        $parts = explode('.', $host);
        $last = strtolower((string) end($parts));

        return $last !== false ? $last : '';
    }

    /**
     * @return array{host: string, tld: string}|null
     */
    private static function suggestTldFix(string $host, string $tld, bool $onlyMapped = false): ?array
    {
        $mapped = self::TLD_TYPOS[$tld] ?? null;
        if ($mapped !== null && self::isKnownTld($mapped)) {
            $base = preg_replace('/\.' . preg_quote($tld, '/') . '$/i', '', $host) ?? $host;

            return ['host' => $base . '.' . $mapped, 'tld' => $mapped];
        }
        if ($onlyMapped) {
            return null;
        }

        // Ein Buchstabe Differenz zu häufigen TLDs
        $candidates = ['de', 'com', 'net', 'org', 'eu', 'at', 'ch', 'info', 'io', 'co', 'nl', 'fr'];
        $best = null;
        $bestDist = 3;
        foreach ($candidates as $cand) {
            if (!self::isKnownTld($cand)) {
                continue;
            }
            $dist = levenshtein($tld, $cand);
            if ($dist > 0 && $dist < $bestDist) {
                $bestDist = $dist;
                $best = $cand;
            }
        }
        if ($best === null || $bestDist > 2) {
            return null;
        }
        $base = preg_replace('/\.' . preg_quote($tld, '/') . '$/i', '', $host) ?? $host;

        return ['host' => $base . '.' . $best, 'tld' => $best];
    }

    private static function rebuildWithHost(string $url, string $newHost): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return 'https://' . $newHost;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $out = $scheme . '://' . $newHost;
        if (isset($parts['port'])) {
            $out .= ':' . (int) $parts['port'];
        }
        $path = (string) ($parts['path'] ?? '');
        if ($path !== '' && $path !== '/') {
            $out .= $path;
        }
        if (isset($parts['query']) && $parts['query'] !== '') {
            $out .= '?' . $parts['query'];
        }
        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $out .= '#' . $parts['fragment'];
        }

        return $out;
    }

    private static function hostResolves(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));
        if ($host === '') {
            return false;
        }

        // Ein Lookup: A/AAAA. Vermeidet lange Ketten (CNAME/NS) und Hänger unter Windows.
        if (defined('DNS_A') && defined('DNS_AAAA')) {
            $records = @dns_get_record($host, DNS_A + DNS_AAAA);
            if (is_array($records) && $records !== []) {
                return true;
            }
        }

        $ip = @gethostbyname($host);

        return $ip !== $host && filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
}
