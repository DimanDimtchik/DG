<?php
declare(strict_types=1);

/**
 * Legt nach Installation (oder per CLI) Pflichtseiten, Startseite, Kontakt und Menü an.
 */
final class WebsiteBootstrapService
{
    /**
     * @param array{
     *   legal?: bool,
     *   homepage?: bool,
     *   contact?: bool,
     *   menu?: bool,
     *   maintenance?: bool,
     *   overwrite?: bool,
     *   enable_maintenance?: bool
     * } $options
     * @return array<string, mixed>
     */
    public static function bootstrap(?int $userId = null, array $options = []): array
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }

        MigrationRunner::runPending();
        WebsiteFormRepository::ensureTables();

        $legal = $options['legal'] ?? true;
        $homepage = $options['homepage'] ?? true;
        $contact = $options['contact'] ?? true;
        $menu = $options['menu'] ?? true;
        $maintenance = $options['maintenance'] ?? true;
        $overwrite = $options['overwrite'] ?? false;
        $enableMaintenance = $options['enable_maintenance'] ?? true;

        $result = [
            'legal' => [],
            'homepage' => null,
            'contact_page' => null,
            'contact_form_id' => null,
            'menu' => false,
            'maintenance' => false,
        ];

        $isFirstRun = WebsitePageRepository::list() === [];

        if ($legal) {
            $result['legal'] = LegalPageGenerator::generateAndSave($userId, $overwrite);
        }

        $formId = null;
        if ($contact) {
            $formId = WebsiteFormRepository::ensureDefaultContactForm($userId);
            $result['contact_form_id'] = $formId;

            $existingContact = WebsitePageRepository::findBySlugAnyStatus('kontakt');
            if ($existingContact === null || $overwrite) {
                $contactId = WebsitePageRepository::save([
                    'title' => 'Kontakt',
                    'slug' => 'kontakt',
                    'status' => WebsitePageRepository::STATUS_PUBLISHED,
                    'layout' => WebsiteHomepageTemplates::contactPageLayout($formId),
                ], $existingContact !== null ? (int) $existingContact['id'] : null, $userId);
                $result['contact_page'] = [
                    'id' => $contactId,
                    'slug' => 'kontakt',
                    'action' => $existingContact !== null ? 'updated' : 'created',
                ];
            } else {
                $result['contact_page'] = [
                    'id' => (int) $existingContact['id'],
                    'slug' => 'kontakt',
                    'action' => 'skipped',
                ];
            }
        }

        if ($homepage) {
            $existingHome = WebsitePageRepository::findBySlugAnyStatus('startseite');
            if ($existingHome === null || $overwrite) {
                $context = self::templateContext();
                $homeId = WebsitePageRepository::save([
                    'title' => 'Startseite',
                    'slug' => 'startseite',
                    'status' => WebsitePageRepository::STATUS_PUBLISHED,
                    'layout' => WebsiteHomepageTemplates::homepageLayout($context),
                ], $existingHome !== null ? (int) $existingHome['id'] : null, $userId);
                $result['homepage'] = [
                    'id' => $homeId,
                    'slug' => 'startseite',
                    'action' => $existingHome !== null ? 'updated' : 'created',
                    'kind' => WebsiteHomepageTemplates::primaryKind($context['business_kinds']),
                ];
            } else {
                $result['homepage'] = [
                    'id' => (int) $existingHome['id'],
                    'slug' => 'startseite',
                    'action' => 'skipped',
                ];
            }
        }

        if ($menu && ($overwrite || $isFirstRun)) {
            self::configureMenuAndChrome();
            $result['menu'] = true;
        }

        if ($maintenance) {
            self::configureMaintenance($enableMaintenance);
            $result['maintenance'] = $enableMaintenance;
        }

        return $result;
    }

    /** @return array{company_name: string, industry: string, city: string, phone: string, email: string, business_kinds: list<string>} */
    private static function templateContext(): array
    {
        $company = CompanySettings::config();
        $extended = CompanyExtendedSettings::config();
        $kinds = SettingsStore::get('install_business_kind', []);
        if (!is_array($kinds)) {
            $kinds = [];
        }

        return [
            'company_name' => trim((string) ($company['name'] ?? '')),
            'industry' => trim((string) ($extended['industry'] ?? '')),
            'city' => trim((string) ($company['city'] ?? '')),
            'phone' => trim((string) ($company['phone'] ?? '')),
            'email' => trim((string) ($company['email'] ?? '')),
            'business_kinds' => array_values(array_filter(array_map('strval', $kinds))),
        ];
    }

    private static function configureMenuAndChrome(): void
    {
        $company = CompanySettings::config();
        $name = trim((string) ($company['name'] ?? ''));
        $year = date('Y');

        SettingsStore::set('website.chrome', [
            'header_title' => $name,
            'header_tagline' => '',
            'footer_text' => '© ' . $year . ($name !== '' ? ' ' . $name : ''),
            'header_js' => '',
            'footer_js' => '',
            'ga_measurement_id' => '',
            'gtm_container_id' => '',
        ]);

        SettingsStore::set('website.menu', [
            'items' => [
                ['label' => 'Start', 'url' => '/', 'auth_only' => false, 'children' => []],
                ['label' => 'Kontakt', 'url' => '/kontakt', 'auth_only' => false, 'children' => []],
                [
                    'label' => 'Rechtliches',
                    'url' => '#',
                    'auth_only' => false,
                    'children' => [
                        ['label' => 'Impressum', 'url' => '/impressum', 'auth_only' => false, 'children' => []],
                        ['label' => 'Datenschutz', 'url' => '/datenschutz', 'auth_only' => false, 'children' => []],
                        ['label' => 'AGB', 'url' => '/agb', 'auth_only' => false, 'children' => []],
                    ],
                ],
            ],
        ]);

        $base = App::publicBaseUrl();
        if ($base !== '' && class_exists('EmailLayoutSettings')) {
            $layout = EmailLayoutSettings::config();
            $layout['footer_show_legal_links'] = true;
            $layout['footer_url_impressum'] = $base . '/impressum';
            $layout['footer_url_datenschutz'] = $base . '/datenschutz';
            $layout['footer_url_agb'] = $base . '/agb';
            if ($name !== '') {
                $layout['footer_company_name'] = $name;
            }
            SettingsStore::set(EmailLayoutSettings::STORE_KEY, $layout);
        }
    }

    private static function configureMaintenance(bool $enabled): void
    {
        $company = CompanySettings::config();
        $email = trim((string) ($company['email'] ?? ''));
        $defaults = WebsiteMaintenanceSettings::defaults();

        SettingsStore::set('website.maintenance', [
            'enabled' => $enabled,
            'headline' => $defaults['headline'],
            'message' => $defaults['message'],
            'email' => $email !== '' ? $email : $defaults['email'],
            'image_url' => WebsiteMaintenanceSettings::DEFAULT_IMAGE,
            'image_media_id' => '',
        ]);
    }
}
