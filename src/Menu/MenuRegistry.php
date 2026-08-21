<?php
declare(strict_types=1);

/**
 * CRM-Navigation: Module, Seitenleiste, Berechtigungen und Dashboard-Kacheln.
 */
final class MenuRegistry
{
    /**
     * Hauptmodule für die Seitenleiste (ohne Dashboard/Einstellungen).
     *
     * @return list<array{slug: string, label: string, icon: string}>
     */
    public static function modules(User $user): array
    {
        if (RoleResolver::isCustomer($user)) {
            return [];
        }

        $items = [];
        $canEdit = RoleResolver::canEdit($user);

        if (DepartmentAccess::canAccessModule($user, 'kontakte') && $canEdit) {
            $items[] = ['slug' => 'kontakte', 'label' => 'Kontakte', 'icon' => 'contacts'];
        }
        if (DepartmentAccess::canAccessModule($user, 'terminkalender') && $canEdit) {
            $items[] = ['slug' => 'terminkalender', 'label' => 'Terminkalender', 'icon' => 'calendar'];
        }
        if (DepartmentAccess::canAccessModule($user, 'post') && $canEdit) {
            $items[] = ['slug' => 'post', 'label' => 'Post', 'icon' => 'mail'];
        }
        if (DepartmentAccess::userCanManageArticleCatalog($user) && $canEdit) {
            $items[] = ['slug' => 'artikel-leistungen', 'label' => 'Artikel & Leistungen', 'icon' => 'catalog'];
        }
        if (RoleResolver::isAdmin($user)) {
            $items[] = ['slug' => 'support-freigabe', 'label' => 'Support-Freigabe', 'icon' => 'settings'];
        }
        if (class_exists('SupportSession') && SupportSession::isActive()) {
            $items[] = ['slug' => 'support-zuschauen', 'label' => 'Bildschirm zuschauen', 'icon' => 'dashboard'];
        }

        return $items;
    }

    /**
     * Vollständige Seitenleiste inkl. Dashboard und href-Links.
     *
     * @return list<array{slug: string, label: string, icon: string, href: string}>
     */
    public static function sidebarItems(User $user): array
    {
        if (RoleResolver::isCustomer($user)) {
            return [
                ['slug' => 'profile', 'label' => 'Mein Profil', 'icon' => 'profile', 'href' => '/app?area=profile'],
            ];
        }

        $items = [];
        if (DepartmentAccess::canAccessModule($user, 'dashboard')) {
            $items[] = ['slug' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => '/app'];
        }

        foreach (self::modules($user) as $module) {
            $items[] = [
                'slug' => $module['slug'],
                'label' => $module['label'],
                'icon' => $module['icon'],
                'href' => '/app?page=' . $module['slug'],
            ];
        }

        if (RoleResolver::isAdmin($user)) {
            $items[] = [
                'slug' => 'bilder',
                'label' => 'Bilder',
                'icon' => 'images',
                'href' => '/app?page=bilder',
            ];
        }

        return $items;
    }

    /**
     * Website-Bereich in der Seitenleiste (eigene Sektion mit Untermenü).
     *
     * @return array{label: string, items: list<array{slug: string, label: string, icon: string, href: string}>}|null
     */
    public static function websiteSection(User $user): ?array
    {
        if (!self::canAccessWebsite($user)) {
            return null;
        }

        return [
            'label' => 'Website',
            'items' => [
                [
                    'slug' => 'website-seiten',
                    'label' => 'Seiten',
                    'icon' => 'document',
                    'href' => '/app?page=website-seiten',
                ],
                [
                    'slug' => 'website-formulare',
                    'label' => 'Formulare',
                    'icon' => 'mail',
                    'href' => '/app?page=website-formulare',
                ],
                [
                    'slug' => 'website-statistik',
                    'label' => 'Statistik',
                    'icon' => 'ledger',
                    'href' => '/app?page=website-statistik',
                ],
                [
                    'slug' => 'website-menu',
                    'label' => 'Menü',
                    'icon' => 'nav',
                    'href' => '/app?page=website-menu',
                ],
                [
                    'slug' => 'website-chrome',
                    'label' => 'Kopf & Fuß',
                    'icon' => 'layout',
                    'href' => '/app?page=website-chrome',
                ],
                [
                    'slug' => 'website-design',
                    'label' => 'Design',
                    'icon' => 'palette',
                    'href' => '/app?page=website-design',
                ],
            ],
        ];
    }

    /**
     * KDV = SaaS-Kunden von Ganz Soft (nur Admin). Nicht CRM-Kontakte.
     *
     * @return array{label: string, items: list<array{slug: string, label: string, icon: string, href: string}>}|null
     */
    public static function kdvSection(User $user): ?array
    {
        if (!self::canAccessKdv($user)) {
            return null;
        }

        return [
            'label' => 'SaaS-Kunden (Ganz Soft)',
            'items' => [
                ['slug' => 'kdv-dashboard', 'label' => 'Übersicht', 'icon' => 'dashboard', 'href' => '/app?page=kdv-dashboard'],
                ['slug' => 'kdv-kunden', 'label' => 'SaaS-Kunden', 'icon' => 'contacts', 'href' => '/app?page=kdv-kunden'],
                ['slug' => 'kdv-support', 'label' => 'Support-Freigaben', 'icon' => 'settings', 'href' => '/app?page=kdv-support'],
            ],
        ];
    }

