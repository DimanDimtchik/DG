<?php

declare(strict_types=1);

/**
 * Übersichtskarten für E-Mail-Postfächer (P2) — ohne Live-IMAP-Probe (schnell).
 */
final class MailboxListService
{
    /**
     * @return list<array{
     *   id: int,
     *   name: string,
     *   email: string,
     *   type: string,
     *   type_label: string,
     *   imap_ready: bool,
     *   smtp_ready: bool,
     *   status_label: string,
     *   status_severity: 'ok'|'warn'|'error',
     *   unread: int,
     *   last_activity: string|null,
     *   open_href: string
     * }>
     */
    public static function cardsForUser(User $user): array
    {
        $boxes = MailboxRepository::accessibleForUser($user);
        if ($boxes === []) {
            return [];
        }

        $ids = [];
        foreach ($boxes as $box) {
            $id = (int) ($box['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $unreadMap = MailLogRepository::unreadCountsByMailbox($ids);
        $activityMap = MailLogRepository::lastActivityByMailbox($ids);
        $globalSmtp = class_exists('MailSettings') && MailSettings::isConfigured();

        $cards = [];
        foreach ($boxes as $box) {
            $id = (int) ($box['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $imapReady = ImapMailboxClient::hasCredentials($box);
            $smtpReady = MailboxRepository::smtpIsConfigured($box) || $globalSmtp;
            $type = (string) ($box['type'] ?? 'shared');
            [$statusLabel, $severity] = self::statusFor($imapReady, $smtpReady, !empty($box['is_active']));

            $cards[] = [
                'id' => $id,
                'name' => trim((string) ($box['name'] ?? '')) !== ''
                    ? (string) $box['name']
                    : (string) ($box['email_address'] ?? ''),
                'email' => (string) ($box['email_address'] ?? ''),
                'type' => $type,
                'type_label' => $type === 'private' ? 'Privat' : 'Gemeinsam',
                'imap_ready' => $imapReady,
                'smtp_ready' => $smtpReady,
                'status_label' => $statusLabel,
                'status_severity' => $severity,
                'unread' => (int) ($unreadMap[$id] ?? 0),
                'last_activity' => $activityMap[$id] ?? null,
                'open_href' => '/app?page=post&mailbox=' . $id,
            ];
        }

        return $cards;
    }

    /**
     * @return array{0: string, 1: 'ok'|'warn'|'error'}
     */
    private static function statusFor(bool $imapReady, bool $smtpReady, bool $active): array
    {
        if (!$active) {
            return ['Deaktiviert', 'error'];
        }
        if ($imapReady && $smtpReady) {
            return ['Bereit (IMAP + SMTP)', 'ok'];
        }
        if ($imapReady && !$smtpReady) {
            return ['Lesen möglich — SMTP fehlt', 'warn'];
        }
        if (!$imapReady && $smtpReady) {
            return ['Senden möglich — IMAP fehlt', 'warn'];
        }

        return ['Zugangsdaten unvollständig', 'error'];
    }
}
