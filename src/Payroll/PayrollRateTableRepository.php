<?php
declare(strict_types=1);

/**
 * Lohn L4b: versionierte Beitrags-/Grenzwert-Tabellen + CSV-Import + Fixture-Seed.
 */
final class PayrollRateTableRepository
{
    public const AUDIT_IMPORT = 'payroll_rate_import';
    public const AUDIT_SUPERSEDE = 'payroll_rate_supersede';
    public const AUDIT_PARAM_PATCH = 'payroll_rate_param_patch';
    public const FIXTURE_LABEL = 'FIXTURE-2026-A';

    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
        self::seedFixtureIfMissing();
    }

    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            $pdo = Database::pdo();

            return $pdo->query("SHOW TABLES LIKE 'dg_payroll_rate_sets'")->fetchColumn() !== false
                && $pdo->query("SHOW TABLES LIKE 'dg_payroll_rate_params'")->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Allowlist Stufe A (L4a) + midi_an_factor für Midi-Pfad.
     *
     * @return list<string>
     */
    public static function allowedKeys(): array
    {
        return [
            'minijob_month_limit_eur',
            'midi_lower_eur',
            'midi_upper_eur',
            'midi_an_factor',
            'kv_an_rate',
            'kv_ag_rate',
            'kv_additional_an_rate',
            'rv_an_rate',
            'rv_ag_rate',
            'av_an_rate',
            'av_ag_rate',
            'pv_an_rate',
            'pv_ag_rate',
            'pv_childless_surcharge_an',
            'bbg_kv_month_eur',
            'bbg_rv_month_eur',
            'u1_rate',
            'u2_rate',
            'u3_rate',
            'minijob_flat_lst_rate',
            'minijob_flat_kv_rate',
            'solz_rate',
            'kst_ek_rate',
            'kst_rk_rate',
            'lst_fixture_factor',
        ];
    }

    /**
     * FIXTURE-2026-A laut L4a (+ midi_an_factor).
     *
     * @return array<string, string> param_key => decimal string
     */
    public static function fixtureParams(): array
    {
        return [
            'minijob_month_limit_eur' => '560.00000000',
            'midi_lower_eur' => '560.01000000',
            'midi_upper_eur' => '2000.00000000',
            'midi_an_factor' => '0.70000000',
            'kv_an_rate' => '0.07300000',
            'kv_ag_rate' => '0.07300000',
            'kv_additional_an_rate' => '0.01700000',
            'rv_an_rate' => '0.09300000',
            'rv_ag_rate' => '0.09300000',
            'av_an_rate' => '0.01300000',
            'av_ag_rate' => '0.01300000',
            'pv_an_rate' => '0.01700000',
            'pv_ag_rate' => '0.01700000',
            'pv_childless_surcharge_an' => '0.00000000',
            'bbg_kv_month_eur' => '5000.00000000',
            'bbg_rv_month_eur' => '7500.00000000',
            'u1_rate' => '0.01700000',
            'u2_rate' => '0.00400000',
            'u3_rate' => '0.00060000',
            'minijob_flat_lst_rate' => '0.02000000',
            'minijob_flat_kv_rate' => '0.13000000',
            'solz_rate' => '0.05500000',
            'kst_ek_rate' => '0.09000000',
            'kst_rk_rate' => '0.09000000',
            'lst_fixture_factor' => '0.14000000',
        ];
    }

    public static function seedFixtureIfMissing(): void
    {
        if (!self::tableReady()) {
            return;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id FROM dg_payroll_rate_sets WHERE label = :label LIMIT 1'
        );
        $stmt->execute(['label' => self::FIXTURE_LABEL]);
        if ($stmt->fetchColumn() !== false) {
            return;
        }
        self::insertSet(
            [
                'valid_from' => '2026-01-01',
                'valid_to' => null,
                'label' => self::FIXTURE_LABEL,
                'source_note' => 'System-Beispiel — nur Tests, keine Amtswerte',
                'checksum' => '',
                'is_active' => 1,
            ],
            self::fixtureParams(),
            null
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(bool $activeOnly = false): array
    {
        self::ensureReady();
        if (!self::tableReady()) {
            return [];
        }
        $sql = 'SELECT * FROM dg_payroll_rate_sets';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY valid_from DESC, id DESC';
        $rows = Database::pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        self::ensureReady();
        if ($id < 1 || !self::tableReady()) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_payroll_rate_sets WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Parameter als assoziatives Array param_key => float-string.
     *
     * @return array<string, string>
     */
    public static function paramsForSet(int $rateSetId): array
    {
        self::ensureReady();
        if ($rateSetId < 1 || !self::tableReady()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT param_key, param_value FROM dg_payroll_rate_params WHERE rate_set_id = :id ORDER BY param_key ASC'
        );
        $stmt->execute(['id' => $rateSetId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[(string) $row['param_key']] = self::formatDecimal((string) $row['param_value']);
        }

        return $out;
    }

    /**
     * Auswahlregel L4a: max(valid_from) ≤ Monatsende und (valid_to IS NULL OR ≥ Monatsbeginn).
     *
     * @return array<string, mixed>|null Kopfzeile
     */
    public static function findForPeriod(string $periodYmd): ?array
    {
        self::ensureReady();
        if (!self::tableReady() || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodYmd)) {
            return null;
        }
        $monthStart = substr($periodYmd, 0, 8) . '01';
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $monthStart);
        if ($dt === false) {
            return null;
        }
        $monthEnd = $dt->modify('last day of this month')->format('Y-m-d');

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_payroll_rate_sets
             WHERE is_active = 1
               AND valid_from <= :month_end
               AND (valid_to IS NULL OR valid_to >= :month_start)
             ORDER BY valid_from DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'month_end' => $monthEnd,
            'month_start' => $monthStart,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * CSV-Import (Text). Format siehe parseImportText().
     *
     * @return int neue rate_set_id
     */
    public static function importFromText(string $text, ?int $userId = null): int
    {
        self::ensureReady();
        if (!self::tableReady()) {
            throw new RuntimeException('Beitragstabellen fehlen (Migration 127).');
        }
        $parsed = self::parseImportText($text);
        $checksum = hash('sha256', str_replace(["\r\n", "\r"], "\n", $text));
        $parsed['meta']['checksum'] = $checksum;

        $id = self::insertSet($parsed['meta'], $parsed['params'], $userId);

        if (class_exists('AuditLog')) {
            AuditLog::record(self::AUDIT_IMPORT, $userId, json_encode([
                'rate_set_id' => $id,
                'label' => $parsed['meta']['label'],
                'valid_from' => $parsed['meta']['valid_from'],
                'param_count' => count($parsed['params']),
                'checksum' => $checksum,
            ], JSON_UNESCAPED_UNICODE));
        }

        return $id;
    }

    /**
     * @return array{meta: array<string, mixed>, params: array<string, string>}
     */
    public static function parseImportText(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);
        $meta = [
            'valid_from' => '',
            'valid_to' => null,
            'label' => '',
            'source_note' => '',
            'checksum' => '',
            'is_active' => 1,
        ];
        $params = [];
        $allowed = array_fill_keys(self::allowedKeys(), true);

        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = str_getcsv($line);
            if ($parts === [null] || $parts === false) {
                continue;
            }
            $parts = array_map(static fn ($p) => trim((string) $p), $parts);
            $head = strtolower($parts[0] ?? '');

            if ($head === 'param') {
                if (count($parts) < 3) {
                    throw new InvalidArgumentException('Zeile ' . ($lineNum + 1) . ': param,key,value erwartet.');
                }
                $key = $parts[1];
                if (!isset($allowed[$key])) {
                    throw new InvalidArgumentException('Zeile ' . ($lineNum + 1) . ': param_key „' . $key . '“ nicht in Allowlist.');
                }
                $val = self::normalizeParamValue($parts[2]);
                if ($val === null) {
                    throw new InvalidArgumentException('Zeile ' . ($lineNum + 1) . ': ungültiger param_value.');
                }
                $params[$key] = $val;
                continue;
            }

            // Meta: key,value
            if (count($parts) < 2) {
                throw new InvalidArgumentException('Zeile ' . ($lineNum + 1) . ': key,value erwartet.');
            }
            $value = $parts[1];
            switch ($head) {
                case 'valid_from':
                    $meta['valid_from'] = self::requireDate($value, 'valid_from');
                    break;
                case 'valid_to':
                    $meta['valid_to'] = $value === '' ? null : self::requireDate($value, 'valid_to');
                    break;
                case 'label':
                    $meta['label'] = mb_substr($value, 0, 120);
                    break;
                case 'source_note':
                    $meta['source_note'] = mb_substr($value, 0, 255);
                    break;
                case 'is_active':
                    $meta['is_active'] = in_array(strtolower($value), ['1', 'true', 'yes', 'ja'], true) ? 1 : 0;
                    break;
                default:
                    throw new InvalidArgumentException('Zeile ' . ($lineNum + 1) . ': unbekanntes Feld „' . $parts[0] . '“.');
            }
        }

        if ($meta['valid_from'] === '' || $meta['label'] === '') {
            throw new InvalidArgumentException('Import braucht valid_from und label.');
        }
        if ($params === []) {
            throw new InvalidArgumentException('Import ohne Parameterzeilen (param,key,value).');
        }
        if ($meta['valid_to'] !== null && $meta['valid_from'] > $meta['valid_to']) {
            throw new InvalidArgumentException('valid_to liegt vor valid_from.');
        }

        return ['meta' => $meta, 'params' => $params];
    }

    /**
     * Deaktiviert Satz (Ablösung) mit Audit — kein hartes Löschen von Fixture mit Params-Historie.
     */
    public static function supersede(int $id, ?int $userId = null): void
    {
        $row = self::find($id);
        if ($row === null) {
            return;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'UPDATE dg_payroll_rate_sets SET
                is_active = 0,
                superseded_at = NOW(),
                superseded_by_user_id = :uid
             WHERE id = :id'
        );
        $stmt->execute([
            'uid' => $userId,
            'id' => $id,
        ]);
        if (class_exists('AuditLog')) {
            AuditLog::record(self::AUDIT_SUPERSEDE, $userId, json_encode([
                'rate_set_id' => $id,
                'label' => $row['label'] ?? '',
                'valid_from' => $row['valid_from'] ?? '',
            ], JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Manuelle Korrektur eines Parameters mit Audit (alt/neu).
     */
    public static function patchParam(int $rateSetId, string $paramKey, string $newValueRaw, ?int $userId = null): void
    {
        if (!in_array($paramKey, self::allowedKeys(), true)) {
            throw new InvalidArgumentException('param_key nicht erlaubt.');
        }
        $set = self::find($rateSetId);
        if ($set === null) {
            throw new InvalidArgumentException('Tabellensatz nicht gefunden.');
        }
        $newVal = self::normalizeParamValue($newValueRaw);
        if ($newVal === null) {
            throw new InvalidArgumentException('param_value ungültig.');
        }
        $params = self::paramsForSet($rateSetId);
        $oldVal = $params[$paramKey] ?? null;

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO dg_payroll_rate_params (rate_set_id, param_key, param_value)
             VALUES (:sid, :k, :v)
             ON DUPLICATE KEY UPDATE param_value = VALUES(param_value)'
        );
        $stmt->execute(['sid' => $rateSetId, 'k' => $paramKey, 'v' => $newVal]);

        if (class_exists('AuditLog')) {
            AuditLog::record(self::AUDIT_PARAM_PATCH, $userId, json_encode([
                'rate_set_id' => $rateSetId,
                'param_key' => $paramKey,
                'old' => $oldVal,
                'new' => $newVal,
            ], JSON_UNESCAPED_UNICODE));
        }
    }

    public static function exampleImportCsv(): string
    {
        $lines = [
            '# DG Payroll Rate Import v1 — Fixture-Beispiel (keine Amtswerte)',
            'valid_from,2026-01-01',
            'valid_to,',
            'label,EXAMPLE-IMPORT-2026',
            'source_note,Beispiel CSV',
            'is_active,1',
        ];
        foreach (self::fixtureParams() as $key => $val) {
            $lines[] = 'param,' . $key . ',' . rtrim(rtrim($val, '0'), '.');
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, string> $params
     */
    private static function insertSet(array $meta, array $params, ?int $userId): int
    {
        $pdo = Database::pdo();
        $label = mb_substr(trim((string) ($meta['label'] ?? '')), 0, 120);
        $validFrom = (string) ($meta['valid_from'] ?? '');
        if ($label === '' || $validFrom === '') {
            throw new InvalidArgumentException('label und valid_from erforderlich.');
        }

        // Kein zweiter aktiver Satz mit gleichem valid_from (L4a)
        $chk = $pdo->prepare(
            'SELECT id, label FROM dg_payroll_rate_sets
             WHERE is_active = 1 AND valid_from = :vf LIMIT 1'
        );
        $chk->execute(['vf' => $validFrom]);
        $clash = $chk->fetch(PDO::FETCH_ASSOC);
        if (is_array($clash) && (string) ($clash['label'] ?? '') !== $label) {
            throw new InvalidArgumentException(
                'Aktiver Satz mit valid_from ' . $validFrom . ' existiert bereits (#' . (int) $clash['id'] . '). Zuerst ablösen.'
            );
        }
        if (is_array($clash) && (string) ($clash['label'] ?? '') === $label) {
            throw new InvalidArgumentException('Satz mit diesem Label und valid_from existiert bereits aktiv.');
        }

        $ins = $pdo->prepare(
            'INSERT INTO dg_payroll_rate_sets
                (valid_from, valid_to, label, source_note, checksum, is_active, created_by_user_id)
             VALUES
                (:valid_from, :valid_to, :label, :source_note, :checksum, :is_active, :uid)'
        );
        $ins->execute([
            'valid_from' => $validFrom,
            'valid_to' => $meta['valid_to'] ?? null,
            'label' => $label,
            'source_note' => mb_substr((string) ($meta['source_note'] ?? ''), 0, 255),
            'checksum' => mb_substr((string) ($meta['checksum'] ?? ''), 0, 64),
            'is_active' => !empty($meta['is_active']) ? 1 : 0,
            'uid' => $userId,
        ]);
        $id = (int) $pdo->lastInsertId();

        $pIns = $pdo->prepare(
            'INSERT INTO dg_payroll_rate_params (rate_set_id, param_key, param_value)
             VALUES (:sid, :k, :v)'
        );
        foreach ($params as $key => $val) {
            if (!in_array($key, self::allowedKeys(), true)) {
                continue;
            }
            $pIns->execute(['sid' => $id, 'k' => $key, 'v' => $val]);
        }

        return $id;
    }

    private static function requireDate(string $value, string $field): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException($field . ' muss JJJJ-MM-TT sein.');
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($dt === false || $dt->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException($field . ' ungültig.');
        }

        return $value;
    }

    public static function normalizeParamValue(mixed $raw): ?string
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        $s = str_replace([' ', "\xc2\xa0"], '', $s);
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);
        }
        if (!preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            return null;
        }
        $f = (float) $s;
        if (!is_finite($f) || abs($f) > 999999999.99999999) {
            return null;
        }

        return self::formatDecimal($s);
    }

    private static function formatDecimal(string $s): string
    {
        return number_format((float) $s, 8, '.', '');
    }
}
