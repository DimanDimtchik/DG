<?php
declare(strict_types=1);

/** LinkedIn Phase 2-3: Kommentare + einfache Insights zu einem Publish-Job. */
final class LinkedInEngagementService
{
    /**
     * @return array{
     *   post_urn: string,
     *   comments: list<array{id: string, text: string, actor: string, created_ms: int, likes: int, created_label: string}>,
     *   summary: array{likes: int, comments: int},
     *   insights: array{video_views: int, viewers: int, time_watched_ms: int, time_watched_label: string},
     *   error: string
     * }
     */
    public static function loadForJob(int $jobId): array
    {
        $empty = [
            'post_urn' => '',
            'comments' => [],
            'summary' => ['likes' => 0, 'comments' => 0],
            'insights' => [
                'video_views' => 0,
                'viewers' => 0,
                'time_watched_ms' => 0,
                'time_watched_label' => '—',
            ],
            'error' => '',
        ];
        $job = PublishJobRepository::find($jobId);
        if ($job === null || (string) ($job['platform'] ?? '') !== PublishJobRepository::PLATFORM_LINKEDIN) {
            $empty['error'] = 'LinkedIn-Job nicht gefunden.';

            return $empty;
        }
        $postUrn = trim((string) ($job['external_id'] ?? ''));
        if ($postUrn === '' || (string) ($job['state'] ?? '') !== PublishJobRepository::STATE_PUBLISHED) {
            $empty['error'] = 'Kein veröffentlichter LinkedIn-Post.';

            return $empty;
        }
        try {
            $access = LinkedInTokenService::accessToken();
            $commentsRaw = LinkedInApiClient::listComments($access, $postUrn);
            $comments = [];
            foreach ($commentsRaw as $c) {
                $ms = (int) ($c['created_ms'] ?? 0);
                $comments[] = [
                    'id' => (string) ($c['id'] ?? ''),
                    'text' => (string) ($c['text'] ?? ''),
                    'actor' => (string) ($c['actor'] ?? ''),
                    'created_ms' => $ms,
                    'likes' => (int) ($c['likes'] ?? 0),
                    'created_label' => $ms > 0 ? date('d.m.Y H:i', (int) floor($ms / 1000)) : '—',
                ];
            }
            $summary = LinkedInApiClient::getSocialSummary($access, $postUrn);
            $analyticsUrn = $postUrn;
            if (!str_contains($analyticsUrn, 'ugcPost')) {
                // Video-Analytics braucht ugcPost — Share-URN liefert ggf. keine Views
                $analyticsUrn = '';
            }
            $insights = LinkedInApiClient::getVideoInsights($access, $analyticsUrn);
            $ms = (int) ($insights['time_watched_ms'] ?? 0);
            $insights['time_watched_label'] = $ms > 0
                ? number_format($ms / 1000, 0, ',', '.') . ' s'
                : '—';

            return [
                'post_urn' => $postUrn,
                'comments' => $comments,
                'summary' => $summary,
                'insights' => $insights,
                'error' => '',
            ];
        } catch (Throwable $e) {
            $empty['post_urn'] = $postUrn;
            $empty['error'] = $e->getMessage();

            return $empty;
        }
    }

    public static function reply(int $jobId, string $text): void
    {
        $job = PublishJobRepository::find($jobId);
        if ($job === null || (string) ($job['platform'] ?? '') !== PublishJobRepository::PLATFORM_LINKEDIN) {
            throw new InvalidArgumentException('LinkedIn-Job nicht gefunden.');
        }
        $postUrn = trim((string) ($job['external_id'] ?? ''));
        $org = LinkedInPublishSettings::organizationUrn();
        if ($postUrn === '' || $org === '') {
            throw new RuntimeException('Post-URN oder Organization-ID fehlt.');
        }
        $access = LinkedInTokenService::accessToken();
        LinkedInApiClient::createComment($access, $postUrn, $org, $text);
    }

    public static function deleteComment(int $jobId, string $commentId): void
    {
        $job = PublishJobRepository::find($jobId);
        if ($job === null || (string) ($job['platform'] ?? '') !== PublishJobRepository::PLATFORM_LINKEDIN) {
            throw new InvalidArgumentException('LinkedIn-Job nicht gefunden.');
        }
        $postUrn = trim((string) ($job['external_id'] ?? ''));
        $org = LinkedInPublishSettings::organizationUrn();
        if ($postUrn === '' || $org === '' || $commentId === '') {
            throw new RuntimeException('Angaben für Löschen unvollständig.');
        }
        $access = LinkedInTokenService::accessToken();
        LinkedInApiClient::deleteComment($access, $postUrn, $commentId, $org);
    }
}
