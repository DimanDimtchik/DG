<?php
declare(strict_types=1);

/** Zuordnung der Steuerkanzlei zu einem Firmen-Kontakt. */
final class TaxAdvisorSettings
{
    public const STORE_KEY = 'tax_advisor';

    /**
     * Liefert die Standardwerte.
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'contact_id' => 0,
        ];
    }

    /**
     * Methode for form.
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        if (!Database::isConfigured()) {
            return [
                'contact_id' => 0,
                'contact' => null,
                'employees' => [],
            ];
        }

        $stored = SettingsStore::get(self::STORE_KEY, self::defaults());
        $contactId = max(0, (int) ($stored['contact_id'] ?? 0));
        $contact = self::resolveCompanyContact($contactId);

        return [
            'contact_id' => $contact?->id ?? 0,
            'contact' => $contact,
            'employees' => $contact !== null
                ? ContactCompanyLinkRepository::employeesForCompany($contact->id)
                : [],
        ];
    }

    /**
     * Methode selected contact id.
     * @return int
     */
    public static function selectedContactId(): int
    {
        return self::forForm()['contact_id'];
    }

    public static function hasAdvisor(): bool
    {
        return self::selectedContactId() > 0;
    }

    /** Kunde ohne Steuerberater — DIY-Modus mit Kanzlei-Funktionen in der App. */
    public static function isDiyMode(): bool
    {
        return !self::hasAdvisor();
    }

    /**
     * Speichert Formulardaten.
     * @param array $input
     * @return void
     * @throws InvalidArgumentException
     */
    public static function saveFromPost(array $input): void
    {
        $contactId = max(0, (int) ($input['tax_advisor_contact_id'] ?? 0));
        if ($contactId > 0 && self::resolveCompanyContact($contactId) === null) {
            throw new InvalidArgumentException('Bitte eine gültige Firma aus den Kontakten als Steuerkanzlei wählen.');
        }

        SettingsStore::set(self::STORE_KEY, ['contact_id' => $contactId]);
    }

    /**
     * Führt aus: resolve company contact.
     * @param int $contactId
     * @return Contact|null
     */
    private static function resolveCompanyContact(int $contactId): ?Contact
    {
        if ($contactId <= 0) {
            return null;
        }

        $contact = ContactRepository::findById($contactId);

        return $contact !== null && $contact->isCompany() ? $contact : null;
    }
}
