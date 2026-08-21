<?php
declare(strict_types=1);

$root = '/www/htdocs/w0217246';
$master = $root . '/dg.ganz-om.de';
$kontur = $root . '/kontur-cosmetics.de';
$transfer = $root . '/cursor-transfer';

copy($transfer . '/UpdateChecker.php', $master . '/src/Update/UpdateChecker.php');
copy($transfer . '/LicenseGuard.php', $master . '/src/Security/LicenseGuard.php');
copy($transfer . '/build-release.php', $master . '/bin/build-release.php');
copy($transfer . '/version.php', $master . '/config/version.php');
copy($transfer . '/force-update.php', $master . '/bin/force-update.php');

echo "=== Build release on master ===\n";
passthru('php ' . escapeshellarg($master . '/bin/build-release.php'), $code);
if ($code !== 0) {
    exit($code);
}

// Reset kontur to 1.0.1 so update has something to do
file_put_contents($kontur . '/config/version.php', "<?php\ndeclare(strict_types=1);\n\nreturn '1.0.1';\n");
// Ensure kontur has the fixed UpdateChecker before applying update
copy($transfer . '/UpdateChecker.php', $kontur . '/src/Update/UpdateChecker.php');
copy($transfer . '/LicenseGuard.php', $kontur . '/src/Security/LicenseGuard.php');
copy($transfer . '/force-update.php', $kontur . '/bin/force-update.php');

// Clear previous update state force flags but keep history
$stateFile = $kontur . '/storage/update-state.json';
$state = [
    'force_pending' => false,
    'installed_version' => '1.0.1',
    'last_error' => null,
];
file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "kontur reset to 1.0.1\n";
echo "master version: " . (require $master . '/config/version.php') . "\n";
echo "kontur version: " . (require $kontur . '/config/version.php') . "\n";
