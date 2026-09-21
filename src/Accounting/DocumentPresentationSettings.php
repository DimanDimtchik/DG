<?php
declare(strict_types=1);

/**
 * Darstellung der Belegkette (Kunden-PDF): Textvorlagen, Anzahlung.
 * Kleinunternehmer § 19 und andere 0-%-Sonderfälle → Firmendaten (tax_special_cases).
 * Nicht Nummernkreise — eigene Einstellungen unter Buchhaltung → Belegdarstellung.
 */
final class DocumentPresentationSettings
{
    public const STORE_KEY = 'document_presentation';

    public const DEPOSIT_NONE = 'none';
    public const DEPOSIT_PERCENT = 'percent';
    public const DEPOSIT_FIXED = 'fixed';
    public const DEPOSIT_MATERIAL = 'material';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'offer_valid_days' => 14,
            'texts' => [
                VoucherDocumentKind::OFFER => [
                    'intro' => VoucherDocumentKind::defaultPositionIntroText(VoucherDocumentKind::OFFER),
                    'footer' => VoucherDocumentKind::defaultPositionFooterText(VoucherDocumentKind::OFFER),
                ],
                VoucherDocumentKind::ORDER_CONFIRMATION => [
                    'intro' => VoucherDocumentKind::defaultPositionIntroText(VoucherDocumentKind::ORDER_CONFIRMATION),
                    'footer' => VoucherDocumentKind::defaultPositionFooterText(VoucherDocumentKind::ORDER_CONFIRMATION),
                ],
                VoucherDocumentKind::DELIVERY_NOTE => [
                    'intro' => VoucherDocumentKind::defaultPositionIntroText(VoucherDocumentKind::DELIVERY_NOTE),
                    'footer' => VoucherDocumentKind::defaultPositionFooterText(VoucherDocumentKind::DELIVERY_NOTE),
                ],
                VoucherDocumentKind::PARTIAL_INVOICE => [
                    'intro' => VoucherDocumentKind::defaultPositionIntroText(VoucherDocumentKind::PARTIAL_INVOICE),
                    'footer' => '',
                ],
                VoucherDocumentKind::INVOICE => [
                    'intro' => VoucherDocumentKind::defaultPositionIntroText(VoucherDocumentKind::INVOICE),
                    'footer' => '',
                ],
                VoucherDocumentKind::FINAL_INVOICE => [
                    'intro' => VoucherDocumentKind::defaultPositionIntroText(VoucherDocumentKind::FINAL_INVOICE),
                    'footer' => '',
                ],
            ],
            'deposit' => [
                'mode' => self::DEPOSIT_NONE,
                'percent' => 30.0,
                'fixed_amount' => 0.0,
                'label' => 'Anzahlung / Abschlag',
                'text' => 'Nach Auftragsbestätigung ist eine Anzahlung fällig. Der Restbetrag wird nach Leistungserbringung berechnet.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        $stored = SettingsStore::get(self::STORE_KEY, self::defaults());
        if (!is_array($stored)) {
            $stored = [];
        }

        return self::sanitize($stored);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function sanitize(array $input): array
    {
        $defaults = self::defaults();
        $offerDays = (int) ($input['offer_valid_days'] ?? $defaults['offer_valid_days']);
        if ($offerDays < 1) {
            $offerDays = 1;
        }
        if ($offerDays > 365) {
            $offerDays = 365;
        }

        $texts = [];
        $defaultTexts = is_array($defaults['texts']) ? $defaults['texts'] : [];
        $inputTexts = is_array($input['texts'] ?? null) ? $input['texts'] : [];
        foreach ($defaultTexts as $kind => $pair) {
            $row = is_array($inputTexts[$kind] ?? null) ? $inputTexts[$kind] : [];
            $texts[$kind] = [
                'intro' => self::sanitizeText((string) ($row['intro'] ?? $pair['intro'] ?? '')),
                'footer' => self::sanitizeText((string) ($row['footer'] ?? $pair['footer'] ?? '')),
            ];
        }

        $depositIn = is_array($input['deposit'] ?? null) ? $input['deposit'] : [];
        $mode = (string) ($depositIn['mode'] ?? self::DEPOSIT_NONE);
        if (!in_array($mode, [self::DEPOSIT_NONE, self::DEPOSIT_PERCENT, self::DEPOSIT_FIXED, self::DEPOSIT_MATERIAL], true)) {
            $mode = self::DEPOSIT_NONE;
        }
        $percent = (float) str_replace(',', '.', (string) ($depositIn['percent'] ?? 30));
        if ($percent < 0) {
            $percent = 0.0;
        }
        if ($percent > 100) {
            $percent = 100.0;
        }
        $fixed = round((float) str_replace(',', '.', (string) ($depositIn['fixed_amount'] ?? 0)), 2);
        if ($fixed < 0) {
            $fixed = 0.0;
        }

        return [
            'offer_valid_days' => $offerDays,
            'texts' => $texts,
            'deposit' => [
                'mode' => $mode,
                'percent' => round($percent, 2),
                'fixed_amount' => $fixed,
                'label' => self::sanitizeText((string) ($depositIn['label'] ?? $defaults['deposit']['label'])) ?: (string) $defaults['deposit']['label'],
                'text' => self::sanitizeText((string) ($depositIn['text'] ?? $defaults['deposit']['text'])),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function saveFromPost(array $input): void
    {
        $texts = [];
        $postedTexts = is_array($input['texts'] ?? null) ? $input['texts'] : [];
        foreach (array_keys(self::defaults()['texts']) as $kind) {
            $row = is_array($postedTexts[$kind] ?? null) ? $postedTexts[$kind] : [];
            $texts[$kind] = [
                'intro' => (string) ($row['intro'] ?? ''),
                'footer' => (string) ($row['footer'] ?? ''),
            ];
        }

        SettingsStore::set(self::STORE_KEY, self::sanitize([
            'offer_valid_days' => (int) ($input['offer_valid_days'] ?? 14),
            'texts' => $texts,
            'deposit' => [
                'mode' => (string) ($input['deposit_mode'] ?? self::DEPOSIT_NONE),
                'percent' => (string) ($input['deposit_percent'] ?? '30'),
                'fixed_amount' => (string) ($input['deposit_fixed_amount'] ?? '0'),
                'label' => (string) ($input['deposit_label'] ?? ''),
                'text' => (string) ($input['deposit_text'] ?? ''),
            ],
        ]));
    }

    public static function offerValidDays(): int
    {
        return (int) self::forForm()['offer_valid_days'];
    }

    /**
     * Standard-Gültigkeitsdatum: Belegdatum + offer_valid_days.
     */
    public static function defaultOfferValidUntilDate(?string $fromYmd = null): string
    {
        $base = trim((string) $fromYmd);
        if ($base === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $base)) {
            $base = date('Y-m-d');
        }
        $days = self::offerValidDays();
        $ts = strtotime($base . ' +' . $days . ' days');

        return $ts !== false ? date('Y-m-d', $ts) : $base;
    }

    public static function defaultIntro(string $documentKind): string
    {
        $kind = VoucherDocumentKind::sanitize($documentKind);
        $cfg = self::forForm();
        $text = trim((string) ($cfg['texts'][$kind]['intro'] ?? ''));

        return $text !== '' ? $text : VoucherDocumentKind::defaultPositionIntroText($kind);
    }

    public static function defaultFooter(string $documentKind): string
    {
        $kind = VoucherDocumentKind::sanitize($documentKind);
        $cfg = self::forForm();
        $text = trim((string) ($cfg['texts'][$kind]['footer'] ?? ''));

        return $text !== '' ? $text : VoucherDocumentKind::defaultPositionFooterText($kind);
    }

    /**
     * @return array{mode: string, percent: float, fixed_amount: float, label: string, text: string}
     */
    public static function depositConfig(): array
    {
        return self::forForm()['deposit'];
    }

    /** Anzahlung in Belegdarstellung aktiv (%, fest oder material). */
    public static function depositRequired(): bool
    {
        $mode = (string) (self::depositConfig()['mode'] ?? self::DEPOSIT_NONE);

        return $mode !== self::DEPOSIT_NONE;
    }

    /**
     * Berechnet den Anzahlungsbetrag (Brutto-Ziel) für einen Beleg.
     *
     * @param array<string, mixed> $voucher findById-/Form-Daten inkl. items
     * @return array{amount: float, mode: string, label: string, hint: string}|null
     */
    public static function depositAmountForVoucher(array $voucher): ?array
    {
        $cfg = self::depositConfig();
        $mode = (string) ($cfg['mode'] ?? self::DEPOSIT_NONE);
        if ($mode === self::DEPOSIT_NONE) {
            return null;
        }

        $label = (string) ($cfg['label'] ?? 'Anzahlung');
        $gross = VoucherRepository::parseMoney($voucher['gross_amount'] ?? 0);
        $amount = 0.0;
        $hint = '';

        if ($mode === self::DEPOSIT_PERCENT) {
            $pct = (float) ($cfg['percent'] ?? 0);
            $amount = round($gross * $pct / 100, 2);
            $hint = number_format($pct, $pct == floor($pct) ? 0 : 2, ',', '.') . ' % vom Auftrag';
        } elseif ($mode === self::DEPOSIT_FIXED) {
            $amount = round((float) ($cfg['fixed_amount'] ?? 0), 2);
            $hint = 'fester Betrag';
        } elseif ($mode === self::DEPOSIT_MATERIAL) {
            $items = is_array($voucher['items'] ?? null) ? $voucher['items'] : [];
            $mat = DepositMaterialCostService::fromVoucherItems($items);
            $amount = round((float) ($mat['amount'] ?? 0), 2);
            $hint = 'Material / EK';
            if ((int) ($mat['lines_missing'] ?? 0) > 0) {
                $hint .= ' — ' . (int) $mat['lines_missing'] . ' Position(en) ohne Einkaufspreis';
            }
            if ((int) ($mat['lines_priced'] ?? 0) === 0) {
                $amount = 0.0;
                $hint = 'Materialkosten nicht berechenbar (keine EK-Preise)';
            }
        }

        if ($amount <= 0.0) {
            return [
                'amount' => 0.0,
                'mode' => $mode,
                'label' => $label,
                'hint' => $hint !== '' ? $hint : 'Anzahlung 0 €',
            ];
        }

        // Nie mehr als Auftragsbrutto verlangen.
        if ($gross > 0.0 && $amount > $gross) {
            $amount = $gross;
        }

        return [
            'amount' => $amount,
            'mode' => $mode,
            'label' => $label,
            'hint' => $hint,
        ];
    }

    private static function sanitizeText(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = trim($text);
        if (mb_strlen($text) > 4000) {
            $text = mb_substr($text, 0, 4000);
        }

        return $text;
    }
}
