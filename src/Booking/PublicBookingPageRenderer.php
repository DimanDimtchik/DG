<?php
declare(strict_types=1);

/**
 * Öffentliche Online-Terminbuchung (/termin, Website-Seite „Terminkalender“).
 */
final class PublicBookingPageRenderer
{
    public static function render(): void
    {
        MigrationRunner::runPending();

        if (!CalendarEmbedSettings::isOnlineBookingEnabled()) {
            View::render('public/termin-disabled', [
                'disabledReason' => 'Die Online-Terminbuchung ist derzeit deaktiviert. Bitte kontaktieren Sie uns direkt.',
            ]);
            return;
        }

        if (!Database::isConfigured()) {
            View::render('public/termin-disabled', [
                'disabledReason' => 'Die Online-Terminbuchung ist vorübergehend nicht verfügbar.',
            ]);
            return;
        }

        CalendarWorkingHoursRepository::ensureSeeded();
        CalendarStaffRepository::ensureSeeded();

        View::render('public/termin', [
            'embedConfig' => CalendarEmbedSettings::config(),
            'bookingArticles' => CalendarArticleRepository::bookingOptions(),
            'bookingEmployees' => CalendarStaffRepository::bookingEmployeeOptions(),
        ]);
    }
}
