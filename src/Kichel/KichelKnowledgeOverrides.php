<?php

declare(strict_types=1);

/**
 * KH1 — Instanz-lokale Knowledge-Ergänzungen (storage/, nicht im Sync-Kern).
 * Gleicher Code überall; Overrides nur in der jeweiligen Instanz-DB/Dateien.
 */
final class KichelKnowledgeOverrides
{
    public static function path(): string
    {
        return DG_ROOT . '/storage/kichel/knowledge-overrides.json';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        $file = self::path();
        if (!is_file($file)) {
            return [];
        }
        try {
            $raw = file_get_contents($file);
            if ($raw === false || trim($raw) === '') {
                return [];
            }
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }
        if (!is_array($data)) {
            return [];
        }

        $out = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            $title = trim((string) ($row['title'] ?? ''));
            $answer = trim((string) ($row['answer'] ?? ''));
            if ($id === '' || ($title === '' && $answer === '')) {
                continue;
            }
            $keywords = $row['keywords'] ?? [];
            if (!is_array($keywords)) {
                $keywords = [];
            }
            $keywords = array_values(array_filter(array_map(
                static fn ($k): string => mb_strtolower(trim((string) $k), 'UTF-8'),
                $keywords
            ), static fn (string $k): bool => $k !== ''));

            $entry = [
                'id' => $id,
                'keywords' => $keywords,
                'title' => $title !== '' ? $title : $id,
                'answer' => $answer,
                'tags' => ['override'],
            ];
            $href = trim((string) ($row['href'] ?? ''));
            if ($href !== '') {
                $entry['href'] = $href;
            }
            $action = trim((string) ($row['action_label'] ?? ''));
            if ($action !== '') {
                $entry['action_label'] = $action;
            }
            $out[] = $entry;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $topic
     */
    public static function upsert(array $topic): bool
    {
        $id = trim((string) ($topic['id'] ?? ''));
        if ($id === '') {
            return false;
        }

        $dir = dirname(self::path());
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        $existing = [];
        $file = self::path();
        if (is_file($file)) {
            try {
                $raw = file_get_contents($file);
                $decoded = $raw !== false ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : null;
                if (is_array($decoded)) {
                    $existing = $decoded;
                }
            } catch (Throwable) {
                $existing = [];
            }
        }

        $normalized = [
            'id' => $id,
            'keywords' => array_values(array_filter(array_map(
                static fn ($k): string => mb_strtolower(trim((string) $k), 'UTF-8'),
                is_array($topic['keywords'] ?? null) ? $topic['keywords'] : []
            ), static fn (string $k): bool => $k !== '')),
            'title' => trim((string) ($topic['title'] ?? $id)),
            'answer' => trim((string) ($topic['answer'] ?? '')),
            'updated_at' => gmdate('c'),
        ];
        $href = trim((string) ($topic['href'] ?? ''));
        if ($href !== '') {
            $normalized['href'] = $href;
        }
        $action = trim((string) ($topic['action_label'] ?? ''));
        if ($action !== '') {
            $normalized['action_label'] = $action;
        }
        if (isset($topic['from_log_id'])) {
            $normalized['from_log_id'] = (int) $topic['from_log_id'];
        }

        $found = false;
        foreach ($existing as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            if (trim((string) ($row['id'] ?? '')) === $id) {
                $existing[$i] = $normalized;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $existing[] = $normalized;
        }

        try {
            $json = json_encode(array_values($existing), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (Throwable) {
            return false;
        }

        return file_put_contents($file, $json . "\n", LOCK_EX) !== false;
    }
}
