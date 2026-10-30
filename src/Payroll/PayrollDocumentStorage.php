<?php
declare(strict_types=1);

/**
 * Lohn L6b: Dateiablage für Abrechnungs-PDFs unter storage/payroll.
 */
final class PayrollDocumentStorage
{
    public static function baseDir(): string
    {
        return DG_ROOT . '/storage/payroll';
    }

    public static function ensureBase(): void
    {
        $dir = self::baseDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
    }

    /**
     * Relativer Pfad: {year}/{month}/lohn-{contactId}-{YYYYMM}-run{runId}[-draft].pdf
     */
    public static function relativePath(
        int $year,
        int $month,
        int $contactId,
        int $runId,
        bool $draft
    ): string {
        $ym = sprintf('%04d%02d', $year, $month);
        $name = sprintf(
            'lohn-%d-%s-run%d%s.pdf',
            $contactId,
            $ym,
            $runId,
            $draft ? '-draft' : ''
        );

        return sprintf('%04d/%02d/%s', $year, $month, $name);
    }

    public static function absolutePath(string $relative): string
    {
        $relative = ltrim(str_replace(['..', '\\'], ['', '/'], $relative), '/');

        return self::baseDir() . '/' . $relative;
    }

    /**
     * Schreibt Bytes; bei Draft Überschreiben erlaubt, sonst Fehler wenn Datei existiert.
     */
    public static function write(string $relative, string $binary, bool $allowOverwrite): string
    {
        self::ensureBase();
        $abs = self::absolutePath($relative);
        $dir = dirname($abs);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        if (is_file($abs) && !$allowOverwrite) {
            throw new RuntimeException('PDF existiert bereits und ist unveränderlich: ' . $relative);
        }
        if (file_put_contents($abs, $binary) === false) {
            throw new RuntimeException('PDF konnte nicht geschrieben werden.');
        }

        return $abs;
    }

    public static function read(string $relative): ?string
    {
        $abs = self::absolutePath($relative);
        if (!is_file($abs)) {
            return null;
        }
        $data = file_get_contents($abs);

        return is_string($data) ? $data : null;
    }

    public static function delete(string $relative): void
    {
        $abs = self::absolutePath($relative);
        if (is_file($abs)) {
            unlink($abs);
        }
    }
}
