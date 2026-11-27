<?php
declare(strict_types=1);

/**
 * KL1 — GII-Import Whitelist: TOC → xml.zip → §-Chunks → storage/kichel/gii/.
 *
 * Kein Massenimport. Abruf nur für fest verdrahtete Slugs (AO/UStG/EStG/+HGB).
 */
final class KichelGiiImporter
{
    public const TOC_URL = 'https://www.gesetze-im-internet.de/gii-toc.xml';

    /** @var array<string, array{slug: string, title_needle: string}> */
    public const WHITELIST = [
        'AO' => ['slug' => 'ao_1977', 'title_needle' => 'Abgabenordnung'],
        'UStG' => ['slug' => 'ustg_1980', 'title_needle' => 'Umsatzsteuergesetz'],
        'EStG' => ['slug' => 'estg', 'title_needle' => 'Einkommensteuergesetz'],
        'HGB' => ['slug' => 'hgb', 'title_needle' => 'Handelsgesetzbuch'],
    ];

    /** Pilot ohne HGB (Default-CLI). */
    public const PILOT_LAWS = ['AO', 'UStG', 'EStG'];

    public static function baseDir(): string
    {
        return DG_ROOT . '/storage/kichel/gii';
    }

    /**
     * @return list<string>
     */
    public static function knownLawAbbrevs(): array
    {
        return array_keys(self::WHITELIST);
    }

    /**
     * @param list<string>|null $laws
     * @return list<string>
     */
    public static function normalizeLawList(?array $laws): array
    {
        if ($laws === null || $laws === []) {
            return self::PILOT_LAWS;
        }
        $out = [];
        foreach ($laws as $raw) {
            $abbr = strtoupper(trim((string) $raw));
            if ($abbr === 'USTG') {
                $abbr = 'UStG';
            } elseif ($abbr === 'ESTG') {
                $abbr = 'EStG';
            }
            if (!isset(self::WHITELIST[$abbr])) {
                throw new InvalidArgumentException('Gesetz nicht in Whitelist: ' . $raw);
            }
            $out[$abbr] = $abbr;
        }

        return array_values($out);
    }

    /**
     * @return list<array{abbr: string, slug: string, title: string, link: string}>
     */
    public static function resolveFromTocFile(string $tocPath, ?array $laws = null): array
    {
        if (!is_readable($tocPath)) {
            throw new InvalidArgumentException('TOC nicht lesbar: ' . $tocPath);
        }
        $xml = @simplexml_load_file($tocPath);
        if ($xml === false) {
            throw new RuntimeException('TOC Parsefehler: ' . $tocPath);
        }

        return self::resolveFromTocXml($xml, $laws);
    }

    /**
     * @param list<string>|null $laws
     * @return list<array{abbr: string, slug: string, title: string, link: string}>
     */
    public static function resolveFromTocXml(SimpleXMLElement $toc, ?array $laws = null): array
    {
        $laws = self::normalizeLawList($laws);
        $bySlug = [];
        foreach ($toc->xpath('//item') ?: [] as $item) {
            $link = trim((string) ($item->link ?? ''));
            $title = trim((string) ($item->title ?? ''));
            if ($link === '') {
                continue;
            }
            $path = parse_url($link, PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                continue;
            }
            if (!preg_match('#/([^/]+)/xml\.zip$#i', $path, $m)) {
                continue;
            }
            $slug = strtolower($m[1]);
            $bySlug[$slug] = ['title' => $title, 'link' => self::httpsLink($link)];
        }

        $resolved = [];
        foreach ($laws as $abbr) {
            $meta = self::WHITELIST[$abbr];
            $slug = $meta['slug'];
            if (!isset($bySlug[$slug])) {
                throw new RuntimeException('TOC ohne Whitelist-Slug: ' . $slug . ' (' . $abbr . ')');
            }
            $hit = $bySlug[$slug];
            $needle = $meta['title_needle'];
            if ($needle !== '' && stripos($hit['title'], $needle) === false) {
                throw new RuntimeException(
                    'TOC-Titel passt nicht zu ' . $abbr . ': erwartet „' . $needle . '“, got „' . $hit['title'] . '“'
                );
            }
            $resolved[] = [
                'abbr' => $abbr,
                'slug' => $slug,
                'title' => $hit['title'],
                'link' => $hit['link'],
            ];
        }

        return $resolved;
    }

