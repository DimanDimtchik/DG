<?php
declare(strict_types=1);

/** Kunden-Kalender-API `/api/mobile/customer/*`. */
final class MobileCustomerApi
{
    /**
     * @param list<string> $parts
     * @return never
     */
    public static function handle(array $parts): void
    {
        $auth = MobileAuthService::requireAuth('customer');
        $contactId = (int) $auth['contact_id'];
        $contact = ContactRepository::findById($contactId);
        $email = strtolower(trim((string) ($contact?->email ?? '')));

        $head = $parts[0] ?? '';
        $id = isset($parts[1]) ? (int) $parts[1] : 0;
        $action = $parts[2] ?? '';

        if ($head === 'catalog' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
            self::assertBookingEnabled();
            MobileApi::ok(self::catalog());
        }

        if ($head === 'slots' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
            self::assertBookingEnabled();
            $articleId = max(0, (int) ($_GET['article_id'] ?? 0));
            $employeeId = max(0, (int) ($_GET['employee_id'] ?? 0));
            $date = trim((string) ($_GET['date'] ?? ''));
            CalendarWorkingHoursRepository::ensureSeeded();
            CalendarStaffRepository::ensureSeeded();
            if ($date !== '') {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    throw new InvalidArgumentException('Ungültiges Datum.');
                }
                MobileApi::ok([
                    'date' => $date,
                    'slots' => BookingSlotService::slotsForDate($date, $articleId, $employeeId, null),
                    'duration_minutes' => BookingSlotService::resolveDurationMinutes($articleId),
                ]);
            }
            $days = max(1, min(90, (int) ($_GET['days'] ?? 30)));
            MobileApi::ok([
                'slots_by_date' => BookingSlotService::availableSlots($articleId, $employeeId, $days, null),
                'duration_minutes' => BookingSlotService::resolveDurationMinutes($articleId),
            ]);
        }

        if ($head === 'bookings' && $id < 1 && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
            if ($email === '') {
                MobileApi::ok(['bookings' => []]);
            }
            $list = [];
            foreach (BookingRepository::findByCustomerEmail($email, 50) as $b) {
                $list[] = self::bookingView($b);
            }
            // also include cancelled for history
            MobileApi::ok(['bookings' => $list]);
        }

        if ($head === 'bookings' && $id < 1 && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            self::assertBookingEnabled();
            $body = MobileApi::jsonBody();
            $name = trim((string) ($body['customer_name'] ?? ($contact?->displayName ?? '')));
            if ($name === '') {
                $name = trim((string) ($contact?->firstName ?? '') . ' ' . (string) ($contact?->lastName ?? ''));
            }
            $bookingId = BookingRepository::save([
                'article_id' => (int) ($body['article_id'] ?? 0),
                'employee_id' => (int) ($body['employee_id'] ?? 0),
                'slot_datetime' => (string) ($body['slot_datetime'] ?? ''),
                'customer_name' => $name,
                'customer_email' => $email,
                'customer_phone' => (string) ($body['customer_phone'] ?? ($contact?->phone1 ?? '')),
                'status' => 'gebucht',
                'admin_notes' => 'Mobile-App-Buchung',
            ]);
            $booking = BookingRepository::findById($bookingId);
            if ($booking !== null) {
                BookingEmailNotifier::afterSave(null, $booking, null);
            }
            MobileApi::ok(['booking' => $booking !== null ? self::bookingView($booking) : ['id' => $bookingId]]);
        }

        if ($head === 'bookings' && $id > 0 && $action === 'cancel' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $booking = self::ownBooking($id, $email);
            BookingRepository::save([
                'article_id' => $booking->articleId,
                'employee_id' => $booking->employeeId,
                'slot_datetime' => $booking->slotDatetime,
                'customer_name' => $booking->customerName,
                'customer_email' => $booking->customerEmail,
                'customer_phone' => $booking->customerPhone,
                'status' => 'storniert',
                'admin_notes' => trim($booking->adminNotes . ' | Storno per App'),
            ], $booking->id);
            $updated = BookingRepository::findById($booking->id);
            MobileApi::ok(['booking' => $updated !== null ? self::bookingView($updated) : null]);
        }

        if ($head === 'bookings' && $id > 0 && $action === 'reschedule' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            self::assertBookingEnabled();
            $booking = self::ownBooking($id, $email);
            $body = MobileApi::jsonBody();
            $newSlot = trim((string) ($body['slot_datetime'] ?? ''));
            if ($newSlot === '') {
                throw new InvalidArgumentException('Neuer Termin (slot_datetime) erforderlich.');
            }
            BookingRepository::save([
                'article_id' => $booking->articleId,
                'employee_id' => (int) ($body['employee_id'] ?? $booking->employeeId),
                'slot_datetime' => $newSlot,
                'customer_name' => $booking->customerName,
                'customer_email' => $booking->customerEmail,
                'customer_phone' => $booking->customerPhone,
                'status' => 'gebucht',
                'admin_notes' => trim($booking->adminNotes . ' | Umbuchung per App'),
            ], $booking->id);
            $updated = BookingRepository::findById($booking->id);
            if ($updated !== null) {
                BookingEmailNotifier::afterSave($booking, $updated, null);
            }
            MobileApi::ok(['booking' => $updated !== null ? self::bookingView($updated) : null]);
        }

        MobileApi::fail(404, 'Unbekannter Kunden-Endpunkt.', 'not_found');
    }

    private static function assertBookingEnabled(): void
    {
        if (!CalendarEmbedSettings::isOnlineBookingEnabled()) {
            throw new RuntimeException('Online-Terminbuchung ist deaktiviert.');
        }
    }

    private static function ownBooking(int $id, string $email): Booking
    {
        $booking = BookingRepository::findById($id);
        if ($booking === null) {
            throw new InvalidArgumentException('Termin nicht gefunden.');
        }
        if ($email === '' || strtolower($booking->customerEmail) !== $email) {
            throw new RuntimeException('Kein Zugriff auf diesen Termin.');
        }

        return $booking;
    }

    /**
     * @return array<string, mixed>
     */
    private static function catalog(): array
    {
        $articles = [];
        foreach (CalendarArticleRepository::all(true, CalendarArticleCatalog::KIND_SERVICE) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $articles[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'duration_minutes' => BookingSlotService::resolveDurationMinutes((int) ($row['id'] ?? 0)),
                'price_gross' => (float) ($row['price_gross'] ?? 0),
                'price_label' => (string) ($row['price_label'] ?? ''),
            ];
        }
        $staff = [];
        foreach (CalendarStaffRepository::getEmployees(true) as $emp) {
            if (!is_array($emp)) {
                continue;
            }
            $staff[] = [
                'id' => (int) ($emp['id'] ?? 0),
                'name' => (string) ($emp['name'] ?? ('#' . (int) ($emp['id'] ?? 0))),
            ];
        }

        return ['articles' => $articles, 'staff' => $staff];
    }

    /**
     * @return array<string, mixed>
     */
    private static function bookingView(Booking $b): array
    {
        return [
            'id' => $b->id,
            'booking_code' => $b->publicCode(),
            'article_id' => $b->articleId,
            'employee_id' => $b->employeeId,
            'slot_datetime' => $b->slotDatetime,
            'customer_name' => $b->customerName,
            'customer_email' => $b->customerEmail,
            'customer_phone' => $b->customerPhone,
            'status' => $b->status,
            'status_label' => $b->statusLabel(),
        ];
    }
}
