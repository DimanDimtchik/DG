<?php
declare(strict_types=1);

/**
 * Zeiterfassung Z2e: Korrektur (Audit) + Überstunden-Abbau.
 * Nur TimeClockService::canViewTeam — Originale Stempel unverändert.
 */
final class TimeCorrectionService
{
    public const EVENT_CORRECTION_AUDIT = 'correction_audit';

    public static function assertCanManage(User $user): void
    {
        if (!TimeClockService::canViewTeam($user)) {
            throw new RuntimeException('Keine Berechtigung für Korrektur/Abbau (nur HR/Admin/full).');
        }
    }

    /**
     * @return array{id: int, message: string}
     */
    public static function addWorkedMinutesCorrection(
        User $user,
        int $contactId,
        string $workDate,
        int $deltaMinutes,
        string $reason,
    ): array {
        self::assertCanManage($user);
        if ($contactId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
            throw new InvalidArgumentException('Kontakt und Datum erforderlich.');
        }
        if ($deltaMinutes === 0) {
            throw new InvalidArgumentException('Korrektur-Minuten dürfen nicht 0 sein.');
        }
        if (abs($deltaMinutes) > 960) {
            throw new InvalidArgumentException('Korrektur maximal ±16 Stunden.');
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new InvalidArgumentException('Begründung mindestens 5 Zeichen.');
        }
        if (mb_strlen($reason) > 500) {
            $reason = mb_substr($reason, 0, 500);
        }
        if (!self::isStaffContact($contactId)) {
            throw new InvalidArgumentException('Nur Mitarbeiter-Kontakte.');
        }

        MigrationRunner::runPending();
        if (!TimeCorrectionRepository::tableReady()) {
            throw new RuntimeException('Korrektur-Tabelle fehlt — bitte Migration 090 ausführen.');
        }

        $id = TimeCorrectionRepository::insert($contactId, $workDate, $deltaMinutes, $reason, $user->id);
        $sign = $deltaMinutes > 0 ? '+' : '';
        $note = sprintf(
            'Korrektur %s%d min (%s) — %s',
            $sign,
            $deltaMinutes,
            $workDate,
            $reason
        );
        TimeClockRepository::insert(
            $contactId,
            self::EVENT_CORRECTION_AUDIT,
            $workDate . ' 23:59:59',
            TimeClockRepository::SOURCE_WEB,
            $note,
            $user->id
        );

        try {
            TimeWorkDayService::aggregateContactDay($contactId, $workDate);
        } catch (Throwable) {
            // best effort
        }

        return [
            'id' => $id,
            'message' => sprintf(
                'Korrektur gebucht: %s%d min am %s (Stempel-Historie unverändert).',
                $sign,
                $deltaMinutes,
                $workDate
            ),
        ];
    }

    /**
     * Überstunden abbuchen (FIFO nach expires_at). Minijob gesperrt.
     *
     * @return array{reduced: int, message: string}
     */
    public static function reduceOvertime(User $user, int $contactId, int $minutes, string $reason): array
    {
        self::assertCanManage($user);
        if ($contactId < 1 || $minutes < 1) {
            throw new InvalidArgumentException('Kontakt und positive Minuten erforderlich.');
        }
        if ($minutes > 6000) {
            throw new InvalidArgumentException('Abbau maximal 100 Stunden pro Buchung.');
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new InvalidArgumentException('Begründung mindestens 5 Zeichen.');
        }
        if (mb_strlen($reason) > 500) {
            $reason = mb_substr($reason, 0, 500);
        }
        if (!self::isStaffContact($contactId)) {
            throw new InvalidArgumentException('Nur Mitarbeiter-Kontakte.');
        }

        $employeeData = self::employeeData($contactId);
        if (EmployeeData::isMinijob($employeeData)) {
            throw new InvalidArgumentException('Minijob: kein Überstundenkonto / kein Abbau.');
        }
        if (!EmployeeData::overtimeAllowed($employeeData)) {
            throw new InvalidArgumentException('Überstunden für diesen Mitarbeiter nicht freigegeben.');
        }

        MigrationRunner::runPending();
        OvertimeLotRepository::syncAccrualsFromWorkDays($contactId);
        $available = OvertimeLotRepository::sumRemainingMinutes($contactId);
        if ($available < 1) {
            throw new InvalidArgumentException('Kein Überstunden-Saldo vorhanden.');
        }
        if ($minutes > $available) {
            throw new InvalidArgumentException(
                'Abbau (' . $minutes . ' min) größer als Saldo (' . $available . ' min).'
            );
        }

        $reduced = OvertimeLotRepository::reduceMinutesFifo($contactId, $minutes);
        OvertimeLotRepository::insertReductionAudit($contactId, $reduced, $reason, $user->id);

        return [
            'reduced' => $reduced,
            'message' => sprintf(
                'Überstunden abgebaut: %s h (Restsaldo %s h).',
                TimeClockService::formatMinutes($reduced),
                TimeClockService::formatMinutes(OvertimeLotRepository::sumRemainingMinutes($contactId))
            ),
        ];
    }

    private static function isStaffContact(int $contactId): bool
    {
        foreach (TimeMonthReportService::staffOptions() as $opt) {
            if ((int) ($opt['id'] ?? 0) === $contactId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private static function employeeData(int $contactId): array
    {
        $c = ContactRepository::findById($contactId);
        if ($c === null) {
            return EmployeeData::empty();
        }
        $raw = $c->employeeData ?? [];

        return is_array($raw) ? EmployeeData::sanitize($raw) : EmployeeData::empty();
    }
}
