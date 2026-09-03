<?php
declare(strict_types=1);

/** Bankumsätze aus MT940 (SWIFT Kontoauszug). */
final class Mt940Importer
{
    /**
     * @return array{batch: string, imported: int, skipped: int, duplicates: int}
     */
    public static function import(string $content): array
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        MigrationRunner::runPending();

        $content = trim($content);
        if ($content === '') {
            throw new InvalidArgumentException('Leere MT940-Datei.');
        }

        $batch = bin2hex(random_bytes(16));
        $imported = 0;
        $skipped = 0;
        $duplicates = 0;

        $statements = preg_split('/\r?\n(?=-{5})/', $content) ?: [$content];
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            foreach (self::parseTransactions($statement) as $tx) {
                if ($tx['amount'] == 0.0 || $tx['transaction_date'] === '') {
                    $skipped++;
                    continue;
                }
                $result = BankTransactionRepository::insertOrSkip($tx + ['import_batch' => $batch]);
                if ($result['skipped']) {
                    $duplicates++;
                    continue;
                }
                $imported++;
            }
        }

        if ($imported > 0) {
            BankReconciliationService::autoMatchBatch($batch);
        }

        return ['batch' => $batch, 'imported' => $imported, 'skipped' => $skipped, 'duplicates' => $duplicates];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function parseTransactions(string $statement): array
    {
        $lines = preg_split('/\r?\n/', $statement) ?: [];
        $rows = [];
        $currentDate = '';
        $currentRef = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, ':20:') || str_starts_with($line, ':25:') || str_starts_with($line, ':28C:')) {
                continue;
            }

            if (preg_match('/^:60[FM]:([CD])?(\d{6})(\d{4})?/', $line, $m) === 1) {
                $currentDate = self::parseMt940Date($m[2]);
                continue;
            }

            if (preg_match('/^:61:(\d{6})(\d{4})?([CD])([A-Z]{1,3})?([\d,]+)/', $line, $m) === 1) {
                $currentDate = self::parseMt940Date($m[1]);
                $sign = ($m[3] ?? 'C') === 'D' ? -1.0 : 1.0;
                $amount = round((float) str_replace(',', '.', (string) ($m[5] ?? '0')), 2) * $sign;
                $currentRef = '';
                $rows[] = [
                    'transaction_date' => $currentDate,
                    'value_date' => $currentDate,
                    'amount' => $amount,
                    'counterparty_name' => '',
                    'counterparty_iban' => '',
                    'reference_text' => '',
                    'end_to_end_id' => '',
                    '_amount_pending' => true,
                ];
                continue;
            }

            if (preg_match('/^:86:(.*)$/', $line, $m) === 1 && $rows !== []) {
                $idx = count($rows) - 1;
                $text = trim((string) ($m[1] ?? ''));
                $rows[$idx]['reference_text'] = mb_substr($text, 0, 500);
                if (preg_match('/\?32([^?]+)/', $text, $name) === 1) {
                    $rows[$idx]['counterparty_name'] = trim($name[1]);
                }
                if (preg_match('/\?31([A-Z0-9 ]+)/', $text, $iban) === 1) {
                    $rows[$idx]['counterparty_iban'] = strtoupper(str_replace(' ', '', $iban[1]));
                }
                unset($rows[$idx]['_amount_pending']);
            }
        }

        return array_values(array_filter($rows, static fn (array $row): bool => !isset($row['_amount_pending'])));
    }

    private static function parseMt940Date(string $raw): string
    {
        if (preg_match('/^(\d{2})(\d{2})(\d{2})$/', $raw, $m) !== 1) {
            return '';
        }
        $year = (int) $m[1];
        $year += $year >= 70 ? 1900 : 2000;

        return sprintf('%04d-%02d-%02d', $year, (int) $m[2], (int) $m[3]);
    }
}
