<?php
declare(strict_types=1);

/** ArbZG-Ausgleich: 6-Kalendermonats-Durchschnitt wöchentliche Arbeitszeit (§3, WD 6/097/19). */
final class ArbzgComplianceService
{
    public const DEFAULT_MAX_WEEKLY_MINUTES = 2880;

    /** ArbZG §4: Verlängerung auf höchstens 10 Stunden / Tag. */
    public const MAX_DAILY_MINUTES = 600;

    /** ArbZG §5: mindestens 11 Stunden ununterbrochene Ruhezeit. */
    public const MIN_REST_MINUTES = 660;

    /** Soft-Hinweis: Wochendurchschnitt > 8 h/Tag (Kalenderwoche Mo–So). */
    public const SOFT_AVG_DAILY_MINUTES = 480;

    /**
     * Abgeschlossener 6-Monats-Zeitraum (endet am letzten Tag des Vormonats).
     *
     * @return array{from: string, to: string}|null
     */
    public static function completedEvaluationPeriod(?string $referenceDate = null): ?array
    {
        $referenceDate = $referenceDate ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $referenceDate)) {
            return null;
        }

        try {
            $ref = new DateTimeImmutable($referenceDate);
            $periodTo = $ref->modify('first day of this month')->modify('-1 day');
            $periodFrom = $periodTo->modify('first day of this month')->modify('-5 months');

            return [
                'from' => $periodFrom->format('Y-m-d'),
                'to' => $periodTo->format('Y-m-d'),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Rollierender Zeitraum der letzten N Kalendermonate bis einschließlich Referenzdatum.
     *
     * @return array{from: string, to: string, months: int}|null
     */
    public static function rollingPeriod(int $months, ?string $referenceDate = null): ?array
    {
        $months = max(1, $months);
        $referenceDate = $referenceDate ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $referenceDate)) {
            return null;
        }

        try {
            $ref = new DateTimeImmutable($referenceDate);
            $periodTo = $ref;
            $periodFrom = $ref->modify('first day of this month')->modify('-' . ($months - 1) . ' months');

            return [
                'from' => $periodFrom->format('Y-m-d'),
                'to' => $periodTo->format('Y-m-d'),
                'months' => $months,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{
     *   contact_id: int,
     *   label: string,
     *   period_from: string,
     *   period_to: string,
     *   total_minutes: int,
     *   avg_weekly_minutes: float,
     *   avg_weekly_display: string,
     *   max_weekly_minutes: int,
     *   months: int,
     *   message: string,
     *   employee_message: string
     * }|null
     */
    public static function evaluateContact(
        int $contactId,
        string $periodFrom,
        string $periodTo,
        ?string $label = null,
    ): ?array {
        if ($contactId < 1
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodFrom)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodTo)) {
            return null;
        }

        $totalMinutes = TimeWorkDayRepository::sumWorkedMinutes($contactId, $periodFrom, $periodTo);
        if ($totalMinutes < 1) {
            return null;
        }

        $weeks = self::weeksInPeriod($periodFrom, $periodTo);
        if ($weeks <= 0) {
            return null;
        }

        $avgWeeklyMinutes = $totalMinutes / $weeks;
        $maxWeeklyMinutes = self::maxWeeklyMinutes();
        if ($avgWeeklyMinutes <= $maxWeeklyMinutes) {
            return null;
        }

        $employeeLabel = $label ?? self::contactLabel($contactId);
        $months = self::monthsSpan($periodFrom, $periodTo);

        return [
            'contact_id' => $contactId,
            'label' => $employeeLabel,
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
            'total_minutes' => $totalMinutes,
            'avg_weekly_minutes' => $avgWeeklyMinutes,
            'avg_weekly_display' => TimeClockService::formatMinutes((int) round($avgWeeklyMinutes)),
            'max_weekly_minutes' => $maxWeeklyMinutes,
            'months' => $months,
            'message' => self::buildManagerMessage($employeeLabel, $months),
            'employee_message' => self::buildEmployeeMessage($months),
        ];
    }

    /**
     * Verstöße im abgeschlossenen 6-Monats-Zeitraum (für E-Mail am Monatsersten).
     *
     * @return list<array<string, mixed>>
     */
    public static function violationsForCompletedPeriod(?string $referenceDate = null): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();

        $period = self::completedEvaluationPeriod($referenceDate);
        if ($period === null) {
            return [];
        }

        $out = [];
        foreach (self::staffContacts() as $contact) {
            $contactId = (int) ($contact['id'] ?? 0);
            if ($contactId < 1) {
                continue;
            }
            $evaluation = self::evaluateContact(
                $contactId,
                $period['from'],
                $period['to'],
                (string) ($contact['label'] ?? ''),
            );
            if ($evaluation !== null) {
                $out[] = $evaluation;
            }
        }

        usort($out, static fn (array $a, array $b): int => strcasecmp(
            (string) ($a['label'] ?? ''),
            (string) ($b['label'] ?? ''),
        ));

        return $out;
    }

    /**
     * Aktuelle Verstöße (rollierend 6 Monate) für Team-UI.
     *
     * @return list<array<string, mixed>>
     */
    public static function currentViolations(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();

        $months = self::evaluationMonths();
        $period = self::rollingPeriod($months);
        if ($period === null) {
            return [];
        }

        $out = [];
        foreach (self::staffContacts() as $contact) {
            $contactId = (int) ($contact['id'] ?? 0);
            if ($contactId < 1) {
                continue;
            }
            $evaluation = self::evaluateContact(
                $contactId,
                $period['from'],
                $period['to'],
                (string) ($contact['label'] ?? ''),
            );
            if ($evaluation !== null) {
                $out[] = $evaluation;
            }
        }

        usort($out, static fn (array $a, array $b): int => strcasecmp(
            (string) ($a['label'] ?? ''),
            (string) ($b['label'] ?? ''),
        ));

        return $out;
    }

    public static function shouldSendMonthlyReminders(?string $today = null): bool
    {
        $today = $today ?? date('Y-m-d');

        return str_ends_with($today, '-01');
    }

    public static function buildManagerMessage(string $employeeLabel, int $months): string
    {
        return sprintf(
            'Durchschnittlich hat %s mehr als 48 Stunden pro Woche in den letzten %d Monaten gearbeitet. '
            . 'Die Überstunden sind dringend abzubauen, um gesetzliche Bestimmungen nach Bundestag-WD 6/097/19 zu erfüllen.',
            $employeeLabel,
            $months,
        );
    }

    public static function buildEmployeeMessage(int $months): string
    {
        return sprintf(
            'Sie haben durchschnittlich mehr als 48 Stunden pro Woche in den letzten %d Monaten gearbeitet. '
            . 'Ihre Überstunden sollen dringend abgebaut werden, um gesetzliche Bestimmungen nach Bundestag-WD 6/097/19 zu erfüllen.',
            $months,
        );
    }

    public static function maxWeeklyMinutes(): int
    {
        $cfg = TimeTrackingSettings::config();
        $hours = max(1, (int) ($cfg['arbzg_max_weekly_hours'] ?? 48));

        return min(168, $hours) * 60;
    }

    public static function evaluationMonths(): int
    {
        $cfg = TimeTrackingSettings::config();

        return max(1, (int) ($cfg['overtime_compensation_months'] ?? 6));
    }

    private static function weeksInPeriod(string $periodFrom, string $periodTo): float
    {
        try {
            $from = new DateTimeImmutable($periodFrom);
            $to = new DateTimeImmutable($periodTo);
            $days = (int) $from->diff($to)->days + 1;

            return max(1.0, $days / 7.0);
        } catch (Throwable) {
            return 0.0;
        }
    }

    private static function monthsSpan(string $periodFrom, string $periodTo): int
    {
        try {
            $from = new DateTimeImmutable($periodFrom);
            $to = new DateTimeImmutable($periodTo);
            $months = ((int) $to->format('Y') - (int) $from->format('Y')) * 12
                + ((int) $to->format('n') - (int) $from->format('n')) + 1;

            return max(1, $months);
        } catch (Throwable) {
            return self::evaluationMonths();
        }
    }

    private static function contactLabel(int $contactId): string
    {
        $contact = ContactRepository::findById($contactId);
        if ($contact === null) {
            return 'Mitarbeiter #' . $contactId;
        }
        $label = trim($contact->displayName);
        if ($label === '') {
            $label = trim($contact->companyName);
        }

        return $label !== '' ? $label : 'Mitarbeiter #' . $contactId;
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private static function staffContacts(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query(
            "SELECT id, display_name, company_name
             FROM dg_contacts
             WHERE contact_role IN ('dg_eigenmitarbeiter', 'administrator', 'mitarbeiter')
             ORDER BY display_name ASC, company_name ASC, id ASC"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['display_name'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($row['company_name'] ?? ''));
            }
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'label' => $label !== '' ? $label : 'Mitarbeiter #' . (int) ($row['id'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Z2d: Soft-Warnungen (kein Hard-Block) — Ruhezeit 11 h, max. 10 h/Tag, Ø-Woche 8 h.
     *
     * @param list<array<string, mixed>> $dayEvents Events des Tages (optional, sonst DB)
     * @return list<string>
     */
    public static function daySoftWarnings(int $contactId, string $date, int $netWorkedMinutes, array $dayEvents = []): array
    {
        if ($contactId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return [];
        }

        $warnings = [];

        if ($netWorkedMinutes > self::MAX_DAILY_MINUTES) {
            $warnings[] = sprintf(
                'ArbZG: Tagesarbeitszeit über 10 h (%s h) — bitte prüfen (Soft-Hinweis, kein Block).',
                TimeClockService::formatMinutes($netWorkedMinutes)
            );
        }

        $rest = self::restPeriodMinutes($contactId, $date, $dayEvents);
        if ($rest !== null && $rest < self::MIN_REST_MINUTES) {
            $warnings[] = sprintf(
                'ArbZG: Ruhezeit unter 11 h (%s h seit letztem Ausstempeln) — Soft-Hinweis, kein Block.',
                TimeClockService::formatMinutes($rest)
            );
        }

        $weekAvg = self::calendarWeekAverageDailyMinutes($contactId, $date);
        if ($weekAvg !== null && $weekAvg > self::SOFT_AVG_DAILY_MINUTES) {
            $warnings[] = sprintf(
                'ArbZG: Wochendurchschnitt bisher über 8 h/Tag (Ø %s h, Kalenderwoche) — Ausgleich im Blick behalten.',
                TimeClockService::formatMinutes((int) round($weekAvg))
            );
        }

        return $warnings;
    }

    /**
     * Minuten zwischen letztem clock_out und erstem clock_in des Tages; null wenn nicht prüfbar.
     *
     * @param list<array<string, mixed>> $dayEvents
     */
    public static function restPeriodMinutes(int $contactId, string $date, array $dayEvents = []): ?int
    {
        if ($dayEvents === []) {
            $dayEvents = TimeClockRepository::eventsForContact($contactId, $date);
        }
        $firstIn = null;
        foreach ($dayEvents as $event) {
            if ((string) ($event['event_type'] ?? '') === TimeClockRepository::EVENT_CLOCK_IN) {
                $firstIn = (string) ($event['occurred_at'] ?? '');
                break;
            }
        }
        if ($firstIn === null || $firstIn === '') {
            return null;
        }

        $prevOut = TimeClockRepository::lastEventBefore(
            $contactId,
            TimeClockRepository::EVENT_CLOCK_OUT,
            $firstIn
        );
        if ($prevOut === null) {
            return null;
        }
        $outAt = (string) ($prevOut['occurred_at'] ?? '');
        if ($outAt === '') {
            return null;
        }

        try {
            $a = new DateTimeImmutable($outAt);
            $b = new DateTimeImmutable($firstIn);
        } catch (Throwable) {
            return null;
        }
        $diff = $b->getTimestamp() - $a->getTimestamp();
        if ($diff < 0) {
            return null;
        }

        return (int) floor($diff / 60);
    }

    /**
     * Ø Tagesminuten in der Kalenderwoche (Mo–So) bis einschließlich $date.
     * Basis: aggregierte Tage + Live-Summe für Tage ohne Aggregation.
     */
    public static function calendarWeekAverageDailyMinutes(int $contactId, string $date): ?float
    {
        if ($contactId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !Database::isConfigured()) {
            return null;
        }

        try {
            $ref = new DateTimeImmutable($date);
            $n = (int) $ref->format('N');
            $monday = $ref->modify('-' . ($n - 1) . ' days');
        } catch (Throwable) {
            return null;
        }

        $from = $monday->format('Y-m-d');
        $to = $date;
        $aggregated = [];
        foreach (TimeWorkDayRepository::listForContactRange($contactId, $from, $to) as $row) {
            $d = (string) ($row['work_date'] ?? '');
            if ($d !== '') {
                $aggregated[$d] = max(0, (int) ($row['worked_minutes'] ?? 0));
            }
        }

        $total = 0;
        $daysCounted = 0;
        $cursor = $monday;
        while ($cursor->format('Y-m-d') <= $to) {
            $d = $cursor->format('Y-m-d');
            if (isset($aggregated[$d])) {
                $mins = $aggregated[$d];
            } else {
                $mins = self::netWorkedMinutesFromEvents(TimeClockRepository::eventsForContact($contactId, $d));
            }
            if ($mins > 0) {
                $total += $mins;
                $daysCounted++;
            }
            $cursor = $cursor->modify('+1 day');
        }

        if ($daysCounted < 1) {
            return null;
        }

        return $total / $daysCounted;
    }

    /**
     * @param list<array<string, mixed>> $events
     */
    private static function netWorkedMinutesFromEvents(array $events): int
    {
        if ($events === []) {
            return 0;
        }
        $segments = TimeClockService::computeSegments($events);
        $gross = (int) ($segments['worked_minutes'] ?? 0);
        $manualBreak = (int) ($segments['break_minutes'] ?? 0);
        $autoBreak = 0;
        if (TimeTrackingSettings::config()['auto_break_enabled'] ?? true) {
            $autoBreak = TimeClockService::autoBreakMinutes($gross, $manualBreak);
        }

        return max(0, $gross - $autoBreak);
    }
}
