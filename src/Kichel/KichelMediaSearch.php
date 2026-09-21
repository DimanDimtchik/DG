<?php
declare(strict_types=1);

/**
 * Kichel: Suche in Akademie-Videos (Kurse/Module) und Media-Bibliothek.
 */
final class KichelMediaSearch
{
    private const MEDIA_HINT_TOKENS = [
        'video', 'videos', 'clip', 'clips', 'schulung', 'schulungen', 'kurs', 'kurse',
        'medien', 'media', 'bibliothek', 'bild', 'bilder', 'logo', 'favicon', 'foto', 'fotos',
    ];

    /**
     * @param list<string> $tokens
     * @return list<array{
     *   kind: string,
     *   score: int,
     *   title: string,
     *   body: string,
     *   href: string,
     *   action_label: string,
     *   dedupe: string
     * }>
     */
    public static function search(User $user, string $query, array $tokens, int $limit = 6): array
    {
        if ($tokens === [] || !Database::isConfigured()) {
            return [];
        }

        $wantsMedia = self::wantsMediaContext($query, $tokens);
        $results = [];

        self::searchAcademy($tokens, $wantsMedia, $results);
        if (MenuRegistry::canAccess($user, 'bilder')) {
            self::searchMediaLibrary($tokens, $wantsMedia, $results);
        }

        usort($results, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($results, 0, max(1, $limit));
    }

    /**
     * @param list<string> $tokens
     */
    private static function wantsMediaContext(string $query, array $tokens): bool
    {
        $q = mb_strtolower(trim($query), 'UTF-8');
        foreach (self::MEDIA_HINT_TOKENS as $hint) {
            if (str_contains($q, $hint)) {
                return true;
            }
        }
        foreach ($tokens as $token) {
            if (in_array($token, self::MEDIA_HINT_TOKENS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $tokens
     * @param list<array<string, mixed>> $results
     */
    private static function searchAcademy(array $tokens, bool $wantsMedia, array &$results): void
    {
        MigrationRunner::runPending();
        $pdo = Database::pdo();

        $contentTokens = array_values(array_filter(
            $tokens,
            static fn (string $t): bool => !in_array($t, self::MEDIA_HINT_TOKENS, true)
        ));
        // Bei „Video Lager“ muss „Lager“ zählen; reine Media-Wörter allein → allgemeine Akademie.
        $matchTokens = $contentTokens !== [] ? $contentTokens : $tokens;

        try {
            $courses = $pdo->query(
                'SELECT id, title, slug, description FROM dg_academy_courses WHERE is_published = 1 ORDER BY id ASC'
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return;
        }

        foreach ($courses as $course) {
            $title = trim((string) ($course['title'] ?? ''));
            $slug = trim((string) ($course['slug'] ?? ''));
            $desc = trim((string) ($course['description'] ?? ''));
            $hay = mb_strtolower($title . ' ' . $slug . ' ' . $desc, 'UTF-8');
            $score = self::scoreHaystack($hay, $matchTokens);
            if ($score < 1) {
                continue;
            }
            if ($wantsMedia) {
                $score += 5;
            }
            $href = '/app?page=akademie&view=kurs&slug=' . rawurlencode($slug !== '' ? $slug : (string) $course['id']);
            $results[] = [
                'kind' => 'academy_course',
                'score' => $score + 2,
                'title' => 'Schulungsvideo: ' . ($title !== '' ? $title : 'Kurs'),
                'body' => $desc !== ''
                    ? $desc
                    : 'Akademie-Kurs mit Erklärvideos — starten unter Akademie → Katalog.',
                'href' => $href,
                'action_label' => $title !== '' ? $title : 'Kurs öffnen',
                'dedupe' => mb_strtolower($href, 'UTF-8'),
            ];
        }

        try {
            $modules = $pdo->query(
                'SELECT m.id, m.title, m.description, m.video_path,
                        (SELECT c.slug FROM dg_academy_course_modules cm
                         INNER JOIN dg_academy_courses c ON c.id = cm.course_id
                         WHERE cm.module_id = m.id AND c.is_published = 1
                         ORDER BY cm.sort_order ASC LIMIT 1) AS course_slug
                 FROM dg_academy_modules m
                 WHERE m.is_active = 1
                 ORDER BY m.id ASC'
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return;
        }

        foreach ($modules as $module) {
            $title = trim((string) ($module['title'] ?? ''));
            $desc = trim((string) ($module['description'] ?? ''));
            $hay = mb_strtolower($title . ' ' . $desc, 'UTF-8');
            $score = self::scoreHaystack($hay, $matchTokens);
            if ($score < 1) {
                continue;
            }
            if ($wantsMedia) {
                $score += 6;
            }
            $courseSlug = trim((string) ($module['course_slug'] ?? ''));
            $moduleId = (int) ($module['id'] ?? 0);
            if ($courseSlug !== '' && $moduleId > 0) {
                $href = '/app?page=akademie&view=modul&slug=' . rawurlencode($courseSlug)
                    . '&module_id=' . $moduleId;
            } elseif ($moduleId > 0) {
                $href = '/app?page=akademie&view=video-vorschau&module_id=' . $moduleId;
            } else {
                continue;
            }
            $clipLabel = $title !== '' ? $title : ('Modul #' . $moduleId);
            $results[] = [
                'kind' => 'academy_module',
                'score' => $score + 3,
                'title' => 'Video: ' . $clipLabel,
                'body' => 'Schulungsclip in der Akademie'
                    . ($courseSlug !== '' ? ' (Kurs „' . $courseSlug . '“)' : '')
                    . '.',
                'href' => $href,
                'action_label' => $clipLabel,
                'dedupe' => 'academy-module:' . $moduleId,
            ];
        }
    }

    /**
     * @param list<string> $tokens
     * @param list<array<string, mixed>> $results
     */
    private static function searchMediaLibrary(array $tokens, bool $wantsMedia, array &$results): void
    {
        MediaRepository::ensureTables();
        $pdo = Database::pdo();

        try {
            $rows = $pdo->query(
                "SELECT media_id, title, alt_text, original_name, source_note, extension
                 FROM dg_media
                 WHERE status = 'active'
                 ORDER BY uploaded_at DESC
                 LIMIT 200"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return;
        }

        foreach ($rows as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            $alt = trim((string) ($row['alt_text'] ?? ''));
            $orig = trim((string) ($row['original_name'] ?? ''));
            $note = trim((string) ($row['source_note'] ?? ''));
            $ext = trim((string) ($row['extension'] ?? ''));
            $mediaId = trim((string) ($row['media_id'] ?? ''));
            $hay = mb_strtolower($title . ' ' . $alt . ' ' . $orig . ' ' . $note . ' ' . $ext . ' ' . $mediaId, 'UTF-8');
            $score = self::scoreHaystack($hay, $tokens);
            if ($score < 1) {
                continue;
            }
            if ($wantsMedia) {
                $score += 3;
            }
            $label = $title !== '' ? $title : ($orig !== '' ? $orig : $mediaId);
            $href = '/app?page=bilder&action=edit&id=' . rawurlencode($mediaId);
            $results[] = [
                'kind' => 'media_item',
                'score' => $score + 1,
                'title' => 'Media: ' . $label,
                'body' => 'Datei in der Media-Bibliothek'
                    . ($ext !== '' ? ' (.' . $ext . ')' : '')
                    . '.',
                'href' => $href,
                'action_label' => $label,
                'dedupe' => 'media:' . mb_strtolower($mediaId, 'UTF-8'),
            ];
        }

        // Immer ein Navigationshint zur Bibliothek, wenn nach Medien gefragt wird.
        if ($wantsMedia) {
            $results[] = [
                'kind' => 'media_library',
                'score' => 8,
                'title' => 'Media-Bibliothek',
                'body' => 'Logos, Fotos und Grafiken hochladen und bearbeiten — Kachel Media.',
                'href' => '/app?page=bilder',
                'action_label' => 'Media-Bibliothek',
                'dedupe' => '/app?page=bilder',
            ];
        }
    }

    /**
     * @param list<string> $tokens
     */
    private static function scoreHaystack(string $haystack, array $tokens): int
    {
        if ($haystack === '' || $tokens === []) {
            return 0;
        }
        $score = 0;
        foreach ($tokens as $token) {
            $token = mb_strtolower(trim($token), 'UTF-8');
            if ($token === '' || mb_strlen($token, 'UTF-8') < 2) {
                continue;
            }
            if ($haystack === $token || preg_match('/(?:^|[\s\-_,.\/])' . preg_quote($token, '/') . '(?:$|[\s\-_,.\/])/u', $haystack) === 1) {
                $score += 6;
                continue;
            }
            if (mb_strlen($token, 'UTF-8') >= 3 && str_contains($haystack, $token)) {
                $score += 3;
            }
        }

        return $score;
    }
}
