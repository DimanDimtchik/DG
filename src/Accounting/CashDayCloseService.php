<?php
declare(strict_types=1);

/** Kassenbuch Tagesabschluss. */
final class CashDayCloseService
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function listClosings(int $year): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_cash_day_closings
             WHERE YEAR(closing_date) = :y
             ORDER BY closing_date DESC'
        );
        $stmt->execute(['y' => $year]);
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    public static function isClosed(string $date): bool
    {
        if (!Database::isConfigured() || $date === '') {
            return false;
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT id FROM dg_cash_day_closings WHERE closing_date = :d LIMIT 1'
        );
        $stmt->execute(['d' => $date]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @return array{opening: float, expected: float, entries_in: float, entries_out: float}
     */
    public static function daySummary(string $date): array
    {
        $opening = self::balanceBefore($date);
        $entries = CashJournalRepository::listForDateRange($date, $date);
        $in = 0.0;
        $out = 0.0;
        foreach ($entries as $entry) {
            $amount = round((float) ($entry['amount'] ?? 0), 2);
            if (($entry['side'] ?? '') === 'in') {
                $in = round($in + $amount, 2);
            } else {
                $out = round($out + $amount, 2);
            }
        }

        return [
            'opening' => $opening,
            'expected' => round($opening + $in - $out, 2),
            'entries_in' => $in,
            'entries_out' => $out,
        ];
    }

    public static function closeDay(string $date, float $countedBalance, string $note, ?int $userId): void
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        MigrationRunner::runPending();

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException('Ungültiges Datum.');
        }
        if (self::isClosed($date)) {
            throw new RuntimeException('Tag bereits abgeschlossen.');
        }

        $summary = self::daySummary($date);
        $expected = $summary['expected'];
        $counted = round($countedBalance, 2);
        $diff = round($counted - $expected, 2);

        Database::pdo()->prepare(
            'INSERT INTO dg_cash_day_closings
             (closing_date, opening_balance, expected_balance, counted_balance, difference, note, closed_by)
             VALUES (:d, :open, :expected, :counted, :diff, :note, :user)'
        )->execute([
            'd' => $date,
            'open' => $summary['opening'],
            'expected' => $expected,
            'counted' => $counted,
            'diff' => $diff,
            'note' => mb_substr(trim($note), 0, 500),
            'user' => $userId,
        ]);
    }

    private static function balanceBefore(string $date): float
    {
        if (!Database::isConfigured()) {
            return 0.0;
        }

        $stmt = Database::pdo()->prepare(
            "SELECT side, SUM(amount) AS total
             FROM dg_cash_journal
             WHERE entry_date < :d
             GROUP BY side"
        );
        $stmt->execute(['d' => $date]);
        $in = 0.0;
        $out = 0.0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            if (($row['side'] ?? '') === 'in') {
                $in = round((float) ($row['total'] ?? 0), 2);
            } else {
                $out = round((float) ($row['total'] ?? 0), 2);
            }
        }

        $closingStmt = Database::pdo()->prepare(
            'SELECT counted_balance FROM dg_cash_day_closings
             WHERE closing_date < :d ORDER BY closing_date DESC LIMIT 1'
        );
        $closingStmt->execute(['d' => $date]);
        $lastClosing = $closingStmt->fetchColumn();
        if ($lastClosing !== false) {
            return round((float) $lastClosing, 2);
        }

        return round($in - $out, 2);
    }
}
