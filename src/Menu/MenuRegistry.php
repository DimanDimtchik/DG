<?php
declare(strict_types=1);

/**
 * CRM-Navigation: Module, Seitenleiste, Berechtigungen und Dashboard-Kacheln.
 */
final class MenuRegistry
{
    /**
     * Hauptmodule für die Seitenleiste „Navigation“ (ohne Dashboard/Einstellungen/Sektionen).
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
        // „Post“ / E-Mail-Postfächer → kommunikationSection()
        if (RoleResolver::isAdmin($user)) {
            $items[] = ['slug' => 'support-freigabe', 'label' => 'Support-Freigabe', 'icon' => 'settings'];
        }
        if (class_exists('SupportSession') && SupportSession::isActive()) {
            $items[] = ['slug' => 'support-zuschauen', 'label' => 'Bildschirm zuschauen', 'icon' => 'dashboard'];
        }

        return $items;
    }

    /**
     * HR-Bereich: Akademie + Zeiterfassung (zwischen Navigation und Warenwirtschaft/Buchhaltung).
     *
     * @return array{label: string, items: list<array{slug: string, label: string, icon: string, href: string}>}|null
     */
    public static function hrSection(User $user): ?array
    {
        if (RoleResolver::isCustomer($user)) {
            return null;
        }

        $items = [];
        if (RoleResolver::canEdit($user)) {
            $items[] = [
                'slug' => 'akademie',
                'label' => 'Akademie',
                'icon' => 'document',
                'href' => '/app?page=akademie',
            ];
        }
        if (TeamAccess::canView($user)) {
            $items[] = [
                'slug' => 'hr-abteilungen',
                'label' => 'Abteilung',
                'icon' => 'contacts',
                'href' => '/app?page=hr-abteilungen',
            ];
            $items[] = [
                'slug' => 'hr-teams',
                'label' => 'Team',
                'icon' => 'contacts',
                'href' => '/app?page=hr-teams',
            ];
        }
        if (DepartmentAccess::canAccessModule($user, 'zeiterfassung') && RoleResolver::canEdit($user)) {
            $items[] = [
                'slug' => 'zeiterfassung',
                'label' => 'Zeiterfassung',
                'icon' => 'clock',
                'href' => '/app?page=zeiterfassung',
            ];
        }

        if ($items === []) {
            return null;
        }

        return ['label' => 'HR', 'items' => $items];
    }

    /**
     * Dienstleistungen: Auftrag/Angebot + Leistungen (zwischen Navigation und HR).
     *
     * @return array{label: string, items: list<array{slug: string, label: string, icon: string, href: string}>}|null
     */
    public static function dienstleistungenSection(User $user): ?array
    {
        if (RoleResolver::isCustomer($user)) {
            return null;
        }

        $items = [];
        if (self::canAccessBuchhaltung($user)) {
            $items[] = [
                'slug' => 'dienstleistungen-auftraege',
                'label' => 'Auftrag/Angebot',
                'icon' => 'receipt',
                'href' => '/app?page=buchhaltung-belege&focus=dienstleistungen',
            ];
        }
        if (DepartmentAccess::userCanManageArticleCatalog($user) && RoleResolver::canEdit($user)) {
            $items[] = [
                'slug' => 'artikel-leistungen',
                'label' => 'Leistungen',
                'icon' => 'catalog',
                'href' => '/app?page=artikel-leistungen',
            ];
        }

        if ($items === []) {
            return null;
        }

        return ['label' => 'Dienstleistungen', 'items' => $items];
    }

