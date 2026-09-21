<?php
declare(strict_types=1);

/** Druck/PDF und E-Mail-HTML für Ausgangsbelege. */
final class VoucherDocumentPrintService
{
    /**
     * @param array<string, mixed> $voucher
     * @param array{show_chain?: bool, for_email?: bool, customer_facing?: bool} $options
     */
    public static function render(array $voucher, array $options = []): string
    {
        $customerFacing = array_key_exists('customer_facing', $options)
            ? !empty($options['customer_facing'])
            : empty($options['show_chain']);
        $context = self::buildContext($voucher, [
            'show_chain' => !empty($options['show_chain']),
            'for_email' => false,
            'customer_facing' => $customerFacing,
        ]);

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
        $context = self::buildContext($voucher, [
            'show_chain' => false,
            'for_email' => false,
            'customer_facing' => true,
        ]);

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
        $context = self::buildContext($voucher, [
            'show_chain' => false,
            'for_email' => true,
            'customer_facing' => true,
        ]);

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

        $base = 'Guten Tag,' . "\n\n"
            . 'anbei erhalten Sie ' . $label
            . (trim((string) ($voucher['invoice_number'] ?? '')) !== ''
                ? ' (Nr. ' . trim((string) $voucher['invoice_number']) . ')'
                : '')
            . '.' . "\n\n";

        if (VoucherDocumentKind::sanitize($kind) === VoucherDocumentKind::OFFER) {
            $base .= 'Wenn Sie das Angebot annehmen möchten, antworten Sie bitte einfach auf diese E-Mail '
                . '(Antwort bewirkt die Auftragsbestätigung).' . "\n\n";
        }

        return $base
            . 'Bei Rückfragen stehen wir Ihnen gerne zur Verfügung.' . "\n\n"
            . 'Mit freundlichen Grüßen';
    }

