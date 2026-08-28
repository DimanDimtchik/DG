<?php
declare(strict_types=1);

/**
 * ganz-om.de — Platzhalter bis CRM-Instanz deployt ist.
 * Wartungsseite = dieselbe DB-Konfiguration wie dg.ganz-om.de (Master).
 */
require __DIR__ . '/bootstrap.php';

SitePlaceholderMaintenance::renderFromCrmRoot(DG_ROOT);
