<?php
declare(strict_types=1);

/**
 * Multi-Firma MF6c: Einbahn-Import von Contact-Export-JSON (Schema MF6a).
 */
final class ContactImportService
{
    private const MAX_BYTES = 8_000_000;
    private const MAX_CONTACTS = 5000;
    private const WARN_AGE_DAYS = 90;

    /** @var list<string> */
    private const FIELD_KEYS = [
        'salutation',
        'first_name',
        'last_name',
        'display_name',
        'company_name',
        'email',
        'email_2',
        'phone_1',
        'phone_2',
        'customer_number',
        'supplier_number',
        'tax_number',
        'vat_id',
        'contact_note',
        'website',
        'contact_role',
        'address1_extra',
        'address1_street',
        'address1_postal',
        'address1_city',
        'address1_country',
        'address2_extra',
        'address2_street',
        'address2_postal',
        'address2_city',
        'address2_country',
    ];

    public static function isImportAllowed(?User $user): bool
    {
        if ($user === null || !MenuRegistry::canAccess($user, 'kontakte')) {
            return false;
        }
        if (!Database::isConfigured()) {
            return false;
        }

        return ContactAccessResolver::canEditContact($user);
    }

    /**
     * @param array<string, mixed> $file $_FILES['…']
     * @return array{
     *   created: int,
     *   updated: int,
     *   skipped: int,
     *   errors: list<string>,
     *   warnings: list<string>,
     *   message: string
     * }
     */
    public static function importUpload(array $file, User $user, bool $overwrite, bool $confirmWarnings): array
    {
        if (!self::isImportAllowed($user)) {
            throw new RuntimeException('Kein Recht zum Kontakt-Import.');
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Bitte eine gültige JSON-Datei hochladen.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new InvalidArgumentException('Upload ungültig.');
        }
        if ($size < 2 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('Datei zu groß oder leer (max. 8 MB).');
        }

        $raw = file_get_contents($tmp);
        if ($raw === false || $raw === '') {
            throw new InvalidArgumentException('Datei konnte nicht gelesen werden.');
        }

        $parsed = self::parseAndValidate($raw);
        if ($parsed['warnings'] !== [] && !$confirmWarnings) {
            throw new InvalidArgumentException(
                'Warnungen bestätigen: ' . implode(' · ', $parsed['warnings'])
            );
        }

        return self::applyPackage($parsed['payload'], $user, $overwrite, $parsed['warnings']);
    }

    /**
     * @return array{payload: array<string, mixed>, warnings: list<string>}
     */
    public static function parseAndValidate(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Ungültiges JSON.');
        }
        if (($data['format'] ?? '') !== ContactExportService::FORMAT) {
            throw new InvalidArgumentException('format muss dg_contact_export sein.');
        }
        if ((int) ($data['v'] ?? 0) !== ContactExportService::VERSION) {
            throw new InvalidArgumentException('Unbekannte Schemaversion (erwartet v=1).');
        }
        $source = is_array($data['source'] ?? null) ? $data['source'] : null;
        if ($source === null) {
            throw new InvalidArgumentException('source fehlt.');
        }
        $domain = FirmSwitcherService::normalizeHost((string) ($source['domain'] ?? ''));
        if ($domain === '') {
            throw new InvalidArgumentException('source.domain fehlt oder ungültig.');
        }
        $source['domain'] = $domain;
        $source['firm_label'] = trim((string) ($source['firm_label'] ?? ''));
        if ($source['firm_label'] === '') {
            $source['firm_label'] = $domain;
        }
        $source['org_id'] = isset($source['org_id']) && $source['org_id'] !== null && $source['org_id'] !== ''
            ? (int) $source['org_id']
            : null;
        $source['share_contacts'] = !empty($source['share_contacts']);
        $data['source'] = $source;

        if (!is_array($data['contacts'] ?? null)) {
            throw new InvalidArgumentException('contacts muss ein Array sein.');
        }
        if (count($data['contacts']) > self::MAX_CONTACTS) {
            throw new InvalidArgumentException('Zu viele Kontakte (max. ' . self::MAX_CONTACTS . ').');
        }
        foreach ($data['contacts'] as $i => $row) {
            if (!is_array($row) || !is_array($row['fields'] ?? null)) {
                throw new InvalidArgumentException('Kontakt #' . ((int) $i + 1) . ': fields fehlt.');
            }
        }

