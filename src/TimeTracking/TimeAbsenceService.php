<?php
declare(strict_types=1);

/**
 * Zeiterfassung Z4d: Krankheit / Sonstiges + Team-Monatskalender.
 */
final class TimeAbsenceService
{
    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'vacation' => 'Urlaub',
            'sick' => 'Krankheit',
            'other' => 'Sonstiges',
            default => $type,
        };
    }

    public static function typeShort(string $type): string
    {
        return match ($type) {
            'vacation' => 'U',
            'sick' => 'K',
            'other' => 'S',
            default => '?',
        };
    }

    /**
     * HR: Krank/Sonstiges direkt genehmigt (+ optional Attest-Ref).
     *
     * @return array{id: int, message: string}
     */
    public static function recordApproved(
        User $user,
        int $contactId,
        string $type,
        string $dateFrom,
        string $dateTo,
        string $reason,
        ?string $documentRef = null,
    ): array {
        if (!TimeClockService::canViewTeam($user)) {
            throw new RuntimeException('Keine Berechtigung (nur HR/Admin/full).');
        }
        if (!in_array($type, ['sick', 'other'], true)) {
            throw new InvalidArgumentException('Typ muss Krankheit oder Sonstiges sein.');
        }

        $id = TimeAbsenceRepository::create([
            'contact_id' => $contactId,
            'type' => $type,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'status' => 'approved',
            'reason' => $reason,
            'document_ref' => $documentRef,
            'created_by' => (int) ($user->id ?? 0) > 0 ? (int) $user->id : null,
        ]);
        TimeAbsenceRepository::setStatus($id, 'approved', (int) ($user->id ?? 0));

        return [
            'id' => $id,
            'message' => self::typeLabel($type) . ' erfasst (genehmigt).',
        ];
    }

    /**
     * MA: eigene Krankmeldung als beantragt (HR bestätigt).
     *
     * @return array{id: int, message: string}
     */
    public static function requestOwnSick(
        User $user,
        string $dateFrom,
        string $dateTo,
        string $reason,
        ?string $documentRef = null,
    ): array {
        if (!RoleResolver::canEdit($user)) {
            throw new RuntimeException('Keine Berechtigung.');
        }
        $ownId = ContactRepository::findStaffContactIdForUser($user);
        if ($ownId === null) {
            throw new InvalidArgumentException('Kein Mitarbeiter-Kontakt verknüpft.');
        }

        $id = TimeAbsenceRepository::create([
            'contact_id' => $ownId,
            'type' => 'sick',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'status' => 'requested',
            'reason' => $reason,
            'document_ref' => $documentRef,
            'created_by' => (int) ($user->id ?? 0) > 0 ? (int) $user->id : null,
        ]);

        return ['id' => $id, 'message' => 'Krankmeldung eingereicht — wartet auf HR-Bestätigung.'];
    }

    /**
     * @return array{message: string}
     */
    public static function approve(User $user, int $absenceId): array
    {
        if (!TimeClockService::canViewTeam($user)) {
            throw new RuntimeException('Keine Berechtigung für Freigabe.');
        }
        $row = self::requirePendingNonVacation($absenceId);
        TimeAbsenceRepository::setStatus($absenceId, 'approved', (int) ($user->id ?? 0));

        return ['message' => self::typeLabel((string) $row['type']) . ' bestätigt.'];
    }

    public static function reject(User $user, int $absenceId, string $reason): void
    {
        if (!TimeClockService::canViewTeam($user)) {
            throw new RuntimeException('Keine Berechtigung.');
        }
        self::requirePendingNonVacation($absenceId);
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Ablehnungsgrund erforderlich.');
        }
        TimeAbsenceRepository::setStatus($absenceId, 'rejected', (int) ($user->id ?? 0), $reason);
    }

    public static function cancelOwn(User $user, int $absenceId): void
    {
        $row = TimeAbsenceRepository::findById($absenceId);
        if ($row === null || (string) ($row['type'] ?? '') === 'vacation') {
            throw new InvalidArgumentException('Eintrag nicht gefunden.');
        }
        $ownId = ContactRepository::findStaffContactIdForUser($user);
        if ($ownId === null || $ownId !== (int) ($row['contact_id'] ?? 0)) {
            throw new RuntimeException('Nur eigene Anträge können zurückgezogen werden.');
        }
        TimeAbsenceRepository::cancel($absenceId, (int) ($user->id ?? 0));
    }

    /**
     * Monatskalender: Tage → Liste Abwesenheiten (genehmigt).
     *
     * @param list<array{id: int, label: string}> $staffOptions
     * @return array{
     *   year_month: string,
     *   days: list<array{date: string, date_display: string, weekday: string, items: list<array<string, mixed>>}>
     * }
     */
    public static function monthCalendar(string $yearMonth, array $staffOptions): array
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $yearMonth, $m)) {
            $yearMonth = date('Y-m');
            $m = [];
            preg_match('/^(\d{4})-(\d{2})$/', $yearMonth, $m);
        }
        $y = (int) $m[1];
        $mo = (int) $m[2];
        $from = sprintf('%04d-%02d-01', $y, $mo);
        $daysInMonth = (int) (new DateTimeImmutable($from))->format('t');
        $to = sprintf('%04d-%02d-%02d', $y, $mo, $daysInMonth);

        $labels = [];
        foreach ($staffOptions as $opt) {
            $labels[(int) ($opt['id'] ?? 0)] = (string) ($opt['label'] ?? '');
        }

        $byDate = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = sprintf('%04d-%02d-%02d', $y, $mo, $d);
            $byDate[$date] = [];
        }

        foreach (TimeAbsenceRepository::listOverlappingRange($from, $to, 'approved') as $row) {
            $cid = (int) ($row['contact_id'] ?? 0);
            $start = (string) ($row['date_from'] ?? '');
            $end = (string) ($row['date_to'] ?? '');
            if ($start === '' || $end === '') {
                continue;
            }
            $cursor = new DateTimeImmutable(max($start, $from));
            $last = new DateTimeImmutable(min($end, $to));
            for ($day = $cursor; $day <= $last; $day = $day->modify('+1 day')) {
                $key = $day->format('Y-m-d');
                if (!isset($byDate[$key])) {
                    continue;
                }
                $byDate[$key][] = [
                    'absence_id' => (int) ($row['id'] ?? 0),
                    'contact_id' => $cid,
                    'label' => $labels[$cid] ?? ('#' . $cid),
                    'type' => (string) ($row['type'] ?? ''),
                    'type_label' => self::typeLabel((string) ($row['type'] ?? '')),
                    'type_short' => self::typeShort((string) ($row['type'] ?? '')),
                    'document_ref' => $row['document_ref'] ?? null,
                ];
            }
        }

        $weekdayLabels = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
        $days = [];
        foreach ($byDate as $date => $items) {
            $dt = new DateTimeImmutable($date);
            $days[] = [
                'date' => $date,
                'date_display' => $dt->format('d.m.'),
                'weekday' => $weekdayLabels[(int) $dt->format('N')] ?? '',
                'items' => $items,
            ];
        }

        return ['year_month' => $yearMonth, 'days' => $days];
    }

    /**
     * @return array<string, mixed>
     */
    private static function requirePendingNonVacation(int $absenceId): array
    {
        $row = TimeAbsenceRepository::findById($absenceId);
        if ($row === null || (string) ($row['type'] ?? '') === 'vacation') {
            throw new InvalidArgumentException('Krank-/Sonstiges-Antrag nicht gefunden.');
        }
        if ((string) ($row['status'] ?? '') !== 'requested') {
            throw new InvalidArgumentException('Nur beantragte Einträge können bestätigt werden.');
        }

        return $row;
    }
}