    /**
     * @param array<string, mixed> $voucher
     * @param array{show_chain?: bool, for_email?: bool, customer_facing?: bool} $options
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
        $customerFacing = array_key_exists('customer_facing', $options)
            ? !empty($options['customer_facing'])
            : !$showChain;

        $chain = $showChain && $voucherId > 0
            ? VoucherDocumentChain::chainView($voucherId)
            : ['documents' => []];

        $finalSummary = null;
        if ($kind === VoucherDocumentKind::FINAL_INVOICE) {
            $parentId = (int) ($voucher['parent_voucher_id'] ?? 0);
            if ($parentId > 0) {
                $finalSummary = VoucherDocumentChain::finalInvoiceSummary(
                    $parentId,
                    $voucherId > 0 ? $voucherId : null
                );
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

        $validUntil = self::offerValidUntil($voucher, $kind);
        $introText = trim((string) ($voucher['document_intro_text'] ?? ''));
        $footerText = trim((string) ($voucher['document_footer_text'] ?? ''));
        if (VoucherDocumentKind::usesPositionTexts($kind, (string) ($voucher['voucher_type'] ?? 'income'))) {
            if ($introText === '') {
                $introText = DocumentPresentationSettings::defaultIntro($kind);
            }
            if ($footerText === '') {
                $footerText = DocumentPresentationSettings::defaultFooter($kind);
            }
        }
        $footerText = self::applyDocumentPlaceholders($footerText, $validUntil);
        $introText = self::applyDocumentPlaceholders($introText, $validUntil);

        $legalNotice = self::legalNotice($kind, $books);
        $footerNotice = self::footerNotice($kind, $books);
        // Interne Hinweise (ohne Buchungswirkung) nie an den Kunden.
        if ($customerFacing && !$books) {
            $legalNotice = '';
            $footerNotice = '';
        }

        $kleinunternehmerHint = '';
        if ($books && $customerFacing) {
            $kleinunternehmerHint = CompanyExtendedSettings::kleinunternehmerHintForDate(
                (string) ($voucher['voucher_date'] ?? '')
            );
        }

        $depositBlock = null;
        $depositCfg = DocumentPresentationSettings::depositConfig();
        if (
            ($depositCfg['mode'] ?? DocumentPresentationSettings::DEPOSIT_NONE) !== DocumentPresentationSettings::DEPOSIT_NONE
            && in_array($kind, [VoucherDocumentKind::OFFER, VoucherDocumentKind::ORDER_CONFIRMATION], true)
        ) {
            $depositBlock = self::formatDepositBlock($depositCfg, $voucher);
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
            'customerFacing' => $customerFacing,
            'pageTitle' => $pageTitle,
            'companyBlock' => self::companyBlock(),
            'customerBlock' => self::customerBlock($voucher, $contact),
            'legalNotice' => $legalNotice,
            'footerNotice' => $footerNotice,
            'primaryBank' => self::primaryBankAccount(),
            'documentStatusLabel' => $customerFacing
                ? ''
                : VoucherDocumentStatus::label((string) ($voucher['document_status'] ?? '')),
            'legalClauseBlocks' => VoucherDocumentLegalClause::blocksForKeys(
                VoucherDocumentLegalClause::sanitizeSelection($voucher['document_legal_clauses'] ?? [])
            ),
            'paymentTermsText' => self::paymentTermsText($voucher),
            'logoUrl' => AppearanceSettings::logoUrl(),
            'logoAlt' => AppearanceSettings::logoAlt(),
            'logoShapeClass' => AppearanceSettings::logoShapeClass(),
            'mandatoryLines' => self::mandatoryLines(),
            'totalsBreakdown' => self::totalsBreakdown($items, $voucher),
            'introTextResolved' => $introText,
            'footerTextResolved' => $footerText,
            'validUntil' => $validUntil,
            'internalNotes' => $customerFacing ? '' : trim((string) ($voucher['notes'] ?? '')),
            'kleinunternehmerHint' => $kleinunternehmerHint,
            'depositBlock' => $depositBlock,
            'provenanceBlock' => self::provenanceBlock($voucher, $kind, $customerFacing),
            'signatureBlock' => self::signatureBlock($voucher, $kind, $customerFacing),
        ];
    }

    /**
     * Unterschriftsblock für manuelle Auftragsbestätigung (Ort, Datum, Auftraggeber/-nehmer).
     *
     * @param array<string, mixed> $voucher
     * @return array{
     *   place: string,
     *   date_label: string,
     *   client_name: string,
     *   contractor_name: string
     * }|null
     */
    public static function signatureBlock(array $voucher, string $kind, bool $customerFacing): ?array
    {
        if (VoucherDocumentKind::sanitize($kind) !== VoucherDocumentKind::ORDER_CONFIRMATION) {
            return null;
        }
        // Mail-Annahme: kein handschriftlicher Unterschriftsblock nötig.
        if (self::parseAcceptanceMeta($voucher) !== null) {
            return null;
        }

        $company = CompanySettings::config();
        $place = trim((string) ($company['city'] ?? ''));
        $dateRaw = trim((string) ($voucher['voucher_date'] ?? ''));
        $dateLabel = $dateRaw !== ''
            ? date('d.m.Y', strtotime($dateRaw) ?: time())
            : date('d.m.Y');

        $client = trim((string) ($voucher['supplier_name'] ?? ''));
        if ($client === '') {
            $contactId = (int) ($voucher['contact_id'] ?? 0);
            if ($contactId > 0) {
                $contact = ContactRepository::findById($contactId);
                if ($contact !== null) {
                    $client = trim($contact->companyName);
                    if ($client === '') {
                        $client = trim($contact->displayName);
                    }
                }
            }
        }

        $contractor = trim((string) (CompanyExtendedSettings::config()['legal_name'] ?? ''));
        if ($contractor === '') {
            $contractor = CompanySettings::displayName();
        }

        return [
            'place' => $place,
            'date_label' => $dateLabel,
            'client_name' => $client,
            'contractor_name' => $contractor,
        ];
    }

