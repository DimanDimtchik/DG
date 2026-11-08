<?php
declare(strict_types=1);

/**
 * ES4b: Kein Kontakt-Picker fremder Signaturen; doc-Override greift nicht.
 *
 * Usage: php bin/letterhead-no-foreign-signature-selftest.php
 */

function assertEs4b(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$rendererPath = $root . '/src/Documents/LetterheadRenderer.php';
$formPath = $root . '/views/modules/dokumentenverfasser-form.php';
assertEs4b(is_file($rendererPath), 'LetterheadRenderer.php vorhanden');
assertEs4b(is_file($formPath), 'dokumentenverfasser-form.php vorhanden');

$rendererSrc = (string) file_get_contents($rendererPath);
$formSrc = (string) file_get_contents($formPath);

assertEs4b(
    !preg_match('/\$doc\[[\'"]signature_image_src[\'"]\]/', $rendererSrc),
    'Renderer liest signature_image_src nicht aus $doc (kein Override)'
);
assertEs4b(
    str_contains($rendererSrc, 'AuthService::user()')
    && str_contains($rendererSrc, 'UserSignatureStore::activeImageSrcForUser'),
    'Renderer bezieht Bild nur aus Session/activeImageSrcForUser'
);
assertEs4b(
    !preg_match('/name=["\']signature_image|signature_media|signature_contact|signature_user_id/i', $formSrc),
    'Dokumentenverfasser: kein Hidden/Select für fremde Signatur-ID'
);
assertEs4b(
    !preg_match('/Unterschrift.*(wählen|auswählen|Picker)|fremde[rn]? Signatur.*(wählen|auswählen)/iu', $formSrc),
    'Dokumentenverfasser: kein UI-Picker-Text für fremde Signatur'
);
assertEs4b(
    str_contains($formSrc, 'keine Auswahl fremder Signaturen'),
    'Dokumentenverfasser: Hinweis keine fremde Auswahl'
);

final class Database
{
    public static function isConfigured(): bool
    {
        return false;
    }
}

final class AuthService
{
    public static ?object $u = null;

    public static function user(): ?object
    {
        return self::$u;
    }
}

final class UserSignatureStore
{
    /** @var list<array{userId:int,preferDataUri:bool}> */
    public static array $calls = [];

    public static function activeImageSrcForUser(int $userId, bool $preferDataUri = false): string
    {
        self::$calls[] = ['userId' => $userId, 'preferDataUri' => $preferDataUri];
        if ($userId !== 7) {
            return '';
        }

        return '/app/media?id=20261108120007_session';
    }
}

require $rendererPath;

$m = (new ReflectionClass(LetterheadRenderer::class))->getMethod('renderBody');
$m->setAccessible(true);

$docForeign = [
    'closing' => 'Mit freundlichen Grüßen',
    'signature' => 'Fremder Name',
    'signature_image_src' => '/app/media?id=20261108120099_foreign',
];

AuthService::$u = null;
UserSignatureStore::$calls = [];
$htmlAnon = $m->invoke(null, [], $docForeign, false);
assertEs4b(!str_contains($htmlAnon, '20261108120099_foreign'), 'ohne Session: fremdes Override-Bild ignoriert');
assertEs4b(!str_contains($htmlAnon, 'dg-lh-signature-image'), 'ohne Session: kein Signatur-Bild');
assertEs4b(UserSignatureStore::$calls === [], 'ohne Session: Store nicht aufgerufen');

AuthService::$u = (object) ['id' => 7];
UserSignatureStore::$calls = [];
$htmlSession = $m->invoke(null, [], $docForeign, false);
assertEs4b(!str_contains($htmlSession, '20261108120099_foreign'), 'mit Session: fremdes Override weiterhin ignoriert');
assertEs4b(str_contains($htmlSession, '20261108120007_session'), 'mit Session: nur eigene activeImageSrc');
assertEs4b(
    count(UserSignatureStore::$calls) === 1 && UserSignatureStore::$calls[0]['userId'] === 7,
    'Store nur mit Session-User-ID aufgerufen'
);

AuthService::$u = (object) ['id' => 99];
UserSignatureStore::$calls = [];
$htmlOther = $m->invoke(null, [], $docForeign, false);
assertEs4b(!str_contains($htmlOther, 'dg-lh-signature-image'), 'andere User-ID ohne aktive Zeile: kein Bild');
assertEs4b(
    count(UserSignatureStore::$calls) === 1 && UserSignatureStore::$calls[0]['userId'] === 99,
    'Store mit der Session-User-ID (99), nicht aus doc'
);

echo "ES4b Selftest bestanden.\n";
exit(0);
