<?php
declare(strict_types=1);

/**
 * KR8a — feste Fragen: erwartete Quellen-Refs, Leerfall, kein Fantasie-§.
 * Usage: php bin/kichel-rag-smoke-selftest.php
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$failed = 0;
$adminRole = (string) App::config('roles.admin', 'administrator');
$user = new User(1, 'admin', 'Administrator', '', [$adminRole], true);

function kr8_assert(bool $ok, string $label, mixed $detail = null): void
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

/**
 * @param list<array<string, mixed>> $facts
 * @return array<string, true>
 */
function kr8_allowed_paragraphs(array $facts): array
{
    $allowed = [];
    foreach ($facts as $fact) {
        $hay = (string) ($fact['title'] ?? '') . ' ' . (string) ($fact['body'] ?? '')
            . ' ' . (string) ($fact['source_label'] ?? '') . ' ' . (string) ($fact['source_ref'] ?? '');
        if (preg_match_all('/§\s*\d+[a-z]?/iu', $hay, $m) === false) {
            continue;
        }
        foreach ($m[0] as $p) {
            $key = mb_strtolower(preg_replace('/\s+/u', '', $p) ?? $p, 'UTF-8');
            $allowed[$key] = true;
        }
    }

    return $allowed;
}

/**
 * @param list<array<string, mixed>> $facts
 */
function kr8_no_fantasy_paragraph(string $answer, array $facts): bool
{
    if (preg_match_all('/§\s*\d+[a-z]?/iu', $answer, $m) === false || $m[0] === []) {
        return true;
    }
    $allowed = kr8_allowed_paragraphs($facts);
    foreach ($m[0] as $p) {
        $key = mb_strtolower(preg_replace('/\s+/u', '', $p) ?? $p, 'UTF-8');
        if (!isset($allowed[$key])) {
            return false;
        }
    }

    return true;
}

/**
 * @param list<string> $needles
 * @param list<array{label?: string, ref?: string}> $sources
 */
function kr8_sources_match(array $sources, array $needles): bool
{
    $blob = '';
    foreach ($sources as $s) {
        $blob .= ' ' . (string) ($s['ref'] ?? '') . ' ' . (string) ($s['label'] ?? '');
    }
    $blob = mb_strtolower($blob, 'UTF-8');
    foreach ($needles as $needle) {
        if (str_contains($blob, mb_strtolower($needle, 'UTF-8'))) {
            return true;
        }
    }

    return false;
}

// --- Gute Fälle (3+) ---
$ust = KichelAssistant::answer($user, 'Wo trage ich die USt-ID ein?');
kr8_assert(
    kr8_sources_match($ust['sources'] ?? [], ['topic:ust-id', 'firmendaten']),
    'USt-ID → Quelle topic/firmendaten',
    $ust['sources'] ?? []
);
kr8_assert(
    kr8_no_fantasy_paragraph((string) ($ust['answer'] ?? ''), $ust['rag_facts'] ?? []),
    'USt-ID keine Fantasie-§'
);

$klein = KichelAssistant::answer($user, 'Was bedeutet die Kleinunternehmerregelung?');
kr8_assert(
    kr8_sources_match($klein['sources'] ?? [], ['tax:tax-u19', 'tax-u19']),
    'Kleinunternehmer → tax:tax-u19',
    $klein['sources'] ?? []
);
kr8_assert(
    kr8_no_fantasy_paragraph((string) ($klein['answer'] ?? ''), $klein['rag_facts'] ?? []),
    'Kleinunternehmer §§ nur aus Fakten',
    $klein['answer'] ?? ''
);

$skonto = KichelAssistant::answer($user, 'Skonto 3 % von 1000 Euro');
kr8_assert(
    ($skonto['rag_used'] ?? false) === true
    && kr8_sources_match($skonto['sources'] ?? [], ['money:skonto']),
    'Skonto → money:skonto + rag_used',
    $skonto['sources'] ?? []
);
kr8_assert(
    str_contains((string) ($skonto['answer'] ?? ''), '970')
    || str_contains((string) ($skonto['answer'] ?? ''), '30'),
    'Skonto Beträge aus PHP',
    $skonto['answer'] ?? ''
);

$ist = KichelAssistant::answer($user, 'Was ist Ist-Versteuerung?');
kr8_assert(
    kr8_sources_match($ist['sources'] ?? [], ['tax:tax-ist-soll', 'tax-ist-soll']),
    'Ist-Versteuerung → tax-ist-soll',
    $ist['sources'] ?? []
);

$legal = KichelAssistant::answer($user, 'Wo finde ich Pflichtseiten Impressum?');
kr8_assert(
    ($legal['kind'] ?? '') === 'legal_pages'
    && kr8_sources_match($legal['sources'] ?? [], ['legal:pflichtseiten']),
    'Pflichtseiten → legal',
    ['kind' => $legal['kind'] ?? null, 'sources' => $legal['sources'] ?? []]
);

// --- Unklare Fälle (2) ---
$noise = KichelAssistant::answer($user, 'asdf qwer zxcv xyz');
kr8_assert(
    empty($noise['rag_facts'])
    && empty($noise['sources'])
    && str_contains((string) ($noise['answer'] ?? ''), 'nichts Passendes'),
    'Nonsense → Leerfall ohne Quellen',
    $noise['answer'] ?? ''
);
kr8_assert(
    !preg_match('/§\s*\d+/u', (string) ($noise['answer'] ?? '')),
    'Nonsense ohne erfundenen §'
);

$vague = KichelAssistant::answer($user, 'Was gilt steuerlich so allgemein?');
$vagueHasFantasy = !kr8_no_fantasy_paragraph((string) ($vague['answer'] ?? ''), $vague['rag_facts'] ?? []);
$vagueOk = empty($vague['rag_facts'])
    || (
        !empty($vague['sources'])
        && !$vagueHasFantasy
    );
kr8_assert($vagueOk, 'vage Steuerfrage: leer oder nur Fakten-§', [
    'sources' => $vague['sources'] ?? [],
    'answer' => mb_substr((string) ($vague['answer'] ?? ''), 0, 200),
]);

// Ollama-Zustand dokumentieren (KR8b-Teil lokal)
$ollamaOn = KichelOllamaClient::isEnabled();
echo ($ollamaOn ? 'INFO' : 'OK  ') . '  ollama_enabled=' . ($ollamaOn ? 'true' : 'false')
    . ' (Smoke ohne Netzpflicht; Dev zusätzlich an/aus prüfen)' . "\n";

echo $failed === 0 ? "ALL OK\n" : "FAILED {$failed}\n";
exit($failed === 0 ? 0 : 1);
