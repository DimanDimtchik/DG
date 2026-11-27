<?php
declare(strict_types=1);

/**
 * Vorinstallierte Website-Seite für CRM-App-Downloads (Termin- + Mitarbeiter-App).
 * Je App: Android- und Apple-Button; ohne Store-Link bleibt der Button öffentlich unsichtbar.
 *
 * Slug bewusst **nicht** `apps` — der bleibt frei für Instanz-Website-Inhalte (Produkt-/Marketing-Seiten).
 */
final class MobileAppDownloadService
{
    /** Öffentlicher Pfad / Slug der CRM-Download-Seite (nicht „apps“). */
    public const PAGE_SLUG = 'app-download';

    /** Historischer Slug (Konflikt mit Website-Seiten) — nur Migration. */
    public const LEGACY_PAGE_SLUG = 'apps';

    public const MENU_LABEL = 'App-Download';

    public const PAGE_KIND = 'mobile_app_download';

    /** Relativ zum Web-Root (statische Auslieferung per .htaccess). */
    public const KALENDER_APK = 'downloads/dg-kalender.apk';
    public const MITARBEITER_APK = 'downloads/dg-mitarbeiter.apk';

    public static function pagePath(): string
    {
        return '/' . self::PAGE_SLUG;
    }

    public static function ensureDraftWebsitePage(?int $userId = null): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        self::migrateLegacyAppsSlug($userId);
        $existing = WebsitePageRepository::findBySlugAnyStatus(self::PAGE_SLUG);
        if ($existing === null) {
            WebsitePageRepository::save([
                'title' => 'Apps herunterladen',
                'slug' => self::PAGE_SLUG,
                'status' => WebsitePageRepository::STATUS_PUBLISHED,
                'layout' => self::pageLayout(),
            ], null, $userId);
        }
        self::ensureMenuItem();
    }

    /**
     * Alte CRM-Download-Seite unter Slug `apps` → `app-download`.
     * Instanz-Seiten unter `apps` ohne page_kind mobile_app_download bleiben unangetastet.
     */
    public static function migrateLegacyAppsSlug(?int $userId = null): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        $legacy = WebsitePageRepository::findBySlugAnyStatus(self::LEGACY_PAGE_SLUG);
        if ($legacy === null) {
            return;
        }
        if (!self::isMobileDownloadPage($legacy)) {
            return;
        }
        $target = WebsitePageRepository::findBySlugAnyStatus(self::PAGE_SLUG);
        if ($target !== null) {
            // Ziel existiert schon — Legacy-CRM-Seite entfernen, damit Slug `apps` frei wird
            WebsitePageRepository::delete((int) ($legacy['id'] ?? 0));
            self::rewriteMenuUrls('/' . self::LEGACY_PAGE_SLUG, self::pagePath(), self::MENU_LABEL);

            return;
        }
        WebsitePageRepository::save([
            'title' => (string) ($legacy['title'] ?? 'Apps herunterladen'),
            'slug' => self::PAGE_SLUG,
            'status' => (string) ($legacy['status'] ?? WebsitePageRepository::STATUS_PUBLISHED),
            'layout' => is_array($legacy['layout'] ?? null) ? $legacy['layout'] : self::pageLayout(),
        ], (int) ($legacy['id'] ?? 0), $userId);
        self::rewriteMenuUrls('/' . self::LEGACY_PAGE_SLUG, self::pagePath(), self::MENU_LABEL);
    }

    /**
     * @param array<string, mixed> $page
     */
    public static function isMobileDownloadPage(array $page): bool
    {
        $layout = $page['layout'] ?? null;
        if (!is_array($layout)) {
            return false;
        }

        return (string) ($layout['page_kind'] ?? '') === self::PAGE_KIND;
    }

    /**
     * Menü-URLs umschreiben (Top-Level + Kinder), z. B. nach Legacy-Migration.
     */
    public static function rewriteMenuUrls(string $fromUrl, string $toUrl, ?string $newLabel = null): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        $from = rtrim($fromUrl, '/') ?: '/';
        $to = rtrim($toUrl, '/') ?: '/';
        if ($from === $to) {
            return;
        }
        $menu = SettingsStore::get('website.menu', []);
        if (!is_array($menu)) {
            return;
        }
        $items = is_array($menu['items'] ?? null) ? $menu['items'] : [];
        if ($items === []) {
            return;
        }
        $changed = false;
        $items = self::rewriteMenuItems($items, $from, $to, $newLabel, $changed);
        if (!$changed) {
            return;
        }
        $menu['items'] = $items;
        SettingsStore::set('website.menu', $menu);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private static function rewriteMenuItems(
        array $items,
        string $from,
        string $to,
        ?string $newLabel,
        bool &$changed
    ): array {
        foreach ($items as $i => $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = rtrim((string) ($item['url'] ?? ''), '/') ?: '/';
            if ($url === $from) {
                $items[$i]['url'] = $to;
                if ($newLabel !== null && $newLabel !== '') {
                    $items[$i]['label'] = $newLabel;
                }
                $changed = true;
            }
            $children = is_array($item['children'] ?? null) ? $item['children'] : [];
            if ($children !== []) {
                $items[$i]['children'] = self::rewriteMenuItems($children, $from, $to, $newLabel, $changed);
            }
        }

        return $items;
    }

    /** Menüpunkt „App-Download“ nachziehen (ohne Duplikat, auch wenn nur im Untermenü). */
    public static function ensureMenuItem(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        $menu = SettingsStore::get('website.menu', []);
        if (!is_array($menu)) {
            $menu = [];
        }
        $items = is_array($menu['items'] ?? null) ? $menu['items'] : [];
        if (WebsiteSettings::menuHasUrl(self::pagePath(), $items)) {
            return;
        }

        $entry = [
            'label' => self::MENU_LABEL,
            'url' => self::pagePath(),
            'auth_only' => false,
            'icon' => 'auto',
            'children' => [],
        ];
        if ($items === []) {
            $items[] = ['label' => 'Start', 'url' => '/', 'auth_only' => false, 'icon' => 'auto', 'children' => []];
        }
        $insertAt = 1;
        foreach ($items as $i => $item) {
            if (!is_array($item)) {
                continue;
            }
            if (rtrim((string) ($item['url'] ?? ''), '/') === '/terminkalender') {
                $insertAt = $i + 1;
                break;
            }
        }
        if ($insertAt > count($items)) {
            $items[] = $entry;
        } else {
            array_splice($items, $insertAt, 0, [$entry]);
        }
        $menu['items'] = $items;
        if (!isset($menu['layout'])) {
            $menu['layout'] = 'auto';
        }
        SettingsStore::set('website.menu', $menu);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, page_kind?: string}
     */
    public static function pageLayout(): array
    {
        return [
            'page_kind' => self::PAGE_KIND,
            'rows' => [
                [
                    'id' => 'row-apps-intro',
                    'columns' => [[
                        'id' => 'col-apps-intro',
                        'width' => 12,
                        'blocks' => [
                            [
                                'id' => 'blk-apps-h',
                                'type' => 'heading',
                                'text' => 'Unsere Apps',
                                'level' => 'h1',
                            ],
                            [
                                'id' => 'blk-apps-t',
                                'type' => 'text',
                                'text' => 'Laden Sie die Termin-App (Kunden) oder die Mitarbeiter-App herunter. '
                                    . 'Nach der Installation geben Sie die CRM-Adresse Ihrer Firma ein '
                                    . '(z. B. die Adresse dieser Website ohne Slash am Ende).',
                            ],
                        ],
                    ]],
                ],
                [
                    'id' => 'row-apps-crm',
                    'columns' => [
                        [
                            'id' => 'col-kalender',
                            'width' => 6,
                            'blocks' => array_merge(
                                [
                                    [
                                        'id' => 'blk-kal-h',
                                        'type' => 'heading',
                                        'text' => 'Termin-App (Kalender)',
                                        'level' => 'h2',
                                    ],
                                    [
                                        'id' => 'blk-kal-t',
                                        'type' => 'text',
                                        'text' => 'Für Kunden: Leistungen ansehen, Wunschtermin wählen und buchen. '
                                            . 'Anmeldung mit E-Mail und Passwort.',
                                    ],
                                ],
                                self::platformButtons('kal', '/' . self::KALENDER_APK, '')
                            ),
                        ],
                        [
                            'id' => 'col-mitarbeiter',
                            'width' => 6,
                            'blocks' => array_merge(
                                [
                                    [
                                        'id' => 'blk-ma-h',
                                        'type' => 'heading',
                                        'text' => 'Mitarbeiter-App',
                                        'level' => 'h2',
                                    ],
                                    [
                                        'id' => 'blk-ma-t',
                                        'type' => 'text',
                                        'text' => 'Für Mitarbeiter: Termine und Stempelzeiten im Blick. '
                                            . 'Anmeldung mit Kennung und Stempeluhr-PIN.',
                                    ],
                                ],
                                self::platformButtons('ma', '/' . self::MITARBEITER_APK, '')
                            ),
                        ],
                    ],
                ],
                [
                    'id' => 'row-apps-hint',
                    'columns' => [[
                        'id' => 'col-apps-hint',
                        'width' => 12,
                        'blocks' => [
                            [
                                'id' => 'blk-apps-hint',
                                'type' => 'text',
                                'text' => 'Hinweis: Zu jeder App gibt es einen Android- und einen Apple-Button. '
                                    . 'Apple-Downloads bleiben unsichtbar, bis der App-Store- oder TestFlight-Link hinterlegt ist. '
                                    . 'Mitarbeiter können per Kontaktakte zur App und zu Passwort/PIN eingeladen werden. '
                                    . 'Fehlt die Android-APK-Datei unter downloads/, erscheint beim Klick eine Fehlermeldung.',
                            ],
                        ],
                    ]],
                ],
            ],
        ];
    }

    /**
     * Android- + Apple-Button. Leerer URL → öffentlich unsichtbar (pending).
     *
     * @return list<array<string, mixed>>
     */
    private static function platformButtons(string $idPrefix, string $androidUrl, string $appleUrl): array
    {
        return [
            self::downloadButton(
                'blk-' . $idPrefix . '-android',
                'Android herunterladen',
                $androidUrl,
                'android',
                $androidUrl === ''
            ),
            self::downloadButton(
                'blk-' . $idPrefix . '-apple',
                'Apple herunterladen',
                $appleUrl,
                'ios',
                $appleUrl === ''
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function downloadButton(
        string $id,
        string $label,
        string $url,
        string $platform,
        bool $pending
    ): array {
        return [
            'id' => $id,
            'type' => 'button',
            'text' => $label,
            'label' => $label,
            'url' => $pending ? 'pending' : $url,
            'platform' => $platform,
            'pending' => $pending,
            'style' => 'primary',
        ];
    }

    /** Absoluter Pfad zum Download-Verzeichnis. */
    public static function downloadsDir(): string
    {
        return rtrim(str_replace('\\', '/', DG_ROOT), '/') . '/downloads';
    }

    public static function ensureDownloadsDir(): void
    {
        $dir = self::downloadsDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    /**
     * Liefert Dateiinfos für die hinterlegten APKs (falls vorhanden).
     *
     * @return array{kalender: ?array{path: string, url: string, bytes: int}, mitarbeiter: ?array{path: string, url: string, bytes: int}}
     */
    public static function availablePackages(): array
    {
        self::ensureDownloadsDir();

        return [
            'kalender' => self::packageMeta(self::KALENDER_APK),
            'mitarbeiter' => self::packageMeta(self::MITARBEITER_APK),
        ];
    }

    /**
     * @return array{path: string, url: string, bytes: int}|null
     */
    private static function packageMeta(string $relative): ?array
    {
        $path = rtrim(str_replace('\\', '/', DG_ROOT), '/') . '/' . ltrim($relative, '/');
        if (!is_file($path)) {
            return null;
        }

        return [
            'path' => $path,
            'url' => '/' . ltrim($relative, '/'),
            'bytes' => (int) filesize($path),
        ];
    }
}
