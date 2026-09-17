<?php
declare(strict_types=1);

/** Persistenz Einkaufsliste / Ignore (`dg_purchase_list_items`). */
final class PurchaseListRepository
{
    public const REASON_BELOW_MIN = 'below_min';
    public const REASON_MISSING_FOR_VOUCHER = 'missing_for_voucher';
    public const REASON_MANUAL = 'manual';

    public const STATUS_OPEN = 'open';
    public const STATUS_IGNORED = 'ignored';
    public const STATUS_ORDERED = 'ordered';
    public const STATUS_DONE = 'done';

    public static function tableReady(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $ready = Database::pdo()->query("SHOW TABLES LIKE 'dg_purchase_list_items'")->fetchColumn() !== false;
        } catch (Throwable) {
            $ready = false;
        }

        return $ready;
    }

    /** @return array<string, mixed>|null */
    public static function findByArticleId(int $articleId): ?array
    {
        if ($articleId < 1 || !Database::isConfigured() || !self::tableReady()) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_purchase_list_items WHERE article_id = :aid LIMIT 1');
        $stmt->execute(['aid' => $articleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::normalize($row) : null;
    }

    public static function findById(int $id): ?array
    {
        if ($id < 1 || !Database::isConfigured() || !self::tableReady()) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_purchase_list_items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::normalize($row) : null;
    }

    /**
     * Upsert open-Eintrag; ignorierte Artikel bleiben unberührt.
     *
     * @return bool true wenn angelegt/aktualisiert, false wenn ignored oder übersprungen
     */
    public static function upsertOpen(
        int $articleId,
        string $reason,
        float $suggestedQty,
        ?int $sourceVoucherId = null,
        string $note = ''
    ): bool {
        if ($articleId < 1 || !Database::isConfigured() || !self::tableReady()) {
            return false;
        }

        $reason = self::sanitizeReason($reason);
        $suggestedQty = max(0.0, round($suggestedQty, 3));
        $existing = self::findByArticleId($articleId);
        if ($existing !== null) {
            $st = (string) ($existing['status'] ?? '');
            // Ignoriert und bereits nachbestellt (unterwegs) nicht wieder öffnen
            if ($st === self::STATUS_IGNORED || $st === self::STATUS_ORDERED) {
                return false;
            }
        }

        $pdo = Database::pdo();
        if ($existing === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO dg_purchase_list_items
                 (article_id, reason, suggested_qty, source_voucher_id, status, note)
                 VALUES (:article_id, :reason, :suggested_qty, :source_voucher_id, :status, :note)'
            );
            $stmt->execute([
                'article_id' => $articleId,
                'reason' => $reason,
                'suggested_qty' => $suggestedQty,
                'source_voucher_id' => $sourceVoucherId !== null && $sourceVoucherId > 0 ? $sourceVoucherId : null,
                'status' => self::STATUS_OPEN,
                'note' => mb_substr(trim($note), 0, 500),
            ]);

            return true;
        }

        // open / done → wieder open mit aktualisiertem Vorschlag
        $stmt = $pdo->prepare(
            'UPDATE dg_purchase_list_items SET
                reason = :reason,
                suggested_qty = :suggested_qty,
                source_voucher_id = COALESCE(:source_voucher_id, source_voucher_id),
                status = :status,
                ignored_at = NULL,
                ignored_by = NULL,
                note = CASE WHEN :note = \'\' THEN note ELSE :note2 END
             WHERE article_id = :article_id'
        );
        $noteClean = mb_substr(trim($note), 0, 500);
        $stmt->execute([
            'reason' => $reason,
            'suggested_qty' => max($suggestedQty, (float) ($existing['suggested_qty'] ?? 0)),
            'source_voucher_id' => $sourceVoucherId !== null && $sourceVoucherId > 0 ? $sourceVoucherId : null,
            'status' => self::STATUS_OPEN,
            'note' => $noteClean,
            'note2' => $noteClean,
            'article_id' => $articleId,
        ]);

        return true;
    }

    /**
     * Manuell als nachbestellt erfassen (auch ohne Einkaufslisten-Eintrag).
     * Bestehende open/done/ignored/ordered → ordered mit gesetzter Menge.
     */
    public static function upsertOrdered(
        int $articleId,
        float $qty,
        string $note = ''
    ): bool {
        if ($articleId < 1 || !Database::isConfigured() || !self::tableReady()) {
            return false;
        }
        $qty = max(0.0, round($qty, 3));
        if ($qty < 0.0005) {
            return false;
        }

        $existing = self::findByArticleId($articleId);
        $pdo = Database::pdo();
        $noteClean = mb_substr(trim($note), 0, 500);

        if ($existing === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO dg_purchase_list_items
                 (article_id, reason, suggested_qty, source_voucher_id, status, note)
                 VALUES (:article_id, :reason, :suggested_qty, NULL, :status, :note)'
            );
            $stmt->execute([
                'article_id' => $articleId,
                'reason' => self::REASON_MANUAL,
                'suggested_qty' => $qty,
                'status' => self::STATUS_ORDERED,
                'note' => $noteClean,
            ]);

            return true;
        }

        $stmt = $pdo->prepare(
            'UPDATE dg_purchase_list_items SET
                reason = :reason,
                suggested_qty = :suggested_qty,
                status = :status,
                ignored_at = NULL,
                ignored_by = NULL,
                note = CASE WHEN :note = \'\' THEN note ELSE :note2 END
             WHERE article_id = :article_id'
        );
        $stmt->execute([
            'reason' => self::REASON_MANUAL,
            'suggested_qty' => $qty,
            'status' => self::STATUS_ORDERED,
            'note' => $noteClean,
            'note2' => $noteClean,
            'article_id' => $articleId,
        ]);

        return true;
    }

    public static function setStatus(int $id, string $status, ?int $userId = null): void
    {
        if ($id < 1 || !Database::isConfigured() || !self::tableReady()) {
            return;
        }
        $status = self::sanitizeStatus($status);
        $ignoredAt = null;
        $ignoredBy = null;
        if ($status === self::STATUS_IGNORED) {
            $ignoredAt = date('Y-m-d H:i:s');
            $ignoredBy = $userId !== null && $userId > 0 ? $userId : null;
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_purchase_list_items SET
                status = :status,
                ignored_at = :ignored_at,
                ignored_by = :ignored_by
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => $status,
            'ignored_at' => $ignoredAt,
            'ignored_by' => $ignoredBy,
            'id' => $id,
        ]);
    }

    public static function setStatusByArticle(int $articleId, string $status, ?int $userId = null): void
    {
        $row = self::findByArticleId($articleId);
        if ($row === null) {
            return;
        }
        self::setStatus((int) $row['id'], $status, $userId);
    }

    /** Korrigiert die Menge (z. B. tatsächlich bestellt). */
    public static function updateSuggestedQty(int $id, float $qty): bool
    {
        if ($id < 1 || !Database::isConfigured() || !self::tableReady()) {
            return false;
        }
        $qty = max(0.0, round($qty, 3));
        if ($qty < 0.0005) {
            return false;
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_purchase_list_items SET suggested_qty = :qty WHERE id = :id'
        );
        $stmt->execute(['qty' => $qty, 'id' => $id]);

        return $stmt->rowCount() > 0 || self::findById($id) !== null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listByStatus(string $status): array
    {
        if (!Database::isConfigured() || !self::tableReady()) {
            return [];
        }
        $status = self::sanitizeStatus($status);
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_purchase_list_items WHERE status = :status ORDER BY updated_at DESC, id DESC'
        );
        $stmt->execute(['status' => $status]);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $rows[] = self::normalize($row);
        }

        return $rows;
    }

    /** Nachbestellt, Ware noch nicht eingetroffen (status=ordered). */
    public static function onOrderQty(int $articleId): float
    {
        $map = self::onOrderQtyByArticleIds([$articleId]);

        return round((float) ($map[$articleId] ?? 0), 3);
    }

    /**
     * @param list<int> $articleIds
     * @return array<int, float>
     */
    public static function onOrderQtyByArticleIds(array $articleIds): array
    {
        $articleIds = array_values(array_unique(array_filter(array_map('intval', $articleIds))));
        if ($articleIds === [] || !Database::isConfigured() || !self::tableReady()) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($articleIds), '?'));
        $sql = "SELECT article_id, COALESCE(SUM(suggested_qty), 0) AS qty
                FROM dg_purchase_list_items
                WHERE article_id IN ({$placeholders})
                  AND status = ?
                GROUP BY article_id";
        try {
            $stmt = Database::pdo()->prepare($sql);
            $stmt->execute([...$articleIds, self::STATUS_ORDERED]);
        } catch (Throwable) {
            return [];
        }

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $map[(int) ($row['article_id'] ?? 0)] = round((float) ($row['qty'] ?? 0), 3);
        }

        return $map;
    }

    public static function sanitizeReason(string $reason): string
    {
        $reason = strtolower(trim($reason));
        $allowed = [self::REASON_BELOW_MIN, self::REASON_MISSING_FOR_VOUCHER, self::REASON_MANUAL];

        return in_array($reason, $allowed, true) ? $reason : self::REASON_MANUAL;
    }

    public static function sanitizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        $allowed = [self::STATUS_OPEN, self::STATUS_IGNORED, self::STATUS_ORDERED, self::STATUS_DONE];

        return in_array($status, $allowed, true) ? $status : self::STATUS_OPEN;
    }

    public static function reasonLabel(string $reason): string
    {
        return match (self::sanitizeReason($reason)) {
            self::REASON_BELOW_MIN => 'Unterbestand',
            self::REASON_MISSING_FOR_VOUCHER => 'Fehlmenge Beleg',
            default => 'Manuell',
        };
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function normalize(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'article_id' => (int) ($row['article_id'] ?? 0),
            'reason' => self::sanitizeReason((string) ($row['reason'] ?? '')),
            'suggested_qty' => round((float) ($row['suggested_qty'] ?? 0), 3),
            'source_voucher_id' => isset($row['source_voucher_id']) && $row['source_voucher_id'] !== null
                ? (int) $row['source_voucher_id'] : null,
            'status' => self::sanitizeStatus((string) ($row['status'] ?? '')),
            'ignored_at' => $row['ignored_at'] ?? null,
            'ignored_by' => isset($row['ignored_by']) && $row['ignored_by'] !== null
                ? (int) $row['ignored_by'] : null,
            'note' => (string) ($row['note'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}
