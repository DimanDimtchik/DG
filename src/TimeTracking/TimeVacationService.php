<?php
declare(strict_types=1);

/**
 * Zeiterfassung Z4c: Urlaubsantrag und Freigabe (keine Krankheit).
 */
final class TimeVacationService
{
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'requested' => 'Beantragt',
            'approved' => 'Genehmigt',
            'rejected' => 'Abgelehnt',
            'cancelled' => 'Zurückgezogen',
            default => $status,
        };
    }

    /**
     * @return array{id: int, days_count: float, message: string}
     */
    public static function request(
        User $user,
        int $contactId,
        string $dateFrom,
        string $dateTo,
        string $reason,
        bool $halfDay = false,
    ): array {
        if (!RoleResolver::canEdit($user)) {
            throw new RuntimeException('Keine Berechtigung für Urlaubsantrag.');
        }
        $ownId = ContactRepository::findStaffContactIdForUser($user);
        if ($ownId === null || $ownId !== $contactId) {
            throw new RuntimeException('Urlaubsantrag nur für den eigenen Mitarbeiter-Kontakt.');
        }

        $days = null;
        if ($halfDay) {
            if ($dateFrom !== $dateTo) {
                throw new InvalidArgumentException('Halber Tag nur bei gleichem Von-/Bis-Datum.');
            }
            $wd = TimeAbsenceRepository::countWorkingDays($dateFrom, $dateTo);
            if ($wd < 1) {
                throw new InvalidArgumentException('Halber Tag nur an einem Werktag (Mo–Fr).');
            }
            $days = 0.5;
        }

        $id = TimeAbsenceRepository::create([
            'contact_id' => $contactId,
            'type' => 'vacation',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'days_count' => $days,
            'status' => 'requested',
            'reason' => $reason,
            'created_by' => (int) ($user->id ?? 0) > 0 ? (int) $user->id : null,
        ]);
        $row = TimeAbsenceRepository::findById($id);
        $daysCount = $row !== null ? (float) $row['days_count'] : 0.0;
        $year = (int) substr($dateFrom, 0, 4);
        $rest = TimeVacationEntitlementRepository::restDays($contactId, $year);
        $msg = sprintf('Urlaubsantrag gespeichert (%.1f Tage).', $daysCount);
        if ($daysCount > $rest) {
            $msg .= sprintf(' Hinweis: Restanspruch aktuell %.1f Tage.', $rest);
        }

        return ['id' => $id, 'days_count' => $daysCount, 'message' => $msg];
    }

    public static function cancelOwn(User $user, int $absenceId): void
    {
        $row = TimeAbsenceRepository::findById($absenceId);
        if ($row === null || (string) ($row['type'] ?? '') !== 'vacation') {
            throw new InvalidArgumentException('Urlaubsantrag nicht gefunden.');
        }
        $ownId = ContactRepository::findStaffContactIdForUser($user);
        if ($ownId === null || $ownId !== (int) ($row['contact_id'] ?? 0)) {
            throw new RuntimeException('Nur eigene Anträge können zurückgezogen werden.');
        }
        TimeAbsenceRepository::cancel($absenceId, (int) ($user->id ?? 0));
    }

    /**
     * @return array{message: string}
     */
    public static function approve(User $user, int $absenceId): array
    {
        self::assertCanDecide($user);
        $row = self::requirePendingVacation($absenceId);
        TimeAbsenceRepository::setStatus($absenceId, 'approved', (int) ($user->id ?? 0));
        $year = (int) substr((string) $row['date_from'], 0, 4);
        $rest = TimeVacationEntitlementRepository::restDays((int) $row['contact_id'], $year);
        $msg = 'Urlaub genehmigt.';
        if ($rest < 0) {
            $msg .= sprintf(' Hinweis: Restanspruch jetzt %.1f Tage.', $rest);
        }

        return ['message' => $msg];
    }

    public static function reject(User $user, int $absenceId, string $reason): void
    {
        self::assertCanDecide($user);
        self::requirePendingVacation($absenceId);
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Ablehnungsgrund ist erforderlich.');
        }
        TimeAbsenceRepository::setStatus($absenceId, 'rejected', (int) ($user->id ?? 0), $reason);
    }

    /**
     * @param array{days_entitled?: float|int|string, days_carried?: float|int|string, note?: string|null} $input
     */
    public static function saveEntitlement(User $user, int $contactId, int $year, array $input): int
    {
        self::assertCanDecide($user);
        if ($contactId < 1) {
            throw new InvalidArgumentException('Mitarbeiter wählen.');
        }

        return TimeVacationEntitlementRepository::save($contactId, $year, $input);
    }

    private static function assertCanDecide(User $user): void
    {
        if (!TimeClockService::canViewTeam($user)) {
            throw new RuntimeException('Keine Berechtigung für Freigabe/Anspruch (nur HR/Admin/full).');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function requirePendingVacation(int $absenceId): array
    {
        $row = TimeAbsenceRepository::findById($absenceId);
        if ($row === null || (string) ($row['type'] ?? '') !== 'vacation') {
            throw new InvalidArgumentException('Urlaubsantrag nicht gefunden.');
        }
        if ((string) ($row['status'] ?? '') !== 'requested') {
            throw new InvalidArgumentException('Nur beantragte Urlaube können freigegeben/abgelehnt werden.');
        }

        return $row;
    }
}
