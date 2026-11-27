<?php

declare(strict_types=1);

/**
 * KH2 — Mitarbeiter sehen keine Roh-Fakten; Admin behält volle Antwort.
 * Usage: php bin/kichel-role-view-selftest.php
 */

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$failed = 0;
$assert = static function (bool $ok, string $label, mixed $detail = null) use (&$failed): void {
    if (!$ok) {
        $failed++;
    }
    $suffix = $detail !== null
        ? ' → ' . json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : '';
    echo ($ok ? 'OK  ' : 'FAIL') . '  ' . $label . $suffix . "\n";
};

$adminRole = (string) App::config('roles.admin', 'administrator');
$employeeRole = (string) App::config('roles.employee', 'dg_eigenmitarbeiter');
$admin = new User(1, 'admin', 'Administrator', '', [$adminRole], true);
$staff = new User(2, 'mitarbeiter', 'Mitarbeiter', '', [$employeeRole], true);

$full = [
    'presentation' => 'simple',
    'kind' => 'topic',
    'answer' => 'Unter Firmendaten.',
    'follow_up' => 'War das hilfreich?',
    'action_links' => [['label' => 'Öffnen', 'href' => '/app?page=einstellungen']],
    'sources' => [
        ['label' => 'CRM-Hilfe', 'ref' => 'topic:ust-id', 'href' => '/app?page=einstellungen&tab=firmendaten'],
    ],
    'rag_facts' => [
        ['type' => 'topic', 'title' => 'USt-ID', 'body' => '…', 'source_ref' => 'topic:ust-id', 'confidence' => 0.9],
    ],
    'rank_meta' => ['top_k' => 3],
    'rag_used' => true,
    'calculation' => [
        'kind' => 'skonto',
        'facts' => ['net' => 1000, 'rate' => 0.03],
        'computed_by' => 'php',
    ],
];

$asAdmin = KichelRoleView::forUser($admin, $full);
$assert(isset($asAdmin['rag_facts']), 'admin keeps rag_facts');
$assert(isset($asAdmin['rank_meta']), 'admin keeps rank_meta');
$assert(($asAdmin['sources'][0]['ref'] ?? '') === 'topic:ust-id', 'admin keeps source ref');
$assert(isset($asAdmin['calculation']['facts']), 'admin keeps calculation.facts');

$asStaff = KichelRoleView::forUser($staff, $full);
$assert(!isset($asStaff['rag_facts']), 'staff without rag_facts');
$assert(!isset($asStaff['rank_meta']), 'staff without rank_meta');
$assert(!array_key_exists('ref', $asStaff['sources'][0] ?? []), 'staff sources without ref');
$assert(($asStaff['sources'][0]['label'] ?? '') === 'CRM-Hilfe', 'staff keeps source label');
$assert(($asStaff['answer'] ?? '') === 'Unter Firmendaten.', 'staff keeps answer');
$assert(($asStaff['rag_used'] ?? false) === true, 'staff keeps rag_used flag');
$assert(!isset($asStaff['calculation']['facts']), 'staff without calculation.facts');
$assert(($asStaff['calculation']['computed_by'] ?? '') === 'php', 'staff keeps calculation meta');

$staffOnly = KichelRoleView::forStaff($full);
$assert(!isset($staffOnly['rag_facts']) && isset($staffOnly['sources']), 'forStaff helper');

$api = (string) file_get_contents(DG_ROOT . '/src/Kichel/KichelApi.php');
$assert(str_contains($api, 'KichelRoleView::forUser'), 'API uses RoleView');
$assert(
    str_contains($api, 'ProtocolRepository::record')
    && strpos($api, 'ProtocolRepository::record') < strpos($api, 'KichelRoleView::forUser'),
    'protocol records before role filter'
);

$live = KichelAssistant::answer($admin, 'Wo trage ich die USt-ID ein?');
$staffView = KichelRoleView::forUser($staff, $live);
$adminView = KichelRoleView::forUser($admin, $live);
$assert(
    isset($adminView['rag_facts']) || ($adminView['rag_used'] ?? false) === true,
    'live admin has rag payload or rag_used'
);
$assert(!isset($staffView['rag_facts']), 'live staff without rag_facts');
$assert(($staffView['answer'] ?? '') !== '', 'live staff still has answer');

if ($failed > 0) {
    fwrite(STDERR, "FAILED: {$failed}\n");
    exit(1);
}
echo "OK KH2 role view selftest\n";
exit(0);
