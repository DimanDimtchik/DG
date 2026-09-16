<?php
declare(strict_types=1);

/**
 * Schicht C: Orchestrierung — Navigation, Felder, Spezial-Logik.
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

        $intent = KichelIntent::detect($query, $tokens);

        $legalAnswer = KichelLegalPages::tryAnswer($query, $tokens);
        if ($legalAnswer !== null) {
            return self::wrapLegal($legalAnswer);
        }

        $ledgerAnswer = KichelLedgerAccounts::tryAnswer($query);
        if ($ledgerAnswer !== null) {
            return self::wrapSimple($ledgerAnswer, 'ledger_account');
        }

        $moneyAnswer = KichelMoneyLogic::tryAnswer($query);
        if ($moneyAnswer !== null && $intent === KichelIntent::CALCULATE) {
            return self::wrapMoney($query, $moneyAnswer);
        }

        $fieldAnswer = KichelFieldCatalog::tryAnswer($query, $tokens);
        $navMatches = KichelNavIndex::search($user, $query, $tokens, 3);
        $topNav = $navMatches[0] ?? null;
        $navScore = (int) ($topNav['score'] ?? 0);
        $fieldScore = $fieldAnswer !== null ? 5 : 0;

        if ($intent === KichelIntent::EXPLAIN && $fieldAnswer !== null) {
            return self::wrapSimple($fieldAnswer, 'field_help');
        }

        if ($intent === KichelIntent::NAVIGATE && $topNav !== null && $navScore >= 4) {
            return self::wrapNav($topNav['entry'], $navScore);
        }

        if ($fieldAnswer !== null && ($intent === KichelIntent::EXPLAIN || $navScore < 5)) {
            return self::wrapSimple($fieldAnswer, 'field_help');
        }

        if ($topNav !== null && $navScore >= 5) {
            return self::wrapNav($topNav['entry'], $navScore);
        }

        if ($moneyAnswer !== null) {
            return self::wrapMoney($query, $moneyAnswer);
        }

        $topicMatches = KichelKnowledge::matchTopics($tokens, 3);
        if ($topicMatches !== [] && (int) ($topicMatches[0]['score'] ?? 0) >= 6) {
            return self::wrapTopic($topicMatches[0]);
        }

        if ($topNav !== null && $navScore >= 3) {
            return self::wrapNav($topNav['entry'], $navScore, true);
        }

        return self::emptyResponse(
            'Dazu habe ich nichts Passendes im CRM gefunden. Formuliere die Frage mit einem Menüpunkt '
            . '(z. B. „Belege“, „Firmendaten“, „Support-Freigabe“) oder einem Stichwort wie „USt-ID“ oder „Logo“.'
        );
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function wrapNav(array $entry, int $score, bool $uncertain = false): array
    {
        $label = (string) ($entry['label'] ?? 'Bereich');
        $description = trim((string) ($entry['description'] ?? ''));
        $section = trim((string) ($entry['section'] ?? ''));
        $href = (string) ($entry['href'] ?? '');

        $parts = [];
        if ($uncertain || $score < 5) {
            $parts[] = 'Meinst du vermutlich „' . $label . '“?';
        } else {
            $parts[] = 'Das findest du unter „' . $label . '“' . ($section !== '' ? ' (' . $section . ')' : '') . '.';
        }
        if ($description !== '') {
            $parts[] = $description;
        }

        $actionLinks = [];
        if ($href !== '') {
            $actionLinks[] = [
                'label' => $label . ' öffnen',
                'href' => $href,
            ];
        }

        return [
            'presentation' => 'simple',
            'kind' => 'navigation',
            'answer' => trim(implode("\n\n", $parts)),
            'follow_up' => self::FOLLOW_UP,
            'action_links' => $actionLinks,
        ];
    }

    /**
     * @param array{topic: array<string, mixed>, score: int} $match
     * @return array<string, mixed>
     */
    private static function wrapTopic(array $match): array
    {
        $topic = $match['topic'];
        $text = trim((string) ($topic['answer'] ?? ''));
        $href = (string) ($topic['href'] ?? '');
        $actionLinks = [];
        if ($href !== '') {
            $actionLinks[] = [
                'label' => trim((string) ($topic['action_label'] ?? '')) ?: 'Direkt dorthin',
                'href' => $href,
            ];
        }

        return [
            'presentation' => 'simple',
            'kind' => 'topic',
            'answer' => $text,
            'follow_up' => self::FOLLOW_UP,
            'action_links' => $actionLinks,
        ];
    }

    /**
     * @param array<string, mixed> $legalAnswer
     * @return array<string, mixed>
     */
    private static function wrapLegal(array $legalAnswer): array
    {
        return [
            'presentation' => 'simple',
            'kind' => 'legal_pages',
            'answer' => (string) ($legalAnswer['answer'] ?? ''),
            'follow_up' => self::FOLLOW_UP,
            'action_links' => [],
            'page_links' => $legalAnswer['page_links'] ?? [],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function wrapSimple(array $payload, string $kind): array
    {
        return [
            'presentation' => 'simple',
            'kind' => $kind,
            'answer' => (string) ($payload['answer'] ?? ''),
            'follow_up' => self::FOLLOW_UP,
            'action_links' => $payload['action_links'] ?? [],
        ];
    }

    /**
     * @param array<string, mixed> $moneyAnswer
     * @return array<string, mixed>
     */
    private static function wrapMoney(string $query, array $moneyAnswer): array
    {
        $moneyFacts = $moneyAnswer['facts'] ?? [];
        $baseMoneyAnswer = (string) ($moneyAnswer['answer'] ?? '');
        $phrased = KichelOllamaClient::phrase($query, $baseMoneyAnswer, $moneyFacts);

        return [
            'presentation' => 'simple',
            'kind' => 'calculation',
            'answer' => $phrased ?? $baseMoneyAnswer,
            'follow_up' => self::FOLLOW_UP,
            'action_links' => [[
                'label' => 'Zahlungsbedingungen öffnen',
                'href' => '/app?page=einstellungen&tab=payment-terms',
            ]],
            'calculation' => [
                'kind' => $moneyAnswer['kind'] ?? '',
                'facts' => $moneyFacts,
                'computed_by' => 'php',
            ],
        ];
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
