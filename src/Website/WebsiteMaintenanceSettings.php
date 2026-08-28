<?php
declare(strict_types=1);

/**
 * Öffentlicher Wartungsmodus der Website (SettingsStore).
 */
final class WebsiteMaintenanceSettings
{
    private const STORE_KEY = 'website.maintenance';

    public const DEFAULT_IMAGE = '/assets/img/maintenance-aufbau.svg';

    /** Hinweis, wenn weder Wartungs- noch Firmen-E-Mail gesetzt ist. */
    public const NO_PUBLIC_CONTACT_MESSAGE = 'Öffentliche Kontaktdaten noch nicht gesetzt.';

    /**
     * @return array{
     *   enabled: bool,
     *   headline: string,
     *   message: string,
     *   email: string,
     *   image_url: string,
     *   image_media_id: string
     * }
     */
    public static function defaults(): array
    {
        $email = '';
        if (class_exists('CompanySettings')) {
            $email = CompanySettings::mailEmail();
        }

        return [
            'enabled' => false,
            'headline' => 'Die Seite befindet sich im Aufbau',
            'message' => 'Wir bereiten die Website vor. Bitte schauen Sie bald wieder vorbei.',
            'email' => $email,
            'image_url' => self::DEFAULT_IMAGE,
            'image_media_id' => '',
        ];
    }

    /**
     * @return array{
     *   enabled: bool,
     *   headline: string,
     *   message: string,
     *   email: string,
     *   image_url: string,
     *   image_media_id: string
     * }
     */
    public static function config(): array
    {
        $defaults = self::defaults();
        if (!class_exists('SettingsStore') || !Database::isConfigured()) {
            return $defaults;
        }

        $stored = SettingsStore::get(self::STORE_KEY, $defaults);
        if (!is_array($stored)) {
            $stored = [];
        }

        $cfg = array_merge($defaults, $stored);
        $cfg['enabled'] = !empty($cfg['enabled']);
        $cfg['headline'] = trim((string) ($cfg['headline'] ?? $defaults['headline']));
        $cfg['message'] = trim((string) ($cfg['message'] ?? $defaults['message']));
        $cfg['email'] = trim((string) ($cfg['email'] ?? $defaults['email']));
        $cfg['image_url'] = trim((string) ($cfg['image_url'] ?? $defaults['image_url']));
        $cfg['image_media_id'] = trim((string) ($cfg['image_media_id'] ?? ''));

        if ($cfg['headline'] === '') {
            $cfg['headline'] = $defaults['headline'];
        }
        if ($cfg['image_url'] === '') {
            $cfg['image_url'] = self::DEFAULT_IMAGE;
        }
        $cfg['email'] = self::resolvePublicContactEmail($cfg);

        return $cfg;
    }

    /**
     * Wartungs-E-Mail aus DB, sonst Firmen-E-Mail aus CRM (Einstellungen → Firma).
     */
    public static function resolvePublicContactEmail(array $cfg): string
    {
        $email = trim((string) ($cfg['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        if (class_exists('CompanySettings')) {
            return CompanySettings::mailEmail();
        }

        return '';
    }

    /** Öffentliche Wartungsseite wenn keine DB (Platzhalter-Instanz, gleicher Code). */
    public static function renderPlaceholderMaintenance(): never
    {
        WebsiteMaintenanceRenderer::send(
            array_merge(self::defaults(), ['enabled' => true]),
            503
        );
    }

    /** Wartungsseite für anonyme Besucher der öffentlichen Website? */
    public static function isActive(): bool
    {
        return self::config()['enabled'] === true;
    }

    /**
     * Speichert Toggle + Texte; optional Bild-Upload aus $_FILES['maintenance_image'].
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed>|null $file One $_FILES entry or null
     */
    public static function save(array $post, ?array $file = null, ?int $userId = null): void
    {
        $current = self::config();
        $defaults = self::defaults();

        $headline = mb_substr(trim((string) ($post['headline'] ?? '')), 0, 160);
        $message = mb_substr(trim((string) ($post['message'] ?? '')), 0, 500);
        $email = mb_substr(trim((string) ($post['email'] ?? '')), 0, 191);

        if ($headline === '') {
            $headline = $defaults['headline'];
        }
        if ($message === '') {
            $message = $defaults['message'];
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Bitte eine gültige E-Mail-Adresse eingeben.');
        }

        $imageUrl = $current['image_url'];
        $imageMediaId = $current['image_media_id'];

        if (!empty($post['reset_image'])) {
            $imageUrl = self::DEFAULT_IMAGE;
            $imageMediaId = '';
        } elseif ($file !== null && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Bild-Upload fehlgeschlagen.');
            }
            MediaRepository::ensureTables();
            $mediaId = MediaId::generate();
            $stored = MediaStorage::storeUpload($mediaId, $file);
            $stored['source_note'] = 'Website-Wartungsmodus';
            $stored['title'] = 'Wartungsmodus-Hintergrund';
            $stored['alt_text'] = 'Wartungsmodus';
            MediaRepository::insert($mediaId, $stored, $userId);
            $imageUrl = MediaStorage::publicUrl($mediaId);
            $imageMediaId = $mediaId;
        }

        SettingsStore::set(self::STORE_KEY, [
            'enabled' => !empty($post['enabled']),
            'headline' => $headline,
            'message' => $message,
            'email' => $email,
            'image_url' => $imageUrl,
            'image_media_id' => $imageMediaId,
        ]);
    }

    /** Rendert die öffentliche Wartungsseite und beendet den Request. */
    public static function renderAndExit(): never
    {
        WebsiteMaintenanceRenderer::send(self::config(), 503);
    }
}
