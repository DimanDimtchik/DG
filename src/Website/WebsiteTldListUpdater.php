<?php

declare(strict_types=1);

/**
 * Aktualisiert die IANA-TLD-Liste periodisch.
 *
 * IANA pflegt keinen festen Jahres-Termin: die Datei wird bei Root-Zone-Änderungen
 * (neue/zurückgezogene TLDs) neu geschrieben; der Header enthält Version + Last Updated.
 * Für CRM reicht ein Wochen-Check — neue gTLDs sind selten, .de/.com/.eu ändern sich kaum.
 *
 * Quelle: https://data.iana.org/TLD/tlds-alpha-by-domain.txt
 * MD5:    https://data.iana.org/TLD/tlds-alpha-by-domain.txt.md5
 */
final class WebsiteTldListUpdater
{
    public const SOURCE_URL = 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt';
    public const MD5_URL = 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt.md5';

    private const STATE_FILE = '/storage/tld-list-state.json';
    private const STORAGE_LIST = '/storage/tlds-alpha-by-domain.txt';
    private const BUNDLED_LIST = '/assets/data/tlds-alpha-by-domain.txt';

    /** Mindestabstand zwischen Checks (Sekunden). */
    private const CHECK_INTERVAL = 7 * 86400;

    /**
     * Autostart: max. alle 7 Tage beim normalen CRM-Request.
     */
    public static function runIfDue(): void
    {
        if (!Database::isConfigured()) {
            return;
        }

        $state = self::loadState();
        $last = (string) ($state['last_check'] ?? '');
        if ($last !== '') {
            $ts = strtotime($last);
            if ($ts !== false && (time() - $ts) < self::CHECK_INTERVAL) {
                return;
            }
        }

        try {
            self::refresh(false);
        } catch (Throwable $e) {
            $state = self::loadState();
            $state['last_check'] = date('c');
            $state['last_error'] = $e->getMessage();
            self::saveState($state);
        }
    }

    /**
     * @return array{
     *   updated: bool,
     *   unchanged: bool,
     *   version: string|null,
     *   remote_version: string|null,
     *   path: string,
     *   message: string
     * }
     */
    public static function refresh(bool $force = false): array
    {
        $root = defined('DG_ROOT') ? (string) DG_ROOT : dirname(__DIR__, 2);
        $storagePath = $root . self::STORAGE_LIST;
        $bundledPath = $root . self::BUNDLED_LIST;
        $activePath = self::activeListPath();

        $localVersion = self::readVersionHeader($activePath);
        $remoteBody = self::httpGet(self::SOURCE_URL);
        if ($remoteBody === null || $remoteBody === '') {
            throw new RuntimeException('IANA-TLD-Liste konnte nicht geladen werden.');
        }
        if (!str_contains($remoteBody, "\nCOM\n") && !preg_match('/^COM$/m', $remoteBody)) {
            throw new RuntimeException('IANA-Antwort sieht nicht wie die TLD-Liste aus.');
        }

        $remoteVersion = self::parseVersionFromBody($remoteBody);
        $remoteMd5 = md5($remoteBody);
        $expectedMd5 = self::fetchRemoteMd5();
        if ($expectedMd5 !== null && !hash_equals($expectedMd5, $remoteMd5)) {
            throw new RuntimeException('IANA-MD5 stimmt nicht mit Download überein.');
        }

        $localBody = is_readable($activePath) ? (string) file_get_contents($activePath) : '';
        $unchanged = $localBody !== '' && hash_equals(md5($localBody), $remoteMd5);

        $state = [
            'last_check' => date('c'),
            'last_error' => null,
            'local_version' => $localVersion,
            'remote_version' => $remoteVersion,
            'remote_md5' => $remoteMd5,
            'last_update' => self::loadState()['last_update'] ?? null,
        ];

        if ($unchanged && !$force) {
            $state['local_version'] = $remoteVersion;
            self::saveState($state);

            return [
                'updated' => false,
                'unchanged' => true,
                'version' => $localVersion,
                'remote_version' => $remoteVersion,
                'path' => $activePath,
                'message' => 'TLD-Liste ist aktuell (' . ($remoteVersion ?? 'ohne Version') . ').',
            ];
        }

        $dir = dirname($storagePath);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('storage/ nicht beschreibbar.');
        }
        if (@file_put_contents($storagePath, $remoteBody) === false) {
            throw new RuntimeException('TLD-Liste konnte nicht nach storage/ geschrieben werden.');
        }