    /**
     * Warenwirtschaft: Katalog, Lager, Rezeptur.
     *
     * @return array{label: string, items: list<array{slug: string, label: string, icon: string, href: string}>}|null
     */
    public static function warenwirtschaftSection(User $user): ?array
    {
        if (RoleResolver::isCustomer($user) || !RoleResolver::canEdit($user)) {
            return null;
        }
        if (!DepartmentAccess::userCanManageArticleCatalog($user)) {
            return null;
        }

        $items = [
            [
                'slug' => 'artikel-leistungen',
                'label' => 'Artikel',
                'icon' => 'catalog',
                'href' => '/app?page=artikel-leistungen',
            ],
            [
                'slug' => 'lager',
                'label' => 'Lager',
                'icon' => 'warehouse',
                'href' => '/app?page=lager',
            ],
        ];
        if (self::isRezepturEnabled()) {
            $items[] = [
                'slug' => 'rezeptur',
                'label' => 'Rezeptur',
                'icon' => 'document',
                'href' => '/app?page=rezeptur',
            ];
        }
        if (self::isReklamationEnabled()) {
            $items[] = [
                'slug' => 'reklamation',
                'label' => 'Reklamation',
                'icon' => 'mail',
                'href' => '/app?page=reklamation',
            ];
        }

        return ['label' => 'Warenwirtschaft', 'items' => $items];
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
                'label' => 'Media',
                'icon' => 'images',
                'href' => '/app?page=bilder',
            ];
        }

        return $items;
    }

    /**
     * Kommunikation: Postfächer, Telefon (Platzhalter), Dokumentenverfasser (Platzhalter).
     *
     * @return array{label: string, items: list<array{slug: string, label: string, icon: string, href: string}>}|null
     */
    public static function kommunikationSection(User $user): ?array
    {
        if (RoleResolver::isCustomer($user) || !RoleResolver::canEdit($user)) {
            return null;
        }

        $items = [];
        if (DepartmentAccess::canAccessModule($user, 'post')) {
            $items[] = [
                'slug' => 'post',
                'label' => 'E-Mail-Postfächer',
                'icon' => 'mail',
                'href' => '/app?page=post',
            ];
        }

        // Telefon: Platzhalter, sichtbar sobald Post oder Kontakte
        if ($items !== [] || DepartmentAccess::canAccessModule($user, 'kontakte')) {
            $items[] = [
                'slug' => 'telefon',
                'label' => 'Telefon',
                'icon' => 'phone',
                'href' => '/app?page=telefon',
            ];
        }

        if (DepartmentAccess::canAccessModule($user, 'dokumentenverfasser')) {
            $items[] = [
                'slug' => 'dokumentenverfasser',
                'label' => 'Dokumentenverfasser',
                'icon' => 'document',
                'href' => '/app?page=dokumentenverfasser',
            ];
        }

        if ($items === []) {
            return null;
        }

        return ['label' => 'Kommunikation', 'items' => $items];
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

        $items = [
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
        ];
        if (self::isGutscheinEnabled()) {
            $items[] = [
                'slug' => 'website-gutschein-flyer',
                'label' => 'Gutschein-Flyer',
                'icon' => 'document',
                'href' => '/app?page=website-gutschein-flyer',
            ];
        }
        $items = array_merge($items, [
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
        ]);

        return ['label' => 'Website', 'items' => $items];
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

        $section = [
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
                    'slug' => 'gutscheine',
                    'label' => 'Gutscheine',
                    'icon' => 'receipt',
                    'href' => '/app?page=gutscheine',
                ],
                [
                    'slug' => 'gutscheine-flyer-vorlagen',
                    'label' => 'Flyer-Vorlagen',
                    'icon' => 'document',
                    'href' => '/app?page=gutscheine-flyer-vorlagen',
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
                    'slug' => 'buchhaltung-manuelle-buchung',
                    'label' => 'Manuelle Buchungen',
                    'icon' => 'ledger',
                    'href' => '/app?page=buchhaltung-manuelle-buchung',
                ],
                [
                    'slug' => 'buchhaltung-auswertungen',
                    'label' => 'Bilanz & GuV',
                    'icon' => 'ledger',
                    'href' => '/app?page=buchhaltung-auswertungen',
                ],
                [
                    'slug' => 'buchhaltung-bwa',
                    'label' => 'BWA',
                    'icon' => 'ledger',
                    'href' => '/app?page=buchhaltung-bwa',
                ],
                [
                    'slug' => 'buchhaltung-susa',
                    'label' => 'SuSa',
                    'icon' => 'ledger',
                    'href' => '/app?page=buchhaltung-susa',
                ],
                [
                    'slug' => 'buchhaltung-bankabgleich',
                    'label' => 'Bankabgleich',
                    'icon' => 'transfer',
                    'href' => '/app?page=buchhaltung-bankabgleich',
                ],
                [
                    'slug' => 'buchhaltung-steuerberater-export',
                    'label' => 'Steuerberater-Export',
                    'icon' => 'document',
                    'href' => '/app?page=buchhaltung-steuerberater-export',
                ],
                [
                    'slug' => 'buchhaltung-ustva',
                    'label' => 'UStVA',
                    'icon' => 'document',
                    'href' => '/app?page=buchhaltung-ustva',
                ],
                [
                    'slug' => 'buchhaltung-jahresabschluss',
                    'label' => 'Jahresabschluss',
                    'icon' => 'yearclose',
                    'href' => '/app?page=buchhaltung-jahresabschluss',
                ],
                [
                    'slug' => 'buchhaltung-anschaffungsrechner',
                    'label' => 'Anschaffungsrechner',
                    'icon' => 'document',
                    'href' => '/app?page=buchhaltung-anschaffungsrechner',
                ],
            ],
        ];

        if (!self::isGutscheinEnabled()) {
            $section['items'] = array_values(array_filter(
                $section['items'],
                static fn (array $item): bool => !in_array($item['slug'], ['gutscheine', 'gutscheine-flyer-vorlagen'], true)
            ));
        }

        return $section;
    }

    /** Admin oder Modul-Berechtigung „buchhaltung“. */
    public static function canAccessBuchhaltung(User $user): bool
    {
        if (RoleResolver::isCustomer($user)) {
            return false;
        }

        return RoleResolver::isAdmin($user) || DepartmentAccess::canAccessModule($user, 'buchhaltung');
    }

    /**
     * Kurztexte für Dashboard-Kacheln (Icon + Titel + Beschreibung).
     * Ziel: 2–3 Zeilen, sachlich, überall vorhanden.
     *
     * @return array<string, string> slug => Beschreibung
     */
    public static function moduleDescriptions(): array
    {
        return [
            'profile' => 'Persönliche Daten, Passwort und Zugangseinstellungen für Ihr Konto.',
            'kontakte' => 'Kunden, Lieferanten, Mitarbeiter und Behörden mit allen Unternehmensfeldern.',
            'terminkalender' => 'Termine, Buchungen und Kalenderbereiche — Planung und Abstimmung im Team.',
            'akademie' => 'Schulungen, Erklärvideos und Zertifikate zu den CRM-Modulen.',
            'hr-abteilungen' => 'Abteilungen anlegen und Mitarbeitern zuordnen — Grundlage für Rechte und Akademie.',
            'hr-teams' => 'Teams innerhalb der Abteilungen pflegen und Ansprechpartner zuweisen.',
            'zeiterfassung' => 'Einstempeln, Pausen und Arbeitszeiten — Übersicht für Mitarbeiter und HR.',
            'post' => 'E-Mail-Postfächer im CRM: Eingang lesen und Nachrichten versenden.',
            'telefon' => 'Click-to-Call, Anrufliste und Popup bei eingehenden Anrufen.',
            'dokumentenverfasser' => 'Briefe und Texte mit Briefpapier — Entwurf, Finalisierung und Export.',
            'artikel-leistungen' => 'Artikel- und Leistungskatalog — Basis für Angebote, Belege und Lager.',
            'dienstleistungen-auftraege' => 'Angebote und Aufträge für Dienstleistungen erfassen und verfolgen.',
            'lager' => 'Bestände, Orte und Bewegungen — von Wareneingang bis Inventur.',
            'rezeptur' => 'Stücklisten und Fertigungszeit — Vorkalkulation wächst schrittweise nach.',
            'bilder' => 'Media-Bibliothek für Logos, Fotos und Grafiken im System.',
            'buchhaltung-konten' => 'Kontenrahmen durchsuchen und Kontenhinweise für die Buchführung.',
            'buchhaltung-belege' => 'Angebot bis Rechnung erfassen — Belegkette, Steuerfelder und Konten.',
            'gutscheine' => 'Mehrzweck-Gutscheine verkaufen, einlösen und aus Reklamation ausstellen.',
            'gutscheine-form' => 'Einzelnen Gutschein anlegen, prüfen oder stornieren.',
            'gutscheine-flyer-vorlagen' => 'Gutschein-Flyer-Vorlagen: Format, Hintergrund, Texte, QR und Handschrift-Zone.',
            'reklamation' => 'Reklamationen von der Annahme bis zu Maßnahmen und Entschuldigung.',
            'reklamation-form' => 'Reklamation erfassen, Maßnahmen festlegen und Gutschein ausstellen.',
            'website-gutschein-flyer' => 'Gutschein-Formular-Flyer drucken — Vorlage + Gutscheinnummer auf dem Blatt.',
            'buchhaltung-gutscheine' => 'Schneller Einstieg in die Gutschein-Verwaltung aus der Buchhaltung.',
            'buchhaltung-ueberweisungen' => 'Zahlungen vorbereiten — inkl. QR-Code und Fotovorlage für die Bank.',
            'buchhaltung-kontenuebersicht' => 'Kontensalden und Auszüge je Geschäftsjahr auf einen Blick.',
            'buchhaltung-opos' => 'Offene Forderungen und Verbindlichkeiten mit Personenkonten.',
            'buchhaltung-kassenbuch' => 'Bar-Ein- und Ausgänge aus Kassenbelegen dokumentieren.',
            'buchhaltung-manuelle-buchung' => 'Freie Journalbuchungen ohne Beleg — Soll gleich Haben.',
            'buchhaltung-auswertungen' => 'Bilanz und GuV je Geschäftsjahr für Überblick und Prüfung.',
            'buchhaltung-bankabgleich' => 'CAMT.053 importieren und Umsätze Belegen zuordnen.',
            'buchhaltung-steuerberater-export' => 'DATEV, Agenda oder Addison — Buchungsstapel und Belege exportieren.',
            'buchhaltung-ustva' => 'Umsatzsteuer-Voranmeldung und ELSTER-CSV vorbereiten.',
            'buchhaltung-jahresabschluss' => 'Checkliste, GuV-Abschluss und Saldenvortrag — DIY-Assistent.',
            'buchhaltung-anschaffungsrechner' => 'Barkauf, Leasing, Rate und Miete mit AfA- und Steuerorientierung vergleichen.',
            'buchhaltung-bwa' => 'Betriebswirtschaftliche Auswertung — Monatsüberblick fürs Management.',
            'buchhaltung-susa' => 'Summen- und Saldenliste kompakt für Prüfung und Steuerberater.',
            'support-freigabe' => 'Zeitlich begrenzter CRM-Zugang für Ganz Soft, optional mit Zuschauen.',
            'support-zuschauen' => 'Bildschirm des Kunden während einer aktiven Support-Freigabe mitverfolgen.',
            'kdv-dashboard' => 'Übersicht über SaaS-Kunden und Lizenzstatus von Ganz Soft.',
            'kdv-kunden' => 'SaaS-Kunden anlegen, verwalten und Lizenzen zuordnen.',
            'kdv-support' => 'Support-Freigaben aller SaaS-Kunden zentral einsehen.',
            'einstellungen' => 'Firma, E-Mail, Abteilungen, Module und System konfigurieren.',
            'website-seiten' => 'Inhaltsseiten der öffentlichen Website anlegen und gestalten.',
            'website-formulare' => 'Formulare bauen, Einträge empfangen und in Seiten einbinden.',
            'website-statistik' => 'Lokale Aufrufe und Links zu Google Analytics / Tag Manager.',
            'website-menu' => 'Navigation der Website — welche Seiten wo verlinkt sind.',
            'website-chrome' => 'Kopfzeile, Fußzeile und zusätzliche Skripte der Website.',
            'website-design' => 'Farben und Erscheinungsbild der öffentlichen Website.',
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
                'description' => self::tileDescription($item['slug'], $descriptions),
            ];
        }

        $dienstleistungen = self::dienstleistungenSection($user);
        if ($dienstleistungen !== null) {
            foreach ($dienstleistungen['items'] as $item) {
                $tiles[] = [
                    'slug' => $item['slug'],
                    'label' => $item['label'],
                    'icon' => $item['icon'],
                    'href' => $item['href'],
                    'description' => self::tileDescription($item['slug'], $descriptions),
                ];
            }
        }

        $hr = self::hrSection($user);
        if ($hr !== null) {
            foreach ($hr['items'] as $item) {
                $tiles[] = [
                    'slug' => $item['slug'],
                    'label' => $item['label'],
                    'icon' => $item['icon'],
                    'href' => $item['href'],
                    'description' => self::tileDescription($item['slug'], $descriptions),
                ];
            }
        }

        $kommunikation = self::kommunikationSection($user);
        if ($kommunikation !== null) {
            foreach ($kommunikation['items'] as $item) {
                $tiles[] = [
                    'slug' => $item['slug'],
                    'label' => $item['label'],
                    'icon' => $item['icon'],
                    'href' => $item['href'],
                    'description' => self::tileDescription($item['slug'], $descriptions),
                ];
            }
        }

        $warenwirtschaft = self::warenwirtschaftSection($user);
        if ($warenwirtschaft !== null) {
            foreach ($warenwirtschaft['items'] as $item) {
                $tiles[] = [
                    'slug' => $item['slug'],
                    'label' => $item['label'],
                    'icon' => $item['icon'],
                    'href' => $item['href'],
                    'description' => self::tileDescription($item['slug'], $descriptions),
                ];
            }
        }

        $buchhaltung = self::buchhaltungSection($user);
        if ($buchhaltung !== null) {
            foreach ($buchhaltung['items'] as $item) {
                $tiles[] = [
                    'slug' => $item['slug'],
                    'label' => $item['label'],
                    'icon' => $item['icon'],
                    'href' => $item['href'],
                    'description' => self::tileDescription($item['slug'], $descriptions),
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
                    'description' => self::tileDescription($item['slug'], $descriptions),
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
                'description' => self::tileDescription('einstellungen', $descriptions),
            ];
        }

        return $tiles;
    }

    /**
     * @param array<string, string> $descriptions
     */
    private static function tileDescription(string $slug, array $descriptions): string
    {
        $text = trim((string) ($descriptions[$slug] ?? ''));

        return $text !== '' ? $text : 'Öffnet diesen Bereich im CRM.';
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
            || $slug === 'buchhaltung-manuelle-buchung'
            || $slug === 'buchhaltung-auswertungen'
            || $slug === 'buchhaltung-bwa'
            || $slug === 'buchhaltung-susa'
            || $slug === 'buchhaltung-bankabgleich'
            || $slug === 'buchhaltung-steuerberater-export'
            || $slug === 'buchhaltung-datev-export'
            || $slug === 'buchhaltung-ustva'
            || $slug === 'buchhaltung-jahresabschluss'
            || $slug === 'buchhaltung-anschaffungsrechner'
        ) {
            return self::canAccessBuchhaltung($user);
        }

        if ($slug === 'kdv-dashboard' || $slug === 'kdv-kunden' || $slug === 'kdv-kunde-form' || $slug === 'kdv-umfirmierung' || $slug === 'kdv-rumpf-wj' || $slug === 'kdv-provision' || $slug === 'kdv-support') {
            return self::canAccessKdv($user);
        }

        if ($slug === 'support-freigabe') {
            return RoleResolver::isAdmin($user);
        }

        if ($slug === 'support-zuschauen') {
            return class_exists('SupportSession') && SupportSession::isActive();
        }

        if ($slug === 'website-gutschein-flyer') {
            // Druck auch aus Buchhaltung/Gutscheine — nicht nur Website-Rechte.
            if (!self::isGutscheinEnabled()) {
                return false;
            }

            return self::canAccessWebsite($user)
                || self::canAccessBuchhaltung($user)
                || (DepartmentAccess::userCanManageArticleCatalog($user) && RoleResolver::canEdit($user));
        }

        if (
            $slug === 'website-seiten'
            || $slug === 'website-seite-form'
            || $slug === 'website-recht'
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

        if ($slug === 'akademie') {
            return RoleResolver::canEdit($user);
        }

        if ($slug === 'hr-abteilungen' || $slug === 'hr-teams' || $slug === 'hr-team-form') {
            return TeamAccess::canView($user);
        }

        if ($slug === 'artikel-leistungen' || $slug === 'lager') {
            if (!DepartmentAccess::userCanManageArticleCatalog($user) || !RoleResolver::canEdit($user)) {
                return false;
            }

            return AcademyGateService::isModuleAccessible($user, $slug);
        }

        if ($slug === 'rezeptur' || $slug === 'rezeptur-form'
            || $slug === 'rezeptur-maschinen' || $slug === 'rezeptur-maschine-form') {
            return self::isRezepturEnabled()
                && DepartmentAccess::userCanManageArticleCatalog($user)
                && RoleResolver::canEdit($user);
        }

        // Gutscheine: Buchhaltung ODER Artikel-/Warenwirtschaft (Menü steht unter Buchhaltung).
        if (
            $slug === 'gutscheine'
            || $slug === 'gutscheine-form'
            || $slug === 'gutscheine-flyer-vorlagen'
            || $slug === 'buchhaltung-gutscheine'
        ) {
            if (!self::isGutscheinEnabled()) {
                return false;
            }

            return self::canAccessBuchhaltung($user)
                || (DepartmentAccess::userCanManageArticleCatalog($user) && RoleResolver::canEdit($user));
        }

        if ($slug === 'reklamation' || $slug === 'reklamation-form') {
            return self::isReklamationEnabled()
                && DepartmentAccess::userCanManageArticleCatalog($user)
                && RoleResolver::canEdit($user);
        }

        if ($slug === 'post') {
            return DepartmentAccess::canAccessModule($user, 'post') && RoleResolver::canEdit($user);
        }

        if ($slug === 'telefon') {
            // Platzhalter: sichtbar für Bearbeiter mit Post- oder Kontakt-Recht
            return RoleResolver::canEdit($user)
                && (
                    DepartmentAccess::canAccessModule($user, 'post')
                    || DepartmentAccess::canAccessModule($user, 'kontakte')
                );
        }

        if ($slug === 'dokumentenverfasser' || $slug === 'dokumentenverfasser-form') {
            return RoleResolver::canEdit($user)
                && DepartmentAccess::canAccessModule($user, 'dokumentenverfasser');
        }

        // Basis-Slug (Menü/Kachel) — ohne diesen Fall landet /app?page=zeiterfassung auf dem Dashboard.
        if ($slug === 'zeiterfassung') {
            return DepartmentAccess::canAccessModule($user, 'zeiterfassung') && RoleResolver::canEdit($user);
        }
        if ($slug === 'zeiterfassung-team') {
            return DepartmentAccess::canAccessModule($user, 'zeiterfassung') && TimeClockService::canViewTeam($user);
        }
        if ($slug === 'zeiterfassung-monat') {
            return DepartmentAccess::canAccessModule($user, 'zeiterfassung') && RoleResolver::canEdit($user);
        }
        if ($slug === 'zeiterfassung-konto') {
            return DepartmentAccess::canAccessModule($user, 'zeiterfassung') && TimeClockService::canViewTeam($user);
        }
        if ($slug === 'zeiterfassung-schicht-vorlagen') {
            return DepartmentAccess::canAccessModule($user, 'zeiterfassung') && TimeClockService::canViewTeam($user);
        }
        if ($slug === 'zeiterfassung-schichten') {
            return DepartmentAccess::canAccessModule($user, 'zeiterfassung') && TimeClockService::canViewTeam($user);
        }
        if ($slug === 'zeiterfassung-urlaub') {
            return DepartmentAccess::canAccessModule($user, 'zeiterfassung') && RoleResolver::canEdit($user);
        }
        if ($slug === 'zeiterfassung-abwesenheit') {
            return DepartmentAccess::canAccessModule($user, 'zeiterfassung') && RoleResolver::canEdit($user);
        }
        if ($slug === 'zeiterfassung-rueckstellung') {
            return TimeProvisionService::canPreview($user);
        }
        if ($slug === 'zeiterfassung-lohnexport') {
            return TimePayrollExportService::canExport($user);
        }
        if ($slug === 'zeiterfassung-stundenimport') {
            return TimeHoursImportService::canImport($user);
        }
        if ($slug === 'zeiterfassung-kiosk') {
            return TimeKioskService::canManagePins($user);
        }

        foreach (self::modules($user) as $item) {
            if ($item['slug'] === $slug) {
                return true;
            }
        }

        return false;
    }

    /**
     * Feature-Flag Rezeptur (R0). Abschalten: App-Config `features.rezeptur` => false.
     * Seite/Schema kommen erst ab R1 — Menüpunkt ist Platzhalter.
     */
    public static function isRezepturEnabled(): bool
    {
        return (bool) App::config('features.rezeptur', true);
    }

    /** Feature-Flag Gutscheine. Abschalten: `features.gutschein` => false. */
    public static function isGutscheinEnabled(): bool
    {
        return (bool) App::config('features.gutschein', true);
    }

    /** Feature-Flag Reklamation. Abschalten: `features.reklamation` => false. */
    public static function isReklamationEnabled(): bool
    {
        return (bool) App::config('features.reklamation', true);
    }
}
