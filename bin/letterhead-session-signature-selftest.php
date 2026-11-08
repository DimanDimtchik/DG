<?php
declare(strict_types=1);

/**
 * ES4a (+ Guard ES4b): Brief/Druck nutzt nur activeForUser(session) — kein Kontakt-Picker, kein Canvas.
 *
 * Usage: php bin/letterhead-session-signature-selftest.php
 */

function assertEs4a(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$rendererPath = dirname(__DIR__) . '/src/Documents/LetterheadRenderer.php';
$storePath = dirname(__DIR__) . '/src/User/UserSignatureStore.php';
assertEs4a(is_file($rendererPath), 'LetterheadRenderer.php vorhanden');
assertEs4a(is_file($storePath), 'UserSignatureStore.php vorhanden');

$rendererSrc = (string) file_get_contents($rendererPath);
$storeSrc = (string) file_get_contents($storePath);

assertEs4a(
    str_contains($storeSrc, 'function activeImageSrcForUser'),
    'Store: activeImageSrcForUser definiert'
);
assertEs4a(
    str_contains($rendererSrc, 'UserSignatureStore::activeImageSrcForUser'),
    'Renderer: activeImageSrcForUser eingebunden'
);
assertEs4a(
    str_contains($rendererSrc, 'AuthService::user()'),
    'Renderer: Session-User via AuthService::user()'
);
assertEs4a(
    str_contains($rendererSrc, 'dg-lh-signature-image'),
    'Renderer: Signatur-Bild-CSS/Markup'
);
assertEs4a(
    !preg_match('/\$doc\[[\'"]signature_image_src[\'"]\]/', $rendererSrc),
    'Renderer: kein doc-Override signature_image_src (ES4b)'
);
assertEs4a(
    !preg_match('/<(select|option)[^>]*(signature|unterschrift)|name=["\']signature_(contact|user|media)/i', $rendererSrc),
    'Renderer: kein Select/Input für fremde Signaturen'
);
assertEs4a(
    !str_contains($rendererSrc, 'appendFromCanvas') && !str_contains($rendererSrc, 'signature_pad'),
    'Renderer: kein Canvas-Pad (ES3 bleibt getrennt)'
);

require dirname(__DIR__) . '/src/Media/MediaId.php';
require $storePath;

$ref = new ReflectionClass(UserSignatureStore::class);
assertEs4a($ref->hasMethod('activeImageSrcForUser'), 'Reflection: activeImageSrcForUser');
assertEs4a($ref->hasMethod('activeForUser'), 'Reflection: activeForUser');
assertEs4a(UserSignatureStore::activeImageSrcForUser(0) === '', 'ohne User-ID kein Bild');

echo "ES4a Selftest bestanden.\n";
exit(0);
