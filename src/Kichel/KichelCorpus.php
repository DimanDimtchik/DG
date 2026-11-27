<?php
declare(strict_types=1);

/**
 * KR4 — CRM-Korpus: einheitliche Chunks (Knowledge / Nav / Feld / Media / Steuerkurztexte).
 */
final class KichelCorpus
{
    /** @var list<array<string, mixed>>|null */
    private static ?array $taxShorttexts = null;

    /**
     * KR4a — Knowledge-Topic → RAG-Fakt (Quelle CRM-Hilfe).
     *
     * @param array<string, mixed> $topic
     * @return array{
     *   type: string,
     *   title: string,
     *   body: string,
     *   source_label: string,
     *   source_ref: string,
     *   href: string|null,
     *   confidence: float,
     *   numbers: array<string, float|int>
     * }|null
     */
    public static function chunkFromTopic(array $topic, int $score = 8): ?array
    {
        $id = trim((string) ($topic['id'] ?? ''));
        $title = trim((string) ($topic['title'] ?? ''));
        $body = trim((string) ($topic['answer'] ?? ''));
        $href = trim((string) ($topic['href'] ?? ''));
        if ($title === '' && $body === '') {
            return null;
        }

        return KichelRagFact::normalize([
            'type' => KichelRagFact::TYPE_TOPIC,
            'title' => $title !== '' ? $title : 'Hilfe',
            'body' => $body,
            'source_label' => 'CRM-Hilfe',
            'source_ref' => $id !== '' ? 'topic:' . $id : 'topic:' . mb_strtolower($title, 'UTF-8'),
            'href' => $href !== '' ? $href : null,
            'confidence' => self::confidenceFromScore($score),
            'numbers' => [],
        ]);
    }

    /**
     * KR4b — Kandidat (Nav/Feld/Media/Topic/Tax) → RAG-Fakt.
     *
     * @param array{kind?: string, score?: int, title?: string, body?: string, href?: string, dedupe?: string, norm_label?: string} $candidate
     * @return array{
     *   type: string,
     *   title: string,
     *   body: string,
     *   source_label: string,
     *   source_ref: string,
     *   href: string|null,
     *   confidence: float,
     *   numbers: array<string, float|int>
     * }|null
     */
    public static function chunkFromCandidate(array $candidate): ?array
    {
        $kind = (string) ($candidate['kind'] ?? KichelRagFact::TYPE_OTHER);
        $type = match ($kind) {
            'navigation' => KichelRagFact::TYPE_NAVIGATION,
            'topic' => KichelRagFact::TYPE_TOPIC,
            'field_help' => KichelRagFact::TYPE_FIELD,
            'tax_note' => KichelRagFact::TYPE_TAX_NOTE,
            'statute' => KichelRagFact::TYPE_STATUTE,
            'caselaw' => KichelRagFact::TYPE_CASELAW,
            'media', 'academy_video', 'academy_course' => KichelRagFact::TYPE_MEDIA,
            default => $kind !== '' ? $kind : KichelRagFact::TYPE_OTHER,
        };

        $title = trim((string) ($candidate['title'] ?? ''));
        $body = trim((string) ($candidate['body'] ?? ''));
        $href = trim((string) ($candidate['href'] ?? ''));
        $dedupe = trim((string) ($candidate['dedupe'] ?? ''));
        $score = (int) ($candidate['score'] ?? 0);
        $normLabel = trim((string) ($candidate['norm_label'] ?? ''));
        $labelOverride = trim((string) ($candidate['source_label'] ?? ''));

        if ($type === KichelRagFact::TYPE_TAX_NOTE && $normLabel !== '' && $body !== '' && !str_contains($body, $normLabel)) {
            $body .= "\nNormhinweis: " . $normLabel . '.';
        }

        $sourceLabel = match ($type) {
            KichelRagFact::TYPE_NAVIGATION => 'CRM-Menü',
            KichelRagFact::TYPE_TOPIC => 'CRM-Hilfe',
            KichelRagFact::TYPE_FIELD => 'CRM-Feldhilfe',
            KichelRagFact::TYPE_MEDIA => 'CRM-Medien',
            KichelRagFact::TYPE_TAX_NOTE => 'CRM-Steuerkurztext',
            KichelRagFact::TYPE_STATUTE => $labelOverride !== '' ? $labelOverride : 'GII',
            KichelRagFact::TYPE_CASELAW => $labelOverride !== '' ? $labelOverride : 'RII',
            default => $labelOverride !== '' ? $labelOverride : 'CRM',
        };

        $sourceRef = $dedupe !== ''
            ? $dedupe
            : ($type . ':' . mb_strtolower($title !== '' ? $title : 'item', 'UTF-8'));

        return KichelRagFact::normalize([
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'source_label' => $sourceLabel,
            'source_ref' => $sourceRef,
            'href' => $href !== '' ? $href : null,
            'confidence' => self::confidenceFromScore($score),
            'numbers' => [],
        ]);
    }

