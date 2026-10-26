<?php
declare(strict_types=1);

/** Reklamationen: Annahme → Beurteilung → Stellungnahme → Maßnahmen. */
final class ComplaintRepository
{
    public const STATUS_INTAKE = 'intake';
    public const STATUS_ASSESSMENT = 'assessment';
    public const STATUS_STATEMENT = 'statement';
    public const STATUS_MEASURES = 'measures';
    public const STATUS_CLOSED = 'closed';

    public const FAULT_UNCLEAR = 'unclear';
    public const FAULT_CUSTOMER = 'customer';
    public const FAULT_COMPANY = 'company';

    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_INTAKE => 'Annahme',
            self::STATUS_ASSESSMENT => 'Beurteilung',
            self::STATUS_STATEMENT => 'Stellungnahme',
            self::STATUS_MEASURES => 'Maßnahmen',
            self::STATUS_CLOSED => 'Abgeschlossen',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function faultOptions(): array
    {
        return [
            self::FAULT_UNCLEAR => 'Noch unklar',
            self::FAULT_CUSTOMER => 'Beim Kunden',
            self::FAULT_COMPANY => 'Bei uns',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listAll(int $limit = 200): array
    {
        self::ensureReady();
        if (!Database::isConfigured()) {
            return [];
        }
        $stmt = Database::pdo()->query(
            'SELECT * FROM dg_complaints ORDER BY id DESC LIMIT ' . max(1, min(500, $limit))
        );
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        self::ensureReady();
        if (!Database::isConfigured() || $id < 1) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_complaints WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function events(int $complaintId): array
    {
        self::ensureReady();
        if (!Database::isConfigured() || $complaintId < 1) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_complaint_events WHERE complaint_id = :id ORDER BY id ASC'
        );
        $stmt->execute(['id' => $complaintId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function emptyForm(): array
    {
        return [
            'status' => self::STATUS_INTAKE,
            'fault' => self::FAULT_UNCLEAR,
            'contact_id' => '',
            'contact_name' => '',
            'source_voucher_id' => '',
            'article_id' => '',
            'article_title' => '',
            'warranty_kind' => 'none',
            'warranty_months' => '0',
            'warranty_text' => '',
            'subject' => '',
            'intake_text' => '',
            'assessment_text' => '',
            'statement_text' => '',
            'measures_text' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function formForId(int $id): array
    {
        $row = self::find($id);
        if ($row === null) {
            return self::emptyForm();
        }

        return [
            'status' => (string) ($row['status'] ?? self::STATUS_INTAKE),
            'fault' => (string) ($row['fault'] ?? self::FAULT_UNCLEAR),
            'contact_id' => (string) ($row['contact_id'] ?? ''),
            'contact_name' => (string) ($row['contact_name'] ?? ''),
            'source_voucher_id' => (string) ($row['source_voucher_id'] ?? ''),
            'article_id' => (string) ($row['article_id'] ?? ''),
            'article_title' => (string) ($row['article_title'] ?? ''),
            'warranty_kind' => (string) ($row['warranty_kind'] ?? 'none'),
            'warranty_months' => (string) ($row['warranty_months'] ?? '0'),
            'warranty_text' => (string) ($row['warranty_text'] ?? ''),
            'subject' => (string) ($row['subject'] ?? ''),
            'intake_text' => (string) ($row['intake_text'] ?? ''),
            'assessment_text' => (string) ($row['assessment_text'] ?? ''),
            'statement_text' => (string) ($row['statement_text'] ?? ''),
            'measures_text' => (string) ($row['measures_text'] ?? ''),
            'apology_gift_voucher_id' => (int) ($row['apology_gift_voucher_id'] ?? 0),
            'complaint_number' => (string) ($row['complaint_number'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function save(array $input, ?int $id = null, ?int $userId = null): int
    {
        self::ensureReady();
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht konfiguriert.');
        }

        $status = self::sanitizeStatus((string) ($input['status'] ?? self::STATUS_INTAKE));
        $fault = self::sanitizeFault((string) ($input['fault'] ?? self::FAULT_UNCLEAR));
        $subject = trim((string) ($input['subject'] ?? ''));
        if ($subject === '') {
            throw new InvalidArgumentException('Betreff ist erforderlich.');
        }

        $articleId = max(0, (int) ($input['article_id'] ?? 0));
        $warrantyKind = 'none';
        $warrantyMonths = 0;
        $warrantyText = '';
        $articleTitle = trim((string) ($input['article_title'] ?? ''));
        if ($articleId > 0) {
            $article = CalendarArticleRepository::findById($articleId);
            if (is_array($article)) {
                $articleTitle = $articleTitle !== '' ? $articleTitle : (string) ($article['title'] ?? '');
                $warrantyKind = ArticleWarranty::sanitizeKind((string) ($article['warranty_kind'] ?? 'none'));
                $warrantyMonths = max(0, (int) ($article['warranty_months'] ?? 0));
                $warrantyText = (string) ($article['warranty_text'] ?? '');
            }
        } else {
            $warrantyKind = ArticleWarranty::sanitizeKind((string) ($input['warranty_kind'] ?? 'none'));
            $warrantyMonths = max(0, (int) ($input['warranty_months'] ?? 0));
            $warrantyText = trim((string) ($input['warranty_text'] ?? ''));
        }

        $fields = [
            'status' => $status,
            'fault' => $fault,
            'contact_id' => max(0, (int) ($input['contact_id'] ?? 0)),
            'contact_name' => mb_substr(trim((string) ($input['contact_name'] ?? '')), 0, 255),
            'source_voucher_id' => max(0, (int) ($input['source_voucher_id'] ?? 0)),
            'article_id' => $articleId,
            'article_title' => mb_substr($articleTitle, 0, 255),
            'warranty_kind' => $warrantyKind,
            'warranty_months' => $warrantyMonths,
            'warranty_text' => $warrantyText,
            'subject' => mb_substr($subject, 0, 255),
            'intake_text' => trim((string) ($input['intake_text'] ?? '')),
            'assessment_text' => trim((string) ($input['assessment_text'] ?? '')),
            'statement_text' => trim((string) ($input['statement_text'] ?? '')),
            'measures_text' => trim((string) ($input['measures_text'] ?? '')),
            'closed_at' => $status === self::STATUS_CLOSED ? date('Y-m-d H:i:s') : null,
        ];

        $pdo = Database::pdo();
        if ($id !== null && $id > 0) {
            $existing = self::find($id);
            if ($existing === null) {
                throw new InvalidArgumentException('Reklamation nicht gefunden.');
            }
            $fields['id'] = $id;
            $stmt = $pdo->prepare(
                'UPDATE dg_complaints SET
                    status = :status, fault = :fault, contact_id = :contact_id, contact_name = :contact_name,
                    source_voucher_id = :source_voucher_id, article_id = :article_id, article_title = :article_title,
                    warranty_kind = :warranty_kind, warranty_months = :warranty_months, warranty_text = :warranty_text,
                    subject = :subject, intake_text = :intake_text, assessment_text = :assessment_text,
                    statement_text = :statement_text, measures_text = :measures_text, closed_at = :closed_at
                 WHERE id = :id'
            );
            $stmt->execute($fields);
            if ((string) ($existing['status'] ?? '') !== $status) {
                self::addEvent($id, 'status', 'Status: ' . (self::statusOptions()[$status] ?? $status), $userId);
            }

            return $id;
        }

        $number = self::nextNumber();
        $stmt = $pdo->prepare(
            'INSERT INTO dg_complaints (
                complaint_number, status, fault, contact_id, contact_name, source_voucher_id,
                article_id, article_title, warranty_kind, warranty_months, warranty_text,
                subject, intake_text, assessment_text, statement_text, measures_text, created_by, closed_at
            ) VALUES (
                :complaint_number, :status, :fault, :contact_id, :contact_name, :source_voucher_id,
                :article_id, :article_title, :warranty_kind, :warranty_months, :warranty_text,
                :subject, :intake_text, :assessment_text, :statement_text, :measures_text, :created_by, :closed_at
            )'
        );
        $stmt->execute([
            'complaint_number' => $number,
            'status' => $fields['status'],
            'fault' => $fields['fault'],
            'contact_id' => $fields['contact_id'],
            'contact_name' => $fields['contact_name'],
            'source_voucher_id' => $fields['source_voucher_id'],
            'article_id' => $fields['article_id'],
            'article_title' => $fields['article_title'],
            'warranty_kind' => $fields['warranty_kind'],
            'warranty_months' => $fields['warranty_months'],
            'warranty_text' => $fields['warranty_text'],
            'subject' => $fields['subject'],
            'intake_text' => $fields['intake_text'],
            'assessment_text' => $fields['assessment_text'],
            'statement_text' => $fields['statement_text'],
            'measures_text' => $fields['measures_text'],
            'created_by' => $userId,
            'closed_at' => $fields['closed_at'],
        ]);
        $newId = (int) $pdo->lastInsertId();
        self::addEvent($newId, 'intake', 'Reklamation angelegt', $userId);

        return $newId;
    }

    public static function addEvent(int $complaintId, string $kind, string $body, ?int $userId = null): void
    {
        self::ensureReady();
        if (!Database::isConfigured() || $complaintId < 1) {
            return;
        }
        Database::pdo()->prepare(
            'INSERT INTO dg_complaint_events (complaint_id, event_kind, body, created_by)
             VALUES (:complaint_id, :event_kind, :body, :created_by)'
        )->execute([
            'complaint_id' => $complaintId,
            'event_kind' => mb_substr(trim($kind), 0, 32),
            'body' => $body,
            'created_by' => $userId,
        ]);
    }

    /**
     * Entschuldigungs-Gutschein (Garantieleistung) ausstellen.
     */
    public static function issueApologyVoucher(int $complaintId, float $amount, ?int $userId = null): int
    {
        $row = self::find($complaintId);
        if ($row === null) {
            throw new InvalidArgumentException('Reklamation nicht gefunden.');
        }
        if ((string) ($row['fault'] ?? '') !== self::FAULT_COMPANY) {
            throw new InvalidArgumentException('Entschuldigungs-Gutschein nur bei Schuld „Bei uns“.');
        }
        if ((int) ($row['apology_gift_voucher_id'] ?? 0) > 0) {
            throw new InvalidArgumentException('Für diese Reklamation existiert bereits ein Entschuldigungs-Gutschein.');
        }
        $amount = round(max(0.01, $amount), 2);
        $settings = GiftVoucherSettings::get();
        $giftId = GiftVoucherRepository::create([
            'voucher_type' => GiftVoucherRepository::TYPE_APOLOGY,
            'amount' => $amount,
            'contact_id' => (int) ($row['contact_id'] ?? 0),
            'complaint_id' => $complaintId,
            'title' => 'Entschuldigung zu ' . (string) ($row['complaint_number'] ?? ('#' . $complaintId)),
            'notes' => 'Garantieleistung aus Reklamation — Aufwandskonto ' . $settings['expense_account'],
            'expense_account' => $settings['expense_account'],
            'liability_account' => $settings['liability_account'],
        ], $userId);

        Database::pdo()->prepare(
            'UPDATE dg_complaints SET apology_gift_voucher_id = :gid WHERE id = :id'
        )->execute(['gid' => $giftId, 'id' => $complaintId]);

        $gift = GiftVoucherRepository::find($giftId);
        self::addEvent(
            $complaintId,
            'apology',
            'Entschuldigungs-Gutschein ' . (string) ($gift['code'] ?? '') . ' über '
            . number_format($amount, 2, ',', '.') . ' € (Garantieleistung)',
            $userId
        );

        return $giftId;
    }

    private static function nextNumber(): string
    {
        $year = date('Y');
        $stmt = Database::pdo()->query(
            "SELECT COUNT(*) FROM dg_complaints WHERE complaint_number LIKE 'RK-{$year}-%'"
        );
        $n = $stmt ? ((int) $stmt->fetchColumn() + 1) : 1;

        return sprintf('RK-%s-%04d', $year, $n);
    }

    public static function sanitizeStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return isset(self::statusOptions()[$status]) ? $status : self::STATUS_INTAKE;
    }

    public static function sanitizeFault(string $fault): string
    {
        $fault = strtolower(trim($fault));

        return isset(self::faultOptions()[$fault]) ? $fault : self::FAULT_UNCLEAR;
    }
}
