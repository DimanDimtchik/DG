<?php
declare(strict_types=1);

/** DATEV Stammdaten: Kontenbeschriftungen und Personenkonten (EXTF). */
final class DatevStammdatenExporter
{
    /**
     * @return array{filename: string, content: string, count: int}
     */
    public static function exportAccounts(int $year): array
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        MigrationRunner::runPending();

        $cfg = DatevExportSettings::config();
        if ($cfg['consultant_number'] === '' || $cfg['client_number'] === '') {
            throw new RuntimeException('DATEV Berater- und Mandantennummer fehlen.');
        }

        $skr = ChartOfAccountsSettings::activeSkrType();
        $stmt = Database::pdo()->prepare(
            'SELECT account_number, name FROM dg_chart_accounts WHERE skr_type = :skr ORDER BY account_number ASC'
        );
        $stmt->execute(['skr' => $skr]);

        $header = [
            'EXTF', '100', '21', 'Kontenbeschriftungen', '7',
            date('YmdHis'), sprintf('%04d0101', $year), sprintf('%04d1231', $year),
            $cfg['consultant_number'], $cfg['client_number'], '', CompanySettings::displayName(), '', 'EUR',
        ];
        $columns = ['Konto', 'Kontenbeschriftung', 'Sprach-ID'];
        $lines = [self::line($header), self::line($columns)];
        $count = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $lines[] = self::line([
                (string) ($row['account_number'] ?? ''),
                mb_substr((string) ($row['name'] ?? ''), 0, 60),
                'de-DE',
            ]);
            $count++;
        }

        return [
            'filename' => sprintf('EXTF_Kontenbeschriftungen_%d.csv', $year),
            'content' => implode("\r\n", $lines) . "\r\n",
            'count' => $count,
        ];
    }

    /**
     * @return array{filename: string, content: string, count: int}
     */
    public static function exportPersonAccounts(): array
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->query(
            "SELECT id, display_name, company_name, debtor_account, creditor_account
             FROM dg_contacts
             WHERE debtor_account <> '' OR creditor_account <> ''
             ORDER BY id ASC"
        );

        $lines = ['Kontonummer;Name;Typ;Kontakt-ID'];
        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = trim((string) ($row['company_name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($row['display_name'] ?? ''));
            }
            foreach (['debtor' => (string) ($row['debtor_account'] ?? ''), 'creditor' => (string) ($row['creditor_account'] ?? '')] as $type => $acc) {
                if ($acc === '') {
                    continue;
                }
                $lines[] = implode(';', [$acc, $name, $type, (string) ($row['id'] ?? '')]);
                $count++;
            }
        }

        return [
            'filename' => 'DATEV_Personenkonten_' . date('Ymd') . '.csv',
            'content' => implode("\r\n", $lines) . "\r\n",
            'count' => $count,
        ];
    }

    /**
     * @param list<string|int> $fields
     */
    private static function line(array $fields): string
    {
        $parts = [];
        foreach ($fields as $field) {
            $value = (string) $field;
            if (str_contains($value, '"') || str_contains($value, ';')) {
                $value = '"' . str_replace('"', '""', $value) . '"';
            }
            $parts[] = $value;
        }

        return implode(';', $parts);
    }
}
