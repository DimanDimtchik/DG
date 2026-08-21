<?php
declare(strict_types=1);

/** Signaturzeile für Post-Versand — personalisiert bei Adress-Formel, sonst Firmen-Team. */
final class MailSignatureResolver
{
    /**
     * Führt aus: resolve.
     * @param array|null $mailbox
     * @param User|null $actor
     * @return string
     */
    public static function resolve(?array $mailbox, ?User $actor = null): string
    {
        if ($mailbox !== null && self::mailboxUsesFormulaEmail($mailbox)) {
            $personal = self::personalName($mailbox, $actor);
            if ($personal !== '') {
                return $personal;
            }
        }

        return self::genericSignature();
    }

    /**
     * Methode generic signature.
     * @return string
     */
    public static function genericSignature(): string
    {
        return EmailLayoutSettings::resolvedFooter()['signature'];
    }

    /**
     * Methode mailbox uses formula email.
     * @param array $mailbox
     * @return bool
     */
    private static function mailboxUsesFormulaEmail(array $mailbox): bool
    {
        if (!MailAddressSettings::config()['enabled']) {
            return false;
        }

        $email = strtolower(trim((string) ($mailbox['email_address'] ?? '')));
        if ($email === '') {
            return false;
        }

        $person = self::personContextForMailbox($mailbox);

        return $person !== null && MailAddressBuilder::matchesFormula($email, $person);
    }

    /**
     * Methode person context for mailbox.
     * @param array $mailbox
     * @return array{first_name: string, last_name: string, login: string}|null
     */
    private static function personContextForMailbox(array $mailbox): ?array
    {
        $contact = self::mailboxContact($mailbox);
        if ($contact === null) {
            return null;
        }

        return MailAddressBuilder::personContextFromContact($contact);
    }

    /**
     * Methode mailbox contact.
     * @param array $mailbox
     * @return Contact|null
     */
    private static function mailboxContact(array $mailbox): ?Contact
    {
        $contactId = (int) ($mailbox['contact_id'] ?? 0);
        if ($contactId > 0) {
            return ContactRepository::findById($contactId);
        }

        $ownerUserId = (int) ($mailbox['owner_user_id'] ?? 0);
        if ($ownerUserId <= 0) {
            return null;
        }

        $user = UserRepository::findById($ownerUserId);
        if ($user === null) {
            return null;
        }

        $staffContactId = ContactRepository::findStaffContactIdForUser($user);
        if ($staffContactId === null || $staffContactId <= 0) {
            return null;
        }

        return ContactRepository::findById($staffContactId);
    }

    /**
     * Methode personal name.
     * @param array $mailbox
     * @param User|null $actor
     * @return string
     */
    private static function personalName(array $mailbox, ?User $actor): string
    {
        $contact = self::mailboxContact($mailbox);
        if ($contact !== null) {
            $label = trim($contact->listLabel());
            if ($label !== '') {
                return $label;
            }
        }

        $fromName = trim(MailboxRepository::displayFromName($mailbox));
        if ($fromName !== '') {
            return $fromName;
        }

        if ($actor !== null && trim($actor->displayName) !== '') {
            return trim($actor->displayName);
        }

        return '';
    }

    /**
     * Methode settings examples.
     * @return array<string, mixed>
     */
    public static function settingsExamples(): array
    {
        $footer = EmailLayoutSettings::resolvedFooter();
        $formulaEmail = MailAddressBuilder::preview([
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'login' => 'maxm',
        ]);

        return [
            'formula_email' => $formulaEmail,
            'formula_signature_example' => 'Max Mustermann',
            'generic_signature' => self::genericSignature(),
            'thanks_line' => (string) ($footer['thanks_line'] ?? ''),
            'salutation' => (string) ($footer['salutation'] ?? ''),
        ];
    }
}
