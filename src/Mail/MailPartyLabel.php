<?php
declare(strict_types=1);

/** Anzeigename für Post-Listen — bevorzugt CRM-Kontakt wie in der Kontaktliste. */
final class MailPartyLabel
{
    /** @var array<int, Contact|null> */
    private static array $byId = [];

    /** @var array<string, Contact|null> */
    private static array $byEmail = [];

    /**
     * Methode for message row.
     * @param array $row
     * @return string
     */
    public static function forMessageRow(array $row): string
    {
        $contact = self::resolveContact($row);
        if ($contact !== null) {
            return $contact->listLabel();
        }

        return trim((string) ($row['from_name'] ?? ''));
    }

    /**
     * Führt aus: resolve contact.
     * @param array $row
     * @return Contact|null
     */
    public static function resolveContact(array $row): ?Contact
    {
        $contactId = (int) ($row['contact_id'] ?? 0);
        if ($contactId > 0) {
            return self::contactById($contactId);
        }

        $email = strtolower(trim((string) ($row['from_address'] ?? '')));
        if ($email === '') {
            return null;
        }

        return self::contactByEmail($email);
    }

    /**
     * Methode contact by id.
     * @param int $contactId
     * @return Contact|null
     */
    private static function contactById(int $contactId): ?Contact
    {
        if (!array_key_exists($contactId, self::$byId)) {
            self::$byId[$contactId] = ContactRepository::findById($contactId);
        }

        return self::$byId[$contactId];
    }

    /**
     * Methode contact by email.
     * @param string $email
     * @return Contact|null
     */
    private static function contactByEmail(string $email): ?Contact
    {
        if (!array_key_exists($email, self::$byEmail)) {
            $contactId = MailLogRepository::guessContactId($email);
            self::$byEmail[$email] = $contactId !== null && $contactId > 0
                ? ContactRepository::findById($contactId)
                : null;
        }

        return self::$byEmail[$email];
    }
}
