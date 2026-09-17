<?php
declare(strict_types=1);

/**
 * Darstellung der Belegkette (Kunden-PDF): Textvorlagen, Anzahlung, Kleinunternehmer § 19.
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
            'kleinunternehmer' => [
                'enabled' => false,
                'valid_from' => '',
                'valid_to' => '',
                'ended_early_at' => '',
                'hint_text' => 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.',
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

        $kuIn = is_array($input['kleinunternehmer'] ?? null) ? $input['kleinunternehmer'] : [];
        $enabled = !empty($kuIn['enabled']);
        $validFrom = self::sanitizeDate((string) ($kuIn['valid_from'] ?? ''));
        $validTo = self::sanitizeDate((string) ($kuIn['valid_to'] ?? ''));
        $endedEarly = self::sanitizeDate((string) ($kuIn['ended_early_at'] ?? ''));
        $hint = self::sanitizeText((string) ($kuIn['hint_text'] ?? $defaults['kleinunternehmer']['hint_text']));
        if ($hint === '') {
            $hint = (string) $defaults['kleinunternehmer']['hint_text'];
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
            'kleinunternehmer' => [
                'enabled' => $enabled,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'ended_early_at' => $endedEarly,
                'hint_text' => $hint,
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
            'kleinunternehmer' => [
                'enabled' => !empty($input['kleinunternehmer_enabled']),
                'valid_from' => (string) ($input['kleinunternehmer_valid_from'] ?? ''),
                'valid_to' => (string) ($input['kleinunternehmer_valid_to'] ?? ''),
                'ended_early_at' => (string) ($input['kleinunternehmer_ended_early_at'] ?? ''),
                'hint_text' => (string) ($input['kleinunternehmer_hint_text'] ?? ''),
            ],
        ]));
    }

    public static function offerValidDays(): int
    {
        return (int) self::forForm()['offer_valid_days'];
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

    /**
     * @return array{enabled: bool, valid_from: string, valid_to: string, ended_early_at: string, hint_text: string}
     */
    public static function kleinunternehmerConfig(): array
    {
        return self::forForm()['kleinunternehmer'];
    }

    /**
     * Prüft, ob am Belegdatum die Kleinunternehmerregelung aktiv ist.
     */
    public static function isKleinunternehmerActiveOn(?string $voucherDateYmd): bool
    {
        $cfg = self::kleinunternehmerConfig();
        if (empty($cfg['enabled'])) {
            return false;
        }
        $day = trim((string) $voucherDateYmd);
        if ($day === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            $day = date('Y-m-d');
        }
        $from = (string) ($cfg['valid_from'] ?? '');
        $to = (string) ($cfg['valid_to'] ?? '');
        $ended = (string) ($cfg['ended_early_at'] ?? '');
        if ($from !== '' && $day < $from) {
            return false;
        }
        if ($ended !== '' && $day >= $ended) {
            return false;
        }
        if ($to !== '' && $day > $to) {
            return false;
        }

        return true;
    }

    public static function kleinunternehmerHintForDate(?string $voucherDateYmd): string
    {
        if (!self::isKleinunternehmerActiveOn($voucherDateYmd)) {
            return '';
        }

        return trim((string) self::kleinunternehmerConfig()['hint_text']);
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

    private static function sanitizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        return $value;
    }
}
