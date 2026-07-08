<?php
declare(strict_types=1);

final class MenuRegistry
{
    /** @return list<array{slug: string, label: string, icon: string}> */
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

        return $items;
    }

    /** @return list<array{slug: string, label: string, icon: string, href: string}> */
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
                    'slug' => 'buchhaltung-jahresabschluss',
                    'label' => 'Jahresabschluss',
                    'icon' => 'yearclose',
                    'href' => '/app?page=buchhaltung-jahresabschluss',
                ],
            ],
        ];
    }

    public static function canAccessBuchhaltung(User $user): bool
    {
        if (RoleResolver::isCustomer($user)) {
            return false;
        }

        return RoleResolver::isAdmin($user) || DepartmentAccess::canAccessModule($user, 'buchhaltung');
    }

    /** Kurztexte für Dashboard-Kacheln (gleiche Reihenfolge wie Seitennavigation). */
    /** @return array<string, string> */
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
            'buchhaltung-jahresabschluss' => 'Geschäftsjahr abschließen und Salden vortragen.',
            'einstellungen' => 'Firma, E-Mail, Module und System konfigurieren.',
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

    /** @return array{slug: string, label: string, icon: string}|null */
    public static function settingsItem(User $user): ?array
    {
        if (!SettingsRegistry::canAccess($user)) {
            return null;
        }

        return ['slug' => 'einstellungen', 'label' => 'Einstellungen', 'icon' => 'settings'];
    }

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
            || $slug === 'buchhaltung-jahresabschluss'
        ) {
            return self::canAccessBuchhaltung($user);
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
