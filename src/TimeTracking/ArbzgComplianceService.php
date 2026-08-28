<?php
declare(strict_types=1);

/** ArbZG-Ausgleich: 6-Kalendermonats-Durchschnitt wöchentliche Arbeitszeit (§3, WD 6/097/19). */
final class ArbzgComplianceService
{
    public const DEFAULT_MAX_WEEKLY_MINUTES = 2880;

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
            "SELECT id, display_name, company_name, supplier_name
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
            if ($label === '') {
                $label = trim((string) ($row['supplier_name'] ?? ''));
            }
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'label' => $label !== '' ? $label : 'Mitarbeiter #' . (int) ($row['id'] ?? 0),
            ];
        }

        return $out;
    }
}
