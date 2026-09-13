<?php
declare(strict_types=1);

/**
 * Grobe Absichtserkennung für die Antwort-Orchestrierung (Schicht C).
 */
final class KichelIntent
{
    public const NAVIGATE = 'navigate';
    public const EXPLAIN = 'explain';
    public const CALCULATE = 'calculate';
    public const GENERAL = 'general';

    /**
     * @param list<string> $tokens
     */
    public static function detect(string $query, array $tokens): string
    {
        $normalized = mb_strtolower($query, 'UTF-8');

        if (preg_match('/\d+\s*(?:%|prozent|eur|€)/u', $normalized)
            || (str_contains($normalized, 'skonto') && preg_match('/\d/u', $normalized))) {
            return self::CALCULATE;
        }

        if (preg_match('/\b(was|welche|welches|wie|format|eintragen|bedeutet|pflicht|muss)\b/u', $normalized)) {
            return self::EXPLAIN;
        }

        if (preg_match('/\b(wo|finde|findest|öffne|oeffne|zeige|zeig|navigier|gehe|route)\b/u', $normalized)) {
            return self::NAVIGATE;
        }

        if (preg_match('/\b(anschauen|anzeigen|sehen|gespeichert|verlinken|ändern|aendern|einstellen)\b/u', $normalized)) {
            return self::NAVIGATE;
        }

        return self::GENERAL;
    }
}
