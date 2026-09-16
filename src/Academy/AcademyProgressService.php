<?php
declare(strict_types=1);

final class AcademyProgressService
{
    /** @return array<string, mixed> */
    public static function assignmentSummary(int $assignmentId): array
    {
        $assignment = AcademyRepository::findAssignmentById($assignmentId);
        if ($assignment === null) {
            throw new InvalidArgumentException('Zuweisung nicht gefunden.');
        }

        $courseId = (int) ($assignment['course_id'] ?? 0);
        $modules = AcademyRepository::modulesForCourse($courseId, true, true);
        $progressRows = AcademyRepository::progressForAssignment($assignmentId);
        $progressByModule = [];
        foreach ($progressRows as $row) {
            $progressByModule[(int) ($row['module_id'] ?? 0)] = $row;
        }

        $completed = 0;
        $totalDuration = 0;
        $totalWall = 0;
        $flags = [];

        foreach ($modules as $module) {
            $moduleId = (int) ($module['id'] ?? 0);
            $duration = (int) ($module['duration_sec'] ?? 0);
            $totalDuration += $duration;
            $prog = $progressByModule[$moduleId] ?? null;
            if ($prog !== null && ($prog['status'] ?? '') === 'completed') {
                ++$completed;
            }
            if ($prog !== null) {
                $totalWall += (int) ($prog['wall_clock_sec'] ?? 0);
                $moduleFlags = self::decodeFlags($prog['anomaly_flags'] ?? null);
                $flags = array_merge($flags, $moduleFlags);
            }
        }

        $minWall = self::minimumWallClockSeconds($modules);
        if ($totalDuration > 0 && $totalWall > 0 && $totalWall < (int) floor($minWall * 0.85)) {
            $flags[] = 'course_too_fast';
        }

        return [
            'assignment' => $assignment,
            'modules' => $modules,
            'progress' => $progressByModule,
            'completed_modules' => $completed,
            'total_modules' => count($modules),
            'total_duration_sec' => $totalDuration,
            'total_wall_clock_sec' => $totalWall,
            'minimum_wall_clock_sec' => $minWall,
            'anomaly_flags' => array_values(array_unique($flags)),
            'risk_level' => self::riskLevel($flags, $totalWall, $minWall),
        ];
    }

    /** @return array{session_uuid: string, session_id: int} */
    public static function startSession(int $userId, int $assignmentId, int $moduleId): array
    {
        self::assertAssignmentOwner($userId, $assignmentId);
        self::ensureProgressRow($assignmentId, $moduleId);

        $uuid = self::uuid();
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_academy_watch_sessions
                (session_uuid, user_id, module_id, assignment_id)
             VALUES (:uuid, :user_id, :module_id, :assignment_id)'
        );
        $stmt->execute([
            'uuid' => $uuid,
            'user_id' => $userId,
            'module_id' => $moduleId,
            'assignment_id' => $assignmentId,
        ]);

        self::touchAssignment($assignmentId, 'in_progress');

