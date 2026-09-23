<?php
declare(strict_types=1);

/**
 * Vorinstallierte Website-Seite „Apps“ — Download Termin-Kalender- und Mitarbeiter-App.
 */
final class MobileAppDownloadService
{
    public const PAGE_SLUG = 'apps';

    /** Relativ zum Web-Root (statische Auslieferung per .htaccess). */
    public const KALENDER_APK = 'downloads/dg-kalender.apk';
    public const MITARBEITER_APK = 'downloads/dg-mitarbeiter.apk';

    public static function ensureDraftWebsitePage(?int $userId = null): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        $existing = WebsitePageRepository::findBySlugAnyStatus(self::PAGE_SLUG);
        if ($existing !== null) {
            return;
        }
        WebsitePageRepository::save([
            'title' => 'Apps herunterladen',
            'slug' => self::PAGE_SLUG,
            'status' => WebsitePageRepository::STATUS_PUBLISHED,
            'layout' => self::pageLayout(),
        ], null, $userId);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, page_kind?: string}
     */
    public static function pageLayout(): array
    {
        return [
            'page_kind' => 'mobile_app_download',
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
                    'id' => 'row-apps-cards',
                    'columns' => [
                        [
                            'id' => 'col-kalender',
                            'width' => 6,
                            'blocks' => [
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
                                [
                                    'id' => 'blk-kal-btn',
                                    'type' => 'button',
                                    'text' => 'Termin-App herunterladen (Android)',
                                    'url' => '/' . self::KALENDER_APK,
                                    'style' => 'primary',
                                ],
                            ],
                        ],
                        [
                            'id' => 'col-mitarbeiter',
                            'width' => 6,
                            'blocks' => [
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
                                [
                                    'id' => 'blk-ma-btn',
                                    'type' => 'button',
                                    'text' => 'Mitarbeiter-App herunterladen (Android)',
                                    'url' => '/' . self::MITARBEITER_APK,
                                    'style' => 'primary',
                                ],
                            ],
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
                                'text' => 'Hinweis: iOS- und Store-Links folgen. Android-APKs legt die Administration '
                                    . 'unter downloads/ auf dem Server ab. Fehlt die Datei, erscheint beim Klick eine Fehlermeldung — '
                                    . 'bitte die Firma kontaktieren.',
                            ],
                        ],
                    ]],
                ],
            ],
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
