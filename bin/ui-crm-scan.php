<?php
declare(strict_types=1);

/**
 * CRM-weiter UI-Anti-Muster-Scan (Reporting).
 * Strikte Gates: php bin/ui-form-spacing-selftest.php
 *
 * Usage:
 *   php bin/ui-crm-scan.php
 *   php bin/ui-crm-scan.php --out=/tmp/ui-crm-scan-report.md
 */
$root = dirname(__DIR__);
$out = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--out=')) {
        $out = substr($arg, 6);
    }
}

/**
 * @return array<string, list<string>>
 */
function uiCrmScan(string $root): array
{
    $results = [
        'naked_submit_after_textarea' => [],
        'bare_action_links' => [],
        'fieldset_not_form_section' => [],
        'field_stack_without_section' => [],
        'inline_flex_dg_form' => [],
        'checkbox_without_field_checkbox' => [],
        'plan_jargon_in_views' => [],
    ];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/views', FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        /** @var SplFileInfo $fileInfo */
        if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
            continue;
        }
        $full = $fileInfo->getPathname();
        $rel = str_replace('\\', '/', substr($full, strlen($root) + 1));
        $html = (string) file_get_contents($full);

        if (preg_match('/<\/textarea>\s*<\/label>\s*<button\b[^>]*type="submit"/s', $html)) {
            $results['naked_submit_after_textarea'][] = $rel;
        }

        $bare = 0;
        $bare += preg_match_all(
            '/<a\s+href="[^"]*"(?![^>]*\bdg-button\b)[^>]*>\s*(Bearbeiten|Anzeigen|Öffnen|Löschen|Deaktivieren|Ablösen|Korrigieren)\s*<\/a>/iu',
            $html
        );
        $bare += preg_match_all(
            '/<a\s+href="[^"]*"(?![^>]*\bdg-button\b)[^>]*>\s*<\?[=].{0,120}\?(Bearbeiten|Anzeigen).{0,80}\?>\s*<\/a>/su',
            $html
        );
        if ($bare > 0) {
            $results['bare_action_links'][] = "{$rel} ({$bare})";
        }

        if (preg_match('/<fieldset(?![^>]*dg-form-section)(?![^>]*dg-fieldset)[^>]*>/i', $html)) {
            $results['fieldset_not_form_section'][] = $rel;
        }

        if (preg_match_all('/<fieldset([^>]*)>([\s\S]*?)<\/fieldset>/i', $html, $blocks, PREG_SET_ORDER)) {
            foreach ($blocks as $block) {
                $attrs = $block[1];
                $body = $block[2];
                if (str_contains($attrs, 'dg-form-section') || str_contains($attrs, 'dg-fieldset')) {
                    continue;
                }
                $nField = preg_match_all('/\bdg-field\b/', $body);
                $nCb = preg_match_all('/type=["\']checkbox["\']/i', $body);
                if ($nField >= 2 || $nCb >= 2) {
                    $results['field_stack_without_section'][] = $rel;
                    break;
                }
            }
        }

        if (preg_match('/<form\b[^>]*\bdg-form\b[^>]*style="[^"]*display\s*:\s*flex/i', $html)) {
            $results['inline_flex_dg_form'][] = $rel;
        }

        if (preg_match_all('/<input\b[^>]*type=["\']checkbox["\'][^>]*>/i', $html, $cbs, PREG_OFFSET_CAPTURE)) {
            foreach ($cbs[0] as $cb) {
                $pos = (int) $cb[1];
                $window = substr($html, max(0, $pos - 250), 250);
                $ok = str_contains($window, 'dg-field--checkbox')
                    || str_contains($window, 'dg-checkbox')
                    || str_contains($window, 'dg-label--checkbox')
                    || str_contains($window, 'dg-working-hours-weekday')
                    || str_contains($window, 'dg-contact-import__check')
                    || str_contains($window, 'dg-voucher-extract__contact-sync');
                if (!$ok) {
                    $results['checkbox_without_field_checkbox'][] = $rel;
                    break;
                }
            }
        }

        $lines = preg_split("/\r\n|\n|\r/", $html) ?: [];
        foreach ($lines as $line) {
            $trim = ltrim($line);
            if ($trim === '' || str_starts_with($trim, '//') || str_starts_with($trim, '*') || str_starts_with($trim, '/*')) {
                continue;
            }
            if (preg_match('/Stufe\s+[A-D0-9]+\b|Gate\s+G?\d+|\((?:L\d{1,2}[a-c]?|M\d[a-z]?)(?:\s*\/[^)]*)?\)|Live-Flag|nur Flag\b|payroll_[a-z0-9_]+\s*[—–-]/u', $line)) {
                if (preg_match('/name=["\'][^"\']*payroll_[^"\']*["\']/', $line) && !preg_match('/payroll_[a-z0-9_]+\s*[—–-]|Stufe|Gate|Live-Flag|\(L|\(M|nur Flag/u', $line)) {
                    continue;
                }
                $results['plan_jargon_in_views'][] = $rel;
                break;
            }
        }
    }

    return $results;
}

$results = uiCrmScan($root);
$lines = ["# CRM UI Scan " . date('Y-m-d'), ''];
foreach ($results as $bucket => $items) {
    $lines[] = "## {$bucket} (" . count($items) . ')';
    if ($items === []) {
        $lines[] = '- (keine Treffer)';
    } else {
        foreach ($items as $item) {
            $lines[] = '- ' . $item;
        }
    }
    $lines[] = '';
}
$report = implode("\n", $lines);
echo $report;

if ($out !== null && $out !== '') {
    $dir = dirname($out);
    if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($out, $report);
    fwrite(STDERR, "Wrote {$out}\n");
}

$blocking = array_merge(
    $results['naked_submit_after_textarea'],
    $results['bare_action_links'],
    $results['plan_jargon_in_views']
);
exit($blocking === [] ? 0 : 2);