    /**
     * Herkunft / Annahme — Kunden-PDF und intern.
     *
     * @param array<string, mixed> $voucher
     * @return array{lines: list<string>, link_url: string, link_label: string}|null
     */
    public static function provenanceBlock(array $voucher, string $kind, bool $customerFacing): ?array
    {
        $kind = VoucherDocumentKind::sanitize($kind);
        $lines = [];
        $linkUrl = '';
        $linkLabel = '';

        $createdAt = trim((string) ($voucher['created_at'] ?? ''));
        $createdById = (int) ($voucher['created_by'] ?? 0);
        $createdByName = '';
        if ($createdById > 0) {
            $user = UserRepository::findById($createdById);
            if ($user !== null) {
                $createdByName = trim($user->displayName);
                if ($createdByName === '') {
                    $createdByName = trim($user->username);
                }
            }
        }
        $createdAtLabel = $createdAt !== ''
            ? date('d.m.Y H:i', strtotime($createdAt) ?: time())
            : '';

        $acceptance = self::parseAcceptanceMeta($voucher);
        if ($acceptance !== null) {
            $when = (string) ($acceptance['at_label'] ?? '');
            $how = (string) ($acceptance['channel_label'] ?? 'E-Mail');
            $who = (string) ($acceptance['by_label'] ?? '');
            $line = 'Angebot angenommen';
            if ($when !== '') {
                $line .= ' am ' . $when;
            }
            if ($how !== '') {
                $line .= ' per ' . $how;
            }
            if ($who !== '') {
                $line .= ' von ' . $who;
            }
            $lines[] = $line . '.';
            $linkUrl = (string) ($acceptance['link_url'] ?? '');
            $linkLabel = (string) ($acceptance['link_label'] ?? 'Zur E-Mail');
        } elseif (in_array($kind, [VoucherDocumentKind::ORDER_CONFIRMATION, VoucherDocumentKind::OFFER], true)) {
            $line = $kind === VoucherDocumentKind::ORDER_CONFIRMATION
                ? 'Auftragsbestätigung manuell erstellt'
                : 'Angebot erstellt';
            if ($createdAtLabel !== '') {
                $line .= ' am ' . $createdAtLabel;
            }
            if ($createdByName !== '') {
                $line .= ' von ' . $createdByName;
            }
            $lines[] = $line . '.';
        }

        $parentId = (int) ($voucher['parent_voucher_id'] ?? 0);
        if ($kind === VoucherDocumentKind::ORDER_CONFIRMATION && $parentId > 0 && $linkUrl === '') {
            $linkUrl = '/app?page=buchhaltung-beleg-form&action=edit&id=' . $parentId;
            $linkLabel = 'Zum Angebot';
        }

        // Kunden-PDF: Annahme-Vermerk ja; interne CRM-Links nur wenn nicht kundenorientiert
        // bzw. Link zur Annahme-Mail wenn öffentlich/erreichbar — Mail-Log bleibt intern.
        if ($customerFacing && $acceptance !== null && ($acceptance['channel'] ?? '') === 'mail') {
            // Link zur Mail nur intern; Kunde sieht Text ohne CRM-URL
            $linkUrl = '';
            $linkLabel = '';
        } elseif ($customerFacing && str_starts_with($linkUrl, '/app')) {
            $linkUrl = '';
            $linkLabel = '';
        }

        if ($lines === []) {
            return null;
        }

        return [
            'lines' => $lines,
            'link_url' => $linkUrl,
            'link_label' => $linkLabel,
        ];
    }

    /**
     * @param array<string, mixed> $voucher
     * @return array<string, mixed>|null
     */
    public static function parseAcceptanceMeta(array $voucher): ?array
    {
        $raw = $voucher['document_acceptance'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($raw) || $raw === []) {
            return null;
        }
        $channel = strtolower(trim((string) ($raw['channel'] ?? 'mail')));
        $at = trim((string) ($raw['at'] ?? ''));
        $by = trim((string) ($raw['by_name'] ?? $raw['by_email'] ?? ''));
        $mailId = (int) ($raw['mail_log_id'] ?? 0);

        return [
            'channel' => $channel,
            'channel_label' => $channel === 'mail' ? 'E-Mail' : ($channel === 'manual' ? 'manuelle Erfassung' : $channel),
            'at_label' => $at !== '' ? date('d.m.Y H:i', strtotime($at) ?: time()) : '',
            'by_label' => $by,
            'link_url' => $mailId > 0 ? '/app?page=post&mail_id=' . $mailId : '',
            'link_label' => $mailId > 0 ? 'Zur Annahme-E-Mail' : '',
        ];
    }

    /**
     * @param array<string, mixed> $voucher
     */
    public static function offerValidUntil(array $voucher, string $kind = ''): string
    {
        if ($kind === '') {
            $kind = (string) ($voucher['document_kind'] ?? '');
        }
        if (VoucherDocumentKind::sanitize($kind) !== VoucherDocumentKind::OFFER) {
            return '';
        }
        $raw = trim((string) ($voucher['delivery_date'] ?? ''));
        if ($raw === '') {
            $base = trim((string) ($voucher['voucher_date'] ?? ''));
            if ($base !== '') {
                return DocumentPresentationSettings::defaultOfferValidUntilDate($base);
            }

            return '';
        }

        return $raw;
    }

    public static function applyDocumentPlaceholders(string $text, string $validUntilYmd): string
    {
        if ($text === '') {
            return '';
        }
        $formatted = $validUntilYmd !== ''
            ? date('d.m.Y', strtotime($validUntilYmd) ?: time())
            : '—';

        $text = str_replace('{valid_until}', $formatted, $text);
        // Alte Angebote: festes Datum hinter „gültig bis …“ an aktuelles Feld anpassen
        if ($formatted !== '—') {
            $replaced = preg_replace(
                '/(gültig\s+bis(?:\s+zum)?\s*)(\d{1,2}\.\d{1,2}\.\d{2,4})/iu',
                '${1}' . $formatted,
                $text,
                1
            );
            if (is_string($replaced)) {
                $text = $replaced;
            }
        }

        return $text;
    }

