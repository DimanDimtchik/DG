<?php

declare(strict_types=1);

/**
 * KH3 — GoBD-Ton: keine frei erfundenen Buchungsvorschläge ohne CRM-Regel.
 *
 * Erlaubt: CRM-Navigation (Belege anlegen, Kontenübersicht öffnen), Beschreibung der CRM-Logik.
 * Verboten: „einfach so buchen“, Kontonummern als Rat, Kontierung aus dem Hut.
 */
final class KichelGobdTone
{
    public const SAFE_REDIRECT = 'Eine konkrete Kontierung gebe ich nicht vor — maßgeblich sind Beleg und die Buchungslogik im CRM. '
        . 'Belege legst du unter Buchhaltung → Belege an; die Verbuchung folgt den hinterlegten Regeln. '
        . 'Bei Unsicherheit die Steuerberatung hinzuziehen.';

    /**
     * Muster für riskante, ungeprüfte Buchungsratschläge (nicht CRM-Navigation).
     *
     * @var list<string>
     */
    private const UNSAFE_PATTERNS = [
        '/\beinfach\s+(so\s+)?(mit\s+)?buch(en|t|e)\b/iu',
        '/\bverbuch(e|en|t)\s+(das|es|es|das|es|die|den)\b/iu',
        '/\bbuch(e|en)\s+(das|es|die|den)\s+(einfach|einfach\s+so|ruhig|einfach\s+mal)\b/iu',
        '/\bauf\s+konto\s+\d{2,6}\b/iu',
        '/\bauf\s+\d{3,5}\s+buch/iu',
        '/\bals\s+(aufwand|ertrags?|erlös|erlos|einnahme|ausgabe|kosten)\s+buch/iu',
        '/\bkontier(e|en|ung)\s+(einfach|einfach\s+so|als|auf)\b/iu',
        '/\bsolltest\s+.+\bbuch(en|t)\b/iu',
        '/\bkannst\s+du\s+(das\s+)?einfach\s+.+\bbuch/iu',
        '/\bohne\s+beleg\s+buch/iu',
        '/\bmanuell\s+auf\s+.+\bbuch(en|t)\b/iu',
    ];

    public static function isUnsafe(string $text): bool
    {
        $normalized = trim($text);
        if ($normalized === '') {
            return false;
        }
        foreach (self::UNSAFE_PATTERNS as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ersetzt riskante Antworten durch den sicheren GoBD-Hinweis.
     */
    public static function filterAnswer(string $answer): string
    {
        if (!self::isUnsafe($answer)) {
            return $answer;
        }

        return self::SAFE_REDIRECT;
    }

    /**
     * @return list<string> Treffer-Muster (für Selftests/Admin)
     */
    public static function matchedPatterns(string $text): array
    {
        $hits = [];
        foreach (self::UNSAFE_PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $hits[] = $pattern;
            }
        }

        return $hits;
    }
}
