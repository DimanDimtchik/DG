<?php
declare(strict_types=1);

/**
 * ES5a: Post — optionales CID-<img> aus aktiver User-Signatur.
 *
 * Usage: php bin/mail-signature-image-selftest.php
 */

function assertEs5a(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
require $root . '/src/Mail/MailMessage.php';
require $root . '/src/Mail/MailSignatureResolver.php';

$ref = new ReflectionClass(MailSignatureResolver::class);
assertEs5a($ref->hasMethod('resolveInlineImage'), 'resolveInlineImage vorhanden');
assertEs5a(MailSignatureResolver::resolveInlineImage(null) === null, 'ohne Actor kein Inline-Bild');

$png = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    true
);
assertEs5a(is_string($png) && $png !== '', 'PNG Fixture');

$msg = new MailMessage(
    subject: 'ES5a',
    htmlBody: '<p>Hallo</p><img src="cid:dg-user-sig-1@crm.local" alt="">',
    to: ['test@example.com'],
    textBody: "Hallo\n",
    attachments: [
        [
            'filename' => 'unterschrift.png',
            'content' => $png,
            'mime' => 'image/png',
            'disposition' => 'inline',
            'cid' => 'dg-user-sig-1@crm.local',
        ],
    ],
);
$mime = $msg->toMime('from@example.com', 'Absender', null, '1.es5a@example.com');
assertEs5a(str_contains($mime, 'multipart/related'), 'MIME multipart/related für CID');
assertEs5a(str_contains($mime, 'Content-ID: <dg-user-sig-1@crm.local>'), 'MIME Content-ID gesetzt');
assertEs5a(str_contains($mime, 'Content-Disposition: inline'), 'MIME inline Disposition');
assertEs5a(!str_contains($mime, 'Content-Disposition: attachment'), 'kein attachment für reines CID-Bild');

$msgMixed = new MailMessage(
    subject: 'ES5a-mix',
    htmlBody: '<p>x</p>',
    to: ['test@example.com'],
    attachments: [
        [
            'filename' => 'unterschrift.png',
            'content' => $png,
            'mime' => 'image/png',
            'disposition' => 'inline',
            'cid' => 'dg-user-sig-2@crm.local',
        ],
        [
            'filename' => 'datei.pdf',
            'content' => '%PDF-1.4',
            'mime' => 'application/pdf',
        ],
    ],
);
$mimeMix = $msgMixed->toMime('from@example.com', 'Absender', null, '2.es5a@example.com');
assertEs5a(str_contains($mimeMix, 'multipart/mixed'), 'MIME mixed bei Dateianhang');
assertEs5a(str_contains($mimeMix, 'multipart/related'), 'MIME related innerhalb von mixed');
assertEs5a(str_contains($mimeMix, 'Content-Disposition: attachment'), 'Dateianhang bleibt attachment');

// Layout: signatur_image_html nur als <img>
final class AppearanceSettings
{
    public static function emailFontSizePx(): int
    {
        return 15;
    }
}

final class EmailLayoutSettings
{
    /** @return array<string, string> */
    public static function emailTheme(): array
    {
        return ['text' => '#222222', 'surface' => '#ffffff'];
    }

    /** @param array<string, mixed> $context */
    public static function resolvedFooter(array $context = []): array
    {
        return [
            'thanks_line' => '',
            'salutation' => '',
            'signature' => '',
            'signature_text_color' => '#222222',
            'surface_color' => '#ffffff',
            'opening_greeting' => '',
        ];
    }
}

require $root . '/src/Mail/CalendarEmailLayout.php';

$block = CalendarEmailLayout::renderClosingSignatureBlock([
    'thanks_line' => 'Vielen Dank',
    'salutation' => 'Mit freundlichen Grüßen',
    'signature' => 'Max Mustermann',
    'signature_image_html' => '<img src="cid:dg-user-sig-1@crm.local" alt="">',
    'signature_text_color' => '#111111',
]);
assertEs5a(str_contains($block, 'cid:dg-user-sig-1@crm.local'), 'Closing enthält CID-img');
assertEs5a(str_contains($block, 'Max Mustermann'), 'Closing behält Text-Signatur');

$blocked = CalendarEmailLayout::renderClosingSignatureBlock([
    'thanks_line' => '',
    'salutation' => '',
    'signature' => 'Nur Text',
    'signature_image_html' => '<script>alert(1)</script>',
    'signature_text_color' => '#111111',
]);
assertEs5a(!str_contains($blocked, '<script'), 'Nicht-img signature_image_html verworfen');
assertEs5a(str_contains($blocked, 'Nur Text'), 'Text-Signatur bleibt ohne Bild');

$svcSrc = (string) file_get_contents($root . '/src/Mail/MailService.php');
assertEs5a(
    str_contains($svcSrc, 'MailSignatureResolver::resolveInlineImage'),
    'MailService bindet resolveInlineImage'
);
assertEs5a(
    str_contains($svcSrc, "'disposition' => 'inline'"),
    'MailService hängt CID-Part an'
);

echo "ES5a Selftest bestanden.\n";
exit(0);
