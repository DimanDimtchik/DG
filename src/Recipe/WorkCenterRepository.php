<?php
declare(strict_types=1);

/**
 * Maschinen / Arbeitsplätze (Work Centers) — Rezeptur R2.
 */
final class WorkCenterRepository
{
    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
    }

    /**
     * @return array{
     *   name: string,
     *   purchase_price: string,
     *   life_hours: string,
     *   kw: string,
     *   space_m2: string,
     *   operators: string,
     *   is_active: bool,
     *   notes: string
     * }
     */
    public static function emptyForm(): array
    {
        return [
            'name' => '',
            'purchase_price' => '0',
            'life_hours' => '10000',
            'kw' => '0',
            'space_m2' => '0',
            'operators' => '1',
            'is_active' => true,
            'notes' => '',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listAll(bool $activeOnly = false): array
    {
        self::ensureReady();
        if (!Database::isConfigured()) {
            return [];
        }
        $sql = 'SELECT * FROM dg_work_centers';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY is_active DESC, name ASC, id ASC';
        $stmt = Database::pdo()->query($sql);

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        self::ensureReady();
        if ($id <= 0 || !Database::isConfigured()) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_work_centers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array{
     *   name: string,
     *   purchase_price: string,
     *   life_hours: string,
     *   kw: string,
     *   space_m2: string,
     *   operators: string,
     *   is_active: bool,
     *   notes: string
     * }
     */
    public static function formForId(int $id): array
    {
        $row = self::find($id);
        if ($row === null) {
            return self::emptyForm();
        }

        return [
            'name' => (string) ($row['name'] ?? ''),
            'purchase_price' => self::fmtDec((string) ($row['purchase_price'] ?? '0')),
            'life_hours' => self::fmtDec((string) ($row['life_hours'] ?? '1')),
            'kw' => self::fmtDec((string) ($row['kw'] ?? '0')),
            'space_m2' => self::fmtDec((string) ($row['space_m2'] ?? '0')),
            'operators' => self::fmtDec((string) ($row['operators'] ?? '1')),
            'is_active' => !empty($row['is_active']),
            'notes' => (string) ($row['notes'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function save(array $post, ?int $id): int
    {
        self::ensureReady();
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }

        $name = trim((string) ($post['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Bitte einen Namen für die Maschine / den Arbeitsplatz angeben.');
        }
        if (mb_strlen($name) > 191) {
            throw new InvalidArgumentException('Name ist zu lang (max. 191 Zeichen).');
        }

        $purchase = self::parseDec((string) ($post['purchase_price'] ?? '0'), 0.0, 'Anschaffungspreis');
        $life = self::parseDec((string) ($post['life_hours'] ?? '1'), 0.01, 'Nutzungsdauer');
        $kw = self::parseDec((string) ($post['kw'] ?? '0'), 0.0, 'Leistung (kW)');
        $space = self::parseDec((string) ($post['space_m2'] ?? '0'), 0.0, 'Fläche');
        $operators = self::parseDec((string) ($post['operators'] ?? '1'), 0.0, 'Bediener');
        $isActive = !empty($post['is_active']) ? 1 : 0;
        $notes = trim((string) ($post['notes'] ?? ''));
        if (mb_strlen($notes) > 1000) {
            $notes = mb_substr($notes, 0, 1000);
        }

        $pdo = Database::pdo();
        if ($id !== null && $id > 0) {
            if (self::find($id) === null) {
                throw new InvalidArgumentException('Arbeitsplatz nicht gefunden.');
            }
            $stmt = $pdo->prepare(
                'UPDATE dg_work_centers
                 SET name = :name,
                     purchase_price = :purchase_price,
                     life_hours = :life_hours,
                     kw = :kw,
                     space_m2 = :space_m2,
                     operators = :operators,
                     is_active = :is_active,
                     notes = :notes
                 WHERE id = :id'
            );
            $stmt->execute([
                'name' => $name,
                'purchase_price' => $purchase,
                'life_hours' => $life,
                'kw' => $kw,
                'space_m2' => $space,
                'operators' => $operators,
                'is_active' => $isActive,
                'notes' => $notes,
                'id' => $id,
            ]);

            return $id;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_work_centers
                (name, purchase_price, life_hours, kw, space_m2, operators, is_active, notes)
             VALUES
                (:name, :purchase_price, :life_hours, :kw, :space_m2, :operators, :is_active, :notes)'
        );
        $stmt->execute([
            'name' => $name,
            'purchase_price' => $purchase,
            'life_hours' => $life,
            'kw' => $kw,
            'space_m2' => $space,
            'operators' => $operators,
            'is_active' => $isActive,
            'notes' => $notes,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function delete(int $id): void
    {
        self::ensureReady();
        if ($id <= 0 || !Database::isConfigured()) {
            return;
        }
        $stmt = Database::pdo()->prepare('DELETE FROM dg_work_centers WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private static function parseDec(string $raw, float $min, string $label): float
    {
        $raw = trim(str_replace([' ', ','], ['', '.'], $raw));
        if ($raw === '' || !is_numeric($raw)) {
            throw new InvalidArgumentException($label . ': ungültige Zahl.');
        }
        $n = (float) $raw;
        if ($n < $min) {
            throw new InvalidArgumentException($label . ' darf nicht kleiner als ' . $min . ' sein.');
        }

        return $n;
    }

    private static function fmtDec(string $value): string
    {
        $value = trim(str_replace(',', '.', $value));
        if ($value === '' || !is_numeric($value)) {
            return '0';
        }
        $formatted = rtrim(rtrim(sprintf('%.4f', (float) $value), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
