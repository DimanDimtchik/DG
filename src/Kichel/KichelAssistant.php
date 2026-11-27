<?php
declare(strict_types=1);

/**
 * Schicht C: Orchestrierung — Antwort aus KichelRetriever (+ optional RAG compose).
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

        $hit = KichelRetriever::retrieve($user, $query);

        return match ($hit['path']) {
            'legal' => self::wrapLegal(is_array($hit['legal'] ?? null) ? $hit['legal'] : []),
            'ledger' => self::wrapSimple(is_array($hit['ledger'] ?? null) ? $hit['ledger'] : [], 'ledger_account'),
            'money' => self::wrapMoney($query, is_array($hit['money'] ?? null) ? $hit['money'] : [], $hit['facts'] ?? []),
            'candidates' => self::wrapCandidates(
                $query,
                $hit['candidates'] ?? [],
                $hit['facts'] ?? [],
                is_array($hit['rank_meta'] ?? null) ? $hit['rank_meta'] : []
            ),
            default => self::emptyResponse(KichelRanker::notFoundMessage()),
        };
    }

    /**
     * @param list<array{kind: string, score: int, title: string, body: string, href: string, action_label: string}> $candidates
     * @param list<array<string, mixed>> $facts
     * @param array<string, mixed> $rankMeta
     * @return array<string, mixed>
     */
    private static function wrapCandidates(string $query, array $candidates, array $facts, array $rankMeta = []): array
    {
        if ($candidates === [] || $facts === []) {
            $response = self::emptyResponse(KichelRanker::notFoundMessage());
            if ($rankMeta !== []) {
                $response['rank_meta'] = $rankMeta;
            }

            return $response;
        }

        if (KichelRetriever::isRagEnabled()) {
            return self::wrapRag($query, $candidates, $facts, $rankMeta);
        }

        if (count($candidates) === 1) {
            $one = self::wrapSingleCandidate($candidates[0]);
            if ($rankMeta !== []) {
                $one['rank_meta'] = $rankMeta;
            }

            return $one;
        }

        $multi = self::wrapMultiCandidates($candidates);
        if ($rankMeta !== []) {
            $multi['rank_meta'] = $rankMeta;
        }

        return $multi;
    }

    /**
     * @param list<array{kind: string, score: int, title: string, body: string, href: string, action_label: string}> $candidates
     * @param list<array<string, mixed>> $facts
     * @param array<string, mixed> $rankMeta
     * @return array<string, mixed>
     */
    private static function wrapRag(string $query, array $candidates, array $facts, array $rankMeta = []): array
    {
        // KR5b — bei mehreren Treffern nummerierte Basis (keine unmarkierte Vermischung)
        $base = count($facts) > 1
            ? KichelRanker::multiFactBaseAnswer($facts)
            : null;
        $answer = KichelOllamaClient::compose($query, $facts, $base);
        $actionLinks = self::actionLinksFromCandidates($candidates);
        $kind = count($candidates) === 1
            ? (string) ($candidates[0]['kind'] ?? 'topic')
            : 'multi_match';

        $out = [
            'presentation' => 'simple',
            'kind' => $kind,
            'answer' => $answer,
            'follow_up' => self::FOLLOW_UP,
            'action_links' => $actionLinks,
            'rag_facts' => $facts,
            'rag_used' => true,
        ];
        if ($rankMeta !== []) {
            $out['rank_meta'] = $rankMeta;
        }

        return $out;
    }

    /**
     * @param list<array{kind: string, score: int, title: string, body: string, href: string, action_label: string}> $candidates
     * @return list<array{label: string, href: string}>
     */
    private static function actionLinksFromCandidates(array $candidates): array
    {
        $actionLinks = [];
        $seenHref = [];
        foreach ($candidates as $candidate) {
            $href = (string) ($candidate['href'] ?? '');
            if ($href === '') {
                continue;
            }
            $hrefKey = mb_strtolower($href, 'UTF-8');
            if (isset($seenHref[$hrefKey])) {
                continue;
            }
            $seenHref[$hrefKey] = true;
            $title = (string) ($candidate['title'] ?? '');
            $label = trim((string) ($candidate['action_label'] ?? ''));
            if ($label === '' || preg_match('/^(Video|Kurs|Bild|Media) öffnen$/u', $label) === 1) {
                $label = self::buttonLabelFromTitle($title);
            }
            $actionLinks[] = [
                'label' => $label,
                'href' => $href,
            ];
        }

        return $actionLinks;
    }

    /**
     * @param array{kind: string, score: int, title: string, body: string, href: string, action_label: string} $candidate
     * @return array<string, mixed>
     */
    private static function wrapSingleCandidate(array $candidate): array
    {
        $title = (string) $candidate['title'];
        $body = trim((string) $candidate['body']);
        $href = (string) $candidate['href'];
        $parts = [];

        if ($candidate['kind'] === 'navigation') {
            $parts[] = 'Das findest du unter „' . $title . '“.';
            if ($body !== '') {
                $parts[] = $body;
            }
        } else {
            if ($body !== '') {
                $parts[] = $body;
            } else {
                $parts[] = $title;
            }
        }

        $actionLinks = [];
        if ($href !== '') {
            $label = trim((string) ($candidate['action_label'] ?? ''));
            if ($label === '' || preg_match('/^(Video|Kurs|Bild|Media) öffnen$/u', $label) === 1) {
                $label = self::buttonLabelFromTitle($title);
            }
            $actionLinks[] = [
                'label' => $label,
                'href' => $href,
            ];
        }

        return [
            'presentation' => 'simple',
            'kind' => (string) $candidate['kind'],
            'answer' => trim(implode("\n\n", $parts)),
            'follow_up' => self::FOLLOW_UP,
            'action_links' => $actionLinks,
            'rag_used' => false,
        ];
    }

    /**
     * @param list<array{kind: string, score: int, title: string, body: string, href: string, action_label: string}> $candidates
     * @return array<string, mixed>
     */
    private static function wrapMultiCandidates(array $candidates): array
    {
        $lines = ['Dazu passen mehrere Stellen im CRM:'];
        $n = 0;
        foreach ($candidates as $candidate) {
            $n++;
            $title = (string) $candidate['title'];
            $body = trim((string) $candidate['body']);
            $line = $n . '. ' . $title;
            if ($body !== '') {
                $line .= ' — ' . $body;
            }
            $lines[] = $line;
        }

        return [
            'presentation' => 'simple',
            'kind' => 'multi_match',
            'answer' => implode("\n\n", $lines),
            'follow_up' => self::FOLLOW_UP,
            'action_links' => self::actionLinksFromCandidates($candidates),
            'rag_used' => false,
        ];
    }

    private static function buttonLabelFromTitle(string $title): string
    {
        $label = trim($title);
        foreach (['Video: ', 'Schulungsvideo: ', 'Media: '] as $prefix) {
            if (str_starts_with($label, $prefix)) {
                $label = trim(substr($label, strlen($prefix)));
                break;
            }
        }

        return $label !== '' ? $label : 'Öffnen';
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
            'rag_used' => false,
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
            'rag_used' => false,
        ];
    }

    /**
     * @param array<string, mixed> $moneyAnswer
     * @param list<array<string, mixed>> $ragFacts
     * @return array<string, mixed>
     */
    private static function wrapMoney(string $query, array $moneyAnswer, array $ragFacts = []): array
    {
        $moneyFacts = is_array($moneyAnswer['facts'] ?? null) ? $moneyAnswer['facts'] : [];
        $baseMoneyAnswer = (string) ($moneyAnswer['answer'] ?? '');
        if ($ragFacts === []) {
            $ragFacts = KichelRagFact::fromMoneyCalculation($moneyFacts, $baseMoneyAnswer);
        }
        $answer = KichelOllamaClient::compose($query, $ragFacts, $baseMoneyAnswer);

        return [
            'presentation' => 'simple',
            'kind' => 'calculation',
            'answer' => $answer,
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
            'rag_facts' => $ragFacts,
            'rag_used' => true,
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
            'rag_used' => false,
        ];
    }
}
