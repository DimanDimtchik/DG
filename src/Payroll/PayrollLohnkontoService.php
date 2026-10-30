<?php
declare(strict_types=1);

/**
 * Lohn L6c: Lohnkonto je MA/Jahr — Zeilen, Summen, Kontroll-CSV (ohne FiBu/SEPA).
 */
final class PayrollLohnkontoService
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function rowsForContactYear(
        int $contactId,
        int $year,
        bool $includeDrafts = false
    ): array {
        if ($contactId < 1 || $year < 2000 || $year > 2100) {
            return [];
        }
        PayrollDocumentRepository::ensureReady();
        if (!PayrollRunRepository::tableReady()) {
            return [];
        }

        $statuses = $includeDrafts
            ? [
                PayrollRunRepository::STATUS_CALCULATED,
                PayrollRunRepository::STATUS_APPROVED,
                PayrollRunRepository::STATUS_LOCKED,
            ]
            : [
                PayrollRunRepository::STATUS_APPROVED,
                PayrollRunRepository::STATUS_LOCKED,
            ];
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $sql = "SELECT
                    e.run_id,
                    e.contact_id,
                    e.status AS emp_status,
                    e.path,
                    e.result_json,
                    e.net_pay,
                    e.gross_total,
                    r.period_year,
                    r.period_month,
                    r.kind,
                    r.status AS run_status,
                    r.is_superseded,
                    r.label
                FROM dg_payroll_run_employees e
                INNER JOIN dg_payroll_runs r ON r.id = e.run_id
                WHERE e.contact_id = ?
                  AND r.period_year = ?
                  AND e.status <> ?
                  AND r.status IN ($placeholders)
                ORDER BY r.period_month ASC, r.id ASC";
        $params = array_merge(
            [$contactId, $year, PayrollRunRepository::EMP_SKIPPED],
            $statuses
        );
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = self::normalizeRow($row);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $dbRow
     * @return array<string, mixed>
     */
    public static function normalizeRow(array $dbRow): array
    {
        $amounts = self::decodeAmounts($dbRow['result_json'] ?? null);
        $gross = $dbRow['gross_total'] !== null && $dbRow['gross_total'] !== ''
            ? (float) $dbRow['gross_total']
            : (float) ($amounts['gross_total'] ?? 0);
        $net = $dbRow['net_pay'] !== null && $dbRow['net_pay'] !== ''
            ? (float) $dbRow['net_pay']
            : (float) ($amounts['net_pay'] ?? 0);
        $lst = (float) ($amounts['lst'] ?? 0);
        $svAn = self::svAnSum($amounts);
        $runId = (int) ($dbRow['run_id'] ?? 0);
        $contactId = (int) ($dbRow['contact_id'] ?? 0);
        $runStatus = (string) ($dbRow['run_status'] ?? '');
        $kind = (string) ($dbRow['kind'] ?? PayrollRunRepository::KIND_REGULAR);
        $isFinal = $runStatus === PayrollRunRepository::STATUS_LOCKED;
        $doc = null;
        if ($isFinal && $runId > 0 && $contactId > 0 && PayrollDocumentRepository::tableReady()) {
            $doc = PayrollDocumentRepository::findByRunContact(
                $runId,
                $contactId,
                PayrollDocumentRepository::KIND_PAYSLIP
            );
        }

        return [
            'run_id' => $runId,
            'contact_id' => $contactId,
            'period_year' => (int) ($dbRow['period_year'] ?? 0),
            'period_month' => (int) ($dbRow['period_month'] ?? 0),
            'kind' => $kind,
            'kind_label' => self::kindLabel($kind),
            'run_status' => $runStatus,
            'emp_status' => (string) ($dbRow['emp_status'] ?? ''),
            'is_superseded' => !empty($dbRow['is_superseded']),
            'is_draft_run' => $runStatus === PayrollRunRepository::STATUS_CALCULATED,
            'label' => (string) ($dbRow['label'] ?? ''),
            'path' => (string) ($dbRow['path'] ?? ''),
            'gross_total' => round($gross, 2),
            'lst' => round($lst, 2),
            'sv_an' => round($svAn, 2),
            'net_pay' => round($net, 2),
            'document_id' => is_array($doc) ? (int) ($doc['id'] ?? 0) : 0,
            'has_final_pdf' => is_array($doc) && (int) ($doc['id'] ?? 0) > 0,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{gross_total: float, lst: float, sv_an: float, net_pay: float, count: int}
     */
    public static function yearTotals(array $rows): array
    {
        $gross = 0.0;
        $lst = 0.0;
        $sv = 0.0;
        $net = 0.0;
        $n = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            // Ersetzte Läufe in Summen belassen (Prüfer: beide sichtbar), aber
            // fachlich nur nicht-superseded zählen — Spec: Kennzeichnung, beide zeigen.
            if (!empty($row['is_superseded'])) {
                continue;
            }
            $gross += (float) ($row['gross_total'] ?? 0);
            $lst += (float) ($row['lst'] ?? 0);
            $sv += (float) ($row['sv_an'] ?? 0);
            $net += (float) ($row['net_pay'] ?? 0);
            $n++;
        }

        return [
            'gross_total' => round($gross, 2),
            'lst' => round($lst, 2),
            'sv_an' => round($sv, 2),
            'net_pay' => round($net, 2),
            'count' => $n,
        ];
    }

    /**
     * @param array<string, float|int> $amounts
     */
    public static function svAnSum(array $amounts): float
    {
        return round(
            (float) ($amounts['kv_an'] ?? 0)
            + (float) ($amounts['rv_an'] ?? 0)
            + (float) ($amounts['av_an'] ?? 0)
            + (float) ($amounts['pv_an'] ?? 0),
            2
        );
    }

    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            PayrollRunRepository::KIND_CORRECTION => 'Korrektur',
            PayrollRunRepository::KIND_STORNO => 'Storno',
            default => 'Regulär',
        };
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array{gross_total: float, lst: float, sv_an: float, net_pay: float, count: int} $totals
     */
    public static function toCsv(
        int $contactId,
        string $contactLabel,
        int $year,
        array $rows,
        array $totals
    ): string {
        $lines = [];
        $lines[] = self::csvLine([
            'Kontakt-ID',
            'Name',
            'Jahr',
            'Monat',
            'Run-ID',
            'Art',
            'Lauf-Status',
            'MA-Status',
            'Ersetzt',
            'Brutto',
            'LSt',
            'SV-AN',
            'Netto',
            'PDF-ID',
        ]);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $lines[] = self::csvLine([
                (string) $contactId,
                $contactLabel,
                (string) $year,
                sprintf('%02d', (int) ($row['period_month'] ?? 0)),
                (string) (int) ($row['run_id'] ?? 0),
                (string) ($row['kind_label'] ?? $row['kind'] ?? ''),
                (string) ($row['run_status'] ?? ''),
                (string) ($row['emp_status'] ?? ''),
                !empty($row['is_superseded']) ? 'ja' : 'nein',
                self::csvMoney((float) ($row['gross_total'] ?? 0)),
                self::csvMoney((float) ($row['lst'] ?? 0)),
                self::csvMoney((float) ($row['sv_an'] ?? 0)),
                self::csvMoney((float) ($row['net_pay'] ?? 0)),
                (string) (int) ($row['document_id'] ?? 0),
            ]);
        }
        $lines[] = self::csvLine([
            '',
            '',
            '',
            'SUMME*',
            (string) (int) ($totals['count'] ?? 0) . ' Läufe',
            '',
            '',
            '',
            '*ohne ersetzt',
            self::csvMoney((float) ($totals['gross_total'] ?? 0)),
            self::csvMoney((float) ($totals['lst'] ?? 0)),
            self::csvMoney((float) ($totals['sv_an'] ?? 0)),
            self::csvMoney((float) ($totals['net_pay'] ?? 0)),
            '',
        ]);

        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    public static function csvFilename(int $contactId, int $year): string
    {
        return sprintf('lohnkonto-%d-%04d.csv', $contactId, $year);
    }

    /**
     * @param list<string|int|float> $cols
     */
    private static function csvLine(array $cols): string
    {
        $out = [];
        foreach ($cols as $c) {
            $s = (string) $c;
            if (str_contains($s, '"') || str_contains($s, ';') || str_contains($s, "\n") || str_contains($s, "\r")) {
                $s = '"' . str_replace('"', '""', $s) . '"';
            }
            $out[] = $s;
        }

        return implode(';', $out);
    }

    private static function csvMoney(float $v): string
    {
        return number_format($v, 2, ',', '');
    }

    /**
     * @return array<string, float|int>
     */
    private static function decodeAmounts(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
