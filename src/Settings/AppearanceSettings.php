<?php
declare(strict_types=1);

/**
 * Appearance Settings.
 */
final class AppearanceSettings
{
    public const STORE_KEY = 'appearance';

    /**
     * Liefert die Standardwerte.
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'ui_font' => 'system',
            'email_font' => 'system',
            'email_font_size' => '16',
            'custom_ui_font' => '',
            'custom_email_font' => '',
            'logo_media_id' => '',
            'favicon_media_id' => '',
        ];
    }

    /**
     * Methode font options.
     * @return array<string, mixed>
     */
    public static function fontOptions(): array
    {
        return [
            'system' => 'System (Segoe UI / Roboto)',
            'inter' => 'Inter',
            'roboto' => 'Roboto',
            'open-sans' => 'Open Sans',
            'source-sans' => 'Source Sans 3',
            'georgia' => 'Georgia (Serif)',
            'custom' => 'Eigene Angabe …',
        ];
    }

    /**
     * Liefert die aktuelle Konfiguration.
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        $cfg = SettingsStore::get(self::STORE_KEY, self::defaults());
        $size = (int) ($cfg['email_font_size'] ?? 16);
        $cfg['email_font_size'] = (string) max(12, min(24, $size));
        $cfg['logo_media_id'] = trim((string) ($cfg['logo_media_id'] ?? ''));
        $cfg['favicon_media_id'] = trim((string) ($cfg['favicon_media_id'] ?? ''));

        return $cfg;
    }

    /**
     * Methode for form.
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        return self::config();
    }

    /**
     * Methode logo media id.
     * @return string
     */
    public static function logoMediaId(): string
    {
        return trim((string) (self::config()['logo_media_id'] ?? ''));
    }

    /**
     * Methode logo url.
     * @return string
     */
    public static function logoUrl(): string
    {
        $id = self::logoMediaId();
        if ($id !== '' && MediaId::isValid($id) && Database::isConfigured()) {
            try {
                MediaRepository::ensureTables();
                if (MediaRepository::find($id) !== null) {
                    return MediaStorage::publicUrl($id);
                }
            } catch (Throwable) {
                // Fallback auf Standard-Logo
            }
        }

        return Asset::url('/assets/img/logo.svg');
    }

    /**
     * Aspect hint for CSS: square | wide | tall.
     * Wide logos (e.g. wordmark) should not be forced into a square box.
     */
    public static function logoShape(): string
    {
        [$w, $h] = self::logoDimensions();
        if ($w < 1 || $h < 1) {
            return 'wide'; // default SVG / unknown → treat as wordmark-friendly
        }
        $ratio = $w / $h;
        if ($ratio >= 1.25) {
            return 'wide';
        }
        if ($ratio <= 0.85) {
            return 'tall';
        }

        return 'square';
    }

    /**
     * @return array{0: int, 1: int} width, height (0,0 if unknown)
     */
    public static function logoDimensions(): array
    {
        $id = self::logoMediaId();
        if ($id !== '' && MediaId::isValid($id) && Database::isConfigured()) {
            try {
                $item = MediaRepository::find($id);
                if ($item !== null) {
                    $w = (int) ($item['width'] ?? 0);
                    $h = (int) ($item['height'] ?? 0);
                    if ($w > 0 && $h > 0) {
                        return [$w, $h];
                    }
                    $path = MediaStorage::absolutePath($id, (string) ($item['stored_name'] ?? ''));
                    if (is_string($path) && is_file($path)) {
                        $size = @getimagesize($path);
                        if (is_array($size) && ($size[0] ?? 0) > 0 && ($size[1] ?? 0) > 0) {
                            return [(int) $size[0], (int) $size[1]];
                        }
                    }
                }
            } catch (Throwable) {
            }
        }

        $fallback = DG_ROOT . '/assets/img/logo.svg';
        if ($id !== '') {
            // Hochgeladenes Logo ohne Metadaten: eher Querformat (Wortmarke)
            return [320, 100];
        }
        if (is_file($fallback)) {
            return [48, 48];
        }

        return [0, 0];
    }

    /** CSS modifier class: dg-logo--square|wide|tall */
    public static function logoShapeClass(string $prefix = 'dg-logo'): string
    {
        return $prefix . '--' . self::logoShape();
    }

    /**
     * Methode logo alt.
     * @return string
     */
    public static function logoAlt(): string
    {
        $id = self::logoMediaId();
        if ($id !== '' && Database::isConfigured()) {
            try {
                $item = MediaRepository::find($id);
                if ($item !== null) {
                    $alt = trim((string) ($item['alt_text'] ?? ''));
                    if ($alt !== '') {
                        return $alt;
                    }
                    $title = trim((string) ($item['title'] ?? ''));
                    if ($title !== '') {
                        return $title;
                    }
                }
            } catch (Throwable) {
            }
        }

        return (string) App::config('crm_name', 'DG');
    }

    /**
     * Speichert logo media id.
     * @param string $mediaId
     * @return void
     * @throws InvalidArgumentException
     */
    public static function setLogoMediaId(string $mediaId): void
    {
        if (!MediaId::isValid($mediaId)) {
            throw new InvalidArgumentException('Ungültige Medien-ID für das Logo.');
        }

        $cfg = self::config();
        $cfg['logo_media_id'] = $mediaId;
        SettingsStore::set(self::STORE_KEY, $cfg);
    }

    /**
     * Methode clear logo media id.
     * @return void
     */
    public static function clearLogoMediaId(): void
    {
        $cfg = self::config();
        $cfg['logo_media_id'] = '';
        SettingsStore::set(self::STORE_KEY, $cfg);
    }

    /**
     * Methode favicon media id.
     * @return string
     */
    public static function faviconMediaId(): string
    {
        return trim((string) (self::config()['favicon_media_id'] ?? ''));
    }

