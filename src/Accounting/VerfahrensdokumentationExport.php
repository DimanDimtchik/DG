<?php
declare(strict_types=1);

/**
 * Verfahrensdokumentation-Vorlagen für Download / GoBD-ZIP (K21b).
 * Quellen: docs/VERFAHRENSDOKUMENTATION-*.md — keine Kundendaten hardcoden.
 */
final class VerfahrensdokumentationExport
{
    public const FILE_FIBU = 'VERFAHRENSDOKUMENTATION-VORLAGE.md';
    public const FILE_CASH = 'VERFAHRENSDOKUMENTATION-REGISTERKASSE.md';

    /**
     * Relativer Archivpfad → Dateiname unter docs/.
     *
     * @return array<string, string>
     */
    public static function catalog(): array
    {
        return [
            'verfahrensdokumentation/' . self::FILE_FIBU => self::FILE_FIBU,
            'verfahrensdokumentation/' . self::FILE_CASH => self::FILE_CASH,
        ];
    }

    /**
     * @return list<string>
     */
    public static function expectedZipNames(): array
    {
        $names = array_keys(self::catalog());
        $names[] = 'README.txt';
        sort($names);

        return $names;
    }

    public static function absolutePath(string $docFile): string
    {
        $base = basename(str_replace('\\', '/', $docFile));
        if ($base !== self::FILE_FIBU && $base !== self::FILE_CASH) {
            throw new InvalidArgumentException('Unbekannte Verfahrensdokumentation.');
        }
        $path = DG_ROOT . '/docs/' . $base;
        if (!is_readable($path)) {
            throw new RuntimeException('Vorlage fehlt: docs/' . $base);
        }

        return $path;
    }

    public static function readMarkdown(string $docFile): string
    {
        $content = file_get_contents(self::absolutePath($docFile));
        if ($content === false) {
            throw new RuntimeException('Vorlage konnte nicht gelesen werden.');
        }

        return $content;
    }

    /**
     * @return array{path: string, filename: string, sha256: string, files: int}
     */
    public static function buildZip(): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP-ZipArchive fehlt — Verfahrensdokumentation-Export nicht möglich.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'dg_vdoc_');
        if ($tmp === false) {
            throw new RuntimeException('Temporäre Datei konnte nicht angelegt werden.');
        }
        $zipPath = $tmp . '.zip';
        @unlink($tmp);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ZIP konnte nicht erstellt werden.');
        }

        $readme = "DG CRM — Verfahrensdokumentation (Vorlagen)\r\n"
            . "Stand Export: " . date('c') . "\r\n\r\n"
            . "Inhalt:\r\n"
            . "- " . self::FILE_FIBU . " — FiBu / GoBD-Gesamtvorlage\r\n"
            . "- " . self::FILE_CASH . " — Baustein Registerkasse / TSE\r\n\r\n"
            . "Platzhalter ([Firma], Namen, Backup) durch den Betrieb ausfüllen.\r\n"
            . "Keine automatische Übermittlung an Behörden.\r\n";

        $zip->addFromString('README.txt', $readme);
        foreach (self::catalog() as $archiveName => $docFile) {
            $zip->addFromString($archiveName, self::readMarkdown($docFile));
        }
        $zip->close();

        $sha = hash_file('sha256', $zipPath);
        if (!is_string($sha)) {
            $sha = '';
        }

        return [
            'path' => $zipPath,
            'filename' => 'DG_Verfahrensdokumentation_' . date('Ymd_His') . '.zip',
            'sha256' => $sha,
            'files' => count(self::catalog()) + 1,
        ];
    }

    public static function downloadZipAndExit(?int $userId = null): void
    {
        $pack = self::buildZip();
        $binary = file_get_contents($pack['path']);
        @unlink($pack['path']);
        if ($binary === false) {
            throw new RuntimeException('ZIP konnte nicht gelesen werden.');
        }

        if (class_exists('AuditLog')) {
            try {
                AuditLog::record('verfahrensdoku_export', null, json_encode([
                    'sha256' => $pack['sha256'],
                    'files' => $pack['files'],
                    'user_id' => $userId,
                ], JSON_UNESCAPED_UNICODE));
            } catch (Throwable) {
                // Download trotzdem
            }
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $pack['filename'] . '"');
        header('Content-Length: ' . (string) strlen($binary));
        header('Cache-Control: no-store');
        echo $binary;
        exit;
    }

    public static function downloadMarkdownAndExit(string $which): void
    {
        $which = strtolower(trim($which));
        $doc = match ($which) {
            'fibu', 'vorlage' => self::FILE_FIBU,
            'registerkasse', 'kasse', 'cash' => self::FILE_CASH,
            default => throw new InvalidArgumentException('Unbekannte Vorlage.'),
        };
        $content = self::readMarkdown($doc);
        $filename = $doc;
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) strlen($content));
        header('Cache-Control: no-store');
        echo $content;
        exit;
    }

    /**
     * Fügt Vorlagen in ein offenes ZipArchive ein (z. B. GoBD-Prüferexport).
     *
     * @return list<array{name: string, sha256: string, rows: int}>
     */
    public static function appendToZip(ZipArchive $zip): array
    {
        $meta = [];
        foreach (self::catalog() as $archiveName => $docFile) {
            $content = self::readMarkdown($docFile);
            $zip->addFromString($archiveName, $content);
            $meta[] = [
                'name' => $archiveName,
                'sha256' => hash('sha256', $content),
                'rows' => 0,
            ];
        }

        return $meta;
    }
}
