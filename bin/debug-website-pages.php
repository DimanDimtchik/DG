<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$pdo = Database::pdo();

echo "DB OK\n";
echo "Pages in dg_website_pages:\n";

$count = $pdo->query('SELECT COUNT(*) FROM dg_website_pages')->fetchColumn();
echo 'COUNT=' . (string) $count . "\n";

$stmt = $pdo->query('SELECT id, title, slug, status FROM dg_website_pages ORDER BY id ASC');
foreach ($stmt ?: [] as $row) {
    echo implode('|', [
        (string) ($row['id'] ?? ''),
        (string) ($row['title'] ?? ''),
        (string) ($row['slug'] ?? ''),
        (string) ($row['status'] ?? ''),
    ]) . "\n";
}

echo "Repository list():\n";
$pages = WebsitePageRepository::list();
echo 'LIST=' . count($pages) . "\n";
foreach ($pages as $page) {
    echo implode('|', [
        (string) ($page['id'] ?? ''),
        (string) ($page['title'] ?? ''),
        (string) ($page['slug'] ?? ''),
        (string) ($page['status'] ?? ''),
    ]) . "\n";
}
