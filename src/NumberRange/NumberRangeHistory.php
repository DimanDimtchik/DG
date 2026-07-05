<?php
declare(strict_types=1);

final class NumberRangeHistory
{
    public static function ensureTable(): void
    {
        if (!Database::isConfigured()) {
            return;
        }

        MigrationRunner::runPending();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listAll(): array
    {
        self::ensureTable();
        if (!Database::isConfigured()) {
            return [];
        }

        $stmt = Database::pdo()->query(
            'SELECT id, document_type, prefix, number_pattern, suffix, number_display, number_pad, formula_label,
                    counter_from, counter_to, used_from, used_until
             FROM dg_number_range_history
             ORDER BY used_from DESC, id DESC'
        );

        $rows = [];
        if ($stmt !== false) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = self::mapRow($row);
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForType(string $type): array
    {
        self::ensureTable();
        if (!Database::isConfigured() || !NumberRangeSettings::isValidType($type)) {
            return [];
        }

        $stmt = Database::pdo()->prepare(
            'SELECT id, document_type, prefix, number_pattern, suffix, number_display, number_pad, formula_label,
                    counter_from, counter_to, used_from, used_until
             FROM dg_number_range_history
             WHERE document_type = :document_type
             ORDER BY used_from DESC, id DESC'
        );
        $stmt->execute(['document_type' => $type]);

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = self::mapRow($row);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $previous
     * @param array<string, mixed> $next
     */
    public static function syncOnSave(string $type, array $previous, array $next): void
    {
        self::ensureTable();
        if (!Database::isConfigured() || !NumberRangeSettings::isValidType($type)) {
            return;
        }

        if (NumberRangeFormula::fingerprint($previous) === NumberRangeFormula::fingerprint($next)) {
            return;
        }

        $counter = InvoiceNumberBuilder::sequenceCounter($next);
        self::closeActive($type, $counter);
        self::insertRecord($type, $next);
    }

    /** @param array<string, mixed> $document */
    private static function insertRecord(string $type, array $document): void
    {
        $counter = InvoiceNumberBuilder::sequenceCounter($document);
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_number_range_history (
                document_type, prefix, number_pattern, suffix, number_display, number_pad, country_code,
                formula_label, counter_from, counter_to, used_from, used_until
             ) VALUES (
                :document_type, :prefix, :number_pattern, :suffix, :number_display, :number_pad, :country_code,
                :formula_label, :counter_from, NULL, NOW(), NULL
             )'
        );
        $stmt->execute([
            'document_type' => $type,
            'prefix' => trim((string) ($document['prefix'] ?? '')),
            'number_pattern' => trim((string) ($document['number_pattern'] ?? '{NR}')),
            'suffix' => trim((string) ($document['suffix'] ?? '')),
            'number_display' => (string) ($document['number_display'] ?? 'decimal'),
            'number_pad' => max(0, min(12, (int) ($document['number_pad'] ?? 0))),
            'country_code' => strtoupper(trim((string) ($document['country_code'] ?? 'DE'))) ?: 'DE',
            'formula_label' => NumberRangeFormula::pattern($document),
            'counter_from' => $counter,
        ]);
    }

    private static function closeActive(string $type, int $counterTo): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_number_range_history
             SET used_until = NOW(), counter_to = :counter_to
             WHERE document_type = :document_type AND used_until IS NULL'
        );
        $stmt->execute([
            'document_type' => $type,
            'counter_to' => max(0, $counterTo),
        ]);
    }

    /** @param array<string, mixed> $row */
    private static function mapRow(array $row): array
    {
        $usedFrom = (string) ($row['used_from'] ?? '');
        $usedUntil = isset($row['used_until']) && $row['used_until'] !== null && $row['used_until'] !== ''
            ? (string) $row['used_until']
            : null;
        $isActive = $usedUntil === null;

        $formula = NumberRangeFormula::pattern([
            'prefix' => (string) ($row['prefix'] ?? ''),
            'number_pattern' => (string) ($row['number_pattern'] ?? '{NR}'),
            'suffix' => (string) ($row['suffix'] ?? ''),
        ]);
        if ($formula === '' && !empty($row['formula_label'])) {
            $formula = (string) $row['formula_label'];
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'document_type' => (string) ($row['document_type'] ?? ''),
            'type_label' => self::typeLabel((string) ($row['document_type'] ?? '')),
            'formula' => $formula,
            'formula_label' => $formula,
            'counter_from' => (int) ($row['counter_from'] ?? 0),
            'counter_to' => isset($row['counter_to']) && $row['counter_to'] !== null ? (int) $row['counter_to'] : null,
            'number_display' => (string) ($row['number_display'] ?? 'decimal'),
            'number_display_label' => self::numberDisplayLabel((string) ($row['number_display'] ?? 'decimal')),
            'number_pad' => max(0, (int) ($row['number_pad'] ?? 0)),
            'used_from' => $usedFrom,
            'used_until' => $usedUntil,
            'is_active' => $isActive,
        ];
    }

    public static function typeLabel(string $type): string
    {
        return NumberRangeSettings::documentTypes()[$type] ?? $type;
    }

    public static function numberDisplayLabel(string $display): string
    {
        $display = strtolower(trim($display));
        $bases = InvoiceNumberTokens::numberBases();

        return $bases[$display] ?? $bases['decimal'];
    }

    public static function formatPadLabel(int $pad): string
    {
        return $pad > 0 ? (string) $pad : '—';
    }

    /** @deprecated Nur noch intern; Spalte in der UI entfernt. */
    public static function durationLabel(string $usedFrom, ?string $usedUntil): string
    {
        if ($usedFrom === '') {
            return '—';
        }

        try {
            $start = new DateTimeImmutable($usedFrom);
            $end = $usedUntil !== null ? new DateTimeImmutable($usedUntil) : new DateTimeImmutable('now');
        } catch (Throwable) {
            return '—';
        }

        if ($end < $start) {
            return '—';
        }

        $diff = $start->diff($end);
        $parts = [];

        if ($diff->y > 0) {
            $parts[] = $diff->y === 1 ? '1 Jahr' : $diff->y . ' Jahre';
        }
        if ($diff->m > 0) {
            $parts[] = $diff->m === 1 ? '1 Monat' : $diff->m . ' Monate';
        }
        if ($parts === [] && $diff->d > 0) {
            $parts[] = $diff->d === 1 ? '1 Tag' : $diff->d . ' Tage';
        }
        if ($parts === [] && $diff->h > 0) {
            $parts[] = $diff->h === 1 ? '1 Stunde' : $diff->h . ' Stunden';
        }
        if ($parts === []) {
            $parts[] = 'weniger als 1 Stunde';
        }

        $label = implode(', ', $parts);

        return $usedUntil === null ? $label . ' (läuft)' : $label;
    }

    public static function formatDateTime(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '—';
        }

        try {
            return (new DateTimeImmutable($value))->format('d.m.Y H:i');
        } catch (Throwable) {
            return '—';
        }
    }
}
