<?php
declare(strict_types=1);

/**
 * Zukunftssicherer UI-Guard (Klassenkatalog + Markup):
 * 1) docs/UI-KLASSENKATALOG.md + Kernklassen in dg.css
 * 2) Lohn-Views ohne Anti-Muster
 * 3) Keine neuen nackten Tabellen-Aktionslinks außerhalb der Legacy-Allowlist
 *
 * Usage: php bin/ui-form-spacing-selftest.php
 * Katalog: docs/UI-KLASSENKATALOG.md
 */
$root = dirname(__DIR__);

function assertUi(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

/**
 * Nackter Aktions-Textlink (ohne dg-button).
 * Erfasst auch <?= $x ? 'Bearbeiten' : 'Anzeigen' ?>.
 */
function uiBareActionLinkCount(string $html): int
{
    $n = 0;
    // Direkter Text
    $n += preg_match_all(
        '/<a\s+href="[^"]*"(?![^>]*\bdg-button\b)[^>]*>\s*(Bearbeiten|Anzeigen|Öffnen|Löschen|Deaktivieren|Ablösen|Korrigieren)\s*<\/a>/iu',
        $html
    );
    // PHP-Ternary-Label
    $n += preg_match_all(
        '/<a\s+href="[^"]*"(?![^>]*\bdg-button\b)[^>]*>\s*<\?[=].{0,120}\?(Bearbeiten|Anzeigen).{0,80}\?>\s*<\/a>/su',
        $html
    );
    return $n;
}

function uiHasNakedSubmitAfterTextarea(string $html): bool
{
    return (bool) preg_match('/<\/textarea>\s*<\/label>\s*<button\b[^>]*type="submit"/s', $html);
}

function uiHasMixedLinkAndButtonInActions(string $html): bool
{
    if (!preg_match_all('/<td\b[^>]*>([\s\S]*?)<\/td>/i', $html, $cells)) {
        return false;
    }
    foreach ($cells[1] as $cell) {
        $hasBare = (bool) preg_match(
            '/<a\s+href="[^"]*"(?![^>]*\bdg-button\b)[^>]*>\s*(?:Bearbeiten|Anzeigen|Öffnen|Löschen|Deaktivieren|Ablösen)\s*<\/a>/iu',
            $cell
        );
        $hasBtn = str_contains($cell, 'dg-button');
        if ($hasBare && $hasBtn) {
            return true;
        }
    }
    return false;
}

$css = (string) file_get_contents($root . '/assets/css/dg.css');
assertUi(str_contains($css, '--dg-space-4'), 'Spacing-Skala');
assertUi(str_contains($css, 'Aktions-Abstand-Algorithmus'), 'Aktions-Abstand-Algorithmus');
assertUi(str_contains($css, '.dg-form > .dg-form-grid + .dg-button'), 'Fallback Grid+Button');
assertUi(str_contains($css, '.dg-form > .dg-field + .dg-button'), 'Fallback Feld+Button');

$katalogPath = $root . '/docs/UI-KLASSENKATALOG.md';
assertUi(is_readable($katalogPath), 'UI-KLASSENKATALOG.md');
$katalog = (string) file_get_contents($katalogPath);
assertUi(str_contains($katalog, '## 5. Links'), 'Katalog: Links/a-Tag');
assertUi(str_contains($katalog, '## 6. Buttons'), 'Katalog: Buttons');
assertUi(str_contains($katalog, '## 8. Tabellen'), 'Katalog: Tabellen');
assertUi(str_contains($katalog, '## 3. Flächen'), 'Katalog: Panels');

$agents = (string) file_get_contents($root . '/AGENTS.md');
assertUi(str_contains($agents, 'UI-KLASSENKATALOG'), 'AGENTS.md verweist auf UI-KLASSENKATALOG');
assertUi(is_readable($root . '/docs/UI-FORM-REGELN.md'), 'UI-FORM-REGELN.md (Kurzverweis)');
$formRegeln = (string) file_get_contents($root . '/docs/UI-FORM-REGELN.md');
assertUi(str_contains($formRegeln, 'UI-KLASSENKATALOG.md'), 'Form-Regeln zeigen auf Katalog');

/** @var list<string> $coreClasses */
$coreClasses = [
    'dg-wrap',
    'dg-page-title',
    'dg-page-header',
    'dg-lead',
    'dg-panel',
    'dg-button',
    'dg-button--primary',
    'dg-button--small',
    'dg-button--ghost',
    'dg-button--danger',
    'dg-button--warning',
    'dg-button--back',
    'dg-form',
    'dg-form-grid',
    'dg-form-actions',
    'dg-field',
    'dg-btn-row',
    'dg-table',
    'dg-table-wrap',
    'dg-table__actions',
    'dg-table__actions-group',
    'dg-badge',
    'dg-flash',
    'dg-stack',
    'dg-sidebar__link',
    'dg-linklike',
];
foreach ($coreClasses as $cls) {
    assertUi(
        str_contains($css, '.' . $cls) || str_contains($css, '.' . str_replace('__', '__', $cls)),
        "CSS Kernklasse .{$cls}"
    );
}

// Lohn: streng — null Toleranz
foreach (glob($root . '/views/modules/lohn-*.php') ?: [] as $path) {
    $rel = 'views/modules/' . basename($path);
    $html = (string) file_get_contents($path);
    assertUi(!uiHasNakedSubmitAfterTextarea($html), "{$rel}: kein nackter Submit nach Textarea");
    assertUi(uiBareActionLinkCount($html) === 0, "{$rel}: keine nackten Aktionslinks (Count=0)");
    assertUi(!uiHasMixedLinkAndButtonInActions($html), "{$rel}: kein Link+Button-Mix in Aktionszelle");
    if (str_contains($html, 'dg-form') && (str_contains($html, 'Importieren') || str_contains($html, 'Anlegen') || str_contains($html, 'Speichern'))) {
        assertUi(str_contains($html, 'dg-form-actions'), "{$rel}: Formulare mit Submit nutzen dg-form-actions");
    }
}

/**
 * Legacy-Allowlist: bekannte Altlasten (Count je Datei).
 * Neue Dateien mit nackten Aktionslinks → FAIL.
 * Mehr Treffer als erlaubt in einer Allowlist-Datei → FAIL.
 * Weniger Treffer → Hinweis, Allowlist senken (kein FAIL).
 *
 * @var array<string, int>
 */
$legacyAllow = [
    'views/modules/akademie.php' => 2,
    'views/modules/gutscheine-flyer-vorlagen.php' => 1,
    'views/modules/gutscheine.php' => 1,
    'views/modules/hr-abteilungen.php' => 1,
    'views/modules/hr-teams.php' => 1,
    'views/modules/kontakte.php' => 2,
    'views/modules/terminkalender.php' => 2,
    'views/modules/website-formular-inbox.php' => 1,
    'views/modules/zeiterfassung-abwesenheit.php' => 1,
    'views/partials/voucher-document-chain.php' => 1,
    'views/settings/tab-legal-products.php' => 1,
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/views', FilesystemIterator::SKIP_DOTS)
);
$foundOutside = [];
$grown = [];
$shrinkHints = [];

foreach ($iterator as $fileInfo) {
    /** @var SplFileInfo $fileInfo */
    if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
        continue;
    }
    $full = $fileInfo->getPathname();
    $rel = str_replace('\\', '/', substr($full, strlen($root) + 1));
    if (str_starts_with(basename($rel), 'lohn-')) {
        continue; // schon streng geprüft
    }
    $html = (string) file_get_contents($full);
    $count = uiBareActionLinkCount($html);
    if ($count === 0) {
        continue;
    }
    if (!isset($legacyAllow[$rel])) {
        $foundOutside[] = "{$rel} ({$count})";
        continue;
    }
    $allowed = $legacyAllow[$rel];
    if ($count > $allowed) {
        $grown[] = "{$rel}: {$count} > allow {$allowed}";
    } elseif ($count < $allowed) {
        $shrinkHints[] = "{$rel}: jetzt {$count}, Allowlist noch {$allowed} — Allowlist senken";
    }
}

assertUi($foundOutside === [], 'Keine neuen Dateien mit nackten Aktionslinks' . ($foundOutside ? ': ' . implode(', ', $foundOutside) : ''));
assertUi($grown === [], 'Allowlist-Counts nicht gewachsen' . ($grown ? ': ' . implode('; ', $grown) : ''));

foreach ($shrinkHints as $hint) {
    echo "HINWEIS: {$hint}\n";
}

// Mix-Pattern global verbieten (auch Legacy): Link + Button in derselben td
$mixHits = [];
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
        continue;
    }
    $full = $fileInfo->getPathname();
    $rel = str_replace('\\', '/', substr($full, strlen($root) + 1));
    $html = (string) file_get_contents($full);
    if (uiHasMixedLinkAndButtonInActions($html)) {
        $mixHits[] = $rel;
    }
}
assertUi($mixHits === [], 'Kein Link+Button-Mix in Aktionszellen' . ($mixHits ? ': ' . implode(', ', $mixHits) : ''));

echo "UI-Form-Spacing Selftest bestanden.\n";
