<?php

declare(strict_types=1);

/**
 * Gutscheine (Stamm) — Geschenk-/Mehrzweckgutscheine und Entschuldigungs-Gutscheine —
 * und Einlösung auf Belegen (dg_voucher_gift_redemptions).
 *
 * Schema: Migration 114 (+118 Typ/Konten, +121 flyer_template_id).
 * Zeilen enthalten Aliase für Cloud-Views: amount = face_value_gross, remaining = remaining_gross,
 * issue_voucher_id = issued_voucher_id.
 */
final class GiftVoucherRepository
{
    public const TYPE_SALE = 'sale';
    public const TYPE_APOLOGY = 'apology';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REDEEMED = 'redeemed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public static function ensureTables(): void
    {
        MigrationRunner::runPending();
    }

    /** Alias für Cloud-Code. */
    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        self::ensureTables();
        FlyerService::ensureReady();
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_SALE => 'Geschenk (Verkauf)',
            self::TYPE_APOLOGY => 'Entschuldigung (Garantieleistung)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => 'Aktiv',
            self::STATUS_REDEEMED => 'Vollständig eingelöst',
            self::STATUS_CANCELLED => 'Storniert',
            self::STATUS_EXPIRED => 'Verfallen',
        ];
    }

    public static function sanitizeType(string $type): string
    {
        $type = strtolower(trim($type));

        return isset(self::typeOptions()[$type]) ? $type : self::TYPE_SALE;
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
     * Master: listAll(300) · Cloud: listAll('active', 200).
     *
     * @param int|string|null $statusOrLimit int = Limit (Master-Aufruf), string = Status-Filter
     * @return list<array<string, mixed>>
     */
    public static function listAll(int|string|null $statusOrLimit = null, int $limit = 200): array
    {
        self::ensureTables();
        if (!Database::isConfigured()) {
            return [];
        }
        $status = null;
        if (is_int($statusOrLimit)) {
            $limit = $statusOrLimit;
        } elseif (is_string($statusOrLimit) && trim($statusOrLimit) !== '') {
            $status = trim($statusOrLimit);
        }
        $limit = max(1, min(500, $limit));

        $sql = 'SELECT * FROM dg_gift_vouchers';
        $params = [];
        if ($status !== null) {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . $limit;
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[] = self::normalizeRow($row);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
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

    /**
     * Alias für Cloud-Code.
     *
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        return self::findById($id);
    }

    /**
     * @return array<string, mixed>|null
     */
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
     * Legt einen Gutschein an und liefert die neue ID (Cloud-Kompatibilität; Master-Aufrufer ignorieren den Rückgabewert).
     *
     * Eingabe: face_value_gross ODER amount; optional remaining_gross, voucher_type, complaint_id,
     * issued_voucher_id (Alias issue_voucher_id), liability_account, expense_account, contact_id, code, title,
     * valid_until, notes. Ist der Schlüssel valid_until nicht gesetzt, gilt GiftVoucherSettings::default_valid_days;
     * ein leerer String bedeutet bewusst „ohne Ablaufdatum“.
     *
     * @param array<string, mixed> $input
     */
    public static function create(array $input, ?int $userId = null): int
    {
        // ensureReady: Migrationen inkl. flyer_template_id + Flyer-Vorlagen
        self::ensureReady();
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht konfiguriert.');
        }

        $settings = GiftVoucherSettings::get();
        $type = self::sanitizeType((string) ($input['voucher_type'] ?? self::TYPE_SALE));

        $faceRaw = $input['face_value_gross'] ?? $input['amount'] ?? 0;
        $face = round(max(0.0, VoucherRepository::parseMoney($faceRaw)), 2);
        if ($face <= 0.0) {
            throw new InvalidArgumentException('Nennwert muss größer als 0 sein.');
        }
        $remaining = array_key_exists('remaining_gross', $input)
            ? round(max(0.0, VoucherRepository::parseMoney($input['remaining_gross'])), 2)
            : $face;
        if ($remaining > $face) {
            $remaining = $face;
        }

        $code = self::normalizeCode((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = self::generateCode();
        }
        if (self::findByCode($code) !== null) {
            throw new InvalidArgumentException('Gutschein-Code ist bereits vergeben.');
        }

        if (array_key_exists('valid_until', $input)) {
            $validUntil = trim((string) $input['valid_until']);
        } else {
            $validUntil = $settings['default_valid_days'] > 0
                ? date('Y-m-d', strtotime('+' . $settings['default_valid_days'] . ' days'))
                : '';
        }
        if ($validUntil !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validUntil)) {
            throw new InvalidArgumentException('Ungültiges Gültig-bis-Datum.');
        }

        $liability = preg_replace('/\D/', '', (string) ($input['liability_account'] ?? $settings['liability_account'])) ?? '';
        if ($liability === '') {
            $liability = $settings['liability_account'];
        }
        $expense = '';
        if ($type === self::TYPE_APOLOGY) {
            $expense = preg_replace('/\D/', '', (string) ($input['expense_account'] ?? $settings['expense_account'])) ?? '';
            if ($expense === '') {
                $expense = $settings['expense_account'];
            }
        }

        $contactId = max(0, (int) ($input['contact_id'] ?? 0));
        $complaintId = max(0, (int) ($input['complaint_id'] ?? 0));
        $issuedVoucherId = max(0, (int) ($input['issued_voucher_id'] ?? $input['issue_voucher_id'] ?? 0));

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            $title = $type === self::TYPE_APOLOGY ? 'Entschuldigungs-Gutschein' : 'Geschenkgutschein';
        }
        $notes = trim((string) ($input['notes'] ?? ''));
        $flyerTemplateId = max(0, (int) ($input['flyer_template_id'] ?? 0));
        if ($flyerTemplateId > 0) {
            $tpl = FlyerService::findTemplate($flyerTemplateId);
            if ($tpl === null || (string) ($tpl['purpose'] ?? '') !== FlyerService::PURPOSE_GIFT_FORM) {
                throw new InvalidArgumentException('Ungültige Flyer-Vorlage.');
            }
            if ((int) ($tpl['is_active'] ?? 0) !== 1) {
                throw new InvalidArgumentException('Flyer-Vorlage ist nicht aktiv.');
            }
        } else {
            $flyerTemplateId = FlyerService::defaultGiftTemplateId();
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_gift_vouchers (
                code, voucher_type, title, contact_id, complaint_id, face_value_gross, remaining_gross,
                status, valid_until, issued_voucher_id, liability_account, expense_account, notes, flyer_template_id, created_by
            ) VALUES (
                :code, :voucher_type, :title, :contact_id, :complaint_id, :face, :remaining,
                :status, :valid_until, :issued_voucher_id, :liability_account, :expense_account, :notes, :flyer_template_id, :created_by
            )'
        );
        $stmt->execute([
            'code' => $code,
            'voucher_type' => $type,
            'title' => mb_substr($title, 0, 191),
            'contact_id' => $contactId > 0 ? $contactId : null,
            'complaint_id' => $complaintId > 0 ? $complaintId : null,
            'face' => $face,
            'remaining' => $remaining,
            'status' => $remaining <= 0.0 ? self::STATUS_REDEEMED : self::STATUS_ACTIVE,
            'valid_until' => $validUntil !== '' ? $validUntil : null,
            'issued_voucher_id' => $issuedVoucherId > 0 ? $issuedVoucherId : null,
            'liability_account' => mb_substr($liability, 0, 16),
            'expense_account' => mb_substr($expense, 0, 16),
            'notes' => $notes !== '' ? $notes : null,
            'flyer_template_id' => $flyerTemplateId > 0 ? $flyerTemplateId : null,
            'created_by' => $userId !== null && $userId > 0 ? $userId : null,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * Stornieren (nur unberührte Gutscheine).
     */
    public static function cancel(int $id): void
    {
        $row = self::findById($id);
        if ($row === null) {
            throw new InvalidArgumentException('Gutschein nicht gefunden.');
        }
        if ((float) ($row['remaining_gross'] ?? 0) < (float) ($row['face_value_gross'] ?? 0) - 0.001) {
            throw new InvalidArgumentException('Bereits teilweise eingelöste Gutscheine können nicht storniert werden.');
        }
        Database::pdo()->prepare(
            'UPDATE dg_gift_vouchers SET status = :status, remaining_gross = 0 WHERE id = :id'
        )->execute(['status' => self::STATUS_CANCELLED, 'id' => $id]);
    }

    /**
     * Status aus Restwert / Ablaufdatum neu bestimmen (stornierte bleiben storniert).
     */
    public static function refreshStatus(int $id): void
    {
        $row = self::findById($id);
        if ($row === null || (string) ($row['status'] ?? '') === self::STATUS_CANCELLED) {
            return;
        }
        $remaining = round((float) ($row['remaining_gross'] ?? 0), 2);
        $status = $remaining <= 0.001 ? self::STATUS_REDEEMED : self::STATUS_ACTIVE;
        $validUntil = (string) ($row['valid_until'] ?? '');
        if ($status === self::STATUS_ACTIVE && $validUntil !== '' && $validUntil < date('Y-m-d')) {
            $status = self::STATUS_EXPIRED;
        }
        Database::pdo()->prepare('UPDATE dg_gift_vouchers SET status = :status WHERE id = :id')
            ->execute(['status' => $status, 'id' => $id]);
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

    /**
     * Restwert ändern (delta < 0 = Einlösung, > 0 = Rückbuchung) und Status nachziehen.
     */
    public static function adjustRemaining(int $giftId, float $delta): void
    {
        $gift = self::findById($giftId);
        if ($gift === null) {
            return;
        }
        if ((string) ($gift['status'] ?? '') === self::STATUS_CANCELLED) {
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
        $status = self::STATUS_ACTIVE;
        if ($remaining <= 0.0) {
            $status = self::STATUS_REDEEMED;
        }
        $validUntil = (string) ($gift['valid_until'] ?? '');
        if ($status === self::STATUS_ACTIVE && $validUntil !== '' && $validUntil < date('Y-m-d')) {
            $status = self::STATUS_EXPIRED;
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
        do {
            try {
                $hex = strtoupper(bin2hex(random_bytes(4)));
            } catch (Throwable) {
                $hex = strtoupper(substr(md5((string) microtime(true)), 0, 8));
            }
            $code = 'GS-' . $hex;
        } while (self::findByCode($code) !== null);

        return $code;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalizeRow(array $row): array
    {
        $face = round((float) ($row['face_value_gross'] ?? 0), 2);
        $remaining = round((float) ($row['remaining_gross'] ?? 0), 2);
        $issued = isset($row['issued_voucher_id']) && $row['issued_voucher_id'] !== null
            ? (int) $row['issued_voucher_id'] : null;
        $code = (string) ($row['code'] ?? '');
        $title = (string) ($row['title'] ?? '');
        $fmt = static fn (float $v): string => number_format($v, 2, ',', '.') . ' €';

        return [
            'id' => (int) ($row['id'] ?? 0),
            'code' => $code,
            'voucher_type' => (string) ($row['voucher_type'] ?? self::TYPE_SALE),
            'title' => $title,
            'contact_id' => isset($row['contact_id']) && $row['contact_id'] !== null ? (int) $row['contact_id'] : null,
            'complaint_id' => isset($row['complaint_id']) && $row['complaint_id'] !== null ? (int) $row['complaint_id'] : null,
            'face_value_gross' => $face,
            'remaining_gross' => $remaining,
            // Aliase für Cloud-Views/-Code
            'amount' => $face,
            'remaining' => $remaining,
            'currency' => (string) ($row['currency'] ?? 'EUR'),
            'status' => (string) ($row['status'] ?? self::STATUS_ACTIVE),
            'valid_until' => isset($row['valid_until']) && $row['valid_until'] !== null ? (string) $row['valid_until'] : null,
            'issued_voucher_id' => $issued,
            'issue_voucher_id' => $issued,
            'liability_account' => (string) ($row['liability_account'] ?? ''),
            'expense_account' => (string) ($row['expense_account'] ?? ''),
            'notes' => (string) ($row['notes'] ?? ''),
            'flyer_template_id' => isset($row['flyer_template_id']) && $row['flyer_template_id'] !== null
                ? (int) $row['flyer_template_id'] : null,
            'created_by' => isset($row['created_by']) && $row['created_by'] !== null ? (int) $row['created_by'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'label' => trim($code . ' — ' . $title),
            'remaining_label' => $fmt($remaining),
        ];
    }
}
