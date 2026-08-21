<?php
declare(strict_types=1);

/** CSV-Import für Termine während der Installation. */
final class InstallBookingImporter
{
    private const BATCH_SIZE = 50;

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
            'slot_datetime' => ['datum', 'datum_zeit', 'termin', 'slot', 'slot_datetime', 'start'],
            'customer_name' => ['kunde', 'kundenname', 'name', 'customer_name'],
            'customer_email' => ['email', 'e_mail', 'customer_email'],
            'customer_phone' => ['telefon', 'phone', 'customer_phone', 'tel'],
            'status' => ['status'],
            'admin_notes' => ['notiz', 'notizen', 'admin_notes', 'bemerkung'],
            'employee_name' => ['mitarbeiter', 'employee', 'employee_name'],
            'article_title' => ['leistung', 'artikel', 'article', 'article_title'],
        ], InstallImportSourcePresets::bookingAliases($source)));

        $dataRows = count($rows) - 1;
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $processed = 0;
        $start = max(1, $offset + 1);
        $pdo = Database::pdo();

        for ($i = $start, $count = count($rows); $i < $count && $processed < self::BATCH_SIZE; $i++) {
            $line = $rows[$i];
            if (InstallCsvHelper::isEmptyRow($line)) {
                continue;
            }

            $raw = InstallCsvHelper::rowFromMap($map, $line);
            try {
                $slot = self::parseDateTime($raw['slot_datetime'] ?? '');
                $customerName = trim($raw['customer_name'] ?? '');
                if ($customerName === '') {
                    throw new InvalidArgumentException('Kundenname fehlt.');
                }

                $employeeId = self::resolveEmployeeId($raw['employee_name'] ?? '');
                $articleId = self::resolveArticleId($raw['article_title'] ?? '');
                $status = trim($raw['status'] ?? '') !== '' ? trim($raw['status']) : 'gebucht';

                $stmt = $pdo->prepare(
                    'INSERT INTO dg_bookings (article_id, employee_id, slot_datetime, customer_name, customer_email, customer_phone, status, admin_notes)
                     VALUES (:article_id, :employee_id, :slot, :name, :email, :phone, :status, :notes)'
                );
                $stmt->execute([
                    'article_id' => $articleId,
                    'employee_id' => $employeeId,
                    'slot' => $slot,
                    'name' => $customerName,
                    'email' => $raw['customer_email'] ?? '',
                    'phone' => $raw['customer_phone'] ?? '',
                    'status' => $status,
                    'notes' => $raw['admin_notes'] ?? '',
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
                ? sprintf('%d Termine importiert.', $imported)
                : sprintf('Termine werden importiert … (%d%%)', $progress),
            'next_offset' => $offset,
        ];
    }

    private static function parseDateTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException('Datum/Zeit fehlt.');
        }

        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'd.m.Y H:i', 'd.m.Y H:i:s', 'd.m.Y'];
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $value);
            if ($dt instanceof DateTime) {
                return $dt->format('Y-m-d H:i:s');
            }
        }

        $ts = strtotime($value);
        if ($ts === false) {
            throw new InvalidArgumentException('Ungültiges Datum: ' . $value);
        }

        return date('Y-m-d H:i:s', $ts);
    }

    private static function resolveEmployeeId(string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }

        foreach (CalendarStaffRepository::getEmployees() as $employee) {
            if (strcasecmp((string) $employee['name'], $name) === 0) {
                return (int) $employee['id'];
            }
        }

        return 0;
    }

    private static function resolveArticleId(string $title): int
    {
        $title = trim($title);
        if ($title === '') {
            return 0;
        }

        foreach (CalendarArticleRepository::all() as $article) {
            if (strcasecmp((string) ($article['title'] ?? ''), $title) === 0) {
                return (int) $article['id'];
            }
        }

        return 0;
    }

    public static function templateCsv(): string
    {
        return InstallCsvHelper::templateCsv(
            ['Datum/Zeit', 'Kunde', 'E-Mail', 'Telefon', 'Status', 'Mitarbeiter', 'Leistung', 'Notiz'],
            [
                'Datum/Zeit' => '2026-09-15 10:00',
                'Kunde' => 'Anna Beispiel',
                'E-Mail' => 'anna@beispiel.de',
                'Telefon' => '+49 221 123456',
                'Status' => 'gebucht',
                'Mitarbeiter' => 'Max Mustermann',
                'Leistung' => 'Beratung',
                'Notiz' => '',
            ]
        );
    }
}
