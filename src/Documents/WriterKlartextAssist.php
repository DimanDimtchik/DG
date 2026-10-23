<?php

declare(strict_types=1);

/**
 * Lokale Klartext-/Lesbarkeits-Hinweise (D4) — kein Cloud, nur Heuristik.
 * Assist, nie Pflicht.
 */
final class WriterKlartextAssist
{
    /** @var list<string> */
    private const FILLERS = [
        'im rahmen',
        'diesbezüglich',
        'bezüglich',
        'hiermit möchten wir',
        'hiermit teilen wir',
        'unter berücksichtigung',
        'in diesem zusammenhang',
        'zwecks',
        'seitens',
        'gegebenenfalls',
        'ggf.',
        'grundsätzlich',
        'nichtsdestotrotz',
        'dementsprechend',
        'insofern',
        'soweit ersichtlich',
    ];

    /**
     * @return array{
     *   ok: bool,
     *   score: int,
     *   summary: string,
     *   metrics: array<string, int|float|string>,
     *   tips: list<array{level: string, title: string, detail: string}>
     * }
     */
    public static function analyze(string $text): array
    {
        $text = self::normalize($text);
        if ($text === '') {
            return [
                'ok' => true,
                'score' => 100,
                'summary' => 'Kein Text — nichts zu prüfen.',
                'metrics' => [
                    'chars' => 0,
                    'words' => 0,
                    'sentences' => 0,
                    'avg_sentence_words' => 0,
                    'reading_ease' => 0,
                ],
                'tips' => [],
            ];
        }

        $words = self::words($text);
        $sentences = self::sentences($text);
        $wordCount = count($words);
        $sentenceCount = max(1, count($sentences));
        $avgSentence = $wordCount / $sentenceCount;
        $syllables = 0;
        foreach ($words as $w) {
            $syllables += self::estimateSyllablesDe($w);
        }
        $asw = $wordCount > 0 ? $syllables / $wordCount : 0.0;
        // Amstad-Variante (Deutsch, Annäherung)
        $readingEase = 180.0 - $avgSentence - (58.5 * $asw);
        $readingEase = max(0.0, min(100.0, $readingEase));

        $tips = [];
        $penalty = 0;

        if ($avgSentence > 28) {
            $penalty += 18;
            $tips[] = [
                'level' => 'warn',
                'title' => 'Sätze eher lang',
                'detail' => sprintf(
                    'Durchschnitt %.1f Wörter/Satz. Ziel für Klartext oft unter 20–25. Lange Sätze teilen.',
                    $avgSentence
                ),
            ];
        } elseif ($avgSentence > 22) {
            $penalty += 8;
            $tips[] = [
                'level' => 'info',
                'title' => 'Sätze etwas lang',
                'detail' => sprintf('Durchschnitt %.1f Wörter/Satz — kürzen hilft der Lesbarkeit.', $avgSentence),
            ];
        }

        foreach ($sentences as $i => $sentence) {
            $sw = count(self::words($sentence));
            if ($sw >= 40) {
                $penalty += 10;
                $tips[] = [
                    'level' => 'warn',
                    'title' => 'Sehr langer Satz',
                    'detail' => sprintf(
                        'Satz %d hat ca. %d Wörter: „%s…“',
                        $i + 1,
                        $sw,
                        mb_substr($sentence, 0, 80)
                    ),
                ];
                if (count($tips) > 12) {
                    break;
                }
            }
        }

        $lower = mb_strtolower($text);
        foreach (self::FILLERS as $filler) {
            if (mb_strpos($lower, $filler) !== false) {
                $penalty += 4;
                $tips[] = [
                    'level' => 'info',
                    'title' => 'Füllformel',
                    'detail' => '„' . $filler . '“ wirkt oft behördlich — direkter formulieren.',
                ];
            }
        }

        $nominal = 0;
        foreach ($words as $w) {
            $lw = mb_strtolower($w);
            if (preg_match('/(ung|heit|keit|nis|schaft)$/u', $lw) === 1 && mb_strlen($lw) > 6) {
                $nominal++;
            }
        }
        $nominalRatio = $wordCount > 0 ? $nominal / $wordCount : 0.0;
        if ($nominalRatio > 0.12 && $nominal >= 4) {
            $penalty += 10;
            $tips[] = [
                'level' => 'info',
                'title' => 'Viel Nominalstil',
                'detail' => sprintf(
                    'Viele Wörter auf -ung/-heit/-keit (ca. %.0f %%). Verben machen den Text lebendiger.',
                    $nominalRatio * 100
                ),
            ];
        }

        if (preg_match('/\b(wird|wurde|worden|wurden)\b/ui', $text) === 1
            && preg_match('/\b(von|durch)\b/ui', $text) === 1) {
            $penalty += 6;
            $tips[] = [
                'level' => 'info',
                'title' => 'Passiv möglich',
                'detail' => 'Passivkonstruktionen (wird/wurde … von/durch) können aktiv klarer sein.',
            ];
        }

        if ($readingEase < 40) {
            $penalty += 15;
            $tips[] = [
                'level' => 'warn',
                'title' => 'Schwer lesbar',
                'detail' => sprintf(
                    'Lesbarkeitswert ca. %.0f (0–100, höher = leichter). Kürzere Sätze und geläufige Wörter helfen.',
                    $readingEase
                ),
            ];
        } elseif ($readingEase < 55) {
            $penalty += 6;
            $tips[] = [
                'level' => 'info',
                'title' => 'Mittlere Lesbarkeit',
                'detail' => sprintf('Lesbarkeitswert ca. %.0f — für Geschäftsbriefe oft ausreichend, aber noch steigerbar.', $readingEase),
            ];
        }

        // Deduplicate tip titles roughly
        $seen = [];
        $unique = [];
        foreach ($tips as $tip) {
            $key = $tip['title'] . '|' . mb_substr($tip['detail'], 0, 40);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $tip;
        }
        $tips = array_slice($unique, 0, 10);

        $score = max(0, min(100, 100 - $penalty));
        $ok = $score >= 70 && $tips === [];
        if ($tips === [] && $score >= 70) {
            $summary = 'Klartext wirkt ausgewogen — keine Auffälligkeiten.';
        } elseif ($score >= 70) {
            $summary = 'Grundsätzlich verständlich; ein paar Hinweise zur Feinschliff.';
        } elseif ($score >= 45) {
            $summary = 'Mehrere Stellen könnten klarer werden (Assist, kein Muss).';
        } else {
            $summary = 'Text wirkt schwer — Sätze kürzen und Füllformeln streichen hilft stark.';
        }

        return [
            'ok' => $ok,
            'score' => $score,
            'summary' => $summary,
            'metrics' => [
                'chars' => mb_strlen($text),
                'words' => $wordCount,
                'sentences' => count($sentences),
                'avg_sentence_words' => round($avgSentence, 1),
                'reading_ease' => round($readingEase, 1),
            ],
            'tips' => $tips,
        ];
    }

    private static function normalize(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @return list<string>
     */
    private static function words(string $text): array
    {
        if ($text === '') {
            return [];
        }
        if (preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'-]*/u', $text, $m) !== 1) {
            return [];
        }

        return $m[0];
    }

    /**
     * @return list<string>
     */
    private static function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+/u', $text) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }

        return $out !== [] ? $out : ($text !== '' ? [$text] : []);
    }

    private static function estimateSyllablesDe(string $word): int
    {
        $w = mb_strtolower($word);
        $w = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $w);
        $w = preg_replace('/[^a-z]/', '', $w) ?? $w;
        if ($w === '') {
            return 1;
        }
        if (preg_match_all('/[aeiouy]+/i', $w, $m) === 1) {
            return max(1, count($m[0]));
        }

        return 1;
    }
}
