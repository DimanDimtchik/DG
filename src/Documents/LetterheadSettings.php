<?php

declare(strict_types=1);

/**
 * Briefpapier / Schreibendesign (Dokumentenverfasser B1).
 * JSON in SettingsStore — keine Extra-Tabelle.
 *
 * DIN 5008 = Zonen/Maße (Default-Layout).
 * Gesetzliche Pflichtangaben + empfohlene Felder = Sichtbarkeit + Soft-Check (B3).
 */
final class LetterheadSettings
{
    public const STORE_KEY = 'letterhead';

    /** DIN-Fenster: Breite mm */
    public const ADDRESS_WINDOW_WIDTH_MM = 85.0;

    /** DIN-Fenster: Höhe mm */
    public const ADDRESS_WINDOW_HEIGHT_MM = 45.0;

    /** DIN: linker Rand zum Fenster mm */
    public const ADDRESS_WINDOW_LEFT_MM = 20.0;

    /**
     * Standardwerte (DIN-orientiert, empfohlene Fußangaben an).
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'page' => [
                'format' => 'A4',
                'margin_top_mm' => 17.0,
                'margin_right_mm' => 20.0,
                'margin_bottom_mm' => 16.7,
                'margin_left_mm' => 24.1,
                'show_din_guides' => false,
            ],
            'header' => [
                'show' => true,
                'show_company_name' => true,
                'show_logo' => true,
                'logo_position' => 'left',
                'logo_max_height_mm' => 18.0,
                'logo_media_id' => '',
                /** DIN Briefkopf Form A ≈ 27 mm, Form B ≈ 45 mm */
                'height_form' => 'B',
            ],
            'sender_line' => [
                'show' => true,
                'font_size_pt' => 8.0,
            ],
            'address_window' => [
                'show' => true,
                'left_mm' => self::ADDRESS_WINDOW_LEFT_MM,
                'top_mm' => 50.0,
                'width_mm' => self::ADDRESS_WINDOW_WIDTH_MM,
                'height_mm' => self::ADDRESS_WINDOW_HEIGHT_MM,
            ],
            'info_block' => [
                'show' => true,
                'fields' => self::defaultInfoFields(),
            ],
            'subject' => [
                'bold' => true,
                'show_label' => false,
            ],
            'footer' => [
                'show' => true,
                'font_size_pt' => 7.0,
                'show_rule' => true,
                'columns' => 3,
                'show_legal_mandatory' => true,
                'show_address' => true,
                'show_phone' => true,
                'show_email' => true,
                'show_website' => true,
                'show_bank' => true,
                'show_tax_number' => true,
                'show_vat_id' => true,
                'show_links' => true,
            ],
            'links' => [
                'impressum' => '',
                'datenschutz' => '',
                'agb' => '',
                'widerruf' => '',
            ],
            'background' => [
                'mode' => 'none',
                'color' => '',
                'media_id' => '',
                'opacity' => 0.08,
                'fit' => 'contain',
                'all_pages' => true,
            ],
            'export_formats' => [
                'pdf' => true,
                'txt' => true,
                'md' => false,
                'docx' => false,
            ],
            /** Ergänzungen, falls Firmendaten unvollständig (nicht doppelpflegen wenn Firma gefüllt) */
            'legal_overrides' => [
                'seat' => '',
                'share_capital' => '',
                'outstanding_contributions' => '',
                'supervisory_chair' => '',
                'managing_directors' => '',
            ],
        ];
    }

    /**
     * Infoblock-Felder (Ihre / Unsere Seite).
     *
     * @return array<string, array{show: bool, label: string, order: int}>
     */
    public static function defaultInfoFields(): array
    {
        return [
            'their_ref' => ['show' => true, 'label' => 'Ihr Zeichen', 'order' => 10],
            'their_date' => ['show' => true, 'label' => 'Ihre Nachricht vom', 'order' => 20],
            'their_handler' => ['show' => true, 'label' => 'Ihr Ansprechpartner', 'order' => 30],
            'their_case_no' => ['show' => true, 'label' => 'Ihre Bearbeitungsnummer', 'order' => 40],
            'our_ref' => ['show' => true, 'label' => 'Unser Zeichen', 'order' => 50],
            'our_handler' => ['show' => true, 'label' => 'Ansprechpartner', 'order' => 60],
            'our_department' => ['show' => true, 'label' => 'Abteilung', 'order' => 70],
            'our_case_no' => ['show' => true, 'label' => 'Bearbeitungsnummer', 'order' => 80],
            'our_phone' => ['show' => true, 'label' => 'Telefon', 'order' => 90],
            'our_email' => ['show' => true, 'label' => 'E-Mail', 'order' => 100],
            'date' => ['show' => true, 'label' => 'Datum', 'order' => 110],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        $defaults = self::defaults();
        if (!Database::isConfigured()) {
            return $defaults;
        }

        try {
            $stored = SettingsStore::get(self::STORE_KEY, []);
        } catch (Throwable) {
            return $defaults;
        }

        return self::deepMerge($defaults, is_array($stored) ? $stored : []);
    }

    /**
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        return self::config();
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function saveFromPost(array $input): void
    {
        SettingsStore::set(self::STORE_KEY, self::sanitize($input, true));
    }

    /**
     * Aktive Exportformate (Schlüssel => true).
     *
     * @return list<string>
     */
    public static function enabledExportFormats(?array $cfg = null): array
    {
        $cfg = $cfg ?? self::config();
        $formats = is_array($cfg['export_formats'] ?? null) ? $cfg['export_formats'] : [];
        $out = [];
        foreach (['pdf', 'txt', 'md', 'docx'] as $key) {
            if (!empty($formats[$key])) {
                $out[] = $key;
            }
        }
        if ($out === []) {
            $out[] = 'pdf';
        }

        return $out;
    }

    /**
     * Infoblock-Felder sortiert (nur sichtbare, wenn $onlyVisible).
     *
     * @return list<array{key: string, show: bool, label: string, order: int}>
     */
    public static function infoFieldsSorted(?array $cfg = null, bool $onlyVisible = false): array
    {
        $cfg = $cfg ?? self::config();
        $fields = is_array($cfg['info_block']['fields'] ?? null)
            ? $cfg['info_block']['fields']
            : self::defaultInfoFields();
        $rows = [];
        foreach ($fields as $key => $field) {
            if (!is_array($field)) {
                continue;
            }
            $show = !empty($field['show']);
            if ($onlyVisible && !$show) {
                continue;
            }
            $rows[] = [
                'key' => (string) $key,
                'show' => $show,
                'label' => trim((string) ($field['label'] ?? $key)),
                'order' => (int) ($field['order'] ?? 999),
            ];
        }
        usort($rows, static fn(array $a, array $b): int => $a['order'] <=> $b['order']);

        return $rows;
    }

    /**
     * Firmendaten für Brief (Firma + Extended + Overrides), ohne Duplikat-Pflege.
     *
     * @return array{
     *   legal_name: string,
     *   company_type: string,
     *   company_type_label: string,
     *   seat: string,
     *   register_court: string,
     *   register_number: string,
     *   managing_directors: string,
     *   supervisory_chair: string,
     *   share_capital: string,
     *   outstanding_contributions: string,
     *   street: string,
     *   postal: string,
     *   city: string,
     *   country: string,
     *   phone: string,
     *   email: string,
     *   website: string,
     *   tax_number: string,
     *   vat_id: string,
     *   bank_lines: list<string>,
     *   links: array{impressum: string, datenschutz: string, agb: string, widerruf: string}
     * }
     */
    public static function resolvedCompany(?array $cfg = null): array
    {
        $cfg = $cfg ?? self::config();
        $basic = CompanySettings::config();
        $ext = class_exists('CompanyExtendedSettings')
            ? CompanyExtendedSettings::config()
            : [];
        $overrides = is_array($cfg['legal_overrides'] ?? null) ? $cfg['legal_overrides'] : [];
        $linksCfg = is_array($cfg['links'] ?? null) ? $cfg['links'] : [];

        $legalName = trim((string) ($ext['legal_name'] ?? ''));
        if ($legalName === '') {
            $legalName = trim((string) ($basic['name'] ?? ''));
        }

        $type = trim((string) ($ext['company_type'] ?? ''));
        $typeLabel = $type !== '' && class_exists('CompanyTypes')
            ? (string) (CompanyTypes::labels()[$type] ?? $type)
            : $type;

        $seat = trim((string) ($overrides['seat'] ?? ''));
        if ($seat === '') {
            $seat = trim((string) ($basic['city'] ?? ''));
        }

        $registerCourt = trim((string) ($ext['trade_register']['court'] ?? ''));
        $registerNumber = trim((string) ($ext['trade_register']['number'] ?? ''));

        $directors = trim((string) ($overrides['managing_directors'] ?? ''));
        if ($directors === '' && !empty($ext['owners']) && is_array($ext['owners'])) {
            $names = [];
            foreach ($ext['owners'] as $owner) {
                if (!is_array($owner)) {
                    continue;
                }
                $n = trim((string) ($owner['name'] ?? ''));
                if ($n !== '') {
                    $names[] = $n;
                }
            }
            $directors = implode(', ', $names);
        }

        $bankLines = [];
        if (!empty($ext['bank_accounts']) && is_array($ext['bank_accounts'])) {
            foreach ($ext['bank_accounts'] as $acc) {
                if (!is_array($acc)) {
                    continue;
                }
                $iban = trim((string) ($acc['iban'] ?? ''));
                $bic = trim((string) ($acc['bic'] ?? ''));
                $bank = trim((string) ($acc['bank_name'] ?? $acc['name'] ?? ''));
                if ($iban === '' && $bic === '' && $bank === '') {
                    continue;
                }
                $parts = array_filter([$bank, $iban !== '' ? 'IBAN ' . $iban : '', $bic !== '' ? 'BIC ' . $bic : '']);
                $bankLines[] = implode(' · ', $parts);
            }
        }

        $emailLayout = class_exists('EmailLayoutSettings') ? EmailLayoutSettings::config() : [];
        $link = static function (string $key) use ($linksCfg, $emailLayout): string {
            $own = trim((string) ($linksCfg[$key] ?? ''));
            if ($own !== '') {
                return $own;
            }
            $map = [
                'impressum' => 'footer_url_impressum',
                'datenschutz' => 'footer_url_datenschutz',
                'agb' => 'footer_url_agb',
            ];
            $layoutKey = $map[$key] ?? '';

            return $layoutKey !== '' ? trim((string) ($emailLayout[$layoutKey] ?? '')) : '';
        };

        $taxEst = trim((string) ($ext['tax_numbers']['est'] ?? ''));
        $taxNumber = $taxEst !== '' ? $taxEst : trim((string) ($basic['tax_number'] ?? ''));
        $vatId = trim((string) ($basic['vat_id'] ?? ''));
        if ($vatId === '') {
            $vatId = trim((string) ($ext['tax_numbers']['ust'] ?? ''));
        }

        return [
            'legal_name' => $legalName,
            'company_type' => $type,
            'company_type_label' => $typeLabel,
            'seat' => $seat,
            'register_court' => $registerCourt,
            'register_number' => $registerNumber,
            'managing_directors' => $directors,
            'supervisory_chair' => trim((string) ($overrides['supervisory_chair'] ?? '')),
            'share_capital' => trim((string) ($overrides['share_capital'] ?? '')),
            'outstanding_contributions' => trim((string) ($overrides['outstanding_contributions'] ?? '')),
            'street' => trim((string) ($basic['street'] ?? '')),
            'postal' => trim((string) ($basic['postal'] ?? '')),
            'city' => trim((string) ($basic['city'] ?? '')),
            'country' => trim((string) ($basic['country'] ?? 'DE')),
            'phone' => trim((string) ($basic['phone'] ?? '')),
            'email' => trim((string) ($basic['email'] ?? '')),
            'website' => trim((string) ($basic['website'] ?? '')),
            'tax_number' => $taxNumber,
            'vat_id' => $vatId,
            'bank_lines' => $bankLines,
            'links' => [
                'impressum' => $link('impressum'),
                'datenschutz' => $link('datenschutz'),
                'agb' => $link('agb'),
                'widerruf' => trim((string) ($linksCfg['widerruf'] ?? '')),
            ],
        ];
    }

    /**
     * Logo: Briefpapier-Override oder Appearance.
     */
    public static function logoMediaId(?array $cfg = null): string
    {
        $cfg = $cfg ?? self::config();
        $own = trim((string) ($cfg['header']['logo_media_id'] ?? ''));
        if ($own !== '') {
            return $own;
        }

        return class_exists('AppearanceSettings') ? AppearanceSettings::logoMediaId() : '';
    }

    public static function logoUrl(?array $cfg = null): string
    {
        $id = self::logoMediaId($cfg);
        if ($id !== '' && class_exists('MediaRepository')) {
            MediaRepository::ensureTables();
            $item = MediaRepository::find($id);
            if ($item !== null && !empty($item['url'])) {
                return (string) $item['url'];
            }
        }

        return class_exists('AppearanceSettings') ? AppearanceSettings::logoUrl() : Asset::url('/assets/img/logo.svg');
    }

    /**
     * Pflichtfelder je Rechtsform (für Soft-Warnung B3).
     *
     * @return list<array{key: string, label: string}>
     */
    public static function mandatoryFieldsForType(string $companyType): array
    {
        $companyType = strtolower(trim($companyType));
        $baseName = [['key' => 'legal_name', 'label' => 'Firma / rechtlicher Name']];

        return match (true) {
            in_array($companyType, ['gmbh', 'gmbh_igr', 'ug', 'ug_igr'], true) => array_merge($baseName, [
                ['key' => 'company_type', 'label' => 'Rechtsform'],
                ['key' => 'seat', 'label' => 'Sitz'],
                ['key' => 'register_court', 'label' => 'Registergericht'],
                ['key' => 'register_number', 'label' => 'Handelsregisternummer'],
                ['key' => 'managing_directors', 'label' => 'Geschäftsführer'],
            ]),
            $companyType === 'ek' => array_merge($baseName, [
                ['key' => 'company_type', 'label' => 'Rechtsform (e. K.)'],
                ['key' => 'seat', 'label' => 'Niederlassung'],
                ['key' => 'register_court', 'label' => 'Registergericht'],
                ['key' => 'register_number', 'label' => 'Registernummer'],
            ]),
            in_array($companyType, ['ohg', 'kg', 'gmbh_co_kg'], true) => array_merge($baseName, [
                ['key' => 'company_type', 'label' => 'Rechtsform'],
                ['key' => 'seat', 'label' => 'Sitz'],
                ['key' => 'register_court', 'label' => 'Registergericht'],
                ['key' => 'register_number', 'label' => 'Registernummer'],
            ]),
            $companyType === 'gbr' => array_merge($baseName, [
                ['key' => 'managing_directors', 'label' => 'Gesellschafter (Namen)'],
            ]),
            default => $baseName,
        };
    }

    /**
     * Empfohlene Fußangaben (Labels für UI).
     *
     * @return list<array{key: string, label: string}>
     */
    public static function recommendedFooterFields(): array
    {
        return [
            ['key' => 'show_address', 'label' => 'Anschrift'],
            ['key' => 'show_phone', 'label' => 'Telefon'],
            ['key' => 'show_email', 'label' => 'E-Mail'],
            ['key' => 'show_website', 'label' => 'Website'],
            ['key' => 'show_bank', 'label' => 'Bankverbindung'],
            ['key' => 'show_tax_number', 'label' => 'Steuernummer'],
            ['key' => 'show_vat_id', 'label' => 'USt-IdNr.'],
            ['key' => 'show_links', 'label' => 'Links Impressum/Datenschutz/AGB/Widerruf'],
        ];
    }

    /**
     * Fehlende Pflichtangaben (Soft-Check).
     *
     * @return list<array{key: string, label: string}>
     */
    public static function missingMandatoryFields(?array $cfg = null): array
    {
        $party = self::resolvedCompany($cfg);
        $required = self::mandatoryFieldsForType($party['company_type']);
        $missing = [];
        foreach ($required as $field) {
            $key = $field['key'];
            $value = trim((string) ($party[$key] ?? ''));
            if ($value === '') {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Absenderzeile (Futter) aus Firmendaten.
     */
    public static function senderLineText(?array $cfg = null): string
    {
        $party = self::resolvedCompany($cfg);
        $parts = array_filter([
            $party['legal_name'],
            trim($party['street'] . ', ' . $party['postal'] . ' ' . $party['city'], ' ,'),
        ]);

        return implode(' · ', $parts);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private static function sanitize(array $input, bool $fromForm = false): array
    {
        $d = self::defaults();
        $page = is_array($input['page'] ?? null) ? $input['page'] : [];
        $header = is_array($input['header'] ?? null) ? $input['header'] : [];
        $sender = is_array($input['sender_line'] ?? null) ? $input['sender_line'] : [];
        $window = is_array($input['address_window'] ?? null) ? $input['address_window'] : [];
        $info = is_array($input['info_block'] ?? null) ? $input['info_block'] : [];
        $subject = is_array($input['subject'] ?? null) ? $input['subject'] : [];
        $footer = is_array($input['footer'] ?? null) ? $input['footer'] : [];
        $links = is_array($input['links'] ?? null) ? $input['links'] : [];
        $bg = is_array($input['background'] ?? null) ? $input['background'] : [];
        $export = is_array($input['export_formats'] ?? null) ? $input['export_formats'] : [];
        $legal = is_array($input['legal_overrides'] ?? null) ? $input['legal_overrides'] : [];

        $flag = static function (array $src, string $key, bool $default) use ($fromForm): bool {
            if (array_key_exists($key, $src)) {
                return !empty($src[$key]) && (string) $src[$key] !== '0';
            }

            return $fromForm ? false : $default;
        };

        $logoPos = (string) ($header['logo_position'] ?? $d['header']['logo_position']);
        if (!in_array($logoPos, ['left', 'center', 'right'], true)) {
            $logoPos = 'left';
        }
        $heightForm = strtoupper((string) ($header['height_form'] ?? 'B'));
        if (!in_array($heightForm, ['A', 'B'], true)) {
            $heightForm = 'B';
        }

        $bgMode = (string) ($bg['mode'] ?? 'none');
        if (!in_array($bgMode, ['none', 'color', 'image', 'watermark'], true)) {
            $bgMode = 'none';
        }
        $fit = (string) ($bg['fit'] ?? 'contain');
        if (!in_array($fit, ['contain', 'cover', 'tile'], true)) {
            $fit = 'contain';
        }

        $infoFieldsIn = is_array($info['fields'] ?? null) ? $info['fields'] : [];
        $infoFields = [];
        foreach (self::defaultInfoFields() as $key => $defaultField) {
            $row = is_array($infoFieldsIn[$key] ?? null) ? $infoFieldsIn[$key] : [];
            $label = trim((string) ($row['label'] ?? $defaultField['label']));
            if ($label === '') {
                $label = $defaultField['label'];
            }
            $showDefault = !empty($defaultField['show']);
            if ($fromForm) {
                $show = array_key_exists('show', $row) && !empty($row['show']) && (string) $row['show'] !== '0';
            } else {
                $show = array_key_exists('show', $row) ? !empty($row['show']) : $showDefault;
            }
            $infoFields[$key] = [
                'show' => $show,
                'label' => mb_substr($label, 0, 80),
                'order' => max(0, min(9999, (int) ($row['order'] ?? $defaultField['order']))),
            ];
        }

        $color = trim((string) ($bg['color'] ?? ''));
        if ($color !== '' && !preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            $color = '';
        }

        $exportFormats = [
            'pdf' => $flag($export, 'pdf', true),
            'txt' => $flag($export, 'txt', true),
            'md' => $flag($export, 'md', false),
            'docx' => $flag($export, 'docx', false),
        ];
        if (!in_array(true, $exportFormats, true)) {
            $exportFormats['pdf'] = true;
        }

        return [
            'page' => [
                'format' => 'A4',
                'margin_top_mm' => self::clampMm($page['margin_top_mm'] ?? $d['page']['margin_top_mm']),
                'margin_right_mm' => self::clampMm($page['margin_right_mm'] ?? $d['page']['margin_right_mm']),
                'margin_bottom_mm' => self::clampMm($page['margin_bottom_mm'] ?? $d['page']['margin_bottom_mm']),
                'margin_left_mm' => self::clampMm($page['margin_left_mm'] ?? $d['page']['margin_left_mm']),
                'show_din_guides' => $flag($page, 'show_din_guides', false),
            ],
            'header' => [
                'show' => $flag($header, 'show', true),
                'show_company_name' => $flag($header, 'show_company_name', true),
                'show_logo' => $flag($header, 'show_logo', true),
                'logo_position' => $logoPos,
                'logo_max_height_mm' => self::clampMm($header['logo_max_height_mm'] ?? $d['header']['logo_max_height_mm'], 5.0, 45.0),
                'logo_media_id' => self::mediaId($header['logo_media_id'] ?? ''),
                'height_form' => $heightForm,
            ],
            'sender_line' => [
                'show' => $flag($sender, 'show', true),
                'font_size_pt' => self::clampFloat($sender['font_size_pt'] ?? $d['sender_line']['font_size_pt'], 6.0, 12.0),
            ],
            'address_window' => [
                'show' => $flag($window, 'show', true),
                'left_mm' => self::clampMm($window['left_mm'] ?? $d['address_window']['left_mm'], 10.0, 40.0),
                'top_mm' => self::clampMm($window['top_mm'] ?? $d['address_window']['top_mm'], 30.0, 80.0),
                'width_mm' => self::clampMm($window['width_mm'] ?? $d['address_window']['width_mm'], 70.0, 100.0),
                'height_mm' => self::clampMm($window['height_mm'] ?? $d['address_window']['height_mm'], 35.0, 55.0),
            ],
            'info_block' => [
                'show' => $flag($info, 'show', true),
                'fields' => $infoFields,
            ],
            'subject' => [
                'bold' => $flag($subject, 'bold', true),
                'show_label' => $flag($subject, 'show_label', false),
            ],
            'footer' => [
                'show' => $flag($footer, 'show', true),
                'font_size_pt' => self::clampFloat($footer['font_size_pt'] ?? $d['footer']['font_size_pt'], 5.0, 11.0),
                'show_rule' => $flag($footer, 'show_rule', true),
                'columns' => max(1, min(4, (int) ($footer['columns'] ?? 3))),
                'show_legal_mandatory' => $flag($footer, 'show_legal_mandatory', true),
                'show_address' => $flag($footer, 'show_address', true),
                'show_phone' => $flag($footer, 'show_phone', true),
                'show_email' => $flag($footer, 'show_email', true),
                'show_website' => $flag($footer, 'show_website', true),
                'show_bank' => $flag($footer, 'show_bank', true),
                'show_tax_number' => $flag($footer, 'show_tax_number', true),
                'show_vat_id' => $flag($footer, 'show_vat_id', true),
                'show_links' => $flag($footer, 'show_links', true),
            ],
            'links' => [
                'impressum' => self::urlOrEmpty($links['impressum'] ?? ''),
                'datenschutz' => self::urlOrEmpty($links['datenschutz'] ?? ''),
                'agb' => self::urlOrEmpty($links['agb'] ?? ''),
                'widerruf' => self::urlOrEmpty($links['widerruf'] ?? ''),
            ],
            'background' => [
                'mode' => $bgMode,
                'color' => $color,
                'media_id' => self::mediaId($bg['media_id'] ?? ''),
                'opacity' => self::clampFloat($bg['opacity'] ?? 0.08, 0.02, 0.5),
                'fit' => $fit,
                'all_pages' => $flag($bg, 'all_pages', true),
            ],
            'export_formats' => $exportFormats,
            'legal_overrides' => [
                'seat' => mb_substr(trim((string) ($legal['seat'] ?? '')), 0, 120),
                'share_capital' => mb_substr(trim((string) ($legal['share_capital'] ?? '')), 0, 80),
                'outstanding_contributions' => mb_substr(trim((string) ($legal['outstanding_contributions'] ?? '')), 0, 80),
                'supervisory_chair' => mb_substr(trim((string) ($legal['supervisory_chair'] ?? '')), 0, 120),
                'managing_directors' => mb_substr(trim((string) ($legal['managing_directors'] ?? '')), 0, 500),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $stored
     * @return array<string, mixed>
     */
    private static function deepMerge(array $defaults, array $stored): array
    {
        $out = $defaults;
        foreach ($defaults as $key => $defaultValue) {
            if (!array_key_exists($key, $stored)) {
                continue;
            }
            $storedValue = $stored[$key];
            if (is_array($defaultValue) && self::isAssoc($defaultValue) && is_array($storedValue)) {
                $out[$key] = self::deepMerge($defaultValue, $storedValue);
            } else {
                $out[$key] = $storedValue;
            }
        }

        return $out;
    }

    /**
     * @param array<string|int, mixed> $arr
     */
    private static function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private static function clampMm(mixed $value, float $min = 5.0, float $max = 80.0): float
    {
        return self::clampFloat($value, $min, $max);
    }

    private static function clampFloat(mixed $value, float $min, float $max): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }
        $n = is_numeric($value) ? (float) $value : $min;

        return max($min, min($max, $n));
    }

    private static function mediaId(mixed $raw): string
    {
        $id = trim((string) $raw);
        if ($id === '' || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $id)) {
            return '';
        }

        return $id;
    }

    private static function urlOrEmpty(mixed $raw): string
    {
        $url = trim((string) $raw);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url) && !str_starts_with($url, '/')) {
            $url = 'https://' . $url;
        }

        return mb_substr($url, 0, 500);
    }
}
