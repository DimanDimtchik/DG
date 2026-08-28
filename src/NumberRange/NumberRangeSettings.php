<?php
declare(strict_types=1);

/**
 * Number Range Settings.
 */
final class NumberRangeSettings
{
    public const STORE_KEY = 'number_ranges';

    /**
     * documentTypes.
     *
     * @return array<string, string>
     */
        public static function documentTypes(): array
    {
        return [
            'offer' => 'Angebot',
            'delivery_note' => 'Lieferschein',
            'order_confirmation' => 'Auftragsbestätigung',
            'partial_invoice' => 'Abschlagsrechnung',
            'invoice' => 'Rechnung',
            'final_invoice' => 'Schlussrechnung',
            'credit_note' => 'Kundengutschrift',
            'article' => 'Artikel',
            'service' => 'Leistung',
            'customer' => 'Kundennummer',
            'supplier' => 'Lieferantennummer',
        ];
    }

    /**
     * typeGroups.
     *
     * @return array<string, list<string>>
     */
        public static function typeGroups(): array
    {
        return [
            'Belege' => ['offer', 'order_confirmation', 'delivery_note', 'partial_invoice', 'invoice', 'final_invoice', 'credit_note'],
            'Stammdaten' => ['article', 'service', 'customer', 'supplier'],
        ];
    }

    /**
     * isMasterDataType
     * @param string $type
     * @return bool
     */
    public static function isMasterDataType(string $type): bool
    {
        return in_array($type, ['article', 'service', 'customer', 'supplier'], true);
    }

    /**
     * isValidType
     * @param string $type
     * @return bool
     */
    public static function isValidType(string $type): bool
    {
        return isset(self::documentTypes()[$type]);
    }

    /**
     * documentDefaults.
     *
     * @return array<string, mixed>
     */
        public static function documentDefaults(): array
    {
        return [
            'prefix' => '',
            'number_pattern' => '{NR}',
            'suffix' => '',
            'counter' => '1',
            'increment_part' => 'number',
            'number_display' => 'decimal',
            'number_pad' => 0,
            'country_code' => 'DE',
        ];
    }

    /**
     * allDefaults.
     *
     * @return array<string, array<string, mixed>>
     */
        public static function allDefaults(): array
    {
        $defaults = self::documentDefaults();
        $out = [];
        foreach (array_keys(self::documentTypes()) as $type) {
            $out[$type] = $defaults;
        }

        return $out;
    }

    /**
     * Liefert alle Einträge.
     *
     * @return array<string, array<string, mixed>>
     */
        public static function all(): array
    {
        if (!Database::isConfigured()) {
            return self::allDefaults();
        }

        $stored = SettingsStore::get(self::STORE_KEY, self::allDefaults());
        $out = [];
        foreach (self::documentTypes() as $type => $label) {
            $raw = is_array($stored[$type] ?? null) ? $stored[$type] : [];
            $out[$type] = self::sanitizeDocument($raw, $type);
        }

        return $out;
    }

        /**
     * document
     * @param string $type
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    public static function document(string $type): array
    {
        if (!self::isValidType($type)) {
            throw new InvalidArgumentException('Unbekannter Nummernkreis.');
        }

        $document = self::all()[$type];
        if (self::shouldApplyDatevDefaults($type, $document)) {
            $document = array_merge($document, self::datevDefaults($type));
        }

        return $document;
    }

    /**
     * preview
     * @param string $type
     * @return string
     */
    public static function preview(string $type): string
    {
        $document = self::document($type);
        $context = InvoiceNumberBuilder::previewContext($document);

        return InvoiceNumberBuilder::build($document, $context);
    }

        /**
     * allocateNext
     * @param string $type
     * @param bool $persist
     * @return array{number: string, sequence: int, sequence_display: string, next_sequence: int}
     */
    public static function allocateNext(string $type, bool $persist = true): array
    {
        $document = self::document($type);
        $peek = InvoiceNumberBuilder::peekNext($document, !$persist);
        if ($persist) {
            $document['counter'] = (string) $peek['next_sequence'];
            self::persistDocument($type, $document);
        }

        return $peek;
    }

