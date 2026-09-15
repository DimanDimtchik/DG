<?php
declare(strict_types=1);

/**
 * Durchsucht CRM-Quellcode und Doku (ohne externe API).
 */
final class KichelCodeSearch
{
    private const MAX_RESULTS = 8;
    private const SNIPPET_LEN = 140;

    /** @var list<string> */
    private const ROOTS = ['src', 'views', 'docs'];

    /** @var list<string> */
    private const EXTENSIONS = ['php', 'md', 'js', 'css', 'sql'];

    /**
     * @param list<string> $tokens
     * @return list<array{path: string, line: int, snippet: string, score: int}>
     */
    public static function search(array $tokens, int $limit = self::MAX_RESULTS): array
    {
        if ($tokens === []) {
            return [];
        }

        $results = [];
        foreach (self::ROOTS as $root) {
            $dir = DG_ROOT . '/' . $root;
            if (!is_dir($dir)) {
                continue;
            }
            self::scanDir($dir, $root, $tokens, $results);
        }

        usort($results, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }

    /**
     * @param list<array{path: string, line: int, snippet: string, score: int}> $results
     */
    private static function scanDir(string $absoluteDir, string $relativePrefix, array $tokens, array &$results): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absoluteDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            $ext = strtolower($file->getExtension());
            if (!in_array($ext, self::EXTENSIONS, true)) {
                continue;
            }

            $basename = $file->getFilename();
            if ($basename === 'dg.min.css' || str_starts_with($basename, '.')) {
                continue;
            }

            $relative = $relativePrefix . '/' . str_replace('\\', '/', substr($file->getPathname(), strlen(DG_ROOT . '/' . $relativePrefix) + 1));
            self::searchFile($file->getPathname(), $relative, $tokens, $results);
        }
    }

    /**
     * @param list<array{path: string, line: int, snippet: string, score: int}> $results
     */
    private static function searchFile(string $path, string $relative, array $tokens, array &$results): void
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return;
        }

        $lineNo = 0;
        $bestScore = 0;
        $bestLine = 0;
        $bestSnippet = '';

        while (($line = fgets($handle)) !== false) {
            $lineNo++;
            $lower = mb_strtolower($line, 'UTF-8');
            $lineScore = 0;
            foreach ($tokens as $token) {
                if (str_contains($lower, $token)) {
                    $lineScore += 2;
                    if (str_contains($lower, 'class ' . $token) || str_contains($lower, 'function ' . $token)) {
                        $lineScore += 3;
                    }
                }
            }
            if ($lineScore > $bestScore) {
                $bestScore = $lineScore;
                $bestLine = $lineNo;
                $bestSnippet = trim($line);
            }
        }
        fclose($handle);

        if ($bestScore <= 0) {
            return;
        }

        if (mb_strlen($bestSnippet, 'UTF-8') > self::SNIPPET_LEN) {
            $bestSnippet = mb_substr($bestSnippet, 0, self::SNIPPET_LEN, 'UTF-8') . '…';
        }

        $results[] = [
            'path' => $relative,
            'line' => $bestLine,
            'snippet' => $bestSnippet,
            'score' => $bestScore,
        ];
    }
}
