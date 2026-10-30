<?php
declare(strict_types=1);

/**
 * Lohn L3c: Stamm-Zuordnung Mitarbeiter ↔ Lohnart (ohne Monatslauf).
 */
final class PayrollEmployeeWageAssignmentRepository
{
    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
        PayrollWageTypeRepository::ensureReady();
    }

    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            return Database::pdo()->query("SHOW TABLES LIKE 'dg_payroll_employee_wage_assignments'")->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{id: int, contact_id: int, wage_type_id: int, amount: string, valid_from: string, valid_to: string, priority: int, notes: string, wage_code?: string, wage_label?: string, wage_unit?: string, wage_active?: int}
     */
    public static function emptyRow(): array
    {
        return [
            'id' => 0,
            'contact_id' => 0,
            'wage_type_id' => 0,
            'amount' => '',
            'valid_from' => '',
            'valid_to' => '',
            'priority' => 0,
            'notes' => '',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forContact(int $contactId): array
    {
        self::ensureReady();
        if ($contactId < 1 || !self::tableReady()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT a.*, w.code AS wage_code, w.label AS wage_label, w.unit AS wage_unit, w.is_active AS wage_active
             FROM dg_payroll_employee_wage_assignments a
             INNER JOIN dg_payroll_wage_types w ON w.id = a.wage_type_id
             WHERE a.contact_id = :cid
             ORDER BY a.priority ASC, a.id ASC'
        );
        $stmt->execute(['cid' => $contactId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = self::mapRow($row);
            }
        }

        return $out;
    }

    /**
     * Formularzeilen: bestehende + leere Slots zum Anlegen.
     *
     * @param list<array<string, mixed>> $existing
     * @return list<array<string, mixed>>
     */
    public static function formRows(array $existing, int $emptySlots = 2): array
    {
        $rows = $existing !== [] ? $existing : [];
        $emptySlots = max(1, min(5, $emptySlots));
        for ($i = 0; $i < $emptySlots; $i++) {
            $rows[] = self::emptyRow();
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array<string, mixed>>
     */
    public static function parseFromPost(array $data): array
    {
        $raw = $data['payroll_wage_assignments'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (!empty($item['delete'])) {
                continue;
            }
            $wageTypeId = (int) ($item['wage_type_id'] ?? 0);
            if ($wageTypeId < 1) {
                continue;
            }
            $amount = self::parseAmount($item['amount'] ?? '');
            if ($amount === null) {
                throw new InvalidArgumentException('Lohnart-Zuordnung: Betrag/Satz ungültig.');
            }
            $validFrom = self::parseDate($item['valid_from'] ?? '');
            $validTo = self::parseDate($item['valid_to'] ?? '');
            if ($validFrom !== null && $validTo !== null && $validFrom > $validTo) {
                throw new InvalidArgumentException('Lohnart-Zuordnung: „gültig bis“ liegt vor „gültig ab“.');
            }
            $out[] = [
                'id' => (int) ($item['id'] ?? 0),
                'wage_type_id' => $wageTypeId,
                'amount' => $amount,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'priority' => (int) ($item['priority'] ?? 0),
                'notes' => mb_substr(trim((string) ($item['notes'] ?? '')), 0, 500),
            ];
        }

        return $out;
    }

    /**
     * Ersetzt die Zuordnungen eines Mitarbeiters (nur bei Lohn-Schreibrecht aufrufen).
     *
     * @param list<array<string, mixed>> $rows aus parseFromPost
     */
    public static function syncForContact(int $contactId, array $rows): void
    {
        if ($contactId < 1) {
            return;
        }
        self::ensureReady();
        if (!self::tableReady()) {
            throw new RuntimeException('Zuordnungs-Tabelle fehlt (Migration 126).');
        }

        $pdo = Database::pdo();
        $existingStmt = $pdo->prepare(
            'SELECT id FROM dg_payroll_employee_wage_assignments WHERE contact_id = :cid'
        );
        $existingStmt->execute(['cid' => $contactId]);
        $existingIds = [];
        foreach ($existingStmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $eid) {
            $existingIds[(int) $eid] = true;
        }

        $keepIds = [];
        $insert = $pdo->prepare(
            'INSERT INTO dg_payroll_employee_wage_assignments
                (contact_id, wage_type_id, amount, valid_from, valid_to, priority, notes)
             VALUES
                (:contact_id, :wage_type_id, :amount, :valid_from, :valid_to, :priority, :notes)'
        );
        $update = $pdo->prepare(
            'UPDATE dg_payroll_employee_wage_assignments SET
                wage_type_id = :wage_type_id,
                amount = :amount,
                valid_from = :valid_from,
                valid_to = :valid_to,
                priority = :priority,
                notes = :notes
             WHERE id = :id AND contact_id = :contact_id'
        );

        foreach ($rows as $row) {
            $wageTypeId = (int) ($row['wage_type_id'] ?? 0);
            if ($wageTypeId < 1) {
                continue;
            }
            $wage = PayrollWageTypeRepository::find($wageTypeId);
            if ($wage === null) {
                throw new InvalidArgumentException('Unbekannte Lohnart in Zuordnung.');
            }
            if ((int) ($wage['is_active'] ?? 0) !== 1) {
                // Bestehende Zuordnung auf inaktive Art bleibt editierbar; neue nur auf aktive
                $rowId = (int) ($row['id'] ?? 0);
                if ($rowId < 1 || !isset($existingIds[$rowId])) {
                    throw new InvalidArgumentException(
                        'Lohnart „' . (string) ($wage['label'] ?? '') . '“ ist deaktiviert.'
                    );
                }
            }

            $payload = [
                'contact_id' => $contactId,
                'wage_type_id' => $wageTypeId,
                'amount' => (string) ($row['amount'] ?? '0'),
                'valid_from' => $row['valid_from'] ?? null,
                'valid_to' => $row['valid_to'] ?? null,
                'priority' => (int) ($row['priority'] ?? 0),
                'notes' => (string) ($row['notes'] ?? ''),
            ];

            $rowId = (int) ($row['id'] ?? 0);
            if ($rowId > 0 && isset($existingIds[$rowId])) {
                $payload['id'] = $rowId;
                $update->execute($payload);
                $keepIds[$rowId] = true;
            } else {
                $insert->execute($payload);
            }
        }

        foreach (array_keys($existingIds) as $oldId) {
            if (!isset($keepIds[$oldId])) {
                $del = $pdo->prepare(
                    'DELETE FROM dg_payroll_employee_wage_assignments WHERE id = :id AND contact_id = :cid'
                );
                $del->execute(['id' => $oldId, 'cid' => $contactId]);
            }
        }
    }

    public static function parseAmount(mixed $raw): ?string
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return '0.0000';
        }
        $s = str_replace([' ', "\xc2\xa0"], '', $s);
        if (str_contains($s, ',') && str_contains($s, '.')) {
            // 1.234,56
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);
        }
        if (!preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            return null;
        }
        $f = (float) $s;
        if (!is_finite($f) || abs($f) > 99999999.9999) {
            return null;
        }

        return number_format($f, 4, '.', '');
    }

    public static function parseDate(mixed $raw): ?string
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            throw new InvalidArgumentException('Lohnart-Zuordnung: Datum bitte als JJJJ-MM-TT.');
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $s);
        if ($dt === false || $dt->format('Y-m-d') !== $s) {
            throw new InvalidArgumentException('Lohnart-Zuordnung: ungültiges Datum.');
        }

        return $s;
    }

    public static function formatAmountDisplay(string $amount): string
    {
        $f = (float) $amount;
        if (abs($f - round($f, 2)) < 0.00005) {
            return number_format($f, 2, ',', '.');
        }

        return rtrim(rtrim(number_format($f, 4, ',', '.'), '0'), ',');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function mapRow(array $row): array
    {
        $amount = (string) ($row['amount'] ?? '0');
        $validFrom = $row['valid_from'] ?? null;
        $validTo = $row['valid_to'] ?? null;

        return [
            'id' => (int) ($row['id'] ?? 0),
            'contact_id' => (int) ($row['contact_id'] ?? 0),
            'wage_type_id' => (int) ($row['wage_type_id'] ?? 0),
            'amount' => $amount,
            'amount_display' => self::formatAmountDisplay($amount),
            'valid_from' => is_string($validFrom) ? $validFrom : '',
            'valid_to' => is_string($validTo) ? $validTo : '',
            'priority' => (int) ($row['priority'] ?? 0),
            'notes' => (string) ($row['notes'] ?? ''),
            'wage_code' => (string) ($row['wage_code'] ?? ''),
            'wage_label' => (string) ($row['wage_label'] ?? ''),
            'wage_unit' => (string) ($row['wage_unit'] ?? ''),
            'wage_active' => (int) ($row['wage_active'] ?? 0),
        ];
    }
}
