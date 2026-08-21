<?php
declare(strict_types=1);

/** Globale Kopf- und Fußzeile für alle CRM-E-Mail-Vorlagen. */
final class EmailLayoutSettings
{
    public const STORE_KEY = 'email_layout';

    /**
     * Liefert die Standardwerte.
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'header_show_logo' => true,
            'header_title' => '',
            'header_subline' => '',
            'body_opening_greeting' => 'Sehr geehrte Damen und Herren,',
            'footer_thanks_line' => 'Vielen Dank im Voraus',
            'footer_salutation' => 'Mit freundlichen Grüßen',
            'footer_signature' => 'Ihr {firma} Team',
            'footer_show_company_block' => true,
            'footer_company_name' => '',
            'footer_street' => '',
            'footer_postal' => '',
            'footer_city' => '',
            'footer_website' => '',
            'footer_extra_text' => '',
            'footer_show_legal_links' => true,
            'footer_url_impressum' => '',
            'footer_url_datenschutz' => '',
            'footer_url_agb' => '',
            'footer_show_social_links' => true,
            'footer_social_facebook' => '',
            'footer_social_instagram' => '',
            'footer_social_linkedin' => '',
            'footer_social_xing' => '',
            'footer_social_x' => '',
            'footer_social_youtube' => '',
            'footer_social_tiktok' => '',
        ];
    }

    /**
     * Methode social network labels.
     * @return array<string, mixed>
     */
    public static function socialNetworkLabels(): array
    {
        return [
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'linkedin' => 'LinkedIn',
            'xing' => 'Xing',
            'x' => 'X',
            'youtube' => 'YouTube',
            'tiktok' => 'TikTok',
        ];
    }

    /**
     * Liefert die aktuelle Konfiguration.
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        if (!Database::isConfigured()) {
            return self::defaults();
        }

        $stored = SettingsStore::get(self::STORE_KEY, self::defaults());
        $defaults = self::defaults();
        $out = [];
        foreach ($defaults as $key => $defaultValue) {
            $out[$key] = $stored[$key] ?? $defaultValue;
        }

        $out['header_show_logo'] = !empty($out['header_show_logo']);
        $out['footer_show_company_block'] = !array_key_exists('footer_show_company_block', $stored)
            || !empty($out['footer_show_company_block']);
        $out['footer_show_legal_links'] = !array_key_exists('footer_show_legal_links', $stored)
            || !empty($out['footer_show_legal_links']);
        $out['footer_show_social_links'] = !array_key_exists('footer_show_social_links', $stored)
            || !empty($out['footer_show_social_links']);

        foreach ($out as $key => $value) {
            if (is_string($value)) {
                $out[$key] = trim($value);
            }
        }

        return $out;
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
     * Methode email theme.
     * @return array<string, mixed>
     */
    public static function emailTheme(): array
    {
        $theme = CrmThemeSettings::colors();

        return [
            'bar_bg' => $theme['menu_bg'],
            'bar_text' => $theme['menu_text'],
            'bar_text_muted' => $theme['menu_text_muted'] ?? $theme['menu_text'],
            'bar_border' => $theme['menu_border'] ?? $theme['border'],
            'bar_link' => $theme['brand'],
            'body_bg' => $theme['body_bg'],
            'surface' => $theme['surface'],
            'text' => $theme['text'],
            'text_muted' => $theme['text_muted'],
            'border' => $theme['border'],
            'primary' => $theme['primary'],
        ];
    }

    /**
     * Führt aus: resolved header.
     * @param array $context
     * @param array|null $cfg
     * @return array<string, mixed>
     */
    public static function resolvedHeader(array $context = [], ?array $cfg = null): array
    {
        $cfg = $cfg ?? self::config();
        $company = CompanySettings::config();
        $emailTheme = self::emailTheme();

        return [
            'show_logo' => (bool) $cfg['header_show_logo'],
            'title' => self::replaceTokens(self::fieldOrCompany((string) $cfg['header_title'], $company['name'] ?? ''), $context),
            'subline' => self::replaceTokens((string) $cfg['header_subline'], $context),
            'background_color' => $emailTheme['bar_bg'],
            'text_color' => $emailTheme['bar_text'],
            'subline_color' => $emailTheme['bar_text_muted'],
            'link_color' => $emailTheme['bar_link'],
            'logo_url' => self::absoluteUrl(AppearanceSettings::logoUrl()),
            'logo_alt' => AppearanceSettings::logoAlt(),
        ];
    }

