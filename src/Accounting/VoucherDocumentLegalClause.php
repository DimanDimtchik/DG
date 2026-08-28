<?php
declare(strict_types=1);

/** Vordefinierte gesetzliche Hinweistexte für Ausgangsrechnungen (§19, §13b, PV, …). */
final class VoucherDocumentLegalClause
{
    public const SMALL_BUSINESS_19 = 'small_business_19';
    public const REVERSE_CHARGE_13B = 'reverse_charge_13b';
    public const REVERSE_CHARGE_13B_CONSTRUCTION = 'reverse_charge_13b_construction';
    public const REVERSE_CHARGE_13B_EU = 'reverse_charge_13b_eu';
    public const PHOTOVOLTAIC_12_3 = 'photovoltaic_12_3';
    public const INTRA_COMMUNITY = 'intra_community_4_1b';
    public const EXPORT_DELIVERY = 'export_4_1a';
    public const TAX_EXEMPT_4 = 'tax_exempt_4';
    public const MARGIN_SCHEME_25A = 'margin_scheme_25a';

    /**
     * @return array<string, array{label: string, hint: string, text: string, group: string}>
     */
    public static function catalog(): array
    {
        return [
            self::SMALL_BUSINESS_19 => [
                'label' => 'Kleinunternehmer § 19 UStG',
                'hint' => 'Kein Umsatzsteuerausweis — nur wenn tatsächlich Kleinunternehmer.',
                'group' => 'Steuerbefreiung',
                'text' => 'Gemäß § 19 Abs. 1 UStG wird keine Umsatzsteuer berechnet und ausgewiesen (Kleinunternehmerregelung).',
            ],
            self::PHOTOVOLTAIC_12_3 => [
                'label' => 'Photovoltaik § 12 Abs. 3 UStG',
                'hint' => 'Steuerbefreite Lieferung/Installation von Photovoltaikanlagen (§ 3g UStG).',
                'group' => 'Steuerbefreiung',
                'text' => 'Die Lieferung und Installation der Photovoltaikanlage ist umsatzsteuerbefreit nach § 12 Abs. 3 UStG i. V. m. § 3g UStG.',
            ],
            self::TAX_EXEMPT_4 => [
                'label' => 'Steuerfreie Leistung § 4 UStG',
                'hint' => 'Allgemeiner Hinweis bei steuerfreien Umsätzen nach § 4 UStG.',
                'group' => 'Steuerbefreiung',
                'text' => 'Die Leistung ist umsatzsteuerfrei gemäß § 4 UStG.',
            ],
            self::INTRA_COMMUNITY => [
                'label' => 'Innergemeinschaftliche Lieferung § 4 Nr. 1b',
                'hint' => 'Warenlieferung in ein anderes EU-Land an Unternehmer mit gültiger USt-IdNr.',
                'group' => 'EU / Ausland',
                'text' => 'Innergemeinschaftliche Lieferung gemäß § 4 Nr. 1b i. V. m. § 6a UStG.',
            ],
            self::EXPORT_DELIVERY => [
                'label' => 'Ausfuhrlieferung § 4 Nr. 1a',
                'hint' => 'Warenlieferung in ein Drittland.',
                'group' => 'EU / Ausland',
                'text' => 'Ausfuhrlieferung gemäß § 4 Nr. 1a i. V. m. § 6 UStG — steuerfrei.',
            ],
            self::REVERSE_CHARGE_13B => [
                'label' => 'Reverse Charge § 13b UStG (allgemein)',
                'hint' => 'Steuerschuldnerschaft des Leistungsempfängers — z. B. bestimmte Drittlands-/Sachverhalte.',
                'group' => 'Reverse Charge',
                'text' => 'Steuerschuldner für die Umsatzsteuer ist der Leistungsempfänger gemäß § 13b UStG.',
            ],
            self::REVERSE_CHARGE_13B_EU => [
                'label' => 'Reverse Charge § 13b (EU-Leistung)',
                'hint' => 'Grenzüberschreitende sonstige Leistung an Unternehmer im EU-Ausland.',
                'group' => 'Reverse Charge',
                'text' => 'Grenzüberschreitende sonstige Leistung — Steuerschuldnerschaft des Leistungsempfängers gemäß § 13b UStG (Reverse-Charge-Verfahren).',
            ],
            self::REVERSE_CHARGE_13B_CONSTRUCTION => [
                'label' => 'Reverse Charge § 13b (Bauleistung)',
                'hint' => 'Bauleistungen im Inland — Steuerschuldnerschaft des Auftraggebers.',
                'group' => 'Reverse Charge',
                'text' => 'Bauleistung im Sinne des § 13b Abs. 2 Nr. 4 UStG — Steuerschuldnerschaft des Leistungsempfängers gemäß § 13b UStG.',
            ],
            self::MARGIN_SCHEME_25A => [
                'label' => 'Differenzbesteuerung § 25a UStG',
                'hint' => 'Gebrauchtwaren, Kunsterzeugnisse, Sammlungsstücke, Antiquitäten.',
                'group' => 'Sonderverfahren',
                'text' => 'Es gilt die Differenzbesteuerung gemäß § 25a UStG; die Umsatzsteuer ist in den Preisen enthalten und wird nicht gesondert ausgewiesen.',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::catalog() as $key => $meta) {
            $options[$key] = (string) ($meta['label'] ?? $key);
        }

        return $options;
    }

    /**
     * @param list<string>|string|null $raw
     * @return list<string>
     */
    public static function sanitizeSelection(mixed $raw): array
    {
        $keys = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = preg_split('/\s*,\s*/', $raw) ?: [];
            }
        }
        if (!is_array($raw)) {
            return [];
        }

