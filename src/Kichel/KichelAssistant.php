<?php
declare(strict_types=1);

/**
 * Orchestriert Fachwissen, Code- und DB-Suche für Kichel.
 * Standard: kurze Endnutzer-Antworten mit direkten Links.
 */
final class KichelAssistant
{
    private const FOLLOW_UP = 'War das hilfreich? Wenn nicht, formuliere die Frage bitte etwas kürzer — zum Beispiel mit einem Stichwort von oben.';

    /**
     * @return array<string, mixed>
     */
    public static function answer(User $user, string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return self::emptyResponse('Stell mir eine Frage — zum Beispiel „Wo trage ich die USt-ID ein?“ oder „Pflichtseiten“.');
        }

        $tokens = KichelKnowledge::tokenize($query);
        if ($tokens === []) {
            $tokens = KichelKnowledge::tokenize(preg_replace('/\s+/', ' ', $query) ?? $query);
        }

        $technical = self::isTechnicalQuery($query, $tokens);
        $moneyAnswer = KichelMoneyLogic::tryAnswer($query);
        $moneyFacts = $moneyAnswer['facts'] ?? [];
        $legalAnswer = KichelLegalPages::tryAnswer($query, $tokens);

        $topicMatches = KichelKnowledge::matchTopics($tokens);
        $navigation = KichelKnowledge::navigationHints($user, $tokens);
        $codeHits = $technical ? KichelCodeSearch::search($tokens) : [];
        $schemaHits = $technical ? KichelSchemaCatalog::searchSchema($tokens) : [];
        $companyFields = $technical ? KichelSchemaCatalog::matchCompanyFields($tokens) : [];

        $topics = array_map(static fn (array $m): array => $m['topic'], $topicMatches);
        $answerParts = [];
        $actionLinks = [];

        if ($legalAnswer !== null) {
            $answerParts[] = (string) $legalAnswer['answer'];
        } elseif ($moneyAnswer !== null) {
            $baseMoneyAnswer = (string) $moneyAnswer['answer'];
            $phrased = KichelOllamaClient::phrase($query, $baseMoneyAnswer, $moneyFacts);
            $answerParts[] = 'Du meinst wahrscheinlich eine Rechenfrage im CRM. '
                . ($phrased ?? $baseMoneyAnswer);
        } elseif ($topics !== []) {
            $topic = $topics[0];
            $title = (string) ($topic['title'] ?? 'dieses Thema');
            $answerParts[] = 'Du meinst wahrscheinlich „' . $title . '“. '
                . self::plainLanguage((string) ($topic['answer'] ?? ''));
            $href = (string) ($topic['href'] ?? '');
            if ($href !== '') {
                $actionLinks[] = [
                    'label' => 'Direkt dorthin',
                    'href' => $href,
                ];
            }
        } elseif ($navigation !== []) {
            $nav = $navigation[0];
            $answerParts[] = 'Du meinst vielleicht „' . ($nav['label'] ?? 'einen Menüpunkt')
                . '“ im CRM?';
            if (($nav['href'] ?? '') !== '') {
                $actionLinks[] = [
                    'label' => (string) $nav['label'],
                    'href' => (string) $nav['href'],
                ];
            }
        } else {
            $answerParts[] = 'Dazu finde ich gerade kein passendes Thema. '
                . 'Versuch es mit einem kurzen Stichwort — zum Beispiel „USt-ID“, „Skonto“ oder „Pflichtseiten“.';
        }

        if ($technical && $companyFields !== []) {
            $lines = [];
            foreach ($companyFields as $field) {
                $lines[] = ucfirst($field['label']) . ': ' . $field['value'];
            }
            $answerParts[] = 'Aus deinen Firmendaten: ' . implode(' · ', $lines);
        }

        if ($technical && $schemaHits !== []) {
            $tableLines = [];
            foreach (array_slice($schemaHits, 0, 3) as $hit) {
                $cols = implode(', ', array_slice($hit['columns'], 0, 6));
                $count = $hit['row_count'] ?? null;
                $suffix = $count !== null ? ' (' . number_format($count, 0, ',', '.') . ' Datensätze)' : '';
                $tableLines[] = $hit['table'] . $suffix . ($cols !== '' ? ' — Spalten: ' . $cols : '');
            }
            $answerParts[] = 'Datenbank-Schema: ' . implode(' | ', $tableLines);
        }

        if ($technical && $codeHits !== []) {
            $answerParts[] = 'Im Quellcode habe ich passende Stellen gefunden — siehe unten.';
        }

        $response = [
            'presentation' => $technical ? 'technical' : 'simple',
            'answer' => trim(implode("\n\n", array_filter($answerParts))),
            'follow_up' => self::FOLLOW_UP,
            'action_links' => $actionLinks,
            'topics' => [],
            'navigation' => $technical ? $navigation : [],
            'code' => $technical ? array_map(static fn (array $hit): array => [
                'path' => $hit['path'],
                'line' => $hit['line'],
                'snippet' => $hit['snippet'],
            ], $codeHits) : [],
            'database' => $technical ? $schemaHits : [],
            'company' => $technical ? $companyFields : [],
            'hints' => [],
        ];

        if ($moneyAnswer !== null) {
            $response['calculation'] = [
                'kind' => $moneyAnswer['kind'],
                'facts' => $moneyFacts,
                'computed_by' => 'php',
            ];
        }
        if ($legalAnswer !== null) {
            $response['page_links'] = $legalAnswer['page_links'];
            $response['overview_links'] = $legalAnswer['overview_links'];
        }

        return $response;
    }

    /**
     * @param list<string> $tokens
     */
    private static function isTechnicalQuery(string $query, array $tokens): bool
    {
        if (preg_match('/\b(dg_[a-z0-9_]+|src\/|views\/|migration|schema|repository|autoload|\.php|sql)\b/ui', $query)) {
            return true;
        }
        foreach ($tokens as $token) {
            if (str_starts_with($token, 'dg_') || str_contains($token, '_note')) {
                return true;
            }
        }

        return false;
    }

    private static function plainLanguage(string $text): string
    {
        $text = preg_replace('/\s*Siehe docs\/[^\s.]+\.[^.\s]*\.?/u', '', $text) ?? $text;
        $text = preg_replace('/\s*Details:\s*docs\/[^\s.]+\.[^.\s]*\.?/u', '', $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyResponse(string $message): array
    {
        return [
            'presentation' => 'simple',
            'answer' => $message,
            'follow_up' => self::FOLLOW_UP,
            'action_links' => [],
            'topics' => [],
            'navigation' => [],
            'code' => [],
            'database' => [],
            'company' => [],
            'hints' => [],
        ];
    }
}