    /** Nur Haupt-Admin (KDV = Ihre SaaS-Kunden, nicht CRM-Kontakte). */
    public static function canAccessKdv(User $user): bool
    {
        return RoleResolver::isAdmin($user);
    }

    /** Admin oder Modul-Berechtigung „website“. */
    public static function canAccessWebsite(User $user): bool
    {
        if (RoleResolver::isCustomer($user)) {
            return false;
        }

        return RoleResolver::isAdmin($user) || DepartmentAccess::canAccessModule($user, 'website');
    }

    /**
     * Buchhaltungs-Bereich in der Seitenleiste (eigene Sektion mit Untermenü).
     *
     * @return array{label: string, items: list<array{slug: string, label: string, icon: string, href: string}>}|null
     */
    public static function buchhaltungSection(User $user): ?array
    {
        if (!self::canAccessBuchhaltung($user)) {
            return null;
        }

        return [
            'label' => 'Buchhaltung',
            'items' => [
                [
                    'slug' => 'buchhaltung-konten',
                    'label' => 'Konten',
                    'icon' => 'accounting',
                    'href' => '/app?page=buchhaltung-konten',
                ],
                [
                    'slug' => 'buchhaltung-belege',
                    'label' => 'Belege',
                    'icon' => 'receipt',
                    'href' => '/app?page=buchhaltung-belege',
                ],
                [
                    'slug' => 'buchhaltung-ueberweisungen',
                    'label' => 'Überweisungen',
                    'icon' => 'transfer',
                    'href' => '/app?page=buchhaltung-ueberweisungen',
                ],
                [
                    'slug' => 'buchhaltung-kontenuebersicht',
                    'label' => 'Kontenübersicht',
                    'icon' => 'ledger',
                    'href' => '/app?page=buchhaltung-kontenuebersicht',
                ],
                [
                    'slug' => 'buchhaltung-opos',
                    'label' => 'Offene Posten',
                    'icon' => 'transfer',
                    'href' => '/app?page=buchhaltung-opos',
                ],
                [
                    'slug' => 'buchhaltung-kassenbuch',
                    'label' => 'Kassenbuch',
                    'icon' => 'receipt',
                    'href' => '/app?page=buchhaltung-kassenbuch',
                ],
                [
                    'slug' => 'buchhaltung-datev-export',
                    'label' => 'DATEV-Export',
                    'icon' => 'document',
                    'href' => '/app?page=buchhaltung-datev-export',
                ],
                [
                    'slug' => 'buchhaltung-jahresabschluss',
                    'label' => 'Jahresabschluss',
                    'icon' => 'yearclose',
                    'href' => '/app?page=buchhaltung-jahresabschluss',
                ],
            ],
        ];
    }

    /** Admin oder Modul-Berechtigung „buchhaltung“. */
    public static function canAccessBuchhaltung(User $user): bool
    {
        if (RoleResolver::isCustomer($user)) {
            return false;
        }

        return RoleResolver::isAdmin($user) || DepartmentAccess::canAccessModule($user, 'buchhaltung');
    }

    /** Kurztexte für Dashboard-Kacheln (gleiche Reihenfolge wie Seitennavigation). */
    /**
     * @return array<string, string> slug => Beschreibung
     */
    public static function moduleDescriptions(): array
    {
        return [
            'profile' => 'Persönliche Daten und Zugang verwalten.',
            'kontakte' => 'Benutzer, Kunden, Lieferanten und Mitarbeiter verwalten.',
            'terminkalender' => 'Buchungen, Artikel und Kalender im Blick behalten.',
            'post' => 'Postfächer, Eingang und Nachrichten versenden.',
            'artikel-leistungen' => 'Artikel- und Leistungskatalog pflegen.',
            'bilder' => 'Medien, Logos und Bilder verwalten.',
            'buchhaltung-konten' => 'Kontenrahmen durchsuchen und Kontenhinweise einsehen.',
            'buchhaltung-belege' => 'Belege erfassen mit Steuerfeldern und Kontenzuordnung.',
            'buchhaltung-ueberweisungen' => 'Überweisungen vorbereiten mit QR-Code und Fotovorlage.',
            'buchhaltung-kontenuebersicht' => 'Kontensalden und Kontoauszüge je Geschäftsjahr.',
            'buchhaltung-opos' => 'Offene Forderungen und Verbindlichkeiten (OPOS) mit Personenkonten.',
            'buchhaltung-kassenbuch' => 'Bar-Ein- und Ausgänge aus Kassenbelegen.',
            'buchhaltung-datev-export' => 'Buchungsstapel im DATEV EXTF-Format exportieren.',
            'buchhaltung-jahresabschluss' => 'Geschäftsjahr abschließen und Salden vortragen.',
            'einstellungen' => 'Firma, E-Mail, Module und System konfigurieren.',
            'website-seiten' => 'Seiten der öffentlichen Website anlegen und gestalten.',
            'website-formulare' => 'Formulare visuell bauen, Einträge empfangen und in Seiten einbinden.',
            'website-statistik' => 'Seitenaufrufe lokal und Links zu Google Analytics / Tag Manager.',
            'website-menu' => 'Navigation der Website pflegen.',
            'website-chrome' => 'Kopfzeile, Fußzeile und zusätzliche Skripte.',
            'website-design' => 'Farben der öffentlichen Website.',
        ];
    }

