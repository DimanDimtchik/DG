<?php
declare(strict_types=1);

/**
 * KR3 — eine Abrufkette: Pflichtpfade zuerst, dann freie Suche → KR2-Fakten (+ Budget).
 */
final class KichelRetriever
{
    public const DEFAULT_MAX_FACTS = 5;
    public const DEFAULT_MAX_CHARS = 4000;

    /** Vor-Ranking: max. Rohkandidaten (Feinfilter = KichelRanker). */
    private const MULTI_MAX = 8;

    /** KL3 — Mindest-Score aus Statute-Index, bevor Norm-Kandidat mitläuft. */
    private const STATUTE_MIN_SCORE = 8;

    /**
     * @return array{
     *   path: 'empty'|'legal'|'ledger'|'money'|'candidates',
     *   query: string,
     *   intent: string,
     *   tokens: list<string>,
     *   legal: array<string, mixed>|null,
     *   ledger: array<string, mixed>|null,
     *   money: array<string, mixed>|null,
     *   candidates: list<array{kind: string, score: int, title: string, body: string, href: string, action_label: string, dedupe: string}>,
     *   facts: list<array{
     *     type: string,
     *     title: string,
     *     body: string,
     *     source_label: string,
     *     source_ref: string,
     *     href: string|null,
     *     confidence: float,
     *     numbers: array<string, float|int>
     *   }>,
     *   rank_meta: array{top_score: int, kept: int, rejected: int, reason: string}
     * }
     */
    public static function retrieve(User $user, string $query): array
    {
        $query = trim($query);
        $tokens = KichelKnowledge::tokenize($query);
        if ($tokens === []) {
            $tokens = KichelKnowledge::tokenize(preg_replace('/\s+/', ' ', $query) ?? $query);
        }
        $intent = KichelIntent::detect($query, $tokens);

        $empty = [
            'path' => 'empty',
            'query' => $query,
            'intent' => $intent,
            'tokens' => $tokens,
            'legal' => null,
            'ledger' => null,
            'money' => null,
            'candidates' => [],
            'facts' => [],
            'rank_meta' => [
                'top_score' => 0,
                'kept' => 0,
                'rejected' => 0,
                'reason' => 'none',
            ],
        ];

        if ($query === '') {
            return $empty;
        }

        // KR3b — Pflichtpfade vor freier Suche
        $legalAnswer = KichelLegalPages::tryAnswer($query, $tokens);
        if ($legalAnswer !== null) {
            return array_merge($empty, [
                'path' => 'legal',
                'legal' => $legalAnswer,
                'facts' => self::factsFromLegal($legalAnswer),
            ]);
        }

        $ledgerAnswer = KichelLedgerAccounts::tryAnswer($query);
        if ($ledgerAnswer !== null) {
            return array_merge($empty, [
                'path' => 'ledger',
                'ledger' => $ledgerAnswer,
                'facts' => self::factsFromLedger($ledgerAnswer),
            ]);
        }

        $moneyAnswer = KichelMoneyLogic::tryAnswer($query);
        if ($moneyAnswer !== null && $intent === KichelIntent::CALCULATE) {
            $base = (string) ($moneyAnswer['answer'] ?? '');
            $moneyFacts = is_array($moneyAnswer['facts'] ?? null) ? $moneyAnswer['facts'] : [];

            return array_merge($empty, [
                'path' => 'money',
                'money' => $moneyAnswer,
                'facts' => self::applyBudget(KichelRagFact::fromMoneyCalculation($moneyFacts, $base)),
            ]);
        }

        $fieldAnswer = KichelFieldCatalog::tryAnswer($query, $tokens);
        $navMatches = KichelNavIndex::search($user, $query, $tokens, 8);
        $topicMatches = KichelKnowledge::matchTopics($tokens, 8);
        $mediaMatches = KichelMediaSearch::search($user, $query, $tokens, 8);
        $candidates = self::collectCandidates($navMatches, $topicMatches, $fieldAnswer, $mediaMatches, $intent, $query, $tokens);

        if ($moneyAnswer !== null && $candidates === []) {
            $base = (string) ($moneyAnswer['answer'] ?? '');
            $moneyFacts = is_array($moneyAnswer['facts'] ?? null) ? $moneyAnswer['facts'] : [];

            return array_merge($empty, [
                'path' => 'money',
                'money' => $moneyAnswer,
                'facts' => self::applyBudget(KichelRagFact::fromMoneyCalculation($moneyFacts, $base)),
            ]);
        }

        // KR5 — Ranking / Leerfall (keine schwachen Treffer an phrase)
        $ranked = KichelRanker::select($candidates);
        $candidates = $ranked['candidates'];
        if ($candidates === []) {
            return array_merge($empty, [
                'rank_meta' => $ranked['meta'],
            ]);
        }

        $facts = self::applyBudget(KichelCorpus::factsFromCandidates($candidates));
        if ($facts === []) {
            return array_merge($empty, [
                'rank_meta' => array_merge($ranked['meta'], ['reason' => 'no_facts']),
            ]);
        }

        return array_merge($empty, [
            'path' => 'candidates',
            'money' => $moneyAnswer,
            'candidates' => $candidates,
            'facts' => $facts,
            'rank_meta' => $ranked['meta'],
        ]);
    }

