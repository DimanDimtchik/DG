<?php
declare(strict_types=1);

/** Stempeluhr: Status, Tagesberechnung, Teamübersicht. */
final class TimeClockService
{
    /**
     * @throws InvalidArgumentException
     */
    public static function recordEvent(int $contactId, string $eventType, ?User $actor = null): void
    {
        if ($contactId < 1) {
            throw new InvalidArgumentException('Kein Mitarbeiter-Kontakt verknüpft. Bitte E-Mail/Login im Kontakt hinterlegen.');
        }

        $eventType = self::sanitizeEventType($eventType);
        $status = self::currentStatus($contactId);
        self::assertTransitionAllowed($status, $eventType);

        $now = date('Y-m-d H:i:s');
        TimeClockRepository::insert(
            $contactId,
            $eventType,
            $now,
            TimeClockRepository::SOURCE_WEB,
            null,
            $actor?->id,
        );
    }

    /**
     * @return array{
     *   state: string,
     *   since: string|null,
     *   since_display: string|null,
     *   label: string
     * }
     */
    public static function currentStatus(int $contactId): array
    {
        $today = date('Y-m-d');
        $events = TimeClockRepository::eventsForContact($contactId, $today);
        if ($events === []) {
            return [
                'state' => 'off',
                'since' => null,
                'since_display' => null,
                'label' => 'Nicht eingestempelt',
            ];
        }

        $last = $events[count($events) - 1];
        $type = (string) ($last['event_type'] ?? '');
        $since = (string) ($last['occurred_at'] ?? '');

        return match ($type) {
            TimeClockRepository::EVENT_CLOCK_IN => [
                'state' => 'working',
                'since' => $since,
                'since_display' => self::formatDateTimeGerman($since),
                'label' => 'Eingestempelt',
            ],
            TimeClockRepository::EVENT_BREAK_START => [
                'state' => 'break',
                'since' => $since,
                'since_display' => self::formatDateTimeGerman($since),
                'label' => 'Pause',
            ],
            TimeClockRepository::EVENT_BREAK_END => [
                'state' => 'working',
                'since' => $since,
                'since_display' => self::formatDateTimeGerman($since),
                'label' => 'Eingestempelt (nach Pause)',
            ],
            default => [
                'state' => 'off',
                'since' => null,
                'since_display' => null,
                'label' => 'Ausgestempelt',
            ],
        };
    }

