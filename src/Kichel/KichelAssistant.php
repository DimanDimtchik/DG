<?php
declare(strict_types=1);

/**
 * Orchestriert Fachwissen, Code- und DB-Suche für Kichel.
 * Standard: kurze Endnutzer-Antworten mit direkten Links.
 */
final class KichelAssistant
{
    private const FOLLOW_UP = 'War das hilfreich?';

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

        $moneyAnswer = KichelMoneyLogic::tryAnswer($query);
        $moneyFacts = $moneyAnswer['facts'] ?? [];
        $legalAnswer = KichelLegalPages::tryAnswer($query, $tokens);
        $ledgerAnswer = KichelLedgerAccounts::tryAnswer($query);

        $topicMatches = KichelKnowledge::matchTopics($tokens);
        $navigation = KichelKnowledge::navigationHints($user, $tokens);

        $answerParts = [];
        $actionLinks = [];

        if ($legalAnswer !== null) {
            $answerParts[] = (string) $legalAnswer['answer'];
        } elseif ($ledgerAnswer !== null) {
            $answerParts[] = (string) $ledgerAnswer['answer'];
            foreach ($ledgerAnswer['action_links'] as $link) {
                $actionLinks[] = $link;
            }
        } elseif ($moneyAnswer !== null) {
            $baseMoneyAnswer = (string) $moneyAnswer['answer'];
            $phrased = KichelOllamaClient::phrase($query, $baseMoneyAnswer, $moneyFacts);
            $answerParts[] = $phrased ?? $baseMoneyAnswer;
            $actionLinks[] = [
                'label' => 'Skonto-Einstellungen öffnen',
                'href' => '/app?page=einstellungen&tab=payment-terms',
            ];
        } elseif ($topicMatches !== []) {
            $match = $topicMatches[0];
            $topic = $match['topic'];
            $score = (int) ($match['score'] ?? 0);
            $text = self::plainLanguage((string) ($topic['answer'] ?? ''));
            if ($score < 4) {
                $title = (string) ($topic['title'] ?? '');
                $text = $title !== ''
                    ? 'Du meinst vielleicht „' . $title . '“: ' . $text
                    : $text;
            }
            $answerParts[] = $text;
            $href = (string) ($topic['href'] ?? '');
            if ($href !== '') {
                $label = trim((string) ($topic['action_label'] ?? ''));
                $actionLinks[] = [
                    'label' => $label !== '' ? $label : 'Direkt dorthin',
                    'href' => $href,
                ];
            }
        } elseif ($navigation !== []) {
            $nav = $navigation[0];
            $answerParts[] = 'Meinst du „' . ($nav['label'] ?? 'einen Menüpunkt') . '“?';
            if (($nav['href'] ?? '') !== '') {
                $actionLinks[] = [
                    'label' => (string) $nav['label'],
                    'href' => (string) $nav['href'],
                ];
            }
        } else {
            $answerParts[] = 'Dazu habe ich nichts Passendes gefunden. Probiere ein Stichwort wie „USt-ID“, „Skonto“ oder „Pflichtseiten“.';
        }

        $response = [
            'presentation' => 'simple',
            'answer' => trim(implode("\n\n", array_filter($answerParts))),
            'follow_up' => self::FOLLOW_UP,
            'action_links' => $actionLinks,
        ];

        if ($moneyAnswer !== null) {
            $response['calculation'] = [
                'kind' => $moneyAnswer['kind'],
                'facts' => $moneyFacts,
                'computed_by' => 'php',
            ];
        }
        if ($legalAnswer !== null) {
            $response['kind'] = 'legal_pages';
            $response['page_links'] = $legalAnswer['page_links'];
        } elseif ($ledgerAnswer !== null) {
            $response['kind'] = 'ledger_account';
        }

        return $response;
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
        ];
    }
}
