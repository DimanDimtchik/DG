<?php
declare(strict_types=1);

/** Einheitliche Wartungsseite für alle CRM-Instanzen (öffentlich, HTTP 503). */
final class WebsiteMaintenanceRenderer
{
    /**
     * @param array{
     *   enabled?: bool,
     *   headline?: string,
     *   message?: string,
     *   email?: string,
     *   image_url?: string,
     *   image_media_id?: string
     * } $maintenance
     */
    public static function send(array $maintenance, int $httpCode = 503): never
    {
        if ($httpCode === 503) {
            header('Retry-After: 3600');
        }
        header('Cache-Control: no-store, no-cache, must-revalidate');
        http_response_code($httpCode);
        View::render('website-maintenance', [
            'maintenance' => self::normalize($maintenance),
        ]);
        exit;
    }

    /**
     * @param array<string, mixed> $maintenance
     * @return array{
     *   enabled: bool,
     *   headline: string,
     *   message: string,
     *   email: string,
     *   image_url: string,
     *   image_media_id: string,
     *   use_inline_art: bool
     * }
     */
    public static function normalize(array $maintenance): array
    {
        $defaults = class_exists('WebsiteMaintenanceSettings')
            ? WebsiteMaintenanceSettings::defaults()
            : [
                'enabled' => true,
                'headline' => 'Die Seite befindet sich im Aufbau',
                'message' => 'Wir bereiten die Website vor. Bitte schauen Sie bald wieder vorbei.',
                'email' => '',
                'image_url' => '/assets/img/maintenance-aufbau.svg',
                'image_media_id' => '',
            ];

        $headline = trim((string) ($maintenance['headline'] ?? $defaults['headline']));
        $message = trim((string) ($maintenance['message'] ?? $defaults['message']));
        $email = trim((string) ($maintenance['email'] ?? $defaults['email']));
        $imageUrl = trim((string) ($maintenance['image_url'] ?? $defaults['image_url']));
        $imageMediaId = trim((string) ($maintenance['image_media_id'] ?? ''));

        if ($headline === '') {
            $headline = (string) $defaults['headline'];
        }
        if ($imageUrl === '') {
            $imageUrl = (string) $defaults['image_url'];
        }

        return [
            'enabled' => !empty($maintenance['enabled'] ?? $defaults['enabled']),
            'headline' => $headline,
            'message' => $message,
            'email' => $email,
            'image_url' => $imageUrl,
            'image_media_id' => $imageMediaId,
            'use_inline_art' => self::usesInlineArt($imageMediaId, $imageUrl),
        ];
    }

    /** Standard-Grafik (inline SVG) — kein Upload, keine externe SVG-Datei nötig. */
    public static function usesInlineArt(string $imageMediaId, string $imageUrl): bool
    {
        if ($imageMediaId !== '') {
            return false;
        }

        if (str_contains($imageUrl, 'maintenance-aufbau.svg')) {
            return true;
        }

        return $imageUrl === '' || str_ends_with(strtolower($imageUrl), '.svg');
    }
}
