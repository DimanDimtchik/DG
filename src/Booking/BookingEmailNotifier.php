<?php
declare(strict_types=1);

/** Versendet Kalender-E-Mails bei Buchung und Storno. */
final class BookingEmailNotifier
{
    /** @var list<string> */
    private const CANCELLED_STATUSES = ['storniert', 'cancelled', 'canceled', 'cancel'];

    public static function afterSave(?Booking $before, Booking $after, ?User $actor = null): void
    {
        if (!Database::isConfigured() || !MailSettings::isConfigured()) {
            return;
        }

        try {
            if ($before === null) {
                if (!self::isCancelled($after->status)) {
                    self::sendConfirmation($after, $actor);
                    self::sendAdminNotification($after, $actor);
                }

                return;
            }

            if (!self::isCancelled($before->status) && self::isCancelled($after->status)) {
                self::sendCancellation($after, $actor);
            }
        } catch (Throwable $e) {
            error_log('DG CRM BookingEmailNotifier: ' . $e->getMessage());
        }
    }

    private static function sendConfirmation(Booking $booking, ?User $actor): void
    {
        if (!CalendarNotificationSettings::config()['send_customer_email']) {
            return;
        }

        $recipient = trim($booking->customerEmail);
        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        self::sendTemplated(
            $booking,
            NotificationTemplateSettings::SLUG_CONFIRMATION,
            [$recipient],
            $actor
        );
    }

    private static function sendCancellation(Booking $booking, ?User $actor): void
    {
        if (!CalendarNotificationSettings::config()['send_customer_email']) {
            return;
        }

        $recipient = trim($booking->customerEmail);
        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        self::sendTemplated(
            $booking,
            NotificationTemplateSettings::SLUG_CANCELLATION,
            [$recipient],
            $actor
        );
    }

    private static function sendAdminNotification(Booking $booking, ?User $actor): void
    {
        $settings = CalendarNotificationSettings::config();
        if (!$settings['send_admin_email']) {
            return;
        }

        $recipient = CalendarNotificationSettings::notifyAdminEmail();
        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        self::sendTemplated(
            $booking,
            NotificationTemplateSettings::SLUG_ADMIN,
            [$recipient],
            $actor
        );
    }

    /** @param list<string> $recipients */
    private static function sendTemplated(
        Booking $booking,
        string $templateSlug,
        array $recipients,
        ?User $actor,
    ): void {
        $departmentId = self::departmentIdFor($booking);
        $template = NotificationTemplateSettings::resolvedTemplate(
            $departmentId,
            NotificationTemplateSettings::CATEGORY_CALENDAR,
            $templateSlug
        );
        if ($template === null) {
            return;
        }

        $context = CalendarEmailTokens::contextForBooking($booking);
        $rendered = CalendarEmailTemplateRenderer::render($templateSlug, $template, $context);

        try {
            MailService::send(new MailMessage(
                subject: $rendered['subject'] !== '' ? $rendered['subject'] : 'Terminbenachrichtigung',
                htmlBody: $rendered['html'],
                to: $recipients,
            ), $actor);
        } catch (Throwable $e) {
            error_log(sprintf(
                'DG CRM BookingEmailNotifier (%s, Buchung #%d): %s',
                $templateSlug,
                $booking->id,
                $e->getMessage()
            ));
        }
    }

    private static function departmentIdFor(Booking $booking): string
    {
        $areaId = CalendarArticleRepository::getAreaId($booking->articleId);
        if ($areaId < 1) {
            return '';
        }

        return CalendarStaffRepository::areaDepartmentMap()[(string) $areaId] ?? '';
    }

    private static function isCancelled(string $status): bool
    {
        return in_array(strtolower(trim($status)), self::CANCELLED_STATUSES, true);
    }
}