    /**
     * @return array{
     *   events: list<array<string, mixed>>,
     *   worked_minutes: int,
     *   break_minutes: int,
     *   scheduled_minutes: int,
     *   worked_display: string,
     *   break_display: string,
     *   scheduled_display: string,
     *   warnings: list<string>,
     *   status: array<string, mixed>
     * }
     */
    public static function daySummary(int $contactId, ?string $date = null): array
    {
        $date = $date ?? date('Y-m-d');
        $events = TimeClockRepository::eventsForContact($contactId, $date);
        $employeeData = self::employeeDataForContact($contactId);
        $scheduled = EmployeeData::dailyTargetMinutes($employeeData);

        $segments = self::computeSegments($events);
        $manualBreak = $segments['break_minutes'];
        $grossWorked = $segments['worked_minutes'];

        $autoBreak = 0;
        if (TimeTrackingSettings::config()['auto_break_enabled'] ?? true) {
            $autoBreak = self::autoBreakMinutes($grossWorked, $manualBreak);
        }
        $totalBreak = $manualBreak + $autoBreak;
        $netWorked = max(0, $grossWorked - $autoBreak);

        $warnings = [];
        if (EmployeeData::isMinijob($employeeData) && $netWorked > $scheduled) {
            $warnings[] = 'Minijob: Überstunden sind nicht vorgesehen.';
        }
        if (!EmployeeData::overtimeAllowed($employeeData) && $netWorked > $scheduled) {
            $warnings[] = 'Überstunden sind für diesen Mitarbeiter nicht freigegeben.';
        }

        $displayEvents = $events;
        foreach ($displayEvents as &$event) {
            $event['event_label'] = self::eventLabel((string) ($event['event_type'] ?? ''));
            $event['occurred_display'] = self::formatDateTimeGerman((string) ($event['occurred_at'] ?? ''));
        }
        unset($event);

        return [
            'events' => $displayEvents,
            'worked_minutes' => $netWorked,
            'break_minutes' => $totalBreak,
            'scheduled_minutes' => $scheduled,
            'worked_display' => self::formatMinutes($netWorked),
            'break_display' => self::formatMinutes($totalBreak),
            'scheduled_display' => self::formatMinutes($scheduled),
            'warnings' => $warnings,
            'status' => self::currentStatus($contactId),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function teamToday(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();

        $today = date('Y-m-d');
        $contacts = self::staffContacts();
        if ($contacts === []) {
            return [];
        }

        $contactIds = array_map(static fn (array $c): int => (int) ($c['id'] ?? 0), $contacts);
        $events = TimeClockRepository::eventsForContactsOnDate($contactIds, $today);
        $byContact = [];
        foreach ($events as $event) {
            $cid = (int) ($event['contact_id'] ?? 0);
            $byContact[$cid][] = $event;
        }

        $out = [];
        foreach ($contacts as $contact) {
            $contactId = (int) ($contact['id'] ?? 0);
            $contactEvents = $byContact[$contactId] ?? [];
            $status = self::statusFromEvents($contactEvents);
            $segments = self::computeSegments($contactEvents);
            $employeeData = is_array($contact['employee_data'] ?? null)
                ? $contact['employee_data']
                : [];
            $scheduled = EmployeeData::dailyTargetMinutes($employeeData);
            $autoBreak = 0;
            if (TimeTrackingSettings::config()['auto_break_enabled'] ?? true) {
                $autoBreak = self::autoBreakMinutes($segments['worked_minutes'], $segments['break_minutes']);
            }
            $netWorked = max(0, $segments['worked_minutes'] - $autoBreak);

            $out[] = [
                'contact_id' => $contactId,
                'label' => (string) ($contact['label'] ?? ''),
                'status_state' => $status['state'],
                'status_label' => $status['label'],
                'since_display' => $status['since_display'],
                'worked_display' => self::formatMinutes($netWorked),
                'scheduled_display' => self::formatMinutes($scheduled),
            ];
        }

        usort($out, static function (array $a, array $b): int {
            $rank = ['working' => 0, 'break' => 1, 'off' => 2];
            $ra = $rank[$a['status_state'] ?? 'off'] ?? 3;
            $rb = $rank[$b['status_state'] ?? 'off'] ?? 3;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        return $out;
    }

    public static function canViewTeam(User $user): bool
    {
        if (RoleResolver::isAdmin($user)) {
            return true;
        }
        if (DepartmentAccess::userInHrDepartment($user)) {
            return true;
        }

        return DepartmentAccess::moduleLevel($user, 'zeiterfassung') === 'full';
    }

    /**
     * @param array{state: string} $status
     */
    private static function assertTransitionAllowed(array $status, string $eventType): void
    {
        $state = (string) ($status['state'] ?? 'off');
        $allowed = match ($state) {
            'off' => [TimeClockRepository::EVENT_CLOCK_IN],
            'working' => [TimeClockRepository::EVENT_CLOCK_OUT, TimeClockRepository::EVENT_BREAK_START],
            'break' => [TimeClockRepository::EVENT_BREAK_END],
            default => [],
        };

        if (!in_array($eventType, $allowed, true)) {
            throw new InvalidArgumentException('Diese Stempelaktion ist im aktuellen Status nicht möglich.');
        }
    }

    private static function sanitizeEventType(string $type): string
    {
        $type = strtolower(trim($type));
        $valid = [
            TimeClockRepository::EVENT_CLOCK_IN,
            TimeClockRepository::EVENT_CLOCK_OUT,
            TimeClockRepository::EVENT_BREAK_START,
            TimeClockRepository::EVENT_BREAK_END,
        ];

        if (!in_array($type, $valid, true)) {
            throw new InvalidArgumentException('Ungültige Stempelaktion.');
        }

        return $type;
    }

    /**
     * @param list<array<string, mixed>> $events
     * @return array{worked_minutes: int, break_minutes: int}
     */
    private static function computeSegments(array $events): array
    {
        $worked = 0;
        $break = 0;
        $clockIn = null;
        $breakStart = null;

        foreach ($events as $event) {
            $type = (string) ($event['event_type'] ?? '');
            $at = self::parseDateTime((string) ($event['occurred_at'] ?? ''));
            if ($at === null) {
                continue;
            }

            if ($type === TimeClockRepository::EVENT_CLOCK_IN) {
                $clockIn = $at;
                $breakStart = null;
            } elseif ($type === TimeClockRepository::EVENT_BREAK_START && $clockIn !== null) {
                $worked += max(0, (int) floor(($at->getTimestamp() - $clockIn->getTimestamp()) / 60));
                $clockIn = null;
                $breakStart = $at;
            } elseif ($type === TimeClockRepository::EVENT_BREAK_END && $breakStart !== null) {
                $break += max(0, (int) floor(($at->getTimestamp() - $breakStart->getTimestamp()) / 60));
                $breakStart = null;
                $clockIn = $at;
            } elseif ($type === TimeClockRepository::EVENT_CLOCK_OUT) {
                if ($clockIn !== null) {
                    $worked += max(0, (int) floor(($at->getTimestamp() - $clockIn->getTimestamp()) / 60));
                    $clockIn = null;
                } elseif ($breakStart !== null) {
                    $break += max(0, (int) floor(($at->getTimestamp() - $breakStart->getTimestamp()) / 60));
                    $breakStart = null;
                }
            }
        }

        if ($clockIn !== null) {
            $now = new DateTimeImmutable();
            $worked += max(0, (int) floor(($now->getTimestamp() - $clockIn->getTimestamp()) / 60));
        }
        if ($breakStart !== null) {
            $now = new DateTimeImmutable();
            $break += max(0, (int) floor(($now->getTimestamp() - $breakStart->getTimestamp()) / 60));
        }

        return ['worked_minutes' => $worked, 'break_minutes' => $break];
    }

    private static function autoBreakMinutes(int $grossWorkedMinutes, int $manualBreakMinutes): int
    {
        $cfg = TimeTrackingSettings::config();
        $threshold6 = (int) ($cfg['break_threshold_6h_minutes'] ?? 360);
        $threshold9 = (int) ($cfg['break_threshold_9h_minutes'] ?? 540);
        $break6 = (int) ($cfg['break_after_6h_minutes'] ?? 30);
        $break9 = (int) ($cfg['break_after_9h_minutes'] ?? 45);

        $required = 0;
        if ($grossWorkedMinutes >= $threshold9) {
            $required = $break9;
        } elseif ($grossWorkedMinutes >= $threshold6) {
            $required = $break6;
        }

        return max(0, $required - $manualBreakMinutes);
    }

    /**
     * @param list<array<string, mixed>> $events
     * @return array{state: string, since: string|null, since_display: string|null, label: string}
     */
    private static function statusFromEvents(array $events): array
    {
        if ($events === []) {
            return [
                'state' => 'off',
                'since' => null,
                'since_display' => null,
                'label' => 'Nicht eingestempelt',
            ];
        }

        $last = $events[count($events) - 1];
        $type = (string) ($last['event_type'] ?? '');
        $since = (string) ($last['occurred_at'] ?? '');

        return match ($type) {
            TimeClockRepository::EVENT_CLOCK_IN, TimeClockRepository::EVENT_BREAK_END => [
                'state' => 'working',
                'since' => $since,
                'since_display' => self::formatDateTimeGerman($since),
                'label' => 'Eingestempelt',
            ],
            TimeClockRepository::EVENT_BREAK_START => [
                'state' => 'break',
                'since' => $since,
                'since_display' => self::formatDateTimeGerman($since),
                'label' => 'Pause',
            ],
            default => [
                'state' => 'off',
                'since' => null,
                'since_display' => null,
                'label' => 'Ausgestempelt',
            ],
        };
    }

    /**
     * @return list<array{id: int, label: string, employee_data: array<string, string>}>
     */
    private static function staffContacts(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query(
            "SELECT id, display_name, company_name, supplier_name, employee_data
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
            $rawEmployee = $row['employee_data'] ?? '';
            $employeeData = [];
            if (is_string($rawEmployee) && $rawEmployee !== '') {
                $decoded = json_decode($rawEmployee, true);
                if (is_array($decoded)) {
                    $employeeData = EmployeeData::sanitize($decoded);
                }
            } elseif (is_array($rawEmployee)) {
                $employeeData = EmployeeData::sanitize($rawEmployee);
            }

            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'label' => $label !== '' ? $label : 'Mitarbeiter #' . (int) ($row['id'] ?? 0),
                'employee_data' => $employeeData,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private static function employeeDataForContact(int $contactId): array
    {
        $contact = ContactRepository::findById($contactId);
        if ($contact === null) {
            return EmployeeData::empty();
        }

        $raw = $contact->employeeData ?? [];
        return is_array($raw) ? EmployeeData::sanitize($raw) : EmployeeData::empty();
    }

    private static function eventLabel(string $type): string
    {
        return match ($type) {
            TimeClockRepository::EVENT_CLOCK_IN => 'Einstempeln',
            TimeClockRepository::EVENT_CLOCK_OUT => 'Ausstempeln',
            TimeClockRepository::EVENT_BREAK_START => 'Pause beginnen',
            TimeClockRepository::EVENT_BREAK_END => 'Pause beenden',
            default => $type,
        };
    }

    public static function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return sprintf('%d:%02d', $hours, $mins);
    }

    private static function formatDateTimeGerman(string $value): string
    {
        $ts = strtotime($value);

        return $ts !== false ? date('d.m.Y H:i', $ts) : $value;
    }

    private static function parseDateTime(string $value): ?DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
