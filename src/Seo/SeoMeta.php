<?php
declare(strict_types=1);

/**
 * SEO meta tags, Open Graph, and JSON-LD structured data.
 */
final class SeoMeta
{
    /**
     * Renders <meta>, Open Graph, and JSON-LD tags for a page.
     *
     * @param array{title?: string, description?: string, image?: string, type?: string, url?: string, noindex?: bool} $page
     * @return string HTML fragment for <head>.
     */
    public static function renderHead(array $page = []): string
    {
        $c = CompanySettings::config();
        $baseUrl = App::publicBaseUrl();
        $siteName = trim($c['name'] ?: (string) App::config('crm_name', ''));

        $title = trim((string) ($page['title'] ?? ''));
        $description = trim((string) ($page['description'] ?? ''));
        $image = trim((string) ($page['image'] ?? ''));
        $url = trim((string) ($page['url'] ?? ''));
        $type = trim((string) ($page['type'] ?? 'website'));
        $noindex = !empty($page['noindex']);

        if ($url === '' && $baseUrl !== '') {
            $url = $baseUrl . ($_SERVER['REQUEST_URI'] ?? '/');
        }

        $html = '';

        // Basic meta
        if ($description !== '') {
            $html .= '<meta name="description" content="' . self::esc($description) . '">' . "\n";
        }
        if ($noindex) {
            $html .= '<meta name="robots" content="noindex, nofollow">' . "\n";
        }
        if ($url !== '') {
            $html .= '<link rel="canonical" href="' . self::esc(strtok($url, '?')) . '">' . "\n";
        }

        // Open Graph
        if ($title !== '') {
            $html .= '<meta property="og:title" content="' . self::esc($title) . '">' . "\n";
        }
        if ($description !== '') {
            $html .= '<meta property="og:description" content="' . self::esc($description) . '">' . "\n";
        }
        $html .= '<meta property="og:type" content="' . self::esc($type) . '">' . "\n";
        if ($url !== '') {
            $html .= '<meta property="og:url" content="' . self::esc($url) . '">' . "\n";
        }
        if ($siteName !== '') {
            $html .= '<meta property="og:site_name" content="' . self::esc($siteName) . '">' . "\n";
        }
        if ($image !== '') {
            $html .= '<meta property="og:image" content="' . self::esc($image) . '">' . "\n";
        }
        $html .= '<meta property="og:locale" content="de_DE">' . "\n";

        return $html;
    }

    /**
     * JSON-LD structured data for Organization / LocalBusiness.
     *
     * @return string <script type="application/ld+json">…</script> or empty string.
     */
    public static function organizationJsonLd(): string
    {
        $c = CompanySettings::config();
        $ext = CompanyExtendedSettings::config();
        $baseUrl = App::publicBaseUrl();

        if (trim($c['name']) === '') {
            return '';
        }

        $type = match ($ext['company_type'] ?? '') {
            'praxis' => 'MedicalBusiness',
            'kanzlei' => 'LegalService',
            'ev', 'stiftung', 'ggmbh' => 'Organization',
            default => 'LocalBusiness',
        };

        $data = [
            '@context' => 'https://schema.org',
            '@type' => $type,
            'name' => $c['name'],
            'url' => $baseUrl ?: ($c['website'] ?: ''),
        ];

        $legalName = trim((string) ($ext['legal_name'] ?? ''));
        if ($legalName !== '' && $legalName !== $c['name']) {
            $data['legalName'] = $legalName;
        }

        if ($c['email'] !== '') {
            $data['email'] = $c['email'];
        }
        if ($c['phone'] !== '') {
            $data['telephone'] = $c['phone'];
        }

        if ($c['street'] !== '' || $c['city'] !== '') {
            $data['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $c['street'],
                'postalCode' => $c['postal'],
                'addressLocality' => $c['city'],
                'addressCountry' => ($c['country'] ?? 'DE') ?: 'DE',
            ];
        }

        $vatId = trim((string) ($c['vat_id'] ?? ''));
        if ($vatId !== '') {
            $data['vatID'] = $vatId;
        }

        $taxNumber = trim((string) ($c['tax_number'] ?? ''));
        if ($taxNumber !== '') {
            $data['taxID'] = $taxNumber;
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }

    /** Escapes attribute/text content for meta tags. */
    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
