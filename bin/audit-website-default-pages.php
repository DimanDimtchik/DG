<?php
declare(strict_types=1);

/**
 * Prüft, welche Bootstrap-/Default-Seiten fehlen.
 * Aufruf: php bin/audit-website-default-pages.php
 */
require dirname(__DIR__) . '/bootstrap.php';

$expected = [
    'impressum' => 'Pflichtseite (Legal)',
    'datenschutz' => 'Pflichtseite (Legal)',
    'agb' => 'Pflichtseite (Legal)',
    'widerruf' => 'Pflichtseite (Legal)',
    'startseite' => 'Startseite',
    'kontakt' => 'Kontakt',
    'terminkalender' => 'Online-Buchung',
    'stempeluhr' => 'Stempeluhr-Kiosk (Entwurf ok)',
    'apps' => 'Mobile Apps Download (Entwurf ok)',
    'preislisten' => 'Preislisten (Entwurf ok)',
];

if (!Database::isConfigured()) {
    fwrite(STDERR, "NO_DB\n");
    exit(2);
}

$found = [];
foreach (WebsitePageRepository::list() as $page) {
    $slug = (string) ($page['slug'] ?? '');
    if ($slug === '') {
        continue;
    }
    $found[$slug] = [
        'id' => (int) ($page['id'] ?? 0),
        'title' => (string) ($page['title'] ?? ''),
        'status' => (string) ($page['status'] ?? ''),
    ];
}

echo "PAGES=" . count($found) . "\n";
foreach ($found as $slug => $meta) {
    echo sprintf("HAVE\t%s\t%s\t%s\n", $slug, $meta['status'], $meta['title']);
}

$missing = 0;
foreach ($expected as $slug => $label) {
    if (!isset($found[$slug])) {
        echo sprintf("MISSING\t%s\t%s\n", $slug, $label);
        $missing++;
    }
}
if ($missing === 0) {
    echo "MISSING\t(none)\n";
}

$tk = WebsitePageRepository::findBySlugAnyStatus('terminkalender');
if ($tk === null) {
    echo "CHECK\tterminkalender\tmissing\n";
} elseif (!WebsitePageRepository::isOnlineBookingPage($tk)) {
    echo "CHECK\tterminkalender\tneeds_booking_layout_repair\n";
} else {
    echo "CHECK\tterminkalender\tok_booking_layout\n";
}

exit($missing > 0 ? 1 : 0);
