<?php
declare(strict_types=1);

/**
 * Lohn L3b: CRUD + Seed für dg_payroll_wage_types.
 */
final class PayrollWageTypeRepository
{
    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
        self::seedDefaultsIfEmpty();
    }

    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            return Database::pdo()->query("SHOW TABLES LIKE 'dg_payroll_wage_types'")->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function seedCatalog(): array
    {
        return [
            ['code' => 'SALARY_MONTH', 'label' => 'Festgehalt / Monatslohn', 'category' => 'salary', 'unit' => 'month', 'direction' => 'earning', 'taxable' => 1, 'subject_to_sv' => 1, 'flat_tax_possible' => 0, 'increases_net' => 1, 'sort_order' => 10],
            ['code' => 'WAGE_HOUR', 'label' => 'Stundenlohn', 'category' => 'hourly', 'unit' => 'hour', 'direction' => 'earning', 'taxable' => 1, 'subject_to_sv' => 1, 'flat_tax_possible' => 0, 'increases_net' => 1, 'sort_order' => 20],
            ['code' => 'OVERTIME_PAY', 'label' => 'Überstundenvergütung', 'category' => 'hourly', 'unit' => 'hour', 'direction' => 'earning', 'taxable' => 1, 'subject_to_sv' => 1, 'flat_tax_possible' => 0, 'increases_net' => 1, 'sort_order' => 25],
            ['code' => 'BONUS', 'label' => 'Prämie / Zulage', 'category' => 'allowance', 'unit' => 'amount', 'direction' => 'earning', 'taxable' => 1, 'subject_to_sv' => 1, 'flat_tax_possible' => 0, 'increases_net' => 1, 'sort_order' => 30],
            ['code' => 'BENEFIT_IN_KIND', 'label' => 'Sachbezug', 'category' => 'benefit', 'unit' => 'amount', 'direction' => 'earning', 'taxable' => 1, 'subject_to_sv' => 1, 'flat_tax_possible' => 1, 'increases_net' => 1, 'sort_order' => 40],
            ['code' => 'FRINGE_BENEFIT', 'label' => 'Geldwerter Vorteil', 'category' => 'benefit', 'unit' => 'amount', 'direction' => 'earning', 'taxable' => 1, 'subject_to_sv' => 1, 'flat_tax_possible' => 1, 'increases_net' => 1, 'sort_order' => 45],
            ['code' => 'TAX_FREE_REFUND', 'label' => 'Steuerfreie Erstattung', 'category' => 'tax_free', 'unit' => 'amount', 'direction' => 'earning', 'taxable' => 0, 'subject_to_sv' => 0, 'flat_tax_possible' => 0, 'increases_net' => 1, 'sort_order' => 50],
            ['code' => 'EMPLOYER_ALLOWANCE', 'label' => 'Zuschuss Arbeitgeber', 'category' => 'allowance', 'unit' => 'amount', 'direction' => 'earning', 'taxable' => 1, 'subject_to_sv' => 1, 'flat_tax_possible' => 0, 'increases_net' => 1, 'sort_order' => 55],
            ['code' => 'NET_DEDUCTION', 'label' => 'Nettolohnabzug', 'category' => 'deduction', 'unit' => 'amount', 'direction' => 'deduction', 'taxable' => 0, 'subject_to_sv' => 0, 'flat_tax_possible' => 0, 'increases_net' => 0, 'sort_order' => 80],
            ['code' => 'ADVANCE_RECOVERY', 'label' => 'Vorschuss-Verrechnung', 'category' => 'deduction', 'unit' => 'amount', 'direction' => 'deduction', 'taxable' => 0, 'subject_to_sv' => 0, 'flat_tax_possible' => 0, 'increases_net' => 0, 'sort_order' => 85],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        return [
            'salary' => 'Festgehalt',
            'hourly' => 'Stundenlohn',
            'benefit' => 'Sachbezug / GwV',
            'allowance' => 'Zuschuss / Zulage',
            'tax_free' => 'Steuerfreie Erstattung',
            'deduction' => 'Abzug',
            'other' => 'Sonstiges',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function unitLabels(): array
    {
        return [
            'month' => 'Monat',
            'hour' => 'Stunde',
            'amount' => 'Betrag',
            'percent' => 'Prozent',
        ];
    }

    public static function seedDefaultsIfEmpty(): void
    {
        if (!self::tableReady()) {
            return;
        }
        $pdo = Database::pdo();
        $count = (int) $pdo->query('SELECT COUNT(*) FROM dg_payroll_wage_types')->fetchColumn();
        if ($count > 0) {
            return;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO dg_payroll_wage_types
                (code, label, category, unit, direction, taxable, subject_to_sv, flat_tax_possible, increases_net,
                 skr03_expense_hint, skr04_expense_hint, skr03_liability_hint, skr04_liability_hint,
                 sort_order, is_system, is_active, notes)
             VALUES
                (:code, :label, :category, :unit, :direction, :taxable, :subject_to_sv, :flat_tax_possible, :increases_net,
                 :skr03_expense_hint, :skr04_expense_hint, :skr03_liability_hint, :skr04_liability_hint,
                 :sort_order, 1, 1, :notes)'
        );
        foreach (self::seedCatalog() as $row) {
            $stmt->execute([
                'code' => $row['code'],
                'label' => $row['label'],
                'category' => $row['category'],
                'unit' => $row['unit'],
                'direction' => $row['direction'],
                'taxable' => (int) $row['taxable'],
                'subject_to_sv' => (int) $row['subject_to_sv'],
                'flat_tax_possible' => (int) $row['flat_tax_possible'],
                'increases_net' => (int) $row['increases_net'],
                'skr03_expense_hint' => '',
                'skr04_expense_hint' => '',
                'skr03_liability_hint' => '',
                'skr04_liability_hint' => '',
                'sort_order' => (int) $row['sort_order'],
                'notes' => 'System-Beispiel — Kontenvorschläge mit Steuerberater pflegen',
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(bool $activeOnly = false): array
    {
        self::ensureReady();
        if (!self::tableReady()) {
            return [];
        }
        $sql = 'SELECT * FROM dg_payroll_wage_types';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $rows = Database::pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        self::ensureReady();
        if ($id < 1 || !self::tableReady()) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_payroll_wage_types WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function save(array $input, ?int $id = null): int
    {
        self::ensureReady();
        if (!self::tableReady()) {
            throw new RuntimeException('Lohnarten-Tabelle fehlt (Migration 125).');
        }

        $existing = $id !== null && $id > 0 ? self::find($id) : null;
        $isSystem = $existing !== null && (int) ($existing['is_system'] ?? 0) === 1;

        $code = strtoupper(preg_replace('/[^A-Z0-9_]/', '', (string) ($input['code'] ?? '')) ?? '');
        if ($code === '') {
            throw new InvalidArgumentException('Code ist erforderlich.');
        }
        if (strlen($code) > 32) {
            throw new InvalidArgumentException('Code max. 32 Zeichen.');
        }
        if ($isSystem) {
            $code = (string) $existing['code'];
        }

        $label = trim((string) ($input['label'] ?? ''));
        if ($label === '') {
            throw new InvalidArgumentException('Bezeichnung ist erforderlich.');
        }

        $categories = array_keys(self::categoryLabels());
        $category = (string) ($input['category'] ?? 'other');
        if (!in_array($category, $categories, true)) {
            $category = 'other';
        }

        $units = array_keys(self::unitLabels());
        $unit = (string) ($input['unit'] ?? 'amount');
        if (!in_array($unit, $units, true)) {
            $unit = 'amount';
        }

        $direction = (string) ($input['direction'] ?? 'earning');
        if (!in_array($direction, ['earning', 'deduction'], true)) {
            $direction = 'earning';
        }

        $taxable = !empty($input['taxable']) ? 1 : 0;
        $subjectToSv = !empty($input['subject_to_sv']) ? 1 : 0;
        $flatTax = !empty($input['flat_tax_possible']) ? 1 : 0;
        $increasesNet = array_key_exists('increases_net', $input)
            ? (!empty($input['increases_net']) ? 1 : 0)
            : ($direction === 'earning' ? 1 : 0);
        $isActive = !empty($input['is_active']) ? 1 : 0;

        $hint = static function (mixed $v): string {
            return mb_substr(preg_replace('/[^0-9A-Za-z]/', '', (string) $v) ?? '', 0, 16);
        };

        $payload = [
            'code' => $code,
            'label' => mb_substr($label, 0, 120),
            'category' => $category,
            'unit' => $unit,
            'direction' => $direction,
            'taxable' => $taxable,
            'subject_to_sv' => $subjectToSv,
            'flat_tax_possible' => $flatTax,
            'increases_net' => $increasesNet,
            'skr03_expense_hint' => $hint($input['skr03_expense_hint'] ?? ''),
            'skr04_expense_hint' => $hint($input['skr04_expense_hint'] ?? ''),
            'skr03_liability_hint' => $hint($input['skr03_liability_hint'] ?? ''),
            'skr04_liability_hint' => $hint($input['skr04_liability_hint'] ?? ''),
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'is_active' => $isActive,
            'notes' => mb_substr(trim((string) ($input['notes'] ?? '')), 0, 2000),
        ];

        $pdo = Database::pdo();
        if ($existing === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO dg_payroll_wage_types
                    (code, label, category, unit, direction, taxable, subject_to_sv, flat_tax_possible, increases_net,
                     skr03_expense_hint, skr04_expense_hint, skr03_liability_hint, skr04_liability_hint,
                     sort_order, is_system, is_active, notes)
                 VALUES
                    (:code, :label, :category, :unit, :direction, :taxable, :subject_to_sv, :flat_tax_possible, :increases_net,
                     :skr03_expense_hint, :skr04_expense_hint, :skr03_liability_hint, :skr04_liability_hint,
                     :sort_order, 0, :is_active, :notes)'
            );
            $stmt->execute($payload);

            return (int) $pdo->lastInsertId();
        }

        $payload['id'] = (int) $existing['id'];
        $stmt = $pdo->prepare(
            'UPDATE dg_payroll_wage_types SET
                code = :code, label = :label, category = :category, unit = :unit, direction = :direction,
                taxable = :taxable, subject_to_sv = :subject_to_sv, flat_tax_possible = :flat_tax_possible,
                increases_net = :increases_net,
                skr03_expense_hint = :skr03_expense_hint, skr04_expense_hint = :skr04_expense_hint,
                skr03_liability_hint = :skr03_liability_hint, skr04_liability_hint = :skr04_liability_hint,
                sort_order = :sort_order, is_active = :is_active, notes = :notes
             WHERE id = :id'
        );
        $stmt->execute($payload);

        return (int) $existing['id'];
    }

    /**
     * Systemzeilen: nur deaktivieren. Eigene: hart löschen.
     */
    public static function deactivateOrDelete(int $id): void
    {
        $row = self::find($id);
        if ($row === null) {
            return;
        }
        if ((int) ($row['is_system'] ?? 0) === 1) {
            $stmt = Database::pdo()->prepare('UPDATE dg_payroll_wage_types SET is_active = 0 WHERE id = :id');
            $stmt->execute(['id' => $id]);

            return;
        }
        $stmt = Database::pdo()->prepare('DELETE FROM dg_payroll_wage_types WHERE id = :id AND is_system = 0');
        $stmt->execute(['id' => $id]);
    }
}
