<?php

declare(strict_types=1);

/**
 * CRM-Verknüpfung Post ↔ Kontakt / Beleg (P5) inkl. Audit für Prüfbarkeit.
 */
final class PostMailLinkService
{
    /**
     * @param array<string, mixed> $message
     */
    public static function assertAccessible(User $actor, array $message): void
    {
        $mailboxId = (int) ($message['mailbox_id'] ?? 0);
        if ($mailboxId < 1 || !MailboxRepository::userCanAccess($actor, $mailboxId)) {
            throw new RuntimeException('Kein Zugriff auf diese Nachricht.');
        }
    }

    /**
     * @param array<string, mixed> $message
     */
    public static function suggestContactId(array $message): ?int
    {
        $linked = (int) ($message['contact_id'] ?? 0);
        if ($linked > 0) {
            return null;
        }
        $email = self::partyEmail($message);
        if ($email === '') {
            return null;
        }

        return MailLogRepository::guessContactId($email);
    }

    /**
     * @param array<string, mixed> $message
     * @return array{
     *   contact: ?Contact,
     *   suggested: ?Contact,
     *   voucher: ?array<string, mixed>,
     *   party_email: string
     * }
     */
    public static function contextForMessage(array $message): array
    {
        $contactId = (int) ($message['contact_id'] ?? 0);
        $contact = $contactId > 0 ? ContactRepository::findById($contactId) : null;
        $suggestedId = self::suggestContactId($message);
        $suggested = $suggestedId !== null ? ContactRepository::findById($suggestedId) : null;
        $voucherId = (int) ($message['voucher_id'] ?? 0);
        $voucher = null;
        if ($voucherId > 0 && class_exists('VoucherRepository')) {
            $voucher = VoucherRepository::findById($voucherId);
        }

        return [
            'contact' => $contact,
            'suggested' => $suggested,
            'voucher' => $voucher,
            'party_email' => self::partyEmail($message),
        ];
    }

    public static function linkContact(User $actor, int $mailId, int $contactId): void
    {
        $message = self::loadMail($actor, $mailId);
        $contact = ContactRepository::findById($contactId);
        if ($contact === null) {
            throw new InvalidArgumentException('Kontakt nicht gefunden.');
        }
        MailLogRepository::setContactId($mailId, $contactId);
        self::audit($actor, 'mail_link_contact', [
            'mail_log_id' => $mailId,
            'contact_id' => $contactId,
            'previous_contact_id' => (int) ($message['contact_id'] ?? 0) ?: null,
        ]);
    }

    public static function unlinkContact(User $actor, int $mailId): void
    {
        $message = self::loadMail($actor, $mailId);
        MailLogRepository::setContactId($mailId, null);
        self::audit($actor, 'mail_unlink_contact', [
            'mail_log_id' => $mailId,
            'previous_contact_id' => (int) ($message['contact_id'] ?? 0) ?: null,
        ]);
    }

    public static function linkVoucher(User $actor, int $mailId, string $voucherRef): void
    {
        $message = self::loadMail($actor, $mailId);
        if (!MenuRegistry::canAccess($actor, 'buchhaltung-beleg-form') && !MenuRegistry::canAccess($actor, 'buchhaltung-belege')) {
            throw new RuntimeException('Keine Berechtigung für Belege.');
        }
        $voucher = self::findVoucherByRef($voucherRef);
        if ($voucher === null) {
            throw new InvalidArgumentException('Beleg nicht gefunden (ID oder Belegnummer).');
        }
        $voucherId = (int) ($voucher['id'] ?? 0);
        MailLogRepository::setVoucherId($mailId, $voucherId);
        self::audit($actor, 'mail_link_voucher', [
            'mail_log_id' => $mailId,
            'voucher_id' => $voucherId,
            'invoice_number' => (string) ($voucher['invoice_number'] ?? ''),
            'previous_voucher_id' => (int) ($message['voucher_id'] ?? 0) ?: null,
        ]);
    }

    public static function unlinkVoucher(User $actor, int $mailId): void
    {
        $message = self::loadMail($actor, $mailId);
        if (!MenuRegistry::canAccess($actor, 'buchhaltung-beleg-form') && !MenuRegistry::canAccess($actor, 'buchhaltung-belege')) {
            throw new RuntimeException('Keine Berechtigung für Belege.');
        }
        MailLogRepository::setVoucherId($mailId, null);
        self::audit($actor, 'mail_unlink_voucher', [
            'mail_log_id' => $mailId,
            'previous_voucher_id' => (int) ($message['voucher_id'] ?? 0) ?: null,
        ]);
    }

    /**
     * Kontakt aus ID oder E-Mail-Adresse auflösen.
     */
    public static function resolveContactRef(string $ref): ?Contact
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }
        if (ctype_digit($ref)) {
            return ContactRepository::findById((int) $ref);
        }
        $email = EmailNormalizer::normalize($ref);
        if ($email === '' || !EmailNormalizer::isValidSyntax($email)) {
            return null;
        }
        $id = MailLogRepository::guessContactId($email);

        return $id !== null ? ContactRepository::findById($id) : ContactRepository::findByEmailMatch($email);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findVoucherByRef(string $ref): ?array
    {
        $ref = trim($ref);
        if ($ref === '' || !Database::isConfigured()) {
            return null;
        }
        if (ctype_digit($ref)) {
            return VoucherRepository::findById((int) $ref);
        }
        $stmt = Database::pdo()->prepare(
            'SELECT id FROM dg_vouchers WHERE invoice_number = :n ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['n' => mb_substr($ref, 0, 100)]);
        $id = (int) ($stmt->fetchColumn() ?: 0);

        return $id > 0 ? VoucherRepository::findById($id) : null;
    }

    /**
     * @param array<string, mixed> $message
     */
    public static function recordOpen(User $actor, array $message): void
    {
        $id = (int) ($message['id'] ?? 0);
        if ($id < 1) {
            return;
        }
        self::audit($actor, 'mail_open', [
            'mail_log_id' => $id,
            'mailbox_id' => (int) ($message['mailbox_id'] ?? 0),
            'direction' => (string) ($message['direction'] ?? ''),
            'subject' => mb_substr((string) ($message['subject'] ?? ''), 0, 200),
            'contact_id' => (int) ($message['contact_id'] ?? 0) ?: null,
            'voucher_id' => (int) ($message['voucher_id'] ?? 0) ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadMail(User $actor, int $mailId): array
    {
        if ($mailId < 1) {
            throw new InvalidArgumentException('Nachricht ungültig.');
        }
        $message = MailLogRepository::findById($mailId);
        if ($message === null) {
            throw new InvalidArgumentException('Nachricht nicht gefunden.');
        }
        self::assertAccessible($actor, $message);

        return $message;
    }

    /**
     * @param array<string, mixed> $message
     */
    private static function partyEmail(array $message): string
    {
        if (($message['direction'] ?? '') === 'out') {
            $to = $message['to_addresses'] ?? [];
            if (is_string($to)) {
                $decoded = json_decode($to, true);
                $to = is_array($decoded) ? $decoded : [];
            }
            if (is_array($to) && $to !== []) {
                return EmailNormalizer::normalize((string) $to[0]);
            }
        }

        return EmailNormalizer::normalize((string) ($message['from_address'] ?? ''));
    }

    /**
     * @param array<string, mixed> $details
     */
    private static function audit(User $actor, string $action, array $details): void
    {
        AuditLog::record($action, $actor->id, json_encode($details, JSON_UNESCAPED_UNICODE));
    }
}
