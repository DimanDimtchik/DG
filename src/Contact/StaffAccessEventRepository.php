<?php
declare(strict_types=1);

/** Audit-Log für Mitarbeiter-Zugangseinladungen (Link/Manuell). */
final class StaffAccessEventRepository
{
    public const KIND_CRM = 'crm_password';
    public const KIND_MAILBOX = 'mailbox_password';
    public const KIND_PIN = 'kiosk_pin';
    public const KIND_APP = 'app_invite';

    public const CHANNEL_LINK = 'link';
    public const CHANNEL_MANUAL = 'manual';

    public static function log(
        int $contactId,
        string $kind,
        string $channel,
        ?int $actorUserId = null,
        ?string $detail = null
    ): void {
        if ($contactId < 1 || !Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_staff_access_events (contact_id, kind, channel, actor_user_id, detail)
             VALUES (:cid, :kind, :channel, :actor, :detail)'
        );
        $stmt->execute([
            'cid' => $contactId,
            'kind' => $kind,
            'channel' => $channel,
            'actor' => $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
            'detail' => $detail !== null && $detail !== '' ? mb_substr($detail, 0, 255) : null,
        ]);
    }

    /**
     * Letztes Event je kind für Statuszeilen.
     *
     * @return array<string, array{kind: string, channel: string, created_at: string, detail: ?string}>
     */
    public static function latestByKind(int $contactId): array
    {
        if ($contactId < 1 || !Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'SELECT e.kind, e.channel, e.created_at, e.detail
             FROM dg_staff_access_events e
             INNER JOIN (
                 SELECT kind, MAX(id) AS max_id
                 FROM dg_staff_access_events
                 WHERE contact_id = :cid
                 GROUP BY kind
             ) t ON t.max_id = e.id'
        );
        $stmt->execute(['cid' => $contactId]);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $kind = (string) ($row['kind'] ?? '');
            if ($kind === '') {
                continue;
            }
            $out[$kind] = [
                'kind' => $kind,
                'channel' => (string) ($row['channel'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'detail' => isset($row['detail']) ? (string) $row['detail'] : null,
            ];
        }

        return $out;
    }
}
