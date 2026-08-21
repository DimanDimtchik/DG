<?php
declare(strict_types=1);

/**
 * Addison / Wolters Kluwer — nutzt dasselbe DATEV-EXTF-Buchungsstapel-Format.
 * Wrapper mit Addison-spezifischem Dateinamen und optionaler Kopfzeile.
 */
final class AddisonExporter
{
    /**
     * @return array{filename: string, content: string, count: int}
     */
    public static function export(int $year, ?string $fromDate = null, ?string $toDate = null): array
    {
        $export = DatevExtfExporter::export($year, $fromDate, $toDate, includeManual: true);

        return [
            'filename' => sprintf('Addison_Buchungsstapel_%d_%s.csv', $year, date('Ymd')),
            'content' => $export['content'],
            'count' => $export['count'],
        ];
    }
}