    /**
     * Transparente Summen: Netto + USt je Satz = Brutto.
     *
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $voucher
     * @return array{
     *   net: float,
     *   tax: float,
     *   gross: float,
     *   by_rate: list<array{rate: int, net: float, tax: float, gross: float}>
     * }
     */
    public static function totalsBreakdown(array $items, array $voucher): array
    {
        $reverseCharge = VoucherReverseCharge::sanitizeType((string) ($voucher['reverse_charge_type'] ?? '')) !== '';
        /** @var array<int, array{rate: int, net: float, tax: float, gross: float}> $byRate */
        $byRate = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (trim((string) ($item['title'] ?? '')) === '') {
                continue;
            }
            $gross = VoucherRepository::parseMoney($item['gross_amount'] ?? 0);
            if ($gross == 0.0) {
                continue;
            }
            $rate = VoucherTaxKeys::sanitizeTaxRate((int) ($item['tax_rate'] ?? 19));
            $amounts = VoucherTaxKeys::calcLineAmounts(abs($gross), $rate, $reverseCharge);
            $sign = $gross < 0 ? -1.0 : 1.0;
            if (!isset($byRate[$rate])) {
                $byRate[$rate] = ['rate' => $rate, 'net' => 0.0, 'tax' => 0.0, 'gross' => 0.0];
            }
            $byRate[$rate]['net'] = round($byRate[$rate]['net'] + $sign * (float) $amounts['net_amount'], 2);
            $byRate[$rate]['tax'] = round($byRate[$rate]['tax'] + $sign * (float) $amounts['tax_amount'], 2);
            $byRate[$rate]['gross'] = round($byRate[$rate]['gross'] + $sign * (float) $amounts['gross_amount'], 2);
        }

        ksort($byRate);
        $rows = array_values($byRate);
        $net = 0.0;
        $tax = 0.0;
        $gross = 0.0;
        foreach ($rows as $row) {
            $net += $row['net'];
            $tax += $row['tax'];
            $gross += $row['gross'];
        }

        if ($rows === []) {
            $headerGross = VoucherRepository::parseMoney($voucher['gross_amount'] ?? 0);
            $headerNet = VoucherRepository::parseMoney($voucher['net_amount'] ?? 0);
            $headerTax = VoucherRepository::parseMoney($voucher['tax_amount'] ?? 0);
            $headerRate = VoucherTaxKeys::sanitizeTaxRate((int) ($voucher['tax_rate'] ?? 19));
            if ($headerGross != 0.0 || $headerNet != 0.0) {
                $rows[] = [
                    'rate' => $headerRate,
                    'net' => $headerNet,
                    'tax' => $headerTax,
                    'gross' => $headerGross,
                ];
                $net = $headerNet;
                $tax = $headerTax;
                $gross = $headerGross;
            }
        }

