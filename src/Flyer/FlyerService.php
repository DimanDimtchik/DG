<?php
declare(strict_types=1);

/**
 * CRM-Flyer an einer Stelle:
 * - gift_form: Gutschein-Formular (Vorlage + aufgedruckte Gutscheinnummer)
 * - booking_qr: Terminkalender-QR-Flyer (Konfiguration weiter in CalendarEmbedSettings)
 *
 * Später: Vorlagen-Bau mit Seitenbildern (Media) — bg_media_id / layout_json vorbereitet.
 */
final class FlyerService
{
    public const PURPOSE_GIFT_FORM = 'gift_form';
    public const PURPOSE_BOOKING_QR = 'booking_qr';

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

    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
        self::ensureDefaultGiftTemplates();
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

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
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

        return is_array($row) ? $row : null;
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
     * Render-Daten für Gutschein-Formular-Druck (Nummer kommt vom ausgestellten Gutschein).
     *
     * @param array<string, mixed> $gift normalizeRow aus GiftVoucherRepository
     * @return array{
     *   template: array<string, mixed>,
     *   code: string,
     *   amount_label: string,
     *   title: string,
     *   headline: string,
     *   subtitle: string,
     *   accent: string,
     *   valid_until: string,
     *   company_name: string,
     *   logo_url: string
     * }
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
            ];
        }

        $giftTitle = trim((string) ($gift['title'] ?? ''));
        $headline = trim((string) ($template['headline'] ?? ''));
        if ($headline === '') {
            $headline = $giftTitle !== '' ? $giftTitle : 'Gutschein';
        }
        $subtitle = trim((string) ($template['subtitle'] ?? ''));
        $accent = trim((string) ($template['accent_color'] ?? ''));
        if ($accent === '' || !preg_match('/^#[0-9A-Fa-f]{3,8}$/', $accent)) {
            $accent = '#2f5d50';
        }
        $amount = (float) ($gift['face_value_gross'] ?? $gift['amount'] ?? 0);
        $valid = (string) ($gift['valid_until'] ?? '');

        return [
            'template' => $template,
            'code' => (string) ($gift['code'] ?? ''),
            'amount_label' => number_format($amount, 2, ',', '.') . ' €',
            'title' => $giftTitle !== '' ? $giftTitle : (string) ($template['title'] ?? 'Gutschein'),
            'headline' => $headline,
            'subtitle' => $subtitle,
            'accent' => $accent,
            'valid_until' => $valid,
            'company_name' => $companyName !== '' ? $companyName : 'Gutschein',
            'logo_url' => $logoUrl,
        ];
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
        $ins = Database::pdo()->prepare(
            'INSERT INTO dg_flyer_templates
                (purpose, slug, title, occasion, headline, subtitle, accent_color, sort_order)
             VALUES
                (:purpose, :slug, :title, :occasion, :headline, :subtitle, :accent, :sort)'
        );
        foreach ([
            ['standard', 'Standard', 'general', 'Gutschein', 'Einlösbar für Artikel und Leistungen', '#2f5d50', 10],
            ['geburtstag', 'Geburtstag', 'birthday', 'Alles Gute zum Geburtstag', 'Ein Geschenk zum Einlösen', '#8b4513', 20],
            ['frauentag', '8. März', 'march8', 'Zum Internationalen Frauentag', 'Ein Geschenk zum Einlösen', '#9b2d4a', 30],
        ] as $row) {
            $ins->execute([
                'purpose' => self::PURPOSE_GIFT_FORM,
                'slug' => $row[0],
                'title' => $row[1],
                'occasion' => $row[2],
                'headline' => $row[3],
                'subtitle' => $row[4],
                'accent' => $row[5],
                'sort' => $row[6],
            ]);
        }
    }
}
