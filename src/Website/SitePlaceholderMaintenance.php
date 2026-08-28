<?php
declare(strict_types=1);

/**
 * Wartungsseite für Domains ohne vollständiges CRM (Platzhalter-Instanzen).
 * Nutzt dieselbe View wie CRM-Instanzen; Texte aus config/site-maintenance.php im Site-Root.
 */
final class SitePlaceholderMaintenance
{
    private const CONFIG_FILE = 'config/site-maintenance.php';

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
    public static function config(string $siteRoot): array
    {
        $defaults = [
            'enabled' => true,
            'headline' => 'Die Seite befindet sich im Aufbau',
            'message' => 'Wir bereiten die Website vor. Bitte schauen Sie bald wieder vorbei.',
            'email' => '',
            'image_url' => WebsiteMaintenanceSettings::DEFAULT_IMAGE,
            'image_media_id' => '',
        ];

        $file = rtrim($siteRoot, '/') . '/' . self::CONFIG_FILE;
        if (!is_readable($file)) {
            return $defaults;
        }

        $local = require $file;
        if (!is_array($local)) {
            return $defaults;
        }

        return array_merge($defaults, $local);
    }

    public static function renderAndExit(string $siteRoot): never
    {
        WebsiteMaintenanceRenderer::send(self::config($siteRoot), 503);
    }

    /**
     * Wartungstexte aus CRM-Datenbank (Master-Code) — eine Quelle für Platzhalter-Domains.
     */
    public static function renderFromCrmRoot(string $crmRoot): never
    {
        WebsiteMaintenanceRenderer::send(self::configFromCrmRoot($crmRoot), 503);
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
    public static function configFromCrmRoot(string $crmRoot): array
    {
        if (!class_exists('WebsiteMaintenanceSettings')) {
            require_once rtrim($crmRoot, '/') . '/src/autoload.php';
        }

        if (!class_exists('Database') || !Database::isConfigured()) {
            return self::config($crmRoot);
        }

        return WebsiteMaintenanceSettings::config();
    }
}
