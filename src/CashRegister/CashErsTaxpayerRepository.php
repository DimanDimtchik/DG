<?php
declare(strict_types=1);

/**
 * Steuerpflichtigen-Stammdaten für §146a-Kassenmeldung (K19a) — Speichern ohne Senden.
 */
final class CashErsTaxpayerRepository
{
    private const TABLE = 'dg_cash_ers_taxpayer';

    public static function tableExists(): bool
    {
        return CashRegisterTable::tableExists(self::TABLE);
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        if (!self::tableExists()) {
            return self::emptyRow();
        }
        $stmt = Database::pdo()->query('SELECT * FROM dg_cash_ers_taxpayer ORDER BY id ASC LIMIT 1');
        $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if ($row === false || !is_array($row)) {
            return self::emptyRow();
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function save(array $data, ?int $userId = null): void
    {
        if (!self::tableExists()) {
            throw new RuntimeException('Tabelle dg_cash_ers_taxpayer fehlt.');
        }
        $taxNumber = preg_replace('/\D+/', '', (string) ($data['tax_number'] ?? '')) ?? '';
        $taxOffice = preg_replace('/\D+/', '', (string) ($data['tax_office_number'] ?? '')) ?? '';
        if (strlen($taxOffice) > 4) {
            $taxOffice = substr($taxOffice, 0, 4);
        }
        if ($taxOffice === '' && strlen($taxNumber) >= 4) {
            $taxOffice = substr($taxNumber, 0, 4);
        }
        if ($taxNumber !== '' && strlen($taxNumber) !== 13) {
            throw new InvalidArgumentException(
                'Steuernummer für die Kassenmeldung: 13 Ziffern im Bundesschema (oder leer lassen).'
            );
        }
        if ($taxOffice !== '' && strlen($taxOffice) !== 4) {
            throw new InvalidArgumentException('Bundesfinanzamt-Nummer: genau 4 Ziffern (oder leer).');
        }

        $fields = [
            'tax_number' => $taxNumber,
            'tax_office_number' => $taxOffice,
            'notes' => mb_substr(trim((string) ($data['notes'] ?? '')), 0, 500),
            'updated_by' => $userId !== null && $userId > 0 ? $userId : null,
        ];

        $existing = self::get();
        $id = (int) ($existing['id'] ?? 0);
        if ($id > 0) {
            CashRegisterTable::update(self::TABLE, $id, $fields);
        } else {
            CashRegisterTable::insert(self::TABLE, $fields);
        }

        if (CashAuditLogRepository::tableExists()) {
            CashAuditLogRepository::insert([
                'event_type' => 'ers.taxpayer_save',
                'user_id' => $userId,
                'message' => 'Kassenmeldung: Steuerpflichtigen-Daten gespeichert (ohne Senden)',
                'details_json' => json_encode([
                    'tax_number_set' => $taxNumber !== '',
                    'tax_office_set' => $taxOffice !== '',
                ], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyRow(): array
    {
        return [
            'id' => 0,
            'tax_number' => '',
            'tax_office_number' => '',
            'notes' => '',
            'updated_by' => null,
        ];
    }
}