        $warnings = [];
        if (empty($source['share_contacts'])) {
            $warnings[] = 'Quell-Paket ohne share_contacts-Flag';
        }
        $host = FirmSwitcherService::normalizeHost((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '' && $domain === $host) {
            $warnings[] = 'Selbst-Import (Quell-Domain = diese Instanz)';
        }
        $exportedAt = (string) ($data['exported_at'] ?? '');
        if ($exportedAt !== '') {
            $ts = strtotime($exportedAt);
            if ($ts !== false && $ts < time() - (self::WARN_AGE_DAYS * 86400)) {
                $warnings[] = 'Paket älter als ' . self::WARN_AGE_DAYS . ' Tage';
            }
        }

        return ['payload' => $data, 'warnings' => $warnings];
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $warnings
     * @return array{created: int, updated: int, skipped: int, errors: list<string>, warnings: list<string>, message: string}
     */
    private static function applyPackage(array $payload, User $user, bool $overwrite, array $warnings): array
    {
        $source = $payload['source'];
        $firmLabel = (string) $source['firm_label'];
        $domain = (string) $source['domain'];
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($payload['contacts'] as $i => $row) {
            $n = (int) $i + 1;
            try {
                $action = self::importOne(is_array($row) ? $row : [], $user, $overwrite, $firmLabel, $domain);
                if ($action === 'created') {
                    $created++;
                } elseif ($action === 'updated') {
                    $updated++;
                } else {
                    $skipped++;
                }
            } catch (Throwable $e) {
                $skipped++;
                $errors[] = '#' . $n . ': ' . $e->getMessage();
                if (count($errors) >= 25) {
                    $errors[] = '… weitere Fehler unterdrückt';
                    break;
                }
            }
        }

        $message = sprintf(
            'Import fertig: %d neu, %d aktualisiert, %d übersprungen.',
            $created,
            $updated,
            $skipped
        );

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'warnings' => $warnings,
            'message' => $message,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return 'created'|'updated'|'skipped'
     */
    private static function importOne(array $row, User $user, bool $overwrite, string $firmLabel, string $domain): string
    {
        $incoming = self::whitelistFields(is_array($row['fields'] ?? null) ? $row['fields'] : []);
        $match = is_array($row['match'] ?? null) ? $row['match'] : [];
        $email = strtolower(trim((string) ($match['email'] ?? $incoming['email'] ?? '')));
        $customerNumber = trim((string) ($match['customer_number'] ?? $incoming['customer_number'] ?? ''));
        $supplierNumber = trim((string) ($match['supplier_number'] ?? $incoming['supplier_number'] ?? ''));

        $existing = null;
        if ($email !== '') {
            $existing = ContactRepository::findByEmailMatch($email);
        }
        if ($existing === null && $customerNumber !== '') {
            $existing = ContactRepository::findByCustomerNumber($customerNumber);
        }
        if ($existing === null && $supplierNumber !== '') {
            $existing = ContactRepository::findBySupplierNumber($supplierNumber);
        }

        $hint = trim((string) ($row['origin_hint'] ?? ''));
        if ($hint === '') {
            $hint = 'Übernommen aus ' . $firmLabel . ' (' . $domain . ')';
        }
        if (function_exists('mb_substr')) {
            $hint = mb_substr($hint, 0, 191);
        } else {
            $hint = substr($hint, 0, 191);
        }

        $role = CrmRole::normalize($incoming['contact_role'] ?? 'dg_kunde');
        if (CrmRole::hasEmployeeProfile($role) && !ContactAccessResolver::canViewAllContactTypes($user)) {
            $role = 'dg_kunde';
        }
        $incoming['contact_role'] = $role;

        if ($existing !== null) {
            if (!ContactAccessResolver::canEditContact($user, $existing)) {
                throw new RuntimeException('Kein Bearbeitungsrecht für bestehenden Kontakt.');
            }
            $form = ContactRepository::toForm($existing);
            $merged = self::mergeForms($form, $incoming, $overwrite);
            $merged['login'] = $existing->login;
            $existingOrigin = trim((string) ($form['origin_firm_note'] ?? ''));
            $merged['origin_firm_note'] = $existingOrigin !== '' ? $existingOrigin : $hint;
            ContactRepository::save($merged, $existing->id);

            return 'updated';
        }

        $display = trim($incoming['display_name']);
        if ($display === '') {
            $display = trim($incoming['company_name']);
        }
        if ($display === '') {
            $display = trim($incoming['first_name'] . ' ' . $incoming['last_name']);
        }
        if ($display === '') {
            $display = $email !== '' ? $email : 'Import-Kontakt';
        }
        $incoming['display_name'] = $display;

        $loginBase = $email !== '' ? $email : $display;
        $login = InstallCsvHelper::uniqueLogin(
            $loginBase,
            static fn (string $candidate): bool => ContactRepository::loginExists($candidate)
        );
        $incoming['login'] = $login;
        $incoming['origin_firm_note'] = $hint;
        if ($incoming['address1_country'] === '') {
            $incoming['address1_country'] = 'DE';
        }
        if ($incoming['address2_country'] === '') {
            $incoming['address2_country'] = 'DE';
        }

        ContactRepository::save($incoming, null);

        return 'created';
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, string>
     */
    private static function whitelistFields(array $fields): array
    {
        $out = [];
        foreach (self::FIELD_KEYS as $key) {
            $out[$key] = trim((string) ($fields[$key] ?? ''));
        }

        return $out;
    }

    /**
     * @param array<string, string> $existing
     * @param array<string, string> $incoming
     * @return array<string, string>
     */
    private static function mergeForms(array $existing, array $incoming, bool $overwrite): array
    {
        foreach (self::FIELD_KEYS as $key) {
            $new = $incoming[$key] ?? '';
            if ($new === '') {
                continue;
            }
            $old = trim((string) ($existing[$key] ?? ''));
            if ($overwrite || $old === '') {
                $existing[$key] = $new;
            }
        }

        return $existing;
    }
}
