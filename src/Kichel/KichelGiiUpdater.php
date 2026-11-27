<?php
declare(strict_types=1);

/**
 * KL4 — TOC-Diff Update: Whitelist-Snapshot vergleichen, geänderte Gesetze neu importieren.
 */
final class KichelGiiUpdater
{
    public const SNAPSHOT_FILENAME = 'toc-snapshot.json';

    public static function snapshotPath(?string $baseDir = null): string
    {
        $dir = $baseDir !== null && $baseDir !== ''
            ? rtrim(str_replace('\\', '/', $baseDir), '/')
            : KichelGiiImporter::baseDir();

        return $dir . '/' . self::SNAPSHOT_FILENAME;
    }

    /**
     * @param list<array{abbr: string, slug: string, title: string, link: string}> $resolved
     * @return array{
     *   saved_at: string,
     *   source: string,
     *   laws: array<string, array{abbr: string, slug: string, title: string, link: string, fingerprint: string}>
     * }
     */
    public static function buildSnapshot(array $resolved, string $source = '', ?string $savedAt = null): array
    {
        $laws = [];
        foreach ($resolved as $entry) {
            $abbr = (string) ($entry['abbr'] ?? '');
            if ($abbr === '') {
                continue;
            }
            $row = [
                'abbr' => $abbr,
                'slug' => (string) ($entry['slug'] ?? ''),
                'title' => (string) ($entry['title'] ?? ''),
                'link' => (string) ($entry['link'] ?? ''),
            ];
            $row['fingerprint'] = self::fingerprint($row);
            $laws[$abbr] = $row;
        }
        ksort($laws);

        return [
            'saved_at' => $savedAt ?? gmdate('c'),
            'source' => $source,
            'laws' => $laws,
        ];
    }

    /**
     * @param array{abbr?: string, slug?: string, title?: string, link?: string} $row
     */
    public static function fingerprint(array $row): string
    {
        $payload = implode('|', [
            (string) ($row['slug'] ?? ''),
            (string) ($row['title'] ?? ''),
            (string) ($row['link'] ?? ''),
        ]);

        return hash('sha256', $payload);
    }

    /**
     * @return array{saved_at: string, source: string, laws: array<string, array<string, mixed>}|null
     */
    public static function loadSnapshot(?string $path = null): ?array
    {
        $path = $path ?? self::snapshotPath();
        if (!is_readable($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['laws']) || !is_array($data['laws'])) {
            return null;
        }

        return [
            'saved_at' => (string) ($data['saved_at'] ?? ''),
            'source' => (string) ($data['source'] ?? ''),
            'laws' => $data['laws'],
        ];
    }

