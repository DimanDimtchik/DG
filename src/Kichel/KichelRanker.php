<?php
declare(strict_types=1);

/**
 * KR5 — Ranking & Leerfall: nur ausreichend starke Treffer, Top-K klar getrennt.
 */
final class KichelRanker
{
    /** Mindest-Score für einen akzeptierten Kandidaten (nach Quell-Filtern). */
    public const MIN_ACCEPT_SCORE = 5;

    /** Maximal an phrase/Antwort übergebene Treffer. */
    public const TOP_K = 4;

    /**
     * Schwächerer Treffer nur behalten, wenn Score >= Top * RELATIVE_FLOOR
     * (verhindert „irgendwas mit Beleg“ neben einem klaren Top-Treffer).
     */
    public const RELATIVE_FLOOR = 0.5;

    /**
     * @param list<array{kind: string, score: int, title: string, body: string, href: string, action_label: string, dedupe?: string, norm_label?: string}> $candidates
     * @return array{
     *   candidates: list<array{kind: string, score: int, title: string, body: string, href: string, action_label: string, dedupe?: string, norm_label?: string}>,
     *   meta: array{top_score: int, kept: int, rejected: int, reason: string}
     * }
     */
    public static function select(array $candidates): array
    {
        if ($candidates === []) {
            return [
                'candidates' => [],
                'meta' => [
                    'top_score' => 0,
                    'kept' => 0,
                    'rejected' => 0,
                    'reason' => 'none',
                ],
            ];
        }

        usort($candidates, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $topScore = (int) ($candidates[0]['score'] ?? 0);
        if ($topScore < self::MIN_ACCEPT_SCORE) {
            return [
                'candidates' => [],
                'meta' => [
                    'top_score' => $topScore,
                    'kept' => 0,
                    'rejected' => count($candidates),
                    'reason' => 'below_min_score',
                ],
            ];
        }

        $floor = (int) max(self::MIN_ACCEPT_SCORE, (int) floor($topScore * self::RELATIVE_FLOOR));
        $kept = [];
        $rejected = 0;
        foreach ($candidates as $row) {
            $score = (int) ($row['score'] ?? 0);
            if ($score < $floor) {
                $rejected++;
                continue;
            }
            if (count($kept) >= self::TOP_K) {
                $rejected++;
                continue;
            }
            $kept[] = $row;
        }

        return [
            'candidates' => $kept,
            'meta' => [
                'top_score' => $topScore,
                'kept' => count($kept),
                'rejected' => $rejected,
                'reason' => $kept === [] ? 'filtered_empty' : 'ok',
            ],
        ];
    }

    public static function notFoundMessage(): string
    {
        return 'Dazu habe ich nichts Passendes im CRM gefunden. '
            . 'Formuliere die Frage mit einem klaren Stichwort — z. B. „USt-ID“, „Belege“, '
            . '„Kleinunternehmer“, „Ist-Versteuerung“, „Skonto 3 % von 1000 Euro“ oder „Pflichtseiten“.';
    }

    /**
     * Nummerierte Basis für phrase (ohne Quellenzeilen — die hängt compose/fallback an).
     *
     * @param list<array<string, mixed>> $facts
     */
    public static function multiFactBaseAnswer(array $facts): string
    {
        $facts = KichelRagFact::normalizeList($facts);
        if ($facts === []) {
            return '';
        }
        if (count($facts) === 1) {
            $body = trim((string) $facts[0]['body']);
            $title = trim((string) $facts[0]['title']);

            return $body !== '' ? $body : $title;
        }

        $lines = ['Dazu passen mehrere gesicherte Hinweise (bitte getrennt lesen, nicht vermischen):'];
        $n = 0;
        foreach ($facts as $fact) {
            $n++;
            $title = trim((string) $fact['title']);
            $body = trim((string) $fact['body']);
            $line = $n . '. ' . ($title !== '' ? $title : 'Hinweis');
            if ($body !== '') {
                $line .= ' — ' . $body;
            }
            $lines[] = $line;
        }

        return implode("\n\n", $lines);
    }
}
