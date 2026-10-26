<?php
declare(strict_types=1);

/**
 * Beleg-Sync: Gutschein-Verkaufszeilen und Einlösungen.
 *
 * Schema (Master): dg_gift_vouchers (face_value_gross, remaining_gross, issued_voucher_id, …),
 * dg_voucher_gift_redemptions (voucher_id, gift_voucher_id, amount_gross),
 * dg_voucher_gift_lines (Migration 118).
 */
final class GiftVoucherService
{
    /**
     * @param array<string, mixed> $data POST-/Formular-Daten
     * @return list<array<string, mixed>>
     */
    public static function parseGiftLines(array $data): array
    {
        $raw = $data['gift_lines'] ?? null;
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        $lineNo = 1;
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $kind = strtolower(trim((string) ($row['line_kind'] ?? 'sale')));
            if ($kind !== 'sale' && $kind !== 'redeem') {
                $kind = 'sale';
            }
            $amount = round((float) str_replace(',', '.', (string) ($row['amount'] ?? 0)), 2);
            $code = strtoupper(trim((string) ($row['gift_code'] ?? '')));
            $giftId = max(0, (int) ($row['gift_voucher_id'] ?? 0));
            $title = trim((string) ($row['title'] ?? ''));
            if ($kind === 'sale') {
                if ($amount <= 0) {
                    continue;
                }
                if ($title === '') {
                    $title = 'Mehrzweck-Gutschein';
                }
            } else {
                if ($code === '' && $giftId < 1) {
                    continue;
                }
                if ($amount <= 0) {
                    continue;
                }
                if ($title === '') {
                    $title = 'Gutschein-Einlösung' . ($code !== '' ? ' ' . $code : '');
                }
            }
            $out[] = [
                'line_no' => $lineNo,
                'line_kind' => $kind,
                'gift_voucher_id' => $giftId,
                'gift_code' => $code,
                'title' => mb_substr($title, 0, 255),
                'amount' => $amount,
            ];
            $lineNo++;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function linesForVoucher(int $voucherId): array
    {
        GiftVoucherRepository::ensureReady();
        if (!Database::isConfigured() || $voucherId < 1) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_voucher_gift_lines WHERE voucher_id = :id ORDER BY line_no ASC, id ASC'
        );
        $stmt->execute(['id' => $voucherId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows ?: [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'line_kind' => (string) ($row['line_kind'] ?? 'sale'),
                'gift_voucher_id' => (string) ($row['gift_voucher_id'] ?? ''),
                'gift_code' => (string) ($row['gift_code'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'amount' => number_format((float) ($row['amount'] ?? 0), 2, ',', ''),
            ];
        }

        return $out;
    }

    /**
     * Speichert Belegzeilen und legt Verkaufs-Gutscheine an / bucht Einlösungen.
     * In bestehender Transaktion des Beleg-Saves aufrufen.
     *
     * @param list<array<string, mixed>> $giftLines
     * @param array<string, mixed> $context
     */
    public static function syncForVoucher(int $voucherId, array $giftLines, array $context = [], ?int $userId = null): void
    {
        GiftVoucherRepository::ensureReady();
        if (!Database::isConfigured() || $voucherId < 1) {
            return;
        }

        $pdo = Database::pdo();

        // Alte Einlösungen dieses Belegs zurückdrehen (Restwert wieder erhöhen)
        self::rollbackRedemptionsForBeleg($voucherId);

        $keepGiftIds = [];
        foreach ($giftLines as $preview) {
            $gid = max(0, (int) ($preview['gift_voucher_id'] ?? 0));
            if ($gid > 0 && (string) ($preview['line_kind'] ?? '') === 'sale') {
                $keepGiftIds[$gid] = true;
            }
        }

        // Verkaufs-Gutscheine dieses Belegs, die nicht mehr in den Zeilen stehen, stornieren (nur ungenutzt)
        $issuedStmt = $pdo->prepare(
            'SELECT id FROM dg_gift_vouchers WHERE issued_voucher_id = :vid AND voucher_type = :typ'
        );
        $issuedStmt->execute(['vid' => $voucherId, 'typ' => GiftVoucherRepository::TYPE_SALE]);
        foreach ($issuedStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $issued) {
            $issuedId = (int) ($issued['id'] ?? 0);
            if ($issuedId > 0 && !isset($keepGiftIds[$issuedId])) {
                try {
                    GiftVoucherRepository::cancel($issuedId);
                } catch (Throwable) {
                    // bereits (teil-)eingelöst: bleibt bestehen
                }
            }
        }

        $pdo->prepare('DELETE FROM dg_voucher_gift_lines WHERE voucher_id = :id')->execute(['id' => $voucherId]);

        if ($giftLines === []) {
            return;
        }

        $contactId = max(0, (int) ($context['contact_id'] ?? 0));
        $insert = $pdo->prepare(
            'INSERT INTO dg_voucher_gift_lines (
                voucher_id, line_no, line_kind, gift_voucher_id, gift_code, title, amount
            ) VALUES (
                :voucher_id, :line_no, :line_kind, :gift_voucher_id, :gift_code, :title, :amount
            )'
        );

        foreach ($giftLines as $line) {
            $kind = (string) ($line['line_kind'] ?? 'sale');
            $amount = round((float) ($line['amount'] ?? 0), 2);
            $giftId = max(0, (int) ($line['gift_voucher_id'] ?? 0));
            $code = strtoupper(trim((string) ($line['gift_code'] ?? '')));
            $title = (string) ($line['title'] ?? '');

            if ($kind === 'sale') {
                $existing = $giftId > 0 ? GiftVoucherRepository::find($giftId) : null;
                if ($existing !== null) {
                    $code = (string) ($existing['code'] ?? $code);
                } else {
                    $giftId = GiftVoucherRepository::create([
                        'voucher_type' => GiftVoucherRepository::TYPE_SALE,
                        'amount' => $amount,
                        'contact_id' => $contactId,
                        'issued_voucher_id' => $voucherId,
                        'title' => $title !== '' ? $title : 'Mehrzweck-Gutschein',
                        'notes' => 'Verkauf über Beleg #' . $voucherId . ' (ohne USt, Verbindlichkeit)',
                    ], $userId);
                    $created = GiftVoucherRepository::find($giftId);
                    $code = (string) ($created['code'] ?? $code);
                }
            } elseif ($kind === 'redeem') {
                $gift = $giftId > 0 ? GiftVoucherRepository::find($giftId) : GiftVoucherRepository::findByCode($code);
                if ($gift === null) {
                    throw new InvalidArgumentException(
                        'Gutschein zum Einlösen nicht gefunden' . ($code !== '' ? ': ' . $code : '') . '.'
                    );
                }
                if ((string) ($gift['status'] ?? '') !== GiftVoucherRepository::STATUS_ACTIVE) {
                    throw new InvalidArgumentException('Gutschein ist nicht aktiv: ' . (string) ($gift['code'] ?? ''));
                }
                $validUntil = (string) ($gift['valid_until'] ?? '');
                if ($validUntil !== '' && $validUntil < date('Y-m-d')) {
                    throw new InvalidArgumentException('Gutschein ' . (string) ($gift['code'] ?? '') . ' ist abgelaufen.');
                }
                $remaining = round((float) ($gift['remaining_gross'] ?? 0), 2);
                if ($amount > $remaining + 0.001) {
                    throw new InvalidArgumentException(
                        'Einlösebetrag übersteigt Restwert von ' . number_format($remaining, 2, ',', '.') . ' €.'
                    );
                }
                $giftId = (int) $gift['id'];
                $code = (string) ($gift['code'] ?? $code);
                self::applyRedemption($giftId, $voucherId, $amount);
            }

            $insert->execute([
                'voucher_id' => $voucherId,
                'line_no' => (int) ($line['line_no'] ?? 1),
                'line_kind' => $kind,
                'gift_voucher_id' => $giftId,
                'gift_code' => $code,
                'title' => $title,
                'amount' => $amount,
            ]);
        }
    }

    /**
     * Buchungszeilen für Gutschein-Verkauf (Verbindlichkeit, 0 % USt).
     *
     * @param list<array<string, mixed>> $giftLines
     * @return list<array<string, mixed>>
     */
    public static function bookingLinesForSales(array $giftLines): array
    {
        $settings = GiftVoucherSettings::get();
        $out = [];
        foreach ($giftLines as $line) {
            if ((string) ($line['line_kind'] ?? '') !== 'sale') {
                continue;
            }
            $amount = round((float) ($line['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }
            $out[] = [
                'account_number' => $settings['liability_account'],
                'description' => (string) ($line['title'] ?? 'Mehrzweck-Gutschein'),
                'gross_amount' => $amount,
                'tax_rate' => 0,
                'tax_type' => 'ust0',
                'tax_key' => '',
                'line_kind' => 'gift_sale',
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $giftLines
     */
    public static function redeemTotal(array $giftLines): float
    {
        $sum = 0.0;
        foreach ($giftLines as $line) {
            if ((string) ($line['line_kind'] ?? '') === 'redeem') {
                $sum += round((float) ($line['amount'] ?? 0), 2);
            }
        }

        return round($sum, 2);
    }

    /**
     * @param list<array<string, mixed>> $giftLines
     */
    public static function saleTotal(array $giftLines): float
    {
        $sum = 0.0;
        foreach ($giftLines as $line) {
            if ((string) ($line['line_kind'] ?? '') === 'sale') {
                $sum += round((float) ($line['amount'] ?? 0), 2);
            }
        }

        return round($sum, 2);
    }

    /**
     * Einlösung buchen: Eintrag in dg_voucher_gift_redemptions + remaining_gross reduzieren (inkl. Status).
     */
    private static function applyRedemption(int $giftId, int $belegId, float $amount): void
    {
        $pdo = Database::pdo();
        // UNIQUE (voucher_id, gift_voucher_id): mehrere Zeilen zum selben Gutschein im selben Beleg summieren.
        $pdo->prepare(
            'INSERT INTO dg_voucher_gift_redemptions (voucher_id, gift_voucher_id, amount_gross)
             VALUES (:voucher_id, :gift_voucher_id, :amount_gross)
             ON DUPLICATE KEY UPDATE amount_gross = amount_gross + VALUES(amount_gross)'
        )->execute([
            'voucher_id' => $belegId,
            'gift_voucher_id' => $giftId,
            'amount_gross' => $amount,
        ]);

        GiftVoucherRepository::adjustRemaining($giftId, -$amount);
    }

    private static function rollbackRedemptionsForBeleg(int $belegId): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, gift_voucher_id, amount_gross FROM dg_voucher_gift_redemptions WHERE voucher_id = :id'
        );
        $stmt->execute(['id' => $belegId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $giftId = (int) ($row['gift_voucher_id'] ?? 0);
            $amount = round((float) ($row['amount_gross'] ?? 0), 2);
            if ($giftId > 0 && $amount > 0) {
                GiftVoucherRepository::adjustRemaining($giftId, $amount);
            }
        }
        $pdo->prepare('DELETE FROM dg_voucher_gift_redemptions WHERE voucher_id = :id')
            ->execute(['id' => $belegId]);
    }
}
