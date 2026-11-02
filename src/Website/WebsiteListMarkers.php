<?php
declare(strict_types=1);

/**
 * Auflistungszeichen für Website-Listen-Blöcke (Builder + öffentliche Seite).
 * JS-Spiegel: assets/js/website-builder.js (listMarkerOptions / listMarkerPresentation).
 */
final class WebsiteListMarkers
{
    /** @var list<string> */
    private const UL_CSS = ['disc', 'circle', 'square', 'none'];

    /** @var list<string> */
    private const OL_CSS = [
        'decimal',
        'decimal-leading-zero',
        'lower-alpha',
        'upper-alpha',
        'lower-roman',
        'upper-roman',
    ];

    /** symbol:id → CSS-Modifikator ws-list--m-* */
    private const SYMBOLS = [
        'symbol:heart' => 'heart',
        'symbol:arrow' => 'arrow',
        'symbol:arrow-bold' => 'arrow-bold',
        'symbol:diamond' => 'diamond',
        'symbol:check' => 'check',
        'symbol:star' => 'star',
    ];

    /**
     * @return list<string>
     */
    public static function allowedMarkers(string $listType): array
    {
        if ($listType === 'ol') {
            return self::OL_CSS;
        }

        return array_merge(self::UL_CSS, array_keys(self::SYMBOLS), ['image']);
    }

    public static function normalize(string $listType, string $marker): string
    {
        $listType = $listType === 'ol' ? 'ol' : 'ul';
        $marker = trim($marker);
        $allowed = self::allowedMarkers($listType);
        if (in_array($marker, $allowed, true)) {
            return $marker;
        }

        return $listType === 'ol' ? 'decimal' : 'disc';
    }

    public static function normalizeImageUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || !WebsiteContent::isSafeHref($url)) {
            return '';
        }

        return $url;
    }

    public static function usesCustomMarker(string $marker): bool
    {
        return str_starts_with($marker, 'symbol:') || $marker === 'image';
    }

    /**
     * @return array{class: string, style: string}
     */
    public static function presentation(string $listType, string $marker, string $markerImageUrl): array
    {
        $listType = $listType === 'ol' ? 'ol' : 'ul';
        $marker = self::normalize($listType, $marker);
        $baseClass = $listType === 'ol' ? 'ws-list' : 'ws-list';

        if ($marker === 'image') {
            $url = self::normalizeImageUrl($markerImageUrl);
            if ($url === '') {
                return [
                    'class' => $baseClass,
                    'style' => 'list-style-type:disc',
                ];
            }

            return [
                'class' => $baseClass . ' ws-list--custom ws-list--m-image',
                'style' => '--ws-list-marker-image:' . self::cssUrl($url),
            ];
        }

        if (isset(self::SYMBOLS[$marker])) {
            return [
                'class' => $baseClass . ' ws-list--custom ws-list--m-' . self::SYMBOLS[$marker],
                'style' => '',
            ];
        }

        return [
            'class' => $baseClass,
            'style' => 'list-style-type:' . $marker,
        ];
    }

    /**
     * Builder-Vorschau: gleiche Modifikatoren auf dg-website-block__list.
     *
     * @return array{class: string, style: string}
     */
    public static function builderPresentation(string $listType, string $marker, string $markerImageUrl): array
    {
        $public = self::presentation($listType, $marker, $markerImageUrl);
        $public['class'] = str_replace('ws-list', 'dg-website-block__list', $public['class']);

        return $public;
    }

    private static function cssUrl(string $url): string
    {
        $escaped = str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '', ''], $url);

        return 'url("' . $escaped . '")';
    }
}
