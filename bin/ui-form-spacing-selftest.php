<?php
declare(strict_types=1);

/**
 * Prüft: Spacing-Algorithmus in dg.css + Lohn-Views nutzen dg-form-actions.
 */
$root = dirname(__DIR__);
$css = (string) file_get_contents($root . '/assets/css/dg.css');

function assertUi(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

assertUi(str_contains($css, '--dg-space-4'), 'Spacing-Skala');
assertUi(str_contains($css, '.dg-button--small'), 'Button small');
assertUi(str_contains($css, '.dg-button--primary'), 'Button primary');
assertUi(str_contains($css, 'Aktions-Abstand-Algorithmus'), 'Aktions-Abstand-Algorithmus Kommentar');
assertUi(str_contains($css, '.dg-form > .dg-form-grid + .dg-button'), 'Fallback Grid+Button');
assertUi(str_contains($css, '.dg-form > .dg-field + .dg-button'), 'Fallback Feld+Button');
assertUi(str_contains($css, '.dg-form-actions'), 'dg-form-actions');

foreach (['lohn-lohnarten.php', 'lohn-beitragstabellen.php'] as $f) {
    $html = (string) file_get_contents($root . '/views/modules/' . $f);
    assertUi(str_contains($html, 'dg-form-actions'), "{$f} hat dg-form-actions");
    assertUi(
        !preg_match('/<\/textarea>\s*<\/label>\s*<button type="submit"/s', $html),
        "{$f}: kein nackter Submit direkt nach Textarea"
    );
}

$lohnarten = (string) file_get_contents($root . '/views/modules/lohn-lohnarten.php');
assertUi(
    str_contains($lohnarten, 'dg-button dg-button--small') && str_contains($lohnarten, '>Bearbeiten</a>'),
    'lohn-lohnarten: Bearbeiten als Small-Button'
);
assertUi(str_contains($lohnarten, 'dg-table__actions-group'), 'lohn-lohnarten: actions-group');
assertUi(
    !preg_match('/<a href="\/app\?page=lohn-lohnarten[^"]*">Bearbeiten<\/a>/', $lohnarten),
    'lohn-lohnarten: kein nackter Bearbeiten-Link'
);

assertUi(is_readable($root . '/docs/UI-FORM-REGELN.md'), 'UI-FORM-REGELN.md');
echo "UI-Form-Spacing Selftest bestanden.\n";
