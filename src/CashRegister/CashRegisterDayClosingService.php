<?php
declare(strict_types=1);

/**
 * Tagesabschluss je Registerkasse (K12a) — Summen Bar/EC/Karte/Gutschein.
 * Bon-/Sitzungs-Sperre nach Abschluss: K12b.
 * Filialabschluss = Summe der Kassen-Abschlüsse (K12c, keine Extra-Tabelle).
 * Nicht FiBu-Kassenbuch (`dg_cash_day_closings` / CashDayCloseService) — K12d.
 */
final class CashRegisterDayClosingService
{
    /** Tag für diese Kasse bereits abgeschlossen? (K12b) */
    public static function isClosed(int $registerId, string $businessDate): bool
    {
        $date = trim($businessDate);
        if ($registerId <= 0 || $date === '' || !CashRegisterDayClosingRepository::tableExists()) {
            return false;
        }

        return CashRegisterDayClosingRepository::findByRegisterDate($registerId, $date) !== null;
    }

    /**
     * Filialabschluss-Übersicht: Summe der Kassen-Abschlüsse (K12c).
     *
     * @return array{
     *   location_id: int,
     *   business_date: string,
     *   register_count: int,
     *   closed_count: int,
     *   complete: bool,
     *   opening_balance: float,
     *   total_cash: float,
     *   total_ec: float,
     *   total_card: float,
     *   total_voucher: float,
     *   total_gross: float,
     *   receipt_count: int,
     *   expected_balance: float,
     *   counted_balance: float,
     *   difference: float,
     *   registers: list<array{register_id: int, code: string, name: string, closed: bool, closing: ?array<string, mixed>}>
     * }
     */
    public static function locationSummary(int $locationId, string $businessDate): array
    {
        if ($locationId <= 0) {
            throw new RuntimeException('Filiale fehlt.');
        }
        $date = trim($businessDate);
        if ($date === '') {
            $date = date('Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new RuntimeException('Geschäftstag ungültig.');
        }
        $location = CashLocationRepository::findById($locationId);
        if ($location === null) {
            throw new RuntimeException('Filiale nicht gefunden.');
        }

        $registers = CashRegisterRepository::listByLocation($locationId, true);
        $closings = CashRegisterDayClosingRepository::listByLocationDate($locationId, $date);
        $byRegister = [];
        foreach ($closings as $row) {
            $rid = (int) ($row['register_id'] ?? 0);
            if ($rid > 0) {
                $byRegister[$rid] = $row;
            }
        }

        $sum = [
            'opening_balance' => 0.0,
            'total_cash' => 0.0,
            'total_ec' => 0.0,
            'total_card' => 0.0,
            'total_voucher' => 0.0,
            'total_gross' => 0.0,
            'receipt_count' => 0,
            'expected_balance' => 0.0,
            'counted_balance' => 0.0,
            'difference' => 0.0,
        ];
        $rows = [];
        $closedCount = 0;
        foreach ($registers as $reg) {
            $rid = (int) ($reg['id'] ?? 0);
            $closing = $byRegister[$rid] ?? null;
            $isClosed = $closing !== null;
            if ($isClosed) {
                $closedCount++;
                foreach (array_keys($sum) as $key) {
                    if ($key === 'receipt_count') {
                        $sum[$key] += (int) ($closing[$key] ?? 0);
                    } else {
                        $sum[$key] = round($sum[$key] + (float) ($closing[$key] ?? 0), 2);
                    }
                }
            }
            $rows[] = [
                'register_id' => $rid,
                'code' => (string) ($reg['code'] ?? ''),
                'name' => (string) ($reg['name'] ?? ''),
                'closed' => $isClosed,
                'closing' => $closing,
            ];
        }

        $registerCount = count($registers);

        return [
            'location_id' => $locationId,
            'business_date' => $date,
            'register_count' => $registerCount,
            'closed_count' => $closedCount,
            'complete' => $registerCount > 0 && $closedCount === $registerCount,
            'opening_balance' => $sum['opening_balance'],
            'total_cash' => $sum['total_cash'],
            'total_ec' => $sum['total_ec'],
            'total_card' => $sum['total_card'],
            'total_voucher' => $sum['total_voucher'],
            'total_gross' => $sum['total_gross'],
            'receipt_count' => $sum['receipt_count'],
            'expected_balance' => $sum['expected_balance'],
            'counted_balance' => $sum['counted_balance'],
            'difference' => $sum['difference'],
            'registers' => $rows,
        ];
    }

    /**
     * Vorschau-Summen (ohne Speichern).
     *
     * @return array{
     *   location_id: int,
     *   register_id: int,
     *   business_date: string,
     *   opening_balance: float,
     *   total_cash: float,
     *   total_ec: float,
     *   total_card: float,
     *   total_voucher: float,
     *   total_gross: float,
     *   receipt_count: int,
     *   expected_balance: float,
     *   already_closed: bool,
     *   open_session: bool,
     *   unfinished_receipts: int
     * }
     */
    public static function preview(int $registerId, string $businessDate): array
    {
        [$register, $date] = self::assertRegisterAndDate($registerId, $businessDate);

        $totals = CashReceiptRepository::dayTotalsByRegisterDate($registerId, $date);
        $opening = CashSessionRepository::sumOpeningBalance($registerId, $date);
        $expected = round($opening + $totals['total_cash'], 2);
        $open = CashSessionRepository::findOpenByRegister($registerId);

        return [
            'location_id' => (int) ($register['location_id'] ?? 0),
            'register_id' => $registerId,
            'business_date' => $date,
            'opening_balance' => $opening,
            'total_cash' => $totals['total_cash'],
            'total_ec' => $totals['total_ec'],
            'total_card' => $totals['total_card'],
            'total_voucher' => $totals['total_voucher'],
            'total_gross' => $totals['total_gross'],
            'receipt_count' => $totals['receipt_count'],
            'expected_balance' => $expected,
            'already_closed' => CashRegisterDayClosingRepository::findByRegisterDate($registerId, $date) !== null,
            'open_session' => $open !== null,
            'unfinished_receipts' => CashReceiptRepository::countUnfinishedByRegisterDate($registerId, $date),
        ];
    }

    /**
     * Tagesabschluss speichern.
     *
     * @return int closing id
     */
    public static function close(
        int $registerId,
        string $businessDate,
        int $closedBy,
        mixed $countedBalance = null,
        string $notes = ''
    ): int {
        if ($closedBy <= 0) {
            throw new RuntimeException('Bediener fehlt.');
        }
        if (!CashRegisterDayClosingRepository::tableExists()) {
            throw new RuntimeException('Abschluss-Tabelle fehlt — bitte Migrationen ausführen.');
        }

        $preview = self::preview($registerId, $businessDate);
        if ($preview['already_closed']) {
            throw new RuntimeException('Dieser Geschäftstag ist für die Kasse bereits abgeschlossen.');
        }
        if ($preview['open_session']) {
            throw new RuntimeException('Bitte zuerst die offene Kassensitzung schließen.');
        }
        if ($preview['unfinished_receipts'] > 0) {
            throw new RuntimeException(
                'Noch offene Bons (Entwurf/Zahlend) an diesem Tag — bitte zuerst fertigstellen oder abbrechen.'
            );
        }

        $counted = $countedBalance === null || $countedBalance === ''
            ? $preview['expected_balance']
            : self::parseMoney($countedBalance);
        $diff = round($counted - $preview['expected_balance'], 2);

        $summary = json_encode([
            'total_cash' => $preview['total_cash'],
            'total_ec' => $preview['total_ec'],
            'total_card' => $preview['total_card'],
            'total_voucher' => $preview['total_voucher'],
            'total_gross' => $preview['total_gross'],
            'receipt_count' => $preview['receipt_count'],
        ], JSON_UNESCAPED_UNICODE);

        return CashRegisterDayClosingRepository::insert([
            'location_id' => $preview['location_id'],
            'register_id' => $registerId,
            'business_date' => $preview['business_date'],
            'opening_balance' => $preview['opening_balance'],
            'total_cash' => $preview['total_cash'],
            'total_ec' => $preview['total_ec'],
            'total_card' => $preview['total_card'],
            'total_voucher' => $preview['total_voucher'],
            'total_gross' => $preview['total_gross'],
            'receipt_count' => $preview['receipt_count'],
            'expected_balance' => $preview['expected_balance'],
            'counted_balance' => round($counted, 2),
            'difference' => $diff,
            'summary_json' => $summary === false ? null : $summary,
            'notes' => trim($notes),
            'closed_by' => $closedBy,
            'closed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    private static function assertRegisterAndDate(int $registerId, string $businessDate): array
    {
        if ($registerId <= 0) {
            throw new RuntimeException('Kasse fehlt.');
        }
        $date = trim($businessDate);
        if ($date === '') {
            $date = date('Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new RuntimeException('Geschäftstag ungültig.');
        }

        $register = CashRegisterRepository::findById($registerId);
        if ($register === null) {
            throw new RuntimeException('Kasse nicht gefunden.');
        }
        $locationId = (int) ($register['location_id'] ?? 0);
        if ($locationId <= 0) {
            throw new RuntimeException('Filiale der Kasse fehlt.');
        }

        return [$register, $date];
    }

    private static function parseMoney(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }
        $raw = str_replace([' ', "\xc2\xa0"], '', $raw);
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } else {
            $raw = str_replace(',', '.', $raw);
        }
        if (!is_numeric($raw)) {
            throw new RuntimeException('Ungültiger Zählbetrag.');
        }

        return (float) $raw;
    }
}
