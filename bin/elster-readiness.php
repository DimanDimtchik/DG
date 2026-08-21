#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * ELSTER/ERiC Readiness-Check — vor Live-Abgabe ausführen.
 *
 * @see docs/ELSTER-ERIC-TODO.md
 */
require dirname(__DIR__) . '/bootstrap.php';

function line(string $text = ''): void
{
    echo $text . "\n";
}

line('== ELSTER / ERiC Readiness ==');
line();

$settings = ElsterSettings::forForm();
line('Modus: ' . ($settings['mode'] ?? '?'));
line('Server unterstützt ERiC: ' . (ElsterSettings::serverSupportsEric() ? 'ja' : 'nein (Shared Hosting / server_ready=false)'));
line('elster.local.php: ' . (($settings['local_configured'] ?? false) ? 'vorhanden' : 'fehlt'));
line();

$readiness = ElsterEricClient::readiness();
foreach ($readiness['items'] as $item) {
    $mark = ($item['ok'] ?? false) ? 'OK' : 'OFFEN';
    line(sprintf('[%s] %s — %s', $mark, $item['label'], $item['detail']));
}

line();
line('Gesamt: ' . ($readiness['ready'] ? 'BEREIT für ERiC-Implementierung' : 'NOCH NICHT BEREIT'));
line();
line('Dokumentation:');
line('  docs/SERVER-MIGRATION.md');
line('  docs/ELSTER-ERIC-TODO.md');
line('Server-Favorit: Hetzner SX65-2');

exit($readiness['ready'] ? 0 : 1);
