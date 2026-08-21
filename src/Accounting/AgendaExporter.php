<?php
declare(strict_types=1);

/**
 * Agenda FIBU CSV-Import (DATEV-kompatibles Semikolon-Format).
 * Spalten laut Agenda/Textbuch-Konvertierung.
 */
final class AgendaExporter
{
    /**
     * @return array{filename: string, content: string, count: int}
     */
    public static function export(int $year, ?string $fromDate = null, ?string $toDate = null): array
    {
        $rows = LedgerExportRepository::postingsForExport($year, $fromDate, $toDate);
        $header = 'Umsatz in Euro;Steuerschlüssel;Gegenkonto;Beleg1;Beleg2;Datum;Konto;Kost1;Kost2;Skonto in Euro;Buchungstext;Umsatzsteuer-ID;Zusatzart;Zusatzinformation';
        $lines = [$header];
        $count = 0;

        foreach ($rows as $row) {
            $amount = round((float) ($row['amount'] ?? 0), 2);
            if ($amount == 0.0) {
                continue;
            }
            $side = (string) ($row['side'] ?? 'debit');
            $signed = $side === 'credit' ? -$amount : $amount;
            $date = self::agendaDate((string) ($row['posting_date'] ?? ''));
            $taxKey = (string) ($row['tax_key'] ?? '');
            $contra = trim((string) ($row['contra_account'] ?? ''));
            $doc1 = trim((string) ($row['document_field1'] ?? ''));
            $doc2 = trim((string) ($row['document_field2'] ?? ''));

            $lines[] = implode(';', [
                number_format($signed, 2, ',', ''),
                $taxKey,
                $contra,
                mb_substr($doc1, 0, 36),
                mb_substr($doc2, 0, 36),
                $date,
                (string) ($row['account_number'] ?? ''),
                '',
                '',
                '',
                mb_substr((string) ($row['description'] ?? ''), 0, 60),
                '',
                '',
                '',
            ]);
            $count++;
        }

        return [
            'filename' => sprintf('Agenda_Buchungen_%d_%s.csv', $year, date('Ymd')),
            'content' => implode("\r\n", $lines) . "\r\n",
            'count' => $count,
        ];
    }

    private static function agendaDate(string $isoDate): string
    {
        $ts = strtotime($isoDate);

        return $ts !== false ? date('d.m.Y', $ts) : '';
    }
}
