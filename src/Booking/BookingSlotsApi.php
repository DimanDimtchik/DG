<?php
declare(strict_types=1);

final class BookingSlotsApi
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Nur GET erlaubt.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!Database::isConfigured()) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Datenbank nicht konfiguriert.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        MigrationRunner::runPending();
        CalendarWorkingHoursRepository::ensureSeeded();
        CalendarStaffRepository::ensureSeeded();

        $articleId = max(0, (int) ($_GET['article_id'] ?? 0));
        $employeeId = max(0, (int) ($_GET['employee_id'] ?? 0));
        $excludeBookingId = max(0, (int) ($_GET['exclude_booking_id'] ?? 0)) ?: null;
        $duration = BookingSlotService::resolveDurationMinutes($articleId);
        $date = trim((string) ($_GET['date'] ?? ''));

        try {
            if ($date !== '') {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    throw new InvalidArgumentException('Ungültiges Datum.');
                }

                $slots = BookingSlotService::slotsForDate($date, $articleId, $employeeId, $excludeBookingId);
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'date' => $date,
                        'article_id' => $articleId,
                        'employee_id' => $employeeId,
                        'duration_minutes' => $duration,
                        'slot_step_minutes' => BookingSlotService::slotStepMinutes(),
                        'uses_employees' => $articleId > 0
                            && CalendarStaffRepository::usesEmployeeSchedulingForArticle($articleId),
                        'slots' => $slots,
                    ],
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $days = max(1, min(366, (int) ($_GET['days'] ?? 365)));
            $grouped = BookingSlotService::availableSlots($articleId, $employeeId, $days, $excludeBookingId);
            echo json_encode([
                'success' => true,
                'data' => [
                    'article_id' => $articleId,
                    'employee_id' => $employeeId,
                    'duration_minutes' => $duration,
                    'slot_step_minutes' => BookingSlotService::slotStepMinutes(),
                    'slots_by_date' => $grouped,
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }
}
