<?php
declare(strict_types=1);

/**
 * Branchenspezifische Startseiten-Vorlagen für die Erstinstallation.
 */
final class WebsiteHomepageTemplates
{
    /** @var list<string> */
    private const KIND_PRIORITY = [
        'medical',
        'law',
        'gastro',
        'crafts',
        'it',
        'products',
        'consulting',
        'services',
        'both',
        'association',
    ];

    /**
     * @param array{
     *   company_name?: string,
     *   industry?: string,
     *   city?: string,
     *   phone?: string,
     *   email?: string,
     *   business_kinds?: list<string>
     * } $context
     * @return array{rows: list<array<string, mixed>>}
     */
    public static function homepageLayout(array $context): array
    {
        $company = trim((string) ($context['company_name'] ?? ''));
        if ($company === '') {
            $company = 'Ihr Unternehmen';
        }
        $industry = trim((string) ($context['industry'] ?? ''));
        $city = trim((string) ($context['city'] ?? ''));
        $kinds = is_array($context['business_kinds'] ?? null) ? $context['business_kinds'] : [];
        $kind = self::primaryKind($kinds);
        $copy = self::copyForKind($kind, $company, $industry, $city);

        return [
            'rows' => [
                [
                    'id' => self::id('row'),
                    'columns' => [[
                        'id' => self::id('col'),
                        'width' => 12,
                        'blocks' => [
                            ['id' => self::id('blk'), 'type' => 'heading', 'text' => $copy['headline'], 'level' => 'h1'],
                            ['id' => self::id('blk'), 'type' => 'text', 'text' => $copy['intro']],
                            ['id' => self::id('blk'), 'type' => 'button', 'text' => 'Kontakt aufnehmen', 'url' => '/kontakt', 'style' => 'primary'],
                        ],
                    ]],
                ],
                [
                    'id' => self::id('row'),
                    'columns' => [
                        [
                            'id' => self::id('col'),
                            'width' => 4,
                            'blocks' => [
                                ['id' => self::id('blk'), 'type' => 'heading', 'text' => $copy['feature1_title'], 'level' => 'h3'],
                                ['id' => self::id('blk'), 'type' => 'text', 'text' => $copy['feature1_text']],
                            ],
                        ],
                        [
                            'id' => self::id('col'),
                            'width' => 4,
                            'blocks' => [
                                ['id' => self::id('blk'), 'type' => 'heading', 'text' => $copy['feature2_title'], 'level' => 'h3'],
                                ['id' => self::id('blk'), 'type' => 'text', 'text' => $copy['feature2_text']],
                            ],
                        ],
                        [
                            'id' => self::id('col'),
                            'width' => 4,
                            'blocks' => [
                                ['id' => self::id('blk'), 'type' => 'heading', 'text' => $copy['feature3_title'], 'level' => 'h3'],
                                ['id' => self::id('blk'), 'type' => 'text', 'text' => $copy['feature3_text']],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>}
     */
    public static function contactPageLayout(int $formId): array
    {
        return [
            'rows' => [
                [
                    'id' => self::id('row'),
                    'columns' => [[
                        'id' => self::id('col'),
                        'width' => 12,
                        'blocks' => [
                            ['id' => self::id('blk'), 'type' => 'heading', 'text' => 'Kontakt', 'level' => 'h1'],
                            ['id' => self::id('blk'), 'type' => 'text', 'text' => 'Schreiben Sie uns – wir melden uns zeitnah bei Ihnen.'],
                            ['id' => self::id('blk'), 'type' => 'form', 'form_id' => $formId],
                        ],
                    ]],
                ],
            ],
        ];
    }

    /** @param list<string> $kinds */
    public static function primaryKind(array $kinds): string
    {
        $kinds = array_values(array_unique(array_filter(array_map('strval', $kinds))));
        foreach (self::KIND_PRIORITY as $priority) {
            if (in_array($priority, $kinds, true)) {
                return $priority;
            }
        }

        return 'services';
    }

    /**
     * Shop-Profil → install_business_kind (ein Wert).
     *
     * @return list<string>
     */
    public static function businessKindsFromProfile(string $profile): array
    {
        $map = [
            'praxis' => ['medical'],
            'agentur' => ['it'],
            'produktion' => ['products'],
            'lokal' => ['gastro'],
            'handwerk' => ['crafts'],
            'dienstleistung' => ['services'],
            'beratung' => ['consulting'],
            'kanzlei' => ['law'],
        ];
        $profile = strtolower(trim($profile));

        return $map[$profile] ?? ['services'];
    }

    /** @return array<string, string> */
    public static function shopProfileOptions(): array
    {
        return [
            'praxis' => 'Praxis / Gesundheit',
            'agentur' => 'Agentur / IT / Kreativ',
            'produktion' => 'Produktion / Handel',
            'lokal' => 'Lokal vor Ort (Gastronomie, Einzelhandel)',
            'handwerk' => 'Handwerk / Meisterbetrieb',
            'dienstleistung' => 'Dienstleistung (allgemein)',
            'beratung' => 'Beratung / Coaching',
            'kanzlei' => 'Kanzlei / Steuerberatung',
        ];
    }

    /**
     * @return array{
     *   headline: string,
     *   intro: string,
     *   feature1_title: string,
     *   feature1_text: string,
     *   feature2_title: string,
     *   feature2_text: string,
     *   feature3_title: string,
     *   feature3_text: string
     * }
     */
    private static function copyForKind(string $kind, string $company, string $industry, string $city): array
    {
        $where = $city !== '' ? ' in ' . $city : '';
        $branch = $industry !== '' ? $industry : 'Ihr Angebot';

        $variants = [
            'medical' => [
                'headline' => 'Willkommen bei ' . $company,
                'intro' => 'Ihre Praxis' . $where . ' – persönliche Betreuung und moderne Organisation. Diese Seite stellen wir gerade für Sie ein.',
                'feature1_title' => 'Termine & Organisation',
                'feature1_text' => 'Online-Terminbuchung und strukturierte Abläufe für Ihr Team.',
                'feature2_title' => 'Vertrauen',
                'feature2_text' => 'Klare Informationen für Patientinnen und Patienten – DSGVO-konform.',
                'feature3_title' => 'Kontakt',
                'feature3_text' => 'Erreichen Sie uns unkompliziert über das Kontaktformular.',
            ],
            'law' => [
                'headline' => $company,
                'intro' => 'Kompetente Beratung' . $where . '. Hier entsteht Ihr professioneller Internetauftritt.',
                'feature1_title' => 'Mandantenorientiert',
                'feature1_text' => 'Seriöser Auftritt mit Impressum und Datenschutz aus Ihren Firmendaten.',
                'feature2_title' => 'Erreichbarkeit',
                'feature2_text' => 'Kontaktformular mit Datenschutz-Hinweis – Anfragen landen direkt bei Ihnen.',
                'feature3_title' => 'Vertrauen',
                'feature3_text' => 'Transparente Angaben gemäß den berufsrechtlichen Vorgaben.',
            ],
            'gastro' => [
                'headline' => 'Willkommen bei ' . $company,
                'intro' => 'Ihr Lokal' . $where . ' – bald finden Gäste hier alle wichtigen Informationen.',
                'feature1_title' => 'Vor Ort',
                'feature1_text' => 'Präsentieren Sie ' . $branch . ' und laden Sie zum Besuch ein.',
                'feature2_title' => 'Reservierung & Anfragen',
                'feature2_text' => 'Gäste können Sie direkt über das Kontaktformular erreichen.',
                'feature3_title' => 'Aufbau',
                'feature3_text' => 'Wir bereiten Ihre Website vor – Inhalte können Sie jederzeit anpassen.',
            ],
            'crafts' => [
                'headline' => $company . ' – Meisterhaft' . $where,
                'intro' => 'Qualität aus dem Handwerk. Diese Startseite ist Ihr erster Eindruck im Netz.',
                'feature1_title' => 'Leistungen',
                'feature1_text' => 'Stellen Sie Ihr Handwerk und Ihre Expertise vor.',
                'feature2_title' => 'Anfragen',
                'feature2_text' => 'Interessenten kontaktieren Sie bequem über das Formular.',
                'feature3_title' => 'Regional',
                'feature3_text' => 'Lokal verwurzelt' . ($city !== '' ? ' in ' . $city : '') . ', professionell online.',
            ],
            'it' => [
                'headline' => $company,
                'intro' => 'Digitale Lösungen und Beratung' . $where . '. Ihre neue Website nimmt Form an.',
                'feature1_title' => 'Projekte',
                'feature1_text' => 'Präsentieren Sie Leistungen, Referenzen und Ihr Team.',
                'feature2_title' => 'Lead-Generierung',
                'feature2_text' => 'Kontaktanfragen mit Datenschutz-Zustimmung direkt ins CRM.',
                'feature3_title' => 'Modern',
                'feature3_text' => 'Responsive Design – auch auf dem Smartphone überzeugend.',
            ],
            'products' => [
                'headline' => 'Willkommen bei ' . $company,
                'intro' => 'Produkte und Angebote' . $where . ' – Ihr Auftritt wird gerade eingerichtet.',
                'feature1_title' => 'Sortiment',
                'feature1_text' => 'Stellen Sie Ihre Produkte und Ihr Unternehmen vor.',
                'feature2_title' => 'Anfragen',
                'feature2_text' => 'Kunden erreichen Sie über das Kontaktformular.',
                'feature3_title' => 'Rechtssicher',
                'feature3_text' => 'Impressum, Datenschutz und AGB werden aus Ihren Daten erzeugt.',
            ],
            'consulting' => [
                'headline' => $company,
                'intro' => 'Beratung mit Weitblick' . $where . '. Hier entsteht Ihr professioneller Auftritt.',
                'feature1_title' => 'Expertise',
                'feature1_text' => 'Kommunizieren Sie Ihr Beratungsangebot klar und verständlich.',
                'feature2_title' => 'Kontakt',
                'feature2_text' => 'Interessenten können Sie direkt ansprechen – mit Datenschutz-Einwilligung.',
                'feature3_title' => 'Vertrauen',
                'feature3_text' => 'Seriöse Basis mit automatisch generierten Pflichtseiten.',
            ],
        ];

        $default = [
            'headline' => 'Willkommen bei ' . $company,
            'intro' => ($industry !== '' ? $branch . ' – ' : '') . 'Ihr neuer Internetauftritt wird gerade vorbereitet' . $where . '.',
            'feature1_title' => 'Über uns',
            'feature1_text' => 'Stellen Sie Ihr Unternehmen und Ihre Leistungen vor.',
            'feature2_title' => 'Kontakt',
            'feature2_text' => 'Erreichen Sie uns über das Kontaktformular mit Datenschutz-Hinweis.',
            'feature3_title' => 'Rechtssicher',
            'feature3_text' => 'Impressum, Datenschutz und AGB basieren auf Ihren Firmendaten.',
        ];

        return $variants[$kind] ?? $default;
    }

    private static function id(string $prefix): string
    {
        return $prefix . '-' . bin2hex(random_bytes(4));
    }
}
