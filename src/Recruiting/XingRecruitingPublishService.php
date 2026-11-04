<?php
declare(strict_types=1);

/**
 * Veröffentlicht vorgemerkte Stellen auf Xing (nur wenn Vertrag + OAuth bereit).
 * Bewerben-URL bleibt immer die eigene /rekrut-Seite.
 */
final class XingRecruitingPublishService
{
    /**
     * @return array{ok: bool, message: string, posting_id: string, permalink: string}
     */
    public static function publishJob(int $jobId, ?int $userId = null): array
    {
        $empty = ['ok' => false, 'message' => '', 'posting_id' => '', 'permalink' => ''];
        if ($jobId < 1) {
            $empty['message'] = 'Stelle ungültig.';

            return $empty;
        }
        if (!XingRecruitingSettings::isReady()) {
            $cfg = XingRecruitingSettings::config();
            if (!XingRecruitingSettings::hasContract($cfg)) {
                $empty['message'] = 'Xing-Jobvertrag fehlt (organization_id und order_id). Ohne Kundenvertrag kein API-Publish.';
            } else {
                $empty['message'] = 'Xing-API nicht bereit — Credentials unter Einstellungen → HR → Xing prüfen.';
            }

            return $empty;
        }

        $job = JobPostingRepository::find($jobId);
        if ($job === null) {
            $empty['message'] = 'Stelle nicht gefunden.';

            return $empty;
        }
        if (empty($job['export_xing'])) {
            $empty['message'] = 'Xing-Export für diese Stelle ist nicht aktiviert.';

            return $empty;
        }
        if ((string) ($job['status'] ?? '') !== JobPostingRepository::STATUS_PUBLISHED) {
            $empty['message'] = 'Stelle muss zuerst im CRM veröffentlicht sein.';

            return $empty;
        }

        $existingRef = trim((string) ($job['xing_external_ref'] ?? ''));
        if ($existingRef !== '' && (string) ($job['xing_publish_status'] ?? '') === JobPostingRepository::PUBLISH_PUBLISHED) {
            return [
                'ok' => true,
                'message' => 'Bereits auf Xing veröffentlicht (ID ' . $existingRef . ').',
                'posting_id' => $existingRef,
                'permalink' => '',
            ];
        }

        $cfg = XingRecruitingSettings::config();
        $payload = XingRecruitingClient::buildCreatePayload($job, $cfg);
        $create = XingRecruitingClient::createPosting($payload, $cfg);
        if (!$create['ok']) {
            self::markError($jobId, $create['error'] !== '' ? $create['error'] : 'Xing-Create fehlgeschlagen.', $userId);

            return [
                'ok' => false,
                'message' => $create['error'] !== '' ? $create['error'] : 'Xing-Create fehlgeschlagen.',
                'posting_id' => '',
                'permalink' => '',
            ];
        }

        $json = $create['json'] ?? [];
        $postingId = (string) ((int) ($json['posting_id'] ?? 0));
        $permalink = trim((string) ($json['permalink'] ?? ''));
        if ($postingId === '0' || $postingId === '') {
            self::markError($jobId, 'Xing lieferte keine posting_id.', $userId);

            return [
                'ok' => false,
                'message' => 'Xing lieferte keine posting_id.',
                'posting_id' => '',
                'permalink' => '',
            ];
        }

        $activated = true;
        $activateMsg = '';
        if (!empty($cfg['auto_activate'])) {
            $act = XingRecruitingClient::activatePosting($postingId, $cfg);
            if (!$act['ok'] && $act['status'] !== 204) {
                $activated = false;
                $activateMsg = $act['error'] !== '' ? $act['error'] : 'Aktivierung fehlgeschlagen.';
            }
        }

        $msg = 'Xing-Posting #' . $postingId . ' angelegt'
            . ($permalink !== '' ? ' — ' . $permalink : '')
            . ($activated ? ($cfg['auto_activate'] ? ', aktiviert.' : ' (noch nicht aktiviert).') : '. Aktivierung: ' . $activateMsg);

        self::markPublished($jobId, $postingId, $msg, $userId);

        return [
            'ok' => true,
            'message' => $msg,
            'posting_id' => $postingId,
            'permalink' => $permalink,
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public static function withdrawJob(int $jobId, ?int $userId = null): array
    {
        $job = JobPostingRepository::find($jobId);
        if ($job === null) {
            return ['ok' => false, 'message' => 'Stelle nicht gefunden.'];
        }
        $ref = trim((string) ($job['xing_external_ref'] ?? ''));
        if ($ref === '') {
            self::markWithdrawnLocal($jobId, 'Xing-Export zurückgezogen (kein Remote-Posting).', $userId);

            return ['ok' => true, 'message' => 'Lokal zurückgezogen.'];
        }
        if (!XingRecruitingSettings::isReady()) {
            self::markWithdrawnLocal($jobId, 'Lokal zurückgezogen — API nicht bereit, Remote ggf. manuell deaktivieren.', $userId);

            return ['ok' => true, 'message' => 'Lokal zurückgezogen (API nicht bereit).'];
        }

        $res = XingRecruitingClient::deactivatePosting($ref);
        if (!$res['ok'] && $res['status'] !== 204) {
            self::markError($jobId, 'Xing-Deaktivieren: ' . ($res['error'] !== '' ? $res['error'] : 'Fehler'), $userId);

            return ['ok' => false, 'message' => $res['error'] !== '' ? $res['error'] : 'Deaktivieren fehlgeschlagen.'];
        }

        self::markWithdrawnLocal($jobId, 'Xing-Posting #' . $ref . ' deaktiviert.', $userId);

        return ['ok' => true, 'message' => 'Xing-Posting deaktiviert.'];
    }

    /**
     * Alle vorgemerkten Xing-Exports verarbeiten (wenn auto oder manuell).
     *
     * @return array{published: int, errors: int, skipped: int, messages: list<string>}
     */
    public static function publishQueued(int $limit = 20, ?int $userId = null): array
    {
        $result = ['published' => 0, 'errors' => 0, 'skipped' => 0, 'messages' => []];
        if (!XingRecruitingSettings::isReady() || !JobPostingRepository::publishChannelsReady()) {
            $result['skipped']++;
            $result['messages'][] = 'Xing nicht bereit oder Schema fehlt.';

            return $result;
        }

        $stmt = Database::pdo()->prepare(
            "SELECT id FROM dg_job_postings
             WHERE export_xing = 1
               AND status = :published
               AND xing_publish_status = :queued
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

    private static function markPublished(int $jobId, string $postingId, string $message, ?int $userId): void
    {
        $prev = JobPostingRepository::find($jobId);
        $from = (string) ($prev['xing_publish_status'] ?? JobPostingRepository::PUBLISH_QUEUED);
        Database::pdo()->prepare(
            'UPDATE dg_job_postings SET
                xing_publish_status = :st,
                xing_external_ref = :ref,
                xing_published_at = :at,
                xing_last_message = :msg
             WHERE id = :id'
        )->execute([
            'st' => JobPostingRepository::PUBLISH_PUBLISHED,
            'ref' => mb_substr($postingId, 0, 191),
            'at' => date('Y-m-d H:i:s'),
            'msg' => mb_substr($message, 0, 500),
            'id' => $jobId,
        ]);
        JobPostingRepository::addPublishEvent(
            $jobId,
            JobPostingRepository::CHANNEL_XING,
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
        $from = (string) ($prev['xing_publish_status'] ?? JobPostingRepository::PUBLISH_QUEUED);
        Database::pdo()->prepare(
            'UPDATE dg_job_postings SET
                xing_publish_status = :st,
                xing_last_message = :msg
             WHERE id = :id'
        )->execute([
            'st' => JobPostingRepository::PUBLISH_ERROR,
            'msg' => mb_substr($message, 0, 500),
            'id' => $jobId,
        ]);
        JobPostingRepository::addPublishEvent(
            $jobId,
            JobPostingRepository::CHANNEL_XING,
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
        $from = (string) ($prev['xing_publish_status'] ?? JobPostingRepository::PUBLISH_NONE);
        Database::pdo()->prepare(
            'UPDATE dg_job_postings SET
                export_xing = 0,
                xing_publish_status = :st,
                xing_last_message = :msg
             WHERE id = :id'
        )->execute([
            'st' => JobPostingRepository::PUBLISH_WITHDRAWN,
            'msg' => mb_substr($message, 0, 500),
            'id' => $jobId,
        ]);
        JobPostingRepository::addPublishEvent(
            $jobId,
            JobPostingRepository::CHANNEL_XING,
            'withdrawn',
            $from,
            JobPostingRepository::PUBLISH_WITHDRAWN,
            $message,
            $userId
        );
    }
}
