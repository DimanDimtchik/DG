<?php
declare(strict_types=1);

/**
 * Krankheit/Sonderurlaub schneidet überlappenden genehmigten Urlaub zu (BUrlG § 9-nah).
 * Resturlaub folgt aus days_count der Urlaubszeilen.
 */
final class TimeAbsenceOverlapService
{
    /** Typen, die genehmigten Urlaub automatisch kürzen. */
    public const INTERRUPT_TYPES = ['sick', 'special_leave'];

    public static function isInterruptType(string $type): bool
    {
        return in_array($type, self::INTERRUPT_TYPES, true);
    }

    public static function adjustmentsTableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            $r = Database::pdo()->query("SHOW TABLES LIKE 'dg_time_absence_adjustments'");

            return $r !== false && $r->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Nach Freigabe/HR-Speichern eines Interruptors: überlappenden Urlaub zuschneiden.
     *
     * @return array{days_carved: float, message: string, vacation_ids: list<int>}
     */
    public static function applyInterrupt(int $interruptId, ?int $byUserId): array
    {
        MigrationRunner::runPending();
        $interrupt = TimeAbsenceRepository::findById($interruptId);
        if ($interrupt === null) {
            return ['days_carved' => 0.0, 'message' => '', 'vacation_ids' => []];
        }
        $type = (string) ($interrupt['type'] ?? '');
        $status = (string) ($interrupt['status'] ?? '');
        if (!self::isInterruptType($type) || $status !== 'approved') {
            return ['days_carved' => 0.0, 'message' => '', 'vacation_ids' => []];
        }

        // Vorherige Carves dieses Interruptors zurücksetzen, dann neu anwenden
        self::restoreAfterInterruptRemoved($interruptId, $byUserId);

        $interrupt = TimeAbsenceRepository::findById($interruptId);
        if ($interrupt === null || (string) ($interrupt['status'] ?? '') !== 'approved') {
            return ['days_carved' => 0.0, 'message' => '', 'vacation_ids' => []];
        }

        $contactId = (int) ($interrupt['contact_id'] ?? 0);
        $iFrom = (string) ($interrupt['date_from'] ?? '');
        $iTo = (string) ($interrupt['date_to'] ?? '');
        $vacations = TimeAbsenceRepository::approvedVacationsOverlapping($contactId, $iFrom, $iTo);
        if ($vacations === []) {
            return ['days_carved' => 0.0, 'message' => '', 'vacation_ids' => []];
        }

        $totalCarved = 0.0;
        $touched = [];
        $label = TimeAbsenceService::typeLabel($type);

        foreach ($vacations as $vac) {
            $vacId = (int) ($vac['id'] ?? 0);
            if ($vacId < 1) {
                continue;
            }
            $result = self::carveOneVacation($vac, $iFrom, $iTo, $interruptId, $label, $byUserId);
            if ($result['days_carved'] > 0) {
                $totalCarved += $result['days_carved'];
                $touched[] = $vacId;
            }
        }

        $totalCarved = round($totalCarved, 1);
        $msg = '';
        if ($totalCarved > 0) {
            $msg = sprintf(
                '%.1f Urlaubstag(e) zurückgebucht (%s während Urlaub).',
                $totalCarved,
                $label
            );
        }

        return [
            'days_carved' => $totalCarved,
            'message' => $msg,
            'vacation_ids' => $touched,
        ];
    }

