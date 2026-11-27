<?php
declare(strict_types=1);

/**
 * KR6 — sources[] in API + Protokoll-Meta.
 * Usage: php bin/kichel-sources-selftest.php
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$failed = 0;
$adminRole = (string) App::config('roles.admin', 'administrator');
$user = new User(1, 'admin', 'Administrator', '', [$adminRole], true);

function kr6_assert(bool $ok, string $label, mixed $detail = null): void
{
    global $failed;
    if (!$ok) {
        $failed++;
    }
    $suffix = $detail !== null
        ? ' → ' . json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : '';
    echo ($ok ? 'OK  ' : 'FAIL') . '  ' . $label . $suffix . "\n";
}

$facts = [
    KichelRagFact::make(
        KichelRagFact::TYPE_TOPIC,
        'USt-ID',
        'Firmendaten.',
        'CRM-Hilfe',
        'topic:ust-id',
        '/app?page=einstellungen&tab=firmendaten'
    ),
];
$ui = KichelRagFact::sourcesForUi($facts);
kr6_assert(
    count($ui) === 1 && ($ui[0]['ref'] ?? '') === 'topic:ust-id' && ($ui[0]['label'] ?? '') === 'CRM-Hilfe',
    'sourcesForUi shape',
    $ui
);

$stripped = KichelRagFact::stripSourceLinesFromAnswer("Text\n\nQuelle: CRM-Hilfe (topic:ust-id)\n");
kr6_assert($stripped === 'Text' && !str_contains($stripped, 'Quelle:'), 'strip Quelle lines', $stripped);

$ans = KichelAssistant::answer($user, 'Wo trage ich die USt-ID ein?');
kr6_assert(
    !empty($ans['sources']) && is_array($ans['sources']),
    'assistant returns sources',
    $ans['sources'] ?? null
);
kr6_assert(
    !str_contains((string) ($ans['answer'] ?? ''), 'Quelle:'),
    'answer without embedded Quelle when sources present',
    $ans['answer'] ?? ''
);

$money = KichelAssistant::answer($user, 'Skonto 3 % von 1000 Euro');
kr6_assert(
    !empty($money['sources']) && ($money['rag_used'] ?? false) === true,
    'money has sources + rag_used',
    $money['sources'] ?? null
);

$json = json_encode($ans, JSON_UNESCAPED_UNICODE);
$meta = KichelProtocolRepository::metaFromResponseJson($json);
kr6_assert($meta['rag_used'] === true || $meta['source_refs'] !== [], 'protocol meta from json', $meta);
kr6_assert(
    in_array('topic:ust-id', $meta['source_refs'], true)
    || str_contains(implode(',', $meta['source_refs']), 'firmendaten')
    || $meta['sources'] !== [],
    'protocol lists source refs',
    $meta['source_refs']
);

$emptyMeta = KichelProtocolRepository::metaFromResponseJson(null);
kr6_assert($emptyMeta['rag_used'] === false && $emptyMeta['sources'] === [], 'empty json meta');

// JS assets mention renderSources
$widgetJs = (string) file_get_contents(DG_ROOT . '/assets/js/kichel.js');
$pageJs = (string) file_get_contents(DG_ROOT . '/assets/js/superkihel.js');
kr6_assert(str_contains($widgetJs, 'renderSources') && str_contains($pageJs, 'renderSources'), 'JS both UIs renderSources');

echo $failed === 0 ? "ALL OK\n" : "FAILED {$failed}\n";
exit($failed === 0 ? 0 : 1);