    public static function isRagEnabled(): bool
    {
        $cfg = self::config();
        if (!array_key_exists('rag_enabled', $cfg)) {
            return true;
        }

        return (bool) $cfg['rag_enabled'];
    }

    /** KL3 — GII-Normen aus JSONL-Index in der freien Suche. */
    public static function isStatuteEnabled(): bool
    {
        $cfg = self::config();
        if (!array_key_exists('rag_statute_enabled', $cfg)) {
            return true;
        }

        return (bool) $cfg['rag_statute_enabled'];
    }

    public static function maxFacts(): int
    {
        $cfg = self::config();
        $n = (int) ($cfg['rag_max_facts'] ?? self::DEFAULT_MAX_FACTS);

        return $n > 0 ? $n : self::DEFAULT_MAX_FACTS;
    }

    public static function maxChars(): int
    {
        $cfg = self::config();
        $n = (int) ($cfg['rag_max_chars'] ?? self::DEFAULT_MAX_CHARS);

        return $n > 0 ? $n : self::DEFAULT_MAX_CHARS;
    }

    /**
     * @param list<array<string, mixed>> $facts
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
    public static function applyBudget(array $facts): array
    {
        $facts = KichelRagFact::normalizeList($facts);
        $maxFacts = self::maxFacts();
        $maxChars = self::maxChars();
        $out = [];
        $chars = 0;
        foreach ($facts as $fact) {
            if (count($out) >= $maxFacts) {
                break;
            }
            $piece = (string) $fact['title'] . "\n" . (string) $fact['body'] . "\n" . (string) $fact['source_label'];
            $len = mb_strlen($piece, 'UTF-8');
            if ($out !== [] && ($chars + $len) > $maxChars) {
                break;
            }
            $out[] = $fact;
            $chars += $len;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function config(): array
    {
        $file = DG_ROOT . '/config/kichel.local.php';
        if (!is_readable($file)) {
            return [];
        }
        $cfg = require $file;

        return is_array($cfg) ? $cfg : [];
    }

    /**
     * @param array<string, mixed> $legalAnswer
     * @return list<array<string, mixed>>
     */
    private static function factsFromLegal(array $legalAnswer): array
    {
        return self::applyBudget([
            KichelRagFact::make(
                KichelRagFact::TYPE_LEGAL,
                'Pflichtseiten',
                (string) ($legalAnswer['answer'] ?? ''),
                'CRM-Rechtstexte',
                'legal:pflichtseiten',
                null,
                1.0
            ),
        ]);
    }

    /**
     * @param array<string, mixed> $ledgerAnswer
     * @return list<array<string, mixed>>
     */
    private static function factsFromLedger(array $ledgerAnswer): array
    {
        $href = null;
        $links = is_array($ledgerAnswer['action_links'] ?? null) ? $ledgerAnswer['action_links'] : [];
        if ($links !== []) {
            $href = (string) ($links[0]['href'] ?? '') ?: null;
        }

        return self::applyBudget([
            KichelRagFact::make(
                KichelRagFact::TYPE_LEDGER,
                'Konto',
                (string) ($ledgerAnswer['answer'] ?? ''),
                'CRM-Konten',
                'ledger:match',
                $href,
                1.0
            ),
        ]);
    }

