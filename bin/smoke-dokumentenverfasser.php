<?php

declare(strict_types=1);

/**
 * Dokumentenverfasser D7 — lokaler Smoke (ohne Live-HTTP).
 *
 * Aufruf: php bin/smoke-dokumentenverfasser.php
 * Exit 0 = OK, sonst Fehlercode.
 */

$root = dirname(__DIR__);
if (!defined('DG_ROOT')) {
    define('DG_ROOT', $root);
}

require $root . '/src/autoload.php';

$errors = [];
$ok = static function (string $msg): void {
    echo '[OK] ' . $msg . PHP_EOL;
};
$fail = static function (string $msg) use (&$errors): void {
    $errors[] = $msg;
    echo '[FAIL] ' . $msg . PHP_EOL;
};

// 1) Kernklassen
foreach ([
    'LetterheadSettings',
    'LetterheadRenderer',
    'WriterDocument',
    'WriterDocumentRepository',
    'WriterDocumentLinkService',
    'WriterExportService',
    'WriterAssistSettings',
    'WriterKlartextAssist',
    'WriterLanguageToolClient',
    'WriterAssistApi',
] as $class) {
    if (!class_exists($class)) {
        $fail('Klasse fehlt: ' . $class);
    } else {
        $ok('Klasse ' . $class);
    }
}

// 2) Modulrecht
if (!isset(DepartmentAccess::MODULE_LABELS['dokumentenverfasser'])) {
    $fail('DepartmentAccess: Modul dokumentenverfasser fehlt');
} else {
    $ok('Modul dokumentenverfasser in DepartmentAccess');
}
$defaults = DepartmentAccess::defaultModules();
if (($defaults['dokumentenverfasser'] ?? 'none') === 'none') {
    $fail('defaultModules: dokumentenverfasser sollte nicht none sein');
} else {
    $ok('defaultModules dokumentenverfasser=' . $defaults['dokumentenverfasser']);
}

// 3) Migration 112 vorhanden
$mig = $root . '/database/migrations/112_writer_documents.sql';
if (!is_file($mig)) {
    $fail('Migration 112_writer_documents.sql fehlt');
} else {
    $ok('Migration 112 vorhanden');
}

// 4) Assets TipTap / Assist
foreach ([
    '/assets/js/vendor/tiptap-writer.mjs',
    '/assets/js/writer-editor.js',
    '/assets/js/writer-assist.js',
    '/assets/js/writer-contact-picker.js',
    '/bin/build-tiptap-writer.mjs',
    '/bin/tiptap-writer-entry.mjs',
    '/views/modules/dokumentenverfasser.php',
    '/views/modules/dokumentenverfasser-form.php',
] as $rel) {
    if (!is_file($root . $rel)) {
        $fail('Datei fehlt: ' . $rel);
    } else {
        $ok('Datei ' . $rel);
    }
}

$vendor = (string) file_get_contents($root . '/assets/js/vendor/tiptap-writer.mjs');
foreach (['Underline', 'TextStyle', 'Color', 'FontFamily', 'FontSize', 'TextAlign', 'Link', 'Highlight'] as $exp) {
    if (!str_contains($vendor, 'as ' . $exp)) {
        $fail('Vendor-Export fehlt: ' . $exp);
    } else {
        $ok('Vendor enthält ' . $exp);
    }
}

// 5) Dokument-Modell + Export (ohne DB)
$doc = WriterDocument::empty();
$doc['subject'] = 'Smoke-Brief';
$doc['recipient_lines'] = ['Smoke GmbH', '12345 Teststadt'];
$doc['body'] = [
    'format' => WriterDocument::BODY_FORMAT_BLOCKS,
    'blocks' => [
        WriterDocument::paragraphBlock('Erster Absatz.'),
        WriterDocument::paragraphBlock('Zweiter Absatz.'),
    ],
    'tiptap' => null,
];
$doc['greeting'] = 'Sehr geehrte Damen und Herren,';
$doc['closing'] = 'Mit freundlichen Grüßen';
$clean = WriterDocument::sanitize($doc);
$render = WriterDocument::toRenderDocument($clean);
if (($render['subject'] ?? '') !== 'Smoke-Brief') {
    $fail('toRenderDocument subject');
} else {
    $ok('toRenderDocument subject');
}
if (count($render['paragraphs'] ?? []) < 2) {
    $fail('toRenderDocument paragraphs');
} else {
    $ok('toRenderDocument paragraphs=' . count($render['paragraphs']));
}

