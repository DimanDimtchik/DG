<?php
declare(strict_types=1);

/**
 * Calendar Email Template Settings.
 */
final class CalendarEmailTemplateSettings
{
    public const STORE_KEY = 'calendar_email_templates';

    public const TEMPLATE_CONFIRMATION = NotificationTemplateSettings::SLUG_CONFIRMATION;
    public const TEMPLATE_CANCELLATION = NotificationTemplateSettings::SLUG_CANCELLATION;
    public const TEMPLATE_ADMIN = NotificationTemplateSettings::SLUG_ADMIN;

    /**
     * Methode template labels.
     * @return array<string, mixed>
     */
    public static function templateLabels(): array
    {
        $out = [];
        foreach (NotificationTemplateSettings::defaultBuiltinTemplates() as $template) {
            $out[(string) $template['event_slug']] = (string) $template['name'];
        }

        return $out;
    }

    /**
     * Führt aus: resolved template.
     * @param string $departmentId
     * @param string $templateKey
     * @return array<string, mixed>
     */
    public static function resolvedTemplate(string $departmentId, string $templateKey): array
    {
        $resolved = NotificationTemplateSettings::resolvedTemplate(
            $departmentId,
            NotificationTemplateSettings::CATEGORY_CALENDAR,
            $templateKey
        );
        if ($resolved === null) {
            foreach (NotificationTemplateSettings::defaultBuiltinTemplates() as $builtin) {
                if ($builtin['event_slug'] === $templateKey) {
                    return [
                        'subject' => $builtin['subject'],
                        'title' => $builtin['title'],
                        'intro' => $builtin['intro'],
                    ];
                }
            }

            return ['subject' => '', 'title' => '', 'intro' => ''];
        }

        return [
            'subject' => (string) $resolved['subject'],
            'title' => (string) $resolved['title'],
            'intro' => (string) $resolved['intro'],
            'event_slug' => (string) ($resolved['event_slug'] ?? ''),
        ];
    }

    /**
     * Methode for form.
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        return NotificationTemplateSettings::forForm();
    }

    /**
     * Speichert Formulardaten.
     * @param array $input
     * @return void
     */
    public static function saveFromPost(array $input): void
    {
        NotificationTemplateSettings::saveFromPost($input);
    }
}
