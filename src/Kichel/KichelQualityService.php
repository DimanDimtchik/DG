<?php

declare(strict_types=1);

/**
 * KH1 — Admin-Qualität: schlecht markieren, Warteschlange, Knowledge übernehmen.
 */
final class KichelQualityService
{
    public const STATUS_BAD = 'bad';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_DONE = 'done';

    /** @var list<string> */
    public const STATUSES = [self::STATUS_BAD, self::STATUS_QUEUED, self::STATUS_DONE];

    /**
     * @return array{title: string, keywords: list<string>, answer: string, href: string}
     */
    public static function draftFromLog(array $row): array
    {
        $query = trim((string) ($row['query_text'] ?? ''));
        $answer = trim((string) ($row['answer_text'] ?? ''));
        $note = self::decodeNote(isset($row['quality_note']) ? (string) $row['quality_note'] : null);

        $title = trim((string) ($note['title'] ?? ''));
        if ($title === '') {
            $title = mb_substr($query !== '' ? $query : 'Hilfe-Thema', 0, 80);
        }

        $keywords = $note['keywords'] ?? [];
        if (!is_array($keywords) || $keywords === []) {
            $keywords = KichelKnowledge::tokenize($query);
        }
        $keywords = array_values(array_filter(array_map(
            static fn ($k): string => mb_strtolower(trim((string) $k), 'UTF-8'),
            $keywords
        ), static fn (string $k): bool => $k !== ''));

        $body = trim((string) ($note['answer'] ?? ''));
        if ($body === '') {
            $body = $answer;
        }

        $href = trim((string) ($note['href'] ?? ''));
        if ($href === '') {
            $meta = KichelProtocolRepository::metaFromResponseJson(
                isset($row['response_json']) ? (string) $row['response_json'] : null
            );
            foreach ($meta['sources'] as $source) {
                $candidate = trim((string) ($source['href'] ?? ''));
                if ($candidate !== '') {
                    $href = $candidate;
                    break;
                }
            }
        }

        return [
            'title' => $title,
            'keywords' => $keywords,
            'answer' => $body,
            'href' => $href,
        ];
    }

    /**
     * @param array{title?: string, keywords?: string|list<string>, answer?: string, href?: string} $input
     * @return array{ok: bool, message: string}
     */
    public static function mark(int $logId, string $status, int $adminUserId, array $input = []): array
    {
        if (!in_array($status, self::STATUSES, true)) {
            return ['ok' => false, 'message' => 'Unbekannter Status.'];
        }
        $row = KichelProtocolRepository::find($logId);
        if ($row === null) {
            return ['ok' => false, 'message' => 'Eintrag nicht gefunden.'];
        }

        $draft = self::draftFromLog($row);
        if (isset($input['title'])) {
            $draft['title'] = trim((string) $input['title']);
        }
        if (array_key_exists('answer', $input)) {
            $draft['answer'] = trim((string) $input['answer']);
        }
        if (array_key_exists('href', $input)) {
            $draft['href'] = trim((string) $input['href']);
        }
        if (array_key_exists('keywords', $input)) {
            $raw = $input['keywords'];
            if (is_string($raw)) {
                $parts = preg_split('/[,;\n]+/u', $raw) ?: [];
            } elseif (is_array($raw)) {
                $parts = $raw;
            } else {
                $parts = [];
            }
            $draft['keywords'] = array_values(array_filter(array_map(
                static fn ($k): string => mb_strtolower(trim((string) $k), 'UTF-8'),
                $parts
            ), static fn (string $k): bool => $k !== ''));
        }

        $noteJson = self::encodeNote($draft);
        if (!KichelProtocolRepository::setQuality($logId, $status, $noteJson, $adminUserId)) {
            return ['ok' => false, 'message' => 'Speichern fehlgeschlagen.'];
        }

        $labels = [
            self::STATUS_BAD => 'Als schlecht markiert.',
            self::STATUS_QUEUED => 'In die Knowledge-Warteschlange gelegt.',
            self::STATUS_DONE => 'Als erledigt markiert.',
        ];

        return ['ok' => true, 'message' => $labels[$status] ?? 'Gespeichert.'];
    }

