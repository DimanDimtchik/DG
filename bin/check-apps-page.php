<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

MigrationRunner::runPending();
MobileAppDownloadService::ensureDraftWebsitePage();
MobileAppDownloadService::ensureDownloadsDir();

$slug = MobileAppDownloadService::PAGE_SLUG;
$p = WebsitePageRepository::findBySlugAnyStatus($slug);
if ($p === null) {
    fwrite(STDERR, "MISSING slug={$slug}\n");
    exit(1);
}

echo 'OK id=' . (int) ($p['id'] ?? 0)
    . ' slug=' . (string) ($p['slug'] ?? '')
    . ' title=' . (string) ($p['title'] ?? '')
    . ' status=' . (string) ($p['status'] ?? '')
    . "\n";
