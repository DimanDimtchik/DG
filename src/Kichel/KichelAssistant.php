<?php
declare(strict_types=1);

/**
 * Schicht C: Orchestrierung — Navigation, Felder, Spezial-Logik.
 */
final class KichelAssistant
{
    private const FOLLOW_UP = 'War das hilfreich?';

    /** Mindest-Score für einen Treffer in der Mehrfach-Liste. */
    private const MULTI_MIN_SCORE = 3;

    /** Maximal angezeigte Treffer. */
    private const MULTI_MAX = 5;

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
        $navMatches = KichelNavIndex::search($user, $query, $tokens, 8);
        $topicMatches = KichelKnowledge::matchTopics($tokens, 8);
        $candidates = self::collectCandidates($navMatches, $topicMatches, $fieldAnswer, $intent, $query, $tokens);

        if ($moneyAnswer !== null && $candidates === []) {
            return self::wrapMoney($query, $moneyAnswer);
        }

        if ($candidates === []) {
            return self::emptyResponse(
                'Dazu habe ich nichts Passendes im CRM gefunden. Formuliere die Frage mit einem Menüpunkt '
                . '(z. B. „Belege“, „Firmendaten“, „Support-Freigabe“) oder einem Stichwort wie „USt-ID“ oder „Logo“.'
            );
        }

        if (count($candidates) === 1) {
            return self::wrapSingleCandidate($candidates[0]);
        }

