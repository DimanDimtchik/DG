<?php

declare(strict_types=1);

/**
 * Geschenkgutscheine (Stamm) und Einlösung auf Erlös-Belegen.
 */
final class GiftVoucherRepository
{
    public static function ensureTables(): void
    {
        MigrationRunner::runPending();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listActive(?int $contactId = null, string $query = '', int $limit = 40): array
    {
        self::ensureTables();
        if (!Database::isConfigured()) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $sql = 'SELECT * FROM dg_gift_vouchers WHERE status = \'active\' AND remaining_gross > 0';
        $params = [];
        if ($contactId !== null && $contactId > 0) {
            $sql .= ' AND (contact_id IS NULL OR contact_id = :contact_id)';
            $params['contact_id'] = $contactId;
        }
        $query = trim($query);
        if ($query !== '') {
            $sql .= ' AND (code LIKE :q OR title LIKE :q2)';
            $like = '%' . $query . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
        }
        $sql .= ' ORDER BY valid_until IS NULL ASC, valid_until ASC, code ASC LIMIT ' . $limit;
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[] = self::normalizeRow($row);
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listAll(int $limit = 200): array
    {
        self::ensureTables();
        if (!Database::isConfigured()) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $stmt = Database::pdo()->query(
            'SELECT * FROM dg_gift_vouchers ORDER BY created_at DESC, id DESC LIMIT ' . $limit
        );
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[] = self::normalizeRow($row);
        }

        return $out;
    }

    public static function findById(int $id): ?array
    {
        self::ensureTables();
        if ($id < 1 || !Database::isConfigured()) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_gift_vouchers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::normalizeRow($row) : null;
    }

    public static function findByCode(string $code): ?array
    {
        self::ensureTables();
        $code = self::normalizeCode($code);
        if ($code === '' || !Database::isConfigured()) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_gift_vouchers WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::normalizeRow($row) : null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function create(array $input, ?int $userId): array
    {
        self::ensureTables();
        $code = self::normalizeCode((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = self::generateCode();
        }
        if (self::findByCode($code) !== null) {
            throw new InvalidArgumentException('Gutschein-Code ist bereits vergeben.');
        }
        $face = round(max(0.0, VoucherRepository::parseMoney($input['face_value_gross'] ?? 0)), 2);
        if ($face <= 0.0) {
            throw new InvalidArgumentException('Nennwert muss größer als 0 sein.');
        }
        $remaining = array_key_exists('remaining_gross', $input)
            ? round(max(0.0, VoucherRepository::parseMoney($input['remaining_gross'])), 2)
            : $face;
        if ($remaining > $face) {
            $remaining = $face;
        }
        $contactId = (int) ($input['contact_id'] ?? 0);
        $validUntil = trim((string) ($input['valid_until'] ?? ''));
        if ($validUntil !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validUntil)) {
            throw new InvalidArgumentException('Ungültiges Gültig-bis-Datum.');
        }
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            $title = 'Geschenkgutschein';
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_gift_vouchers
             (code, title, contact_id, face_value_gross, remaining_gross, status, valid_until, notes, created_by)
             VALUES
             (:code, :title, :contact_id, :face, :remaining, :status, :valid_until, :notes, :created_by)'
        );
        $stmt->execute([
            'code' => $code,
            'title' => mb_substr($title, 0, 191),
            'contact_id' => $contactId > 0 ? $contactId : null,
            'face' => $face,
            'remaining' => $remaining,
            'status' => $remaining <= 0.0 ? 'redeemed' : 'active',
            'valid_until' => $validUntil !== '' ? $validUntil : null,
            'notes' => trim((string) ($input['notes'] ?? '')) ?: null,
            'created_by' => $userId !== null && $userId > 0 ? $userId : null,
        ]);

        $id = (int) Database::pdo()->lastInsertId();
        $row = self::findById($id);
        if ($row === null) {
            throw new RuntimeException('Gutschein konnte nicht geladen werden.');
        }

        return $row;
    }

    /**
     * @return list<array{gift_voucher_id: int, code: string, title: string, amount_gross: float, remaining_before: float}>
     */
    public static function parseRedemptionRows(array $data): array
    {
        $raw = $data['gift_redemptions'] ?? null;
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        $seen = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $giftId = (int) ($row['gift_voucher_id'] ?? 0);
            $amount = round(max(0.0, VoucherRepository::parseMoney($row['amount_gross'] ?? 0)), 2);
            if ($giftId < 1 || $amount <= 0.0) {
                continue;
            }
            if (isset($seen[$giftId])) {
                throw new InvalidArgumentException('Jeder Gutschein darf nur einmal pro Beleg eingelöst werden.');
            }
            $seen[$giftId] = true;
            $gift = self::findById($giftId);
            if ($gift === null) {
                throw new InvalidArgumentException('Gutschein #' . $giftId . ' nicht gefunden.');
            }
            $out[] = [
                'gift_voucher_id' => $giftId,
                'code' => (string) $gift['code'],
                'title' => (string) $gift['title'],
                'amount_gross' => $amount,
                'remaining_before' => round((float) ($gift['remaining_gross'] ?? 0), 2),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function redemptionsForVoucher(int $voucherId): array
    {
        self::ensureTables();
        if ($voucherId < 1 || !Database::isConfigured()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT r.id, r.voucher_id, r.gift_voucher_id, r.amount_gross, r.created_at,
                    g.code, g.title, g.remaining_gross, g.face_value_gross
             FROM dg_voucher_gift_redemptions r
             INNER JOIN dg_gift_vouchers g ON g.id = r.gift_voucher_id
             WHERE r.voucher_id = :vid
             ORDER BY r.id ASC'
        );
        $stmt->execute(['vid' => $voucherId]);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $amount = round((float) $row['amount_gross'], 2);
            $remainingAfter = round((float) $row['remaining_gross'], 2);
            // Für die Beleg-Bearbeitung: Restwert inkl. dieses Beleg-Abzugs (nach Save ist remaining bereits reduziert).
            $available = round($remainingAfter + $amount, 2);
            $out[] = [
                'id' => (int) $row['id'],
                'voucher_id' => (int) $row['voucher_id'],
                'gift_voucher_id' => (int) $row['gift_voucher_id'],
                'amount_gross' => $amount,
                'code' => (string) $row['code'],
                'title' => (string) $row['title'],
                'remaining_gross' => $available,
                'remaining_after' => $remainingAfter,
                'face_value_gross' => round((float) $row['face_value_gross'], 2),
            ];
        }

        return $out;
    }

    /**
     * Alte Einlösungen dieses Belegs zurückbuchen, neue setzen (in bestehender TX aufrufen).
     *
     * @param list<array{gift_voucher_id: int, amount_gross: float}> $rows
     */
    public static function syncRedemptionsForVoucher(int $voucherId, array $rows): float
    {
        self::ensureTables();
        if ($voucherId < 1) {
            return 0.0;
        }
        $pdo = Database::pdo();

        $prev = $pdo->prepare(
            'SELECT id, gift_voucher_id, amount_gross FROM dg_voucher_gift_redemptions WHERE voucher_id = :vid'
        );
        $prev->execute(['vid' => $voucherId]);
        $previous = $prev->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($previous as $old) {
            $giftId = (int) ($old['gift_voucher_id'] ?? 0);
            $amount = round((float) ($old['amount_gross'] ?? 0), 2);
            if ($giftId > 0 && $amount > 0) {
                self::adjustRemaining($giftId, $amount);
            }
        }
        $pdo->prepare('DELETE FROM dg_voucher_gift_redemptions WHERE voucher_id = :vid')->execute(['vid' => $voucherId]);

        $total = 0.0;
        $ins = $pdo->prepare(
            'INSERT INTO dg_voucher_gift_redemptions (voucher_id, gift_voucher_id, amount_gross)
             VALUES (:voucher_id, :gift_voucher_id, :amount_gross)'
        );
        foreach ($rows as $row) {
            $giftId = (int) ($row['gift_voucher_id'] ?? 0);
            $amount = round(max(0.0, (float) ($row['amount_gross'] ?? 0)), 2);
            if ($giftId < 1 || $amount <= 0) {
                continue;
            }
            $gift = self::findById($giftId);
            if ($gift === null) {
                throw new InvalidArgumentException('Gutschein #' . $giftId . ' nicht gefunden.');
            }
            $validUntil = (string) ($gift['valid_until'] ?? '');
            if ($validUntil !== '' && $validUntil < date('Y-m-d')) {
                throw new InvalidArgumentException('Gutschein ' . $gift['code'] . ' ist abgelaufen.');
            }
            $remaining = round((float) ($gift['remaining_gross'] ?? 0), 2);
            if ($amount > $remaining + 0.009) {
                throw new InvalidArgumentException(
                    'Gutschein ' . $gift['code'] . ': Restwert nur noch '
                    . number_format($remaining, 2, ',', '.') . ' €.'
                );
            }
            $ins->execute([
                'voucher_id' => $voucherId,
                'gift_voucher_id' => $giftId,
                'amount_gross' => $amount,
            ]);
            self::adjustRemaining($giftId, -$amount);
            $total += $amount;
        }

        return round($total, 2);
    }

    private static function adjustRemaining(int $giftId, float $delta): void
    {
        $gift = self::findById($giftId);
        if ($gift === null) {
            return;
        }
        $face = round((float) ($gift['face_value_gross'] ?? 0), 2);
        $remaining = round((float) ($gift['remaining_gross'] ?? 0) + $delta, 2);
        if ($remaining < 0) {
            $remaining = 0.0;
        }
        if ($remaining > $face) {
            $remaining = $face;
        }
        $status = 'active';
        if ($remaining <= 0.0) {
            $status = 'redeemed';
        }
        $validUntil = (string) ($gift['valid_until'] ?? '');
        if ($status === 'active' && $validUntil !== '' && $validUntil < date('Y-m-d')) {
            $status = 'expired';
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_gift_vouchers SET remaining_gross = :remaining, status = :status WHERE id = :id'
        );
        $stmt->execute([
            'remaining' => $remaining,
            'status' => $status,
            'id' => $giftId,
        ]);
    }

    public static function normalizeCode(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = preg_replace('/\s+/', '', $code) ?? '';

        return mb_substr($code, 0, 64);
    }

    public static function generateCode(): string
    {
        try {
            $hex = strtoupper(bin2hex(random_bytes(4)));
        } catch (Throwable) {
            $hex = strtoupper(substr(md5((string) microtime(true)), 0, 8));
        }

        return 'GS-' . $hex;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function normalizeRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'code' => (string) ($row['code'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'contact_id' => isset($row['contact_id']) && $row['contact_id'] !== null ? (int) $row['contact_id'] : null,
            'face_value_gross' => round((float) ($row['face_value_gross'] ?? 0), 2),
            'remaining_gross' => round((float) ($row['remaining_gross'] ?? 0), 2),
            'currency' => (string) ($row['currency'] ?? 'EUR'),
            'status' => (string) ($row['status'] ?? 'active'),
            'valid_until' => $row['valid_until'] !== null ? (string) $row['valid_until'] : null,
            'issued_voucher_id' => isset($row['issued_voucher_id']) && $row['issued_voucher_id'] !== null
                ? (int) $row['issued_voucher_id'] : null,
            'notes' => (string) ($row['notes'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'label' => trim((string) ($row['code'] ?? '') . ' — ' . (string) ($row['title'] ?? '')),
        ];
    }
}
