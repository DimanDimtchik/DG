<?php
declare(strict_types=1);

$defaults = WebsiteMaintenanceSettings::defaults();
WebsiteMaintenanceRenderer::send([
    'headline' => (string) App::config('crm_name', 'CRM') . ' – Seite nicht gefunden',
    'message' => 'Die Seite ist noch nicht online oder existiert nicht.',
    'email' => $defaults['email'],
], 404);
