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
            $status = VoucherDocumentStatus::sanitize((string) ($row['document_status'] ?? ''));
            if (
                !empty($row['is_draft'])
                || $status === VoucherDocumentStatus::DRAFT
                || VoucherDocumentStatus::isClosed($status)
            ) {
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
            $amount = VoucherRepository::parseMoney($row['gross_amount'] ?? 0);
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
     * @param int|null $paymentId Bei Abschlag: genau diese Vorstufen-Zahlung (eine RE pro Zahlung)
     * @return array<string, mixed>
     */
    public static function prefillFollowUp(int $parentId, string $documentKind, ?int $paymentId = null): array
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

        if (self::isFollowUpBlockedByDeposit($documentKind, $parentId)) {
            throw new InvalidArgumentException(
                'Rechnung/Schlussrechnung erst nach gebuchter Anzahlung (Abschlagsrechnung) möglich. '
                . 'Lieferschein und Abschlag sind freigegeben.'
            );
        }

        $parentStatus = VoucherDocumentStatus::sanitize((string) ($parent['document_status'] ?? ''));
        $parentKind = VoucherDocumentKind::sanitize((string) ($parent['document_kind'] ?? ''));
        if (
            VoucherDocumentStatus::isClosed($parentStatus)
            && !(
                $parentKind === VoucherDocumentKind::OFFER
                && $documentKind === VoucherDocumentKind::OFFER
            )
        ) {
            throw new InvalidArgumentException(
                'Aus stornierten oder abgelaufenen Angeboten nur „Neu anbieten“ möglich.'
            );
        }

        $form = VoucherRepository::toForm($parent);
        $form['voucher_type'] = 'income';
        $form['document_kind'] = $documentKind;
        $form['document_status'] = VoucherDocumentStatus::defaultForKind($documentKind);
        $form['parent_voucher_id'] = (string) $parentId;
        $form['voucher_date'] = date('Y-m-d');
        $form['delivery_date'] = $documentKind === VoucherDocumentKind::OFFER
            ? DocumentPresentationSettings::defaultOfferValidUntilDate($form['voucher_date'])
            : date('Y-m-d');
        $form['payment_status'] = VoucherPaymentStatus::OPEN;
        $form['paid_amount'] = '';
        $form['paid_at'] = '';
        $form['arap_enabled'] = '0';
        $form['invoice_number'] = '';
        $form['discount_percent'] = '0';
        $form['discount_amount'] = '';
        $form['absorb_payment_id'] = '';

        try {
            $form['invoice_number'] = VoucherRepository::peekDocumentNumber('income', $documentKind);
        } catch (Throwable) {
            $form['invoice_number'] = '';
        }

        // Abschlagsrechnung: genau eine Ist-Zahlung → eine RE; sonst Rest der konfigurierten Anzahlung.
        if ($documentKind === VoucherDocumentKind::PARTIAL_INVOICE) {
            $deposit = DocumentPresentationSettings::depositAmountForVoucher($parent);
            $configured = $deposit !== null ? (float) ($deposit['amount'] ?? 0) : 0.0;
            $unabsorbed = self::unabsorbedAdvancePayments($parentId);
            $picked = null;
            if ($paymentId !== null && $paymentId > 0) {
                foreach ($unabsorbed as $pay) {
                    if ((int) ($pay['id'] ?? 0) === $paymentId) {
                        $picked = $pay;
                        break;
                    }
                }
                if ($picked === null) {
                    throw new InvalidArgumentException(
                        'Zahlung #' . $paymentId . ' ist nicht als offene Anzahlung in der Belegkette verfügbar.'
                    );
                }
            } elseif ($unabsorbed !== []) {
                $picked = $unabsorbed[0];
            }

            if ($picked !== null) {
                $target = round((float) ($picked['amount'] ?? 0), 2);
                $method = trim((string) ($picked['payment_method_label'] ?? $picked['payment_method'] ?? 'Zahlung'));
                $sourceHint = 'Ist-Zahlung ' . $method . ' vom ' . (string) ($picked['payment_date'] ?? '');
                $form['absorb_payment_id'] = (string) (int) ($picked['id'] ?? 0);
            } elseif ($configured > 0.01) {
                $progress = self::depositProgress($parentId);
                $remainingExpected = (float) ($progress['remaining'] ?? $configured);
                $target = $remainingExpected > 0.01 ? $remainingExpected : $configured;
                $sourceHint = trim((string) ($deposit['hint'] ?? 'konfigurierte Anzahlung'));
            } else {
                throw new InvalidArgumentException(
                    'Anzahlung für Abschlagsrechnung nicht berechenbar'
                    . (isset($deposit['hint']) && $deposit['hint'] !== '' ? ' (' . $deposit['hint'] . ')' : '')
                    . '. Bitte Zahlung erfassen oder Belegdarstellung / Einkaufspreise prüfen.'
                );
            }

            $parentGross = VoucherRepository::parseMoney($parent['gross_amount'] ?? 0);
            if ($parentGross > 0.01 && $target > $parentGross) {
                $target = $parentGross;
            }
            // Abschlag = eine Anzahlungsposition (keine skalierten Artikelzeilen).
            $taxRate = 19;
            if (is_array($form['items'] ?? null) && $form['items'] !== []) {
                $taxRate = (int) ($form['items'][0]['tax_rate'] ?? 19);
            }
            $form['items'] = [[
                'article_id' => '',
                'catalog_kind' => '',
                'article_number' => '',
                'title' => 'Anzahlung',
                'area_id' => '',
                'area_name' => '',
                'unit' => 'Pauschale',
                'quantity' => '1',
                'unit_price_gross' => VoucherRepository::formatMoney($target),
                'gross_amount' => VoucherRepository::formatMoney($target),
                'tax_rate' => (string) $taxRate,
                'tax_type' => $taxRate === 7 ? 'ust7' : 'ust19',
            ]];
            $form['gross_amount'] = VoucherRepository::formatMoney($target);
            $form['notes'] = trim((string) ($form['notes'] ?? ''));
            $depositNote = 'Abschlag / Anzahlung: ' . VoucherRepository::formatMoney($target) . ' €'
                . ($sourceHint !== '' ? ' (' . $sourceHint . ')' : '')
                . '.';
            if ($picked !== null && $configured > 0.01) {
                $depositNote .= ' Erwartete Anzahlung gesamt: '
                    . VoucherRepository::formatMoney($configured) . ' €.';
            }
            $form['notes'] = $form['notes'] !== '' ? $form['notes'] . "\n" . $depositNote : $depositNote;
            $form['chain_payments_total'] = $target;
            $form['chain_payments'] = $picked !== null ? [$picked] : [];
            $form['deposit_progress'] = self::depositProgress($parentId);
        }

        if ($documentKind === VoucherDocumentKind::FINAL_INVOICE) {
            // Volle Auftragspositionen (Angebot/AB) — nie die Anzahlungszeile eines Abschlags.
            $orderSourceId = self::chainAnchorId($parentId);
            if ($orderSourceId < 1) {
                $orderSourceId = $parentId;
            }
            $orderSource = VoucherRepository::findById($orderSourceId);
            if ($orderSource !== null) {
                $orderForm = VoucherRepository::toForm($orderSource);
                if (is_array($orderForm['items'] ?? null) && $orderForm['items'] !== []) {
                    $form['items'] = $orderForm['items'];
                }
                if (is_array($orderForm['lines'] ?? null) && $orderForm['lines'] !== []) {
                    $form['lines'] = $orderForm['lines'];
                }
                foreach (['description', 'tax_rate', 'tax_key', 'account_number'] as $copyKey) {
                    if (trim((string) ($orderForm[$copyKey] ?? '')) !== '') {
                        $form[$copyKey] = $orderForm[$copyKey];
                    }
                }
            }

            $summary = self::finalInvoiceSummary($parentId);
            $orderTotal = (float) ($summary['order_total'] ?? 0);
            if ($orderTotal <= 0.01 && $orderSource !== null) {
                $orderTotal = VoucherRepository::parseMoney($orderSource['gross_amount'] ?? 0);
            }
            if ($orderTotal > 0.01) {
                $form['gross_amount'] = VoucherRepository::formatMoney($orderTotal);
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
                $deductionNote = 'Abzüglich Anzahlungen: ' . implode(', ', $partialLabels)
                    . ' (' . ($summary['partial_total_display'] ?? '') . ').'
                    . ' Restbetrag: ' . ($summary['remaining_display'] ?? '0,00') . ' €.';
                $form['notes'] = $form['notes'] !== '' ? $form['notes'] . "\n" . $deductionNote : $deductionNote;
            }
        }

        return $form;
    }

    /**
     * Abgelaufenes/storniertes Angebot → neues Angebot mit aktuellen Artikelpreisen.
     *
     * @return array<string, mixed>
     */
    public static function prefillReanimatedOffer(int $parentId): array
    {
        $parent = VoucherRepository::findById($parentId);
        if ($parent === null) {
            throw new InvalidArgumentException('Vorgänger-Angebot nicht gefunden.');
        }
        if (VoucherDocumentKind::sanitize((string) ($parent['document_kind'] ?? '')) !== VoucherDocumentKind::OFFER) {
            throw new InvalidArgumentException('Nur Angebote können neu angeboten werden.');
        }
        $status = VoucherDocumentStatus::sanitize((string) ($parent['document_status'] ?? ''));
        if (!VoucherDocumentStatus::isClosed($status)) {
            throw new InvalidArgumentException(
                'Neu anbieten nur für abgelaufene oder stornierte Angebote.'
            );
        }

        $form = self::prefillFollowUp($parentId, VoucherDocumentKind::OFFER);
        $form['items'] = self::refreshItemPricesFromCatalog(
            is_array($form['items'] ?? null) ? $form['items'] : []
        );
        $form['document_intro_text'] = DocumentPresentationSettings::defaultIntro(VoucherDocumentKind::OFFER);
        $form['document_footer_text'] = DocumentPresentationSettings::defaultFooter(VoucherDocumentKind::OFFER);
        $form['notes'] = '[Neu anbieten] Nachfolger zu Angebot #' . $parentId
            . ' (Status war: ' . VoucherDocumentStatus::label($status) . '). Preise aus Artikelstamm aktualisiert.';

        return $form;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public static function refreshItemPricesFromCatalog(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $articleId = (int) ($item['article_id'] ?? 0);
            if ($articleId > 0) {
                $price = CalendarArticleRepository::priceGross($articleId);
                if ($price > 0) {
                    $qty = VoucherRepository::parseQuantity($item['quantity'] ?? '1');
                    if ($qty <= 0) {
                        $qty = 1.0;
                    }
                    $item['unit_price_gross'] = VoucherRepository::formatMoney($price);
                    $item['gross_amount'] = VoucherRepository::formatMoney(round($price * $qty, 2));
                }
            }
            $out[] = $item;
        }

        return $out;
    }

    public static function reanimateOfferUrl(int $offerId): string
    {
        return '/app?page=buchhaltung-beleg-form&action=new&follow_from=' . $offerId
            . '&document_kind=' . rawurlencode(VoucherDocumentKind::OFFER)
            . '&reanimate=1';
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

            return VoucherRepository::parseMoney($row['gross_amount'] ?? 0);
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
                $sum = round($sum + VoucherRepository::parseMoney($item['gross_amount'] ?? 0), 2);
            }
            if ($sum > 0) {
                return $sum;
            }
        }

        return VoucherRepository::parseMoney($best['gross_amount'] ?? 0);
    }

    /**
     * Ziel-URL nach Angebotsannahme: bestehende AB öffnen oder Folgebeleg vorbereiten.
     */
    public static function orderConfirmationFollowUpUrl(int $offerId): string
    {
        $existingAb = OfferAcceptanceMailService::existingOrderConfirmationId($offerId);
        if ($existingAb > 0) {
            return '/app?page=buchhaltung-beleg-form&action=edit&id=' . $existingAb;
        }

        return '/app?page=buchhaltung-beleg-form&action=new&follow_from=' . $offerId
            . '&document_kind=' . rawurlencode(VoucherDocumentKind::ORDER_CONFIRMATION);
    }

    public static function subtreeHasKind(int $voucherId, string $kind): bool
    {
        $rootId = self::findRootId($voucherId);
        if ($rootId < 1) {
            return false;
        }

        $kind = VoucherDocumentKind::sanitize($kind);
        if ($kind === '') {
            return false;
        }

        foreach (self::collectSubtree($rootId) as $row) {
            if ((string) ($row['document_kind'] ?? '') === $kind) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gebuchte Anzahlung = nicht-entwurfliche Abschlagsrechnung in der Kette.
     */
    public static function hasBookedDeposit(int $voucherId): bool
    {
        return self::partialInvoicesForFinal($voucherId) !== [];
    }

    /**
     * Rechnung/Schlussrechnung gesperrt, wenn Anzahlung eingestellt und noch nicht gebucht.
     * Lieferschein und Abschlag bleiben erlaubt.
     */
    public static function isFollowUpBlockedByDeposit(string $followKind, int $fromVoucherId): bool
    {
        $followKind = VoucherDocumentKind::sanitize($followKind);
        if (!in_array($followKind, [VoucherDocumentKind::INVOICE, VoucherDocumentKind::FINAL_INVOICE], true)) {
            return false;
        }
        if (!DocumentPresentationSettings::depositRequired()) {
            return false;
        }

        return !self::hasBookedDeposit($fromVoucherId);
    }

    /**
     * @return array{blocked: list<string>, reason: string}
     */
    public static function followUpDepositGate(int $voucherId, array $followKinds): array
    {
        $blocked = [];
        foreach ($followKinds as $kind) {
            $kind = VoucherDocumentKind::sanitize((string) $kind);
            if ($kind !== '' && self::isFollowUpBlockedByDeposit($kind, $voucherId)) {
                $blocked[] = $kind;
            }
        }

        $reason = '';
        if ($blocked !== []) {
            $reason = 'Anzahlung ist in der Belegdarstellung aktiv — zuerst Abschlagsrechnung (Anzahlung) buchen. '
                . 'Lieferschein und Abschlag bleiben möglich; Rechnung und Schlussrechnung erst danach.';
        }

        return ['blocked' => $blocked, 'reason' => $reason];
    }

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
        $docStatus = (string) ($row['document_status'] ?? '');

        return [
            'id' => $id,
            'document_kind' => $kind,
            'document_label' => $label,
            'document_status' => $docStatus,
            'document_status_label' => VoucherDocumentStatus::label($docStatus),
            'document_status_badge_class' => VoucherDocumentStatus::badgeClass($docStatus),
            'invoice_number' => (string) ($row['invoice_number'] ?? ''),
            'voucher_date' => (string) ($row['voucher_date'] ?? ''),
            'gross_amount' => VoucherRepository::parseMoney($row['gross_amount'] ?? 0),
            'gross_display' => VoucherRepository::formatMoney(VoucherRepository::parseMoney($row['gross_amount'] ?? 0)),
            'is_draft' => !empty($row['is_draft']),
            'url' => '/app?page=buchhaltung-beleg-form&action=edit&id=' . $id,
            'books' => VoucherDocumentKind::isBookable($kind, (string) ($row['voucher_type'] ?? 'income')),
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    /**
     * Eine Vorstufen-Zahlung auf die Abschlagsrechnung ziehen (eine RE pro Zahlung).
     *
     * @return int Anzahl umgehängter Zahlungen (0 oder 1)
     */
    public static function absorbAdvancePaymentsOnto(int $targetVoucherId, ?int $paymentId = null): int
    {
        if (!Database::isConfigured() || $targetVoucherId < 1) {
            return 0;
        }

        $target = VoucherRepository::findById($targetVoucherId);
        if ($target === null) {
            return 0;
        }
        $kind = VoucherDocumentKind::sanitize((string) ($target['document_kind'] ?? ''));
        if ($kind !== VoucherDocumentKind::PARTIAL_INVOICE) {
            return 0;
        }

        $rootId = self::findRootId($targetVoucherId);
        if ($rootId < 1) {
            return 0;
        }

        $sourceIds = [];
        foreach (self::collectSubtree($rootId) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1 || $id === $targetVoucherId) {
                continue;
            }
            $rowKind = VoucherDocumentKind::sanitize((string) ($row['document_kind'] ?? ''));
            $type = VoucherRepository::normalizeVoucherType((string) ($row['voucher_type'] ?? 'expense'));
            if (in_array($rowKind, [
                VoucherDocumentKind::OFFER,
                VoucherDocumentKind::ORDER_CONFIRMATION,
                VoucherDocumentKind::DELIVERY_NOTE,
            ], true)) {
                $sourceIds[] = $id;
                continue;
            }
            if ($rowKind === '' && $type === 'income') {
                $sourceIds[] = $id;
            }
        }
        $sourceIds = array_values(array_unique($sourceIds));
        if ($sourceIds === []) {
            return 0;
        }

        $pdo = Database::pdo();
        $payId = $paymentId !== null && $paymentId > 0 ? $paymentId : 0;
        if ($payId < 1) {
            // Fallback: älteste offene Vorstufen-Zahlung, Betrag ≈ Abschlag-Brutto
            $targetGross = VoucherRepository::parseMoney($target['gross_amount'] ?? 0);
            $candidates = self::unabsorbedAdvancePayments($targetVoucherId);
            foreach ($candidates as $cand) {
                $amt = round((float) ($cand['amount'] ?? 0), 2);
                if ($targetGross <= 0.01 || abs($amt - $targetGross) < 0.02) {
                    $payId = (int) ($cand['id'] ?? 0);
                    break;
                }
            }
            if ($payId < 1 && $candidates !== []) {
                $payId = (int) ($candidates[0]['id'] ?? 0);
            }
        }
        if ($payId < 1) {
            return 0;
        }

        $select = $pdo->prepare(
            'SELECT id, voucher_id FROM dg_voucher_payments WHERE id = :id LIMIT 1'
        );
        $select->execute(['id' => $payId]);
        $pay = $select->fetch(PDO::FETCH_ASSOC);
        if (!is_array($pay)) {
            return 0;
        }
        $sourceId = (int) ($pay['voucher_id'] ?? 0);
        if ($sourceId < 1 || !in_array($sourceId, $sourceIds, true)) {
            return 0;
        }

        $update = $pdo->prepare(
            'UPDATE dg_voucher_payments SET voucher_id = :target WHERE id = :id AND voucher_id = :source'
        );
        $update->execute([
            'target' => $targetVoucherId,
            'id' => $payId,
            'source' => $sourceId,
        ]);
        if ($update->rowCount() < 1) {
            return 0;
        }

        VoucherPaymentRepository::syncVoucherSettlement($sourceId);
        VoucherPaymentRepository::syncVoucherSettlement($targetVoucherId);

        return 1;
    }

    /**
     * Erwartete vs. erhaltene Anzahlung in der Belegkette.
     *
     * @return array{
     *   expected: float,
     *   expected_display: string,
     *   received: float,
     *   received_display: string,
     *   remaining: float,
     *   remaining_display: string,
     *   order_total: float,
     *   order_total_display: string,
     *   basis: float,
     *   basis_label: string,
     *   mode: string,
     *   label: string
     * }
     */
    public static function depositProgress(int $voucherId): array
    {
        $orderTotal = self::orderTotalGross($voucherId);
        $anchor = VoucherRepository::findById(self::chainAnchorId($voucherId) ?: $voucherId)
            ?? VoucherRepository::findById($voucherId);
        $deposit = $anchor !== null
            ? DocumentPresentationSettings::depositAmountForVoucher($anchor)
            : null;
        $expected = $deposit !== null ? round((float) ($deposit['amount'] ?? 0), 2) : 0.0;
        $mode = (string) ($deposit['mode'] ?? DocumentPresentationSettings::DEPOSIT_NONE);
        $label = (string) ($deposit['label'] ?? 'Anzahlung');

        // Erhalten = alle Ist-Zahlungen in der Belegkette (auch wenn Abschlag noch Entwurf)
        $received = self::totalPaidInChain($voucherId);

        // Anzahlung 0 → Rest bezogen auf Gesamtbetrag; sonst auf erwartete Anzahlung
        if ($expected <= 0.01) {
            $basis = $orderTotal;
            $basisLabel = 'Gesamtbetrag';
        } else {
            $basis = $expected;
            $basisLabel = 'Erwartete Anzahlung';
        }
        $remaining = round(max(0, $basis - $received), 2);

        return [
            'expected' => $expected,
            'expected_display' => VoucherRepository::formatMoney($expected),
            'received' => $received,
            'received_display' => VoucherRepository::formatMoney($received),
            'remaining' => $remaining,
            'remaining_display' => VoucherRepository::formatMoney($remaining),
            'order_total' => $orderTotal,
            'order_total_display' => VoucherRepository::formatMoney($orderTotal),
            'basis' => $basis,
            'basis_label' => $basisLabel,
            'mode' => $mode,
            'label' => $label,
        ];
    }

    /**
     * Zahlungen auf Angebot/AB/Lieferschein, die noch keine Abschlagsrechnung haben.
     *
     * @return list<array<string, mixed>>
     */
    public static function unabsorbedAdvancePayments(int $voucherId): array
    {
        $all = self::paymentsInChain($voucherId);
        $out = [];
        foreach ($all as $pay) {
            $kind = VoucherDocumentKind::sanitize((string) ($pay['chain_document_kind'] ?? ''));
            if (in_array($kind, [
                VoucherDocumentKind::OFFER,
                VoucherDocumentKind::ORDER_CONFIRMATION,
                VoucherDocumentKind::DELIVERY_NOTE,
                '',
            ], true)) {
                $out[] = $pay;
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function paymentsInChain(int $voucherId): array
    {
        if (!Database::isConfigured() || $voucherId < 1) {
            return [];
        }

        $rootId = self::findRootId($voucherId);
        if ($rootId < 1) {
            return [];
        }

        $out = [];
        foreach (self::collectSubtree($rootId) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $kindLabel = VoucherDocumentKind::label((string) ($row['document_kind'] ?? ''));
            $number = trim((string) ($row['invoice_number'] ?? ''));
            $docLabel = $kindLabel !== '' ? $kindLabel : 'Beleg';
            if ($number !== '') {
                $docLabel .= ' ' . $number;
            } else {
                $docLabel .= ' #' . $id;
            }
            foreach (VoucherPaymentRepository::listForVoucher($id) as $pay) {
                $pay['chain_voucher_id'] = $id;
                $pay['chain_document_label'] = $docLabel;
                $pay['chain_document_kind'] = (string) ($row['document_kind'] ?? '');
                $out[] = $pay;
            }
        }

        usort(
            $out,
            static function (array $a, array $b): int {
                $da = (string) ($a['payment_date'] ?? '');
                $db = (string) ($b['payment_date'] ?? '');
                if ($da !== $db) {
                    return $da <=> $db;
                }

                return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            }
        );

        return $out;
    }

    public static function totalPaidInChain(int $voucherId): float
    {
        $sum = 0.0;
        foreach (self::paymentsInChain($voucherId) as $pay) {
            $sum = round($sum + (float) ($pay['amount'] ?? 0), 2);
        }

        return $sum;
    }

    private static function scaleItemsToGross(array $items, float $targetGross): array
    {
        $current = 0.0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $current += VoucherRepository::parseMoney($item['gross_amount'] ?? 0);
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
            $gross = round(VoucherRepository::parseMoney($item['gross_amount'] ?? 0) * $factor, 2);
            $qty = VoucherRepository::parseQuantity($item['quantity'] ?? '1');
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
