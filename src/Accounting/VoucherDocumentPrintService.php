<?php
declare(strict_types=1);

/** Druck/PDF und E-Mail-HTML für Ausgangsbelege. */
final class VoucherDocumentPrintService
{
    /**
     * @param array<string, mixed> $voucher
     */
    public static function render(array $voucher): string
    {
        $context = self::buildContext($voucher, ['show_chain' => true, 'for_email' => false]);

        return AccountingPrintService::render(
            'voucher-document',
            $context,
            (string) $context['pageTitle'],
            ['document_styles' => true]
        );
    }

    /**
     * Vollständiges HTML-Dokument für E-Mail-Anhang (Druck → PDF).
     *
     * @param array<string, mixed> $voucher
     */
    public static function renderAttachmentHtml(array $voucher): string
    {
        $context = self::buildContext($voucher, ['show_chain' => false, 'for_email' => false]);

        return AccountingPrintService::render(
            'voucher-document',
            $context,
            (string) $context['pageTitle'],
            ['document_styles' => true, 'hide_print_button' => true]
        );
    }

    /**
     * Eingebetteter Dokumentinhalt für E-Mail-Body.
     *
     * @param array<string, mixed> $voucher
     */
    public static function renderEmailBodyFragment(array $voucher): string
    {
        $context = self::buildContext($voucher, ['show_chain' => false, 'for_email' => true]);

        return AccountingPrintService::renderBody('voucher-document', $context);
    }

    public static function defaultEmailSubject(array $voucher): string
    {
        $kind = (string) ($voucher['document_kind'] ?? '');
        $label = VoucherDocumentKind::label($kind);
        if ($label === '') {
            $label = 'Beleg';
        }
        $number = trim((string) ($voucher['invoice_number'] ?? ''));
        $company = CompanySettings::displayName();
        $prefix = $company !== '' ? $company . ' — ' : '';

        return $prefix . $label . ($number !== '' ? ' ' . $number : '');
    }

    public static function defaultEmailIntro(array $voucher): string
    {
        $kind = (string) ($voucher['document_kind'] ?? '');
        $label = VoucherDocumentKind::label($kind);
        if ($label === '') {
            $label = 'Ihr Beleg';
        }

        return 'Guten Tag,' . "\n\n"
            . 'anbei erhalten Sie ' . $label
            . (trim((string) ($voucher['invoice_number'] ?? '')) !== ''
                ? ' (Nr. ' . trim((string) $voucher['invoice_number']) . ')'
                : '')
            . '.' . "\n\n"
            . 'Bei Rückfragen stehen wir Ihnen gerne zur Verfügung.' . "\n\n"
            . 'Mit freundlichen Grüßen';
    }