    /**
     * Interruptor entfernt/abgelehnt: Urlaub aus Audit wiederherstellen.
     */
    public static function restoreAfterInterruptRemoved(int $interruptId, ?int $byUserId): float
    {
        MigrationRunner::runPending();
        if ($interruptId < 1 || !self::adjustmentsTableReady()) {
            return 0.0;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT a.* FROM dg_time_absence_adjustments a
             WHERE a.interrupt_absence_id = :iid AND a.action = \'carve\'
               AND NOT EXISTS (
                   SELECT 1 FROM dg_time_absence_adjustments b
                   WHERE b.interrupt_absence_id = a.interrupt_absence_id
                     AND b.vacation_absence_id = a.vacation_absence_id
                     AND b.action = \'restore\'
                     AND b.id > a.id
               )
             ORDER BY a.id DESC'
        );
        $stmt->execute(['iid' => $interruptId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return 0.0;
        }

        // Pro vacation_absence_id nur den neuesten Carve
        $seenVac = [];
        $restoredDays = 0.0;
        foreach ($rows as $adj) {
            if (!is_array($adj)) {
                continue;
            }
            $vacId = (int) ($adj['vacation_absence_id'] ?? 0);
            if ($vacId < 1 || isset($seenVac[$vacId])) {
                continue;
            }
            $seenVac[$vacId] = true;

            $payload = json_decode((string) ($adj['payload_json'] ?? ''), true);
            if (!is_array($payload) || !is_array($payload['vacation_before'] ?? null)) {
                continue;
            }
            $before = $payload['vacation_before'];
            $createdIds = is_array($payload['created_ids'] ?? null)
                ? array_map('intval', $payload['created_ids'])
                : [];

            foreach ($createdIds as $cid) {
                if ($cid > 0 && $cid !== $vacId) {
                    $created = TimeAbsenceRepository::findById($cid);
                    if ($created !== null && (string) ($created['status'] ?? '') === 'approved') {
                        TimeAbsenceRepository::applyCarveFields(
                            $cid,
                            (string) ($created['date_from'] ?? ''),
                            (string) ($created['date_to'] ?? ''),
                            max(0.5, (float) ($created['days_count'] ?? 0.5)),
                            'cancelled',
                            'Automatisch storniert: Wiederherstellung nach Rücknahme Unterbrecher #' . $interruptId,
                            $byUserId
                        );
                    }
                }
            }

            $from = (string) ($before['date_from'] ?? '');
            $to = (string) ($before['date_to'] ?? '');
            $days = (float) ($before['days_count'] ?? 0);
            $status = (string) ($before['status'] ?? 'approved');
            $reason = (string) ($before['reason'] ?? '');
            if ($from === '' || $to === '') {
                continue;
            }
            if ($days < 0.5) {
                $days = TimeAbsenceRepository::countWorkingDays($from, $to);
            }
            if ($days < 0.5) {
                continue;
            }
            TimeAbsenceRepository::applyCarveFields(
                $vacId,
                $from,
                $to,
                $days,
                in_array($status, TimeAbsenceRepository::STATUSES, true) ? $status : 'approved',
                $reason !== '' ? $reason : 'Urlaub wiederhergestellt',
                $byUserId
            );

            $carved = (float) ($adj['days_carved'] ?? 0);
            $restoredDays += $carved;

            self::insertAdjustment([
                'interrupt_absence_id' => $interruptId,
                'vacation_absence_id' => $vacId,
                'action' => 'restore',
                'date_from' => (string) ($adj['date_from'] ?? $from),
                'date_to' => (string) ($adj['date_to'] ?? $to),
                'days_carved' => $carved,
                'payload_json' => json_encode(['restored_from_adjustment_id' => (int) ($adj['id'] ?? 0)], JSON_UNESCAPED_UNICODE),
                'created_by' => $byUserId,
            ]);
        }

        return round($restoredDays, 1);
    }

    /**
     * @param array<string, mixed> $vac
     * @return array{days_carved: float}
     */
    private static function carveOneVacation(
        array $vac,
        string $iFrom,
        string $iTo,
        int $interruptId,
        string $interruptLabel,
        ?int $byUserId,
    ): array {
        $vacId = (int) ($vac['id'] ?? 0);
        $vFrom = (string) ($vac['date_from'] ?? '');
        $vTo = (string) ($vac['date_to'] ?? '');
        $contactId = (int) ($vac['contact_id'] ?? 0);
        if ($vacId < 1 || $vFrom === '' || $vTo === '') {
            return ['days_carved' => 0.0];
        }

        $overlapFrom = max($vFrom, $iFrom);
        $overlapTo = min($vTo, $iTo);
        if ($overlapTo < $overlapFrom) {
            return ['days_carved' => 0.0];
        }
        $daysCarved = TimeAbsenceRepository::countWorkingDays($overlapFrom, $overlapTo);
        if ($daysCarved < 0.5) {
            return ['days_carved' => 0.0];
        }

        $before = $vac;
        $createdIds = [];
        $note = sprintf(
            'Angepasst: %s #%d (%s–%s, %.1f Tag(e))',
            $interruptLabel,
            $interruptId,
            $overlapFrom,
            $overlapTo,
            $daysCarved
        );

        // Fall A: Interrupt deckt gesamten Urlaub
        if ($overlapFrom <= $vFrom && $overlapTo >= $vTo) {
            $origReason = trim((string) ($vac['reason'] ?? ''));
            TimeAbsenceRepository::applyCarveFields(
                $vacId,
                $vFrom,
                $vTo,
                max(0.5, (float) ($vac['days_count'] ?? $daysCarved)),
                'cancelled',
                ($origReason !== '' ? $origReason . ' · ' : '') . 'Ersetzt durch ' . $note,
                $byUserId
            );
            self::insertAdjustment([
                'interrupt_absence_id' => $interruptId,
                'vacation_absence_id' => $vacId,
                'action' => 'carve',
                'date_from' => $overlapFrom,
                'date_to' => $overlapTo,
                'days_carved' => $daysCarved,
                'payload_json' => json_encode([
                    'mode' => 'cancel',
                    'vacation_before' => $before,
                    'created_ids' => [],
                ], JSON_UNESCAPED_UNICODE),
                'created_by' => $byUserId,
            ]);

            return ['days_carved' => $daysCarved];
        }

        // Fall B: Schnitt am Anfang → Von nach vorne
        if ($overlapFrom <= $vFrom && $overlapTo < $vTo) {
            $newFrom = (new DateTimeImmutable($overlapTo))->modify('+1 day')->format('Y-m-d');
            $newDays = TimeAbsenceRepository::countWorkingDays($newFrom, $vTo);
            if ($newDays < 0.5) {
                TimeAbsenceRepository::applyCarveFields(
                    $vacId,
                    $vFrom,
                    $vTo,
                    max(0.5, (float) ($vac['days_count'] ?? 0.5)),
                    'cancelled',
                    trim((string) ($vac['reason'] ?? '')) . ' · Ersetzt durch ' . $note,
                    $byUserId
                );
            } else {
                $origReason = trim((string) ($vac['reason'] ?? ''));
                TimeAbsenceRepository::applyCarveFields(
                    $vacId,
                    $newFrom,
                    $vTo,
                    $newDays,
                    'approved',
                    ($origReason !== '' ? $origReason . ' · ' : '') . $note,
                    $byUserId
                );
            }
            self::insertAdjustment([
                'interrupt_absence_id' => $interruptId,
                'vacation_absence_id' => $vacId,
                'action' => 'carve',
                'date_from' => $overlapFrom,
                'date_to' => $overlapTo,
                'days_carved' => $daysCarved,
                'payload_json' => json_encode([
                    'mode' => 'trim_start',
                    'vacation_before' => $before,
                    'created_ids' => [],
                ], JSON_UNESCAPED_UNICODE),
                'created_by' => $byUserId,
            ]);

            return ['days_carved' => $daysCarved];
        }

        // Fall C: Schnitt am Ende → Bis nach hinten
        if ($overlapFrom > $vFrom && $overlapTo >= $vTo) {
            $newTo = (new DateTimeImmutable($overlapFrom))->modify('-1 day')->format('Y-m-d');
            $newDays = TimeAbsenceRepository::countWorkingDays($vFrom, $newTo);
            if ($newDays < 0.5) {
                TimeAbsenceRepository::applyCarveFields(
                    $vacId,
                    $vFrom,
                    $vTo,
                    max(0.5, (float) ($vac['days_count'] ?? 0.5)),
                    'cancelled',
                    trim((string) ($vac['reason'] ?? '')) . ' · Ersetzt durch ' . $note,
                    $byUserId
                );
            } else {
                $origReason = trim((string) ($vac['reason'] ?? ''));
                TimeAbsenceRepository::applyCarveFields(
                    $vacId,
                    $vFrom,
                    $newTo,
                    $newDays,
                    'approved',
                    ($origReason !== '' ? $origReason . ' · ' : '') . $note,
                    $byUserId
                );
            }
            self::insertAdjustment([
                'interrupt_absence_id' => $interruptId,
                'vacation_absence_id' => $vacId,
                'action' => 'carve',
                'date_from' => $overlapFrom,
                'date_to' => $overlapTo,
                'days_carved' => $daysCarved,
                'payload_json' => json_encode([
                    'mode' => 'trim_end',
                    'vacation_before' => $before,
                    'created_ids' => [],
                ], JSON_UNESCAPED_UNICODE),
                'created_by' => $byUserId,
            ]);

            return ['days_carved' => $daysCarved];
        }

        // Fall D: Mitte → Original kürzen + zweite Urlaubszeile danach
        $leftTo = (new DateTimeImmutable($overlapFrom))->modify('-1 day')->format('Y-m-d');
        $rightFrom = (new DateTimeImmutable($overlapTo))->modify('+1 day')->format('Y-m-d');
        $leftDays = TimeAbsenceRepository::countWorkingDays($vFrom, $leftTo);
        $rightDays = TimeAbsenceRepository::countWorkingDays($rightFrom, $vTo);
        $origReason = trim((string) ($vac['reason'] ?? ''));

        if ($leftDays >= 0.5) {
            TimeAbsenceRepository::applyCarveFields(
                $vacId,
                $vFrom,
                $leftTo,
                $leftDays,
                'approved',
                ($origReason !== '' ? $origReason . ' · ' : '') . $note,
                $byUserId
            );
        } else {
            TimeAbsenceRepository::applyCarveFields(
                $vacId,
                $vFrom,
                $vTo,
                max(0.5, (float) ($vac['days_count'] ?? 0.5)),
                'cancelled',
                ($origReason !== '' ? $origReason . ' · ' : '') . 'Ersetzt (Mitte) durch ' . $note,
                $byUserId
            );
        }

        if ($rightDays >= 0.5) {
            $newId = TimeAbsenceRepository::create([
                'contact_id' => $contactId,
                'type' => 'vacation',
                'date_from' => $rightFrom,
                'date_to' => $vTo,
                'days_count' => $rightDays,
                'status' => 'approved',
                'reason' => ($origReason !== '' ? $origReason . ' · ' : '') . 'Fortsetzung nach ' . $note,
                'created_by' => $byUserId,
            ]);
            TimeAbsenceRepository::setStatus($newId, 'approved', $byUserId);
            $createdIds[] = $newId;
        }

        self::insertAdjustment([
            'interrupt_absence_id' => $interruptId,
            'vacation_absence_id' => $vacId,
            'action' => 'carve',
            'date_from' => $overlapFrom,
            'date_to' => $overlapTo,
            'days_carved' => $daysCarved,
            'payload_json' => json_encode([
                'mode' => 'split',
                'vacation_before' => $before,
                'created_ids' => $createdIds,
            ], JSON_UNESCAPED_UNICODE),
            'created_by' => $byUserId,
        ]);

        return ['days_carved' => $daysCarved];
    }

    /**
     * @param array{
     *   interrupt_absence_id: int,
     *   vacation_absence_id: int,
     *   action: string,
     *   date_from: string,
     *   date_to: string,
     *   days_carved: float,
     *   payload_json: string|null,
     *   created_by: int|null
     * } $row
     */
    private static function insertAdjustment(array $row): void
    {
        if (!self::adjustmentsTableReady()) {
            return;
        }
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_time_absence_adjustments
                (interrupt_absence_id, vacation_absence_id, action, date_from, date_to, days_carved, payload_json, created_by)
             VALUES
                (:iid, :vid, :action, :from, :to, :days, :payload, :by)'
        );
        $stmt->execute([
            'iid' => (int) $row['interrupt_absence_id'],
            'vid' => (int) $row['vacation_absence_id'],
            'action' => (string) $row['action'],
            'from' => (string) $row['date_from'],
            'to' => (string) $row['date_to'],
            'days' => round((float) $row['days_carved'], 1),
            'payload' => $row['payload_json'],
            'by' => ($row['created_by'] !== null && (int) $row['created_by'] > 0)
                ? (int) $row['created_by']
                : null,
        ]);
    }
}
