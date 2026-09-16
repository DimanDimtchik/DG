<?php
declare(strict_types=1);

final class AcademyApi
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $user = AuthService::user();
        if (!$user || !MenuRegistry::canAccess($user, 'akademie')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Keine Berechtigung.'], JSON_UNESCAPED_UNICODE);

            return;
        }

        $action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));
        try {
            $result = match ($action) {
                'start' => self::handleStart($user),
                'heartbeat' => self::handleHeartbeat($user),
                'complete' => self::handleComplete($user),
                default => throw new InvalidArgumentException('Unbekannte Aktion.'),
            };
            echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** @return array<string, mixed> */
    private static function handleStart(User $user): array
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['_csrf'] ?? null)) {
            throw new InvalidArgumentException('CSRF ungültig.');
        }

        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
        $moduleId = (int) ($_POST['module_id'] ?? 0);
        if ($assignmentId < 1 || $moduleId < 1) {
            throw new InvalidArgumentException('Zuweisung oder Modul fehlt.');
        }

        $assignment = AcademyRepository::findAssignmentById($assignmentId);
        if ($assignment === null) {
            throw new InvalidArgumentException('Zuweisung nicht gefunden.');
        }
        if (!AcademyRepository::hasAcceptedRules(
            (int) $user->id,
            (int) ($assignment['course_id'] ?? 0),
            (string) ($assignment['course_version'] ?? '1.0')
        )) {
            throw new InvalidArgumentException('Bitte zuerst die Schulungsregeln akzeptieren.');
        }

        return AcademyProgressService::startSession((int) $user->id, $assignmentId, $moduleId);
    }

    /** @return array<string, mixed> */
    private static function handleHeartbeat(User $user): array
    {
        $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }

        $sessionUuid = trim((string) ($payload['session_uuid'] ?? ''));
        if ($sessionUuid === '') {
            throw new InvalidArgumentException('Sitzung fehlt.');
        }

        return AcademyProgressService::heartbeat($sessionUuid, (int) $user->id, $payload);
    }

    /** @return array<string, mixed> */
    private static function handleComplete(User $user): array
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['_csrf'] ?? null)) {
            throw new InvalidArgumentException('CSRF ungültig.');
        }

        $sessionUuid = trim((string) ($_POST['session_uuid'] ?? ''));
        if ($sessionUuid === '') {
            throw new InvalidArgumentException('Sitzung fehlt.');
        }

        return AcademyProgressService::completeModule($sessionUuid, (int) $user->id);
    }
}