    /**
     * @param array<string, mixed> $voucher
     * @param array{show_chain?: bool, for_email?: bool} $options
     * @return array<string, mixed>
     */
    public static function buildContext(array $voucher, array $options = []): array
    {
        $voucherId = (int) ($voucher['id'] ?? 0);
        $kind = (string) ($voucher['document_kind'] ?? '');
        $kindLabel = VoucherDocumentKind::label($kind);
        if ($kindLabel === '') {
            $kindLabel = 'Beleg';
        }

        $number = trim((string) ($voucher['invoice_number'] ?? ''));
        $pageTitle = $kindLabel . ($number !== '' ? ' ' . $number : '');

        $showChain = !empty($options['show_chain']);
        $forEmail = !empty($options['for_email']);

        $chain = $showChain && $voucherId > 0
            ? VoucherDocumentChain::chainView($voucherId)
            : ['documents' => []];

        $finalSummary = null;
        if ($kind === VoucherDocumentKind::FINAL_INVOICE && $voucherId > 0) {
            $parentId = (int) ($voucher['parent_voucher_id'] ?? 0);
            if ($parentId > 0) {
                $finalSummary = VoucherDocumentChain::finalInvoiceSummary($parentId, $voucherId);
            }
        }

        $items = is_array($voucher['items'] ?? null) ? $voucher['items'] : [];
        if ($items === [] && $voucherId > 0) {
            $items = VoucherRepository::itemsForVoucher($voucherId);
        }

        $books = VoucherDocumentKind::isBookable($kind, (string) ($voucher['voucher_type'] ?? 'income'));
        $contact = null;
        $contactId = (int) ($voucher['contact_id'] ?? 0);
        if ($contactId > 0) {
            $contact = ContactRepository::findById($contactId);
        }

        return [
            'voucher' => $voucher,
            'kind' => $kind,
            'kindLabel' => $kindLabel,
            'items' => $items,
            'chain' => $chain,
            'finalSummary' => $finalSummary,
            'books' => $books,
            'forEmail' => $forEmail,
            'showChain' => $showChain,
            'pageTitle' => $pageTitle,
            'companyBlock' => self::companyBlock(),
            'customerBlock' => self::customerBlock($voucher, $contact),
            'legalNotice' => self::legalNotice($kind, $books),
            'footerNotice' => self::footerNotice($kind, $books),
            'primaryBank' => self::primaryBankAccount(),
            'documentStatusLabel' => VoucherDocumentStatus::label((string) ($voucher['document_status'] ?? '')),
            'legalClauseBlocks' => VoucherDocumentLegalClause::blocksForKeys(
                VoucherDocumentLegalClause::sanitizeSelection($voucher['document_legal_clauses'] ?? [])
            ),
            'paymentTermsText' => self::paymentTermsText($voucher),
        ];
    }

    /**
     * @param array<string, mixed> $voucher
     */
    public static function paymentTermsText(array $voucher): string
    {
        $tiers = PaymentTermsService::sanitizeTiers($voucher['payment_term_tiers'] ?? []);
        if ($tiers === []) {
            return '';
        }
        $voucherDate = (string) ($voucher['voucher_date'] ?? '');
        $dueDate = (string) ($voucher['payment_due_date'] ?? '');
        if ($dueDate === '' && $voucherDate !== '') {
            $dueDate = PaymentTermsService::dueDateFromTiers($voucherDate, $tiers);
        }

        return PaymentTermsService::composeText($tiers, $voucherDate, $dueDate);
    }

