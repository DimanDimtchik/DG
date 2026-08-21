<?php
declare(strict_types=1);

$roots = [
    'ganz-soft.de' => '/www/htdocs/w0217246',
    'dg.ganz-om.de' => '/www/htdocs/w0217246/dg.ganz-om.de',
    'kontur-cosmetics.de' => '/www/htdocs/w0217246/kontur-cosmetics.de',
];

foreach ($roots as $label => $root) {
    echo "===== $label =====\n";
    $cmd = 'cd ' . escapeshellarg($root) . ' && php -r ' . escapeshellarg(
        'require "bootstrap.php";'
        . '$m=WebsiteSettings::menu();'
        . 'echo "MENU:\\n".json_encode($m, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\\n";'
        . 'foreach (WebsitePageRepository::list() as $p) {'
        . '  echo $p["id"]."|".$p["title"]."|".$p["slug"]."|".$p["status"]."\\n";'
        . '}'
    );
    passthru($cmd);
    echo "\n";
}
