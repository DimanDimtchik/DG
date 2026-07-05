<?php
declare(strict_types=1);

/** Öffentliche Terminbuchung (ohne CRM-Login). */
final class PublicBookingApi
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Nur POST erlaubt.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!CalendarEmbedSettings::isOnlineBookingEnabled()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Online-Terminbuchung ist derzeit deaktiviert.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!Database::isConfigured()) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Buchung vorübergehend nicht möglich.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sitzung abgelaufen — bitte Seite neu laden.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            echo json_encode(['success' => true, 'message' => CalendarEmbedSettings::successMessage()], JSON_UNESCAPED_UNICODE);
            return;
        }

        MigrationRunner::runPending();
        CalendarWorkingHoursRepository::ensureSeeded();
        CalendarStaffRepository::ensureSeeded();

        try {
            $bookingId = self::createBooking($_POST);
            $booking = BookingRepository::findById($bookingId);
            if ($booking !== null) {
                BookingEmailNotifier::afterSave(null, $booking, null);
            }

            echo json_encode([
                'success' => true,
                'message' => CalendarEmbedSettings::successMessage(),
                'booking_id' => $bookingId,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** @param array<string, mixed> $post */
    private static function createBooking(array $post): int
    {
        $name = trim((string) ($post['customer_name'] ?? ''));
        $email = strtolower(trim((string) ($post['customer_email'] ?? '')));
        $phone = trim((string) ($post['customer_phone'] ?? ''));
        $articleId = max(0, (int) ($post['article_id'] ?? 0));
        $employeeId = max(0, (int) ($post['employee_id'] ?? 0));
        $slotDatetime = trim((string) ($post['slot_datetime'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('Bitte Ihren Namen angeben.');
        }
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Bitte eine gültige E-Mail-Adresse angeben.');
        }
        if ($articleId < 1) {
            throw new InvalidArgumentException('Bitte eine Leistung wählen.');
        }
        if (CalendarArticleRepository::findById($articleId) === null) {
            throw new InvalidArgumentException('Die gewählte Leistung ist nicht mehr verfügbar.');
        }
        if ($slotDatetime === '') {
            throw new InvalidArgumentException('Bitte einen Termin wählen.');
        }

        return BookingRepository::save([
            'article_id' => $articleId,
            'employee_id' => $employeeId,
            'slot_datetime' => $slotDatetime,
            'customer_name' => $name,
            'customer_email' => $email,
            'customer_phone' => $phone,
            'status' => 'gebucht',
            'admin_notes' => 'Online-Buchung',
        ]);
    }
}
