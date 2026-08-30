<?php
/**
 * Bereinigt gespeicherte Website-Texte (HTML-Entities, <br>, Tags) in der DB.
 *
 *   php bin/repair-website-text.php
 */
declare(strict_types=1);

define('DG_ROOT', dirname(__DIR__));
require_once DG_ROOT . '/src/autoload.php';

if (!Database::isConfigured()) {
    fwrite(STDERR, "ERROR: Datenbank nicht konfiguriert.\n");
    exit(1);
}

MigrationRunner::runPending();
$pdo = Database::pdo();
$rows = $pdo->query('SELECT id, title, slug, status, layout_json, updated_at FROM dg_website_pages ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
$upd = $pdo->prepare('UPDATE dg_website_pages SET layout_json = :layout_json WHERE id = :id');
$fixed = 0;

foreach ($rows as $row) {
    $raw = (string) ($row['layout_json'] ?? '');
    $decoded = $raw !== '' ? json_decode($raw, true) : null;
    if (!is_array($decoded) || !isset($decoded['rows']) || !is_array($decoded['rows'])) {
        continue;
    }
    $normalized = WebsiteContent::normalizeLayout($decoded);
    $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if ($json === $raw) {
        continue;
    }
    $upd->execute(['layout_json' => $json, 'id' => (int) $row['id']]);
    $fixed++;
    echo "OK: {$row['title']} (/{$row['slug']})\n";
}

echo "Fertig: {$fixed} Seite(n) bereinigt.\n";
