<?php
declare(strict_types=1);

/** Mitarbeiter-App-API `/api/mobile/staff/*`. */
final class MobileStaffApi
{
    /**
     * @param list<string> $parts
     * @return never
     */
    public static function handle(array $parts): void
    {
        $auth = MobileAuthService::requireAuth('staff');
        $contactId = (int) $auth['contact_id'];
        $head = $parts[0] ?? '';
        $sub = $parts[1] ?? '';

        if ($head === 'profile' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
            MobileApi::ok(self::profile($contactId));
        }

        if ($head === 'clock' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
            MobileApi::ok([
                'status' => TimeClockService::currentStatus($contactId),
                'summary' => TimeClockService::daySummary($contactId),
            ]);
        }

        if ($head === 'clock' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $body = MobileApi::jsonBody();
            $event = (string) ($body['event_type'] ?? '');
            TimeClockService::recordEvent(
                $contactId,
                $event,
                null,
                TimeClockRepository::SOURCE_MOBILE
            );
            MobileApi::ok([
                'status' => TimeClockService::currentStatus($contactId),
                'summary' => TimeClockService::daySummary($contactId),
            ]);
        }

        if ($head === 'konto' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
            OvertimeLotRepository::syncAccrualsFromWorkDays($contactId);
            $balance = OvertimeLotRepository::sumRemainingMinutes($contactId);
            $lots = [];
            foreach (OvertimeLotRepository::listOpenLots($contactId) as $lot) {
                if (!is_array($lot)) {
                    continue;
                }
                $lots[] = [
                    'accrued_date' => (string) ($lot['accrued_date'] ?? ''),
                    'minutes_remaining' => (int) ($lot['minutes_remaining'] ?? 0),
                    'remaining_display' => TimeClockService::formatMinutes((int) ($lot['minutes_remaining'] ?? 0)),
                    'expires_at' => (string) ($lot['expires_at'] ?? ''),
                ];
            }
            MobileApi::ok([
                'balance_minutes' => $balance,
                'balance_display' => TimeClockService::formatMinutes($balance),
                'lots' => $lots,
            ]);
        }

        if ($head === 'absences' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
            $year = max(2000, min(2100, (int) ($_GET['year'] ?? date('Y'))));
            $rows = [];
            foreach (TimeAbsenceRepository::listForContact($contactId, $year) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $rows[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'type' => (string) ($row['type'] ?? ''),
                    'type_label' => TimeAbsenceService::typeLabel((string) ($row['type'] ?? '')),
                    'date_from' => (string) ($row['date_from'] ?? ''),
                    'date_to' => (string) ($row['date_to'] ?? ''),
                    'days_count' => (float) ($row['days_count'] ?? 0),
                    'status' => (string) ($row['status'] ?? ''),
                    'status_label' => TimeVacationService::statusLabel((string) ($row['status'] ?? '')),
                    'reason' => (string) ($row['reason'] ?? ''),
                ];
            }
            MobileApi::ok([
                'year' => $year,
                'enabled_types' => TimeTrackingSettings::enabledAbsenceTypesForKiosk(),
                'absences' => $rows,
            ]);
        }

        if ($head === 'absences' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $type = (string) ($_POST['type'] ?? MobileApi::jsonBody()['type'] ?? '');
            $from = (string) ($_POST['date_from'] ?? MobileApi::jsonBody()['date_from'] ?? '');
            $to = (string) ($_POST['date_to'] ?? MobileApi::jsonBody()['date_to'] ?? '');
            $reason = (string) ($_POST['reason'] ?? MobileApi::jsonBody()['reason'] ?? '');
            $half = !empty($_POST['half_day']) || !empty(MobileApi::jsonBody()['half_day']);
            $files = is_array($_FILES['evidence'] ?? null) ? $_FILES['evidence'] : [];
            $res = TimeAbsenceService::requestFromKiosk(
                $contactId,
                $type,
                $from,
                $to,
                $reason,
                $half,
                $files
            );
            MobileApi::ok($res);
        }

        if ($head === 'shifts' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
            $week = trim((string) ($_GET['week'] ?? ''));
            if ($week === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $week)) {
                $week = (new DateTimeImmutable('monday this week'))->format('Y-m-d');
            }
            $monday = new DateTimeImmutable($week);
            if ((int) $monday->format('N') !== 1) {
                $monday = $monday->modify('monday this week');
            }
            $sunday = $monday->modify('+6 days');
            $map = TimeShiftAssignmentRepository::mapForRange(
                $monday->format('Y-m-d'),
                $sunday->format('Y-m-d')
            );
            $mine = [];
            foreach ($map as $row) {
                if (!is_array($row) || (int) ($row['contact_id'] ?? 0) !== $contactId) {
                    continue;
                }
                $start = (string) ($row['start_time'] ?? '');
                $end = (string) ($row['end_time'] ?? '');
                $mins = 0;
                if ($start !== '' && $end !== '' && preg_match('/^\d{1,2}:\d{2}/', $start) && preg_match('/^\d{1,2}:\d{2}/', $end)) {
                    $s = (int) substr($start, 0, 2) * 60 + (int) substr($start, 3, 2);
                    $e = (int) substr($end, 0, 2) * 60 + (int) substr($end, 3, 2);
                    $mins = max(0, $e - $s);
                }
                $mine[] = [
                    'date' => (string) ($row['work_date'] ?? ''),
                    'template_name' => (string) ($row['template_name'] ?? ''),
                    'start_time' => $start,
                    'end_time' => $end,
                    'duration_minutes' => $mins,
                ];
            }
            usort($mine, static fn ($a, $b): int => strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? '')));
            MobileApi::ok([
                'week_monday' => $monday->format('Y-m-d'),
                'week_sunday' => $sunday->format('Y-m-d'),
                'shifts' => $mine,
            ]);
        }

        if ($head === 'documents' && $sub === 'download' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
            self::downloadDocument($contactId);
        }

        if ($head === 'documents' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
            $c = ContactRepository::findById($contactId);
            $files = is_array($c?->employeeFiles ?? null) ? $c->employeeFiles : [];
            MobileApi::ok([
                'documents' => EmployeeDocuments::listUploaded($files),
            ]);
        }

        MobileApi::fail(404, 'Unbekannter Mitarbeiter-Endpunkt.', 'not_found');
    }

    /**
     * @return array<string, mixed>
     */
    private static function profile(int $contactId): array
    {
        $c = ContactRepository::findById($contactId);
        if ($c === null) {
            throw new InvalidArgumentException('Kontakt nicht gefunden.');
        }
        $raw = is_array($c->employeeData ?? null) ? $c->employeeData : [];
        $data = EmployeeData::sanitize($raw);
        // Keine sensiblen Rohfelder unnötig erweitern — ausgewählte Felder
        $safe = [
            'job_type' => (string) ($data['job_type'] ?? ''),
            'working_hours' => (string) ($data['working_hours'] ?? ''),
            'daily_work_minutes' => (string) ($data['daily_work_minutes'] ?? ''),
            'entry_date' => (string) ($data['entry_date'] ?? ''),
            'contract_start' => (string) ($data['contract_start'] ?? ''),
            'employment_relationship' => (string) ($data['employment_relationship'] ?? ''),
            'work_location' => (string) ($data['work_location'] ?? ''),
        ];

        return [
            'contact' => MobileAuthService::contactPublic($contactId),
            'employee' => $safe,
        ];
    }

    /** @return never */
    private static function downloadDocument(int $contactId): void
    {
        $type = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($_GET['type'] ?? ''))) ?? '';
        $fileIndex = isset($_GET['file']) ? (int) $_GET['file'] : null;
        $c = ContactRepository::findById($contactId);
        $files = is_array($c?->employeeFiles ?? null) ? $c->employeeFiles : [];
        $entry = $files[$type] ?? null;
        if ($type === '' || !is_array($entry)) {
            MobileApi::fail(404, 'Dokument nicht gefunden.', 'not_found');
        }
        // Multi-file types (z. B. medical_certificates)
        if ($fileIndex !== null && isset($entry[$fileIndex]) && is_array($entry[$fileIndex])) {
            $entry = $entry[$fileIndex];
        }
        $pathRel = (string) ($entry['path'] ?? '');
        if ($pathRel === '' || str_contains($pathRel, '..')) {
            MobileApi::fail(404, 'Datei fehlt.', 'not_found');
        }
        $abs = DG_ROOT . '/' . ltrim(str_replace('\\', '/', $pathRel), '/');
        if (!is_file($abs)) {
            // try storage-relative
            $abs2 = DG_ROOT . '/storage/' . ltrim(str_replace('\\', '/', $pathRel), '/');
            $abs = is_file($abs2) ? $abs2 : $abs;
        }
        if (!is_file($abs)) {
            MobileApi::fail(404, 'Datei fehlt auf dem Server.', 'not_found');
        }
        $name = basename((string) ($entry['original_name'] ?? 'dokument.bin'));
        $mime = (string) ($entry['mime'] ?? 'application/octet-stream');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
        header('Content-Length: ' . (string) filesize($abs));
        header('Cache-Control: private, no-store');
        readfile($abs);
        exit;
    }
}
