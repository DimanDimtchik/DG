<?php
declare(strict_types=1);

/**
 * Schicht B: Felder pro Bereich — Was eintragen, welches Format.
 */
final class KichelFieldCatalog
{
    /** @var list<array<string, mixed>>|null */
    private static ?array $areas = null;

    /**
     * @return list<array<string, mixed>>
     */
    private static function areas(): array
    {
        if (self::$areas === null) {
            /** @var list<array<string, mixed>> $loaded */
            $loaded = require __DIR__ . '/data/fields.php';
            self::$areas = $loaded;
        }

        return self::$areas;
    }

    /**
     * @param list<string> $tokens
     * @return array{
     *   kind: string,
     *   answer: string,
     *   action_links: list<array{label: string, href: string}>,
     *   area_id: string
     * }|null
     */
    public static function tryAnswer(string $query, array $tokens): ?array
    {
        if ($tokens === []) {
            return null;
        }

        $best = null;
        $bestScore = 0;

        foreach (self::areas() as $area) {
            foreach ($area['fields'] as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $score = self::scoreField($field, $tokens, $query);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = ['area' => $area, 'field' => $field, 'score' => $score];
                }
            }
        }

        if ($best === null || $bestScore < 4) {
            return null;
        }

        return self::buildResponse($best['area'], $best['field']);
    }

    /**
     * @param array<string, mixed> $field
     * @param list<string> $tokens
     */
    private static function scoreField(array $field, array $tokens, string $query): int
    {
        $keywords = $field['keywords'] ?? [];
        if (!is_array($keywords)) {
            return 0;
        }

        $normalizedQuery = mb_strtolower($query, 'UTF-8');
        $score = 0;

        foreach ($keywords as $keyword) {
            $kw = mb_strtolower((string) $keyword, 'UTF-8');
            if ($kw === '') {
                continue;
            }
            if (str_contains($normalizedQuery, $kw)) {
                $score += 6;
            }
        }

        foreach ($tokens as $token) {
            foreach ($keywords as $keyword) {
                $kw = mb_strtolower((string) $keyword, 'UTF-8');
                if ($token === $kw) {
                    $score += 5;
                } elseif (mb_strlen($token, 'UTF-8') >= 3 && (str_contains($kw, $token) || str_contains($token, $kw))) {
                    $score += 2;
                }
            }
            $label = mb_strtolower((string) ($field['label'] ?? ''), 'UTF-8');
            if ($label !== '' && mb_strlen($token, 'UTF-8') >= 3 && str_contains($label, $token)) {
                $score += 3;
            }
        }

        return $score;
    }

    /**
     * @param array<string, mixed> $area
     * @param array<string, mixed> $field
     * @return array{
     *   kind: string,
     *   answer: string,
     *   action_links: list<array{label: string, href: string}>,
     *   area_id: string
     * }
     */
    private static function buildResponse(array $area, array $field): array
    {
        $areaLabel = (string) ($area['area_label'] ?? 'Bereich');
        $href = (string) ($area['href'] ?? '');
        $intro = trim((string) ($area['intro'] ?? ''));
        $fieldLabel = (string) ($field['label'] ?? '');
        $format = trim((string) ($field['format'] ?? ''));
        $hint = trim((string) ($field['hint'] ?? ''));

        $parts = [];
        $parts[] = 'Das findest du unter **' . $areaLabel . '**.';
        if ($intro !== '') {
            $parts[] = $intro;
        }
        if ($fieldLabel !== '') {
            $fieldBlock = '**' . $fieldLabel . ':** ' . $hint;
            if ($format !== '') {
                $fieldBlock .= ' Format: ' . $format . '.';
            }
            $parts[] = $fieldBlock;
        }

        return [
            'kind' => 'field_help',
            'area_id' => (string) ($area['area_id'] ?? ''),
            'answer' => str_replace('**', '', implode("\n\n", $parts)),
            'action_links' => $href !== '' ? [[
                'label' => $areaLabel . ' öffnen',
                'href' => $href,
            ]] : [],
        ];
    }
}
