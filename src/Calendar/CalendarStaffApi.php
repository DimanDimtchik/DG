<?php
declare(strict_types=1);

final class CalendarStaffApi
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $user = AuthService::user();
        if (!$user || !RoleResolver::isAdmin($user)) {
            self::error('Keine Berechtigung.', 403);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::error('Nur POST erlaubt.', 405);
        }

        if (!Csrf::verify($_POST['_csrf'] ?? $_POST['staff_nonce'] ?? null)) {
            self::error('Ungültiges Formular (CSRF).', 403);
        }

        if (!Database::isConfigured()) {
            self::error('Datenbank nicht konfiguriert.');
        }

        MigrationRunner::runPending();
        CalendarStaffRepository::ensureSeeded();

        $action = (string) ($_POST['action'] ?? '');

        try {
            match ($action) {
                'save_area' => self::ok(CalendarStaffRepository::saveArea($_POST)),
                'delete_area' => self::ok(CalendarStaffRepository::deleteArea((int) ($_POST['id'] ?? 0))),
                'save_employee' => self::ok(CalendarStaffRepository::saveEmployee($_POST)),
                'delete_employee' => self::ok(CalendarStaffRepository::deleteEmployee((int) ($_POST['id'] ?? 0))),
                'load_employee_hours_editor' => self::ok([
                    'html' => CalendarStaffRepository::renderHoursEditorHtml((int) ($_POST['employee_id'] ?? 0)),
                ], false),
                'save_employee_hours' => self::ok(CalendarStaffRepository::saveEmployeeHours(
                    (int) ($_POST['employee_id'] ?? 0),
                    CalendarStaffRepository::parseHoursWindows($_POST)
                )),
                'save_employee_absence' => self::ok(CalendarStaffRepository::saveAbsence($_POST)),
                'delete_employee_absence' => self::ok(CalendarStaffRepository::deleteAbsence((int) ($_POST['id'] ?? 0))),
                default => self::error('Unbekannte Aktion.'),
            };
        } catch (Throwable $e) {
            self::error($e->getMessage());
        }
    }

    /** @param array<string, mixed> $data */
    private static function ok(array $data = [], bool $reload = true): never
    {
        $payload = $data;
        if ($reload) {
            $payload['reload'] = true;
        }
        echo json_encode(['success' => true, 'data' => $payload], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function error(string $message, int $code = 400): never
    {
        http_response_code($code);
        echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
