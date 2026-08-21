<?php
declare(strict_types=1);

/**
 * Generates sitemap.xml and robots.txt for the public website.
 */
final class SitemapGenerator
{
    /**
     * robots.txt with disallowed CRM paths and sitemap reference.
     */
    public static function robotsTxt(): string
    {
        $baseUrl = App::publicBaseUrl();
        $out = "User-agent: *\n";
        $out .= "Disallow: /app\n";
        $out .= "Disallow: /login\n";
        $out .= "Disallow: /register\n";
        $out .= "Disallow: /install.php\n";
        $out .= "Disallow: /api/\n";
        $out .= "Disallow: /konto-aktivieren\n";
        $out .= "Disallow: /passwort-vergessen\n";
        $out .= "Disallow: /passwort-zuruecksetzen\n";
        $out .= "\n";
        if ($baseUrl !== '') {
            $out .= "Sitemap: $baseUrl/sitemap.xml\n";
        }
        return $out;
    }

    /**
     * sitemap.xml with homepage, legal pages and published website pages.
     *
     * @return string XML document or empty string if no base URL.
     */
    public static function sitemapXml(): string
    {
        $baseUrl = App::publicBaseUrl();
        if ($baseUrl === '') {
            return '';
        }

        $urls = [];

        // Homepage
        $urls[] = ['loc' => $baseUrl . '/', 'priority' => '1.0', 'changefreq' => 'weekly'];

        // Legal pages
        foreach (['impressum', 'datenschutz', 'agb'] as $slug) {
            $urls[] = ['loc' => $baseUrl . '/' . $slug, 'priority' => '0.3', 'changefreq' => 'monthly'];
        }

        // Website pages from DB
        if (Database::isConfigured()) {
            try {
                $pages = WebsitePageRepository::listAll();
                foreach ($pages as $page) {
                    $slug = trim((string) ($page['slug'] ?? ''));
                    if ($slug === '' || in_array($slug, ['impressum', 'datenschutz', 'agb'], true)) {
                        continue;
                    }
                    $urls[] = [
                        'loc' => $baseUrl . '/' . $slug,
                        'priority' => '0.5',
                        'changefreq' => 'weekly',
                        'lastmod' => !empty($page['updated_at']) ? date('Y-m-d', strtotime($page['updated_at'])) : null,
                    ];
                }
            } catch (Throwable) {
                // DB not ready yet
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($u['loc']) . "</loc>\n";
            if (!empty($u['lastmod'])) {
                $xml .= '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
            }
            $xml .= '    <changefreq>' . $u['changefreq'] . "</changefreq>\n";
            $xml .= '    <priority>' . $u['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= "</urlset>\n";

        return $xml;
    }
}
