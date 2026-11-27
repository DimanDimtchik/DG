<?php
declare(strict_types=1);

/**
 * KL5 — RII-Import Pilot: nur BFH + Themenfilter (Steuer-Normen), hartes Max.
 *
 * TOC: https://www.rechtsprechung-im-internet.de/rii-toc.xml (groß → XMLReader).
 */
final class KichelRiiImporter
{
    public const TOC_URL = 'https://www.rechtsprechung-im-internet.de/rii-toc.xml';

    public const COURT_PREFIX = 'BFH';

    /** Default-Cap pro Lauf (kein Vollbestand). */
    public const DEFAULT_MAX = 15;

    /** Themenfilter auf norm/Titel/Body nach dem Parse. */
    public const TOPIC_NEEDLES = [
        'EStG', 'UStG', 'AO', 'KStG', 'GewStG', 'UStDV', 'EStDV', 'FGO',
        'Kleinunternehmer', 'Vorsteuer', 'Umsatzsteuer', 'Einkommensteuer',
    ];

    public static function baseDir(): string
    {
        return DG_ROOT . '/storage/kichel/rii';
    }

    /**
     * @return list<array{gericht: string, date: string, aktenzeichen: string, link: string, modified: string}>
     */
    public static function listFromTocFile(
        string $tocPath,
        string $courtPrefix = self::COURT_PREFIX,
        int $max = self::DEFAULT_MAX
    ): array {
        if (!is_readable($tocPath)) {
            throw new InvalidArgumentException('RII-TOC nicht lesbar: ' . $tocPath);
        }
        $max = max(1, min(200, $max));
        $reader = new XMLReader();
        if (!$reader->open($tocPath)) {
            throw new RuntimeException('RII-TOC open fehlgeschlagen: ' . $tocPath);
        }
        $rows = [];
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'item') {
                    continue;
                }
                $outer = $reader->readOuterXML();
                $sx = @simplexml_load_string($outer);
                if ($sx === false) {
                    continue;
                }
                $gericht = trim((string) ($sx->gericht ?? ''));
                if ($gericht === '' || stripos($gericht, $courtPrefix) !== 0) {
                    continue;
                }
                $link = KichelGiiImporter::httpsLink(trim((string) ($sx->link ?? '')));
                if ($link === '') {
                    continue;
                }
                $rows[] = [
                    'gericht' => $gericht,
                    'date' => trim((string) ($sx->{'entsch-datum'} ?? '')),
                    'aktenzeichen' => trim((string) ($sx->aktenzeichen ?? '')),
                    'link' => $link,
                    'modified' => trim((string) ($sx->modified ?? '')),
                ];
            }
        } finally {
            $reader->close();
        }

        usort($rows, static function (array $a, array $b): int {
            // neueste zuerst
            return strcmp((string) $b['date'], (string) $a['date']);
        });

        return array_slice($rows, 0, $max * 5); // Puffer vor Themenfilter
    }

    /**
     * @param list<string>|null $topicNeedles
     * @return list<array{gericht: string, date: string, aktenzeichen: string, link: string, modified: string}>
     */
    public static function resolveToc(
        ?string $tocSource = null,
        string $courtPrefix = self::COURT_PREFIX,
        int $max = self::DEFAULT_MAX
    ): array {
        $tocSource = $tocSource !== null && $tocSource !== '' ? $tocSource : self::TOC_URL;
        if (preg_match('#^https?://#i', $tocSource)) {
            $tmp = self::downloadToTemp($tocSource, 'rii-toc-', '.xml');
            try {
                return self::listFromTocFile($tmp, $courtPrefix, $max);
            } finally {
                @unlink($tmp);
            }
        }

        return self::listFromTocFile($tocSource, $courtPrefix, $max);
    }

    /**
     * Offline: XML-Datei → Chunks (mit Themenfilter).
     *
     * @param list<string>|null $topicNeedles
     * @return list<array<string, mixed>>
     */
    public static function importFromXmlFile(
        string $xmlPath,
        ?string $fetchedAt = null,
        ?array $topicNeedles = null,
        bool $requireTopic = true
    ): array {
        $chunks = KichelRiiXmlParser::parseFile($xmlPath, $fetchedAt);
        if (!$requireTopic) {
            return $chunks;
        }
        $out = [];
        foreach ($chunks as $chunk) {
            if (KichelRiiXmlParser::matchesTopicFilter($chunk, $topicNeedles)) {
                $out[] = $chunk;
            }
        }

        return $out;
    }

    /**
     * @param list<array{gericht: string, date: string, aktenzeichen: string, link: string, modified: string}> $entries
     * @param list<string>|null $topicNeedles
     * @return array{chunks: list<array<string, mixed>>, imported: list<array<string, mixed>>, skipped: list<array<string, mixed>>}
     */
    public static function importEntries(
        array $entries,
        int $maxKeep = self::DEFAULT_MAX,
        ?string $fetchedAt = null,
        ?array $topicNeedles = null,
        bool $download = true
    ): array {
        $fetchedAt = $fetchedAt ?? gmdate('c');
        $maxKeep = max(1, min(200, $maxKeep));
        $chunks = [];
        $imported = [];
        $skipped = [];

        foreach ($entries as $entry) {
            if (count($chunks) >= $maxKeep) {
                break;
            }
            if (!$download) {
                $skipped[] = array_merge($entry, ['reason' => 'dry_run']);
                continue;
            }
            try {
                $work = sys_get_temp_dir() . '/rii-imp-' . bin2hex(random_bytes(4));
                if (!is_dir($work) && !mkdir($work, 0775, true) && !is_dir($work)) {
                    throw new RuntimeException('work dir');
                }
                $zipPath = $work . '/doc.zip';
                KichelGiiImporter::downloadFile($entry['link'], $zipPath);
                $xmlPath = KichelGiiImporter::extractFirstXml($zipPath, $work . '/xml');
                $parsed = KichelRiiXmlParser::parseFile($xmlPath, $fetchedAt);
                $kept = [];
                foreach ($parsed as $chunk) {
                    if (KichelRiiXmlParser::matchesTopicFilter($chunk, $topicNeedles)) {
                        $kept[] = $chunk;
                    }
                }
                if ($kept === []) {
                    $skipped[] = array_merge($entry, ['reason' => 'topic_filter']);
                } else {
                    foreach ($kept as $c) {
                        if (count($chunks) >= $maxKeep) {
                            break;
                        }
                        $chunks[] = $c;
                    }
                    $imported[] = array_merge($entry, [
                        'doknr' => (string) ($kept[0]['doknr'] ?? ''),
                        'chunk_count' => count($kept),
                    ]);
                }
                self::rmTree($work);
            } catch (Throwable $e) {
                $skipped[] = array_merge($entry, ['reason' => 'error', 'error' => $e->getMessage()]);
            }
        }

        return [
            'chunks' => $chunks,
            'imported' => $imported,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param list<array<string, mixed>> $chunks
     * @param array<string, mixed> $meta
     * @return array{jsonl: string, manifest: string, chunk_count: int}
     */
    public static function writeChunks(array $chunks, array $meta = [], ?string $baseDir = null): array
    {
        $dir = $baseDir !== null && $baseDir !== ''
            ? rtrim(str_replace('\\', '/', $baseDir), '/')
            : self::baseDir();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('RII-Speicherordner fehlt: ' . $dir);
        }
        $jsonl = $dir . '/bfh.jsonl';
        $manifest = $dir . '/bfh.manifest.json';
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
            'court' => self::COURT_PREFIX,
            'chunk_count' => count($chunks),
            'written_at' => gmdate('c'),
            'source' => 'rechtsprechung-im-internet.de',
            'format' => 'rii-dok-xml',
            'topic_needles' => self::TOPIC_NEEDLES,
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
     * @return array<string, mixed>
     */
    public static function run(
        ?string $tocSource = null,
        int $max = self::DEFAULT_MAX,
        bool $download = true,
        ?string $baseDir = null,
        ?string $fetchedAt = null
    ): array {
        $candidates = self::resolveToc($tocSource, self::COURT_PREFIX, $max);
        $pack = self::importEntries($candidates, $max, $fetchedAt, null, $download);
        $written = null;
        if ($download && $pack['chunks'] !== []) {
            $written = self::writeChunks($pack['chunks'], [
                'fetched_at' => $fetchedAt ?? gmdate('c'),
                'candidate_count' => count($candidates),
                'imported_count' => count($pack['imported']),
            ], $baseDir);
            KichelCaselawIndex::reset();
        }

        return [
            'candidates' => count($candidates),
            'imported' => $pack['imported'],
            'skipped' => $pack['skipped'],
            'chunk_count' => count($pack['chunks']),
            'written' => $written,
            'download' => $download,
        ];
    }

    private static function downloadToTemp(string $url, string $prefix, string $suffix): string
    {
        $tmp = tempnam(sys_get_temp_dir(), $prefix);
        if ($tmp === false) {
            throw new RuntimeException('tempnam fehlgeschlagen');
        }
        $path = $tmp . $suffix;
        @unlink($tmp);
        KichelGiiImporter::downloadFile($url, $path);

        return $path;
    }

    private static function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                self::rmTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
