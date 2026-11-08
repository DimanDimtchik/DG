<?php
declare(strict_types=1);

/**
 * ES5b: Fallback Text-Signatur wenn keine aktive Bildzeile.
 *
 * Usage: php bin/mail-signature-text-fallback-selftest.php
 */

function assertEs5b(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);

final class EmailLayoutSettings
{
    /** @var array<string, string> */
    public static array $footer = [
        'signature' => '',
        'thanks_line' => 'Vielen Dank',
        'salutation' => 'Mit freundlichen Grüßen',
    ];

    /** @return array<string, mixed> */
    public static function resolvedFooter(array $context = []): array
    {
        return self::$footer;
    }

    /** @return array<string, string> */
    public static function emailTheme(): array
    {
        return ['text' => '#222', 'surface' => '#fff'];
    }
}

final class CompanySettings
{
    public static string $name = 'Beispiel Firma GmbH';

    public static function displayName(): string
    {
        return self::$name;
    }
}

final class AppearanceSettings
{
    public static function emailFontSizePx(): int
    {
        return 15;
    }
}

require $root . '/src/User/User.php';
require $root . '/src/Mail/MailSignatureResolver.php';
require $root . '/src/Mail/CalendarEmailLayout.php';

$ref = new ReflectionClass(MailSignatureResolver::class);
assertEs5b($ref->hasMethod('resolveTextFallback'), 'resolveTextFallback vorhanden');

EmailLayoutSettings::$footer['signature'] = 'Team Signatur';
assertEs5b(
    MailSignatureResolver::resolveTextFallback(null, null) === 'Team Signatur',
    'generic/settings Text wenn gesetzt'
);

EmailLayoutSettings::$footer['signature'] = '';
$actor = new User(3, 'anna', 'Anna Beispiel', 'anna@example.com', ['admin'], true);
assertEs5b(
    MailSignatureResolver::resolveTextFallback(null, $actor) === 'Anna Beispiel',
    'ohne Settings-Signatur → Actor-Anzeigename'
);

$actorNoDisplay = new User(4, 'bob', '', 'bob@example.com', ['user'], true);
assertEs5b(
    MailSignatureResolver::resolveTextFallback(null, $actorNoDisplay) === 'bob',
    'ohne Anzeigename → Username'
);

$actorEmpty = new User(5, '', '', '', ['user'], true);
CompanySettings::$name = 'Firma Fallback AG';
assertEs5b(
    MailSignatureResolver::resolveTextFallback(null, $actorEmpty) === 'Firma Fallback AG',
    'ohne Actor-Namen → Firmenname'
);

// Closing ohne Bild: Text sichtbar
$block = CalendarEmailLayout::renderClosingSignatureBlock([
    'thanks_line' => 'Vielen Dank',
    'salutation' => 'Mit freundlichen Grüßen',
    'signature' => 'Anna Beispiel',
    'signature_image_html' => '',
    'signature_text_color' => '#111',
]);
assertEs5b(!str_contains($block, '<img'), 'ohne Bildzeile kein img');
assertEs5b(str_contains($block, 'Anna Beispiel'), 'Text-Signatur im Closing ohne Bild');

$svcSrc = (string) file_get_contents($root . '/src/Mail/MailService.php');
assertEs5b(
    substr_count($svcSrc, 'MailSignatureResolver::resolveTextFallback') >= 2,
    'MailService nutzt resolveTextFallback (HTML + Plaintext)'
);
assertEs5b(
    !preg_match(
        '/prepareHtmlBody.*?if \(\$mailbox !== null\) \{\s*\$footer\[.signature.\] = MailSignatureResolver::resolve/s',
        $svcSrc
    ),
    'Text-Fallback nicht mehr nur bei gesetztem mailbox'
);

echo "ES5b Selftest bestanden.\n";
exit(0);