    public static function httpsLink(string $link): string
    {
        $link = trim($link);
        if (str_starts_with($link, 'http://')) {
            return 'https://' . substr($link, 7);
        }

        return $link;
    }

    /**
     * TOC von URL oder lokalem Pfad laden.
     *
     * @param list<string>|null $laws
     * @return list<array{abbr: string, slug: string, title: string, link: string}>
     */
    public static function resolveToc(?string $tocSource = null, ?array $laws = null): array
    {
        $tocSource = $tocSource !== null && $tocSource !== '' ? $tocSource : self::TOC_URL;
        if (preg_match('#^https?://#i', $tocSource)) {
            $tmp = self::downloadToTemp($tocSource, 'gii-toc-', '.xml');
            try {
                return self::resolveFromTocFile($tmp, $laws);
            } finally {
                @unlink($tmp);
            }
        }

        return self::resolveFromTocFile($tocSource, $laws);
    }

    /**
     * ZIP laden, XML finden, parsen. Schreibt nicht.
     *
     * @return array{entry: array{abbr: string, slug: string, title: string, link: string}, xml_path: string, chunks: list<array<string, mixed>>, work_dir: string}
     */
    public static function importLawFromZipUrl(
        array $entry,
        ?string $fetchedAt = null,
        ?string $workDir = null
    ): array {
        $fetchedAt = $fetchedAt ?? gmdate('c');
        $workDir = $workDir ?? (sys_get_temp_dir() . '/gii-import-' . $entry['slug'] . '-' . bin2hex(random_bytes(4)));
        if (!is_dir($workDir) && !mkdir($workDir, 0775, true) && !is_dir($workDir)) {
            throw new RuntimeException('Arbeitsverzeichnis fehlt: ' . $workDir);
        }
        $zipPath = $workDir . '/xml.zip';
        self::downloadFile($entry['link'], $zipPath);
        $xmlPath = self::extractFirstXml($zipPath, $workDir . '/xml');
        $chunks = KichelGiiXmlParser::parseFile($xmlPath, $entry['abbr'], $entry['slug'], $fetchedAt);

        return [
            'entry' => $entry,
            'xml_path' => $xmlPath,
            'chunks' => $chunks,
            'work_dir' => $workDir,
        ];
    }

    /**
     * Lokale XML-Datei → Chunks (Selftest / Offline).
     *
     * @return list<array<string, mixed>>
     */
    public static function importLawFromXmlFile(
        string $xmlPath,
        string $lawAbbr,
        string $giiSlug,
        ?string $fetchedAt = null
    ): array {
        return KichelGiiXmlParser::parseFile($xmlPath, $lawAbbr, $giiSlug, $fetchedAt);
    }

    /**
     * Chunks als JSONL + Manifest speichern (für KL2).
     *
     * @param list<array<string, mixed>> $chunks
     * @param array<string, mixed> $meta
     * @return array{jsonl: string, manifest: string, chunk_count: int}
     */
    public static function writeChunks(string $lawAbbr, array $chunks, array $meta = []): array
    {
        $dir = self::baseDir();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Speicherordner fehlt: ' . $dir);
        }
        $safe = preg_replace('/[^a-z0-9_-]+/i', '', $lawAbbr) ?: 'law';
        $jsonl = $dir . '/' . strtolower($safe) . '.jsonl';
        $manifest = $dir . '/' . strtolower($safe) . '.manifest.json';

