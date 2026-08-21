<?php
declare(strict_types=1);

/**
 * Calendar Email Template Renderer.
 */
final class CalendarEmailTemplateRenderer
{
    /**
     * Führt aus: render.
     * @param string $templateKey
     * @param array $template
     * @param array|null $context
     * @return array{subject: string, html: string}
     */
    public static function render(string $templateKey, array $template, ?array $context = null): array
    {
        $context = $context ?? CalendarEmailTokens::demoContext();
        $subject = CalendarEmailTokens::replace(trim((string) ($template['subject'] ?? '')), $context);
        $title = CalendarEmailTokens::replace(trim((string) ($template['title'] ?? '')), $context);
        $intro = CalendarEmailTokens::replace(trim((string) ($template['intro'] ?? '')), $context);

        $footerNote = '';
        if (($template['event_slug'] ?? '') === NotificationTemplateSettings::SLUG_ADMIN
            || $templateKey === NotificationTemplateSettings::SLUG_ADMIN
            || $templateKey === CalendarEmailTemplateSettings::TEMPLATE_ADMIN) {
            $footerNote = 'Diese E-Mail wurde automatisch vom Terminkalender versendet.';
        }

        $inner = CalendarEmailLayout::renderBookingEmail([
            'title' => $title,
            'intro' => $intro,
            'details' => self::detailsForContext($context),
            'footer_note' => $footerNote,
            'context' => $context,
        ]);

        return [
            'subject' => $subject,
            'html' => $inner,
        ];
    }

    /**
     * Methode preview.
     * @param string $templateKey
     * @param array $template
     * @param array|null $context
     * @return array<string, mixed>
     */
    public static function preview(string $templateKey, array $template, ?array $context = null): array
    {
        return self::render($templateKey, $template, $context);
    }

    /**
     * Methode details for context.
     * @param array $context
     * @return array<string, mixed>
     */
    private static function detailsForContext(array $context): array
    {
        return [
            'Leistung' => $context['leistung'] ?? '',
            'Termin' => trim(($context['termin_datum'] ?? '') . ', ' . ($context['termin_zeit'] ?? '')),
            'Kunde' => $context['kunde_name'] ?? '',
            'E-Mail' => $context['kunde_email'] ?? '',
            'Telefon' => $context['kunde_telefon'] ?? '',
            'Mitarbeiter' => $context['mitarbeiter'] ?? '',
        ];
    }
}
