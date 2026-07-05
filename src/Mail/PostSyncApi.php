<?php
declare(strict_types=1);

/** JSON-API: Postfach-Ordner per IMAP synchronisieren (asynchron vom Browser). */
final class PostSyncApi
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $user = AuthService::user();
        if ($user === null) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Nicht angemeldet'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'GET required'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $mailboxId = max(0, (int) ($_GET['mailbox'] ?? 0));
        $folder = trim((string) ($_GET['folder'] ?? 'INBOX'));
        if ($folder === '') {
            $folder = 'INBOX';
        }
        $force = !empty($_GET['refresh']);

        if ($mailboxId <= 0 || !MailboxRepository::userCanAccess($user, $mailboxId)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Kein Zugriff auf dieses Postfach'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $mailbox = MailboxRepository::findById($mailboxId);
        if ($mailbox === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Postfach nicht gefunden'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $synced = 0;
        $error = null;
        $authFailed = false;
        $imapError = '';
        if (ImapMailboxClient::hasCredentials($mailbox)) {
            try {
                $synced = MailImapSync::syncFolder($mailbox, $folder, $force);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
            $imapError = ImapMailboxClient::lastError();
            $authFailed = ImapMailboxClient::isAuthFailure()
                || ($error !== null && str_contains(strtolower($error), 'anmeldung'));
        }

        $rows = MailLogRepository::folderMessagesForMailboxes([$mailboxId], $folder, 50, $mailboxId);
        $isSent = MailFolderCatalog::usesLocalSent($folder);
        $kasProvisioned = !empty($mailbox['kas_provisioned']);

        $hint = null;
        if ($authFailed && $kasProvisioned) {
            $hint = 'Unter Einstellungen → Postfächer → „IMAP-Passwort zurücksetzen“ klicken, dann erneut abrufen.';
        } elseif ($error !== null && $imapError !== '' && $imapError !== $error) {
            $hint = $imapError;
        }

        echo json_encode([
            'ok' => $error === null,
            'error' => $error,
            'auth_failed' => $authFailed,
            'imap_error' => $imapError !== '' ? $imapError : null,
            'hint' => $hint,
            'phase' => $error === null ? 'done' : 'error',
            'synced' => $synced,
            'folder' => $folder,
            'is_sent' => $isSent,
            'kas_provisioned' => $kasProvisioned,
            'rows' => array_map(static function (array $row): array {
                $displayName = trim((string) ($row['party_display_name'] ?? $row['from_name'] ?? ''));
                $address = (string) ($row['from_address'] ?? '');

                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'is_read' => !empty($row['is_read']),
                    'display_name' => $displayName,
                    'address' => $address,
                    'subject' => (string) ($row['subject'] ?? ''),
                    'date' => (string) ($row['received_at'] ?? $row['created_at'] ?? ''),
                    'url' => (int) ($row['id'] ?? 0) > 0
                        ? '/app?page=post&id=' . (int) $row['id'] . '&mailbox=' . (int) ($row['mailbox_id'] ?? 0)
                        : '',
                ];
            }, $rows),
        ], JSON_UNESCAPED_UNICODE);
    }
}
