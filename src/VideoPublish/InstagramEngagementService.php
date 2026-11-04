<?php
declare(strict_types=1);

/** Instagram Phase 3-3: Kommentare + einfache Media-Insights zu einem Publish-Job. */
final class InstagramEngagementService
{
    /**
     * @return array{
     *   media_id: string,
     *   comments: list<array{id: string, text: string, username: string, created_label: string, likes: int}>,
     *   insights: array{plays: int, reach: int, likes: int, comments: int, shares: int, saved: int, total_interactions: int},
     *   error: string
     * }
     */
    public static function loadForJob(int $jobId): array
    {
        $empty = [
            'media_id' => '',
            'comments' => [],
            'insights' => [
                'plays' => 0,
                'reach' => 0,
                'likes' => 0,
                'comments' => 0,
                'shares' => 0,
                'saved' => 0,
                'total_interactions' => 0,
            ],
            'error' => '',
        ];
        $job = PublishJobRepository::find($jobId);
        if ($job === null || (string) ($job['platform'] ?? '') !== PublishJobRepository::PLATFORM_INSTAGRAM) {
            $empty['error'] = 'Instagram-Job nicht gefunden.';

            return $empty;
        }
        $mediaId = trim((string) ($job['external_id'] ?? ''));
        if ($mediaId === '' || (string) ($job['state'] ?? '') !== PublishJobRepository::STATE_PUBLISHED) {
            $empty['error'] = 'Kein veröffentlichtes Instagram-Reel.';

            return $empty;
        }
        try {
            $access = InstagramTokenService::accessToken();
            $commentsRaw = InstagramApiClient::listComments($access, $mediaId);
            $comments = [];
            foreach ($commentsRaw as $c) {
                $ts = (string) ($c['timestamp'] ?? '');
                $label = '—';
                if ($ts !== '') {
                    $unix = strtotime($ts);
                    $label = $unix !== false ? date('d.m.Y H:i', $unix) : $ts;
                }
                $comments[] = [
                    'id' => (string) ($c['id'] ?? ''),
                    'text' => (string) ($c['text'] ?? ''),
                    'username' => (string) ($c['username'] ?? ''),
                    'created_label' => $label,
                    'likes' => (int) ($c['like_count'] ?? 0),
                ];
            }
            $insights = InstagramApiClient::getMediaInsights($access, $mediaId);

            return [
                'media_id' => $mediaId,
                'comments' => $comments,
                'insights' => $insights,
                'error' => '',
            ];
        } catch (Throwable $e) {
            $empty['media_id'] = $mediaId;
            $empty['error'] = $e->getMessage();

            return $empty;
        }
    }

    public static function reply(int $jobId, string $text): void
    {
        $job = PublishJobRepository::find($jobId);
        if ($job === null || (string) ($job['platform'] ?? '') !== PublishJobRepository::PLATFORM_INSTAGRAM) {
            throw new InvalidArgumentException('Instagram-Job nicht gefunden.');
        }
        $mediaId = trim((string) ($job['external_id'] ?? ''));
        if ($mediaId === '') {
            throw new RuntimeException('Media-ID fehlt.');
        }
        $access = InstagramTokenService::accessToken();
        InstagramApiClient::createMediaComment($access, $mediaId, $text);
    }

    public static function deleteComment(int $jobId, string $commentId): void
    {
        $job = PublishJobRepository::find($jobId);
        if ($job === null || (string) ($job['platform'] ?? '') !== PublishJobRepository::PLATFORM_INSTAGRAM) {
            throw new InvalidArgumentException('Instagram-Job nicht gefunden.');
        }
        if (trim((string) ($job['external_id'] ?? '')) === '' || trim($commentId) === '') {
            throw new RuntimeException('Angaben für Löschen unvollständig.');
        }
        $access = InstagramTokenService::accessToken();
        InstagramApiClient::deleteComment($access, trim($commentId));
    }

    public static function hideComment(int $jobId, string $commentId, bool $hide = true): void
    {
        $job = PublishJobRepository::find($jobId);
        if ($job === null || (string) ($job['platform'] ?? '') !== PublishJobRepository::PLATFORM_INSTAGRAM) {
            throw new InvalidArgumentException('Instagram-Job nicht gefunden.');
        }
        if (trim($commentId) === '') {
            throw new RuntimeException('Kommentar-ID fehlt.');
        }
        $access = InstagramTokenService::accessToken();
        InstagramApiClient::hideComment($access, trim($commentId), $hide);
    }
}