    /**
     * Übernimmt Draft in Instanz-Overrides und setzt Status done.
     *
     * @param array{title?: string, keywords?: string|list<string>, answer?: string, href?: string} $input
     * @return array{ok: bool, message: string}
     */
    public static function applyToKnowledge(int $logId, int $adminUserId, array $input = []): array
    {
        $row = KichelProtocolRepository::find($logId);
        if ($row === null) {
            return ['ok' => false, 'message' => 'Eintrag nicht gefunden.'];
        }

        $queued = self::mark($logId, self::STATUS_QUEUED, $adminUserId, $input);
        if (!$queued['ok']) {
            return $queued;
        }
        $row = KichelProtocolRepository::find($logId);
        if ($row === null) {
            return ['ok' => false, 'message' => 'Eintrag nicht gefunden.'];
        }

        $draft = self::draftFromLog($row);
        if ($draft['title'] === '' || $draft['answer'] === '') {
            return ['ok' => false, 'message' => 'Titel und Antworttext sind nötig.'];
        }
        if ($draft['keywords'] === []) {
            $draft['keywords'] = KichelKnowledge::tokenize($draft['title'] . ' ' . $draft['answer']);
        }

        $topicId = 'override-log-' . $logId;
        $ok = KichelKnowledgeOverrides::upsert([
            'id' => $topicId,
            'keywords' => $draft['keywords'],
            'title' => $draft['title'],
            'answer' => $draft['answer'],
            'href' => $draft['href'],
            'action_label' => $draft['href'] !== '' ? 'Öffnen' : null,
            'from_log_id' => $logId,
        ]);
        if (!$ok) {
            return ['ok' => false, 'message' => 'Knowledge-Datei konnte nicht geschrieben werden.'];
        }

        KichelKnowledge::resetTopicsCache();
        if (!KichelProtocolRepository::setQuality($logId, self::STATUS_DONE, self::encodeNote($draft), $adminUserId)) {
            return ['ok' => false, 'message' => 'Übernommen, Status konnte nicht gesetzt werden.'];
        }

        return ['ok' => true, 'message' => 'In die Hilfe-Wissensbasis der Instanz übernommen.'];
    }

    /**
     * Nutzer-Feedback (Widget/Super): „Nein“ → bad.
     *
     * @return array{ok: bool, message: string}
     */
    public static function applyUserFeedback(int $logId, string $rating, int $userId): array
    {
        $rating = strtolower(trim($rating));
        if (!in_array($rating, ['yes', 'no'], true)) {
            return ['ok' => false, 'message' => 'Ungültiges Feedback.'];
        }
        $row = KichelProtocolRepository::find($logId);
        if ($row === null) {
            return ['ok' => false, 'message' => 'Eintrag nicht gefunden.'];
        }
        if ($rating === 'yes') {
            return ['ok' => true, 'message' => 'Danke.'];
        }
        $existing = trim((string) ($row['quality_status'] ?? ''));
        if ($existing === self::STATUS_DONE || $existing === self::STATUS_QUEUED) {
            return ['ok' => true, 'message' => 'Bereits in Bearbeitung.'];
        }

        return self::mark($logId, self::STATUS_BAD, $userId);
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            self::STATUS_BAD => 'Schlecht',
            self::STATUS_QUEUED => 'Warteschlange',
            self::STATUS_DONE => 'Erledigt',
            default => '—',
        };
    }

    /**
     * @return array{title?: string, keywords?: list<string>, answer?: string, href?: string}
     */
    public static function decodeNote(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return ['answer' => $json];
        }
        if (!is_array($data)) {
            return [];
        }

        return $data;
    }

    /**
     * @param array{title: string, keywords: list<string>, answer: string, href: string} $draft
     */
    public static function encodeNote(array $draft): string
    {
        try {
            return json_encode([
                'title' => $draft['title'],
                'keywords' => $draft['keywords'],
                'answer' => $draft['answer'],
                'href' => $draft['href'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (Throwable) {
            return '';
        }
    }
}
