<?php
declare(strict_types=1);

/**
 * Zeiterfassung Z4d: Abwesenheit (Urlaub/Krankheit/OT-Abbau/…) + Team-Monatskalender.
 */
final class TimeAbsenceService
{
    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'vacation' => 'Urlaub',
            'sick' => 'Krankheit',
            'other' => 'Sonstiges',
            'ot_comp' => 'Überstundenabbau',
            'unpaid_leave' => 'Unbezahlter Urlaub',
            'special_leave' => 'Sonderurlaub',
            default => $type,
        };
    }

    public static function typeShort(string $type): string
    {
        return match ($type) {
            'vacation' => 'U',
            'sick' => 'K',
            'other' => 'S',
            'ot_comp' => 'Ü',
            'unpaid_leave' => 'N',
            'special_leave' => 'So',
            default => '?',
        };
    }

    /**
     * Kiosk: Abwesenheitsantrag für Session-Kontakt (ohne CRM-User).
     *
     * @return array{id: int, message: string}
     */
    public static function requestFromKiosk(
        int $contactId,
        string $type,
        string $dateFrom,
        string $dateTo,
        string $reason,
        bool $halfDay = false,
        array $evidenceFiles = [],
    ): array {
        if ($contactId < 1) {
            throw new InvalidArgumentException('Kein Mitarbeiter angemeldet.');
        }
        if (!in_array($type, TimeTrackingSettings::enabledAbsenceTypesForKiosk(), true)) {
            throw new InvalidArgumentException('Dieser Abwesenheitstyp ist nicht freigeschaltet.');
        }

        $days = null;
        if ($halfDay) {
            if ($type !== 'vacation') {
                throw new InvalidArgumentException('Halber Tag nur bei Urlaub.');
            }
            if ($dateFrom !== $dateTo) {
                throw new InvalidArgumentException('Halber Tag nur bei gleichem Von-/Bis-Datum.');
            }
            $wd = TimeAbsenceRepository::countWorkingDays($dateFrom, $dateTo);
            if ($wd < 1) {
                throw new InvalidArgumentException('Halber Tag nur an einem Werktag (Mo–Fr).');
            }
            $days = 0.5;
        }

        if ($type === 'ot_comp') {
            self::assertOtCompPossible($contactId, $dateFrom, $dateTo);
        }

        $id = TimeAbsenceRepository::create([
            'contact_id' => $contactId,
            'type' => $type,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'days_count' => $days,
            'status' => 'requested',
            'reason' => $reason,
            'created_by' => null,
        ]);

        $attached = 0;
        if (TimeAbsenceEvidenceStorage::allowsEvidence($type) && $evidenceFiles !== []) {
            $attached = TimeAbsenceEvidenceStorage::storeUploads($id, $contactId, $evidenceFiles, null);
        }

        $row = TimeAbsenceRepository::findById($id);
        $daysCount = $row !== null ? (float) ($row['days_count'] ?? 0) : 0.0;
        $msg = self::typeLabel($type) . ' beantragt';
        if ($type === 'vacation') {
            $msg .= sprintf(' (%.1f Tage)', $daysCount);
        }
        if ($attached > 0) {
            $msg .= sprintf(' · %d Nachweis(e)', $attached);
        }
        $msg .= ' — wartet auf HR-Bestätigung.';

        return ['id' => $id, 'message' => $msg];
    }

    /**
     * HR: Abwesenheit direkt genehmigt (+ optional Attest-Ref).
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
        bool $halfDay = false,
        array $evidenceFiles = [],
    ): array {
        if (!TimeClockService::canViewTeam($user)) {
            throw new RuntimeException('Keine Berechtigung (nur HR/Admin/full).');
        }
        if (!in_array($type, TimeAbsenceRepository::TYPES, true)) {
            throw new InvalidArgumentException('Ungültiger Abwesenheitstyp.');
        }

        $days = null;
        if ($halfDay) {
            if ($type !== 'vacation') {
                throw new InvalidArgumentException('Halber Tag nur bei Urlaub.');
            }
            if ($dateFrom !== $dateTo) {
                throw new InvalidArgumentException('Halber Tag nur bei gleichem Von-/Bis-Datum.');
            }
            $wd = TimeAbsenceRepository::countWorkingDays($dateFrom, $dateTo);
            if ($wd < 1) {
                throw new InvalidArgumentException('Halber Tag nur an einem Werktag (Mo–Fr).');
            }
            $days = 0.5;
        }

        if ($type === 'ot_comp') {
            self::assertOtCompPossible($contactId, $dateFrom, $dateTo);
        }

        $id = TimeAbsenceRepository::create([
            'contact_id' => $contactId,
            'type' => $type,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'days_count' => $days,
            'status' => 'approved',
            'reason' => $reason,
            'document_ref' => in_array($type, ['sick', 'other', 'special_leave'], true) ? $documentRef : null,
            'created_by' => (int) ($user->id ?? 0) > 0 ? (int) $user->id : null,
        ]);
        TimeAbsenceRepository::setStatus($id, 'approved', (int) ($user->id ?? 0));

        $attached = 0;
        if (TimeAbsenceEvidenceStorage::allowsEvidence($type) && $evidenceFiles !== []) {
            $attached = TimeAbsenceEvidenceStorage::storeUploads(
                $id,
                $contactId,
                $evidenceFiles,
                (int) ($user->id ?? 0) > 0 ? (int) $user->id : null
            );
        }

        $otMsg = '';
        if ($type === 'ot_comp') {
            $otMsg = self::applyOtCompConversion(
                $contactId,
                $dateFrom,
                $dateTo,
                (int) ($user->id ?? 0) > 0 ? (int) $user->id : null
            );
        }

        $row = TimeAbsenceRepository::findById($id);
        $daysCount = $row !== null ? (float) ($row['days_count'] ?? 0) : 0.0;
        $msg = self::typeLabel($type) . ' erfasst (genehmigt)';
        if ($type === 'vacation') {
            $msg .= sprintf(' — %.1f Tage', $daysCount);
            $year = (int) substr($dateFrom, 0, 4);
            $rest = TimeVacationEntitlementRepository::restDays($contactId, $year);
            if ($daysCount > $rest) {
                $msg .= sprintf(' (Hinweis: Restanspruch aktuell %.1f Tage)', $rest);
            }
        }
        if ($attached > 0) {
            $msg .= sprintf(' · %d Nachweis(e)', $attached);
        }
        if ($otMsg !== '') {
            $msg .= ' — ' . $otMsg;
        }
        $msg .= '.';

        return ['id' => $id, 'message' => $msg];
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
        array $evidenceFiles = [],
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

        $attached = 0;
        if ($evidenceFiles !== []) {
            $attached = TimeAbsenceEvidenceStorage::storeUploads(
                $id,
                $ownId,
                $evidenceFiles,
                (int) ($user->id ?? 0) > 0 ? (int) $user->id : null
            );
        }

        $msg = 'Krankmeldung eingereicht — wartet auf HR-Bestätigung.';
        if ($attached > 0) {
            $msg = sprintf('Krankmeldung mit %d Nachweis(en) eingereicht — wartet auf HR-Bestätigung.', $attached);
        }

        return ['id' => $id, 'message' => $msg];
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
        $type = (string) ($row['type'] ?? '');
        $contactId = (int) ($row['contact_id'] ?? 0);
        $dateFrom = (string) ($row['date_from'] ?? '');
        $dateTo = (string) ($row['date_to'] ?? '');

        if ($type === 'ot_comp') {
            self::assertOtCompPossible($contactId, $dateFrom, $dateTo);
        }

        TimeAbsenceRepository::setStatus($absenceId, 'approved', (int) ($user->id ?? 0));

        $msg = self::typeLabel($type) . ' bestätigt';
        if ($type === 'ot_comp') {
            $otMsg = self::applyOtCompConversion(
                $contactId,
                $dateFrom,
                $dateTo,
                (int) ($user->id ?? 0) > 0 ? (int) $user->id : null
            );
            if ($otMsg !== '') {
                $msg .= ' — ' . $otMsg;
            }
        }
        $msg .= '.';

        return ['message' => $msg];
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
     * Minutenbedarf und Werktage für Überstundenabbau (Soll ohne Abwesenheits-Null).
     *
     * @return array{total_minutes: int, days: list<array{date: string, minutes: int}>}
     */
    public static function otCompPlan(int $contactId, string $dateFrom, string $dateTo): array
    {
        if ($contactId < 1
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)
            || $dateTo < $dateFrom) {
            throw new InvalidArgumentException('Ungültiger Zeitraum für Überstundenabbau.');
        }

        $days = [];
        $total = 0;
        $from = new DateTimeImmutable($dateFrom);
        $to = new DateTimeImmutable($dateTo);
        for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) {
            if ((int) $d->format('N') > 5) {
                continue;
            }
            $ymd = $d->format('Y-m-d');
            $mins = TimeScheduleService::targetMinutesIgnoringAbsence($contactId, $ymd);
            if ($mins < 1) {
                continue;
            }
            $days[] = ['date' => $ymd, 'minutes' => $mins];
            $total += $mins;
        }

        if ($total < 1 || $days === []) {
            throw new InvalidArgumentException('Keine Werktage mit Soll-Stunden im Zeitraum.');
        }

        return ['total_minutes' => $total, 'days' => $days];
    }

    private static function assertOtCompPossible(int $contactId, string $dateFrom, string $dateTo): void
    {
        $c = ContactRepository::findById($contactId);
        $raw = $c !== null && is_array($c->employeeData ?? null) ? $c->employeeData : [];
        $employeeData = is_array($raw) ? EmployeeData::sanitize($raw) : EmployeeData::empty();
        if (EmployeeData::isMinijob($employeeData)) {
            throw new InvalidArgumentException('Minijob: kein Überstundenkonto / kein Abbau.');
        }
        if (!EmployeeData::overtimeAllowed($employeeData)) {
            throw new InvalidArgumentException('Überstunden für diesen Mitarbeiter nicht freigegeben.');
        }

        $plan = self::otCompPlan($contactId, $dateFrom, $dateTo);
        OvertimeLotRepository::syncAccrualsFromWorkDays($contactId);
        $available = OvertimeLotRepository::sumRemainingMinutes($contactId);
        if ($available < $plan['total_minutes']) {
            throw new InvalidArgumentException(sprintf(
                'Überstundenkonto unzureichend: benötigt %s h, vorhanden %s h.',
                TimeClockService::formatMinutes($plan['total_minutes']),
                TimeClockService::formatMinutes($available)
            ));
        }
    }

    /**
     * FIFO-Abbuchung + Ist-Gutschrift pro Tag. Rückgabe Kurztext für Flash.
     */
    private static function applyOtCompConversion(
        int $contactId,
        string $dateFrom,
        string $dateTo,
        ?int $userId,
    ): string {
        $plan = self::otCompPlan($contactId, $dateFrom, $dateTo);
        OvertimeLotRepository::syncAccrualsFromWorkDays($contactId);
        $reduced = OvertimeLotRepository::reduceMinutesFifo($contactId, $plan['total_minutes']);
        if ($reduced < $plan['total_minutes']) {
            throw new RuntimeException(sprintf(
                'Konto-Abbuchung unvollständig (%s von %s h). Bitte manuell prüfen.',
                TimeClockService::formatMinutes($reduced),
                TimeClockService::formatMinutes($plan['total_minutes'])
            ));
        }

        OvertimeLotRepository::insertReductionAudit(
            $contactId,
            $reduced,
            'Überstundenabbau Abwesenheit ' . $dateFrom . '–' . $dateTo,
            $userId
        );

        foreach ($plan['days'] as $day) {
            $ymd = (string) ($day['date'] ?? '');
            $mins = (int) ($day['minutes'] ?? 0);
            if ($ymd === '' || $mins < 1) {
                continue;
            }
            $existing = TimeWorkDayRepository::find($contactId, $ymd);
            $worked = $existing !== null ? max(0, (int) ($existing['worked_minutes'] ?? 0)) : 0;
            $break = $existing !== null ? max(0, (int) ($existing['break_minutes'] ?? 0)) : 0;
            $newWorked = max($worked, $mins);
            TimeWorkDayRepository::upsert($contactId, $ymd, [
                'scheduled_minutes' => $mins,
                'worked_minutes' => $newWorked,
                'break_minutes' => $break,
                'overtime_minutes' => max(0, $newWorked - $mins),
                'status' => 'closed',
            ]);
        }

        return sprintf(
            '%s h vom Zeitkonto als Ist gutgeschrieben',
            TimeClockService::formatMinutes($reduced)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function requirePendingNonVacation(int $absenceId): array
    {
        $row = TimeAbsenceRepository::findById($absenceId);
        if ($row === null || (string) ($row['type'] ?? '') === 'vacation') {
            throw new InvalidArgumentException('Abwesenheitsantrag nicht gefunden.');
        }
        if ((string) ($row['status'] ?? '') !== 'requested') {
            throw new InvalidArgumentException('Nur beantragte Einträge können bestätigt werden.');
        }

        return $row;
    }
}
