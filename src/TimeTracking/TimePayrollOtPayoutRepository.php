<?php
declare(strict_types=1);

/** Zeiterfassung Z6e: geplante Überstunden-Auszahlung je Monat. */
final class TimePayrollOtPayoutRepository
{
    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            $r = Database::pdo()->query("SHOW TABLES LIKE 'dg_time_payroll_ot_payouts'");

            return $r !== false && $r->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, array{minutes: int, applied_minutes: int|null, applied_at: string|null, export_id: int|null}>
     */
    public static function mapForMonth(string $yearMonth): array
    {
        if (!self::tableReady() || !preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            return [];
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'SELECT contact_id, minutes, applied_minutes, applied_at, export_id
             FROM dg_time_payroll_ot_payouts
             WHERE `year_month` = :ym'
        );
        $stmt->execute(['ym' => $yearMonth]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cid = (int) ($row['contact_id'] ?? 0);
            if ($cid < 1) {
                continue;
            }
            $applied = $row['applied_minutes'] ?? null;
            $out[$cid] = [
                'minutes' => max(0, (int) ($row['minutes'] ?? 0)),
                'applied_minutes' => $applied !== null ? max(0, (int) $applied) : null,
                'applied_at' => isset($row['applied_at']) && $row['applied_at'] !== null && $row['applied_at'] !== ''
                    ? (string) $row['applied_at']
                    : null,
                'export_id' => isset($row['export_id']) && $row['export_id'] !== null
                    ? (int) $row['export_id']
                    : null,
            ];
        }

        return $out;
    }

    /**
     * Speichert geplante Minuten (überschreibt, sofern noch nicht abgebucht).
     *
     * @param array<int, int> $minutesByContact contact_id => minutes
     * @return int Anzahl geänderter Zeilen
     */
    public static function saveDrafts(string $yearMonth, array $minutesByContact, ?int $createdBy): int
    {
        if (!self::tableReady()) {
            throw new RuntimeException('Auszahlungs-Tabelle fehlt — Migration 098 ausführen.');
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            throw new InvalidArgumentException('Monat JJJJ-MM erforderlich.');
        }
        MigrationRunner::runPending();
        $pdo = Database::pdo();
        $changed = 0;
        $sel = $pdo->prepare(
            'SELECT id, applied_at FROM dg_time_payroll_ot_payouts
             WHERE `year_month` = :ym AND contact_id = :cid LIMIT 1'
        );
        $ins = $pdo->prepare(
            'INSERT INTO dg_time_payroll_ot_payouts
                (`year_month`, contact_id, minutes, created_by)
             VALUES (:ym, :cid, :minutes, :by)'
        );
        $upd = $pdo->prepare(
            'UPDATE dg_time_payroll_ot_payouts
             SET minutes = :minutes, updated_at = NOW()
             WHERE id = :id AND applied_at IS NULL'
        );
        $del = $pdo->prepare(
            'DELETE FROM dg_time_payroll_ot_payouts
             WHERE id = :id AND applied_at IS NULL'
        );

        foreach ($minutesByContact as $contactId => $minutes) {
            $cid = (int) $contactId;
            $mins = max(0, (int) $minutes);
            if ($cid < 1) {
                continue;
            }
            $sel->execute(['ym' => $yearMonth, 'cid' => $cid]);
            $row = $sel->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && !empty($row['applied_at'])) {
                continue; // bereits abgebucht — nicht mehr ändern
            }
            if ($mins < 1) {
                if (is_array($row) && (int) ($row['id'] ?? 0) > 0) {
                    $del->execute(['id' => (int) $row['id']]);
                    $changed += $del->rowCount();
                }
                continue;
            }
            if (is_array($row) && (int) ($row['id'] ?? 0) > 0) {
                $upd->execute(['minutes' => $mins, 'id' => (int) $row['id']]);
                $changed += $upd->rowCount();
            } else {
                $ins->execute([
                    'ym' => $yearMonth,
                    'cid' => $cid,
                    'minutes' => $mins,
                    'by' => ($createdBy !== null && $createdBy > 0) ? $createdBy : null,
                ]);
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * Bucht offene Auszahlungen vom Überstundenkonto (FIFO) und markiert sie als angewendet.
     *
     * @return array{applied: int, contacts: int, skipped: int}
     */
    public static function applyPending(string $yearMonth, int $exportId, ?int $createdBy): array
    {
        if (!self::tableReady() || !preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            return ['applied' => 0, 'contacts' => 0, 'skipped' => 0];
        }
        MigrationRunner::runPending();
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, contact_id, minutes FROM dg_time_payroll_ot_payouts
             WHERE `year_month` = :ym AND applied_at IS NULL AND minutes > 0
             ORDER BY contact_id ASC'
        );
        $stmt->execute(['ym' => $yearMonth]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $mark = $pdo->prepare(
            'UPDATE dg_time_payroll_ot_payouts
             SET applied_minutes = :am, export_id = :eid, applied_at = NOW()
             WHERE id = :id AND applied_at IS NULL'
        );

        $applied = 0;
        $contacts = 0;
        $skipped = 0;
        $reason = sprintf('Lohn-Export %s Überstunden-Auszahlung (Export #%d)', $yearMonth, $exportId);

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            $cid = (int) ($row['contact_id'] ?? 0);
            $mins = max(0, (int) ($row['minutes'] ?? 0));
            if ($id < 1 || $cid < 1 || $mins < 1) {
                continue;
            }
            $available = OvertimeLotRepository::sumRemainingMinutes($cid);
            if ($available < 1) {
                $mark->execute(['am' => 0, 'eid' => $exportId > 0 ? $exportId : null, 'id' => $id]);
                $skipped++;
                continue;
            }
            $take = min($mins, $available);
            $reduced = OvertimeLotRepository::reduceMinutesFifo($cid, $take);
            if ($reduced < 1) {
                $mark->execute(['am' => 0, 'eid' => $exportId > 0 ? $exportId : null, 'id' => $id]);
                $skipped++;
                continue;
            }
            OvertimeLotRepository::insertReductionAudit($cid, $reduced, $reason, $createdBy);
            $mark->execute(['am' => $reduced, 'eid' => $exportId > 0 ? $exportId : null, 'id' => $id]);
            $applied += $reduced;
            $contacts++;
        }

        return ['applied' => $applied, 'contacts' => $contacts, 'skipped' => $skipped];
    }
}