    /**
     * Führt aus: resolved footer.
     * @param array $context
     * @param array|null $cfg
     * @return array<string, mixed>
     */
    public static function resolvedFooter(array $context = [], ?array $cfg = null): array
    {
        $cfg = $cfg ?? self::config();
        $company = CompanySettings::config();
        $emailTheme = self::emailTheme();

        return [
            'opening_greeting' => self::replaceTokens(
                self::fieldOrDefault((string) $cfg['body_opening_greeting'], (string) self::defaults()['body_opening_greeting']),
                $context
            ),
            'thanks_line' => self::replaceTokens(
                self::fieldOrDefault((string) $cfg['footer_thanks_line'], (string) self::defaults()['footer_thanks_line']),
                $context
            ),
            'salutation' => self::replaceTokens(
                self::fieldOrDefault((string) $cfg['footer_salutation'], (string) self::defaults()['footer_salutation']),
                $context
            ),
            'signature' => self::replaceTokens(
                self::fieldOrDefault((string) $cfg['footer_signature'], (string) self::defaults()['footer_signature']),
                $context
            ),
            'show_company_block' => (bool) $cfg['footer_show_company_block'],
            'company_name' => self::replaceTokens(self::fieldOrCompany((string) $cfg['footer_company_name'], $company['name'] ?? ''), $context),
            'street' => self::replaceTokens(self::fieldOrCompany((string) $cfg['footer_street'], $company['street'] ?? ''), $context),
            'postal' => self::replaceTokens(self::fieldOrCompany((string) $cfg['footer_postal'], $company['postal'] ?? ''), $context),
            'city' => self::replaceTokens(self::fieldOrCompany((string) $cfg['footer_city'], $company['city'] ?? ''), $context),
            'website' => self::replaceTokens(self::fieldOrCompany((string) $cfg['footer_website'], $company['website'] ?? ''), $context),
            'extra_text' => self::replaceTokens((string) $cfg['footer_extra_text'], $context),
            'show_legal_links' => (bool) $cfg['footer_show_legal_links'],
            'legal_links' => self::resolvedLegalLinks($cfg),
            'show_social_links' => (bool) $cfg['footer_show_social_links'],
            'social_links' => self::resolvedSocialLinks($cfg),
            'signature_text_color' => $emailTheme['text'],
            'surface_color' => $emailTheme['surface'],
            'bar_background_color' => $emailTheme['bar_bg'],
            'bar_text_color' => $emailTheme['bar_text'],
            'bar_text_muted' => $emailTheme['bar_text_muted'],
            'bar_border_color' => $emailTheme['bar_border'],
            'link_color' => $emailTheme['bar_link'],
        ];
    }

    /**
     * Methode preview config from input.
     * @param array $input
     * @return array<string, mixed>
     */
    public static function previewConfigFromInput(array $input): array
    {
        $defaults = self::defaults();
        $data = $defaults;
        foreach ($defaults as $key => $defaultValue) {
            if (in_array($key, [
                'header_show_logo',
                'footer_show_company_block',
                'footer_show_legal_links',
                'footer_show_social_links',
            ], true)) {
                continue;
            }
            $data[$key] = trim((string) ($input[$key] ?? $defaultValue));
        }

        $data['header_show_logo'] = !empty($input['header_show_logo']);
        $data['footer_show_company_block'] = !empty($input['footer_show_company_block']);
        $data['footer_show_legal_links'] = !empty($input['footer_show_legal_links']);
        $data['footer_show_social_links'] = !empty($input['footer_show_social_links']);

        foreach (['footer_url_impressum', 'footer_url_datenschutz', 'footer_url_agb'] as $urlKey) {
            $data[$urlKey] = self::normalizeUrl($data[$urlKey]);
        }
        foreach (array_keys(self::socialNetworkLabels()) as $networkKey) {
            $field = 'footer_social_' . $networkKey;
            $data[$field] = self::normalizeUrl((string) ($data[$field] ?? ''));
        }

        return $data;
    }

