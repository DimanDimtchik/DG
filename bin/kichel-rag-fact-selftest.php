<?php
declare(strict_types=1);

/**
 * KR2 — Schema / Fallback / Money-Adapter (ohne Ollama-Netzwerk).
 * Usage: php bin/kichel-rag-fact-selftest.php
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$failed = 0;

function kr2_assert(bool $ok, string $label, mixed $detail = null) : void
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

// KR2a — normalize / make
$fact = KichelRagFact::make(
    KichelRagFact::TYPE_TOPIC,
    'USt-ID',
    'Unter Einstellungen → Firmendaten.',
    'CRM-Hilfe',
    'topic:ust-id',
    '/app?page=einstellungen&tab=firmendaten',
    0.9
);
kr2_assert(
    ($fact['type'] ?? '') === 'topic'
    && ($fact['source_ref'] ?? '') === 'topic:ust-id'
    && ($fact['href'] ?? null) === '/app?page=einstellungen&tab=firmendaten'
    && abs((float) ($fact['confidence'] ?? 0) - 0.9) < 0.001,
    'make/normalize schema keys',
    $fact
);

kr2_assert(KichelRagFact::normalize([]) === null, 'normalize rejects empty');
kr2_assert(KichelRagFact::normalize(['title' => 'x']) !== null, 'normalize accepts title-only');

$list = KichelRagFact::normalizeList([
    $fact,
    ['body' => ''], // reject
    'x',
]);
kr2_assert(count($list) === 1, 'normalizeList filters junk', count($list));

// KR2c — Fallback ohne Ollama
$fallbackOne = KichelRagFact::fallbackAnswer([$fact]);
kr2_assert(
    str_contains($fallbackOne, 'Firmendaten') && str_contains($fallbackOne, 'Quelle:'),
    'fallback single includes body + source',
    $fallbackOne
);

$fallbackBase = KichelRagFact::fallbackAnswer([$fact], "Basiszeile mit 12,00 €.");
kr2_assert(
    str_starts_with($fallbackBase, 'Basiszeile') && str_contains($fallbackBase, 'Quelle:'),
    'fallback keeps baseAnswer + source',
    $fallbackBase
);

$multi = KichelRagFact::fallbackAnswer([
    $fact,
    KichelRagFact::make(
        KichelRagFact::TYPE_NAVIGATION,
        'Belege',
        'Unter Buchhaltung → Belege.',
        'CRM-Menü',
        'nav:belege',
        '/app?page=buchhaltung-belege'
    ),
]);
kr2_assert(
    str_contains($multi, '1. ') && str_contains($multi, '2. ') && str_contains($multi, 'Quelle:'),
    'fallback multi numbered + sources',
    $multi
);

// Money-Adapter + compose ohne Ollama (= Fallback)
$money = KichelMoneyLogic::tryAnswer('Skonto 3 % von 1000 Euro');
kr2_assert($money !== null, 'money logic sample');
if ($money !== null) {
    $rag = KichelRagFact::fromMoneyCalculation($money['facts'], $money['answer']);
    kr2_assert(
        count($rag) === 1
        && ($rag[0]['type'] ?? '') === KichelRagFact::TYPE_CALCULATION
        && isset($rag[0]['numbers']['payable_amount']),
        'fromMoneyCalculation shape',
        $rag[0]['numbers'] ?? null
    );

    $composed = KichelOllamaClient::compose('Skonto 3 % von 1000 Euro', $rag, $money['answer']);
    kr2_assert(
        str_contains($composed, '€') && str_contains($composed, 'Quelle:'),
        'compose without ollama uses fallback with source',
        $composed
    );

    $numbers = KichelRagFact::collectNumbers($rag);
    kr2_assert(isset($numbers['net_amount']), 'collectNumbers from rag list', $numbers);
}

// Prompt-Vertrag: ohne Ollama kein Netzaufruf / null
if (!KichelOllamaClient::isEnabled()) {
    kr2_assert(
        KichelOllamaClient::phrase('x', 'Basis', [$fact]) === null,
        'phrase returns null when ollama disabled'
    );
} else {
    echo "SKIP  phrase live call (ollama_enabled=true on this host)\n";
}

echo $failed === 0 ? "ALL OK\n" : "FAILED {$failed}\n";
exit($failed === 0 ? 0 : 1);
