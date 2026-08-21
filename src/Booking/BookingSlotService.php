<?php
declare(strict_types=1);

/** Verfügbare Buchungsslots (Leistungen, globale Arbeitszeiten, Mitarbeiter, Belegungen). */
final class BookingSlotService
{
    public const DEFAULT_DURATION_MINUTES = 15;

    /** @var list<string> */
    private const CANCELLED_STATUSES = ['storniert', 'cancelled', 'canceled', 'cancel'];

    /**
     * Methode slot step minutes.
     * @return int
     */
    public static function slotStepMinutes(): int
    {
        return CalendarWorkingHoursRepository::slotStepMinutes();
    }

    /**
     * Führt aus: resolve duration minutes.
     * @param int $articleId
     * @return int
     */
    public static function resolveDurationMinutes(int $articleId): int
    {
        if ($articleId > 0) {
            $minutes = CalendarArticleRepository::getWorkMinutes($articleId);
            if ($minutes > 0) {
                return $minutes;
            }
        }

        return self::DEFAULT_DURATION_MINUTES;
    }

    /**
     * Methode assert bookable.
     * @param string $slotDatetime
     * @param int $articleId
     * @param int $employeeId
     * @param int|null $excludeBookingId
     * @return void
     * @throws InvalidArgumentException
     */
    public static function assertBookable(
        string $slotDatetime,
        int $articleId = 0,
        int $employeeId = 0,
        ?int $excludeBookingId = null,
    ): void {
        if (!self::isPeriodAvailable($slotDatetime, $articleId, $employeeId, $excludeBookingId)) {
            throw new InvalidArgumentException('Der gewählte Termin ist nicht verfügbar (außerhalb der Arbeitszeiten oder bereits belegt).');
        }
    }

    /**
     * Methode slots for date.
     * @param string $dateYmd
     * @param int $articleId
     * @param int $employeeId
     * @param int|null $excludeBookingId
     * @return array<string, mixed>
     */
    public static function slotsForDate(
        string $dateYmd,
        int $articleId = 0,
        int $employeeId = 0,
        ?int $excludeBookingId = null,
    ): array {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            return [];
        }

        $durationMinutes = self::resolveDurationMinutes($articleId);
        $day = self::dayStart($dateYmd);
        $windows = self::dayWindows($day, $articleId, $employeeId);
        if ($windows === []) {
            return [];
        }

        $slots = [];
        $now = new DateTimeImmutable('now', self::timezone());

        foreach ($windows as [$windowStart, $windowEnd]) {
            $latestStart = $windowEnd->modify('-' . $durationMinutes . ' minutes');
            if ($latestStart < $windowStart) {
                continue;
            }

            $cursor = $windowStart;
            while ($cursor <= $latestStart) {
                $formatted = self::normalizeSlotDatetime($cursor->format('Y-m-d H:i:s'));
                if ($cursor > $now && self::isPeriodAvailable($formatted, $articleId, $employeeId, $excludeBookingId)) {
                    $slots[] = $formatted;
                }
                $cursor = $cursor->modify('+' . self::slotStepMinutes() . ' minutes');
            }
        }

