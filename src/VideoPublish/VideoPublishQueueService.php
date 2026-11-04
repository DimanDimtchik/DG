<?php
declare(strict_types=1);

/** Cron / runIfDue: aktive YouTube- und LinkedIn-Jobs weiterführen. */
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

        $jobs = PublishJobRepository::listActive($maxJobs);
        foreach ($jobs as $job) {
            $platform = (string) ($job['platform'] ?? '');
            $jobId = (int) ($job['id'] ?? 0);
            if ($jobId < 1) {
                continue;
            }
            if ($platform === PublishJobRepository::PLATFORM_YOUTUBE) {
                if (!YouTubePublishSettings::credentialsReady()) {
                    $result['messages'][] = '#' . $jobId . ': YouTube nicht konfiguriert.';
                    continue;
                }
                $result['processed']++;
                for ($i = 0; $i < $maxChunksPerJob; $i++) {
                    $step = YouTubeResumableUploadService::processJob($jobId);
                    $result['messages'][] = '#' . $jobId . ': ' . $step['message'];
                    self::tallyStep($result, $jobId, $step);
                    if (!empty($step['done'])) {
                        break;
                    }
                }
                continue;
            }
            if ($platform === PublishJobRepository::PLATFORM_LINKEDIN) {
                if (!LinkedInPublishSettings::credentialsReady()) {
                    $result['messages'][] = '#' . $jobId . ': LinkedIn nicht konfiguriert.';
                    continue;
                }
                $result['processed']++;
                for ($i = 0; $i < $maxChunksPerJob; $i++) {
                    $step = LinkedInResumableUploadService::processJob($jobId);
                    $result['messages'][] = '#' . $jobId . ': ' . $step['message'];
                    self::tallyStep($result, $jobId, $step);
                    if (!empty($step['done'])) {
                        break;
                    }
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

    /**
     * @param array{processed: int, published: int, failed: int, messages: list<string>} $result
     * @param array{ok: bool, message: string, done: bool} $step
     */
    private static function tallyStep(array &$result, int $jobId, array $step): void
    {
        if (empty($step['done'])) {
            return;
        }
        if (!empty($step['ok'])) {
            $fresh = PublishJobRepository::find($jobId);
            if (($fresh['state'] ?? '') === PublishJobRepository::STATE_PUBLISHED) {
                $result['published']++;
            }
        } else {
            $result['failed']++;
        }
    }
}