        /**
     * saveDocument
     * @param string $type
     * @param array $input Formulardaten
     * @return void
     * @throws InvalidArgumentException
     */
    public static function saveDocument(string $type, array $input): void
    {
        if (!self::isValidType($type)) {
            throw new InvalidArgumentException('Unbekannter Nummernkreis.');
        }

        $previous = self::document($type);
        $all = self::all();
        $all[$type] = self::sanitizeDocument($input, $type);
        SettingsStore::set(self::STORE_KEY, $all);
        NumberRangeHistory::syncOnSave($type, $previous, $all[$type]);
    }

        /**
     * sanitizeDocument
     * @param array $raw Rohdaten
     * @param string|null $type
     * @return array<string, mixed>
     */
    public static function sanitizeDocument(array $raw, ?string $type = null): array
    {
        $defaults = self::documentDefaults();
        $validCountries = array_keys(CountryCodes::all());
        $parts = ['prefix', 'number', 'suffix'];

        $raw = InvoiceNumberTokens::migrateLegacyDocument($raw);
        $clean = $defaults;

        $clean['prefix'] = trim((string) ($raw['prefix'] ?? $clean['prefix']));
        $clean['number_pattern'] = trim((string) ($raw['number_pattern'] ?? $raw['number_part'] ?? $clean['number_pattern']));
        if ($clean['number_pattern'] === '') {
            $clean['number_pattern'] = '{NR}';
        }
        $clean['suffix'] = trim((string) ($raw['suffix'] ?? $clean['suffix']));
        $clean['counter'] = InvoiceNumberBuilder::sanitizeSequenceCounter($raw['counter'] ?? $raw['number'] ?? $clean['counter']);

        $inc = strtolower(trim((string) ($raw['increment_part'] ?? 'number')));
        $clean['increment_part'] = in_array($inc, $parts, true) ? $inc : 'number';

        $display = strtolower(trim((string) ($raw['number_display'] ?? 'decimal')));
        $clean['number_display'] = in_array($display, array_keys(InvoiceNumberTokens::numberBases()), true)
            ? $display
            : 'decimal';
        $clean['number_pad'] = max(0, min(12, (int) ($raw['number_pad'] ?? 0)));

        $countryCode = strtoupper(trim((string) ($raw['country_code'] ?? $clean['country_code'])));
        $clean['country_code'] = in_array($countryCode, $validCountries, true) ? $countryCode : 'DE';

        return $clean;
    }

        /**
     * datevDefaults
     * @param string $type
     * @return array<string, string>
     */
    private static function datevDefaults(string $type): array
    {
        return match ($type) {
            'customer' => ['counter' => '10000'],
            'supplier' => ['counter' => '70000'],
            default => [],
        };
    }

        /**
     * shouldApplyDatevDefaults
     * @param string $type
     * @param array $document
     * @return bool
     */
    private static function shouldApplyDatevDefaults(string $type, array $document): bool
    {
        if (!in_array($type, ['customer', 'supplier'], true)) {
            return false;
        }

        $defaults = self::documentDefaults();

        return trim((string) ($document['prefix'] ?? '')) === (string) $defaults['prefix']
            && trim((string) ($document['suffix'] ?? '')) === (string) $defaults['suffix']
            && trim((string) ($document['number_pattern'] ?? '{NR}')) === (string) $defaults['number_pattern']
            && (string) ($document['counter'] ?? '1') === '1';
    }

        /**
     * Speichert Formulardaten
     * @param string $type
     * @param array $post
     * @return void
     * @throws InvalidArgumentException
     */
    public static function saveFromPost(string $type, array $post): void
    {
        $raw = $post['number_range'] ?? [];
        if (!is_array($raw)) {
            throw new InvalidArgumentException('Ungültige Formulardaten.');
        }

        self::saveDocument($type, $raw);
    }

        /**
     * persistDocument
     * @param string $type
     * @param array $document
     * @return void
     */
    private static function persistDocument(string $type, array $document): void
    {
        $all = self::all();
        $all[$type] = self::sanitizeDocument($document, $type);
        SettingsStore::set(self::STORE_KEY, $all);
    }
}
