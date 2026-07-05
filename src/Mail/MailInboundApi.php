<?php
declare(strict_types=1);

/** Webhook-Empfang von IMAP→Webhook-Diensten (Variante B). */
final class MailInboundApi
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'POST required'], JSON_THROW_ON_ERROR);
            return;
        }

        if (!Database::isConfigured()) {
            http_response_code(503);
            echo json_encode(['ok' => false, 'error' => 'Database not configured'], JSON_THROW_ON_ERROR);
            return;
        }

        $token = trim((string) ($_GET['token'] ?? $_SERVER['HTTP_X_MAILBOX_TOKEN'] ?? ''));
        if ($token === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Missing token'], JSON_THROW_ON_ERROR);
            return;
        }

        $mailbox = MailboxRepository::findByWebhookToken($token);
        if ($mailbox === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Unknown mailbox'], JSON_THROW_ON_ERROR);
            return;
        }

        $raw = file_get_contents('php://input') ?: '';
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid JSON'], JSON_THROW_ON_ERROR);
            return;
        }

        try {
            $parsed = self::normalizePayload($payload);
            $messageId = trim((string) ($parsed['message_id'] ?? ''));
            if ($messageId !== '' && MailLogRepository::inboundExists($messageId, (int) $mailbox['id'])) {
                echo json_encode(['ok' => true, 'duplicate' => true], JSON_THROW_ON_ERROR);
                return;
            }

            $logId = MailLogRepository::createInbound(
                mailboxId: (int) $mailbox['id'],
                fromEmail: $parsed['from_email'],
                fromName: $parsed['from_name'],
                toAddresses: $parsed['to'],
                ccAddresses: $parsed['cc'],
                subject: $parsed['subject'],
                bodyPreview: $parsed['body_preview'],
                messageId: $messageId !== '' ? $messageId : null,
                contactId: MailLogRepository::guessContactId($parsed['from_email']),
            );

            if ($parsed['mime'] !== '') {
                $archive = MailArchiveStorage::saveInbound($logId, $parsed['mime']);
                MailLogRepository::markArchived($logId, $archive['path'], $archive['size'], $messageId);
                MailLogRepository::markReceived($logId);
            } else {
                $mime = self::buildSimpleMime($parsed, $messageId);
                $archive = MailArchiveStorage::saveInbound($logId, $mime);
                MailLogRepository::markArchived($logId, $archive['path'], $archive['size'], $messageId);
                MailLogRepository::markReceived($logId);
            }

            http_response_code(200);
            echo json_encode(['ok' => true, 'id' => $logId], JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_THROW_ON_ERROR);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *   from_email: string,
     *   from_name: string,
     *   to: list<string>,
     *   cc: list<string>,
     *   subject: string,
     *   body_preview: string,
     *   text: string,
     *   html: string,
     *   mime: string,
     *   message_id: string
     * }
     */
    private static function normalizePayload(array $payload): array
    {
        $email = $payload['email'] ?? $payload['message'] ?? $payload;
        if (!is_array($email)) {
            $email = $payload;
        }

        $fromRaw = (string) ($email['from'] ?? $payload['from'] ?? '');
        [$fromEmail, $fromName] = self::parseAddress($fromRaw);

        $to = self::parseAddressList($email['to'] ?? $payload['to'] ?? []);
        $cc = self::parseAddressList($email['cc'] ?? $payload['cc'] ?? []);

        $subject = trim((string) ($email['subject'] ?? $payload['subject'] ?? ''));
        $text = trim((string) ($email['text'] ?? $email['textBody'] ?? $email['text_body'] ?? $payload['text'] ?? ''));
        $html = trim((string) ($email['html'] ?? $email['htmlBody'] ?? $email['html_body'] ?? $payload['html'] ?? ''));
        $mime = trim((string) ($email['raw'] ?? $email['mime'] ?? $email['rawMime'] ?? $payload['raw'] ?? ''));
        $messageId = trim((string) ($email['messageId'] ?? $email['message_id'] ?? $payload['message_id'] ?? ''));

        $preview = $text !== '' ? $text : MailMessage::bodyPreview($html);
        $preview = preg_replace('/\s+/u', ' ', $preview) ?? $preview;

        return [
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'to' => $to,
            'cc' => $cc,
            'subject' => $subject,
            'body_preview' => $preview,
            'text' => $text,
            'html' => $html,
            'mime' => $mime,
            'message_id' => $messageId,
        ];
    }

    /** @return array{0: string, 1: string} */
    private static function parseAddress(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['', ''];
        }
        if (preg_match('/^(.*)<([^>]+)>$/', $raw, $m)) {
            return [trim($m[2]), trim(trim($m[1]), '"')];
        }
        if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return [$raw, ''];
        }

        return [$raw, ''];
    }

    /** @return list<string> */
    private static function parseAddressList(mixed $value): array
    {
        if (is_string($value)) {
            $parts = preg_split('/[,;]/', $value) ?: [];
        } elseif (is_array($value)) {
            $parts = $value;
        } else {
            return [];
        }

        $out = [];
        foreach ($parts as $part) {
            if (is_array($part)) {
                $email = trim((string) ($part['address'] ?? $part['email'] ?? ''));
            } else {
                [$email] = self::parseAddress((string) $part);
            }
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[] = $email;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param array{from_email: string, from_name: string, to: list<string>, subject: string, text: string, html: string} $parsed
     */
    private static function buildSimpleMime(array $parsed, string $messageId): string
    {
        $from = $parsed['from_name'] !== ''
            ? '=?UTF-8?B?' . base64_encode($parsed['from_name']) . '?= <' . $parsed['from_email'] . '>'
            : $parsed['from_email'];
        $to = $parsed['to'] !== [] ? implode(', ', $parsed['to']) : 'undisclosed-recipients:;';
        $date = gmdate('D, d M Y H:i:s') . ' +0000';
        $msgId = $messageId !== '' ? $messageId : '<' . bin2hex(random_bytes(12)) . '@dg-crm>';

        $body = $parsed['html'] !== '' ? $parsed['html'] : nl2br(htmlspecialchars($parsed['text'], ENT_QUOTES, 'UTF-8'));
        $contentType = $parsed['html'] !== '' ? 'text/html' : 'text/plain';
        $bodyRaw = $parsed['html'] !== '' ? $parsed['html'] : $parsed['text'];

        return implode("\r\n", [
            'From: ' . $from,
            'To: ' . $to,
            'Subject: =?UTF-8?B?' . base64_encode($parsed['subject']) . '?=',
            'Date: ' . $date,
            'Message-ID: ' . $msgId,
            'MIME-Version: 1.0',
            'Content-Type: ' . $contentType . '; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $bodyRaw,
        ]);
    }
}
