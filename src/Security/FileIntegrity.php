<?php
declare(strict_types=1);

/**
 * Prüft kritische CRM-Dateien per SHA-256-Manifest auf unerlaubte Änderungen.
 */
final class FileIntegrity
{
    private const MANIFEST_FILE = '/storage/integrity_manifest.json';
    private const CHECK_INTERVAL = 86400; // 24h

    private const CRITICAL_PATHS = [
        '/index.php',
        '/bootstrap.php',
        '/src/Auth/AuthService.php',
        '/src/App.php',
        '/src/Security/LicenseGuard.php',
        '/src/Security/Firewall.php',
        '/src/Security/LoginThrottle.php',
        '/src/Security/SecurityHeaders.php',
        '/src/Database/Database.php',
        '/src/Csrf.php',
        '/src/autoload.php',
    ];

    /**
     * Führt die Integritätsprüfung aus, wenn seit der letzten Prüfung 24 Stunden vergangen sind.
     */
    public static function runIfDue(): void
    {
        $stateFile = DG_ROOT . '/storage/integrity_last_check.txt';
        $last = is_readable($stateFile) ? (int) file_get_contents($stateFile) : 0;

        if ((time() - $last) < self::CHECK_INTERVAL) {
            return;
        }

        @file_put_contents($stateFile, (string) time());
        self::check();
    }

    /**
     * Erzeugt ein neues Integritäts-Manifest für alle kritischen Pfade.
     *
     * @return array<string, string> Relativer Pfad => SHA-256-Hash.
     */
    public static function generateManifest(): array
    {
        $manifest = [];
        foreach (self::CRITICAL_PATHS as $rel) {
            $abs = DG_ROOT . $rel;
            if (is_readable($abs)) {
                $manifest[$rel] = hash_file('sha256', $abs);
            }
        }

        $dir = dirname(DG_ROOT . self::MANIFEST_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents(DG_ROOT . self::MANIFEST_FILE, json_encode($manifest, JSON_PRETTY_PRINT));

        return $manifest;
    }

    /**
     * Vergleicht aktuelle Dateihashes mit dem gespeicherten Manifest.
     *
     * @return list<array{file: string, reason: 'missing'|'modified'}> Abweichungen; leer wenn alles ok.
     */
    public static function check(): array
    {
        $file = DG_ROOT . self::MANIFEST_FILE;
        if (!is_readable($file)) {
            self::generateManifest();
            return [];
        }

        $manifest = json_decode((string) file_get_contents($file), true) ?: [];
        $tampered = [];

        foreach ($manifest as $rel => $expectedHash) {
            $abs = DG_ROOT . $rel;
            if (!is_readable($abs)) {
                $tampered[] = ['file' => $rel, 'reason' => 'missing'];
                continue;
            }
            $actual = hash_file('sha256', $abs);
            if ($actual !== $expectedHash) {
                $tampered[] = ['file' => $rel, 'reason' => 'modified'];
            }
        }

        if (!empty($tampered)) {
            AuditLog::record('file_integrity_violation', null, json_encode($tampered));
        }

        return $tampered;
    }

    /**
     * Alias für {@see check()} – liefert das aktuelle Prüfergebnis.
     *
     * @return list<array{file: string, reason: 'missing'|'modified'}>
     */
    public static function lastResult(): array
    {
        return self::check();
    }
}