    /**
     * Prüft: has favicon.
     * @return bool
     */
    public static function hasFavicon(): bool
    {
        $id = self::faviconMediaId();

        return $id !== '' && MediaId::isValid($id);
    }

    /**
     * Methode favicon is svg.
     * @return bool
     */
    public static function faviconIsSvg(): bool
    {
        $id = self::faviconMediaId();
        if ($id === '' || !Database::isConfigured()) {
            return false;
        }

        try {
            MediaRepository::ensureTables();

            return MediaStorage::faviconUsesSvg($id);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Speichert favicon media id.
     * @param string $mediaId
     * @return void
     * @throws InvalidArgumentException
     */
    public static function setFaviconMediaId(string $mediaId): void
    {
        if (!MediaId::isValid($mediaId)) {
            throw new InvalidArgumentException('Ungültige Medien-ID für das Favicon.');
        }

        $cfg = self::config();
        $cfg['favicon_media_id'] = $mediaId;
        SettingsStore::set(self::STORE_KEY, $cfg);
    }

    /**
     * Methode clear favicon media id.
     * @return void
     */
    public static function clearFaviconMediaId(): void
    {
        $cfg = self::config();
        $cfg['favicon_media_id'] = '';
        SettingsStore::set(self::STORE_KEY, $cfg);
    }

    /**
     * Methode ui font family.
     * @return string
     */
    public static function uiFontFamily(): string
    {
        return self::resolveFontFamily(
            (string) (self::config()['ui_font'] ?? 'system'),
            trim((string) (self::config()['custom_ui_font'] ?? ''))
        );
    }

    /**
     * Methode email font family.
     * @return string
     */
    public static function emailFontFamily(): string
    {
        return self::resolveFontFamily(
            (string) (self::config()['email_font'] ?? 'system'),
            trim((string) (self::config()['custom_email_font'] ?? ''))
        );
    }

    /**
     * Methode email font size px.
     * @return int
     */
    public static function emailFontSizePx(): int
    {
        return (int) (self::config()['email_font_size'] ?? 16);
    }

    /**
     * Methode wrap email html.
     * @param string $innerHtml
     * @return string
     */
    public static function wrapEmailHtml(string $innerHtml): string
    {
        $family = htmlspecialchars(self::emailFontFamily(), ENT_QUOTES, 'UTF-8');
        $size = self::emailFontSizePx();

        return '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"></head><body'
            . ' style="margin:0;padding:16px;font-family:' . $family . ';font-size:' . $size . 'px;line-height:1.5;color:#1d2327;">'
            . $innerHtml
            . '</body></html>';
    }

    /**
     * Methode font family map.
     * @return array<string, mixed>
     */
    public static function fontFamilyMap(): array
    {
        $map = [];
        foreach (array_keys(self::fontOptions()) as $key) {
            if ($key === 'custom') {
                continue;
            }
            $map[$key] = self::resolveFontFamily($key, '');
        }

        return $map;
    }

    /**
     * Methode google fonts href.
     * @return string|null
     */
    public static function googleFontsHref(): ?string
    {
        $cfg = self::config();
        $families = [];
        foreach (['ui_font', 'email_font'] as $key) {
            $slug = self::googleSlug((string) ($cfg[$key] ?? 'system'));
            if ($slug !== null) {
                $families[$slug] = true;
            }
        }
        if ($families === []) {
            return null;
        }

        $query = implode('&family=', array_map(
            static fn(string $slug): string => $slug . ':wght@400;600',
            array_keys($families)
        ));

        return 'https://fonts.googleapis.com/css2?family=' . $query . '&display=swap';
    }

    /**
     * Methode save.
     * @param array $input
     * @return void
     */
    public static function save(array $input): void
    {
        $uiFont = trim((string) ($input['ui_font'] ?? 'system'));
        $emailFont = trim((string) ($input['email_font'] ?? 'system'));
        if (!isset(self::fontOptions()[$uiFont])) {
            $uiFont = 'system';
        }
        if (!isset(self::fontOptions()[$emailFont])) {
            $emailFont = 'system';
        }

        $existing = self::config();

        $data = [
            'ui_font' => $uiFont,
            'email_font' => $emailFont,
            'email_font_size' => (string) max(12, min(24, (int) ($input['email_font_size'] ?? 16))),
            'custom_ui_font' => trim((string) ($input['custom_ui_font'] ?? '')),
            'custom_email_font' => trim((string) ($input['custom_email_font'] ?? '')),
            'logo_media_id' => $existing['logo_media_id'] ?? '',
            'favicon_media_id' => $existing['favicon_media_id'] ?? '',
        ];

        SettingsStore::set(self::STORE_KEY, $data);
    }

    /**
     * Führt aus: resolve font family.
     * @param string $choice
     * @param string $custom
     * @return string
     */
    private static function resolveFontFamily(string $choice, string $custom): string
    {
        if ($choice === 'custom' && $custom !== '') {
            return $custom;
        }

        return match ($choice) {
            'inter' => '"Inter", system-ui, sans-serif',
            'roboto' => '"Roboto", system-ui, sans-serif',
            'open-sans' => '"Open Sans", system-ui, sans-serif',
            'source-sans' => '"Source Sans 3", system-ui, sans-serif',
            'georgia' => 'Georgia, "Times New Roman", serif',
            default => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
        };
    }

    /**
     * Methode google slug.
     * @param string $choice
     * @return string|null
     */
    private static function googleSlug(string $choice): ?string
    {
        return match ($choice) {
            'inter' => 'Inter',
            'roboto' => 'Roboto',
            'open-sans' => 'Open+Sans',
            'source-sans' => 'Source+Sans+3',
            default => null,
        };
    }
}