        $catalog = self::catalog();
        foreach ($raw as $key) {
            $key = strtolower(trim((string) $key));
            if ($key !== '' && isset($catalog[$key])) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string>
     */
    public static function parseFromRequest(array $data): array
    {
        $raw = $data['document_legal_clauses'] ?? [];

        return self::sanitizeSelection(is_array($raw) ? $raw : []);
    }

    /**
     * @return list<string>
     */
    public static function textsForKeys(array $keys): array
    {
        $catalog = self::catalog();
        $texts = [];
        foreach (self::sanitizeSelection($keys) as $key) {
            $text = trim((string) ($catalog[$key]['text'] ?? ''));
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    public static function textForKey(string $key): string
    {
        $texts = self::textsForKeys([$key]);

        return $texts[0] ?? '';
    }

    /**
     * @param list<string> $keys
     * @return list<array{key: string, label: string, text: string}>
     */
    public static function blocksForKeys(array $keys): array
    {
        $catalog = self::catalog();
        $blocks = [];
        foreach (self::sanitizeSelection($keys) as $key) {
            $blocks[] = [
                'key' => $key,
                'label' => (string) ($catalog[$key]['label'] ?? $key),
                'text' => (string) ($catalog[$key]['text'] ?? ''),
            ];
        }

        return $blocks;
    }

    public static function composeFooter(string $freeText, array $keys): string
    {
        $parts = [];
        $freeText = trim($freeText);
        if ($freeText !== '') {
            $parts[] = $freeText;
        }
        foreach (self::textsForKeys($keys) as $text) {
            $parts[] = $text;
        }

        return implode("\n\n", $parts);
    }

    /**
     * @return array<string, mixed>
     */
    public static function clientConfig(): array
    {
        $groups = [];
        foreach (self::catalog() as $key => $meta) {
            $groups[] = [
                'key' => $key,
                'label' => (string) ($meta['label'] ?? $key),
                'hint' => (string) ($meta['hint'] ?? ''),
                'text' => (string) ($meta['text'] ?? ''),
                'group' => (string) ($meta['group'] ?? 'Sonstiges'),
            ];
        }

        return [
            'clauses' => $groups,
            'reverseChargeSuggestions' => [
                VoucherReverseCharge::TYPE_CONSTRUCTION => [self::REVERSE_CHARGE_13B_CONSTRUCTION],
                VoucherReverseCharge::TYPE_EU => [self::REVERSE_CHARGE_13B_EU],
                VoucherReverseCharge::TYPE_THIRD_COUNTRY => [self::REVERSE_CHARGE_13B],
            ],
        ];
    }

    /**
     * Vorschläge aus Belegdaten (Reverse Charge, Positionen).
     *
     * @param array<string, mixed> $voucher
     * @return list<string>
     */
    public static function suggestedKeys(array $voucher): array
    {
        $suggested = [];
        $reverseChargeType = VoucherReverseCharge::sanitizeType((string) ($voucher['reverse_charge_type'] ?? ''));
        if ($reverseChargeType === VoucherReverseCharge::TYPE_CONSTRUCTION) {
            $suggested[] = self::REVERSE_CHARGE_13B_CONSTRUCTION;
        } elseif ($reverseChargeType === VoucherReverseCharge::TYPE_EU) {
            $suggested[] = self::REVERSE_CHARGE_13B_EU;
        } elseif ($reverseChargeType === VoucherReverseCharge::TYPE_THIRD_COUNTRY) {
            $suggested[] = self::REVERSE_CHARGE_13B;
        }

        $items = is_array($voucher['items'] ?? null) ? $voucher['items'] : [];
        if ($items === [] && (int) ($voucher['id'] ?? 0) > 0) {
            $items = VoucherRepository::itemsForVoucher((int) $voucher['id']);
        }
        $taxRates = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $taxRates[(int) ($item['tax_rate'] ?? 19)] = true;
        }
        if ($taxRates === [0 => true] && count($taxRates) === 1 && $reverseChargeType === '') {
            // 0 % allein — Hinweis, keine Auto-Auswahl (Kleinunternehmer vs. PV)
        }

        return array_values(array_unique($suggested));
    }

    public static function encodeSelection(array $keys): ?string
    {
        $keys = self::sanitizeSelection($keys);
        if ($keys === []) {
            return null;
        }

        return json_encode($keys, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