        return $slots;
    }

    /**
     * Berechnet verfügbare Zeitslots.
     * @param int $articleId
     * @param int $employeeId
     * @param int $daysAhead
     * @param int|null $excludeBookingId
     * @return array<string, mixed>
     */
    public static function availableSlots(
        int $articleId = 0,
        int $employeeId = 0,
        int $daysAhead = 365,
        ?int $excludeBookingId = null,
    ): array {
        $daysAhead = max(1, min(366, $daysAhead));
        $today = new DateTimeImmutable('today', self::timezone());
        $grouped = [];

        for ($offset = 0; $offset < $daysAhead; $offset++) {
            $day = $today->modify('+' . $offset . ' days');
            $dateYmd = $day->format('Y-m-d');
            $daily = self::slotsForDate($dateYmd, $articleId, $employeeId, $excludeBookingId);
            if ($daily !== []) {
                $grouped[self::dateLabel($day)] = $daily;
            }
        }

        return $grouped;
    }

    /**
     * Prüft: is period available.
     * @param string $slotDatetime
     * @param int $articleId
     * @param int $employeeId
     * @param int|null $excludeBookingId
     * @return bool
     */
    public static function isPeriodAvailable(
        string $slotDatetime,
        int $articleId = 0,
        int $employeeId = 0,
        ?int $excludeBookingId = null,
    ): bool {
        $durationMinutes = self::resolveDurationMinutes($articleId);
        $normalized = self::normalizeSlotDatetime($slotDatetime);
        $slotStart = self::parseSlotDatetime($normalized);
        if ($slotStart === null || $durationMinutes < 1) {
            return false;
        }

        $slotEnd = $slotStart->modify('+' . $durationMinutes . ' minutes');
        $now = new DateTimeImmutable('now', self::timezone());
        if ($slotStart <= $now) {
            return false;
        }

        if ($articleId > 0 && CalendarStaffRepository::usesEmployeeSchedulingForArticle($articleId)) {
            if ($employeeId > 0) {
                if (!in_array($employeeId, CalendarStaffRepository::getQualifiedEmployeeIdsForArticle($articleId), true)) {
                    return false;
                }
                if (!CalendarStaffRepository::isEmployeeSchedulableAt($employeeId, $normalized, $durationMinutes)) {
                    return false;
                }
            } elseif (!self::slotFitsArticleEmployeeWindows($slotStart, $slotEnd, $articleId)) {
                return false;
            }
        } elseif ($employeeId > 0) {
            if (!CalendarStaffRepository::isEmployeeSchedulableAt($employeeId, $normalized, $durationMinutes)) {
                return false;
            }
        } elseif (!self::slotFitsGlobalHours($slotStart, $slotEnd)) {
            return false;
        }

        return !self::hasBookingConflict($normalized, $durationMinutes, $articleId, $employeeId, $excludeBookingId);
    }

    /**
     * Führt aus: normalize slot datetime.
     * @param string $value
     * @return string
     */
    public static function normalizeSlotDatetime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = str_replace('T', ' ', $value);
        if (preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $matches)) {
            $total = ((int) $matches[2] * 60) + (int) $matches[3];
            $step = self::slotStepMinutes();
            $snapped = (int) round($total / $step) * $step;
            if ($snapped >= 24 * 60) {
                $snapped = 24 * 60 - $step;
            }

            return sprintf(
                '%s %02d:%02d:00',
                $matches[1],
                intdiv($snapped, 60),
                $snapped % 60
            );
        }

        $ts = strtotime($value);

        return $ts ? date('Y-m-d H:i:s', $ts) : '';
    }

    /**
     * Methode slot fits global hours.
     * @param DateTimeImmutable $slotStart
     * @param DateTimeImmutable $slotEnd
     * @return bool
     */
    private static function slotFitsGlobalHours(DateTimeImmutable $slotStart, DateTimeImmutable $slotEnd): bool
    {
        $hours = CalendarWorkingHoursRepository::getForDate($slotStart->format('Y-m-d'));
        if (!CalendarWorkingHoursRepository::isWorkingWeekday($slotStart, $hours['weekdays'])) {
            return false;
        }

        $bounds = self::globalDayBounds($slotStart, $hours);
        if ($bounds === null) {
            return false;
        }

        [$dayStart, $dayEnd] = $bounds;

        return $slotStart >= $dayStart && $slotEnd <= $dayEnd;
    }

    /**
     * Slot Fits Article Employee Windows.
     * @param DateTimeImmutable $slotStart
     * @param DateTimeImmutable $slotEnd
     * @param int $articleId
     * @return bool
     */
    private static function slotFitsArticleEmployeeWindows(
        DateTimeImmutable $slotStart,
        DateTimeImmutable $slotEnd,
        int $articleId,
    ): bool {
        $windows = CalendarStaffRepository::getMergedDayWindowsForArticle($articleId, $slotStart);
        foreach ($windows as [$windowStart, $windowEnd]) {
            if ($slotStart >= $windowStart && $slotEnd <= $windowEnd) {
                return true;
            }
        }

        return false;
    }

    /**
     * Methode global day bounds.
     * @param DateTimeImmutable $day
     * @param array $workingHours
     * @return array|null
     */
    private static function globalDayBounds(DateTimeImmutable $day, array $workingHours): ?array
    {
        $start = self::parseClockTime((string) ($workingHours['start_time'] ?? ''));
        $end = self::parseClockTime((string) ($workingHours['end_time'] ?? ''));

        $dayStart = $day->setTime($start['hour'], $start['minute'], 0);
        $dayEnd = $day->setTime($end['hour'], $end['minute'], 0);

        if ($dayEnd <= $dayStart) {
            return null;
        }

        return [$dayStart, $dayEnd];
    }

    /**
     * Methode day windows.
     * @param DateTimeImmutable $day
     * @param int $articleId
     * @param int $employeeId
     * @return array<string, mixed>
     */
    private static function dayWindows(DateTimeImmutable $day, int $articleId, int $employeeId): array
    {
        if ($articleId > 0 && CalendarStaffRepository::usesEmployeeSchedulingForArticle($articleId)) {
            if ($employeeId > 0) {
                $employee = CalendarStaffRepository::getEmployeeById($employeeId);
                if ($employee === null || (int) ($employee['is_active'] ?? 0) !== 1) {
                    return [];
                }
                if (!in_array($employeeId, CalendarStaffRepository::getQualifiedEmployeeIdsForArticle($articleId), true)) {
                    return [];
                }

                return CalendarStaffRepository::getEmployeeDayWindows($employeeId, $day);
            }

            return CalendarStaffRepository::getMergedDayWindowsForArticle($articleId, $day);
        }

        if ($employeeId > 0) {
            $employee = CalendarStaffRepository::getEmployeeById($employeeId);
            if ($employee === null || (int) ($employee['is_active'] ?? 0) !== 1) {
                return [];
            }

            return CalendarStaffRepository::getEmployeeDayWindows($employeeId, $day);
        }

        $hours = CalendarWorkingHoursRepository::getForDate($day->format('Y-m-d'));
        if (!CalendarWorkingHoursRepository::isWorkingWeekday($day, $hours['weekdays'])) {
            return [];
        }

        $bounds = self::globalDayBounds($day, $hours);

        return $bounds !== null ? [$bounds] : [];
    }

    /**
     * Prüft: has booking conflict.
     * @param string $slotNormalized
     * @param int $durationMinutes
     * @param int $articleId
     * @param int $employeeId
     * @param int|null $excludeBookingId
     * @return bool
     */
    private static function hasBookingConflict(
        string $slotNormalized,
        int $durationMinutes,
        int $articleId,
        int $employeeId,
        ?int $excludeBookingId,
    ): bool {
        if ($articleId > 0
            && CalendarStaffRepository::usesEmployeeSchedulingForArticle($articleId)
            && $employeeId < 1
        ) {
            foreach (CalendarStaffRepository::getQualifiedEmployeeIdsForArticle($articleId) as $qualifiedId) {
                if (!self::hasBookingConflictForEmployee($slotNormalized, $durationMinutes, $qualifiedId, $excludeBookingId)) {
                    return false;
                }
            }

            return CalendarStaffRepository::getQualifiedEmployeeIdsForArticle($articleId) !== [];
        }

        return self::hasBookingConflictForEmployee($slotNormalized, $durationMinutes, $employeeId, $excludeBookingId);
    }

    /**
     * Prüft: has booking conflict for employee.
     * @param string $slotNormalized
     * @param int $durationMinutes
     * @param int $employeeId
     * @param int|null $excludeBookingId
     * @return bool
     */
    private static function hasBookingConflictForEmployee(
        string $slotNormalized,
        int $durationMinutes,
        int $employeeId,
        ?int $excludeBookingId,
    ): bool {
        if (!Database::isConfigured()) {
            return false;
        }

        $start = self::parseSlotDatetime($slotNormalized);
        if ($start === null) {
            return true;
        }

        $end = $start->modify('+' . $durationMinutes . ' minutes');
        $cancelledPlaceholders = [];
        $params = [
            'slot_end' => $end->format('Y-m-d H:i:s'),
            'default_duration' => self::DEFAULT_DURATION_MINUTES,
            'slot_start' => $start->format('Y-m-d H:i:s'),
        ];
        foreach (self::CANCELLED_STATUSES as $index => $status) {
            $key = 'cancelled_' . $index;
            $cancelledPlaceholders[] = ':' . $key;
            $params[$key] = $status;
        }

        $sql = 'SELECT b.id FROM dg_bookings b
                LEFT JOIN dg_calendar_articles a ON a.id = b.article_id
                WHERE LOWER(b.status) NOT IN (' . implode(', ', $cancelledPlaceholders) . ')
                AND b.slot_datetime < :slot_end
                AND DATE_ADD(b.slot_datetime, INTERVAL COALESCE(NULLIF(a.work_minutes, 0), :default_duration) MINUTE) > :slot_start';

        if ($employeeId > 0) {
            $sql .= ' AND (b.employee_id = 0 OR b.employee_id = :employee_id)';
            $params['employee_id'] = $employeeId;
        }

        if ($excludeBookingId !== null && $excludeBookingId > 0) {
            $sql .= ' AND b.id <> :exclude_id';
            $params['exclude_id'] = $excludeBookingId;
        }

        $sql .= ' LIMIT 1';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Führt aus: parse slot datetime.
     * @param string $value
     * @return DateTimeImmutable|null
     */
    private static function parseSlotDatetime(string $value): ?DateTimeImmutable
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2}) (\d{1,2}):(\d{2}):(\d{2})$/', $value, $matches)) {
            return null;
        }

        $dt = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            sprintf(
                '%04d-%02d-%02d %02d:%02d:%02d',
                (int) $matches[1],
                (int) $matches[2],
                (int) $matches[3],
                (int) $matches[4],
                (int) $matches[5],
                (int) $matches[6]
            ),
            self::timezone()
        );

        return $dt ?: null;
    }

    /**
     * Führt aus: parse clock time.
     * @param string $timeValue
     * @return array<string, mixed>
     */
    private static function parseClockTime(string $timeValue): array
    {
        if (preg_match('/^(\d{1,2}):(\d{2})/', trim($timeValue), $matches)) {
            return [
                'hour' => max(0, min(23, (int) $matches[1])),
                'minute' => max(0, min(59, (int) $matches[2])),
            ];
        }

        return ['hour' => 9, 'minute' => 0];
    }

    /**
     * Methode day start.
     * @param string $dateYmd
     * @return DateTimeImmutable
     */
    private static function dayStart(string $dateYmd): DateTimeImmutable
    {
        return new DateTimeImmutable($dateYmd . ' 00:00:00', self::timezone());
    }

    /**
     * Methode date label.
     * @param DateTimeImmutable $day
     * @return string
     */
    private static function dateLabel(DateTimeImmutable $day): string
    {
        $labels = CalendarStaffRepository::weekdayLabels();
        $weekday = (int) $day->format('N');

        return ($labels[$weekday] ?? $day->format('l')) . ', ' . $day->format('d.m.Y');
    }

    /**
     * Methode timezone.
     * @return DateTimeZone
     */
    private static function timezone(): DateTimeZone
    {
        return new DateTimeZone((string) App::config('timezone', 'Europe/Berlin'));
    }
}
