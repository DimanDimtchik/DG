<?php
declare(strict_types=1);

/**
 * Platzhalter / Wartungsmodus für ganz-om.de
 * Nutzt dieselbe Wartungsseite wie das CRM (Inline-SVG), sobald bootstrap.php vorhanden ist.
 */
$crmBootstrap = __DIR__ . '/bootstrap.php';
if (is_file($crmBootstrap)) {
    require $crmBootstrap;
    if (class_exists('WebsiteMaintenanceSettings')) {
        WebsiteMaintenanceSettings::renderAndExit();
    }
    if (class_exists('WebsiteMaintenanceRenderer')) {
        WebsiteMaintenanceRenderer::send([
            'headline' => 'Die Seite befindet sich im Aufbau',
            'message' => 'Wir bereiten die Website vor. Bitte schauen Sie bald wieder vorbei.',
            'email' => '',
        ], 503);
    }
}

// Minimaler Fallback ohne CRM (gleiches Layout, Inline-SVG — keine externe Datei nötig)
header('Content-Type: text/html; charset=utf-8');
http_response_code(503);
header('Retry-After: 3600');
header('Cache-Control: no-store, no-cache, must-revalidate');
?><!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Die Seite befindet sich im Aufbau</title>
  <style>
    :root { --wm-text:#2c2824; --wm-muted:#5a534c; --wm-accent:#6e6258; --wm-bg:#f4efe8; --wm-card:#fff; }
    * { box-sizing:border-box; } html,body { margin:0; min-height:100%; }
    body { font-family:"Segoe UI",Helvetica,Arial,sans-serif; color:var(--wm-text); background:var(--wm-bg);
      min-height:100vh; display:flex; align-items:center; justify-content:center; padding:1.5rem; }
    .wm-wrap { width:min(32rem,100%); text-align:center; }
    .wm-art { display:block; width:min(100%,22rem); margin:0 auto 1.25rem; border-radius:12px;
      border:1px solid rgba(110,98,88,.18); background:#f4efe8; overflow:hidden; }
    .wm-art svg { display:block; width:100%; height:auto; }
    .wm-card { background:var(--wm-card); border:1px solid rgba(110,98,88,.2); border-radius:1rem;
      padding:1.5rem 1.35rem; box-shadow:0 12px 40px rgba(44,40,36,.1); }
    .wm-card h1 { margin:0 0 .75rem; font-size:clamp(1.35rem,3.5vw,1.75rem); line-height:1.25; }
    .wm-card p { margin:0 0 .75rem; color:var(--wm-muted); line-height:1.45; }
    .wm-card a { color:var(--wm-accent); font-weight:600; }
  </style>
</head>
<body>
  <main class="wm-wrap">
    <div class="wm-art" aria-hidden="true">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 420" role="img" aria-label="Website im Aufbau">
        <rect width="640" height="420" fill="#f4efe8"/>
        <g fill="none" stroke="#6e6258" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M40 340 H600" opacity="0.9"/>
          <path d="M120 340 V120 M520 340 V120" opacity="0.45"/>
          <path d="M120 120 H520 M120 190 H520 M120 260 H520" opacity="0.4"/>
          <path d="M200 340 V160 H440 V340" opacity="0.95"/>
          <path d="M175 160 L320 70 L465 160" opacity="0.95"/>
          <rect x="235" y="200" width="50" height="50" opacity="0.55"/>
          <rect x="355" y="200" width="50" height="50" opacity="0.55"/>
          <path d="M300 340 V280 H340 V340" opacity="0.7"/>
          <path d="M320 70 V20 H470" opacity="0.9"/>
          <path d="M470 20 V70" opacity="0.65" stroke-dasharray="8 10"/>
          <circle cx="470" cy="78" r="6" fill="#6e6258" stroke="none" opacity="0.75"/>
        </g>
      </svg>
    </div>
    <div class="wm-card">
      <h1>Die Seite befindet sich im Aufbau</h1>
      <p>Wir bereiten die Website vor. Bitte schauen Sie bald wieder vorbei.</p>
      <p><a href="https://ganz-soft.de">ganz-soft.de</a></p>
    </div>
  </main>
</body>
</html>
