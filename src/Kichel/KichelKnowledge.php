<?php
declare(strict_types=1);

/**
 * Regelbasierte Fach- und Navigationsantworten für Kichel.
 */
final class KichelKnowledge
{
    /** @var list<array<string, mixed>>|null */
    private static ?array $topics = null;

    /**
     * @return list<array<string, mixed>>
     */
    private static function topics(): array
    {
        if (self::$topics === null) {
            /** @var list<array<string, mixed>> $loaded */
            $loaded = require __DIR__ . '/data/knowledge.php';
            self::$topics = $loaded;
        }

        return self::$topics;
    }

    /**
     * @return list<string>
     */
    public static function tokenize(string $query): array
    {
        $normalized = mb_strtolower($query, 'UTF-8');
        $normalized = preg_replace('/[^\p{L}\p{N}\-_]+/u', ' ', $normalized) ?? '';
        $parts = preg_split('/\s+/u', trim($normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = [
            'der', 'die', 'das', 'den', 'dem', 'des', 'ein', 'eine', 'einer', 'eines',
            'und', 'oder', 'wo', 'wie', 'was', 'ist', 'sind', 'im', 'in', 'für', 'von',
            'zu', 'zum', 'zur', 'mit', 'auf', 'an', 'am', 'als', 'bei', 'mir', 'mich',
            'finde', 'finden', 'zeige', 'zeigen', 'suche', 'such', 'bitte', 'kann', 'kannst',
        ];

        $tokens = [];
        foreach ($parts as $part) {
            if ($part === '' || mb_strlen($part, 'UTF-8') < 2 || in_array($part, $stop, true)) {
                continue;
            }
            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @param list<string> $tokens
     * @return list<array{topic: array<string, mixed>, score: int}>
     */
    public static function matchTopics(array $tokens, int $limit = 5): array
    {
        if ($tokens === []) {
            return [];
        }

        $scored = [];
        foreach (self::topics() as $topic) {
            $score = 0;
            $keywords = $topic['keywords'] ?? [];
            if (!is_array($keywords)) {
                continue;
            }
            foreach ($tokens as $token) {
                foreach ($keywords as $keyword) {
                    $kw = (string) $keyword;
                    if ($token === $kw || str_contains($kw, $token) || str_contains($token, $kw)) {
                        $score += $token === $kw ? 4 : 2;
                    }
                }
                $title = mb_strtolower((string) ($topic['title'] ?? ''), 'UTF-8');
                if ($title !== '' && str_contains($title, $token)) {
                    $score += 1;
                }
            }
            if ($score > 0) {
                $scored[] = ['topic' => $topic, 'score' => $score];
            }
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * @param list<string> $tokens
     * @return list<array{label: string, href: string, kind: string}>
     */
    public static function navigationHints(User $user, array $tokens): array
    {
        $hints = [];
        $seen = [];

        foreach (MenuRegistry::sidebarItems($user) as $item) {
            self::pushNavHint($hints, $seen, $item['label'], $item['href'], $tokens, 'module');
        }

        $buchhaltung = MenuRegistry::buchhaltungSection($user);
        if ($buchhaltung !== null) {
            foreach ($buchhaltung['items'] as $item) {
                self::pushNavHint($hints, $seen, $item['label'], $item['href'], $tokens, 'buchhaltung');
            }
        }

        $website = MenuRegistry::websiteSection($user);
        if ($website !== null) {
            foreach ($website['items'] as $item) {
                self::pushNavHint($hints, $seen, $item['label'], $item['href'], $tokens, 'website');
            }
        }

        if (RoleResolver::isAdmin($user)) {
            foreach (SettingsRegistry::allTabs() as $tabId => $tab) {
                $label = (string) ($tab['label'] ?? '');
                $href = SettingsRegistry::tabUrl($tabId);
                self::pushNavHint($hints, $seen, $label, $href, $tokens, 'settings');
            }
        }

        return array_slice($hints, 0, 6);
    }

    /**
     * @param list<array{label: string, href: string, kind: string}> $hints
     * @param array<string, true> $seen
     * @param list<string> $tokens
     */
    private static function pushNavHint(array &$hints, array &$seen, string $label, string $href, array $tokens, string $kind): void
    {
        if ($label === '' || isset($seen[$href])) {
            return;
        }

        $labelLower = mb_strtolower($label, 'UTF-8');
        $match = false;
        foreach ($tokens as $token) {
            if (str_contains($labelLower, $token)) {
                $match = true;
                break;
            }
        }
        if (!$match) {
            return;
        }

        $seen[$href] = true;
        $hints[] = ['label' => $label, 'href' => $href, 'kind' => $kind];
    }
}