        // Bundled-Kopie mitziehen, wenn beschreibbar (Master-Repo / Deploy-Quelle)
        if (is_writable($bundledPath) || (is_dir(dirname($bundledPath)) && !file_exists($bundledPath))) {
            @file_put_contents($bundledPath, $remoteBody);
        }

        $state['local_version'] = $remoteVersion;
        $state['last_update'] = date('c');
        self::saveState($state);

        // Cache im Normalizer invalidieren
        WebsiteUrlNormalizer::clearTldCache();

        return [
            'updated' => true,
            'unchanged' => false,
            'version' => $remoteVersion,
            'remote_version' => $remoteVersion,
            'path' => $storagePath,
            'message' => 'TLD-Liste aktualisiert auf ' . ($remoteVersion ?? 'neue Fassung') . '.',
        ];
    }

    /**
     * Aktive Datei: storage/ (Auto-Update) vor gebündelter assets/-Kopie.
     */
    public static function activeListPath(): string
    {
        $root = defined('DG_ROOT') ? (string) DG_ROOT : dirname(__DIR__, 2);
        $storage = $root . self::STORAGE_LIST;
        if (is_readable($storage) && filesize($storage) > 100) {
            return $storage;
        }

        return $root . self::BUNDLED_LIST;
    }

    /**
     * @return array<string, mixed>
     */
    public static function status(): array
    {
        $state = self::loadState();
        $path = self::activeListPath();

        return [
            'path' => $path,
            'local_version' => self::readVersionHeader($path),
            'last_check' => $state['last_check'] ?? null,
            'last_update' => $state['last_update'] ?? null,
            'last_error' => $state['last_error'] ?? null,
            'remote_version' => $state['remote_version'] ?? null,
            'interval_days' => (int) (self::CHECK_INTERVAL / 86400),
        ];
    }

    private static function fetchRemoteMd5(): ?string
    {
        $raw = self::httpGet(self::MD5_URL);
        if ($raw === null || $raw === '') {
            return null;
        }
        // Format: "<md5>  tlds-alpha-by-domain.txt"
        if (preg_match('/\b([a-f0-9]{32})\b/i', $raw, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    private static function httpGet(string $url): ?string
    {
        $body = self::httpGetCurl($url, true);
        if ($body !== null) {
            return $body;
        }
        // Lokale PHP-Builds ohne CA-Store (oft Windows): einmal ohne Verify,
        // Inhalt wird danach per Format + optional MD5 geprüft.
        $body = self::httpGetCurl($url, false);
        if ($body !== null) {
            return $body;
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 20,
                'header' => "User-Agent: DG-CRM-TLD-Updater/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);

        return $body === false ? null : $body;
    }

    private static function httpGetCurl(string $url, bool $verifyPeer): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'DG-CRM-TLD-Updater/1.0',
            CURLOPT_SSL_VERIFYPEER => $verifyPeer,
            CURLOPT_SSL_VERIFYHOST => $verifyPeer ? 2 : 0,
        ];
        $ca = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
        if (is_string($ca) && $ca !== '' && is_readable($ca)) {
            $opts[CURLOPT_CAINFO] = $ca;
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($body === false || $code >= 400) {
            return null;
        }

        return (string) $body;
    }

    private static function readVersionHeader(string $path): ?string
    {
        if (!is_readable($path)) {
            return null;
        }
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return null;
        }
        $line = fgets($fh);
        fclose($fh);

        return self::parseVersionFromBody((string) $line);
    }

    private static function parseVersionFromBody(string $body): ?string
    {
        if (preg_match('/Version\s+(\d+)/i', $body, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadState(): array
    {
        $root = defined('DG_ROOT') ? (string) DG_ROOT : dirname(__DIR__, 2);
        $path = $root . self::STATE_FILE;
        if (!is_readable($path)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) ? $json : [];
    }

    /** @param array<string, mixed> $state */
    private static function saveState(array $state): void
    {
        $root = defined('DG_ROOT') ? (string) DG_ROOT : dirname(__DIR__, 2);
        $path = $root . self::STATE_FILE;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
