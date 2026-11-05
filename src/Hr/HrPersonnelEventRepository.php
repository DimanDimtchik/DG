<?php
declare(strict_types=1);

/**
 * Persistenz für Personalvorgänge (Abmahnung, Kündigung, Akte).
 */
final class HrPersonnelEventRepository
{
    public const TYPE_NOTE = 'note';
    public const TYPE_WARNING = 'warning';
    public const TYPE_TERM_ORDINARY = 'termination_ordinary';
    public const TYPE_TERM_SUMMARY = 'termination_summary';
    public const TYPE_TERM_AGREEMENT = 'termination_agreement';

    public const STATUS_ISSUED = 'issued';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public const CHANNEL_HAND = 'hand';
    public const CHANNEL_REGISTERED = 'registered_mail';
    public const CHANNEL_WITNESS = 'witness';
    public const CHANNEL_OTHER = 'other';

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_NOTE => 'Aktennotiz / Scan',
            self::TYPE_WARNING => 'Abmahnung',
            self::TYPE_TERM_ORDINARY => 'Kündigung (ordentlich)',
            self::TYPE_TERM_SUMMARY => 'Kündigung (fristlos)',
            self::TYPE_TERM_AGREEMENT => 'Aufhebungsvertrag',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_ISSUED => 'Erstellt',
            self::STATUS_DELIVERED => 'Zugegangen',
            self::STATUS_CANCELLED => 'Storniert',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function channelLabels(): array
    {
        return [
            self::CHANNEL_HAND => 'Persönliche Übergabe',
            self::CHANNEL_REGISTERED => 'Einschreiben / Zustellnachweis',
            self::CHANNEL_WITNESS => 'Übergabe mit Zeuge/Zeugin',
            self::CHANNEL_OTHER => 'Sonstiger Kanal',
        ];
    }

    public static function ensureSchema(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        try {
            MigrationRunner::runPending();
        } catch (Throwable) {
            // Schema ggf. später
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForContact(int $contactId): array
    {
        self::ensureSchema();
        if ($contactId <= 0 || !self::tableReady()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_hr_personnel_events WHERE contact_id = :cid ORDER BY event_date DESC, id DESC'
        );
        $stmt->execute(['cid' => $contactId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([self::class, 'normalize'], $rows);
    }

    public static function find(int $id): ?array
    {
        self::ensureSchema();
        if ($id <= 0 || !self::tableReady()) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_hr_personnel_events WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::normalize($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function insert(array $data): int
    {
        self::ensureSchema();
        if (!self::tableReady()) {
            throw new RuntimeException('Tabelle für Personalvorgänge fehlt. Bitte Migrationen ausführen.');
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO dg_hr_personnel_events (
                contact_id, event_type, event_date, title, summary, facts_text, expectation_text, threat_text,
                status, delivery_date, delivery_channel, delivery_note,
                file_doc_type, file_index, file_path, created_by
            ) VALUES (
                :contact_id, :event_type, :event_date, :title, :summary, :facts_text, :expectation_text, :threat_text,
                :status, :delivery_date, :delivery_channel, :delivery_note,
                :file_doc_type, :file_index, :file_path, :created_by
            )'
        );
        $stmt->execute([
            'contact_id' => (int) ($data['contact_id'] ?? 0),
            'event_type' => (string) ($data['event_type'] ?? self::TYPE_NOTE),
            'event_date' => (string) ($data['event_date'] ?? date('Y-m-d')),
            'title' => mb_substr(trim((string) ($data['title'] ?? '')), 0, 255),
            'summary' => self::nullIfEmpty($data['summary'] ?? null),
            'facts_text' => self::nullIfEmpty($data['facts_text'] ?? null),
            'expectation_text' => self::nullIfEmpty($data['expectation_text'] ?? null),
            'threat_text' => self::nullIfEmpty($data['threat_text'] ?? null),
            'status' => (string) ($data['status'] ?? self::STATUS_ISSUED),
            'delivery_date' => self::nullIfEmpty($data['delivery_date'] ?? null),
            'delivery_channel' => trim((string) ($data['delivery_channel'] ?? '')),
            'delivery_note' => self::nullIfEmpty($data['delivery_note'] ?? null),
            'file_doc_type' => trim((string) ($data['file_doc_type'] ?? '')),
            'file_index' => isset($data['file_index']) && $data['file_index'] !== null && $data['file_index'] !== ''
                ? (int) $data['file_index']
                : null,
            'file_path' => trim((string) ($data['file_path'] ?? '')),
            'created_by' => isset($data['created_by']) && (int) $data['created_by'] > 0 ? (int) $data['created_by'] : null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $patch
     */
    public static function update(int $id, array $patch): void
    {
        self::ensureSchema();
        if ($id <= 0 || !self::tableReady()) {
            throw new RuntimeException('Personalvorgang nicht speicherbar.');
        }
        $allowed = [
            'event_date', 'title', 'summary', 'facts_text', 'expectation_text', 'threat_text',
            'status', 'delivery_date', 'delivery_channel', 'delivery_note',
            'file_doc_type', 'file_index', 'file_path',
        ];
        $sets = [];
        $params = ['id' => $id];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $patch)) {
                continue;
            }
            $sets[] = $key . ' = :' . $key;
            if (in_array($key, ['summary', 'facts_text', 'expectation_text', 'threat_text', 'delivery_date', 'delivery_note'], true)) {
                $params[$key] = self::nullIfEmpty($patch[$key]);
            } elseif ($key === 'file_index') {
                $params[$key] = $patch[$key] === null || $patch[$key] === '' ? null : (int) $patch[$key];
            } else {
                $params[$key] = is_string($patch[$key]) ? trim($patch[$key]) : $patch[$key];
            }
        }
        if ($sets === []) {
            return;
        }
        $sql = 'UPDATE dg_hr_personnel_events SET ' . implode(', ', $sets) . ' WHERE id = :id';
        Database::pdo()->prepare($sql)->execute($params);
    }

    private static function tableReady(): bool
    {
        try {
            Database::pdo()->query('SELECT 1 FROM dg_hr_personnel_events LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function normalize(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'contact_id' => (int) ($row['contact_id'] ?? 0),
            'event_type' => (string) ($row['event_type'] ?? self::TYPE_NOTE),
            'event_date' => (string) ($row['event_date'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'summary' => (string) ($row['summary'] ?? ''),
            'facts_text' => (string) ($row['facts_text'] ?? ''),
            'expectation_text' => (string) ($row['expectation_text'] ?? ''),
            'threat_text' => (string) ($row['threat_text'] ?? ''),
            'status' => (string) ($row['status'] ?? self::STATUS_ISSUED),
            'delivery_date' => (string) ($row['delivery_date'] ?? ''),
            'delivery_channel' => (string) ($row['delivery_channel'] ?? ''),
            'delivery_note' => (string) ($row['delivery_note'] ?? ''),
            'file_doc_type' => (string) ($row['file_doc_type'] ?? ''),
            'file_index' => isset($row['file_index']) && $row['file_index'] !== null && $row['file_index'] !== ''
                ? (int) $row['file_index']
                : null,
            'file_path' => (string) ($row['file_path'] ?? ''),
            'created_by' => isset($row['created_by']) && $row['created_by'] !== null ? (int) $row['created_by'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private static function nullIfEmpty(mixed $value): ?string
    {
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
