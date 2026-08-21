<?php
declare(strict_types=1);

/** Öffentliche Online-Terminbuchung (Einbindung). */
final class CalendarEmbedSettings
{
    public const STORE_KEY = 'calendar_embed';

    /**
     * Liefert die Standardwerte.
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'online_booking_enabled' => false,
            'page_title' => 'Termin online vereinbaren',
            'intro_text' => 'Wählen Sie eine Leistung, einen freien Termin und hinterlassen Sie Ihre Kontaktdaten. Sie erhalten eine Bestätigung per E-Mail.',
            'success_message' => 'Vielen Dank — Ihr Termin ist gebucht. Sie erhalten in Kürze eine Bestätigung per E-Mail.',
        ];
    }

    /**
     * Liefert die aktuelle Konfiguration.
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        $stored = SettingsStore::get(self::STORE_KEY, self::defaults());
        $defaults = self::defaults();
        $out = [];
        foreach ($defaults as $key => $defaultValue) {
            $out[$key] = $stored[$key] ?? $defaultValue;
        }
        $out['online_booking_enabled'] = !empty($out['online_booking_enabled']);

        foreach (['page_title', 'intro_text', 'success_message'] as $textKey) {
            $out[$textKey] = trim((string) $out[$textKey]);
        }

        return $out;
    }

    /**
     * Methode for form.
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        $cfg = self::config();

        return array_merge($cfg, [
            'public_url' => self::publicBookingUrl(),
        ]);
    }

    /**
     * Prüft: is online booking enabled.
     * @return bool
     */
    public static function isOnlineBookingEnabled(): bool
    {
        return (bool) self::config()['online_booking_enabled'];
    }

    /**
     * Methode page title.
     * @return string
     */
    public static function pageTitle(): string
    {
        $title = trim((string) self::config()['page_title']);

        return $title !== '' ? $title : (string) self::defaults()['page_title'];
    }

    /**
     * Methode intro text.
     * @return string
     */
    public static function introText(): string
    {
        return trim((string) self::config()['intro_text']);
    }

    /**
     * Methode success message.
     * @return string
     */
    public static function successMessage(): string
    {
        $message = trim((string) self::config()['success_message']);

        return $message !== '' ? $message : (string) self::defaults()['success_message'];
    }

    /**
     * Methode public booking url.
     * @return string
     */
    public static function publicBookingUrl(): string
    {
        $base = App::publicBaseUrl();

        return $base !== '' ? $base . '/termin' : '/termin';
    }

    /**
     * Methode save.
     * @param array $input
     * @return void
     */
    public static function save(array $input): void
    {
        SettingsStore::set(self::STORE_KEY, [
            'online_booking_enabled' => !empty($input['online_booking_enabled']) ? 1 : 0,
            'page_title' => trim((string) ($input['page_title'] ?? '')),
            'intro_text' => trim((string) ($input['intro_text'] ?? '')),
            'success_message' => trim((string) ($input['success_message'] ?? '')),
        ]);
    }
}
