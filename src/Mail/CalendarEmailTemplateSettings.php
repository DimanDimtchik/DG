<?php
declare(strict_types=1);

/** @deprecated Nutzen Sie NotificationTemplateSettings — Kompatibilität für Kalender-Versand. */
final class CalendarEmailTemplateSettings
{
    public const STORE_KEY = 'calendar_email_templates';

    public const TEMPLATE_CONFIRMATION = NotificationTemplateSettings::SLUG_CONFIRMATION;
    public const TEMPLATE_CANCELLATION = NotificationTemplateSettings::SLUG_CANCELLATION;
    public const TEMPLATE_ADMIN = NotificationTemplateSettings::SLUG_ADMIN;

    /** @return array<string, string> */
    public static function templateLabels(): array
    {
        $out = [];
        foreach (NotificationTemplateSettings::defaultBuiltinTemplates() as $template) {
            $out[(string) $template['event_slug']] = (string) $template['name'];
        }

        return $out;
    }

    /** @return array{subject: string, title: string, intro: string} */
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

    /** @return array<string, mixed> */
    public static function forForm(): array
    {
        return NotificationTemplateSettings::forForm();
    }

    /** @param array<string, mixed> $input */
    public static function saveFromPost(array $input): void
    {
        NotificationTemplateSettings::saveFromPost($input);
    }
}
