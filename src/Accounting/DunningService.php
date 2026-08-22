<?php
declare(strict_types=1);

/** Mahnwesen: automatischer Versand und Mahnkosten. */
final class DunningService
{
    /**
     * @return array{sent: int, skipped: int, errors: list<string>}
     */
    public static function runAutomatic(): array
    {
        $config = AccountingPaymentSettings::dunningConfig();
        if (empty($config['auto_send'])) {
            return ['sent' => 0, 'skipped' => 0, 'errors' => []];
        }

        if (!MailSettings::isConfigured()) {
            return ['sent' => 0, 'skipped' => 0, 'errors' => ['SMTP nicht konfiguriert.']];
        }

        $result = ['sent' => 0, 'skipped' => 0, 'errors' => []];
        foreach (self::candidates() as $candidate) {
            $voucherId = (int) ($candidate['voucher_id'] ?? 0);
            $nextLevel = (int) ($candidate['next_level'] ?? 0);
            if ($voucherId < 1 || $nextLevel < 1) {
                $result['skipped']++;
                continue;
            }
            try {
                self::sendLevel($voucherId, $nextLevel, null);
                $result['sent']++;
            } catch (Throwable $e) {
                $result['errors'][] = 'Beleg #' . $voucherId . ': ' . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * @return list<array{voucher_id: int, next_level: int, days_overdue: int}>
     */
    public static function candidates(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();

        $config = AccountingPaymentSettings::dunningConfig();
        $levels = is_array($config['levels'] ?? null) ? $config['levels'] : [];
        if ($levels === []) {
            return [];
        }

        $pdo = Database::pdo();
        $sql = "SELECT v.*, c.email AS contact_email, c.email2 AS contact_email2
                FROM dg_vouchers v
                LEFT JOIN dg_contacts c ON c.id = v.contact_id
                WHERE v.is_draft = 0
                  AND v.voucher_type = 'income'
                  AND v.payment_status IN ('open', 'partial', 'direct_debit')
                  AND v.payment_due_date IS NOT NULL";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $today = date('Y-m-d');
        $out = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!VoucherDocumentKind::isBookable((string) ($row['document_kind'] ?? ''), 'income')) {
                continue;
            }
            $open = self::openAmount($row);
            if ($open <= 0.0) {
                continue;
            }
            $dueDate = (string) ($row['payment_due_date'] ?? '');
            if ($dueDate === '') {
                continue;
            }
            $daysOverdue = PaymentTermsService::daysOverdue($dueDate, $today);
            if ($daysOverdue <= 0) {
                continue;
            }
            $currentLevel = (int) ($row['dunning_level'] ?? 0);
            $nextLevelIndex = $currentLevel;
            if ($nextLevelIndex >= count($levels)) {
                continue;
            }
            $levelConfig = $levels[$nextLevelIndex];
            $requiredDays = (int) ($levelConfig['days_after_due'] ?? 0);
            if ($daysOverdue < $requiredDays) {
                continue;
            }
            $email = self::recipientFromRow($row);
            if ($email === '') {
                continue;
            }

            $out[] = [
                'voucher_id' => (int) ($row['id'] ?? 0),
                'next_level' => $nextLevelIndex + 1,
                'days_overdue' => $daysOverdue,
            ];
        }

        return $out;
    }

    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public static function sendLevel(int $voucherId, int $levelNumber, ?User $actor): void
    {
        if ($voucherId < 1) {
            throw new InvalidArgumentException('Beleg nicht gefunden.');
        }
        $voucher = VoucherRepository::findById($voucherId);
        if ($voucher === null) {
            throw new InvalidArgumentException('Beleg nicht gefunden.');
        }
        if (VoucherRepository::normalizeVoucherType((string) ($voucher['voucher_type'] ?? '')) !== 'income') {
            throw new InvalidArgumentException('Mahnungen sind nur für Einnahmen-Belege möglich.');
        }
        if (!in_array(
            VoucherPaymentStatus::sanitize((string) ($voucher['payment_status'] ?? '')),
            [VoucherPaymentStatus::OPEN, VoucherPaymentStatus::PARTIAL, VoucherPaymentStatus::DIRECT_DEBIT],
            true
        )) {
            throw new InvalidArgumentException('Beleg ist bereits ausgeglichen.');
        }

        $config = AccountingPaymentSettings::dunningConfig();
        $levels = is_array($config['levels'] ?? null) ? $config['levels'] : [];
        $levelIndex = $levelNumber - 1;
        if ($levelIndex < 0 || $levelIndex >= count($levels)) {
            throw new InvalidArgumentException('Ungültige Mahnstufe.');
        }
        $level = $levels[$levelIndex];
        $currentLevel = (int) ($voucher['dunning_level'] ?? 0);
        if ($levelNumber <= $currentLevel) {
            throw new InvalidArgumentException('Diese Mahnstufe wurde bereits versendet.');
        }
        if ($levelNumber !== $currentLevel + 1) {
            throw new InvalidArgumentException('Bitte zuerst die vorherige Mahnstufe senden.');
        }

        $contactId = (int) ($voucher['contact_id'] ?? 0);
        $to = VoucherDocumentMailService::defaultRecipient($contactId);
        if ($to === '') {
            throw new InvalidArgumentException('Keine E-Mail-Adresse beim Kontakt hinterlegt.');
        }

        $feeAmount = round((float) ($level['fee_amount'] ?? 0), 2);
        $openBefore = self::openAmount($voucher);
        $tokens = self::buildTokens($voucher, $openBefore, $feeAmount);
        $subject = self::replaceTokens((string) ($level['subject'] ?? ''), $tokens);
        $intro = self::replaceTokens((string) ($level['intro'] ?? ''), $tokens);
        if ($subject === '') {
            $subject = (string) ($level['label'] ?? 'Mahnung') . ' — ' . $tokens['{RECHNUNGSNR}'];
        }
        if ($intro === '') {
            $intro = 'Bitte begleichen Sie den offenen Betrag.';
        }

        $introHtml = nl2br(htmlspecialchars($intro, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
        $htmlBody = '<div style="font-family:Segoe UI,Arial,sans-serif;font-size:14px;color:#1c2330;line-height:1.5;">'
            . '<p>' . $introHtml . '</p></div>';

        MailService::send(
            new MailMessage(
                subject: $subject,
                htmlBody: $htmlBody,
                to: [$to],
                textBody: $intro,
                contactId: $contactId > 0 ? $contactId : null,
            ),
            $actor,
        );

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if ($feeAmount > 0) {
                $newGross = round((float) ($voucher['gross_amount'] ?? 0) + $feeAmount, 2);
                $newFeeTotal = round((float) ($voucher['dunning_fee_total'] ?? 0) + $feeAmount, 2);
                $stmt = $pdo->prepare(
                    'UPDATE dg_vouchers SET gross_amount = :gross, dunning_fee_total = :fee_total WHERE id = :id'
                );
                $stmt->execute([
                    'gross' => $newGross,
                    'fee_total' => $newFeeTotal,
                    'id' => $voucherId,
                ]);
            }

            $stmt = $pdo->prepare(
                'UPDATE dg_vouchers SET dunning_level = :level, last_dunning_sent_at = NOW() WHERE id = :id'
            );
            $stmt->execute([
                'level' => $levelNumber,
                'id' => $voucherId,
            ]);

            $stmt = $pdo->prepare(
                'INSERT INTO dg_voucher_dunnings (voucher_id, level, label, fee_amount, recipient_email, sent_at, created_by)
                 VALUES (:voucher_id, :level, :label, :fee_amount, :email, NOW(), :created_by)'
            );
            $stmt->execute([
                'voucher_id' => $voucherId,
                'level' => $levelNumber,
                'label' => (string) ($level['label'] ?? ''),
                'fee_amount' => $feeAmount,
                'email' => $to,
                'created_by' => $actor?->id,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        VoucherRepository::refreshLedger($voucherId);
    }

    /**
     * @param array<string, mixed> $voucher
     */
    public static function openAmount(array $voucher): float
    {
        return VoucherPaymentRepository::openAmount($voucher);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function recipientFromRow(array $row): string
    {
        $email = trim((string) ($row['contact_email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        $email2 = trim((string) ($row['contact_email2'] ?? ''));
        if ($email2 !== '' && filter_var($email2, FILTER_VALIDATE_EMAIL)) {
            return $email2;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $voucher
     * @return array<string, string>
     */
    private static function buildTokens(array $voucher, float $openAmount, float $feeAmount): array
    {
        $company = CompanySettings::forForm();

        return [
            '{RECHNUNGSNR}' => trim((string) ($voucher['invoice_number'] ?? '')),
            '{BELEGDATUM}' => self::formatDateGerman((string) ($voucher['voucher_date'] ?? '')),
            '{FAELLIG}' => self::formatDateGerman((string) ($voucher['payment_due_date'] ?? '')),
            '{OFFEN}' => number_format($openAmount, 2, ',', '.'),
            '{MAHNGEBUEHR}' => number_format($feeAmount, 2, ',', '.'),
            '{FIRMA}' => trim((string) ($company['company_name'] ?? '')),
        ];
    }

  /**
     * @param array<string, string> $tokens
     */
    private static function replaceTokens(string $text, array $tokens): string
    {
        foreach ($tokens as $key => $value) {
            $text = str_replace($key, $value, $text);
        }

        return trim($text);
    }

    private static function formatDateGerman(string $date): string
    {
        $ts = strtotime($date);

        return $ts !== false ? date('d.m.Y', $ts) : $date;
    }
}