    /**
     * @param list<array{kind: string, score: int, title: string, body: string, href: string, action_label?: string, dedupe?: string, norm_label?: string}> $candidates
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
    public static function factsFromCandidates(array $candidates): array
    {
        $rows = [];
        foreach ($candidates as $candidate) {
            $fact = self::chunkFromCandidate($candidate);
            if ($fact !== null) {
                $rows[] = $fact;
            }
        }

        return $rows;
    }

    /**
     * KR4c — Steuerkurztexte matchen.
     *
     * @param list<string> $tokens
     * @return list<array{entry: array<string, mixed>, score: int}>
     */
    public static function matchTaxShorttexts(array $tokens, int $limit = 5): array
    {
        if ($tokens === []) {
            return [];
        }

        $scored = [];
        foreach (self::taxShorttexts() as $entry) {
            $score = 0;
            $keywords = $entry['keywords'] ?? [];
            if (!is_array($keywords)) {
                continue;
            }
            foreach ($tokens as $token) {
                foreach ($keywords as $keyword) {
                    $kw = mb_strtolower((string) $keyword, 'UTF-8');
                    if ($token === $kw) {
                        $score += 8;
                        continue;
                    }
                    if (mb_strlen($token, 'UTF-8') >= 3 && (str_contains($kw, $token) || str_contains($token, $kw))) {
                        $score += 3;
                    }
                }
                $title = mb_strtolower((string) ($entry['title'] ?? ''), 'UTF-8');
                if ($title !== '' && str_contains($title, $token)) {
                    $score += 1;
                }
            }
            // Query-Tokens wie "19" + "ustg" / "kleinunternehmer"
            if ($score > 0) {
                $scored[] = ['entry' => $entry, 'score' => $score];
            }
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * @param array<string, mixed> $entry
     * @return array{kind: string, score: int, title: string, body: string, href: string, action_label: string, dedupe: string, norm_label: string}
     */
    public static function taxEntryToCandidate(array $entry, int $score): array
    {
        $id = trim((string) ($entry['id'] ?? 'tax'));
        $href = trim((string) ($entry['href'] ?? ''));
        $action = trim((string) ($entry['action_label'] ?? ''));

        return [
            'kind' => 'tax_note',
            'score' => $score,
            'title' => trim((string) ($entry['title'] ?? 'Steuerkurztext')),
            'body' => trim((string) ($entry['body'] ?? '')),
            'href' => $href,
            'action_label' => $action !== '' ? $action : 'Öffnen',
            'dedupe' => 'tax:' . $id,
            'norm_label' => trim((string) ($entry['norm_label'] ?? '')),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function taxShorttexts(): array
    {
        if (self::$taxShorttexts === null) {
            /** @var list<array<string, mixed>> $loaded */
            $loaded = require __DIR__ . '/data/tax-shorttexts.php';
            self::$taxShorttexts = $loaded;
        }

        return self::$taxShorttexts;
    }

    private static function confidenceFromScore(int $score): float
    {
        return min(1.0, max(0.1, $score / 15.0));
    }
}
