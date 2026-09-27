<?php
declare(strict_types=1);

/**
 * Lesetext und CRM-Zielseite für Akademie-Module (Vorschau / Player).
 */
final class AcademyModulePresentation
{
    /**
     * Lesetext unter dem Video: Locale-Skript (Intro/Segmente), sonst DB-Beschreibung.
     *
     * @param array<string, mixed> $module
     */
    public static function readingText(array $module): string
    {
        $locale = self::localeReadingBody($module);
        if ($locale !== '') {
            return $locale;
        }

        return trim((string) ($module['description'] ?? ''));
    }

    /**
     * Kurztext für DB beim Upload, wenn Beschreibung leer: Locale-Intro, sonst gesamter Lesetext.
     *
     * @param array<string, mixed> $module
     */
    public static function suggestedDescription(array $module): string
    {
        $intro = self::localeIntro($module);
        if ($intro !== '') {
            return $intro;
        }

        return self::readingText($module);
    }

    /**
     * Zielseite für Speichern/Button: gesetzte target_page, sonst aus Video-Slug.
     *
     * @param array<string, mixed> $module
     */
    public static function resolvedTargetPage(array $module): string
    {
        $page = trim((string) ($module['target_page'] ?? ''));
        if ($page !== '') {
            return $page === 'dashboard' || preg_match('/^[a-z0-9_-]+$/i', $page)
                ? strtolower($page)
                : '';
        }

        return self::guessTargetPage($module);
    }

