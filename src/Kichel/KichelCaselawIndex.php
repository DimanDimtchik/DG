<?php
declare(strict_types=1);

/**
 * KL5 — Volltext-Index über RII-JSONL (storage/kichel/rii/*.jsonl).
 */
final class KichelCaselawIndex
{
    /** @var list<array<string, mixed>>|null */
    private static ?array $chunks = null;

    private static ?string $loadedDir = null;

    private static ?string $dirOverride = null;

    public static function baseDir(): string
    {
        if (self::$dirOverride !== null && self::$dirOverride !== '') {
            return self::$dirOverride;
        }

        return KichelRiiImporter::baseDir();
    }

    public static function setDirOverride(?string $dir): void
    {
        self::$dirOverride = $dir !== null && $dir !== ''
            ? rtrim(str_replace('\\', '/', $dir), '/')
            : null;
        self::reset();
    }

    public static function reset(): void
    {
        self::$chunks = null;
        self::$loadedDir = null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(?string $dir = null): array
    {
        self::ensureLoaded($dir);

        return self::$chunks ?? [];
    }

    public static function count(?string $dir = null): int
    {
        return count(self::all($dir));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function loadFromDir(string $dir): array
    {
        $dir = rtrim(str_replace('\\', '/', $dir), '/');
        if ($dir === '' || !is_dir($dir)) {
            self::$chunks = [];
            self::$loadedDir = $dir;

            return [];
        }
        $files = glob($dir . '/*.jsonl') ?: [];
        sort($files);
        $chunks = [];
        $seen = [];
        foreach ($files as $file) {
            $fh = fopen($file, 'rb');
            if ($fh === false) {
                continue;
            }
            while (($line = fgets($fh)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $row = json_decode($line, true);
                if (!is_array($row)) {
                    continue;
                }
                $norm = self::normalizeChunk($row);
                if ($norm === null) {
                    continue;
                }
                $ref = (string) $norm['source_ref'];
                if (isset($seen[$ref])) {
                    continue;
                }
                $seen[$ref] = true;
                $chunks[] = $norm;
            }
            fclose($fh);
        }
        self::$chunks = $chunks;
        self::$loadedDir = $dir;

        return $chunks;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    public static function normalizeChunk(array $row): ?array
    {
        $title = trim((string) ($row['title'] ?? ''));
        $body = trim((string) ($row['body'] ?? ''));
        $sourceRef = trim((string) ($row['source_ref'] ?? ''));
        if (($title === '' && $body === '') || $sourceRef === '') {
            return null;
        }
        $hrefRaw = $row['href'] ?? null;
        $href = is_string($hrefRaw) && trim($hrefRaw) !== '' ? trim($hrefRaw) : null;

        return [
            'type' => KichelRagFact::TYPE_CASELAW,
            'court' => trim((string) ($row['court'] ?? '')),
            'gertyp' => trim((string) ($row['gertyp'] ?? '')),
            'aktenzeichen' => trim((string) ($row['aktenzeichen'] ?? '')),
            'doknr' => trim((string) ($row['doknr'] ?? '')),
            'norm' => trim((string) ($row['norm'] ?? '')),
            'title' => $title !== '' ? $title : $sourceRef,
            'body' => $body,
            'source_label' => trim((string) ($row['source_label'] ?? 'RII')) ?: 'RII',
            'source_ref' => $sourceRef,
            'href' => $href,
            'fetched_at' => trim((string) ($row['fetched_at'] ?? '')),
            'confidence' => isset($row['confidence']) ? (float) $row['confidence'] : 0.9,
            'numbers' => is_array($row['numbers'] ?? null) ? $row['numbers'] : [],
            '_hay' => mb_strtolower($title . "\n" . $body . "\n" . (string) ($row['norm'] ?? ''), 'UTF-8'),
            '_court_key' => mb_strtolower(trim((string) ($row['gertyp'] ?? '')), 'UTF-8'),
        ];
    }

    /**
     * @param list<string> $tokens
     * @return list<array{chunk: array<string, mixed>, score: int}>
     */
    public static function search(array $tokens, int $limit = 5, ?string $dir = null): array
    {
        $limit = max(1, min(50, $limit));
        $tokens = array_values(array_filter(array_map(
            static fn ($t) => mb_strtolower(trim((string) $t), 'UTF-8'),
            $tokens
        ), static fn (string $t): bool => $t !== ''));
        if ($tokens === []) {
            return [];
        }
        $chunks = self::all($dir);
        $scored = [];
        foreach ($chunks as $chunk) {
            $score = self::scoreChunk($chunk, $tokens);
            if ($score > 0) {
                $scored[] = ['chunk' => $chunk, 'score' => $score];
            }
        }
        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * @return list<array{chunk: array<string, mixed>, score: int}>
     */
    public static function searchQuery(string $query, int $limit = 5, ?string $dir = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        $tokens = KichelKnowledge::tokenize($query);
        if (preg_match('/\bbfh\b/iu', $query)) {
            $tokens[] = 'bfh';
        }
        foreach (['ustg', 'estg', 'ao', 'kstg', 'gewstg'] as $law) {
            if (preg_match('/\b' . $law . '\b/iu', $query)) {
                $tokens[] = $law;
            }
        }
        $tokens = array_values(array_unique($tokens));

        return self::search($tokens, $limit, $dir);
    }

    /**
     * @param list<array{chunk: array<string, mixed>, score: int}> $hits
     * @return list<array<string, mixed>>
     */
    public static function factsFromHits(array $hits): array
    {
        $facts = [];
        foreach ($hits as $hit) {
            $chunk = $hit['chunk'] ?? null;
            if (!is_array($chunk)) {
                continue;
            }
            $score = (int) ($hit['score'] ?? 0);
            $fact = KichelRagFact::normalize([
                'type' => KichelRagFact::TYPE_CASELAW,
                'title' => (string) ($chunk['title'] ?? ''),
                'body' => (string) ($chunk['body'] ?? ''),
                'source_label' => (string) ($chunk['source_label'] ?? 'RII'),
                'source_ref' => (string) ($chunk['source_ref'] ?? ''),
                'href' => $chunk['href'] ?? null,
                'confidence' => min(1.0, max(0.2, $score / 35.0)),
                'numbers' => [],
            ]);
            if ($fact !== null) {
                $facts[] = $fact;
            }
        }

        return $facts;
    }

    /**
     * @param array<string, mixed> $chunk
     * @param list<string> $tokens
     */
    private static function scoreChunk(array $chunk, array $tokens): int
    {
        $score = 0;
        $hay = (string) ($chunk['_hay'] ?? '');
        $courtKey = (string) ($chunk['_court_key'] ?? '');
        foreach ($tokens as $token) {
            if ($token === 'bfh' && ($courtKey === 'bfh' || str_contains($hay, 'bfh'))) {
                $score += 10;
                continue;
            }
            if (mb_strlen($token, 'UTF-8') < 2) {
                continue;
            }
            if (str_contains($hay, $token)) {
                $len = mb_strlen($token, 'UTF-8');
                $score += $len >= 6 ? 5 : ($len >= 4 ? 3 : 1);
            }
        }

        return $score;
    }

    private static function ensureLoaded(?string $dir): void
    {
        $dir = $dir !== null && $dir !== '' ? rtrim(str_replace('\\', '/', $dir), '/') : self::baseDir();
        if (self::$chunks !== null && self::$loadedDir === $dir) {
            return;
        }
        self::loadFromDir($dir);
    }
}
