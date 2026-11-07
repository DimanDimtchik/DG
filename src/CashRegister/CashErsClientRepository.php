<?php
declare(strict_types=1);

/**
 * ERS-/Kassen-Meldefelder je Register (§146a Abs. 4 AO) — K19a, ohne Übermittlung.
 */
final class CashErsClientRepository
{
    private const TABLE = 'dg_cash_ers_clients';

    public static function tableExists(): bool
    {
        return CashRegisterTable::tableExists(self::TABLE);
    }

    /**
     * Kassensystem-Typen laut Meldeweg (Kurzliste; Detail Spec K19b).
     *
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            '1' => 'Elektronische Registrierkasse / Kassensystem',
            '2' => 'Taxameter',
            '3' => 'Wegstreckenzähler',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByRegisterId(int $registerId): ?array
    {
        if ($registerId <= 0 || !self::tableExists()) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_cash_ers_clients WHERE register_id = :rid LIMIT 1'
        );
        $stmt->execute(['rid' => $registerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>
     */
    public static function formDefaults(int $registerId): array
    {
        $existing = self::findByRegisterId($registerId);
        if ($existing !== null) {
            return $existing;
        }

        return [
            'id' => 0,
            'register_id' => $registerId,
            'ers_type' => '1',
            'manufacturer' => '',
            'model' => '',
            'serial_number' => '',
            'software' => 'DG CRM Registerkasse',
            'software_version' => '',
            'date_acquisition' => null,
            'date_commissioning' => null,
            'date_tss_commissioning' => null,
            'date_decommissioning' => null,
            'decommissioning_reason' => '',
            'remarks' => '',
            'data_status' => 'draft',
            'notes' => '',
        ];
    }

    /**
     * Übersicht: Kassen + Meldezeile (falls vorhanden).
     *
     * @return list<array<string, mixed>>
     */
    public static function listWithRegisters(?int $locationId = null): array
    {
        if (!CashRegisterRepository::tableExists()) {
            return [];
        }
        $registers = CashRegisterRepository::listAll($locationId, false);
        $out = [];
        foreach ($registers as $reg) {
            $rid = (int) ($reg['id'] ?? 0);
            $ers = $rid > 0 ? self::findByRegisterId($rid) : null;
            $out[] = array_merge($reg, [
                'ers_id' => (int) ($ers['id'] ?? 0),
                'ers_type' => (string) ($ers['ers_type'] ?? ''),
                'ers_manufacturer' => (string) ($ers['manufacturer'] ?? ''),
                'ers_model' => (string) ($ers['model'] ?? ''),
                'ers_serial_number' => (string) ($ers['serial_number'] ?? ''),
                'ers_data_status' => (string) ($ers['data_status'] ?? ''),
                'ers_date_commissioning' => $ers['date_commissioning'] ?? null,
                'ers_has_row' => $ers !== null,
                'ers_complete' => $ers !== null && self::isCoreComplete($ers),
            ]);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function upsertForRegister(array $data, ?int $userId = null): int
    {
        if (!self::tableExists()) {
            throw new RuntimeException('Tabelle dg_cash_ers_clients fehlt.');
        }
        $registerId = (int) ($data['register_id'] ?? 0);
        if ($registerId <= 0 || CashRegisterRepository::findById($registerId) === null) {
            throw new InvalidArgumentException('Kasse nicht gefunden.');
        }

        $ersType = trim((string) ($data['ers_type'] ?? '1'));
        if (!isset(self::typeLabels()[$ersType])) {
            throw new InvalidArgumentException('Unbekannter Kassensystem-Typ.');
        }

        $fields = [
            'register_id' => $registerId,
            'ers_type' => $ersType,
            'manufacturer' => mb_substr(trim((string) ($data['manufacturer'] ?? '')), 0, 120),
            'model' => mb_substr(trim((string) ($data['model'] ?? '')), 0, 120),
            'serial_number' => mb_substr(trim((string) ($data['serial_number'] ?? '')), 0, 120),
            'software' => mb_substr(trim((string) ($data['software'] ?? '')), 0, 120),
            'software_version' => mb_substr(trim((string) ($data['software_version'] ?? '')), 0, 64),
            'date_acquisition' => self::sanitizeDate($data['date_acquisition'] ?? null),
            'date_commissioning' => self::sanitizeDate($data['date_commissioning'] ?? null),
            'date_tss_commissioning' => self::sanitizeDate($data['date_tss_commissioning'] ?? null),
            'date_decommissioning' => self::sanitizeDate($data['date_decommissioning'] ?? null),
            'decommissioning_reason' => mb_substr(trim((string) ($data['decommissioning_reason'] ?? '')), 0, 255),
            'remarks' => mb_substr(trim((string) ($data['remarks'] ?? '')), 0, 500),
            'data_status' => 'draft',
            'notes' => mb_substr(trim((string) ($data['notes'] ?? '')), 0, 500),
            'updated_by' => $userId !== null && $userId > 0 ? $userId : null,
        ];

        $existing = self::findByRegisterId($registerId);
        if ($existing !== null) {
            $id = (int) ($existing['id'] ?? 0);
            unset($fields['register_id']);
            CashRegisterTable::update(self::TABLE, $id, $fields);
        } else {
            $id = CashRegisterTable::insert(self::TABLE, $fields);
        }

        if (CashAuditLogRepository::tableExists()) {
            CashAuditLogRepository::insert([
                'event_type' => 'ers.client_save',
                'register_id' => $registerId,
                'user_id' => $userId,
                'message' => 'Kassenmeldung: Meldefelder gespeichert (ohne Senden)',
                'details_json' => json_encode([
                    'ers_id' => $id,
                    'ers_type' => $ersType,
                    'core_complete' => self::isCoreComplete(array_merge($fields, ['register_id' => $registerId])),
                ], JSON_UNESCAPED_UNICODE),
            ]);
        }

        return $id;
    }

    /**
     * Kernfelder für spätere Meldung — nur Hinweis, kein Blockieren beim Speichern.
     *
     * @param array<string, mixed> $row
     */
    public static function isCoreComplete(array $row): bool
    {
        $need = [
            trim((string) ($row['manufacturer'] ?? '')),
            trim((string) ($row['model'] ?? '')),
            trim((string) ($row['serial_number'] ?? '')),
            trim((string) ($row['software'] ?? '')),
        ];
        foreach ($need as $v) {
            if ($v === '') {
                return false;
            }
        }
        $acq = self::sanitizeDate($row['date_acquisition'] ?? null);
        $com = self::sanitizeDate($row['date_commissioning'] ?? null);

        return $acq !== null && $com !== null;
    }

    private static function sanitizeDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
