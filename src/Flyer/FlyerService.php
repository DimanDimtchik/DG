<?php
declare(strict_types=1);

/**
 * CRM-Flyer an einer Stelle:
 * - gift_form: Gutschein-Formular (Vorlage + aufgedruckte Gutscheinnummer)
 * - booking_qr: Terminkalender-QR-Flyer (Konfiguration weiter in CalendarEmbedSettings)
 *
 * Phase-1-Builder: festes Zonen-Layout in layout_json (kein Drag-and-Drop).
 */
final class FlyerService
{
    public const PURPOSE_GIFT_FORM = 'gift_form';
    public const PURPOSE_BOOKING_QR = 'booking_qr';

    /** @var list<string> */
    public const FORMATS = ['a6', 'a5', 'a4'];

    /**
     * @return array<string, string>
     */
    public static function purposeLabels(): array
    {
        return [
            self::PURPOSE_GIFT_FORM => 'Gutschein-Formular (Code/Betrag auf Druck)',
            self::PURPOSE_BOOKING_QR => 'Terminkalender-QR (nur Booking-Link)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function occasionLabels(): array
    {
        return [
            'general' => 'Allgemein',
            'birthday' => 'Geburtstag',
            'march8' => '8. März',
            'christmas' => 'Weihnachten',
            'custom' => 'Sonstiges',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function formatLabels(): array
    {
        return [
            'a6' => 'A6 (105 × 148 mm)',
            'a5' => 'A5 (148 × 210 mm)',
            'a4' => 'A4 (210 × 297 mm)',
        ];
    }

    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
        self::ensureDefaultGiftTemplates();
        self::backfillEmptyLayouts();
    }

    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            $r = Database::pdo()->query("SHOW TABLES LIKE 'dg_flyer_templates'");

            return $r !== false && $r->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /** Booking-QR: Einstellungen aus dem Terminkalender-Embed (keine zweite Speicherung). */
    public static function bookingQrConfig(): array
    {
        return CalendarEmbedSettings::get();
    }

    /**
     * Standard-Layout für Gutschein-Formular-Flyer (Phase 1).
     *
     * @return array<string, mixed>
     */
    public static function defaultLayout(): array
    {
        return [
            'format' => 'a6',
            'bg' => [
                'mode' => 'color', // color | image
                'color' => '#ffffff',
                'media_id' => '',
                'opacity' => 1.0,
            ],
            'show_logo' => true,
            'headline' => 'Gutschein',
            'subtitle' => 'Einlösbar für Artikel und Leistungen',
            'text_color' => '#1a1a1a',
            'accent_color' => '#2f5d50',
            'contrast_banner' => false,
            'image_media_id' => '',
            'qr' => [
                'booking' => false,
                'social' => false,
                'social_url' => '',
            ],
            'handwriting' => [
                'enabled' => true,
                'label' => 'Persönliche Grüße',
            ],
            'footer' => 'Bitte diesen Flyer oder die Rechnung beim Einlösen vorzeigen.',
        ];
    }

    /**
     * @param array<string, mixed>|string|null $raw
     * @return array<string, mixed>
     */
    public static function normalizeLayout($raw, ?array $templateRow = null): array
    {
        $base = self::defaultLayout();
        $data = [];
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        } elseif (is_array($raw)) {
            $data = $raw;
        }

        $format = strtolower(trim((string) ($data['format'] ?? $base['format'])));
        if (!in_array($format, self::FORMATS, true)) {
            $format = 'a6';
        }

        $bgIn = is_array($data['bg'] ?? null) ? $data['bg'] : [];
        $bgMode = strtolower(trim((string) ($bgIn['mode'] ?? $base['bg']['mode'])));
        if ($bgMode !== 'image') {
            $bgMode = 'color';
        }
        $bgColor = self::normalizeHex((string) ($bgIn['color'] ?? $base['bg']['color']), (string) $base['bg']['color']);
        $bgMedia = self::normalizeMediaId((string) ($bgIn['media_id'] ?? ($data['bg_media_id'] ?? '')));
        // Legacy: Spalten-bg_media_id war INT — String-IDs liegen in layout_json.
        if ($bgMedia === '' && is_array($templateRow)) {
            $colBg = trim((string) ($templateRow['bg_media_id'] ?? ''));
            if ($colBg !== '' && MediaId::isValid($colBg)) {
                $bgMedia = $colBg;
            }
        }
        $opacity = (float) ($bgIn['opacity'] ?? 1);
        if ($opacity < 0.2) {
            $opacity = 0.2;
        }
        if ($opacity > 1) {
            $opacity = 1.0;
        }
        if ($bgMode === 'image' && $bgMedia === '') {
            $bgMode = 'color';
        }

        $headline = trim((string) ($data['headline'] ?? ''));
        if ($headline === '' && is_array($templateRow)) {
            $headline = trim((string) ($templateRow['headline'] ?? ''));
        }
        if ($headline === '') {
            $headline = (string) $base['headline'];
        }

        $subtitle = trim((string) ($data['subtitle'] ?? ''));
        if ($subtitle === '' && is_array($templateRow) && !array_key_exists('subtitle', $data)) {
            $subtitle = trim((string) ($templateRow['subtitle'] ?? ''));
        }

        $accent = self::normalizeHex(
            (string) ($data['accent_color'] ?? ''),
            ''
        );
        if ($accent === '' && is_array($templateRow)) {
            $accent = self::normalizeHex((string) ($templateRow['accent_color'] ?? ''), '');
        }
        if ($accent === '') {
            $accent = (string) $base['accent_color'];
        }

        $textColor = self::normalizeHex(
            (string) ($data['text_color'] ?? $base['text_color']),
            (string) $base['text_color']
        );

        $qrIn = is_array($data['qr'] ?? null) ? $data['qr'] : [];
        $socialUrl = trim((string) ($qrIn['social_url'] ?? ''));
        if ($socialUrl !== '' && !preg_match('#^https?://#i', $socialUrl)) {
            $socialUrl = 'https://' . ltrim($socialUrl, '/');
        }

        $hwIn = is_array($data['handwriting'] ?? null) ? $data['handwriting'] : [];
        $hwLabel = trim((string) ($hwIn['label'] ?? $base['handwriting']['label']));
        if ($hwLabel === '') {
            $hwLabel = (string) $base['handwriting']['label'];
        }

        $footer = trim((string) ($data['footer'] ?? $base['footer']));
        if ($footer === '') {
            $footer = (string) $base['footer'];
        }

        return [
            'format' => $format,
            'bg' => [
                'mode' => $bgMode,
                'color' => $bgColor,
                'media_id' => $bgMedia,
                'opacity' => round($opacity, 2),
            ],
            'show_logo' => !empty($data['show_logo']) || (!array_key_exists('show_logo', $data) && !empty($base['show_logo'])),
            'headline' => mb_substr($headline, 0, 255),
            'subtitle' => mb_substr($subtitle, 0, 255),
            'text_color' => $textColor,
            'accent_color' => $accent,
            'contrast_banner' => !empty($data['contrast_banner']),
            'image_media_id' => self::normalizeMediaId((string) ($data['image_media_id'] ?? '')),
            'qr' => [
                'booking' => !empty($qrIn['booking']),
                'social' => !empty($qrIn['social']),
                'social_url' => mb_substr($socialUrl, 0, 500),
            ],
            'handwriting' => [
                'enabled' => array_key_exists('enabled', $hwIn)
                    ? !empty($hwIn['enabled'])
                    : (array_key_exists('handwriting', $data) ? !empty($hwIn['enabled']) : true),
                'label' => mb_substr($hwLabel, 0, 120),
            ],
            'footer' => mb_substr($footer, 0, 500),
        ];
    }

    /**
     * Layout aus Template-Zeile (inkl. layout_json / Spalten-Fallbacks).
     *
     * @param array<string, mixed> $template
     * @return array<string, mixed>
     */
    public static function layoutFromTemplate(array $template): array
    {
        return self::normalizeLayout($template['layout_json'] ?? null, $template);
    }

    /**
     * @param array<string, mixed> $input Formularfelder (flach oder layout[…])
     * @return array<string, mixed>
     */
    public static function layoutFromRequest(array $input): array
    {
        $layoutIn = is_array($input['layout'] ?? null) ? $input['layout'] : $input;
        $bgMode = (string) ($layoutIn['bg_mode'] ?? $layoutIn['bg']['mode'] ?? 'color');
        $payload = [
            'format' => $layoutIn['format'] ?? 'a6',
            'bg' => [
                'mode' => $bgMode,
                'color' => $layoutIn['bg_color'] ?? ($layoutIn['bg']['color'] ?? '#ffffff'),
                'media_id' => $layoutIn['bg_media_id'] ?? ($layoutIn['bg']['media_id'] ?? ''),
                'opacity' => $layoutIn['bg_opacity'] ?? ($layoutIn['bg']['opacity'] ?? 1),
            ],
            'show_logo' => !empty($layoutIn['show_logo']),
            'headline' => $layoutIn['headline'] ?? '',
            'subtitle' => $layoutIn['subtitle'] ?? '',
            'text_color' => $layoutIn['text_color'] ?? '#1a1a1a',
            'accent_color' => $layoutIn['accent_color'] ?? '#2f5d50',
            'contrast_banner' => !empty($layoutIn['contrast_banner']),
            'image_media_id' => $layoutIn['image_media_id'] ?? '',
            'qr' => [
                'booking' => !empty($layoutIn['qr_booking']) || !empty($layoutIn['qr']['booking']),
                'social' => !empty($layoutIn['qr_social']) || !empty($layoutIn['qr']['social']),
                'social_url' => $layoutIn['social_url'] ?? ($layoutIn['qr']['social_url'] ?? ''),
            ],
            'handwriting' => [
                'enabled' => !empty($layoutIn['handwriting_enabled'])
                    || (!empty($layoutIn['handwriting']['enabled']) && empty($layoutIn['handwriting_off'])),
                'label' => $layoutIn['handwriting_label'] ?? ($layoutIn['handwriting']['label'] ?? 'Persönliche Grüße'),
            ],
            'footer' => $layoutIn['footer'] ?? '',
        ];
        // Checkbox: wenn Feld fehlt = aus
        if (!isset($layoutIn['handwriting_enabled']) && !isset($layoutIn['handwriting']['enabled'])) {
            $payload['handwriting']['enabled'] = false;
        }
        if (!isset($layoutIn['show_logo'])) {
            $payload['show_logo'] = false;
        }

        return self::normalizeLayout($payload);
    }

    /**
     * Aktive Gutschein-Flyer-Vorlagen für Auswahl beim Ausstellen.
     *
     * @return list<array<string, mixed>>
     */
    public static function listGiftTemplates(bool $activeOnly = true): array
    {
        self::ensureReady();
        if (!self::tableReady()) {
            return [];
        }
        $sql = 'SELECT * FROM dg_flyer_templates WHERE purpose = :p';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, title ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['p' => self::PURPOSE_GIFT_FORM]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = self::hydrateTemplateRow($row);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findTemplate(int $id): ?array
    {
        self::ensureReady();
        if ($id < 1 || !self::tableReady()) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_flyer_templates WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::hydrateTemplateRow($row) : null;
    }

    public static function defaultGiftTemplateId(): int
    {
        foreach (self::listGiftTemplates(true) as $row) {
            if ((string) ($row['slug'] ?? '') === 'standard') {
                return (int) ($row['id'] ?? 0);
            }
        }
        $list = self::listGiftTemplates(true);

        return $list !== [] ? (int) ($list[0]['id'] ?? 0) : 0;
    }

    /**
     * Speichert / aktualisiert eine gift_form-Vorlage.
     *
     * @param array<string, mixed> $input
     */
    public static function saveGiftTemplate(array $input, ?int $id = null): int
    {
        self::ensureReady();
        if (!self::tableReady()) {
            throw new RuntimeException('Flyer-Vorlagen-Tabelle fehlt.');
        }

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Titel ist erforderlich.');
        }
        $slug = self::slugify((string) ($input['slug'] ?? $title));
        if ($slug === '') {
            $slug = 'vorlage-' . substr(bin2hex(random_bytes(3)), 0, 6);
        }
        $occasion = (string) ($input['occasion'] ?? 'general');
        if (!isset(self::occasionLabels()[$occasion])) {
            $occasion = 'custom';
        }
        $layout = self::layoutFromRequest($input);
        $sort = (int) ($input['sort_order'] ?? 0);
        $active = !empty($input['is_active']) ? 1 : 0;
        if ($id === null && !array_key_exists('is_active', $input)) {
            $active = 1;
        }

        $pdo = Database::pdo();
        if ($id !== null && $id > 0) {
            $existing = self::findTemplate($id);
            if ($existing === null || (string) ($existing['purpose'] ?? '') !== self::PURPOSE_GIFT_FORM) {
                throw new InvalidArgumentException('Vorlage nicht gefunden.');
            }
            // Slug unverändert lassen, wenn leer übergeben
            if (trim((string) ($input['slug'] ?? '')) === '') {
                $slug = (string) ($existing['slug'] ?? $slug);
            }
            if (self::slugExists($slug, $id)) {
                throw new InvalidArgumentException('Slug bereits vergeben.');
            }
            $stmt = $pdo->prepare(
                'UPDATE dg_flyer_templates SET
                    slug = :slug,
                    title = :title,
                    occasion = :occasion,
                    headline = :headline,
                    subtitle = :subtitle,
                    accent_color = :accent,
                    bg_media_id = NULL,
                    layout_json = :layout,
                    is_active = :active,
                    sort_order = :sort
                 WHERE id = :id AND purpose = :purpose'
            );
            $stmt->execute([
                'slug' => $slug,
                'title' => mb_substr($title, 0, 191),
                'occasion' => $occasion,
                'headline' => $layout['headline'],
                'subtitle' => $layout['subtitle'],
                'accent' => $layout['accent_color'],
                'layout' => json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'active' => $active,
                'sort' => $sort,
                'id' => $id,
                'purpose' => self::PURPOSE_GIFT_FORM,
            ]);

            return $id;
        }

        // Unique slug
        $baseSlug = $slug;
        $n = 2;
        while (self::slugExists($slug, null)) {
            $slug = $baseSlug . '-' . $n;
            $n++;
            if ($n > 50) {
                $slug = $baseSlug . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
                break;
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_flyer_templates
                (purpose, slug, title, occasion, headline, subtitle, accent_color, bg_media_id, layout_json, is_active, sort_order)
             VALUES
                (:purpose, :slug, :title, :occasion, :headline, :subtitle, :accent, NULL, :layout, :active, :sort)'
        );
        $stmt->execute([
            'purpose' => self::PURPOSE_GIFT_FORM,
            'slug' => $slug,
            'title' => mb_substr($title, 0, 191),
            'occasion' => $occasion,
            'headline' => $layout['headline'],
            'subtitle' => $layout['subtitle'],
            'accent' => $layout['accent_color'],
            'layout' => json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'active' => $active,
            'sort' => $sort,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Render-Daten für Gutschein-Formular-Druck (Nummer kommt vom ausgestellten Gutschein).
     *
     * @param array<string, mixed> $gift normalizeRow aus GiftVoucherRepository
     * @return array<string, mixed>
     */
    public static function giftPrintModel(array $gift, ?array $template = null, string $companyName = '', string $logoUrl = ''): array
    {
        $tid = (int) ($gift['flyer_template_id'] ?? 0);
        if ($template === null && $tid > 0) {
            $template = self::findTemplate($tid);
        }
        if ($template === null) {
            $template = [
                'slug' => 'standard',
                'title' => 'Standard',
                'headline' => 'Gutschein',
                'subtitle' => 'Einlösbar für Artikel und Leistungen',
                'accent_color' => '#2f5d50',
                'occasion' => 'general',
                'layout_json' => null,
            ];
        } else {
            $template = self::hydrateTemplateRow($template);
        }

        $layout = self::layoutFromTemplate($template);

        $giftTitle = trim((string) ($gift['title'] ?? ''));
        $headline = trim((string) ($layout['headline'] ?? ''));
        if ($headline === '') {
            $headline = $giftTitle !== '' ? $giftTitle : 'Gutschein';
        }
        $subtitle = trim((string) ($layout['subtitle'] ?? ''));
        $accent = (string) ($layout['accent_color'] ?? '#2f5d50');
        $amount = (float) ($gift['face_value_gross'] ?? $gift['amount'] ?? 0);
        $valid = (string) ($gift['valid_until'] ?? '');

        $showLogo = !empty($layout['show_logo']);
        $resolvedLogo = $showLogo ? $logoUrl : '';
        if ($showLogo && $resolvedLogo === '' && class_exists('AppearanceSettings')) {
            $resolvedLogo = (string) AppearanceSettings::logoUrl();
        }

        $bgUrl = '';
        $bgMediaId = (string) ($layout['bg']['media_id'] ?? '');
        if (($layout['bg']['mode'] ?? '') === 'image' && $bgMediaId !== '') {
            $bgUrl = MediaStorage::publicUrl($bgMediaId);
        }
        $imageUrl = '';
        $imageMediaId = (string) ($layout['image_media_id'] ?? '');
        if ($imageMediaId !== '') {
            $imageUrl = MediaStorage::publicUrl($imageMediaId);
        }

        $bookingUrl = '';
        if (!empty($layout['qr']['booking'])) {
            $bookingUrl = CalendarEmbedSettings::publicBookingUrl();
            if (class_exists('App')) {
                $base = App::publicBaseUrl();
                if ($base !== '' && str_starts_with($bookingUrl, '/')) {
                    $bookingUrl = $base . $bookingUrl;
                }
            }
        }

        $socialUrl = '';
        if (!empty($layout['qr']['social'])) {
            $socialUrl = trim((string) ($layout['qr']['social_url'] ?? ''));
            if ($socialUrl === '') {
                try {
                    $firm = CompanySettings::config();
                    $socialUrl = trim((string) ($firm['website'] ?? ''));
                } catch (Throwable) {
                    $socialUrl = '';
                }
            }
            if ($socialUrl !== '' && !preg_match('#^https?://#i', $socialUrl)) {
                $socialUrl = 'https://' . ltrim($socialUrl, '/');
            }
        }

        $pageMm = match ((string) $layout['format']) {
            'a4' => ['w' => 210, 'h' => 297],
            'a5' => ['w' => 148, 'h' => 210],
            default => ['w' => 105, 'h' => 148],
        };

        return [
            'template' => $template,
            'layout' => $layout,
            'code' => (string) ($gift['code'] ?? ''),
            'amount_label' => number_format($amount, 2, ',', '.') . ' €',
            'amount' => $amount,
            'title' => $giftTitle !== '' ? $giftTitle : (string) ($template['title'] ?? 'Gutschein'),
            'headline' => $headline,
            'subtitle' => $subtitle,
            'accent' => $accent,
            'text_color' => (string) ($layout['text_color'] ?? '#1a1a1a'),
            'valid_until' => $valid,
            'company_name' => $companyName !== '' ? $companyName : 'Gutschein',
            'logo_url' => $resolvedLogo,
            'show_logo' => $showLogo,
            'bg_mode' => (string) ($layout['bg']['mode'] ?? 'color'),
            'bg_color' => (string) ($layout['bg']['color'] ?? '#ffffff'),
            'bg_url' => $bgUrl,
            'bg_opacity' => (float) ($layout['bg']['opacity'] ?? 1),
            'image_url' => $imageUrl,
            'contrast_banner' => !empty($layout['contrast_banner']),
            'qr_booking_url' => $bookingUrl,
            'qr_social_url' => $socialUrl,
            'handwriting' => $layout['handwriting'],
            'footer' => (string) ($layout['footer'] ?? ''),
            'format' => (string) $layout['format'],
            'page_mm' => $pageMm,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function hydrateTemplateRow(array $row): array
    {
        $layout = self::normalizeLayout($row['layout_json'] ?? null, $row);
        $row['layout'] = $layout;
        $row['layout_json'] = json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $row['headline'] = $layout['headline'];
        $row['subtitle'] = $layout['subtitle'];
        $row['accent_color'] = $layout['accent_color'];

        return $row;
    }

    private static function backfillEmptyLayouts(): void
    {
        if (!self::tableReady()) {
            return;
        }
        try {
            $stmt = Database::pdo()->query(
                "SELECT id, headline, subtitle, accent_color, layout_json
                 FROM dg_flyer_templates
                 WHERE purpose = 'gift_form'
                   AND (layout_json IS NULL OR layout_json = '' OR layout_json = '{}')"
            );
            if ($stmt === false) {
                return;
            }
            $upd = Database::pdo()->prepare(
                'UPDATE dg_flyer_templates SET layout_json = :layout, headline = :headline, subtitle = :subtitle, accent_color = :accent WHERE id = :id'
            );
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!is_array($row)) {
                    continue;
                }
                $layout = self::normalizeLayout(null, $row);
                $upd->execute([
                    'layout' => json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'headline' => $layout['headline'],
                    'subtitle' => $layout['subtitle'],
                    'accent' => $layout['accent_color'],
                    'id' => (int) $row['id'],
                ]);
            }
        } catch (Throwable) {
            // still usable without backfill
        }
    }

    private static function ensureDefaultGiftTemplates(): void
    {
        if (!self::tableReady()) {
            return;
        }
        $stmt = Database::pdo()->query('SELECT COUNT(*) FROM dg_flyer_templates WHERE purpose = \'gift_form\'');
        if ($stmt && (int) $stmt->fetchColumn() > 0) {
            return;
        }
        foreach ([
            ['standard', 'Standard', 'general', 'Gutschein', 'Einlösbar für Artikel und Leistungen', '#2f5d50', 10],
            ['geburtstag', 'Geburtstag', 'birthday', 'Alles Gute zum Geburtstag', 'Ein Geschenk zum Einlösen', '#8b4513', 20],
            ['frauentag', '8. März', 'march8', 'Zum Internationalen Frauentag', 'Ein Geschenk zum Einlösen', '#9b2d4a', 30],
        ] as $row) {
            $layout = self::normalizeLayout([
                'headline' => $row[3],
                'subtitle' => $row[4],
                'accent_color' => $row[5],
            ]);
            $ins = Database::pdo()->prepare(
                'INSERT INTO dg_flyer_templates
                    (purpose, slug, title, occasion, headline, subtitle, accent_color, layout_json, sort_order)
                 VALUES
                    (:purpose, :slug, :title, :occasion, :headline, :subtitle, :accent, :layout, :sort)'
            );
            $ins->execute([
                'purpose' => self::PURPOSE_GIFT_FORM,
                'slug' => $row[0],
                'title' => $row[1],
                'occasion' => $row[2],
                'headline' => $layout['headline'],
                'subtitle' => $layout['subtitle'],
                'accent' => $layout['accent_color'],
                'layout' => json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'sort' => $row[6],
            ]);
        }
    }

    private static function normalizeHex(string $value, string $fallback): string
    {
        $value = trim($value);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1) {
            return strtolower($value);
        }
        if (preg_match('/^#[0-9A-Fa-f]{3}$/', $value) === 1) {
            $r = $value[1];
            $g = $value[2];
            $b = $value[3];

            return strtolower('#' . $r . $r . $g . $g . $b . $b);
        }

        return $fallback;
    }

    private static function normalizeMediaId(string $id): string
    {
        $id = trim($id);

        return MediaId::isValid($id) ? $id : '';
    }

    private static function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
        $value = trim($value, '-');

        return mb_substr($value, 0, 64);
    }

    private static function slugExists(string $slug, ?int $exceptId): bool
    {
        $sql = 'SELECT id FROM dg_flyer_templates WHERE purpose = :p AND slug = :s';
        $params = ['p' => self::PURPOSE_GIFT_FORM, 's' => $slug];
        if ($exceptId !== null && $exceptId > 0) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        $sql .= ' LIMIT 1';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }
}