    /**
     * Führt aus: resolved legal links.
     * @param array $cfg
     * @return array<string, mixed>
     */
    private static function resolvedLegalLinks(array $cfg): array
    {
        $links = [];
        $map = [
            'impressum' => 'Impressum',
            'datenschutz' => 'Datenschutz',
            'agb' => 'AGB',
        ];
        foreach ($map as $key => $label) {
            $url = self::normalizeUrl((string) ($cfg['footer_url_' . $key] ?? ''));
            if ($url !== '') {
                $links[] = ['label' => $label, 'url' => $url];
            }
        }

        return $links;
    }

    /**
     * Führt aus: resolved social links.
     * @param array $cfg
     * @return array<string, mixed>
     */
    private static function resolvedSocialLinks(array $cfg): array
    {
        $links = [];
        foreach (self::socialNetworkLabels() as $key => $label) {
            $url = self::normalizeUrl((string) ($cfg['footer_social_' . $key] ?? ''));
            if ($url !== '') {
                $links[] = ['label' => $label, 'url' => $url];
            }
        }

        return $links;
    }

    /**
     * Speichert Formulardaten.
     * @param array $input
     * @return void
     */
    public static function saveFromPost(array $input): void
    {
        $data = self::previewConfigFromInput($input);
        $data['header_show_logo'] = !empty($input['header_show_logo']) ? 1 : 0;
        $data['footer_show_company_block'] = !empty($input['footer_show_company_block']) ? 1 : 0;
        $data['footer_show_legal_links'] = !empty($input['footer_show_legal_links']) ? 1 : 0;
        $data['footer_show_social_links'] = !empty($input['footer_show_social_links']) ? 1 : 0;

        SettingsStore::set(self::STORE_KEY, $data);
    }

    /**
     * Methode absolute url.
     * @param string $path
     * @return string
     */
    public static function absoluteUrl(string $path): string
    {
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

            return $scheme . '://' . $host . ($path[0] === '/' ? $path : '/' . $path);
        }

        $home = trim((string) App::config('home_url', ''));
        if ($home !== '') {
            return rtrim($home, '/') . ($path[0] === '/' ? $path : '/' . $path);
        }

        return $path;
    }

    /**
     * Methode field or company.
     * @param string $value
     * @param string $companyValue
     * @return string
     */
    private static function fieldOrCompany(string $value, string $companyValue): string
    {
        return $value !== '' ? $value : trim($companyValue);
    }

    /**
     * Methode field or default.
     * @param string $value
     * @param string $default
     * @return string
     */
    private static function fieldOrDefault(string $value, string $default): string
    {
        $value = trim($value);

        return $value !== '' ? $value : trim($default);
    }

    /**
     * Methode replace tokens.
     * @param string $text
     * @param array $context
     * @return string
     */
    private static function replaceTokens(string $text, array $context): string
    {
        if ($text === '') {
            return '';
        }

        $company = CompanySettings::config();
        $merged = array_merge([
            'firma' => $company['name'] !== '' ? $company['name'] : CompanySettings::displayName(),
            'firma_adresse' => trim(implode("\n", array_filter([
                trim($company['street'] ?? ''),
                trim(($company['postal'] ?? '') . ' ' . ($company['city'] ?? '')),
            ]))),
            'firma_website' => trim($company['website'] ?? ''),
        ], $context);

        return CalendarEmailTokens::replace($text, $merged);
    }

    /**
     * Führt aus: normalize url.
     * @param string $url
     * @return string
     */
    private static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return 'https://' . $url;
    }
}
