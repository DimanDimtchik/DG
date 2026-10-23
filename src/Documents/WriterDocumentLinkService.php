<?php

declare(strict_types=1);

/**
 * CRM-Verknüpfung Dokumentenverfasser ↔ Kontakt / Beleg (D6), analog Post P5.
 */
final class WriterDocumentLinkService
{
    /**
     * @param array<string, mixed> $doc WriterDocument::fromRow()-Struktur
     * @return array{contact: ?Contact, voucher: ?array<string, mixed>}
     */
    public static function contextForDocument(array $doc): array
    {
        $contactId = (int) ($doc['contact_id'] ?? 0);
        $contact = $contactId > 0 ? ContactRepository::findById($contactId) : null;
        $voucherId = (int) ($doc['voucher_id'] ?? 0);
        $voucher = null;
        if ($voucherId > 0 && class_exists('VoucherRepository')) {
            $voucher = VoucherRepository::findById($voucherId);
        }

        return [
            'contact' => $contact,
            'voucher' => $voucher,
        ];
    }

    public static function linkContact(User $actor, int $documentId, int $contactId, bool $fillRecipientIfEmpty = true): void
    {
        $doc = self::loadDocument($documentId);
        $contact = ContactRepository::findById($contactId);
        if ($contact === null) {
            throw new InvalidArgumentException('Kontakt nicht gefunden.');
        }
        if (!ContactAccessResolver::canViewContact($actor, $contact)) {
            throw new RuntimeException('Keine Berechtigung für diesen Kontakt.');
        }

        $previous = (int) ($doc['contact_id'] ?? 0) ?: null;
        WriterDocumentRepository::setContactId($documentId, $contactId, $actor->id);

        if ($fillRecipientIfEmpty && self::recipientIsEmpty($doc)) {
            $lines = self::recipientLinesFromContact($contact);
            if ($lines !== []) {
                WriterDocumentRepository::setRecipientLines($documentId, $lines, $actor->id);
            }
        }

        self::audit($actor, 'writer_link_contact', [
            'document_id' => $documentId,
            'contact_id' => $contactId,
            'previous_contact_id' => $previous,
            'filled_recipient' => $fillRecipientIfEmpty && self::recipientIsEmpty($doc),
        ]);
    }

    public static function unlinkContact(User $actor, int $documentId): void
    {
        $doc = self::loadDocument($documentId);
        $previous = (int) ($doc['contact_id'] ?? 0) ?: null;
        WriterDocumentRepository::setContactId($documentId, null, $actor->id);
        self::audit($actor, 'writer_unlink_contact', [
            'document_id' => $documentId,
            'previous_contact_id' => $previous,
        ]);
    }

    public static function linkVoucher(User $actor, int $documentId, string $voucherRef): void
    {
        self::assertVoucherAccess($actor);
        $doc = self::loadDocument($documentId);
        $voucher = PostMailLinkService::findVoucherByRef($voucherRef);
        if ($voucher === null) {
            throw new InvalidArgumentException('Beleg nicht gefunden (ID oder Belegnummer).');
        }
        $voucherId = (int) ($voucher['id'] ?? 0);
        $previous = (int) ($doc['voucher_id'] ?? 0) ?: null;
        WriterDocumentRepository::setVoucherId($documentId, $voucherId, $actor->id);
        self::audit($actor, 'writer_link_voucher', [
            'document_id' => $documentId,
            'voucher_id' => $voucherId,
            'invoice_number' => (string) ($voucher['invoice_number'] ?? ''),
            'previous_voucher_id' => $previous,
        ]);
    }

    public static function unlinkVoucher(User $actor, int $documentId): void
    {
        self::assertVoucherAccess($actor);
        $doc = self::loadDocument($documentId);
        $previous = (int) ($doc['voucher_id'] ?? 0) ?: null;
        WriterDocumentRepository::setVoucherId($documentId, null, $actor->id);
        self::audit($actor, 'writer_unlink_voucher', [
            'document_id' => $documentId,
            'previous_voucher_id' => $previous,
        ]);
    }

    /**
     * @return list<string>
     */
    public static function recipientLinesFromContact(Contact $contact): array
    {
        $lines = [];
        $label = trim($contact->listLabel());
        if ($label !== '') {
            $lines[] = $label;
        }
        $street = trim($contact->address1Street);
        $extra = trim($contact->address1Extra);
        if ($street !== '') {
            $lines[] = $street;
        }
        if ($extra !== '') {
            $lines[] = $extra;
        }
        $cityLine = trim($contact->address1Postal . ' ' . $contact->address1City);
        if ($cityLine !== '') {
            $lines[] = $cityLine;
        }
        $country = trim($contact->address1Country);
        if ($country !== '' && strtoupper($country) !== 'DE') {
            $lines[] = $country;
        }

        return array_slice(array_values(array_filter($lines, static fn(string $l): bool => $l !== '')), 0, 12);
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadDocument(int $documentId): array
    {
        if ($documentId < 1) {
            throw new InvalidArgumentException('Dokument ungültig.');
        }
        $doc = WriterDocumentRepository::findDocument($documentId);
        if ($doc === null) {
            throw new InvalidArgumentException('Dokument nicht gefunden.');
        }

        return $doc;
    }

    /**
     * @param array<string, mixed> $doc
     */
    private static function recipientIsEmpty(array $doc): bool
    {
        $lines = is_array($doc['recipient_lines'] ?? null) ? $doc['recipient_lines'] : [];
        foreach ($lines as $line) {
            if (trim((string) $line) !== '') {
                return false;
            }
        }

        return true;
    }

    private static function assertVoucherAccess(User $actor): void
    {
        if (
            !MenuRegistry::canAccess($actor, 'buchhaltung-beleg-form')
            && !MenuRegistry::canAccess($actor, 'buchhaltung-belege')
        ) {
            throw new RuntimeException('Keine Berechtigung für Belege.');
        }
    }

    /**
     * @param array<string, mixed> $details
     */
    private static function audit(User $actor, string $action, array $details): void
    {
        AuditLog::record($action, $actor->id, json_encode($details, JSON_UNESCAPED_UNICODE));
    }
}
