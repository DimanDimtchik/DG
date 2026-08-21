<?php
declare(strict_types=1);

/** Tabellarische Import-Hilfen (CSV, Excel, XML, JSON). */
final class InstallCsvHelper
{
    /**
     * Liest tabellarische Daten — akzeptiert dieselben Formate wie der Artikel-Import.
     *
     * @return list<list<string>>
     */
    public static function readRows(string $path): array
    {
        if (!is_readable($path)) {
            throw new InvalidArgumentException('Die Datei konnte nicht gelesen werden.');
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, InstallImportSourcePresets::tabularExtensions(), true)) {
            throw new InvalidArgumentException(
                'Dateiformat wird nicht unterstützt. Bitte laden Sie eine Excel- (.xlsx), CSV-, XML- oder JSON-Datei hoch.'
            );
        }

        return CalendarArticleImportReader::readFile($path, $ext === 'txt' ? 'csv' : $ext);
    }

    /**
     * @param array<string, list<string>> $baseAliases
     * @param array<string, list<string>> $extraAliases
     * @return array<string, list<string>>
     */
    public static function mergeAliases(array $baseAliases, array $extraAliases): array
    {
        $merged = $baseAliases;
        foreach ($extraAliases as $field => $names) {
            $merged[$field] = array_values(array_unique(array_merge($merged[$field] ?? [], $names)));
        }

        return $merged;
    }

    /**
     * @param list<string> $headerRow
     * @param array<string, list<string>> $aliases
     * @return array<string, int|null>
     */
    public static function mapColumns(array $headerRow, array $aliases): array
    {
        $normalized = array_map(self::normalizeHeader(...), $headerRow);
        $map = [];
        foreach ($aliases as $field => $names) {
            $map[$field] = null;
            foreach ($normalized as $index => $col) {
                if (in_array($col, $names, true)) {
                    $map[$field] = $index;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * @param array<string, int|null> $map
     * @param list<string> $line
     * @return array<string, string>
     */
    public static function rowFromMap(array $map, array $line): array
    {
        $row = [];
        foreach ($map as $field => $index) {
            if ($index !== null && isset($line[$index])) {
                $row[$field] = trim((string) $line[$index]);
            }
        }

        return $row;
    }

    public static function isEmptyRow(array $line): bool
    {
        foreach ($line as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    public static function normalizeHeader(string $value): string
    {
        $value = CalendarArticleImportReader::cleanCell($value);
        $value = strtolower($value);
        $value = str_replace([' ', '-', '/', '(', ')', '.'], ['_', '_', '_', '', '', ''], $value);

        return $value;
    }

    public static function uniqueLogin(string $base, callable $exists): string
    {
        $login = self::slugify($base);
        if ($login === '') {
            $login = 'kontakt';
        }
        $candidate = $login;
        $suffix = 2;
        while ($exists($candidate)) {
            $candidate = $login . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    public static function slugify(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
        $value = trim($value, '-');

        return substr($value, 0, 55);
    }

    public static function templateCsv(array $headers, array $example = []): string
    {
        $content = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $parts = [];
        foreach ($headers as $header) {
            $parts[] = '"' . str_replace('"', '""', $header) . '"';
        }
        $content .= implode(';', $parts) . "\r\n";
        if ($example !== []) {
            $exampleParts = [];
            foreach ($headers as $header) {
                $exampleParts[] = '"' . str_replace('"', '""', (string) ($example[$header] ?? '')) . '"';
            }
            $content .= implode(';', $exampleParts) . "\r\n";
        }

        return $content;
    }
}
