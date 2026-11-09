<?php
declare(strict_types=1);

/**
 * Kassensitzung öffnen (K11a) / schließen (K11c).
 * Bon ohne offene Sitzung: CashReceiptService::startDraft (K11b).
 * Abgeschlossener Tag: keine neue Sitzung (K12b); Zählung in K12a.
 */
final class CashSessionService
{
    /**
     * Kasse bewusst öffnen (kein Auto-Open).
     *
     * @return int session id
     */
    public static function open(
        int $registerId,
        int $openedBy,
        mixed $openingBalance = 0,
        ?string $businessDate = null,
        string $notes = ''
    ): int {
        if ($registerId <= 0) {
            throw new RuntimeException('Kasse fehlt.');
        }
        if ($openedBy <= 0) {
            throw new RuntimeException('Bediener fehlt.');
        }
        if (!CashSessionRepository::tableExists()) {
            throw new RuntimeException('Sitzungs-Tabelle fehlt — bitte Migrationen ausführen.');
        }

        $register = CashRegisterRepository::findById($registerId);
        if ($register === null) {
            throw new RuntimeException('Kasse nicht gefunden.');
        }
        if (empty($register['is_active'])) {
            throw new RuntimeException('Kasse ist inaktiv.');
        }

        if (CashSessionRepository::findOpenByRegister($registerId) !== null) {
            throw new RuntimeException('Für diese Kasse ist bereits eine Sitzung offen.');
        }

        $balance = self::parseMoney($openingBalance);
        if ($balance < 0) {
            throw new RuntimeException('Anfangsbestand darf nicht negativ sein.');
        }

        $date = trim((string) ($businessDate ?? ''));
        if ($date === '') {
            $date = date('Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new RuntimeException('Geschäftstag ungültig.');
        }

        if (CashRegisterDayClosingService::isClosed($registerId, $date)) {
            throw new RuntimeException(
                'Geschäftstag ist abgeschlossen — keine neue Sitzung für diesen Tag.'
            );
        }

        return CashSessionRepository::insert([
            'register_id' => $registerId,
            'business_date' => $date,
            'status' => 'open',
            'opening_balance' => round($balance, 2),
            'opened_by' => $openedBy,
            'opened_at' => date('Y-m-d H:i:s'),
            'notes' => trim($notes),
        ]);
    }

    /**
     * Offene Sitzung schließen (K11c) — Zählfelder bleiben leer (K12).
     *
     * @return int session id
     */
    public static function close(int $registerId, int $closedBy, string $notes = ''): int
    {
        if ($registerId <= 0) {
            throw new RuntimeException('Kasse fehlt.');
        }
        if ($closedBy <= 0) {
            throw new RuntimeException('Bediener fehlt.');
        }
        if (!CashSessionRepository::tableExists()) {
            throw new RuntimeException('Sitzungs-Tabelle fehlt — bitte Migrationen ausführen.');
        }

        $session = CashSessionRepository::findOpenByRegister($registerId);
        if ($session === null) {
            throw new RuntimeException('Keine offene Sitzung für diese Kasse.');
        }
        $sessionId = (int) ($session['id'] ?? 0);
        if ($sessionId <= 0) {
            throw new RuntimeException('Keine offene Sitzung für diese Kasse.');
        }

        $openReceipts = CashReceiptRepository::countUnfinishedBySession($sessionId);
        if ($openReceipts > 0) {
            throw new RuntimeException(
                'Sitzung hat noch offene Bons (Entwurf/Zahlend) — bitte zuerst abschließen oder abbrechen.'
            );
        }

        $note = trim($notes);
        $existingNotes = trim((string) ($session['notes'] ?? ''));
        if ($note !== '') {
            $existingNotes = $existingNotes === '' ? $note : ($existingNotes . ' | ' . $note);
        }

        CashSessionRepository::update($sessionId, [
            'status' => 'closed',
            'closed_by' => $closedBy,
            'closed_at' => date('Y-m-d H:i:s'),
            'notes' => $existingNotes,
        ]);

        return $sessionId;
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
            throw new RuntimeException('Ungültiger Anfangsbestand.');
        }

        return (float) $raw;
    }
}
