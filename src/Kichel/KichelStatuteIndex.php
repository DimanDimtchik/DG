<?php
declare(strict_types=1);

/**
 * KL2 — Volltext-Index über GII-JSONL (storage/kichel/gii/*.jsonl).
 *
 * Lädt Chunks in den Speicher und scored tokenbasiert. Retriever-Verdrahtung → KL3.
 */
final class KichelStatuteIndex
{
    /** @var list<array<string, mixed>>|null */
    private static ?array $chunks = null;

    /** @var list<array<string, mixed>>|null */
    private static ?array $manifests = null;

    private static ?string $loadedDir = null;

    /** Selftest / abweichender Korpus-Pfad (null = storage/kichel/gii). */
    private static ?string $dirOverride = null;

    public static function baseDir(): string
    {
        if (self::$dirOverride !== null && self::$dirOverride !== '') {
            return self::$dirOverride;
        }

        return KichelGiiImporter::baseDir();
    }

    /**
     * Pfad-Override (Selftests). null = Standard. Leert den Cache.
     */
    public static function setDirOverride(?string $dir): void
    {
        self::$dirOverride = $dir !== null && $dir !== ''
            ? rtrim(str_replace('\\', '/', $dir), '/')
            : null;
        self::reset();
    }

    /**
     * Cache leeren (Selftests).
     */
    public static function reset(): void
    {
        self::$chunks = null;
        self::$manifests = null;
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
    public static function manifests(?string $dir = null): array
    {
        self::ensureLoaded($dir);

        return self::$manifests ?? [];
    }

    /**
     * JSONL-Dateien laden (eine Zeile = ein Chunk).
     *
     * @return list<array<string, mixed>>
     */
    public static function loadFromDir(string $dir): array
    {
        $dir = rtrim(str_replace('\\', '/', $dir), '/');
        if ($dir === '' || !is_dir($dir)) {
            self::$chunks = [];
            self::$manifests = [];
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
            $lineNo = 0;
            while (($line = fgets($fh)) !== false) {
                $lineNo++;
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

        $manifests = [];
        foreach (glob($dir . '/*.manifest.json') ?: [] as $mf) {
            $raw = @file_get_contents($mf);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($data)) {
                $data['_file'] = basename($mf);
                $manifests[] = $data;
            }
        }

        self::$chunks = $chunks;
        self::$manifests = $manifests;
        self::$loadedDir = $dir;

        return $chunks;
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
        if ($chunks === []) {
            return [];
        }

        $lawHints = self::lawHintsFromTokens($tokens);
        $paraHints = self::paragraphHintsFromTokens($tokens);

        $scored = [];
        foreach ($chunks as $chunk) {
            $score = self::scoreChunk($chunk, $tokens, $lawHints, $paraHints);
            if ($score > 0) {
                $scored[] = ['chunk' => $chunk, 'score' => $score];
            }
        }

        usort($scored, static function (array $a, array $b): int {
            $cmp = $b['score'] <=> $a['score'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($a['chunk']['source_ref'] ?? ''), (string) ($b['chunk']['source_ref'] ?? ''));
        });

        return array_slice($scored, 0, $limit);
    }

    /**
     * Query-String → Tokens → Suche.
     *
     * @return list<array{chunk: array<string, mixed>, score: int}>
     */
    public static function searchQuery(string $query, int $limit = 5, ?string $dir = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        $tokens = KichelKnowledge::tokenize($query);
        // §-Nummern und Gesetzeskürzel oft als kurze Tokens — ergänzen
        if (preg_match_all('/§\s*(\d+[a-z]?)/iu', $query, $m)) {
            foreach ($m[1] as $num) {
                $tokens[] = mb_strtolower((string) $num, 'UTF-8');
                $tokens[] = '§' . mb_strtolower((string) $num, 'UTF-8');
            }
        }
        foreach (['ustg', 'estg', 'ao', 'hgb'] as $law) {
            if (preg_match('/\b' . $law . '\b/iu', $query)) {
                $tokens[] = $law;
            }
        }
        $tokens = array_values(array_unique($tokens));

        return self::search($tokens, $limit, $dir);
    }

    /**
     * Treffer → KR2-Fakten (type=statute bleibt erhalten).
     *
     * @param list<array{chunk: array<string, mixed>, score: int}> $hits
     * @return list<array{
     *   type: string,
     *   title: string,
     *   body: string,
     *   source_label: string,
     *   source_ref: string,
     *   href: string|null,
     *   confidence: float,
     *   numbers: array<string, float|int>
     * }>
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
                'type' => KichelRagFact::TYPE_STATUTE,
                'title' => (string) ($chunk['title'] ?? ''),
                'body' => (string) ($chunk['body'] ?? ''),
                'source_label' => (string) ($chunk['source_label'] ?? 'GII'),
                'source_ref' => (string) ($chunk['source_ref'] ?? ''),
                'href' => $chunk['href'] ?? null,
                'confidence' => min(1.0, max(0.2, $score / 40.0)),
                'numbers' => is_array($chunk['numbers'] ?? null) ? $chunk['numbers'] : [],
            ]);
            if ($fact !== null) {
                $facts[] = $fact;
            }
        }

        return $facts;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    public static function normalizeChunk(array $row): ?array
    {
        $title = trim((string) ($row['title'] ?? ''));
        $body = trim((string) ($row['body'] ?? ''));
        if ($title === '' && $body === '') {
            return null;
        }
        $type = trim((string) ($row['type'] ?? KichelRagFact::TYPE_STATUTE));
        if ($type === '') {
            $type = KichelRagFact::TYPE_STATUTE;
        }
        $sourceRef = trim((string) ($row['source_ref'] ?? ''));
        if ($sourceRef === '') {
            return null;
        }
        $hrefRaw = $row['href'] ?? null;
        $href = is_string($hrefRaw) && trim($hrefRaw) !== '' ? trim($hrefRaw) : null;

        return [
            'type' => $type,
            'law' => trim((string) ($row['law'] ?? '')),
            'enbez' => trim((string) ($row['enbez'] ?? '')),
            'absatz' => isset($row['absatz']) && $row['absatz'] !== null && $row['absatz'] !== ''
                ? (string) $row['absatz']
                : null,
            'title' => $title !== '' ? $title : $sourceRef,
            'body' => $body,
            'source_label' => trim((string) ($row['source_label'] ?? 'GII')) ?: 'GII',
            'source_ref' => $sourceRef,
            'href' => $href,
            'jurabk' => trim((string) ($row['jurabk'] ?? '')),
            'gii_slug' => trim((string) ($row['gii_slug'] ?? '')),
            'fetched_at' => trim((string) ($row['fetched_at'] ?? '')),
            'confidence' => isset($row['confidence']) ? (float) $row['confidence'] : 0.95,
            'numbers' => is_array($row['numbers'] ?? null) ? $row['numbers'] : [],
            '_hay' => mb_strtolower($title . "\n" . $body, 'UTF-8'),
            '_law_key' => mb_strtolower(trim((string) ($row['law'] ?? '')), 'UTF-8'),
            '_enbez_key' => KichelGiiXmlParser::normalizeRefEnbez((string) ($row['enbez'] ?? '')),
        ];
    }

    /**
     * @param list<string> $tokens
     * @return list<string> lowercase law keys (ustg, ao, …)
     */
    private static function lawHintsFromTokens(array $tokens): array
    {
        $map = [
            'ustg' => 'ustg',
            'umsatzsteuer' => 'ustg',
            'umsatzsteuergesetz' => 'ustg',
            'estg' => 'estg',
            'einkommensteuer' => 'estg',
            'einkommensteuergesetz' => 'estg',
            'ao' => 'ao',
            'abgabenordnung' => 'ao',
            'hgb' => 'hgb',
            'handelsgesetzbuch' => 'hgb',
        ];
        $out = [];
        foreach ($tokens as $t) {
            if (isset($map[$t])) {
                $out[$map[$t]] = $map[$t];
            }
        }

        return array_values($out);
    }

    /**
     * @param list<string> $tokens
     * @return list<string> normalized enbez keys without spaces, e.g. §19
     */
    private static function paragraphHintsFromTokens(array $tokens): array
    {
        $out = [];
        foreach ($tokens as $t) {
            if (preg_match('/^§?\s*(\d+[a-z]?)$/u', $t, $m)) {
                $out['§' . $m[1]] = '§' . $m[1];
            }
        }

        return array_values($out);
    }

    /**
     * @param array<string, mixed> $chunk
     * @param list<string> $tokens
     * @param list<string> $lawHints
     * @param list<string> $paraHints
     */
    private static function scoreChunk(array $chunk, array $tokens, array $lawHints, array $paraHints): int
    {
        $score = 0;
        $hay = (string) ($chunk['_hay'] ?? '');
        $lawKey = (string) ($chunk['_law_key'] ?? '');
        $enbezKey = (string) ($chunk['_enbez_key'] ?? '');

        if ($lawHints !== []) {
            $lawHit = false;
            foreach ($lawHints as $hint) {
                if ($lawKey === $hint) {
                    $score += 12;
                    $lawHit = true;
                    break;
                }
            }
            // Query nennt anderes Gesetz → stark abwerten
            if (!$lawHit && $lawKey !== '') {
                $score -= 8;
            }
        }

        if ($paraHints !== [] && $enbezKey !== '') {
            foreach ($paraHints as $hint) {
                if ($enbezKey === $hint) {
                    $score += 24;
                } elseif (str_starts_with($enbezKey, $hint)) {
                    // §1 trifft §1b nur leicht
                    $score += 4;
                }
            }
        }

        foreach ($tokens as $token) {
            if (mb_strlen($token, 'UTF-8') < 2) {
                continue;
            }
            // reine Gesetzes-/§-Tokens schon oben gewertet
            if (in_array($token, ['ustg', 'estg', 'ao', 'hgb', 'umsatzsteuer', 'einkommensteuer', 'abgabenordnung', 'handelsgesetzbuch'], true)) {
                continue;
            }
            if (preg_match('/^§?\d+[a-z]?$/u', $token)) {
                continue;
            }
            if (str_contains($hay, $token)) {
                $len = mb_strlen($token, 'UTF-8');
                $score += $len >= 6 ? 4 : ($len >= 4 ? 2 : 1);
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