        $fh = fopen($jsonl, 'wb');
        if ($fh === false) {
            throw new RuntimeException('JSONL nicht schreibbar: ' . $jsonl);
        }
        foreach ($chunks as $chunk) {
            $line = json_encode($chunk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($line === false) {
                fclose($fh);
                throw new RuntimeException('Chunk JSON encode fehlgeschlagen');
            }
            fwrite($fh, $line . "\n");
        }
        fclose($fh);

        $payload = array_merge([
            'law' => $lawAbbr,
            'chunk_count' => count($chunks),
            'written_at' => gmdate('c'),
            'source' => 'gesetze-im-internet.de',
            'format' => 'gii-norm-xml',
        ], $meta);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false || file_put_contents($manifest, $json . "\n") === false) {
            throw new RuntimeException('Manifest nicht schreibbar: ' . $manifest);
        }

        return [
            'jsonl' => $jsonl,
            'manifest' => $manifest,
            'chunk_count' => count($chunks),
        ];
    }

    /**
     * @param list<string>|null $laws
     * @return array{resolved: list<array{abbr: string, slug: string, title: string, link: string}>, results: list<array<string, mixed>>}
     */
    public static function run(
        ?array $laws = null,
        ?string $tocSource = null,
        bool $download = true,
        ?string $fetchedAt = null
    ): array {
        $resolved = self::resolveToc($tocSource, $laws);
        $results = [];
        $fetchedAt = $fetchedAt ?? gmdate('c');

        foreach ($resolved as $entry) {
            if (!$download) {
                $results[] = [
                    'abbr' => $entry['abbr'],
                    'slug' => $entry['slug'],
                    'title' => $entry['title'],
                    'link' => $entry['link'],
                    'skipped_download' => true,
                ];
                continue;
            }
            $pack = self::importLawFromZipUrl($entry, $fetchedAt);
            $written = self::writeChunks($entry['abbr'], $pack['chunks'], [
                'slug' => $entry['slug'],
                'title' => $entry['title'],
                'link' => $entry['link'],
                'fetched_at' => $fetchedAt,
                'xml_basename' => basename($pack['xml_path']),
            ]);
            $results[] = [
                'abbr' => $entry['abbr'],
                'slug' => $entry['slug'],
                'title' => $entry['title'],
                'link' => $entry['link'],
                'chunk_count' => $written['chunk_count'],
                'jsonl' => $written['jsonl'],
                'manifest' => $written['manifest'],
            ];
        }

        return ['resolved' => $resolved, 'results' => $results];
    }

    public static function extractFirstXml(string $zipPath, string $targetDir): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP-ZipArchive fehlt — GII-Import nicht möglich.');
        }
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Entpack-Ordner fehlt: ' . $targetDir);
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('ZIP öffnen fehlgeschlagen: ' . $zipPath);
        }
        try {
            if (!$zip->extractTo($targetDir)) {
                throw new RuntimeException('ZIP entpacken fehlgeschlagen: ' . $zipPath);
            }
        } finally {
            $zip->close();
        }
        $xmls = glob($targetDir . '/*.xml') ?: [];
        if ($xmls === []) {
            $xmls = glob($targetDir . '/**/*.xml') ?: [];
        }
        if ($xmls === []) {
            throw new RuntimeException('Kein XML in ZIP: ' . $zipPath);
        }
        sort($xmls);

        return $xmls[0];
    }

    public static function downloadFile(string $url, string $targetPath): void
    {
        $dir = dirname($targetPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Download-Ordner fehlt: ' . $dir);
        }
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 120,
                'header' => "User-Agent: DG-CRM-KichelGiiImporter/1.0\r\nAccept: */*\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false || $data === '') {
            throw new RuntimeException('Download fehlgeschlagen: ' . $url);
        }
        if (file_put_contents($targetPath, $data) === false) {
            throw new RuntimeException('Download speichern fehlgeschlagen: ' . $targetPath);
        }
    }

    private static function downloadToTemp(string $url, string $prefix, string $suffix): string
    {
        $tmp = tempnam(sys_get_temp_dir(), $prefix);
        if ($tmp === false) {
            throw new RuntimeException('tempnam fehlgeschlagen');
        }
        $path = $tmp . $suffix;
        @unlink($tmp);
        self::downloadFile($url, $path);

        return $path;
    }
}