        return ['session_uuid' => $uuid, 'session_id' => (int) Database::pdo()->lastInsertId()];
    }

    /** @param array<string, mixed> $payload */
    public static function heartbeat(string $sessionUuid, int $userId, array $payload): array
    {
        $session = self::findSession($sessionUuid, $userId);
        if ($session === null) {
            throw new InvalidArgumentException('Sitzung nicht gefunden.');
        }

        $rate = min(4.0, max(0.25, (float) ($payload['playback_rate'] ?? 1.0)));
        $delta = max(0, min(120, (int) ($payload['delta_sec'] ?? 0)));
        $position = max(0, (int) ($payload['position_sec'] ?? 0));
        $tabVisible = !empty($payload['tab_visible']);
        $module = AcademyRepository::findModule((int) ($session['module_id'] ?? 0));
        if ($module === null) {
            throw new InvalidArgumentException('Modul nicht gefunden.');
        }

        $maxRate = (float) ($module['max_playback_rate'] ?? 2.0);
        $flags = [];
        if ($rate > $maxRate + 0.01) {
            $flags[] = 'playback_rate_exceeded';
            $delta = 0;
        }

        if ($delta > 0 && $tabVisible) {
            $stmt = Database::pdo()->prepare(
                'UPDATE dg_academy_watch_sessions SET
                    watched_seconds = watched_seconds + :delta,
                    wall_clock_sec = wall_clock_sec + :delta,
                    heartbeat_count = heartbeat_count + 1,
                    max_playback_rate = GREATEST(max_playback_rate, :rate),
                    avg_playback_rate = CASE
                        WHEN heartbeat_count = 0 THEN :rate
                        ELSE ((avg_playback_rate * heartbeat_count) + :rate) / (heartbeat_count + 1)
                    END
                 WHERE id = :id'
            );
            $stmt->execute([
                'delta' => $delta,
                'rate' => $rate,
                'id' => (int) $session['id'],
            ]);
        } elseif (!$tabVisible && $delta > 0) {
            $stmt = Database::pdo()->prepare(
                'UPDATE dg_academy_watch_sessions SET tab_hidden_sec = tab_hidden_sec + :delta WHERE id = :id'
            );
            $stmt->execute(['delta' => $delta, 'id' => (int) $session['id']]);
            $flags[] = 'tab_hidden';
        }

        self::recordEvent((int) $session['id'], $userId, (int) $session['module_id'], 'heartbeat', [
            'rate' => $rate,
            'delta' => $delta,
            'position' => $position,
            'tab_visible' => $tabVisible,
            'flags' => $flags,
        ]);

        self::syncProgressFromSession((int) $session['assignment_id'], (int) $session['module_id'], $position, $flags);

        return ['ok' => true, 'flags' => $flags];
    }

    public static function completeModule(string $sessionUuid, int $userId): array
    {
        $session = self::findSession($sessionUuid, $userId);
        if ($session === null) {
            throw new InvalidArgumentException('Sitzung nicht gefunden.');
        }

        $assignmentId = (int) ($session['assignment_id'] ?? 0);
        $moduleId = (int) ($session['module_id'] ?? 0);
        $module = AcademyRepository::findModule($moduleId);
        if ($module === null) {
            throw new InvalidArgumentException('Modul nicht gefunden.');
        }

        $duration = max(1, (int) ($module['duration_sec'] ?? 0));
        $minPercent = max(1, min(100, (int) ($module['min_watch_percent'] ?? 90)));
        $maxRate = (float) ($module['max_playback_rate'] ?? 2.0);
        $requiredWatch = (int) ceil($duration * ($minPercent / 100));
        $minWall = (int) ceil($requiredWatch / $maxRate);

        $watched = (int) ($session['watched_seconds'] ?? 0);
        $wall = (int) ($session['wall_clock_sec'] ?? 0);
        $flags = self::decodeFlags($session['anomaly_flags'] ?? null);

        if ((float) ($session['max_playback_rate'] ?? 1) > $maxRate + 0.01) {
            $flags[] = 'playback_rate_exceeded';
        }
        if ($watched < $requiredWatch) {
            $flags[] = 'insufficient_watch_time';
        }
        if ($wall < (int) floor($minWall * 0.9)) {
            $flags[] = 'wall_clock_too_short';
        }

        $completed = $watched >= $requiredWatch && !in_array('playback_rate_exceeded', $flags, true);

        $stmt = Database::pdo()->prepare(
            'UPDATE dg_academy_watch_sessions SET ended_at = NOW(), anomaly_flags = :flags WHERE id = :id'
        );
        $stmt->execute([
            'flags' => self::encodeFlags($flags),
            'id' => (int) $session['id'],
        ]);

        self::ensureProgressRow($assignmentId, $moduleId);
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_academy_module_progress SET
                status = :status,
                watched_seconds = GREATEST(watched_seconds, :watched),
                wall_clock_sec = GREATEST(wall_clock_sec, :wall),
                max_playback_rate = GREATEST(max_playback_rate, :max_rate),
                anomaly_flags = :flags,
                completed_at = CASE WHEN :status = \'completed\' THEN NOW() ELSE completed_at END
             WHERE assignment_id = :assignment_id AND module_id = :module_id'
        );
        $stmt->execute([
            'status' => $completed ? 'completed' : 'in_progress',
            'watched' => $watched,
            'wall' => $wall,
            'max_rate' => (float) ($session['max_playback_rate'] ?? 1),
            'flags' => self::encodeFlags($flags),
            'assignment_id' => $assignmentId,
            'module_id' => $moduleId,
        ]);

        self::maybeCompleteAssignment($assignmentId);

        return [
            'completed' => $completed,
            'flags' => $flags,
            'required_watch_sec' => $requiredWatch,
            'watched_sec' => $watched,
        ];
    }

    private static function maybeCompleteAssignment(int $assignmentId): void
    {
        $summary = self::assignmentSummary($assignmentId);
        if (($summary['completed_modules'] ?? 0) < ($summary['total_modules'] ?? 0)) {
            return;
        }

        $assignment = $summary['assignment'];
        $status = !empty($assignment['certificate_enabled']) ? 'pending_review' : 'pending_review';
        if (($summary['risk_level'] ?? '') === 'red') {
            $status = 'flagged';
        }

        $stmt = Database::pdo()->prepare(
            'UPDATE dg_academy_assignments SET status = :status, completed_at = NOW(), updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['status' => $status, 'id' => $assignmentId]);

        if (!empty($assignment['certificate_enabled'])) {
            AcademyHrService::ensurePendingCertificate($assignmentId);
        }
    }

    /** @param list<array<string, mixed>> $modules */
    private static function minimumWallClockSeconds(array $modules): int
    {
        $sum = 0;
        foreach ($modules as $module) {
            $duration = max(0, (int) ($module['duration_sec'] ?? 0));
            $percent = max(1, min(100, (int) ($module['min_watch_percent'] ?? 90)));
            $maxRate = max(1.0, (float) ($module['max_playback_rate'] ?? 2.0));
            $required = (int) ceil($duration * ($percent / 100));
            $sum += (int) ceil($required / $maxRate);
        }

        return $sum;
    }

    /** @param list<string> $flags */
    private static function riskLevel(array $flags, int $wall, int $minWall): string
    {
        $red = ['playback_rate_exceeded', 'course_too_fast', 'insufficient_watch_time', 'wall_clock_too_short'];
        foreach ($flags as $flag) {
            if (in_array($flag, $red, true)) {
                return 'red';
            }
        }
        if ($minWall > 0 && $wall > 0 && $wall < (int) floor($minWall * 0.95)) {
            return 'yellow';
        }

        return 'green';
    }

    private static function touchAssignment(int $assignmentId, string $status): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_academy_assignments SET status = :status, started_at = COALESCE(started_at, NOW()), updated_at = NOW()
             WHERE id = :id AND status = \'open\''
        );
        $stmt->execute(['status' => $status, 'id' => $assignmentId]);
    }

    private static function ensureProgressRow(int $assignmentId, int $moduleId): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT IGNORE INTO dg_academy_module_progress (assignment_id, module_id, status)
             VALUES (:assignment_id, :module_id, \'not_started\')'
        );
        $stmt->execute(['assignment_id' => $assignmentId, 'module_id' => $moduleId]);
    }

    /** @param list<string> $flags */
    private static function syncProgressFromSession(int $assignmentId, int $moduleId, int $position, array $flags): void
    {
        self::ensureProgressRow($assignmentId, $moduleId);
        $sessionAgg = Database::pdo()->prepare(
            'SELECT COALESCE(SUM(watched_seconds), 0) AS watched, COALESCE(SUM(wall_clock_sec), 0) AS wall,
                    COALESCE(MAX(max_playback_rate), 1) AS max_rate
             FROM dg_academy_watch_sessions
             WHERE assignment_id = :assignment_id AND module_id = :module_id'
        );
        $sessionAgg->execute(['assignment_id' => $assignmentId, 'module_id' => $moduleId]);
        $agg = $sessionAgg->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = Database::pdo()->prepare(
            'UPDATE dg_academy_module_progress SET
                status = CASE WHEN status = \'not_started\' THEN \'in_progress\' ELSE status END,
                watched_seconds = :watched,
                wall_clock_sec = :wall,
                last_position_sec = :position,
                max_playback_rate = GREATEST(max_playback_rate, :max_rate),
                anomaly_flags = :flags
             WHERE assignment_id = :assignment_id AND module_id = :module_id'
        );
        $stmt->execute([
            'watched' => (int) ($agg['watched'] ?? 0),
            'wall' => (int) ($agg['wall'] ?? 0),
            'position' => $position,
            'max_rate' => (float) ($agg['max_rate'] ?? 1),
            'flags' => self::encodeFlags($flags),
            'assignment_id' => $assignmentId,
            'module_id' => $moduleId,
        ]);
    }

    /** @return array<string, mixed>|null */
    private static function findSession(string $uuid, int $userId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_academy_watch_sessions
             WHERE session_uuid = :uuid AND user_id = :user_id AND ended_at IS NULL LIMIT 1'
        );
        $stmt->execute(['uuid' => $uuid, 'user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private static function assertAssignmentOwner(int $userId, int $assignmentId): void
    {
        $assignment = AcademyRepository::findAssignmentById($assignmentId);
        if ($assignment === null || (int) ($assignment['user_id'] ?? 0) !== $userId) {
            throw new InvalidArgumentException('Keine Berechtigung für diese Zuweisung.');
        }
    }

    /** @param array<string, mixed> $payload */
    private static function recordEvent(int $sessionId, int $userId, int $moduleId, string $type, array $payload): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_academy_watch_events (session_id, user_id, module_id, event_type, payload)
             VALUES (:session_id, :user_id, :module_id, :event_type, :payload)'
        );
        $stmt->execute([
            'session_id' => $sessionId,
            'user_id' => $userId,
            'module_id' => $moduleId,
            'event_type' => $type,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /** @return list<string> */
    private static function decodeFlags(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /** @param list<string> $flags */
    private static function encodeFlags(array $flags): ?string
    {
        $flags = array_values(array_unique(array_filter($flags, static fn ($f) => is_string($f) && $f !== '')));

        return $flags === [] ? null : json_encode($flags, JSON_UNESCAPED_UNICODE);
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
