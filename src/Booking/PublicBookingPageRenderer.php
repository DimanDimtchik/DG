<?php
declare(strict_types=1);

/**
 * Öffentliche Online-Terminbuchung (/termin, Website-Block „online_booking“).
 */
final class PublicBookingPageRenderer
{
    /**
     * Vollständige Standalone-Seite (/termin).
     */
    public static function render(bool $preview = false): void
    {
        $data = self::widgetData($preview);
        if ($data === null) {
            View::render('public/termin-disabled', [
                'disabledReason' => 'Die Online-Terminbuchung ist derzeit deaktiviert. Bitte kontaktieren Sie uns direkt.',
            ]);

            return;
        }

        View::render('public/termin', $data);
    }

    /**
     * Eingebettetes Buchungsformular (Website-Seite / Vorschau).
     */
    public static function renderWidget(bool $preview = false): void
    {
        $data = self::widgetData($preview);
        if ($data === null) {
            echo '<p class="ws-booking-disabled">Die Online-Terminbuchung ist derzeit deaktiviert. Bitte kontaktieren Sie uns direkt.</p>';

            return;
        }

        View::render('public/termin-widget', array_merge($data, [
            'embedded' => true,
            'previewMode' => $preview,
        ]));
    }

    /**
     * @return array<string, mixed>|null null wenn öffentlich deaktiviert (ohne Vorschau)
     */
    public static function widgetData(bool $preview = false): ?array
    {
        MigrationRunner::runPending();

        $onlineBookingEnabled = CalendarEmbedSettings::isOnlineBookingEnabled();

        if (!$preview && !$onlineBookingEnabled) {
            return null;
        }

        if (!Database::isConfigured()) {
            return null;
        }

        CalendarWorkingHoursRepository::ensureSeeded();
        CalendarStaffRepository::ensureSeeded();

        return [
            'embedConfig' => CalendarEmbedSettings::config(),
            'bookingArticles' => CalendarArticleRepository::bookingOptions(),
            'bookingEmployees' => CalendarStaffRepository::bookingEmployeeOptions(),
            'previewMode' => $preview,
            'onlineBookingEnabled' => $onlineBookingEnabled,
        ];
    }

    public static function pageNeedsAssets(array $page): bool
    {
        if (WebsitePageRepository::isOnlineBookingPage($page)) {
            return true;
        }
        $layout = is_array($page['layout'] ?? null) ? $page['layout'] : [];

        return WebsitePageRepository::layoutHasBlockType($layout, 'online_booking');
    }
}
