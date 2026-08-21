<?php
declare(strict_types=1);

/**
 * Consent-aware pageview tracking for the public website.
 * No IP storage; skips bots and preview mode.
 */
final class WebsitePageviewTracker
{
    /** @var list<string> */
    private const BOT_MARKERS = [
        'bot', 'crawl', 'spider', 'slurp', 'bingpreview', 'facebookexternalhit',
        'embedly', 'quora link preview', 'whatsapp', 'telegram', 'preview',
        'headless', 'phantom', 'selenium', 'wget', 'curl/', 'python-requests',
    ];

    /**
     * @param array<string, mixed>|null $page Mapped website page (optional)
     */
    public static function trackPublicPage(?array $page = null, bool $previewMode = false): void
    {
        if ($previewMode || PHP_SAPI === 'cli') {
            return;
        }
        if (!Database::isConfigured() || !CookieConsent::hasConsent('analytics')) {
            return;
        }
        if (self::isBot((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
            return;
        }

        $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        if ($page !== null) {
            $slug = (string) ($page['slug'] ?? '');
            if ($slug !== '') {
                $path = WebsitePageRepository::publicPath($slug);
            }
            $pageId = (int) ($page['id'] ?? 0);
        } else {
            $pageId = 0;
        }

        $refHost = self::referrerHost((string) ($_SERVER['HTTP_REFERER'] ?? ''));
        // Ignore same-site referrers as "source"
        $ownHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($refHost !== '' && $ownHost !== '' && ($refHost === $ownHost || str_ends_with($refHost, '.' . $ownHost))) {
            $refHost = '';
        }

        try {
            WebsitePageviewRepository::record($path, $pageId > 0 ? $pageId : null, $refHost);
        } catch (Throwable) {
            // Tracking must never break the public page.
        }
    }

    public static function isBot(string $ua): bool
    {
        $ua = strtolower(trim($ua));
        if ($ua === '') {
            return true;
        }
        foreach (self::BOT_MARKERS as $marker) {
            if (str_contains($ua, $marker)) {
                return true;
            }
        }

        return false;
    }

    public static function referrerHost(string $referrer): string
    {
        $referrer = trim($referrer);
        if ($referrer === '') {
            return '';
        }
        $host = parse_url($referrer, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return '';
        }

        return strtolower($host);
    }

    /**
     * External analytics dashboard URLs for the admin UI.
     *
     * @return list<array{label: string, url: string, hint: string}>
     */
    public static function externalDashboardLinks(): array
    {
        return WebsiteAnalytics::adminLinks();
    }
}