    /**
     * Alle CTA-Buttons unter dem Video: Praxis-Stelle + Modul-Thema.
     *
     * @param array<string, mixed> $module
     * @return list<array{href: string, label: string, kind: string}>
     */
    public static function crmTargets(array $module): array
    {
        $out = [];
        $seen = [];

        $practice = self::practiceTarget($module);
        if ($practice !== null) {
            $out[] = $practice;
            $seen[$practice['href']] = true;
        }

        $topic = self::crmTarget($module);
        if ($topic !== null && !isset($seen[$topic['href']])) {
            $out[] = $topic;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $module
     * @return array{href: string, label: string, kind: string}|null
     */
    public static function crmTarget(array $module): ?array
    {
        $page = self::resolvedTargetPage($module);
        if ($page === '') {
            return null;
        }
        $href = self::hrefForPage($page);
        if ($href === null) {
            return null;
        }
        $label = self::labelForPage($page);

        return [
            'href' => $href,
            'label' => $label !== '' ? ('Zum Thema: ' . $label) : 'Zum Thema im CRM',
            'kind' => 'topic',
        ];
    }

    /**
     * Praxis-Button: genaue Stelle aus dem Video (z. B. Mitarbeiterliste zum Bearbeiten).
     *
     * @param array<string, mixed> $module
     * @return array{href: string, label: string, kind: string}|null
     */
    public static function practiceTarget(array $module): ?array
    {
        $fromLocale = self::localePracticeCta($module);
        if ($fromLocale !== null) {
            return $fromLocale;
        }

        $slug = self::videoSlug($module);
        if ($slug === '') {
            return null;
        }
        $destKey = self::practiceDestKeyForSlug($slug);
        if ($destKey === '') {
            return null;
        }
        $dest = self::practiceDestinations()[$destKey] ?? null;
        if ($dest === null) {
            return null;
        }

        return [
            'href' => $dest['href'],
            'label' => $dest['label'],
            'kind' => 'practice',
        ];
    }

    /**
     * Auswahl für Admin-Formular.
     *
     * @return array<string, string> pageSlug => Label
     */
    public static function targetPageOptions(): array
    {
        $opts = [
            '' => '— automatisch aus Video-Datei —',
            'dashboard' => 'Dashboard',
        ];
        foreach (self::knownPageLabels() as $slug => $label) {
            $opts[$slug] = $label;
        }

        return $opts;
    }

    /** @param array<string, mixed> $module */
    public static function videoSlug(array $module): string
    {
        $path = trim((string) ($module['video_path'] ?? ''));
        if ($path === '') {
            return '';
        }
        $base = pathinfo($path, PATHINFO_FILENAME);
        $base = (string) preg_replace('/\.(de|en|fr|it|es|pl|nl|pt)$/i', '', $base);
        // Upload speichert oft „slug-YYYYMMDDHHMMSS“
        $base = (string) preg_replace('/-\d{14}$/', '', $base);

        return strtolower(trim($base));
    }

    /** @param array<string, mixed> $module */
    private static function localeIntro(array $module): string
    {
        $data = self::localeData($module);
        if ($data === null) {
            return '';
        }

        return trim((string) ($data['intro'] ?? ''));
    }

    /** @param array<string, mixed> $module */
    private static function localeReadingBody(array $module): string
    {
        $data = self::localeData($module);
        if ($data === null) {
            return '';
        }
        $chunks = [];
        $intro = trim((string) ($data['intro'] ?? ''));
        if ($intro !== '') {
            $chunks[] = $intro;
        }
        $segments = $data['segments'] ?? null;
        if (is_array($segments)) {
            foreach ($segments as $text) {
                if (is_array($text)) {
                    $text = (string) ($text['narration'] ?? $text['text'] ?? '');
                }
                $text = trim((string) $text);
                if ($text === '') {
                    continue;
                }
                $chunks[] = $text;
            }
        }

        return trim(implode("\n\n", $chunks));
    }

    /**
     * @param array<string, mixed> $module
     * @return array<string, mixed>|null
     */
    private static function localeData(array $module): ?array
    {
        $slug = self::videoSlug($module);
        if ($slug === '') {
            return null;
        }
        $file = DG_ROOT . '/docs/akademie/locales/de/' . $slug . '.json';
        if (!is_readable($file)) {
            return null;
        }
        $raw = file_get_contents($file);
        if ($raw === false || trim($raw) === '') {
            return null;
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    /** @param array<string, mixed> $module */
    private static function guessTargetPage(array $module): string
    {
        $slug = self::videoSlug($module);
        if ($slug === '') {
            return '';
        }
        $map = [
            'dashboard-ueberblick' => 'dashboard',
            'kontakte-ueberblick' => 'kontakte',
            'kontakte-felder-stamm' => 'kontakte',
            'kontakte-felder-adresse' => 'kontakte',
            'kontakte-felder-kommunikation' => 'kontakte',
            'kontakte-felder-bank' => 'kontakte',
            'kontakte-felder-social' => 'kontakte',
            'kontakte-felder-kunde-lieferant' => 'kontakte',
            'kontakte-felder-mitarbeiter' => 'kontakte',
            'terminkalender-ueberblick' => 'terminkalender',
            'terminkalender-neuer-termin' => 'terminkalender',
            'terminkalender-online-buchung' => 'terminkalender',
            'terminkalender-online-kunde' => 'terminkalender',
            'lager-ueberblick' => 'lager',
            'lager-platz-check' => 'lager',
            'lager-ein-ausgang' => 'lager',
            'lager-inventur' => 'lager',
            'media-ueberblick' => 'bilder',
            'media-bearbeiten' => 'bilder',
            'media-zuschneiden-freistellen' => 'bilder',
            'akademie-ueberblick' => 'akademie',
            'akademie-tabs' => 'akademie',
            'akademie-kurs-lernen' => 'akademie',
            'konten-ueberblick' => 'buchhaltung-konten',
            'konten-hinweise' => 'buchhaltung-konten',
            'konten-kontenuebersicht' => 'buchhaltung-kontenuebersicht',
            'kassenbuch-ueberblick' => 'buchhaltung-kassenbuch',
            'opos-ueberblick' => 'buchhaltung-opos',
            'guv-ueberblick' => 'buchhaltung-auswertungen',
            'bankabgleich-ueberblick' => 'buchhaltung-bankabgleich',
            'steuerberater-export-ueberblick' => 'buchhaltung-steuerberater-export',
            'manuelle-buchungen-ueberblick' => 'buchhaltung-manuelle-buchung',
            'manuelle-buchungen-erfassen' => 'buchhaltung-manuelle-buchung',
            'statistik-ueberblick' => 'website-statistik',
            'kichel-ueberblick' => 'dashboard',
        ];
        if (isset($map[$slug])) {
            return $map[$slug];
        }
        if (str_starts_with($slug, 'kontakte')) {
            return 'kontakte';
        }
        if (str_starts_with($slug, 'terminkalender')) {
            return 'terminkalender';
        }
        if (str_starts_with($slug, 'lager')) {
            return 'lager';
        }
        if (str_starts_with($slug, 'media') || str_starts_with($slug, 'bilder')) {
            return 'bilder';
        }
        if (str_starts_with($slug, 'akademie')) {
            return 'akademie';
        }
        if (str_starts_with($slug, 'zeiterfassung')) {
            return 'zeiterfassung';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $module
     * @return array{href: string, label: string, kind: string}|null
     */
    private static function localePracticeCta(array $module): ?array
    {
        $data = self::localeData($module);
        if ($data === null) {
            return null;
        }
        $practice = $data['practice'] ?? null;
        if (!is_array($practice)) {
            return null;
        }
        $destKey = trim((string) ($practice['dest'] ?? ''));
        if ($destKey !== '' && isset(self::practiceDestinations()[$destKey])) {
            $dest = self::practiceDestinations()[$destKey];
            $label = trim((string) ($practice['label'] ?? ''));

            return [
                'href' => $dest['href'],
                'label' => $label !== '' ? $label : $dest['label'],
                'kind' => 'practice',
            ];
        }
        $href = trim((string) ($practice['href'] ?? ''));
        $label = trim((string) ($practice['label'] ?? ''));
        if ($href === '' || $label === '' || !str_starts_with($href, '/app')) {
            return null;
        }
        if (str_contains($href, '//') || preg_match('/[\s<>"\']/', $href)) {
            return null;
        }

        return [
            'href' => $href,
            'label' => $label,
            'kind' => 'practice',
        ];
    }

    private static function practiceDestKeyForSlug(string $slug): string
    {
        $map = [
            'kontakte-felder-mitarbeiter' => 'kontakte-mitarbeiter-liste',
            'kontakte-felder-stamm' => 'kontakte-liste-bearbeiten',
            'kontakte-felder-adresse' => 'kontakte-liste-bearbeiten',
            'kontakte-felder-kommunikation' => 'kontakte-liste-bearbeiten',
            'kontakte-felder-bank' => 'kontakte-liste-bearbeiten',
            'kontakte-felder-social' => 'kontakte-liste-bearbeiten',
            'kontakte-felder-kunde-lieferant' => 'kontakte-liste-bearbeiten',
            'kontakte-ueberblick' => 'kontakte-liste',
            'terminkalender-neuer-termin' => 'terminkalender-neu',
            'terminkalender-online-buchung' => 'terminkalender',
            'terminkalender-online-kunde' => 'terminkalender',
            'terminkalender-ueberblick' => 'terminkalender',
            'lager-platz-check' => 'lager',
            'lager-ein-ausgang' => 'lager',
            'lager-inventur' => 'lager',
            'media-bearbeiten' => 'bilder',
            'media-zuschneiden-freistellen' => 'bilder',
            'manuelle-buchungen-erfassen' => 'buchhaltung-manuelle-buchung',
            'akademie-kurs-lernen' => 'akademie',
            'akademie-tabs' => 'akademie',
        ];

        return $map[$slug] ?? '';
    }

    /**
     * @return array<string, array{href: string, label: string}>
     */
    private static function practiceDestinations(): array
    {
        return [
            'kontakte-liste' => [
                'href' => '/app?page=kontakte',
                'label' => 'Zur Kontaktliste',
            ],
            'kontakte-liste-bearbeiten' => [
                'href' => '/app?page=kontakte',
                'label' => 'Zur Kontaktliste — Bearbeiten wählen',
            ],
            'kontakte-mitarbeiter-liste' => [
                'href' => '/app?page=kontakte&role=mitarbeiter',
                'label' => 'Zur Mitarbeiterliste — Bearbeiten wählen',
            ],
            'terminkalender' => [
                'href' => '/app?page=terminkalender',
                'label' => 'Zum Terminkalender',
            ],
            'terminkalender-neu' => [
                'href' => '/app?page=terminkalender&action=new',
                'label' => 'Neuen Termin anlegen',
            ],
            'lager' => [
                'href' => '/app?page=lager',
                'label' => 'Zum Lager',
            ],
            'bilder' => [
                'href' => '/app?page=bilder',
                'label' => 'Zur Media-Bibliothek',
            ],
            'buchhaltung-manuelle-buchung' => [
                'href' => '/app?page=buchhaltung-manuelle-buchung',
                'label' => 'Zur manuellen Buchung',
            ],
            'akademie' => [
                'href' => '/app?page=akademie',
                'label' => 'Zur Akademie',
            ],
        ];
    }

    private static function hrefForPage(string $page): ?string
    {
        $page = trim($page);
        if ($page === '' || $page === 'dashboard') {
            return '/app';
        }
        if (!preg_match('/^[a-z0-9_-]+$/i', $page)) {
            return null;
        }

        return '/app?page=' . rawurlencode($page);
    }

    private static function labelForPage(string $page): string
    {
        if ($page === '' || $page === 'dashboard') {
            return 'Dashboard';
        }
        $known = self::knownPageLabels();

        return $known[$page] ?? $page;
    }

    /** @return array<string, string> */
    private static function knownPageLabels(): array
    {
        return [
            'kontakte' => 'Kontakte',
            'terminkalender' => 'Terminkalender',
            'zeiterfassung' => 'Zeiterfassung',
            'zeiterfassung-urlaub' => 'Urlaub',
            'zeiterfassung-abwesenheit' => 'Abwesenheit',
            'post' => 'Post',
            'akademie' => 'Akademie',
            'artikel-leistungen' => 'Artikel & Leistungen',
            'lager' => 'Lager',
            'bilder' => 'Media',
            'buchhaltung-konten' => 'Konten',
            'buchhaltung-belege' => 'Belege',
            'buchhaltung-ueberweisungen' => 'Überweisungen',
            'buchhaltung-kontenuebersicht' => 'Kontenübersicht',
            'buchhaltung-opos' => 'Offene Posten',
            'buchhaltung-kassenbuch' => 'Kassenbuch',
            'buchhaltung-manuelle-buchung' => 'Manuelle Buchungen',
            'buchhaltung-auswertungen' => 'Bilanz & GuV',
            'buchhaltung-bwa' => 'BWA',
            'buchhaltung-susa' => 'SuSa',
            'buchhaltung-bankabgleich' => 'Bankabgleich',
            'buchhaltung-steuerberater-export' => 'Steuerberater-Export',
            'buchhaltung-ustva' => 'UStVA',
            'buchhaltung-jahresabschluss' => 'Jahresabschluss',
            'website-seiten' => 'Website-Seiten',
            'website-formulare' => 'Formulare',
            'website-statistik' => 'Website-Statistik',
            'website-menu' => 'Website-Menü',
            'website-chrome' => 'Kopf & Fuß',
            'website-design' => 'Website-Design',
            'einstellungen' => 'Einstellungen',
            'support-freigabe' => 'Support-Freigabe',
        ];
    }
}
