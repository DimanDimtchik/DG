<?php
declare(strict_types=1);

/** CSV-Import für Kontakte während der Installation. */
final class InstallContactImporter
{
    private const BATCH_SIZE = 40;

    /**
     * @return array{done: bool, progress: int, imported: int, skipped: int, errors: list<string>, message: string}
     */
    public static function importBatch(string $path, int $offset, string $source = 'other'): array
    {
        $rows = InstallCsvHelper::readRows($path);
        if (count($rows) < 2) {
            return [
                'done' => true,
                'progress' => 100,
                'imported' => 0,
                'skipped' => 0,
                'errors' => ['Die Datei enthält keine Datenzeilen.'],
                'message' => 'Keine Datenzeilen gefunden.',
            ];
        }

        $map = InstallCsvHelper::mapColumns($rows[0], InstallCsvHelper::mergeAliases([
            'salutation' => ['anrede', 'salutation'],
            'first_name' => ['vorname', 'first_name'],
            'last_name' => ['nachname', 'last_name'],
            'company_name' => ['firma', 'firmenname', 'company', 'company_name'],
            'display_name' => ['anzeigename', 'display_name', 'name'],
            'email' => ['email', 'e_mail', 'mail'],
            'phone_1' => ['telefon', 'phone', 'phone_1', 'tel'],
            'customer_number' => ['kundennummer', 'customer_number', 'kdnr'],
            'supplier_number' => ['lieferantennummer', 'supplier_number', 'liefnr'],
            'tax_number' => ['steuernummer', 'tax_number'],
            'vat_id' => ['ust_idnr', 'ust_id', 'vat_id', 'ustid'],
            'street' => ['strasse', 'straße', 'street', 'address1_street'],
            'postal' => ['plz', 'postal', 'postleitzahl'],
            'city' => ['ort', 'stadt', 'city'],
            'country' => ['land', 'country'],
            'contact_role' => ['rolle', 'contact_role', 'typ'],
            'login' => ['login', 'benutzername', 'username'],
        ], InstallImportSourcePresets::contactAliases($source)));

        $dataRows = count($rows) - 1;
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $processed = 0;
        $start = max(1, $offset + 1);

        for ($i = $start, $count = count($rows); $i < $count && $processed < self::BATCH_SIZE; $i++) {
            $line = $rows[$i];
            if (InstallCsvHelper::isEmptyRow($line)) {
                continue;
            }

            $raw = InstallCsvHelper::rowFromMap($map, $line);
            try {
                $salutation = $raw['salutation'] ?? '';
                $companyName = $raw['company_name'] ?? '';
                if ($salutation === '' && $companyName !== '') {
                    $salutation = 'Firma';
                }

                $displayName = $raw['display_name'] ?? '';
                $firstName = $raw['first_name'] ?? '';
                $lastName = $raw['last_name'] ?? '';
                if ($displayName === '') {
                    $displayName = $salutation === 'Firma' && $companyName !== ''
                        ? $companyName
                        : trim($firstName . ' ' . $lastName);
                }
                if ($displayName === '') {
                    throw new InvalidArgumentException('Name fehlt.');
                }

                $loginBase = $raw['login'] ?? '';
                if ($loginBase === '') {
                    $loginBase = $raw['email'] ?? $displayName;
                }
                $login = InstallCsvHelper::uniqueLogin(
                    $loginBase,
                    static fn (string $candidate): bool => ContactRepository::loginExists($candidate)
                );

                ContactRepository::save([
                    'salutation' => $salutation,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'display_name' => $displayName,
                    'company_name' => $companyName,
                    'email' => $raw['email'] ?? '',
                    'phone_1' => $raw['phone_1'] ?? '',
                    'customer_number' => $raw['customer_number'] ?? '',
                    'supplier_number' => $raw['supplier_number'] ?? '',
                    'tax_number' => $raw['tax_number'] ?? '',
                    'vat_id' => $raw['vat_id'] ?? '',
                    'address1_street' => $raw['street'] ?? '',
                    'address1_postal' => $raw['postal'] ?? '',
                    'address1_city' => $raw['city'] ?? '',
                    'address1_country' => ($raw['country'] ?? '') !== '' ? $raw['country'] : 'DE',
                    'contact_role' => $raw['contact_role'] ?? 'kunde',
                    'login' => $login,
                ]);
                $imported++;
            } catch (Throwable $e) {
                $errors[] = 'Zeile ' . ($i + 1) . ': ' . $e->getMessage();
                $skipped++;
            }

            $processed++;
            $offset = $i;
        }

        $done = $offset >= $count - 1;
        $progress = $dataRows > 0 ? (int) min(100, round(($offset / $dataRows) * 100)) : 100;

        return [
            'done' => $done,
            'progress' => $progress,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => $done
                ? sprintf('%d Kontakte importiert.', $imported)
                : sprintf('Kontakte werden importiert … (%d%%)', $progress),
            'next_offset' => $offset,
        ];
    }

    public static function templateCsv(): string
    {
        return InstallCsvHelper::templateCsv(
            ['Anrede', 'Vorname', 'Nachname', 'Firma', 'E-Mail', 'Telefon', 'Kundennummer', 'Straße', 'PLZ', 'Ort', 'Rolle'],
            [
                'Anrede' => 'Frau',
                'Vorname' => 'Anna',
                'Nachname' => 'Beispiel',
                'Firma' => '',
                'E-Mail' => 'anna@beispiel.de',
                'Telefon' => '+49 221 123456',
                'Kundennummer' => 'K-10001',
                'Straße' => 'Musterweg 1',
                'PLZ' => '50667',
                'Ort' => 'Köln',
                'Rolle' => 'kunde',
            ]
        );
    }
}