    /**
     * @return array{name: string, lines: list<string>}
     */
    public static function companyBlock(): array
    {
        $basic = CompanySettings::config();
        $extended = CompanyExtendedSettings::config();
        $name = trim((string) ($extended['legal_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($basic['name'] ?? ''));
        }
        if ($name === '') {
            $name = 'DG CRM';
        }

        $lines = [];
        $street = trim((string) ($basic['street'] ?? ''));
        $postal = trim((string) ($basic['postal'] ?? ''));
        $city = trim((string) ($basic['city'] ?? ''));
        if ($street !== '') {
            $lines[] = $street;
        }
        if ($postal !== '' || $city !== '') {
            $lines[] = trim($postal . ' ' . $city);
        }
        if (trim((string) ($basic['phone'] ?? '')) !== '') {
            $lines[] = 'Tel. ' . trim((string) $basic['phone']);
        }
        if (trim((string) ($basic['email'] ?? '')) !== '') {
            $lines[] = trim((string) $basic['email']);
        }
        $vat = trim((string) ($extended['tax_numbers']['ust'] ?? $basic['vat_id'] ?? ''));
        if ($vat !== '') {
            $lines[] = 'USt-IdNr. ' . $vat;
        }
        $tradeCourt = trim((string) ($extended['trade_register']['court'] ?? ''));
        $tradeNumber = trim((string) ($extended['trade_register']['number'] ?? ''));
        if ($tradeCourt !== '' || $tradeNumber !== '') {
            $lines[] = trim($tradeCourt . ' ' . $tradeNumber);
        }

        return ['name' => $name, 'lines' => $lines];
    }

    /**
     * @param array<string, mixed> $voucher
     */
    public static function customerBlock(array $voucher, ?Contact $contact): array
    {
        $name = trim((string) ($voucher['supplier_name'] ?? ''));
        if ($name === '' && $contact !== null) {
            $name = trim($contact->companyName);
            if ($name === '') {
                $name = trim($contact->displayName);
            }
        }

        $lines = [];
        if ($contact !== null) {
            if ($contact->address1Street !== '') {
                $lines[] = $contact->address1Street;
            }
            $cityLine = trim($contact->address1Postal . ' ' . $contact->address1City);
            if ($cityLine !== '') {
                $lines[] = $cityLine;
            }
            if ($contact->vatId !== '') {
                $lines[] = 'USt-IdNr. ' . $contact->vatId;
            }
        }

        return ['name' => $name, 'lines' => $lines];
    }

  /**
     * @return array{holder: string, iban: string, bank: string, bic: string}|null
     */
    public static function primaryBankAccount(): ?array
    {
        $accounts = CompanyExtendedSettings::config()['bank_accounts'] ?? [];
        if (!is_array($accounts)) {
            return null;
        }

        foreach ($accounts as $row) {
            if (!is_array($row)) {
                continue;
            }
            $iban = trim((string) ($row['iban'] ?? ''));
            if ($iban === '') {
                continue;
            }

            return [
                'holder' => trim((string) ($row['holder'] ?? '')),
                'iban' => $iban,
                'bank' => trim((string) ($row['bank_name'] ?? '')),
                'bic' => trim((string) ($row['bic'] ?? '')),
            ];
        }

        return null;
    }

    public static function legalNotice(string $kind, bool $books): string
    {
        $kind = VoucherDocumentKind::sanitize($kind);

        if (!$books) {
            return match ($kind) {
                VoucherDocumentKind::OFFER => 'Unverbindliches Angebot — ohne Buchungs- und Umsatzsteuerwirkung.',
                VoucherDocumentKind::ORDER_CONFIRMATION => 'Auftragsbestätigung — noch keine Rechnung, keine Umsatzsteuer.',
                VoucherDocumentKind::DELIVERY_NOTE => 'Lieferschein — kein Rechnungs- oder Buchungsbeleg.',
                default => 'Unverbindlich — keine Buchung / keine UStVA-Meldung.',
            };
        }

        return match ($kind) {
            VoucherDocumentKind::PARTIAL_INVOICE => 'Abschlagsrechnung — Teilbetrag des Auftrags.',
            VoucherDocumentKind::FINAL_INVOICE => 'Schlussrechnung — Restbetrag nach Abzug der Abschlagsrechnungen.',
            VoucherDocumentKind::INVOICE => 'Rechnung — Zahlbar ohne Abzug gemäß vereinbarten Zahlungsbedingungen.',
            default => '',
        };
    }

    public static function footerNotice(string $kind, bool $books): string
    {
        if (!$books) {
            return 'Dieses Dokument dient der Information und stellt keine Rechnung dar.';
        }

        $kind = VoucherDocumentKind::sanitize($kind);
        if (in_array($kind, [VoucherDocumentKind::INVOICE, VoucherDocumentKind::PARTIAL_INVOICE, VoucherDocumentKind::FINAL_INVOICE], true)) {
            return 'Es gilt das vereinbarte Zahlungsziel. Bei Zahlung innerhalb der Skontofrist gewähren wir den vereinbarten Skontoabzug.';
        }

        return '';
    }

    public static function attachmentFilename(array $voucher): string
    {
        $kindLabel = VoucherDocumentKind::label((string) ($voucher['document_kind'] ?? ''));
        if ($kindLabel === '') {
            $kindLabel = 'Beleg';
        }
        $number = trim((string) ($voucher['invoice_number'] ?? ''));
        $base = $kindLabel . ($number !== '' ? '_' . preg_replace('/[^A-Za-z0-9._-]+/', '_', $number) : '');

        return $base . '.html';
    }
}
