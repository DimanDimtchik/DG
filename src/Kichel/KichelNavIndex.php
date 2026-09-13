<?php
declare(strict_types=1);

/**
 * Schicht A: CRM-Navigation aus MenuRegistry + SettingsRegistry (ohne Hardcoding pro Frage).
 */
final class KichelNavIndex
{
    /** @var array<string, list<array<string, mixed>>> */
    private static array $cache = [];

    /**
     * @return list<array{
     *   id: string,
     *   label: string,
     *   description: string,
     *   href: string,
     *   kind: string,
     *   section: string
     * }>
     */
    public static function entriesForUser(User $user): array
    {
        $key = 'u' . $user->id;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $entries = [];
        $descriptions = MenuRegistry::moduleDescriptions();

        $push = static function (
            array &$target,
            string $id,
            string $label,
            string $href,
            string $kind,
            string $section,
            string $description = ''
        ): void {
            if ($label === '' || $href === '') {
                return;
            }
            $target[] = [
                'id' => $id,
                'label' => $label,
                'description' => $description,
                'href' => $href,
                'kind' => $kind,
                'section' => $section,
            ];
        };

        foreach (MenuRegistry::sidebarItems($user) as $item) {
            $slug = (string) ($item['slug'] ?? '');
            $push(
                $entries,
                $slug,
                (string) ($item['label'] ?? ''),
                (string) ($item['href'] ?? ''),
                'module',
                'Hauptmenü',
                $descriptions[$slug] ?? ''
            );
        }

        $sections = [
            MenuRegistry::buchhaltungSection($user),
            MenuRegistry::websiteSection($user),
            MenuRegistry::kdvSection($user),
        ];
        foreach ($sections as $section) {
            if ($section === null) {
                continue;
            }
            $sectionLabel = (string) ($section['label'] ?? '');
            foreach ($section['items'] as $item) {
                $slug = (string) ($item['slug'] ?? '');
                $push(
                    $entries,
                    $slug,
                    (string) ($item['label'] ?? ''),
                    (string) ($item['href'] ?? ''),
                    'section',
                    $sectionLabel,
                    $descriptions[$slug] ?? ''
                );
            }
        }

        if (RoleResolver::isAdmin($user)) {
            $push(
                $entries,
                'einstellungen',
                'Einstellungen',
                '/app?page=einstellungen',
                'settings',
                'System',
                $descriptions['einstellungen'] ?? 'Firma, E-Mail, Module und System konfigurieren.'
            );

            foreach (SettingsRegistry::allTabs() as $tabId => $tab) {
                $sectionLabel = (string) ($tab['sectionLabel'] ?? 'Einstellungen');
                $lead = (string) ($tab['lead'] ?? '');
                $push(
                    $entries,
                    'settings:' . $tabId,
                    (string) ($tab['label'] ?? $tabId),
                    SettingsRegistry::tabUrl($tabId),
                    'settings-tab',
                    $sectionLabel,
                    $lead
                );
            }
        }

        self::$cache[$key] = $entries;

        return $entries;
    }

    /**
     * @param list<string> $tokens
     * @return list<array{entry: array<string, mixed>, score: int}>
     */
    public static function search(User $user, string $query, array $tokens, int $limit = 5): array
    {
        if ($tokens === []) {
            return [];
        }

        $normalizedQuery = mb_strtolower(trim($query), 'UTF-8');
        $scored = [];

        foreach (self::entriesForUser($user) as $entry) {
            $score = self::scoreEntry($entry, $tokens, $normalizedQuery);
            if ($score > 0) {
                $scored[] = ['entry' => $entry, 'score' => $score];
            }
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * @param array<string, mixed> $entry
     * @param list<string> $tokens
     */
    public static function scoreEntry(array $entry, array $tokens, string $normalizedQuery): int
    {
        $label = mb_strtolower((string) ($entry['label'] ?? ''), 'UTF-8');
        $description = mb_strtolower((string) ($entry['description'] ?? ''), 'UTF-8');
        $id = mb_strtolower((string) ($entry['id'] ?? ''), 'UTF-8');
        $section = mb_strtolower((string) ($entry['section'] ?? ''), 'UTF-8');
        $score = 0;

        if ($label !== '' && str_contains($normalizedQuery, $label)) {
            $score += 8;
        }

        foreach ($tokens as $token) {
            if ($token === $label || $token === $id) {
                $score += 6;
                continue;
            }
            if ($label !== '' && self::textContainsToken($label, $token)) {
                $score += 4;
            }
            if ($description !== '' && self::textContainsToken($description, $token)) {
                $score += 3;
            }
            if ($section !== '' && self::textContainsToken($section, $token)) {
                $score += 1;
            }
            if ($id !== '' && self::textContainsToken(str_replace([':', '-', '_'], ' ', $id), $token)) {
                $score += 2;
            }
        }

        return $score;
    }

    private static function textContainsToken(string $text, string $token): bool
    {
        if ($token === 'konto' && str_contains($text, 'skonto')) {
            return false;
        }
        if ($token === 'mein' && str_contains($text, 'allgemein')) {
            return false;
        }
        if ($token === 'freigabe' && str_contains($text, 'support')) {
            return false;
        }
        if (mb_strlen($token, 'UTF-8') < 3 && $text !== $token) {
            return false;
        }

        return str_contains($text, $token);
    }
}
