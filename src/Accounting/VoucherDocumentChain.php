<?php
declare(strict_types=1);

/** Belegkette: Vorgänger, Folgebelege, Abschlags-Summen, Folgebeleg-Vorlage. */
final class VoucherDocumentChain
{
    /**
     * Alle Belege in derselben Kette (gemeinsame Wurzel), sortiert.
     *
     * @return list<array<string, mixed>>
     */
    public static function chainDocuments(int $voucherId): array
    {
        if (!Database::isConfigured() || $voucherId < 1) {
            return [];
        }

        $rootId = self::findRootId($voucherId);
        if ($rootId < 1) {
            return [];
        }

        $all = self::collectSubtree($rootId);
        usort($all, static function (array $a, array $b): int {
            $order = VoucherDocumentKind::sortOrder((string) ($a['document_kind'] ?? ''))
                <=> VoucherDocumentKind::sortOrder((string) ($b['document_kind'] ?? ''));
            if ($order !== 0) {
                return $order;
            }

            return strcmp((string) ($a['voucher_date'] ?? ''), (string) ($b['voucher_date'] ?? ''))
                ?: ((int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
        });

        return array_map(static fn (array $row): array => self::chainPayload($row), $all);
    }

    /**
     * @return array{documents: list<array<string, mixed>>, current_id: int}
     */
    public static function chainView(int $voucherId): array
    {
        $documents = self::chainDocuments($voucherId);
        foreach ($documents as &$doc) {
            $doc['is_current'] = (int) ($doc['id'] ?? 0) === $voucherId;
        }
        unset($doc);

        return [
            'documents' => $documents,
            'current_id' => $voucherId,
        ];
    }

    /**
     * Abschlagsrechnungen in derselben Kette (für Schlussrechnung).
     *
     * @return list<array<string, mixed>>
     */
    public static function partialInvoicesForFinal(int $voucherId, ?int $excludeId = null): array
    {
        $anchorId = self::chainAnchorId($voucherId);
        if ($anchorId < 1) {
            return [];
        }

        $partials = [];
        foreach (self::collectSubtree($anchorId) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($excludeId !== null && $id === $excludeId) {
                continue;
            }
            if ((string) ($row['document_kind'] ?? '') !== VoucherDocumentKind::PARTIAL_INVOICE) {
                continue;
            }
            if (!empty($row['is_draft'])) {
                continue;
            }
            $partials[] = $row;
        }

        return $partials;
    }

    /**
     * @return array{order_total: float, partial_total: float, remaining: float, partials: list<array<string, mixed>>}
     */
    public static function finalInvoiceSummary(int $parentId, ?int $excludeVoucherId = null): array
    {
        $orderTotal = self::orderTotalGross($parentId);
        $partials = self::partialInvoicesForFinal($parentId, $excludeVoucherId);
        $partialTotal = 0.0;
        $partialRows = [];
        foreach ($partials as $row) {
            $amount = round((float) ($row['gross_amount'] ?? 0), 2);
            $partialTotal = round($partialTotal + $amount, 2);
            $partialRows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'invoice_number' => (string) ($row['invoice_number'] ?? ''),
                'voucher_date' => (string) ($row['voucher_date'] ?? ''),
                'gross_amount' => $amount,
                'gross_display' => VoucherRepository::formatMoney($amount),
                'url' => '/app?page=buchhaltung-beleg-form&action=edit&id=' . (int) ($row['id'] ?? 0),
            ];
        }

        return [
            'order_total' => $orderTotal,
            'order_total_display' => VoucherRepository::formatMoney($orderTotal),
            'partial_total' => $partialTotal,
            'partial_total_display' => VoucherRepository::formatMoney($partialTotal),
            'remaining' => round(max(0, $orderTotal - $partialTotal), 2),
            'remaining_display' => VoucherRepository::formatMoney(max(0, $orderTotal - $partialTotal)),
            'partials' => $partialRows,
        ];
    }

    /**
     * Formular-Vorlage für Folgebeleg.
     *
     * @return array<string, mixed>
     */
    public static function prefillFollowUp(int $parentId, string $documentKind): array
    {
        $documentKind = VoucherDocumentKind::sanitize($documentKind);
        if ($documentKind === '') {
            throw new InvalidArgumentException('Ungültige Dokumentart für Folgebeleg.');
        }

        $parent = VoucherRepository::findById($parentId);
        if ($parent === null) {
            throw new InvalidArgumentException('Vorgänger-Beleg nicht gefunden.');
        }

        if (VoucherRepository::normalizeVoucherType((string) ($parent['voucher_type'] ?? '')) !== 'income') {
            throw new InvalidArgumentException('Folgebelege sind nur für Einnahmen-Belege möglich.');
        }

        $form = VoucherRepository::toForm($parent);
        $form['voucher_type'] = 'income';
        $form['document_kind'] = $documentKind;
        $form['parent_voucher_id'] = (string) $parentId;
        $form['voucher_date'] = date('Y-m-d');
        $form['delivery_date'] = date('Y-m-d');
        $form['payment_status'] = VoucherPaymentStatus::OPEN;
        $form['paid_amount'] = '';
        $form['paid_at'] = '';
        $form['arap_enabled'] = '0';
        $form['invoice_number'] = '';

        try {
            $form['invoice_number'] = VoucherRepository::peekDocumentNumber('income', $documentKind);
        } catch (Throwable) {
            $form['invoice_number'] = '';
        }

        if ($documentKind === VoucherDocumentKind::FINAL_INVOICE) {
            $summary = self::finalInvoiceSummary($parentId);
            $remaining = (float) ($summary['remaining'] ?? 0);
            if ($remaining > 0 && is_array($form['items'] ?? null) && $form['items'] !== []) {
                $form['items'] = self::scaleItemsToGross($form['items'], $remaining);
            }
            $form['chain_summary'] = $summary;
            $partialLabels = array_map(
                static fn (array $p): string => trim((string) ($p['invoice_number'] ?? '')) !== ''
                    ? (string) $p['invoice_number']
                    : ('#' . (int) ($p['id'] ?? 0)),
                $summary['partials'] ?? []
            );
            if ($partialLabels !== []) {
                $form['notes'] = trim((string) ($form['notes'] ?? ''));
                $deductionNote = 'Abzüglich Abschlagsrechnungen: ' . implode(', ', $partialLabels)
                    . ' (' . ($summary['partial_total_display'] ?? '') . ').';
                $form['notes'] = $form['notes'] !== '' ? $form['notes'] . "\n" . $deductionNote : $deductionNote;
            }
        }

        return $form;
    }

    public static function findRootId(int $voucherId): int
    {
        if (!Database::isConfigured() || $voucherId < 1) {
            return 0;
        }

        $currentId = $voucherId;
        $guard = 0;
        while ($guard < 50) {
            $guard++;
            $stmt = Database::pdo()->prepare(
                'SELECT id, parent_voucher_id FROM dg_vouchers WHERE id = :id LIMIT 1'
            );
            $stmt->execute(['id' => $currentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return 0;
            }
            $parentId = (int) ($row['parent_voucher_id'] ?? 0);
            if ($parentId < 1) {
                return (int) ($row['id'] ?? 0);
            }
            $currentId = $parentId;
        }

        return $currentId;
    }

    /**
     * Anker für Abschläge: Auftragsbestätigung, sonst Angebot, sonst direkter Parent.
     */
    public static function chainAnchorId(int $voucherId): int
    {
        $rootId = self::findRootId($voucherId);
        if ($rootId < 1) {
            return 0;
        }

        $subtree = self::collectSubtree($rootId);
        foreach ([VoucherDocumentKind::ORDER_CONFIRMATION, VoucherDocumentKind::OFFER] as $preferred) {
            foreach ($subtree as $row) {
                if ((string) ($row['document_kind'] ?? '') === $preferred) {
                    return (int) ($row['id'] ?? 0);
                }
            }
        }

        return $rootId;
    }

    public static function orderTotalGross(int $voucherId): float
    {
        $candidates = [];
        $currentId = $voucherId;
        $guard = 0;
        while ($currentId > 0 && $guard < 50) {
            $guard++;
            $row = VoucherRepository::findById($currentId);
            if ($row === null) {
                break;
            }
            $kind = (string) ($row['document_kind'] ?? '');
            if (in_array($kind, [
                VoucherDocumentKind::OFFER,
                VoucherDocumentKind::ORDER_CONFIRMATION,
                VoucherDocumentKind::DELIVERY_NOTE,
                VoucherDocumentKind::INVOICE,
            ], true)) {
                $candidates[] = $row;
            }
            $currentId = (int) ($row['parent_voucher_id'] ?? 0);
        }

        if ($candidates === []) {
            $row = VoucherRepository::findById($voucherId);

            return round((float) ($row['gross_amount'] ?? 0), 2);
        }

        usort($candidates, static fn (array $a, array $b): int =>
            VoucherDocumentKind::sortOrder((string) ($b['document_kind'] ?? ''))
            <=> VoucherDocumentKind::sortOrder((string) ($a['document_kind'] ?? '')));

        $best = $candidates[0];
        $items = is_array($best['items'] ?? null) ? $best['items'] : [];
        if ($items !== []) {
            $sum = 0.0;
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $sum += round((float) ($item['gross_amount'] ?? 0), 2);
            }
            if ($sum > 0) {
                return round($sum, 2);
            }
        }

        return round((float) ($best['gross_amount'] ?? 0), 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function collectSubtree(int $rootId): array
    {
        $pdo = Database::pdo();
        $pending = [$rootId];
        $seen = [];
        $rows = [];

        while ($pending !== []) {
            $id = array_shift($pending);
            if ($id < 1 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            $stmt = $pdo->prepare('SELECT * FROM dg_vouchers WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                continue;
            }
            $rows[] = $row;

            $childStmt = $pdo->prepare('SELECT id FROM dg_vouchers WHERE parent_voucher_id = :pid');
            $childStmt->execute(['pid' => $id]);
            while ($child = $childStmt->fetch(PDO::FETCH_ASSOC)) {
                $childId = (int) ($child['id'] ?? 0);
                if ($childId > 0) {
                    $pending[] = $childId;
                }
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function chainPayload(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $kind = (string) ($row['document_kind'] ?? '');
        $label = VoucherDocumentKind::label($kind);
        if ($label === '') {
            $label = VoucherRepository::typeLabel((string) ($row['voucher_type'] ?? ''));
        }

        return [
            'id' => $id,
            'document_kind' => $kind,
            'document_label' => $label,
            'invoice_number' => (string) ($row['invoice_number'] ?? ''),
            'voucher_date' => (string) ($row['voucher_date'] ?? ''),
            'gross_amount' => round((float) ($row['gross_amount'] ?? 0), 2),
            'gross_display' => VoucherRepository::formatMoney((float) ($row['gross_amount'] ?? 0)),
            'is_draft' => !empty($row['is_draft']),
            'url' => '/app?page=buchhaltung-beleg-form&action=edit&id=' . $id,
            'books' => VoucherDocumentKind::isBookable($kind, (string) ($row['voucher_type'] ?? 'income')),
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private static function scaleItemsToGross(array $items, float $targetGross): array
    {
        $current = 0.0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $current += round((float) str_replace(',', '.', (string) ($item['gross_amount'] ?? '0')), 2);
        }
        if ($current <= 0) {
            return $items;
        }

        $factor = $targetGross / $current;
        $scaled = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $gross = round((float) str_replace(',', '.', (string) ($item['gross_amount'] ?? '0')) * $factor, 2);
            $qty = (float) str_replace(',', '.', (string) ($item['quantity'] ?? '1'));
            if ($qty <= 0) {
                $qty = 1.0;
            }
            $item['gross_amount'] = VoucherRepository::formatMoney($gross);
            $item['unit_price_gross'] = VoucherRepository::formatMoney(round($gross / $qty, 2));
            $scaled[] = $item;
        }

        return $scaled;
    }
}