        return self::wrapMultiCandidates($candidates, $query);
    }

    /**
     * @param list<array{entry: array<string, mixed>, score: int}> $navMatches
     * @param list<array{topic: array<string, mixed>, score: int}> $topicMatches
     * @param array<string, mixed>|null $fieldAnswer
     * @param list<string> $tokens
     * @return list<array{kind: string, score: int, title: string, body: string, href: string, action_label: string}>
     */
    private static function collectCandidates(
        array $navMatches,
        array $topicMatches,
        ?array $fieldAnswer,
        string $intent,
        string $query,
        array $tokens
    ): array {
        $raw = [];

        foreach ($navMatches as $match) {
            $score = (int) ($match['score'] ?? 0);
            // Navigation nur mit klarem Treffer — sonst erscheinen zu viele Menüpunkte mit „Beleg“ im Text.
            if ($score < 6) {
                continue;
            }
            $entry = $match['entry'] ?? [];
            if (!is_array($entry)) {
                continue;
            }
            $label = trim((string) ($entry['label'] ?? ''));
            $href = trim((string) ($entry['href'] ?? ''));
            if ($label === '' || $href === '') {
                continue;
            }
            $section = trim((string) ($entry['section'] ?? ''));
            $description = trim((string) ($entry['description'] ?? ''));
            $bodyParts = [];
            if ($section !== '') {
                $bodyParts[] = 'Bereich: ' . $section;
            }
            if ($description !== '') {
                $bodyParts[] = $description;
            }
            $raw[] = [
                'kind' => 'navigation',
                'score' => $score,
                'title' => $label,
                'body' => implode(' — ', $bodyParts),
                'href' => $href,
                'action_label' => $label . ' öffnen',
                'dedupe' => mb_strtolower($href, 'UTF-8'),
            ];
        }

        foreach ($topicMatches as $match) {
            $score = (int) ($match['score'] ?? 0);
            if ($score < 4) {
                continue;
            }
            $topic = $match['topic'] ?? [];
            if (!is_array($topic)) {
                continue;
            }
            $title = trim((string) ($topic['title'] ?? 'Thema'));
            $answer = trim((string) ($topic['answer'] ?? ''));
            $href = trim((string) ($topic['href'] ?? ''));
            $actionLabel = trim((string) ($topic['action_label'] ?? '')) ?: 'Direkt dorthin';
            $raw[] = [
                'kind' => 'topic',
                'score' => $score,
                'title' => $title,
                'body' => $answer,
                'href' => $href,
                'action_label' => $actionLabel,
                'dedupe' => $href !== ''
                    ? mb_strtolower($href, 'UTF-8')
                    : ('topic:' . mb_strtolower((string) ($topic['id'] ?? $title), 'UTF-8')),
            ];
        }

        if ($fieldAnswer !== null && ($intent === KichelIntent::EXPLAIN || $raw === [])) {
            $links = is_array($fieldAnswer['action_links'] ?? null) ? $fieldAnswer['action_links'] : [];
            $href = '';
            $actionLabel = 'Einstellungen öffnen';
            if ($links !== []) {
                $href = (string) ($links[0]['href'] ?? '');
                $actionLabel = (string) ($links[0]['label'] ?? $actionLabel);
            }
            $raw[] = [
                'kind' => 'field_help',
                'score' => 5,
                'title' => 'Feldhilfe',
                'body' => trim((string) ($fieldAnswer['answer'] ?? '')),
                'href' => $href,
                'action_label' => $actionLabel,
                'dedupe' => $href !== '' ? mb_strtolower($href, 'UTF-8') : 'field:' . md5((string) ($fieldAnswer['answer'] ?? '')),
            ];
        }

        // Verwandte Treffer ergänzen (z. B. Angebot → Belege + Nummernkreise).
        foreach (self::relatedCandidates($query, $tokens) as $related) {
            $raw[] = $related;
        }

        if ($raw === []) {
            return [];
        }

        usort($raw, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $out = [];
        $seen = [];
        foreach ($raw as $row) {
            if ((int) $row['score'] < self::MULTI_MIN_SCORE) {
                continue;
            }
            $key = (string) $row['dedupe'];
            if ($key !== '' && isset($seen[$key])) {
                $existingIdx = $seen[$key];
                $existing = $out[$existingIdx];
                if (mb_strlen((string) $row['body'], 'UTF-8') > mb_strlen((string) $existing['body'], 'UTF-8')) {
                    $row['score'] = max((int) $existing['score'], (int) $row['score']);
                    $out[$existingIdx] = $row;
                } elseif ((int) $row['score'] > (int) $existing['score']) {
                    $existing['score'] = (int) $row['score'];
                    $out[$existingIdx] = $existing;
                }
                continue;
            }
            if (count($out) >= self::MULTI_MAX) {
                continue;
            }
            if ($key !== '') {
                $seen[$key] = count($out);
            }
            $out[] = $row;
        }

        usort($out, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $out;
    }

    /**
     * Zusätzliche verwandte Treffer, die allein stehend unvollständig wären.
     *
     * @param list<string> $tokens
     * @return list<array{kind: string, score: int, title: string, body: string, href: string, action_label: string, dedupe: string}>
     */
    private static function relatedCandidates(string $query, array $tokens): array
    {
        $q = mb_strtolower(trim($query), 'UTF-8');
        $out = [];

        $docTokens = ['angebot', 'angebote', 'rechnung', 'rechnungen', 'lieferschein', 'auftragsbestätigung', 'auftragsbestaetigung', 'schlussrechnung', 'abschlagsrechnung'];
        $wantsDoc = false;
        foreach ($tokens as $token) {
            if (in_array($token, $docTokens, true)) {
                $wantsDoc = true;
                break;
            }
        }
        if (
            str_contains($q, 'angebot')
            || str_contains($q, 'rechnung')
            || str_contains($q, 'lieferschein')
            || str_contains($q, 'belegkette')
        ) {
            $wantsDoc = true;
        }

        $wantsNumberOnly = str_contains($q, 'nummernkreis')
            || str_contains($q, 'belegnummer')
            || str_contains($q, 'rechnungsnummer')
            || str_contains($q, 'angebotsnummer');

        if ($wantsDoc) {
            $out[] = [
                'kind' => 'topic',
                'score' => 12,
                'title' => 'Belege / Belegkette',
                'body' => 'Angebot, Auftragsbestätigung, Lieferschein und Rechnung unter Buchhaltung → Belege anlegen (Neuer Beleg → Einnahmen → Dokumentart). Folgebelege über „Folgebeleg erstellen“.',
                'href' => '/app?page=buchhaltung-belege',
                'action_label' => 'Belege öffnen',
                'dedupe' => '/app?page=buchhaltung-belege',
            ];
            if (!$wantsNumberOnly) {
                $out[] = [
                    'kind' => 'navigation',
                    'score' => 7,
                    'title' => 'Nummernkreise',
                    'body' => 'Angebots-, Rechnungs- und weitere Belegnummern unter Einstellungen → Organisation → Nummernkreise.',
                    'href' => '/app?page=einstellungen&tab=nummernkreise',
                    'action_label' => 'Nummernkreise öffnen',
                    'dedupe' => '/app?page=einstellungen&tab=nummernkreise',
                ];
            }
        }

        if ($wantsNumberOnly) {
            $out[] = [
                'kind' => 'navigation',
                'score' => 14,
                'title' => 'Nummernkreise',
                'body' => 'Präfixe und Zähler für Angebot, Rechnung, Lieferschein usw. unter Einstellungen → Organisation → Nummernkreise.',
                'href' => '/app?page=einstellungen&tab=nummernkreise',
                'action_label' => 'Nummernkreise öffnen',
                'dedupe' => '/app?page=einstellungen&tab=nummernkreise',
            ];
            $out[] = [
                'kind' => 'topic',
                'score' => 8,
                'title' => 'Belege',
                'body' => 'Die Nummern werden beim Speichern des jeweiligen Belegs vergeben — Belege unter Buchhaltung → Belege.',
                'href' => '/app?page=buchhaltung-belege',
                'action_label' => 'Belege öffnen',
                'dedupe' => '/app?page=buchhaltung-belege',
            ];
        }

        return $out;
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
            $actionLinks[] = [
                'label' => (string) $candidate['action_label'],
                'href' => $href,
            ];
        }

        return [
            'presentation' => 'simple',
            'kind' => (string) $candidate['kind'],
            'answer' => trim(implode("\n\n", $parts)),
            'follow_up' => self::FOLLOW_UP,
            'action_links' => $actionLinks,
        ];
    }

    /**
     * @param list<array{kind: string, score: int, title: string, body: string, href: string, action_label: string}> $candidates
     * @return array<string, mixed>
     */
    private static function wrapMultiCandidates(array $candidates, string $query): array
    {
        unset($query);
        $lines = ['Dazu passen mehrere Stellen im CRM:'];
        $actionLinks = [];
        $n = 0;
        foreach ($candidates as $candidate) {
            $n++;
            $title = (string) $candidate['title'];
            $body = trim((string) $candidate['body']);
            $href = (string) $candidate['href'];
            $line = $n . '. ' . $title;
            if ($body !== '') {
                $line .= ' — ' . $body;
            }
            $lines[] = $line;
            if ($href !== '') {
                $actionLinks[] = [
                    'label' => (string) $candidate['action_label'],
                    'href' => $href,
                ];
            }
        }

        return [
            'presentation' => 'simple',
            'kind' => 'multi_match',
            'answer' => implode("\n\n", $lines),
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
