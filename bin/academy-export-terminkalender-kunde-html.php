#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Exportiert Screenshots nur für die Endkunden-Ansicht (öffentliche Buchung + Bestätigungsmail).
 * Kein Admin, keine Einstellungen.
 *
 * php bin/academy-export-terminkalender-kunde-html.php [--base=https://ganz-soft.de]
 */

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}

require_once DG_ROOT . '/src/autoload.php';

MigrationRunner::runPending();

$baseUrl = 'https://ganz-soft.de';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $baseUrl = rtrim(substr($arg, 7), '/') . '/';
    }
}

if (!Database::isConfigured()) {
    fwrite(STDERR, "Keine DB-Konfiguration.\n");
    exit(1);
}

$outDir = DG_ROOT . '/storage/media/training/terminkalender';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

function exportStandaloneHtml(string $baseUrl, string $path, string $view, array $vars): void
{
    ob_start();
    View::render($view, $vars);
    $html = ob_get_clean();

    if (!str_contains($html, '<base ')) {
        $html = preg_replace(
            '/<head>/i',
            '<head>' . "\n" . '  <base href="' . htmlspecialchars($baseUrl, ENT_QUOTES) . '">',
            $html,
            1
        ) ?? $html;
    }

    file_put_contents($path, $html);
    echo "HTML: {$path}\n";
}

function exportEmailPreviewHtml(string $baseUrl, string $path): void
{
    $template = CalendarEmailTemplateSettings::resolvedTemplate(
        NotificationTemplateSettings::CALENDAR_OWNER_ID,
        NotificationTemplateSettings::SLUG_CONFIRMATION
    );
    $template['event_slug'] = NotificationTemplateSettings::SLUG_CONFIRMATION;
    $rendered = CalendarEmailTemplateRenderer::render(
        NotificationTemplateSettings::SLUG_CONFIRMATION,
        $template
    );

    $subject = htmlspecialchars((string) ($rendered['subject'] ?? ''), ENT_QUOTES, 'UTF-8');
    $body = (string) ($rendered['html'] ?? '');

    $html = '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
        . '<base href="' . htmlspecialchars($baseUrl, ENT_QUOTES) . '">'
        . '<title>E-Mail-Vorschau — Terminbestätigung</title>'
        . '<style>'
        . 'body{margin:0;background:#eef2f7;font-family:Arial,Helvetica,sans-serif;color:#1e293b;}'
        . '.dg-academy-email-shell{max-width:920px;margin:0 auto;padding:28px 20px 48px;}'
        . '.dg-academy-email-meta{background:#fff;border:1px solid #dbe3ef;border-radius:12px;padding:16px 20px;margin-bottom:16px;}'
        . '.dg-academy-email-meta strong{display:block;font-size:13px;color:#64748b;margin-bottom:6px;}'
        . '.dg-academy-email-meta span{font-size:18px;font-weight:600;}'
        . '#dg-academy-email-frame{background:#fff;border:1px solid #dbe3ef;border-radius:12px;padding:12px;}'
        . '</style></head><body>'
        . '<div class="dg-academy-email-shell">'
        . '<div class="dg-academy-email-meta"><strong>Betreff</strong><span>' . $subject . '</span></div>'
        . '<div id="dg-academy-email-frame">' . $body . '</div>'
        . '</div></body></html>';

    file_put_contents($path, $html);
    echo "HTML: {$path}\n";
}

$bookingArticles = CalendarArticleRepository::bookingOptions();
$bookingEmployees = CalendarStaffRepository::bookingEmployeeOptions();

$publicVars = [
    'embedConfig' => CalendarEmbedSettings::config(),
    'bookingArticles' => $bookingArticles,
    'bookingEmployees' => $bookingEmployees,
    'previewMode' => false,
    'onlineBookingEnabled' => true,
];

foreach ([1 => 'step1', 2 => 'step2', 3 => 'step3', 'done' => 'success'] as $step => $suffix) {
    exportStandaloneHtml(
        $baseUrl,
        $outDir . '/terminkalender-public-' . $suffix . '.html',
        'public/termin',
        array_merge($publicVars, ['academyDemoStep' => $step])
    );
}

exportEmailPreviewHtml($baseUrl, $outDir . '/terminkalender-email-confirmation.html');

$meta = [
    'exported_at' => date('c'),
    'base_url' => $baseUrl,
    'kind' => 'terminkalender-online-kunde',
];
file_put_contents($outDir . '/terminkalender-kunde-export.meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Meta: {$outDir}/terminkalender-kunde-export.meta.json\n";
