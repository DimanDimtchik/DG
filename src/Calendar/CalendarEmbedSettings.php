<?php
declare(strict_types=1);

/** Öffentliche Online-Terminbuchung (Einbindung) inkl. QR-Design. */
final class CalendarEmbedSettings
{
    public const STORE_KEY = 'calendar_embed';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'online_booking_enabled' => false,
            'page_title' => 'Termin online vereinbaren',
            'intro_text' => 'Wählen Sie eine Leistung, einen freien Termin und hinterlassen Sie Ihre Kontaktdaten. Sie erhalten eine Bestätigung per E-Mail.',
            'success_message' => 'Vielen Dank — Ihr Termin ist gebucht. Sie erhalten in Kürze eine Bestätigung per E-Mail.',
            'qr' => self::qrDefaults(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function qrDefaults(): array
    {
        return [
            'size' => 280,
            'export_size' => 1200,
            'margin' => 12,
            'fg_color' => '#1a1a1a',
            'bg_color' => '#ffffff',
            'dots_type' => 'rounded',
            'corners_square_type' => 'extra-rounded',
            'corners_dot_type' => 'dot',
            'error_correction' => 'Q',
            'shape' => 'square',
            'frame_enabled' => 1,
            'frame_width' => 3,
            'frame_color' => '#1a1a1a',
            'frame_radius' => 12,
            'frame_padding' => 16,
            'frame_preset' => 'classic',
            'caption' => '',
            'download_name' => 'termin-buchen',
            'center_image_source' => 'none',
            'center_media_id' => '',
            'center_image_size' => 'tiny',
            'center_emoji' => '📅',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        $stored = SettingsStore::get(self::STORE_KEY, self::defaults());
        $defaults = self::defaults();
        $out = [];
        foreach ($defaults as $key => $defaultValue) {
            if ($key === 'qr') {
                continue;
            }
            $out[$key] = $stored[$key] ?? $defaultValue;
        }
        $out['online_booking_enabled'] = !empty($out['online_booking_enabled']);
        foreach (['page_title', 'intro_text', 'success_message'] as $textKey) {
            $out[$textKey] = trim((string) $out[$textKey]);
        }
        $out['qr'] = self::normalizeQr(is_array($stored['qr'] ?? null) ? $stored['qr'] : []);

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        $cfg = self::config();
        $qr = $cfg['qr'];
        $company = '';
        try {
            $company = trim((string) (CompanySettings::config()['name'] ?? ''));
        } catch (Throwable) {
            $company = '';
        }
        if ($company === '') {
            $company = (string) App::config('crm_name');
        }
        if (trim((string) $qr['caption']) === '') {
            $qr['caption'] = 'Termin buchen – ' . $company;
        }

        $logoUrl = AppearanceSettings::logoUrl();
        $faviconUrl = MediaFaviconGenerator::publicUrl(48);
        $centerMediaId = (string) $qr['center_media_id'];
        $centerCustomUrl = '';
        if ($centerMediaId !== '' && MediaId::isValid($centerMediaId) && Database::isConfigured()) {
            try {
                MediaRepository::ensureTables();
                if (MediaRepository::find($centerMediaId) !== null) {
                    $centerCustomUrl = MediaStorage::publicUrl($centerMediaId);
                }
            } catch (Throwable) {
                $centerCustomUrl = '';
            }
        }

        return array_merge($cfg, [
            'public_url' => self::publicBookingUrl(),
            'qr' => $qr,
            'qr_logo_url' => $logoUrl,
            'qr_favicon_url' => $faviconUrl,
            'qr_center_custom_url' => $centerCustomUrl,
            'qr_company_name' => $company,
        ]);
    }

    public static function isOnlineBookingEnabled(): bool
    {
        return (bool) self::config()['online_booking_enabled'];
    }

    public static function pageTitle(): string
    {
        $title = trim((string) self::config()['page_title']);

        return $title !== '' ? $title : (string) self::defaults()['page_title'];
    }

    public static function introText(): string
    {
        return trim((string) self::config()['intro_text']);
    }

    public static function successMessage(): string
    {
        $message = trim((string) self::config()['success_message']);

        return $message !== '' ? $message : (string) self::defaults()['success_message'];
    }

    public static function publicBookingUrl(): string
    {
        $base = App::publicBaseUrl();

        return $base !== '' ? $base . '/termin' : '/termin';
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function save(array $input): void
    {
        $qrInput = is_array($input['qr'] ?? null) ? $input['qr'] : $input;
        SettingsStore::set(self::STORE_KEY, [
            'online_booking_enabled' => !empty($input['online_booking_enabled']) ? 1 : 0,
            'page_title' => trim((string) ($input['page_title'] ?? '')),
            'intro_text' => trim((string) ($input['intro_text'] ?? '')),
            'success_message' => trim((string) ($input['success_message'] ?? '')),
            'qr' => self::normalizeQr($qrInput),
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function normalizeQr(array $input): array
    {
        $d = self::qrDefaults();
        $hex = static function (string $value, string $fallback): string {
            $value = trim($value);
            if (preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1) {
                return strtolower($value);
            }

            return $fallback;
        };
        $pick = static function (string $value, array $allowed, string $fallback): string {
            return in_array($value, $allowed, true) ? $value : $fallback;
        };

        $source = $pick(
            (string) ($input['center_image_source'] ?? $d['center_image_source']),
            ['none', 'logo', 'favicon', 'custom', 'emoji'],
            'none'
        );
        $mediaId = trim((string) ($input['center_media_id'] ?? ''));
        if ($mediaId !== '' && !MediaId::isValid($mediaId)) {
            $mediaId = '';
        }
        $emoji = trim((string) ($input['center_emoji'] ?? $d['center_emoji']));
        if ($emoji === '' || mb_strlen($emoji) > 32) {
            $emoji = (string) $d['center_emoji'];
        }

        $download = preg_replace('/[^a-zA-Z0-9._-]+/', '-', (string) ($input['download_name'] ?? $d['download_name'])) ?? 'termin-buchen';
        $download = trim($download, '-._') ?: 'termin-buchen';

        return [
            'size' => max(160, min(800, (int) ($input['size'] ?? $d['size']))),
            'export_size' => max(400, min(2400, (int) ($input['export_size'] ?? $d['export_size']))),
            'margin' => max(0, min(80, (int) ($input['margin'] ?? $d['margin']))),
            'fg_color' => $hex((string) ($input['fg_color'] ?? ''), (string) $d['fg_color']),
            'bg_color' => $hex((string) ($input['bg_color'] ?? ''), (string) $d['bg_color']),
            'dots_type' => $pick(
                (string) ($input['dots_type'] ?? ''),
                ['square', 'rounded', 'dots', 'classy', 'classy-rounded', 'extra-rounded'],
                (string) $d['dots_type']
            ),
            'corners_square_type' => $pick(
                (string) ($input['corners_square_type'] ?? ''),
                ['square', 'dot', 'extra-rounded'],
                (string) $d['corners_square_type']
            ),
            'corners_dot_type' => $pick(
                (string) ($input['corners_dot_type'] ?? ''),
                ['square', 'dot'],
                (string) $d['corners_dot_type']
            ),
            'error_correction' => $pick(
                (string) ($input['error_correction'] ?? ''),
                ['L', 'M', 'Q', 'H'],
                (string) $d['error_correction']
            ),
            'shape' => $pick((string) ($input['shape'] ?? ''), ['square', 'circle'], (string) $d['shape']),
            'frame_enabled' => !empty($input['frame_enabled']) ? 1 : 0,
            'frame_width' => max(0, min(24, (int) ($input['frame_width'] ?? $d['frame_width']))),
            'frame_color' => $hex((string) ($input['frame_color'] ?? ''), (string) $d['frame_color']),
            'frame_radius' => max(0, min(80, (int) ($input['frame_radius'] ?? $d['frame_radius']))),
            'frame_padding' => max(0, min(80, (int) ($input['frame_padding'] ?? $d['frame_padding']))),
            'frame_preset' => $pick(
                (string) ($input['frame_preset'] ?? ''),
                ['none', 'classic', 'soft', 'bold', 'card'],
                (string) $d['frame_preset']
            ),
            'caption' => trim(mb_substr((string) ($input['caption'] ?? ''), 0, 120)),
            'download_name' => $download,
            'center_image_source' => $source,
            'center_media_id' => $source === 'custom' ? $mediaId : '',
            'center_image_size' => $pick(
                (string) ($input['center_image_size'] ?? ''),
                ['tiny', 'small'],
                (string) $d['center_image_size']
            ),
            'center_emoji' => $emoji,
        ];
    }
}
