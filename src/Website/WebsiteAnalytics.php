<?php
declare(strict_types=1);

/**
 * Google Analytics 4 and Tag Manager snippets for the public website.
 * Scripts are only output when CookieConsent category "analytics" is granted.
 */
final class WebsiteAnalytics
{
    /**
     * Normalize a GA4 measurement id (G-XXXXXXXX).
     */
    public static function normalizeGaId(string $raw): string
    {
        $raw = strtoupper(trim($raw));
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^G-[A-Z0-9]+$/', $raw)) {
            return $raw;
        }

        return '';
    }

    /**
     * Normalize a GTM container id (GTM-XXXXXXX).
     */
    public static function normalizeGtmId(string $raw): string
    {
        $raw = strtoupper(trim($raw));
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^GTM-[A-Z0-9]+$/', $raw)) {
            return $raw;
        }

        return '';
    }

    /**
     * @return array{ga_id: string, gtm_id: string}
     */
    public static function configuredIds(): array
    {
        $chrome = WebsiteSettings::chrome();

        return [
            'ga_id' => self::normalizeGaId((string) ($chrome['ga_measurement_id'] ?? '')),
            'gtm_id' => self::normalizeGtmId((string) ($chrome['gtm_container_id'] ?? '')),
        ];
    }

    public static function isConfigured(): bool
    {
        $ids = self::configuredIds();

        return $ids['ga_id'] !== '' || $ids['gtm_id'] !== '';
    }

    /**
     * Head snippets (GTM + GA4) when analytics consent is present.
     */
    public static function headHtml(): string
    {
        if (!CookieConsent::hasConsent('analytics')) {
            return '';
        }

        $ids = self::configuredIds();
        $html = '';

        if ($ids['gtm_id'] !== '') {
            $gtm = View::escape($ids['gtm_id']);
            $html .= "<!-- Google Tag Manager -->\n"
                . "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':"
                . "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],"
                . "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src="
                . "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);"
                . "})(window,document,'script','dataLayer','{$gtm}');</script>\n"
                . "<!-- End Google Tag Manager -->\n";
        }

        // If GTM is used, GA is usually configured inside GTM — still allow standalone GA4.
        if ($ids['ga_id'] !== '' && $ids['gtm_id'] === '') {
            $ga = View::escape($ids['ga_id']);
            $html .= "<!-- Google Analytics (GA4) -->\n"
                . "<script async src=\"https://www.googletagmanager.com/gtag/js?id={$ga}\"></script>\n"
                . "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}"
                . "gtag('js', new Date());gtag('config', '{$ga}');</script>\n";
        } elseif ($ids['ga_id'] !== '' && $ids['gtm_id'] !== '') {
            // Both set: load GA4 via gtag as well (harmless if also in GTM; user may want only GTM)
            // Prefer GTM-only when both set to avoid double counting — skip standalone GA.
        }

        return $html;
    }

    /**
     * Body-opening noscript iframe for GTM.
     */
    public static function bodyOpenHtml(): string
    {
        if (!CookieConsent::hasConsent('analytics')) {
            return '';
        }
        $ids = self::configuredIds();
        if ($ids['gtm_id'] === '') {
            return '';
        }
        $gtm = View::escape($ids['gtm_id']);

        return "<!-- Google Tag Manager (noscript) -->\n"
            . "<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id={$gtm}\" "
            . "height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n"
            . "<!-- End Google Tag Manager (noscript) -->\n";
    }

    /**
     * Admin links to Google dashboards.
     *
     * @return list<array{label: string, url: string, hint: string}>
     */
    public static function adminLinks(): array
    {
        $ids = self::configuredIds();
        $links = [];
        if ($ids['ga_id'] !== '') {
            $links[] = [
                'label' => 'Google Analytics',
                'url' => 'https://analytics.google.com/analytics/web/',
                'hint' => 'Property ' . $ids['ga_id'],
            ];
        }
        if ($ids['gtm_id'] !== '') {
            $links[] = [
                'label' => 'Google Tag Manager',
                'url' => 'https://tagmanager.google.com/',
                'hint' => 'Container ' . $ids['gtm_id'],
            ];
        }

        return $links;
    }
}