    /**
     * @param array{saved_at: string, source: string, laws: array<string, array<string, mixed>>} $snapshot
     */
    public static function saveSnapshot(array $snapshot, ?string $path = null): string
    {
        $path = $path ?? self::snapshotPath();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Snapshot-Ordner fehlt: ' . $dir);
        }
        $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false || file_put_contents($path, $json . "\n") === false) {
            throw new RuntimeException('Snapshot nicht schreibbar: ' . $path);
        }

        return $path;
    }

    /**
     * @param list<array{abbr: string, slug: string, title: string, link: string}> $resolved
     * @param array{laws?: array<string, array<string, mixed>}|null $previous
     * @return array{
     *   changed: list<string>,
     *   unchanged: list<string>,
     *   added: list<string>,
     *   removed: list<string>,
     *   details: array<string, array{status: string, old?: array<string, mixed>, new?: array<string, mixed>}>
     * }
     */
    public static function diff(array $resolved, ?array $previous): array
    {
        $current = self::buildSnapshot($resolved)['laws'];
        $oldLaws = is_array($previous['laws'] ?? null) ? $previous['laws'] : [];

        $changed = [];
        $unchanged = [];
        $added = [];
        $removed = [];
        $details = [];

        foreach ($current as $abbr => $row) {
            if (!isset($oldLaws[$abbr])) {
                $added[] = $abbr;
                $details[$abbr] = ['status' => 'added', 'new' => $row];
                continue;
            }
            $oldFp = (string) ($oldLaws[$abbr]['fingerprint'] ?? self::fingerprint($oldLaws[$abbr]));
            $newFp = (string) ($row['fingerprint'] ?? self::fingerprint($row));
            if ($oldFp !== $newFp) {
                $changed[] = $abbr;
                $details[$abbr] = [
                    'status' => 'changed',
                    'old' => $oldLaws[$abbr],
                    'new' => $row,
                ];
            } else {
                $unchanged[] = $abbr;
                $details[$abbr] = ['status' => 'unchanged', 'new' => $row];
            }
        }

        foreach ($oldLaws as $abbr => $_old) {
            if (!isset($current[$abbr])) {
                $removed[] = $abbr;
                $details[$abbr] = ['status' => 'removed', 'old' => $_old];
            }
        }

        sort($changed);
        sort($unchanged);
        sort($added);
        sort($removed);

        return [
            'changed' => $changed,
            'unchanged' => $unchanged,
            'added' => $added,
            'removed' => $removed,
            'details' => $details,
        ];
    }

    /**
     * TOC laden, Diff gegen Snapshot. Ohne Netz wenn $tocSource Datei ist.
     *
     * @param list<string>|null $laws
     * @return array{
     *   resolved: list<array{abbr: string, slug: string, title: string, link: string}>,
     *   snapshot_path: string,
     *   previous: array<string, mixed>|null,
     *   diff: array{changed: list<string>, unchanged: list<string>, added: list<string>, removed: list<string>, details: array<string, array<string, mixed>>},
     *   to_import: list<string>
     * }
     */
    public static function check(
        ?array $laws = null,
        ?string $tocSource = null,
        ?string $baseDir = null,
        bool $force = false
    ): array {
        $tocSource = $tocSource !== null && $tocSource !== '' ? $tocSource : KichelGiiImporter::TOC_URL;
        $resolved = KichelGiiImporter::resolveToc($tocSource, $laws);
        $snapPath = self::snapshotPath($baseDir);
        $previous = self::loadSnapshot($snapPath);
        $diff = self::diff($resolved, $previous);

        if ($force) {
            $toImport = array_column($resolved, 'abbr');
        } else {
            $toImport = array_values(array_unique(array_merge($diff['changed'], $diff['added'])));
        }

        return [
            'resolved' => $resolved,
            'snapshot_path' => $snapPath,
            'previous' => $previous,
            'diff' => $diff,
            'to_import' => $toImport,
            'toc_source' => $tocSource,
        ];
    }

    /**
     * Diff prüfen und bei Bedarf neu importieren; Snapshot speichern; Index-Cache leeren.
     *
     * @param list<string>|null $laws
     * @return array<string, mixed>
     */
    public static function apply(
        ?array $laws = null,
        ?string $tocSource = null,
        ?string $baseDir = null,
        bool $force = false,
        bool $download = true,
        ?string $fetchedAt = null
    ): array {
        $check = self::check($laws, $tocSource, $baseDir, $force);
        $toImport = $check['to_import'];
        $importResults = [];

        if ($toImport !== [] && $download) {
            // Temporär baseDir umleiten, falls Override
            $writtenVia = null;
            if ($baseDir !== null && $baseDir !== '') {
                $writtenVia = self::runImportIntoDir($toImport, $check['toc_source'], $baseDir, $fetchedAt);
                $importResults = $writtenVia;
            } else {
                $pack = KichelGiiImporter::run($toImport, $check['toc_source'], true, $fetchedAt);
                $importResults = $pack['results'];
            }
            KichelStatuteIndex::reset();
        } elseif ($toImport !== [] && !$download) {
            foreach ($toImport as $abbr) {
                $importResults[] = [
                    'abbr' => $abbr,
                    'skipped_download' => true,
                ];
            }
        }

        $snapshot = self::buildSnapshot($check['resolved'], (string) $check['toc_source'], $fetchedAt);
        $saved = self::saveSnapshot($snapshot, $check['snapshot_path']);

        return [
            'ok' => true,
            'diff' => $check['diff'],
            'to_import' => $toImport,
            'imported' => $importResults,
            'snapshot_path' => $saved,
            'snapshot' => $snapshot,
            'download' => $download,
            'force' => $force,
        ];
    }

    /**
     * Import in abweichendes Zielverzeichnis (Selftest).
     *
     * @param list<string> $laws
     * @return list<array<string, mixed>>
     */
    private static function runImportIntoDir(
        array $laws,
        string $tocSource,
        string $baseDir,
        ?string $fetchedAt
    ): array {
        $resolved = KichelGiiImporter::resolveToc($tocSource, $laws);
        $fetchedAt = $fetchedAt ?? gmdate('c');
        $results = [];
        foreach ($resolved as $entry) {
            $pack = KichelGiiImporter::importLawFromZipUrl($entry, $fetchedAt);
            $written = self::writeChunksToDir($baseDir, $entry['abbr'], $pack['chunks'], [
                'slug' => $entry['slug'],
                'title' => $entry['title'],
                'link' => $entry['link'],
                'fetched_at' => $fetchedAt,
                'xml_basename' => basename($pack['xml_path']),
            ]);
            $results[] = [
                'abbr' => $entry['abbr'],
                'slug' => $entry['slug'],
                'chunk_count' => $written['chunk_count'],
                'jsonl' => $written['jsonl'],
                'manifest' => $written['manifest'],
            ];
        }

        return $results;
    }

    /**
     * @param list<array<string, mixed>> $chunks
     * @param array<string, mixed> $meta
     * @return array{jsonl: string, manifest: string, chunk_count: int}
     */
    public static function writeChunksToDir(string $dir, string $lawAbbr, array $chunks, array $meta = []): array
    {
        $dir = rtrim(str_replace('\\', '/', $dir), '/');
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
}
