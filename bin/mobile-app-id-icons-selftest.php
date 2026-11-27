<?php
declare(strict_types=1);

/**
 * MA5a: App-ID / Anzeigename / Icons je App (kein com.example).
 *
 * Usage: php bin/mobile-app-id-icons-selftest.php
 */

function assertMa5a(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$plan = $root . '/docs/MOBILE-APPS-REST-PLAN.md';
assertMa5a(is_file($plan), 'MOBILE-APPS-REST-PLAN.md');
$planSrc = (string) file_get_contents($plan);
assertMa5a(preg_match('/\|\s*\*\*MA5a\*\*.*\|\s*\[x\]/u', $planSrc) === 1, 'Plan MA5a abgehakt');
assertMa5a(str_contains($planSrc, 'de.ganzsoft.dgkalender'), 'Plan nennt Kalender-App-ID');
assertMa5a(str_contains($planSrc, 'de.ganzsoft.dgmitarbeiter'), 'Plan nennt Mitarbeiter-App-ID');
assertMa5a(str_contains($planSrc, 'mobile-app-id-icons-selftest'), 'Selftest in Verweisen');

$apps = [
    'kalender' => [
        'id' => 'de.ganzsoft.dgkalender',
        'label' => 'DG Kalender',
        'mainKt' => 'android/app/src/main/kotlin/de/ganzsoft/dgkalender/MainActivity.kt',
    ],
    'mitarbeiter' => [
        'id' => 'de.ganzsoft.dgmitarbeiter',
        'label' => 'DG Mitarbeiter',
        'mainKt' => 'android/app/src/main/kotlin/de/ganzsoft/dgmitarbeiter/MainActivity.kt',
    ],
];

foreach ($apps as $name => $meta) {
    $base = $root . '/apps/' . $name;
    $gradle = $base . '/android/app/build.gradle.kts';
    $manifest = $base . '/android/app/src/main/AndroidManifest.xml';
    $mainKt = $base . '/' . $meta['mainKt'];
    $webManifest = $base . '/web/manifest.json';
    $iconBrand = $base . '/assets/branding/app_icon.png';
    $mipmap = $base . '/android/app/src/main/res/mipmap-xxxhdpi/ic_launcher.png';
    $webIcon = $base . '/web/icons/Icon-512.png';
    $favicon = $base . '/web/favicon.png';
    $winRc = $base . '/windows/runner/Runner.rc';

    assertMa5a(is_file($gradle), "{$name} build.gradle.kts");
    $g = (string) file_get_contents($gradle);
    assertMa5a(str_contains($g, 'applicationId = "' . $meta['id'] . '"'), "{$name} applicationId");
    assertMa5a(str_contains($g, 'namespace = "' . $meta['id'] . '"'), "{$name} namespace");
    assertMa5a(!str_contains($g, 'com.example'), "{$name} kein com.example in gradle");

    assertMa5a(is_file($mainKt), "{$name} MainActivity.kt Pfad");
    $kt = (string) file_get_contents($mainKt);
    assertMa5a(str_contains($kt, 'package ' . $meta['id']), "{$name} Kotlin package");
    assertMa5a(!is_dir($base . '/android/app/src/main/kotlin/com/example'), "{$name} kein com/example Kotlin-Ordner");

    assertMa5a(is_file($manifest), "{$name} AndroidManifest");
    $m = (string) file_get_contents($manifest);
    assertMa5a(str_contains($m, 'android:label="' . $meta['label'] . '"'), "{$name} Anzeigename Android");

    assertMa5a(is_file($webManifest), "{$name} web manifest");
    $wm = (string) file_get_contents($webManifest);
    assertMa5a(str_contains($wm, '"' . $meta['label'] . '"'), "{$name} Anzeigename Web");

    assertMa5a(is_file($iconBrand) && filesize($iconBrand) > 1000, "{$name} Branding-Icon");
    assertMa5a(is_file($mipmap) && filesize($mipmap) > 100, "{$name} Android Launcher-Icon");
    assertMa5a(is_file($webIcon) && filesize($webIcon) > 100, "{$name} Web Icon-512");
    assertMa5a(is_file($favicon) && filesize($favicon) > 50, "{$name} Favicon");

    if (is_file($winRc)) {
        $rc = (string) file_get_contents($winRc);
        assertMa5a(!str_contains($rc, 'com.example'), "{$name} Windows kein com.example");
        assertMa5a(str_contains($rc, $meta['label']), "{$name} Windows ProductName");
    }

    // iOS optional: wenn vorhanden, kein com.example
    $ios = $base . '/ios';
    if (is_dir($ios)) {
        $plistHit = false;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ios, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.plist')) {
                if (str_contains((string) file_get_contents($f->getPathname()), 'com.example')) {
                    $plistHit = true;
                    break;
                }
            }
        }
        assertMa5a(!$plistHit, "{$name} iOS ohne com.example");
    } else {
        echo "OK: {$name} iOS noch nicht angelegt (Android/Web fokussiert)\n";
    }
}

// Glob-Scan: kein com.example mehr in App-Quellen (außer ggf. Kommentaren — hart verboten)
foreach (['kalender', 'mitarbeiter'] as $name) {
    $hit = [];
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/apps/' . $name, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iter as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        if (preg_match('/\.(png|jpg|ico|lock)$/i', $path)) {
            continue;
        }
        // build/ und .dart_tool ignorieren
        if (str_contains($path, '/build/') || str_contains($path, '/.dart_tool/')) {
            continue;
        }
        $content = (string) @file_get_contents($path);
        if ($content !== '' && str_contains($content, 'com.example')) {
            $hit[] = substr($path, strlen($root) + 1);
        }
    }
    assertMa5a($hit === [], "{$name}: keine com.example-Reste" . ($hit === [] ? '' : ' in ' . implode(', ', array_slice($hit, 0, 5))));
}

echo "MA5a Selftest bestanden.\n";
exit(0);
