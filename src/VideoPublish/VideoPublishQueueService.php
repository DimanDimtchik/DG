<?php
declare(strict_types=1);

/** Cron / runIfDue: aktive YouTube-Jobs weiterführen. */
final class VideoPublishQueueService
{
    /**
     * @return array{processed: int, published: int, failed: int, messages: list<string>}
     */
    public static function runAutomatic(int $maxJobs = 3, int $maxChunksPerJob = 8): array
    {
        $result = [
            'processed' => 0,
            'published' => 0,
            'failed' => 0,
            'messages' => [],
        ];
        if (!Database::isConfigured() || !PublishJobRepository::tableReady()) {
            return $result;
        }
        if (!YouTubePublishSettings::credentialsReady()) {
            $result['messages'][] = 'YouTube nicht konfiguriert.';

            return $result;
        }

        $jobs = PublishJobRepository::listActive($maxJobs);
        foreach ($jobs as $job) {
            if ((string) ($job['platform'] ?? '') !== PublishJobRepository::PLATFORM_YOUTUBE) {
                continue;
            }
            $jobId = (int) ($job['id'] ?? 0);
            if ($jobId < 1) {
                continue;
            }
            $result['processed']++;
            for ($i = 0; $i < $maxChunksPerJob; $i++) {
                $step = YouTubeResumableUploadService::processJob($jobId);
                $result['messages'][] = '#' . $jobId . ': ' . $step['message'];
                if (!empty($step['done'])) {
                    if (!empty($step['ok'])) {
                        $fresh = PublishJobRepository::find($jobId);
                        if (($fresh['state'] ?? '') === PublishJobRepository::STATE_PUBLISHED) {
                            $result['published']++;
                        }
                    } else {
                        $result['failed']++;
                    }
                    break;
                }
            }
        }

        return $result;
    }

    public static function runIfDue(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        $stateFile = DG_ROOT . '/storage/video-publish/queue-auto-state.json';
        $today = date('Y-m-d H:i'); // max. 1×/Minute
        $state = [];
        if (is_file($stateFile)) {
            $raw = json_decode((string) file_get_contents($stateFile), true);
            $state = is_array($raw) ? $raw : [];
        }
        if (($state['last_run_minute'] ?? '') === $today) {
            return;
        }
        $result = self::runAutomatic(2, 4);
        VideoAssetStorage::ensureDir();
        @file_put_contents($stateFile, json_encode([
            'last_run_minute' => $today,
            'processed' => $result['processed'],
            'published' => $result['published'],
            'failed' => $result['failed'],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
