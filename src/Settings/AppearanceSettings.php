<?php
declare(strict_types=1);

final class AppearanceSettings
{
    public const STORE_KEY = 'appearance';

    /** @return array<string, string> */
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

    /** @return array<string, string> */
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

    /** @return array<string, string> */
    public static function config(): array
    {
        $cfg = SettingsStore::get(self::STORE_KEY, self::defaults());
        $size = (int) ($cfg['email_font_size'] ?? 16);
        $cfg['email_font_size'] = (string) max(12, min(24, $size));
        $cfg['logo_media_id'] = trim((string) ($cfg['logo_media_id'] ?? ''));
        $cfg['favicon_media_id'] = trim((string) ($cfg['favicon_media_id'] ?? ''));

        return $cfg;
    }

    /** @return array<string, string> */
    public static function forForm(): array
    {
        return self::config();
    }

    public static function logoMediaId(): string
    {
        return trim((string) (self::config()['logo_media_id'] ?? ''));
    }

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

    public static function setLogoMediaId(string $mediaId): void
    {
        if (!MediaId::isValid($mediaId)) {
            throw new InvalidArgumentException('Ungültige Medien-ID für das Logo.');
        }

        $cfg = self::config();
        $cfg['logo_media_id'] = $mediaId;
        SettingsStore::set(self::STORE_KEY, $cfg);
    }

    public static function clearLogoMediaId(): void
    {
        $cfg = self::config();
        $cfg['logo_media_id'] = '';
        SettingsStore::set(self::STORE_KEY, $cfg);
    }

    public static function faviconMediaId(): string
    {
        return trim((string) (self::config()['favicon_media_id'] ?? ''));
    }

    public static function hasFavicon(): bool
    {
        $id = self::faviconMediaId();

        return $id !== '' && MediaId::isValid($id);
    }

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

    public static function setFaviconMediaId(string $mediaId): void
    {
        if (!MediaId::isValid($mediaId)) {
            throw new InvalidArgumentException('Ungültige Medien-ID für das Favicon.');
        }

        $cfg = self::config();
        $cfg['favicon_media_id'] = $mediaId;
        SettingsStore::set(self::STORE_KEY, $cfg);
    }

    public static function clearFaviconMediaId(): void
    {
        $cfg = self::config();
        $cfg['favicon_media_id'] = '';
        SettingsStore::set(self::STORE_KEY, $cfg);
    }

    public static function uiFontFamily(): string
    {
        return self::resolveFontFamily(
            (string) (self::config()['ui_font'] ?? 'system'),
            trim((string) (self::config()['custom_ui_font'] ?? ''))
        );
    }

    public static function emailFontFamily(): string
    {
        return self::resolveFontFamily(
            (string) (self::config()['email_font'] ?? 'system'),
            trim((string) (self::config()['custom_email_font'] ?? ''))
        );
    }

    public static function emailFontSizePx(): int
    {
        return (int) (self::config()['email_font_size'] ?? 16);
    }

    public static function wrapEmailHtml(string $innerHtml): string
    {
        $family = htmlspecialchars(self::emailFontFamily(), ENT_QUOTES, 'UTF-8');
        $size = self::emailFontSizePx();

        return '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"></head><body'
            . ' style="margin:0;padding:16px;font-family:' . $family . ';font-size:' . $size . 'px;line-height:1.5;color:#1d2327;">'
            . $innerHtml
            . '</body></html>';
    }

    /** @return array<string, string> */
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

    /** @param array<string, mixed> $input */
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
