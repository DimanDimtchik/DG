<?php
declare(strict_types=1);

final class MailAddressBuilder
{
    /**
     * @param array{first_name?: string, last_name?: string, login?: string} $person
     */
    public static function buildLocalPart(array $person, int $collisionNr = 0): string
    {
        $cfg = MailAddressSettings::config();

        return MailAddressTokens::resolveLocalPart($cfg['local_pattern'], [
            'first_name' => (string) ($person['first_name'] ?? ''),
            'last_name' => (string) ($person['last_name'] ?? ''),
            'login' => (string) ($person['login'] ?? ''),
            'separator' => $cfg['separator'],
        ], $collisionNr);
    }

    /**
     * @param array{first_name?: string, last_name?: string, login?: string} $person
     */
    public static function buildEmail(array $person, int $collisionNr = 0): string
    {
        $domain = MailAddressSettings::effectiveDomain();
        if ($domain === '') {
            throw new InvalidArgumentException('Mail-Domain ist nicht konfiguriert (Einstellungen → E-Mail).');
        }

        $local = self::buildLocalPart($person, $collisionNr);
        if ($local === '') {
            throw new InvalidArgumentException('E-Mail-Lokalteil konnte nicht gebildet werden (Vorname/Nachname/Login prüfen).');
        }

        return $local . '@' . $domain;
    }

    /**
     * @param array{first_name?: string, last_name?: string, login?: string} $person
     */
    public static function allocateUniqueEmail(array $person): string
    {
        for ($i = 0; $i < 50; $i++) {
            $email = strtolower(self::buildEmail($person, $i));
            if (!self::isEmailTaken($email)) {
                return $email;
            }
        }

        throw new RuntimeException('Keine freie E-Mail-Adresse nach Formel gefunden.');
    }

    /** @return array{first_name: string, last_name: string, login: string} */
    public static function personContextFromContact(Contact $contact): array
    {
        return [
            'first_name' => $contact->firstName,
            'last_name' => $contact->lastName,
            'login' => $contact->login,
        ];
    }

    /**
     * @param array{first_name?: string, last_name?: string, login?: string} $person
     */
    public static function matchesFormula(string $email, array $person): bool
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return true;
        }

        try {
            $domain = MailAddressSettings::effectiveDomain();
        } catch (Throwable) {
            return false;
        }

        if ($domain === '' || !str_ends_with($email, '@' . strtolower($domain))) {
            return false;
        }

        for ($i = 0; $i < 50; $i++) {
            if ($email === strtolower(self::buildEmail($person, $i))) {
                return true;
            }
        }

        return false;
    }

    public static function isEmailTaken(string $email, ?int $excludeContactId = null): bool
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return false;
        }

        return MailboxRepository::emailExists($email)
            || ContactRepository::isEmailInUse($email, $excludeContactId)
            || ContactCompanyLinkRepository::isWorkEmailInUse($email, $excludeContactId);
    }

    /** @return list<array{email: string, label: string}> */
    private static function enteredMailCandidates(Contact $contact): array
    {
        $candidates = [
            ['email' => strtolower(trim($contact->email)), 'label' => 'E-Mail'],
            ['email' => strtolower(trim($contact->email2)), 'label' => 'E-Mail 2'],
        ];

        if ($contact->id > 0 && !$contact->isCompany()) {
            $employerForm = ContactCompanyLinkRepository::employerFormForPerson($contact->id);
            $candidates[] = [
                'email' => strtolower(trim((string) ($employerForm['employer_work_email'] ?? ''))),
                'label' => 'E-Mail (dienstlich)',
            ];
        }

        return array_values(array_filter(
            $candidates,
            static fn(array $row): bool => $row['email'] !== ''
        ));
    }

    /**
     * Prüft, ob eine automatische Adresse angelegt werden darf (ohne Kollisions-Zähler).
     *
     * @return array{ok: bool, email: string, reason: string}
     */
    public static function evaluateAutoCreate(Contact $contact): array
    {
        $person = self::personContextFromContact($contact);
        $entered = strtolower(trim($contact->email));
        $employerWork = '';
        foreach (self::enteredMailCandidates($contact) as $row) {
            if ($row['label'] === 'E-Mail (dienstlich)') {
                $employerWork = $row['email'];
                break;
            }
        }

        try {
            $formulaEmail = strtolower(self::buildEmail($person, 0));
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'email' => '',
                'reason' => $e->getMessage(),
            ];
        }

        $excludeId = $contact->id > 0 ? $contact->id : null;

        foreach (self::enteredMailCandidates($contact) as $candidate) {
            if (!self::matchesFormula($candidate['email'], $person)) {
                return [
                    'ok' => false,
                    'email' => $candidate['email'],
                    'reason' => $candidate['label'] . ' entspricht nicht der Adress-Formel (Einstellungen → E-Mail).',
                ];
            }
            if (self::isEmailTaken($candidate['email'], $excludeId)) {
                return [
                    'ok' => false,
                    'email' => $candidate['email'],
                    'reason' => $candidate['label'] . ' „' . $candidate['email'] . '“ ist bereits vergeben.',
                ];
            }
        }

        $targetEmail = $entered !== ''
            ? $entered
            : ($employerWork !== '' ? $employerWork : $formulaEmail);

        if (!self::matchesFormula($targetEmail, $person)) {
            return [
                'ok' => false,
                'email' => $targetEmail,
                'reason' => 'Die Adress-Formel konnte für diesen Kontakt nicht angewendet werden.',
            ];
        }

        if (self::isEmailTaken($targetEmail, $excludeId)) {
            return [
                'ok' => false,
                'email' => $targetEmail,
                'reason' => 'Die Adresse „' . $targetEmail . '“ nach Formel ist bereits vergeben.',
            ];
        }

        return [
            'ok' => true,
            'email' => $targetEmail,
            'reason' => '',
        ];
    }

    /**
     * @param array{first_name?: string, last_name?: string, login?: string} $person
     */
    public static function preview(array $person): string
    {
        try {
            return self::buildEmail($person, 0);
        } catch (Throwable) {
            return '';
        }
    }
}
