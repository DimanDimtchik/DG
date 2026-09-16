#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * LDAP Readiness-Check — vor Aktivierung von Hybrid/LDAP-only ausführen.
 *
 * @see docs/LDAP-INTEGRATION.md
 */
require dirname(__DIR__) . '/bootstrap.php';

function line(string $text = ''): void
{
    echo $text . "\n";
}

line('== LDAP Readiness ==');
line();

$settings = LdapSettings::forForm();
line('Modus (CRM): ' . ($settings['mode'] ?? '?'));
line('Server unterstützt LDAP: ' . (LdapSettings::serverSupportsLdap() ? 'ja' : 'nein (Shared Hosting / enabled=false)'));
line('ldap.local.php: ' . (($settings['local_configured'] ?? false) ? 'vorhanden' : 'fehlt'));
line('JIT-Provision: ' . (!empty($settings['jit_provision']) ? 'ja' : 'nein'));
line();

$readiness = LdapAuthenticator::readiness();
foreach ($readiness['items'] as $item) {
    $mark = ($item['ok'] ?? false) ? 'OK' : 'OFFEN';
    line(sprintf('[%s] %s — %s', $mark, $item['label'], $item['detail']));
}

line();
line('Gesamt: ' . ($readiness['ready'] ? 'BEREIT für LDAP-Login' : 'NOCH NICHT BEREIT (normal auf Kasserver)'));
line();
line('Dokumentation: docs/LDAP-INTEGRATION.md');
line('Migration: database/migrations/065_user_auth_source.sql');

exit($readiness['ready'] ? 0 : 1);