    /**
     * Alle Navigationspunkte für das Dashboard (ohne „Dashboard“ selbst).
     *
     * @return list<array{slug: string, label: string, icon: string, href: string, description: string}>
     */
    public static function dashboardTiles(User $user): array
    {
        $descriptions = self::moduleDescriptions();
        $tiles = [];

        foreach (self::sidebarItems($user) as $item) {
            if ($item['slug'] === 'dashboard') {
                continue;
            }
            $tiles[] = [
                'slug' => $item['slug'],
                'label' => $item['label'],
                'icon' => $item['icon'],
                'href' => $item['href'],
                'description' => $descriptions[$item['slug']] ?? '',
            ];
        }

        $buchhaltung = self::buchhaltungSection($user);
        if ($buchhaltung !== null) {
            foreach ($buchhaltung['items'] as $item) {
                $tiles[] = [
                    'slug' => $item['slug'],
                    'label' => $item['label'],
                    'icon' => $item['icon'],
                    'href' => $item['href'],
                    'description' => $descriptions[$item['slug']] ?? '',
                ];
            }
        }

        $website = self::websiteSection($user);
        if ($website !== null) {
            foreach ($website['items'] as $item) {
                $tiles[] = [
                    'slug' => $item['slug'],
                    'label' => $item['label'],
                    'icon' => $item['icon'],
                    'href' => $item['href'],
                    'description' => $descriptions[$item['slug']] ?? '',
                ];
            }
        }

        $settings = self::settingsItem($user);
        if ($settings !== null) {
            $tiles[] = [
                'slug' => $settings['slug'],
                'label' => $settings['label'],
                'icon' => $settings['icon'],
                'href' => '/app?page=einstellungen',
                'description' => $descriptions['einstellungen'] ?? '',
            ];
        }

        return $tiles;
    }

    /** @return array{slug: string, label: string, icon: string}|null Einstellungen-Menüpunkt oder null. */
    public static function settingsItem(User $user): ?array
    {
        if (!SettingsRegistry::canAccess($user)) {
            return null;
        }

        return ['slug' => 'einstellungen', 'label' => 'Einstellungen', 'icon' => 'settings'];
    }

    /**
     * Prüft Zugriff auf eine CRM-Seite anhand des Slugs.
     */
    public static function canAccess(User $user, string $slug): bool
    {
        if (RoleResolver::isCustomer($user)) {
            return false;
        }

        if ($slug === 'dashboard') {
            return DepartmentAccess::canAccessModule($user, 'dashboard');
        }

        if ($slug === 'einstellungen') {
            return SettingsRegistry::canAccess($user);
        }

        if ($slug === 'bilder') {
            return RoleResolver::isAdmin($user);
        }

        if (
            $slug === 'buchhaltung-konten'
            || $slug === 'buchhaltung-belege'
            || $slug === 'buchhaltung-beleg-form'
            || $slug === 'buchhaltung-ueberweisungen'
            || $slug === 'buchhaltung-kontenuebersicht'
            || $slug === 'buchhaltung-opos'
            || $slug === 'buchhaltung-kassenbuch'
            || $slug === 'buchhaltung-datev-export'
            || $slug === 'buchhaltung-jahresabschluss'
        ) {
            return self::canAccessBuchhaltung($user);
        }

        if ($slug === 'kdv-dashboard' || $slug === 'kdv-kunden' || $slug === 'kdv-kunde-form' || $slug === 'kdv-provision' || $slug === 'kdv-support') {
            return self::canAccessKdv($user);
        }

        if ($slug === 'support-freigabe') {
            return RoleResolver::isAdmin($user);
        }

        if ($slug === 'support-zuschauen') {
            return class_exists('SupportSession') && SupportSession::isActive();
        }

        if (
            $slug === 'website-seiten'
            || $slug === 'website-seite-form'
            || $slug === 'website-formulare'
            || $slug === 'website-formular-form'
            || $slug === 'website-formular-inbox'
            || $slug === 'website-statistik'
            || $slug === 'website-menu'
            || $slug === 'website-chrome'
            || $slug === 'website-design'
        ) {
            return self::canAccessWebsite($user);
        }

        if ($slug === 'artikel-leistungen') {
            return DepartmentAccess::userCanManageArticleCatalog($user) && RoleResolver::canEdit($user);
        }

        if ($slug === 'post') {
            return DepartmentAccess::canAccessModule($user, 'post') && RoleResolver::canEdit($user);
        }

        foreach (self::modules($user) as $item) {
            if ($item['slug'] === $slug) {
                return true;
            }
        }

        return false;
    }
}
