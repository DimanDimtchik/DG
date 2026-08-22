<?php
declare(strict_types=1);

/** Versand von Ausgangsbelegen (Angebot, Rechnung, …) per E-Mail. */
final class VoucherDocumentMailService
{
    /**
     * @param array<string, mixed> $voucher
     */
    public static function canSend(array $voucher): bool
    {
        if (!Database::isConfigured() || !MailSettings::isConfigured()) {
            return false;
        }

        $kind = VoucherDocumentKind::sanitize((string) ($voucher['document_kind'] ?? ''));
        if ($kind === '') {
            return false;
        }

        return VoucherRepository::normalizeVoucherType((string) ($voucher['voucher_type'] ?? '')) === 'income'
            && empty($voucher['is_draft']);
    }

    public static function defaultRecipient(int $contactId): string
    {
        if ($contactId < 1) {
            return '';
        }

        $contact = ContactRepository::findById($contactId);
        if ($contact === null) {
            return '';
        }

        $email = trim($contact->email);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        $email2 = trim($contact->email2);
        if ($email2 !== '' && filter_var($email2, FILTER_VALIDATE_EMAIL)) {
            return $email2;
        }

        return '';
    }

    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public static function send(
        int $voucherId,
        string $to,
        string $subject,
        string $intro,
        User $actor,
        bool $markAsSent = true,
        bool $attachDocument = true,
    ): void {
        if ($voucherId < 1) {
            throw new InvalidArgumentException('Beleg nicht gefunden.');
        }

        $voucher = VoucherRepository::findById($voucherId);
        if ($voucher === null) {
            throw new InvalidArgumentException('Beleg nicht gefunden.');
        }

        if (!self::canSend($voucher)) {
            throw new InvalidArgumentException('Dieser Beleg kann nicht per E-Mail versendet werden.');
        }

        $recipients = self::parseRecipients($to);
        if ($recipients === []) {
            throw new InvalidArgumentException('Bitte eine gültige E-Mail-Adresse eingeben.');
        }

        $subject = trim($subject);
        if ($subject === '') {
            $subject = VoucherDocumentPrintService::defaultEmailSubject($voucher);
        }

        $intro = trim($intro);
        if ($intro === '') {
            $intro = VoucherDocumentPrintService::defaultEmailIntro($voucher);
        }

        $documentFragment = VoucherDocumentPrintService::renderEmailBodyFragment($voucher);
        $introHtml = nl2br(htmlspecialchars($intro, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
        $htmlBody = '<div style="font-family:Segoe UI,Arial,sans-serif;font-size:14px;color:#1c2330;line-height:1.5;">'
            . '<p>' . $introHtml . '</p>'
            . '<div style="margin-top:16px;border:1px solid #dfe3ea;border-radius:6px;padding:12px;">'
            . $documentFragment
            . '</div></div>';

        $attachments = [];
        if ($attachDocument) {
            $attachmentHtml = VoucherDocumentPrintService::renderAttachmentHtml($voucher);
            $attachments[] = [
                'filename' => VoucherDocumentPrintService::attachmentFilename($voucher),
                'content' => $attachmentHtml,
                'mime' => 'text/html; charset=UTF-8',
            ];
        }

        $contactId = (int) ($voucher['contact_id'] ?? 0);

        MailService::send(
            new MailMessage(
                subject: $subject,
                htmlBody: $htmlBody,
                to: $recipients,
                textBody: $intro,
                contactId: $contactId > 0 ? $contactId : null,
                attachments: $attachments,
            ),
            $actor,
        );

        if ($markAsSent) {
            self::maybeMarkSent($voucher);
        }
    }

    /**
     * @return list<string>
     */
    private static function parseRecipients(string $raw): array
    {
        $parts = preg_split('/[;,]+/', $raw) ?: [];
        $valid = [];
        foreach ($parts as $part) {
            $email = trim($part);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid[] = $email;
            }
        }

        return array_values(array_unique($valid));
    }

    /**
     * @param array<string, mixed> $voucher
     */
    private static function maybeMarkSent(array $voucher): void
    {
        $voucherId = (int) ($voucher['id'] ?? 0);
        $kind = (string) ($voucher['document_kind'] ?? '');
        $current = VoucherDocumentStatus::sanitize((string) ($voucher['document_status'] ?? ''));
        $next = VoucherDocumentStatus::nextStatuses($current, $kind);
        if (!in_array(VoucherDocumentStatus::SENT, $next, true)) {
            return;
        }

        try {
            VoucherRepository::updateDocumentStatus($voucherId, VoucherDocumentStatus::SENT);
        } catch (Throwable) {
            // Versand darf nicht fehlschlagen, wenn Status nicht gesetzt werden kann.
        }
    }
}