        return [
            'net' => round($net, 2),
            'tax' => round($tax, 2),
            'gross' => round($gross, 2),
            'by_rate' => $rows,
        ];
    }

    /**
     * @param array{mode: string, percent: float, fixed_amount: float, label: string, text: string} $cfg
     * @param array<string, mixed> $voucher
     * @return array{label: string, amount_label: string, text: string}|null
     */
    public static function formatDepositBlock(array $cfg, array $voucher): ?array
    {
        $computed = DocumentPresentationSettings::depositAmountForVoucher($voucher);
        if ($computed === null) {
            return null;
        }

        $mode = (string) ($computed['mode'] ?? DocumentPresentationSettings::DEPOSIT_NONE);
        $amount = (float) ($computed['amount'] ?? 0);
        $hint = (string) ($computed['hint'] ?? '');
        $amountLabel = '';

        if ($mode === DocumentPresentationSettings::DEPOSIT_PERCENT) {
            $pct = (float) (DocumentPresentationSettings::depositConfig()['percent'] ?? 0);
            $amountLabel = number_format($pct, $pct == floor($pct) ? 0 : 2, ',', '.') . ' %'
                . ($amount > 0 ? ' (= ' . number_format($amount, 2, ',', '.') . ' €)' : '');
        } elseif ($mode === DocumentPresentationSettings::DEPOSIT_FIXED) {
            $amountLabel = number_format($amount, 2, ',', '.') . ' €';
        } elseif ($mode === DocumentPresentationSettings::DEPOSIT_MATERIAL) {
            $amountLabel = $amount > 0
                ? number_format($amount, 2, ',', '.') . ' € (Material / EK)'
                : ($hint !== '' ? $hint : 'Materialkosten nicht berechenbar');
            if ($amount > 0 && $hint !== '' && str_contains($hint, 'ohne Einkaufspreis')) {
                $amountLabel .= ' — Hinweis: ' . $hint;
            }
        }

        return [
            'label' => (string) ($computed['label'] ?? 'Anzahlung'),
            'amount_label' => $amountLabel,
            'text' => (string) ($cfg['text'] ?? ''),
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
        $est = trim((string) ($extended['tax_numbers']['est'] ?? $basic['tax_number'] ?? ''));
        if ($est !== '') {
            $lines[] = 'Steuernummer ' . $est;
        }
        $wId = trim((string) ($extended['tax_numbers']['wirtschafts_id'] ?? ''));
        if ($wId !== '') {
            $lines[] = 'Wirtschafts-ID ' . $wId;
        }
        $tradeCourt = trim((string) ($extended['trade_register']['court'] ?? ''));
        $tradeNumber = trim((string) ($extended['trade_register']['number'] ?? ''));
        if ($tradeCourt !== '' || $tradeNumber !== '') {
            $lines[] = trim($tradeCourt . ' ' . $tradeNumber);
        }
        $owner = self::primaryOwnerName($extended);

        return ['name' => $name, 'lines' => $lines, 'owner' => $owner];
    }

    /**
     * Pflichtangaben für Rechnungsfuß (§ 14 UStG ergänzend).
     *
     * @return list<string>
     */
    public static function mandatoryLines(): array
    {
        $extended = CompanyExtendedSettings::config();
        $basic = CompanySettings::config();
        $lines = [];

        $owner = self::primaryOwnerName($extended);
        if ($owner !== '') {
            $lines[] = 'Geschäftsführung / Inhaber: ' . $owner;
        }

        $est = trim((string) ($extended['tax_numbers']['est'] ?? $basic['tax_number'] ?? ''));
        if ($est !== '') {
            $lines[] = 'Steuernummer: ' . $est;
        }

        $ust = trim((string) ($extended['tax_numbers']['ust'] ?? $basic['vat_id'] ?? ''));
        if ($ust !== '') {
            $lines[] = 'USt-IdNr.: ' . $ust;
        }

        $court = trim((string) ($extended['trade_register']['court'] ?? ''));
        $number = trim((string) ($extended['trade_register']['number'] ?? ''));
        if ($court !== '' || $number !== '') {
            $lines[] = 'Handelsregister: ' . trim($court . ' ' . $number);
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $extended
     */
    private static function primaryOwnerName(array $extended): string
    {
        $owners = is_array($extended['owners'] ?? null) ? $extended['owners'] : [];
        foreach ($owners as $owner) {
            if (!is_array($owner)) {
                continue;
            }
            $name = trim((string) ($owner['name'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return '';
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

        $withIban = [];
        foreach ($accounts as $row) {
            if (!is_array($row)) {
                continue;
            }
            $iban = strtoupper(str_replace(' ', '', trim((string) ($row['iban'] ?? ''))));
            if ($iban === '') {
                continue;
            }
            $withIban[] = $row;
        }
        if ($withIban === []) {
            return null;
        }

        $chosen = null;
        foreach ($withIban as $row) {
            if (!empty($row['is_primary'])) {
                $chosen = $row;
                break;
            }
        }
        if ($chosen === null) {
            // Erstes Giro mit IBAN, sonst erste IBAN (auch Kreditkarte mit IBAN).
            foreach ($withIban as $row) {
                if (($row['type'] ?? '') === 'giro') {
                    $chosen = $row;
                    break;
                }
            }
            $chosen = $chosen ?? $withIban[0];
        }

        return [
            'holder' => trim((string) ($chosen['account_holder'] ?? $chosen['holder'] ?? '')),
            'iban' => strtoupper(str_replace(' ', '', trim((string) ($chosen['iban'] ?? '')))),
            'bank' => trim((string) ($chosen['bank_name'] ?? '')),
            'bic' => trim((string) ($chosen['bic'] ?? '')),
        ];
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
            VoucherDocumentKind::FINAL_INVOICE => 'Schlussrechnung — Positionen wie Auftrag, abzüglich bereits geleisteter Anzahlungen.',
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
