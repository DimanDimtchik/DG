<?php
declare(strict_types=1);

/**
 * Orchestriert Fachwissen, Code- und DB-Suche für Kichel.
 */
final class KichelAssistant
{
    /**
     * @return array{
     *   answer: string,
     *   topics: list<array<string, mixed>>,
     *   navigation: list<array{label: string, href: string, kind: string}>,
     *   code: list<array{path: string, line: int, snippet: string}>,
     *   database: list<array<string, mixed>>,
     *   company: list<array{key: string, label: string, value: string}>,
     *   hints: list<string>
     * }
     */
    public static function answer(User $user, string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return self::emptyResponse('Stellen Sie mir eine Frage — z. B. „Wo trage ich die USt-ID ein?“ oder „contact_note Tabelle“.');
        }

        $tokens = KichelKnowledge::tokenize($query);
        if ($tokens === []) {
            $tokens = KichelKnowledge::tokenize(preg_replace('/\s+/', ' ', $query) ?? $query);
        }

        $topicMatches = KichelKnowledge::matchTopics($tokens);
        $navigation = KichelKnowledge::navigationHints($user, $tokens);
        $codeHits = KichelCodeSearch::search($tokens);
        $schemaHits = KichelSchemaCatalog::searchSchema($tokens);
        $companyFields = KichelSchemaCatalog::matchCompanyFields($tokens);

        $topics = array_map(static fn (array $m): array => $m['topic'], $topicMatches);
        $answerParts = [];

        if ($topics !== []) {
            foreach (array_slice($topics, 0, 2) as $topic) {
                $answerParts[] = (string) ($topic['answer'] ?? '');
            }
        } else {
            $answerParts[] = self::genericIntro($query);
        }

        if ($companyFields !== []) {
            $lines = [];
            foreach ($companyFields as $field) {
                $lines[] = ucfirst($field['label']) . ': ' . $field['value'];
            }
            $answerParts[] = 'Aus Ihren Firmendaten: ' . implode(' · ', $lines);
        }

        if ($schemaHits !== []) {
            $tableLines = [];
            foreach (array_slice($schemaHits, 0, 3) as $hit) {
                $cols = implode(', ', array_slice($hit['columns'], 0, 6));
                $count = $hit['row_count'] ?? null;
                $suffix = $count !== null ? ' (' . number_format($count, 0, ',', '.') . ' Datensätze)' : '';
                $tableLines[] = $hit['table'] . $suffix . ($cols !== '' ? ' — Spalten: ' . $cols : '');
            }
            $answerParts[] = 'Datenbank-Schema (Migrationen): ' . implode(' | ', $tableLines);
        }

        if ($codeHits !== []) {
            $answerParts[] = 'Im Quellcode habe ich passende Stellen gefunden — siehe Trefferliste unten.';
        }

        if ($navigation !== [] && $topics === []) {
            $navLabels = array_map(static fn (array $n): string => $n['label'], $navigation);
            $answerParts[] = 'Vielleicht meinen Sie: ' . implode(', ', $navLabels) . '.';
        }

        $hints = self::hints($tokens, $topics, $codeHits, $schemaHits);

        return [
            'answer' => trim(implode("\n\n", array_filter($answerParts))),
            'topics' => array_map(static function (array $topic): array {
                return [
                    'id' => $topic['id'] ?? '',
                    'title' => $topic['title'] ?? '',
                    'href' => $topic['href'] ?? null,
                ];
            }, $topics),
            'navigation' => $navigation,
            'code' => array_map(static fn (array $hit): array => [
                'path' => $hit['path'],
                'line' => $hit['line'],
                'snippet' => $hit['snippet'],
            ], $codeHits),
            'database' => $schemaHits,
            'company' => $companyFields,
            'hints' => $hints,
        ];
    }

    /**
     * @param list<string> $tokens
     * @param list<array<string, mixed>> $topics
     * @param list<array<string, mixed>> $codeHits
     * @param list<array<string, mixed>> $schemaHits
     * @return list<string>
     */
    private static function hints(array $tokens, array $topics, array $codeHits, array $schemaHits): array
    {
        $hints = [
            'Ich durchsuche CRM-Wissen, Quellcode (src/, views/, docs/) und das DB-Schema aus Migrationen.',
            'Beispiele: „Skonto einstellen“, „dg_contacts“, „LegalPageGenerator“.',
        ];

        if ($topics === [] && $codeHits === [] && $schemaHits === []) {
            $hints[] = 'Keine Treffer — versuchen Sie einen kürzeren Begriff (z. B. „IMAP“, „Belegkette“, „contact_note“).';
        }

        return $hints;
    }

    private static function genericIntro(string $query): string
    {
        return 'Zu „' . $query . '“ habe ich kein festes Fachthema — ich habe trotzdem Code und Datenbankschema durchsucht.';
    }

    /**
     * @return array{answer: string, topics: list<mixed>, navigation: list<mixed>, code: list<mixed>, database: list<mixed>, company: list<mixed>, hints: list<string>}
     */
    private static function emptyResponse(string $message): array
    {
        return [
            'answer' => $message,
            'topics' => [],
            'navigation' => [],
            'code' => [],
            'database' => [],
            'company' => [],
            'hints' => [
                'Fachfragen zu Steuer, Buchhaltung und CRM-Einstellungen',
                'Code-Suche in src/, views/, docs/',
                'DB-Schema und sichere Firmendaten-Hinweise',
            ],
        ];
    }
}
