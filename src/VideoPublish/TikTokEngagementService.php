<?php
declare(strict_types=1);

/**
 * TikTok Phase 4-3: Display-API Insights (Views/Likes/Comments-Count/Shares).
 * Kommentar-Moderation gibt es in der Produkt-API nicht (nur Research API / App).
 */
final class TikTokEngagementService
{
    /**
     * @return array{
     *   video_id: string,
     *   share_url: string,
     *   title: string,
     *   insights: array{views: int, likes: int, comments: int, shares: int, duration: int},
     *   account: array{follower_count: int, following_count: int, likes_count: int, video_count: int, display_name: string},
     *   comments_available: bool,
     *   comments_note: string,
     *   error: string
     * }
     */
    public static function loadForJob(int $jobId): array
    {
        $empty = [
            'video_id' => '',
            'share_url' => '',
            'title' => '',
            'insights' => [
                'views' => 0,
                'likes' => 0,
                'comments' => 0,
                'shares' => 0,
                'duration' => 0,
            ],
            'account' => [
                'follower_count' => 0,
                'following_count' => 0,
                'likes_count' => 0,
                'video_count' => 0,
                'display_name' => '',
            ],
            'comments_available' => false,
            'comments_note' => 'Kommentare lesen/antworten geht über die TikTok-Produkt-API nicht — nur in der TikTok-App (Research-API ist kein CRM-Pfad).',
            'error' => '',
        ];
        $job = PublishJobRepository::find($jobId);
        if ($job === null || (string) ($job['platform'] ?? '') !== PublishJobRepository::PLATFORM_TIKTOK) {
            $empty['error'] = 'TikTok-Job nicht gefunden.';

            return $empty;
        }
        if ((string) ($job['state'] ?? '') !== PublishJobRepository::STATE_PUBLISHED) {
            $empty['error'] = 'Kein abgeschlossener TikTok-Job.';

            return $empty;
        }

        $payload = PublishJobRepository::payload($job);
        $externalId = trim((string) ($job['external_id'] ?? ''));
        $videoId = self::resolveVideoId($externalId, $payload);
        if ($videoId === '') {
            $empty['error'] = 'Noch keine Video-Item-ID. Inbox-Drafts und private Posts liefern oft nur eine publish_id — Insights erst nach öffentlichem Post.';

            return $empty;
        }

        try {
            $access = TikTokTokenService::accessToken();
            $stats = TikTokApiClient::queryVideoStats($access, $videoId);
            $account = [
                'follower_count' => 0,
                'following_count' => 0,
                'likes_count' => 0,
                'video_count' => 0,
                'display_name' => '',
            ];
            try {
                $account = TikTokApiClient::fetchAccountStats($access);
            } catch (Throwable) {
                // Stats-Scope optional — Video-Insights reichen.
            }

            return [
                'video_id' => $stats['id'],
                'share_url' => $stats['share_url'],
                'title' => $stats['title'],
                'insights' => [
                    'views' => $stats['view_count'],
                    'likes' => $stats['like_count'],
                    'comments' => $stats['comment_count'],
                    'shares' => $stats['share_count'],
                    'duration' => $stats['duration'],
                ],
                'account' => $account,
                'comments_available' => false,
                'comments_note' => $empty['comments_note'],
                'error' => '',
            ];
        } catch (Throwable $e) {
            $empty['video_id'] = $videoId;
            $empty['error'] = $e->getMessage();

            return $empty;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function resolveVideoId(string $externalId, array $payload): string
    {
        if (TikTokApiClient::looksLikeVideoItemId($externalId)) {
            return $externalId;
        }
        $fromPayload = trim((string) ($payload['video_id'] ?? ''));
        if (TikTokApiClient::looksLikeVideoItemId($fromPayload)) {
            return $fromPayload;
        }
        $list = $payload['publicly_available_post_ids'] ?? null;
        if (is_array($list)) {
            foreach ($list as $id) {
                $id = trim((string) $id);
                if (TikTokApiClient::looksLikeVideoItemId($id)) {
                    return $id;
                }
            }
        }

        return '';
    }
}
