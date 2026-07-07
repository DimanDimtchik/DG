<?php
declare(strict_types=1);

/**
 * Vollständigen SKR-Katalog in die Datenbank importieren.
 * php bin/import-chart-catalog.php [skr03|skr04|all]
 */
require dirname(__DIR__) . '/bootstrap.php';

MigrationRunner::runPending();

if (!Database::isConfigured()) {
    fwrite(STDERR, "Datenbank nicht konfiguriert.\n");
    exit(1);
}

$target = strtolower(trim((string) ($argv[1] ?? 'all')));
$types = match ($target) {
    'skr03', 'skr04' => [$target],
    'all' => ['skr03', 'skr04'],
    default => [],
};

if ($types === []) {
    fwrite(STDERR, "Verwendung: php bin/import-chart-catalog.php [skr03|skr04|all]\n");
    exit(1);
}

SettingsStore::set('chart_catalog_sync', []);

foreach ($types as $skrType) {
    ChartAccountRepository::ensureSeeded($skrType);
    $dbCount = ChartAccountRepository::countForSkr($skrType);
    $catalogCount = ChartAccountCatalog::catalogCount($skrType);
    $hintCount = ChartAccountRepository::countWithDetailedHints($skrType);
    echo strtoupper($skrType) . ": {$dbCount} Konten in DB (Katalog: {$catalogCount}, mit Hinweisen: {$hintCount})\n";
}

exit(0);
