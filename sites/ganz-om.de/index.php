<?php
declare(strict_types=1);

/**
 * ganz-om.de — Platzhalter bis CRM-Instanz deployt ist.
 * Wartungsseite automatisiert über SitePlaceholderMaintenance + WebsiteMaintenanceRenderer.
 */
require __DIR__ . '/bootstrap.php';

if (class_exists('WebsiteMaintenanceSettings') && class_exists('Database') && Database::isConfigured()) {
    WebsiteMaintenanceSettings::renderAndExit();
}

SitePlaceholderMaintenance::renderAndExit(DG_SITE_ROOT);
