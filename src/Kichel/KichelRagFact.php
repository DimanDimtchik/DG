<?php
declare(strict_types=1);

/**
 * KR2 — einheitliches Fakten-Schema für RAG (Suche → phrase / Fallback).
 *
 * Jeder Fakt ist ein Assoc-Array mit festen Keys. Keine Beträge erfinden:
 * Zahlen stehen im body und optional unter numbers (für Guards).
 */
final class KichelRagFact
{
    public const TYPE_TOPIC = 'topic';
    public const TYPE_NAVIGATION = 'navigation';
    public const TYPE_FIELD = 'field_help';
    public const TYPE_MEDIA = 'media';
    public const TYPE_LEGAL = 'legal_pages';
    public const TYPE_LEDGER = 'ledger_account';
    public const TYPE_CALCULATION = 'calculation';
    public const TYPE_TAX_NOTE = 'tax_note';
    public const TYPE_STATUTE = 'statute';
    public const TYPE_CASELAW = 'caselaw';
    public const TYPE_OTHER = 'other';

    /**
     * @param array<string, float|int> $numbers
     * @return array{
     *   type: string,
     *   title: string,
     *   body: string,
     *   source_label: string,
     *   source_ref: string,
     *   href: string|null,
     *   confidence: float,
     *   numbers: array<string, float|int>
     * }
     */
    public static function make(
        string $type,
        string $title,
        string $body,
        string $sourceLabel,
        string $sourceRef,
        ?string $href = null,
        float $confidence = 1.0,
        array $numbers = []
    ): array {
        $row = [
            'type' => $type !== '' ? $type : self::TYPE_OTHER,
            'title' => trim($title),
            'body' => trim($body),
            'source_label' => trim($sourceLabel),
            'source_ref' => trim($sourceRef),
            'href' => $href !== null && trim($href) !== '' ? trim($href) : null,
            'confidence' => max(0.0, min(1.0, $confidence)),
            'numbers' => $numbers,
        ];

        $normalized = self::normalize($row);
        if ($normalized === null) {
            throw new InvalidArgumentException('KichelRagFact::make: ungültiger Fakt.');
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $row
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
    public static function normalize(array $row): ?array
    {
        $title = trim((string) ($row['title'] ?? ''));
        $body = trim((string) ($row['body'] ?? ''));
        if ($title === '' && $body === '') {
            return null;
        }

        $type = trim((string) ($row['type'] ?? self::TYPE_OTHER));
        if ($type === '') {
            $type = self::TYPE_OTHER;
        }

        $sourceLabel = trim((string) ($row['source_label'] ?? ''));
        if ($sourceLabel === '') {
            $sourceLabel = 'CRM';
        }

        $sourceRef = trim((string) ($row['source_ref'] ?? ''));
        if ($sourceRef === '') {
            $sourceRef = $type . ':' . mb_strtolower($title !== '' ? $title : mb_substr($body, 0, 40), 'UTF-8');
        }

        $hrefRaw = $row['href'] ?? null;
        $href = is_string($hrefRaw) && trim($hrefRaw) !== '' ? trim($hrefRaw) : null;

        $confidence = isset($row['confidence']) ? (float) $row['confidence'] : 1.0;
        if ($confidence < 0.0) {
            $confidence = 0.0;
        } elseif ($confidence > 1.0) {
            $confidence = 1.0;
        }

        $numbers = [];
        if (isset($row['numbers']) && is_array($row['numbers'])) {
            foreach ($row['numbers'] as $key => $value) {
                if (!is_string($key) && !is_int($key)) {
                    continue;
                }
                if (is_int($value) || is_float($value)) {
                    $numbers[(string) $key] = $value;
                }
            }
        }

        return [
            'type' => $type,
            'title' => $title !== '' ? $title : $sourceLabel,
            'body' => $body,
            'source_label' => $sourceLabel,
            'source_ref' => $sourceRef,
            'href' => $href,
            'confidence' => $confidence,
            'numbers' => $numbers,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
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
    public static function normalizeList(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = self::normalize($row);
            if ($normalized !== null) {
                $out[] = $normalized;
            }
        }

        return $out;
    }

    /**
     * Money-Fakten (flach) → ein RAG-Fakt mit numbers (KR2 Adapter für wrapMoney).
     *
     * @param array<string, string|float|int> $moneyFacts
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
    public static function fromMoneyCalculation(array $moneyFacts, string $baseAnswer): array
    {
        $numbers = [];
        foreach ($moneyFacts as $key => $value) {
            if (is_int($value) || is_float($value)) {
                $numbers[(string) $key] = $value;
            }
        }

        return [
            self::make(
                self::TYPE_CALCULATION,
                'Skonto-Berechnung',
                trim($baseAnswer),
                'CRM-Berechnung (PHP)',
                'money:skonto',
                '/app?page=einstellungen&tab=payment-terms',
                1.0,
                $numbers
            ),
        ];
    }

    /**
     * Antwort ohne Ollama: Basistext oder Bausteine aus Fakten + Quellenzeile.
     *
     * @param list<array<string, mixed>> $facts
     */
    public static function fallbackAnswer(array $facts, ?string $baseAnswer = null): string
    {
        $facts = self::normalizeList($facts);
        $base = trim((string) $baseAnswer);
        if ($base !== '') {
            if (str_contains($base, 'Quelle:')) {
                return $base;
            }
            $sources = self::sourceLines($facts);
            if ($sources === []) {
                return $base;
            }

            return $base . "\n\n" . implode("\n", $sources);
        }

        if ($facts === []) {
            return 'Dazu liegen keine gesicherten Fakten vor. Bitte die Frage mit einem klaren Stichwort erneut stellen.';
        }

        if (count($facts) === 1) {
            $fact = $facts[0];
            $parts = [];
            $body = trim((string) $fact['body']);
            $title = trim((string) $fact['title']);
            if ($body !== '') {
                $parts[] = $body;
            } elseif ($title !== '') {
                $parts[] = $title;
            }
            $parts = array_merge($parts, self::sourceLines([$fact]));

            return trim(implode("\n\n", $parts));
        }

        $lines = ['Dazu passen mehrere gesicherte Hinweise:'];
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
        foreach (self::sourceLines($facts) as $sourceLine) {
            $lines[] = $sourceLine;
        }

        return implode("\n\n", $lines);
    }

    /**
     * @param list<array{
     *   type: string,
     *   title: string,
     *   body: string,
     *   source_label: string,
     *   source_ref: string,
     *   href: string|null,
     *   confidence: float,
     *   numbers: array<string, float|int>
     * }> $facts
     * @return list<string>
     */
    public static function sourceLines(array $facts): array
    {
        $lines = [];
        $seen = [];
        foreach ($facts as $fact) {
            $label = trim((string) ($fact['source_label'] ?? ''));
            $ref = trim((string) ($fact['source_ref'] ?? ''));
            if ($label === '' && $ref === '') {
                continue;
            }
            $text = 'Quelle: ' . ($label !== '' ? $label : $ref);
            if ($ref !== '' && $label !== '' && !str_contains($label, $ref)) {
                $text .= ' (' . $ref . ')';
            }
            $key = mb_strtolower($text, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $lines[] = $text;
        }

        return $lines;
    }

    /**
     * KR6a — strukturierte Quellen für Widget/Super-Kichel.
     *
     * @param list<array<string, mixed>> $facts
     * @return list<array{label: string, ref: string, href: string|null}>
     */
    public static function sourcesForUi(array $facts): array
    {
        $out = [];
        $seen = [];
        foreach (self::normalizeList($facts) as $fact) {
            $label = trim((string) ($fact['source_label'] ?? ''));
            $ref = trim((string) ($fact['source_ref'] ?? ''));
            if ($label === '' && $ref === '') {
                continue;
            }
            $key = mb_strtolower($label . '|' . $ref, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'label' => $label !== '' ? $label : $ref,
                'ref' => $ref,
                'href' => $fact['href'] ?? null,
            ];
        }

        return $out;
    }

    /** Entfernt eingebettete „Quelle:“-Zeilen, wenn die UI `sources` separat zeigt. */
    public static function stripSourceLinesFromAnswer(string $answer): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $answer) ?: [];
        $kept = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                $kept[] = $line;
                continue;
            }
            if (str_starts_with($trim, 'Quelle:')) {
                continue;
            }
            $kept[] = $line;
        }
        $text = implode("\n", $kept);
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Flache Zahlen für number-Guards (RAG-Liste oder Legacy-Assoc).
     *
     * @param list<array<string, mixed>>|array<string, mixed> $facts
     * @return array<string, float|int>
     */
    public static function collectNumbers(array $facts): array
    {
        if ($facts === []) {
            return [];
        }

        $isList = array_is_list($facts);
        if (!$isList) {
            $out = [];
            foreach ($facts as $key => $value) {
                if (is_int($value) || is_float($value)) {
                    $out[(string) $key] = $value;
                }
            }

            return $out;
        }

        $out = [];
        foreach (self::normalizeList($facts) as $i => $fact) {
            foreach ($fact['numbers'] as $key => $value) {
                $out[$key] = $value;
            }
            // Fallback: wenn numbers leer, trotzdem unique prefix vermeiden
            unset($i);
        }

        return $out;
    }
}
