<?php
declare(strict_types=1);

/**
 * Veröffentlicht vorgemerkte Stellen auf LinkedIn Jobs (nur nach App-Review).
 * Bewerben-URL bleibt immer die eigene /rekrut-Seite (companyApplyUrl).
 */
final class LinkedInRecruitingPublishService
{
    /**
     * @return array{ok: bool, message: string, external_id: string}
     */
    public static function publishJob(int $jobId, ?int $userId = null): array
    {
        $empty = ['ok' => false, 'message' => '', 'external_id' => ''];
        if ($jobId < 1) {
            $empty['message'] = 'Stelle ungültig.';

            return $empty;
        }
        if (!LinkedInRecruitingSettings::isReady()) {
            $cfg = LinkedInRecruitingSettings::config();
            if (empty($cfg['app_review_approved'])) {
                $empty['message'] = 'LinkedIn App-Review noch nicht bestätigt — Publish gesperrt.';
            } elseif (LinkedInRecruitingSettings::companyId($cfg) === '') {
                $empty['message'] = 'LinkedIn Firmen-Seiten-ID fehlt.';
            } else {
                $empty['message'] = 'LinkedIn-API nicht bereit — Einstellungen → HR → LinkedIn prüfen.';
            }

            return $empty;
        }

        $job = JobPostingRepository::find($jobId);
        if ($job === null) {
            $empty['message'] = 'Stelle nicht gefunden.';

            return $empty;
        }
        if (empty($job['export_linkedin'])) {
            $empty['message'] = 'LinkedIn-Export für diese Stelle ist nicht aktiviert.';

            return $empty;
        }
        if ((string) ($job['status'] ?? '') !== JobPostingRepository::STATUS_PUBLISHED) {
            $empty['message'] = 'Stelle muss zuerst im CRM veröffentlicht sein.';

            return $empty;
        }

        $externalId = LinkedInRecruitingClient::externalJobPostingId($jobId);
        $existingRef = trim((string) ($job['linkedin_external_ref'] ?? ''));
        if (
            $existingRef !== ''
            && (string) ($job['linkedin_publish_status'] ?? '') === JobPostingRepository::PUBLISH_PUBLISHED
        ) {
            return [
                'ok' => true,
                'message' => 'Bereits auf LinkedIn veröffentlicht (' . $existingRef . ').',
                'external_id' => $existingRef,
            ];
        }

        $element = LinkedInRecruitingClient::buildJobElement($job, 'CREATE');
        $res = LinkedInRecruitingClient::batchJobPostings([$element]);
        if (!$res['ok']) {
            self::markError($jobId, $res['error'] !== '' ? $res['error'] : 'LinkedIn-Create fehlgeschlagen.', $userId);

            return [
                'ok' => false,
                'message' => $res['error'] !== '' ? $res['error'] : 'LinkedIn-Create fehlgeschlagen.',
                'external_id' => '',
            ];
        }

        $msg = 'LinkedIn-Job ' . $externalId . ' übermittelt (BATCH async). Bewerben-URL: '
            . JobPostingRepository::publicUrl((string) ($job['slug'] ?? ''));
        self::markPublished($jobId, $externalId, $msg, $userId);

        return ['ok' => true, 'message' => $msg, 'external_id' => $externalId];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public static function closeJob(int $jobId, ?int $userId = null): array
    {
        $job = JobPostingRepository::find($jobId);
        if ($job === null) {
            return ['ok' => false, 'message' => 'Stelle nicht gefunden.'];
        }
        $ref = trim((string) ($job['linkedin_external_ref'] ?? ''));
        if ($ref === '') {
            self::markWithdrawnLocal($jobId, 'LinkedIn-Export zurückgezogen (kein Remote-Posting).', $userId);

            return ['ok' => true, 'message' => 'Lokal zurückgezogen.'];
        }
        if (!LinkedInRecruitingSettings::isReady()) {
            self::markWithdrawnLocal(
                $jobId,
                'Lokal zurückgezogen — API nicht bereit, Remote ggf. manuell schließen.',
                $userId
            );

            return ['ok' => true, 'message' => 'Lokal zurückgezogen (API nicht bereit).'];
        }

        $element = LinkedInRecruitingClient::buildJobElement($job, 'CLOSE');
        $res = LinkedInRecruitingClient::batchJobPostings([$element]);
        if (!$res['ok']) {
            self::markError($jobId, 'LinkedIn-Close: ' . ($res['error'] !== '' ? $res['error'] : 'Fehler'), $userId);

            return ['ok' => false, 'message' => $res['error'] !== '' ? $res['error'] : 'Schließen fehlgeschlagen.'];
        }

        self::markWithdrawnLocal($jobId, 'LinkedIn-Job ' . $ref . ' geschlossen.', $userId);

        return ['ok' => true, 'message' => 'LinkedIn-Job geschlossen.'];
    }

    /**
     * @return array{published: int, errors: int, skipped: int, messages: list<string>}
     */
    public static function publishQueued(int $limit = 20, ?int $userId = null): array
    {
        $result = ['published' => 0, 'errors' => 0, 'skipped' => 0, 'messages' => []];
        if (!LinkedInRecruitingSettings::isReady() || !JobPostingRepository::publishChannelsReady()) {
            $result['skipped']++;
            $result['messages'][] = 'LinkedIn nicht bereit oder Schema fehlt.';

            return $result;
        }

        $stmt = Database::pdo()->prepare(
            "SELECT id FROM dg_job_postings
             WHERE export_linkedin = 1
               AND status = :published
               AND linkedin_publish_status = :queued
             ORDER BY id ASC
             LIMIT " . max(1, min(50, $limit))
        );
        $stmt->execute([
            'published' => JobPostingRepository::STATUS_PUBLISHED,
            'queued' => JobPostingRepository::PUBLISH_QUEUED,
        ]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!is_array($ids) || $ids === []) {
            return $result;
        }

        foreach ($ids as $id) {
            $r = self::publishJob((int) $id, $userId);
            $result['messages'][] = '#' . (int) $id . ': ' . $r['message'];
            if ($r['ok']) {
                $result['published']++;
            } else {
                $result['errors']++;
            }
        }

        return $result;
    }

    private static function markPublished(int $jobId, string $externalId, string $message, ?int $userId): void
    {
        $prev = JobPostingRepository::find($jobId);
        $from = (string) ($prev['linkedin_publish_status'] ?? JobPostingRepository::PUBLISH_QUEUED);
        Database::pdo()->prepare(
            'UPDATE dg_job_postings SET
                linkedin_publish_status = :st,
                linkedin_external_ref = :ref,
                linkedin_published_at = :at,
                linkedin_last_message = :msg
             WHERE id = :id'
        )->execute([
            'st' => JobPostingRepository::PUBLISH_PUBLISHED,
            'ref' => mb_substr($externalId, 0, 191),
            'at' => date('Y-m-d H:i:s'),
            'msg' => mb_substr($message, 0, 500),
            'id' => $jobId,
        ]);
        JobPostingRepository::addPublishEvent(
            $jobId,
            JobPostingRepository::CHANNEL_LINKEDIN,
            'published',
            $from,
            JobPostingRepository::PUBLISH_PUBLISHED,
            $message,
            $userId
        );
    }

    private static function markError(int $jobId, string $message, ?int $userId): void
    {
        $prev = JobPostingRepository::find($jobId);
        $from = (string) ($prev['linkedin_publish_status'] ?? JobPostingRepository::PUBLISH_QUEUED);
        Database::pdo()->prepare(
            'UPDATE dg_job_postings SET
                linkedin_publish_status = :st,
                linkedin_last_message = :msg
             WHERE id = :id'
        )->execute([
            'st' => JobPostingRepository::PUBLISH_ERROR,
            'msg' => mb_substr($message, 0, 500),
            'id' => $jobId,
        ]);
        JobPostingRepository::addPublishEvent(
            $jobId,
            JobPostingRepository::CHANNEL_LINKEDIN,
            'error',
            $from,
            JobPostingRepository::PUBLISH_ERROR,
            $message,
            $userId
        );
    }

    private static function markWithdrawnLocal(int $jobId, string $message, ?int $userId): void
    {
        $prev = JobPostingRepository::find($jobId);
        $from = (string) ($prev['linkedin_publish_status'] ?? JobPostingRepository::PUBLISH_NONE);
        Database::pdo()->prepare(
            'UPDATE dg_job_postings SET
                export_linkedin = 0,
                linkedin_publish_status = :st,
                linkedin_last_message = :msg
             WHERE id = :id'
        )->execute([
            'st' => JobPostingRepository::PUBLISH_WITHDRAWN,
            'msg' => mb_substr($message, 0, 500),
            'id' => $jobId,
        ]);
        JobPostingRepository::addPublishEvent(
            $jobId,
            JobPostingRepository::CHANNEL_LINKEDIN,
            'withdrawn',
            $from,
            JobPostingRepository::PUBLISH_WITHDRAWN,
            $message,
            $userId
        );
    }
}
