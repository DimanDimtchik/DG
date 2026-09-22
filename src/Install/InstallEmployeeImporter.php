<?php
declare(strict_types=1);

/**
 * CSV-Import für Kalender-Mitarbeiter während der Installation.
 * Legt zusätzlich einen Mitarbeiter-Kontakt an (contact_id Pflicht ab 095).
 */
final class InstallEmployeeImporter
{
    private const BATCH_SIZE = 25;

    /**
     * @return array{done: bool, progress: int, imported: int, skipped: int, errors: list<string>, message: string, next_offset?: int}
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
            'name' => ['name', 'mitarbeiter', 'display_name', 'anzeigename'],
            'area' => ['bereich', 'area', 'abteilung'],
            'email' => ['email', 'e_mail', 'mail'],
            'active' => ['aktiv', 'active', 'is_active'],
            'first_name' => ['vorname', 'first_name'],
            'last_name' => ['nachname', 'last_name'],
            'login' => ['login', 'benutzername', 'username', 'personalnummer'],
        ], InstallImportSourcePresets::employeeAliases($source)));

        $defaultAreaId = self::ensureDefaultAreaId();
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
                $name = trim($raw['name'] ?? '');
                $firstName = trim($raw['first_name'] ?? '');
                $lastName = trim($raw['last_name'] ?? '');
                if ($name === '' && ($firstName !== '' || $lastName !== '')) {
                    $name = trim($firstName . ' ' . $lastName);
                }
                if ($name === '') {
                    throw new InvalidArgumentException('Name fehlt.');
                }
                if ($firstName === '' && $lastName === '') {
                    $parts = preg_split('/\s+/', $name, 2) ?: [];
                    $firstName = trim((string) ($parts[0] ?? $name));
                    $lastName = trim((string) ($parts[1] ?? ''));
                }

                $areaName = trim($raw['area'] ?? '');
                $areaId = $areaName !== '' ? self::resolveAreaId($areaName) : $defaultAreaId;
                $isActive = self::parseBool($raw['active'] ?? 'ja', true);
                $email = trim($raw['email'] ?? '');

                $loginBase = trim($raw['login'] ?? '');
                if ($loginBase === '') {
                    $loginBase = $email !== '' ? $email : $name;
                }
                $login = InstallCsvHelper::uniqueLogin(
                    $loginBase,
                    static fn (string $candidate): bool => ContactRepository::loginExists($candidate)
                );

                $contactId = ContactRepository::save([
                    'salutation' => '',
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'display_name' => $name,
                    'company_name' => '',
                    'email' => $email,
                    'contact_role' => 'mitarbeiter',
                    'login' => $login,
                    'address1_country' => 'DE',
                ]);

                CalendarStaffRepository::saveEmployee([
                    'name' => $name,
                    'contact_id' => $contactId,
                    'user_id' => 0,
                    'supervisor_id' => 0,
                    'sort_order' => 0,
                    'is_active' => $isActive,
                    'area_ids' => [$areaId],
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
                ? sprintf('%d Mitarbeiter (Kontakt + Kalender) importiert.', $imported)
                : sprintf('Mitarbeiter werden importiert … (%d%%)', $progress),
            'next_offset' => $offset,
        ];
    }

    private static function ensureDefaultAreaId(): int
    {
        $areas = CalendarStaffRepository::getAreas(true);
        if ($areas !== []) {
            return (int) $areas[0]['id'];
        }

        CalendarStaffRepository::saveArea(['name' => 'Standard', 'sort_order' => 0, 'is_active' => 1]);
        $areas = CalendarStaffRepository::getAreas(true);

        return $areas !== [] ? (int) $areas[0]['id'] : 0;
    }

    private static function resolveAreaId(string $name): int
    {
        foreach (CalendarStaffRepository::getAreas() as $area) {
            if (strcasecmp((string) $area['name'], $name) === 0) {
                return (int) $area['id'];
            }
        }

        CalendarStaffRepository::saveArea(['name' => $name, 'sort_order' => 0, 'is_active' => 1]);
        foreach (CalendarStaffRepository::getAreas() as $area) {
            if (strcasecmp((string) $area['name'], $name) === 0) {
                return (int) $area['id'];
            }
        }

        return self::ensureDefaultAreaId();
    }

    private static function parseBool(string $raw, bool $default): bool
    {
        $v = mb_strtolower(trim($raw));
        if ($v === '') {
            return $default;
        }
        if (in_array($v, ['1', 'ja', 'yes', 'true', 'aktiv', 'active'], true)) {
            return true;
        }
        if (in_array($v, ['0', 'nein', 'no', 'false', 'inaktiv', 'inactive'], true)) {
            return false;
        }

        return $default;
    }
}