    /**
     * @param list<array{entry: array<string, mixed>, score: int}> $navMatches
     * @param list<array{topic: array<string, mixed>, score: int}> $topicMatches
     * @param array<string, mixed>|null $fieldAnswer
     * @param list<array{kind: string, score: int, title: string, body: string, href: string, action_label: string, dedupe: string}> $mediaMatches
     * @param list<string> $tokens
     * @return list<array{kind: string, score: int, title: string, body: string, href: string, action_label: string, dedupe: string, norm_label?: string}>
     */
    private static function collectCandidates(
        array $navMatches,
        array $topicMatches,
        ?array $fieldAnswer,
        array $mediaMatches,
        string $intent,
        string $query,
        array $tokens
    ): array {
        $raw = [];

        foreach ($mediaMatches as $match) {
            $score = (int) ($match['score'] ?? 0);
            if ($score < 4) {
                continue;
            }
            $raw[] = [
                'kind' => (string) ($match['kind'] ?? 'media'),
                'score' => $score,
                'title' => (string) ($match['title'] ?? 'Medien'),
                'body' => (string) ($match['body'] ?? ''),
                'href' => (string) ($match['href'] ?? ''),
                'action_label' => (string) ($match['action_label'] ?? 'Öffnen'),
                'dedupe' => (string) ($match['dedupe'] ?? ''),
            ];
        }

        foreach ($navMatches as $match) {
            $score = (int) ($match['score'] ?? 0);
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
            $topicId = (string) ($topic['id'] ?? $title);
            $raw[] = [
                'kind' => 'topic',
                'score' => $score,
                'title' => $title,
                'body' => $answer,
                'href' => $href,
                'action_label' => $actionLabel,
                'dedupe' => 'topic:' . mb_strtolower($topicId, 'UTF-8'),
            ];
        }

        // KR4c — Steuerkurztexte
        foreach (KichelCorpus::matchTaxShorttexts($tokens, 5) as $taxMatch) {
            $score = (int) ($taxMatch['score'] ?? 0);
            if ($score < 4) {
                continue;
            }
            $entry = $taxMatch['entry'] ?? [];
            if (!is_array($entry)) {
                continue;
            }
            $raw[] = KichelCorpus::taxEntryToCandidate($entry, $score);
        }

        // KL3 — GII-Normen (Volltext-Index)
        if (self::isStatuteEnabled()) {
            foreach (self::statuteCandidates($query, $tokens) as $statuteRow) {
                $raw[] = $statuteRow;
            }
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
            // Feine Schwelle setzt KichelRanker::select (KR5); hier nur Dedup + Cap.
            if ((int) $row['score'] < 1) {
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
     * KL3 — Statute-Index → Kandidaten (kind=statute).
     *
     * @param list<string> $tokens
     * @return list<array{kind: string, score: int, title: string, body: string, href: string, action_label: string, dedupe: string, source_label?: string}>
     */
    private static function statuteCandidates(string $query, array $tokens): array
    {
        $cfg = self::config();
        $limit = (int) ($cfg['rag_statute_limit'] ?? 5);
        if ($limit < 1) {
            $limit = 5;
        }
        $dir = null;
        if (isset($cfg['rag_statute_dir']) && is_string($cfg['rag_statute_dir']) && trim($cfg['rag_statute_dir']) !== '') {
            $dir = trim($cfg['rag_statute_dir']);
        }

        $hits = KichelStatuteIndex::searchQuery($query, $limit, $dir);
        if ($hits === [] && $tokens !== []) {
            $hits = KichelStatuteIndex::search($tokens, $limit, $dir);
        }

        $out = [];
        foreach ($hits as $hit) {
            $score = (int) ($hit['score'] ?? 0);
            if ($score < self::STATUTE_MIN_SCORE) {
                continue;
            }
            $chunk = $hit['chunk'] ?? null;
            if (!is_array($chunk)) {
                continue;
            }
            $ref = trim((string) ($chunk['source_ref'] ?? ''));
            if ($ref === '') {
                continue;
            }
            $href = trim((string) ($chunk['href'] ?? ''));
            $out[] = [
                'kind' => 'statute',
                'score' => $score,
                'title' => trim((string) ($chunk['title'] ?? $ref)),
                'body' => trim((string) ($chunk['body'] ?? '')),
                'href' => $href,
                'action_label' => 'Norm bei GII öffnen',
                'dedupe' => $ref,
                'source_label' => trim((string) ($chunk['source_label'] ?? 'GII')) ?: 'GII',
            ];
        }

        return $out;
    }

    /**
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
                'dedupe' => 'topic:belege-kette',
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
                'dedupe' => 'topic:belege',
            ];
        }

        return $out;
    }
}