$neutral = 'Sehr geehrte Damen und Herren,';
$herr = new Contact(
    1, '', 'Herr', 'Dietrich', 'Ganz', 'Dietrich Ganz', '',
    '', '', '', '', '', '', '', '', '',
    '', '', '', '', '', '', '', '', '', '',
    '', '', [], [], [], [], []
);
$frau = new Contact(
    2, '', 'Frau', 'Anna', 'Meier', 'Anna Meier', '',
    '', '', '', '', '', '', '', '', '',
    '', '', '', '', '', '', '', '', '', '',
    '', '', [], [], [], [], []
);
$greetingCases = [
    ['Sehr geehrter Herr …,', $herr, 'Sehr geehrter Herr Ganz,'],
    ['Sehr geehrte Frau ...,', $frau, 'Sehr geehrte Frau Meier,'],
    ['Liebe …,', $frau, 'Liebe Anna,'],
    ['Lieber …,', $herr, 'Lieber Dietrich,'],
    ['Sehr geehrter Herr …', null, $neutral],
    ['', null, $neutral],
    ['Sehr geehrte/r ', null, $neutral],
    ['Guten Tag,', null, 'Guten Tag,'],
    ['Guten Tag,', $herr, 'Guten Tag,'],
];
foreach ($greetingCases as [$input, $contact, $expected]) {
    $got = WriterDocument::resolveGreeting($input, $contact);
    if ($got !== $expected) {
        $fail('resolveGreeting «' . $input . '» => «' . $got . '» erwartet «' . $expected . '»');
    }
}
$ok('resolveGreeting');

$txt = WriterExportService::toPlainText($clean);
$md = WriterExportService::toMarkdown($clean);
if (!str_contains($txt, 'Erster Absatz') || !str_contains($md, '# Smoke-Brief')) {
    $fail('Export TXT/MD Inhalt');
} else {
    $ok('Export TXT/MD');
}
try {
    $docx = WriterExportService::toDocxBinary($clean);
    if (strlen($docx) < 50) {
        $fail('DOCX zu klein');
    } else {
        $ok('Export DOCX bytes=' . strlen($docx));
    }
} catch (Throwable $e) {
    $fail('DOCX: ' . $e->getMessage());
}

$html = LetterheadRenderer::renderFullHtml($render, LetterheadSettings::defaults(), [
    'show_guides' => false,
    'title' => 'Smoke',
]);
if (!str_contains($html, 'Smoke-Brief') || !str_contains($html, 'dg-lh-page')) {
    $fail('LetterheadRenderer HTML');
} elseif (str_contains($html, 'class="dg-lh-window"') || !str_contains($html, 'class="dg-lh-meta"')) {
    $fail('LetterheadRenderer: Adresse muss in der Meta-Tabelle liegen');
} else {
    $metaPos = strpos($html, 'class="dg-lh-meta"');
    $bodyPos = strpos($html, 'class="dg-lh-body-content"');
    if ($metaPos === false || $bodyPos === false || $metaPos > $bodyPos) {
        $fail('LetterheadRenderer: Text steht nicht unter der Adresszeile');
    } elseif (!str_contains($html, 'dg-lh-stack') || !str_contains($html, 'contentLimit')) {
        $fail('LetterheadRenderer: Vorschau-Stack/Pagination fehlt');
    } else {
        $ok('LetterheadRenderer HTML');
    }
}

$pdfHtml = LetterheadRenderer::renderFullHtml($render, LetterheadSettings::defaults(), [
    'show_guides' => false,
    'title' => 'Smoke PDF',
    'for_pdf' => true,
]);
if (!preg_match('/@page\s*\{[^}]*margin:\s*[0-9.]+mm/s', $pdfHtml)) {
    $fail('LetterheadRenderer PDF: @page-Ränder fehlen');
} elseif (str_contains($pdfHtml, 'class="dg-lh-fold-marks"')) {
    $fail('LetterheadRenderer PDF: Falzmarken gehören in page_script, nicht ins HTML');
} else {
    $ok('LetterheadRenderer PDF @page');
}

$klar = WriterKlartextAssist::analyze(
    'Hiermit möchten wir Ihnen im Rahmen der Bearbeitung mitteilen, dass die Angelegenheit unter Berücksichtigung aller Umstände erledigt wird.'
);
if (!isset($klar['score'], $klar['tips'])) {
    $fail('Klartext-Assist Struktur');
} else {
    $ok('Klartext-Assist score=' . $klar['score']);
}

$formats = WriterExportService::enabledFormats([
    'export_formats' => ['pdf' => true, 'txt' => true, 'md' => false, 'docx' => false],
]);
if (empty($formats['pdf']) || empty($formats['txt']) || !empty($formats['md']) || !empty($formats['docx'])) {
    $fail('enabledFormats Filter');
} else {
    $ok('enabledFormats respektiert Briefpapier-Schalter');
}

// 6) Autoload-Pfade für Writer*
$mapFile = (string) file_get_contents($root . '/src/autoload.php');
foreach (['WriterDocument', 'WriterExportService', 'WriterDocumentLinkService', 'LetterheadRenderer'] as $name) {
    if (!str_contains($mapFile, "'" . $name . "'")) {
        $fail('autoload ohne ' . $name);
    }
}
$ok('autoload Einträge geprüft');

echo PHP_EOL;
if ($errors !== []) {
    echo 'SMOKE_FAIL count=' . count($errors) . PHP_EOL;
    exit(1);
}

echo 'SMOKE_OK dokumentenverfasser' . PHP_EOL;
exit(0);
