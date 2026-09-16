<?php
declare(strict_types=1);

/** Vorlagen-Zeilen für den Website-Seiten-Editor (Muster einfügen). */
final class WebsitePagePatterns
{
    /**
     * @return array<string, array{label: string, description: string, rows: list<array<string, mixed>>}>
     */
    public static function catalog(): array
    {
        return [
            'academy-online-buchung' => [
                'label' => 'Video: Online buchen (Akademie)',
                'description' => 'Überschrift, Kurztext und Schulungsvideo zur Online-Terminbuchung.',
                'rows' => self::academyOnlineBookingVideoRow(),
            ],
            'online-booking-block' => [
                'label' => 'Online-Terminbuchung',
                'description' => 'Das Buchungsformular für Kunden (Leistung, Termin, Kontakt).',
                'rows' => self::onlineBookingBlockRow(),
            ],
            'hero-text-image' => [
                'label' => 'Text + Bild (2 Spalten)',
                'description' => 'Kurzer Text links, Bild rechts — z. B. für Willkommen oder Aktionen.',
                'rows' => self::heroTextImageRow(),
            ],
            'gallery-row' => [
                'label' => 'Bildergalerie',
                'description' => 'Galerie-Block — Bilder im Inspektor hinzufügen.',
                'rows' => self::galleryRow(),
            ],
        ];
    }

    /**
     * JSON für den Editor (neue IDs werden beim Einfügen im Browser erzeugt).
     *
     * @return array<string, array{label: string, description: string, rows: list<array<string, mixed>>}>
     */
    public static function forEditor(): array
    {
        return self::catalog();
    }

    /**
     * Standardlayout für die Systemseite Terminkalender.
     *
     * @return array{page_kind: string, rows: list<array<string, mixed>>}
     */
    public static function defaultTerminkalenderLayout(): array
    {
        return [
            'page_kind' => WebsitePageRepository::PAGE_KIND_ONLINE_BOOKING,
            'rows' => array_merge(
                self::academyOnlineBookingVideoRow(),
                self::onlineBookingBlockRow()
            ),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function academyOnlineBookingVideoRow(): array
    {
        return [[
            'id' => 'row-pattern-academy-video',
            'columns' => [[
                'id' => 'col-pattern-academy-video',
                'width' => 12,
                'blocks' => [
                    [
                        'id' => 'blk-pattern-academy-heading',
                        'type' => 'heading',
                        'text' => 'So buchen Sie online',
                        'level' => 'h2',
                    ],
                    [
                        'id' => 'blk-pattern-academy-text',
                        'type' => 'text',
                        'text' => 'Kurzanleitung für Ihre Kunden. Sie können diesen Abschnitt anpassen, verschieben oder entfernen.',
                    ],
                    [
                        'id' => 'blk-pattern-academy-video',
                        'type' => 'video',
                        'label' => '',
                        'url' => '/media/training/terminkalender/terminkalender-online-kunde.mp4',
                        'caption' => 'So buchen Sie online einen Termin',
                    ],
                ],
            ]],
        ]];
    }

    /** @return list<array<string, mixed>> */
    public static function onlineBookingBlockRow(): array
    {
        return [[
            'id' => 'row-pattern-online-booking',
            'columns' => [[
                'id' => 'col-pattern-online-booking',
                'width' => 12,
                'blocks' => [[
                    'id' => 'blk-pattern-online-booking',
                    'type' => 'online_booking',
                ]],
            ]],
        ]];
    }

    /** @return list<array<string, mixed>> */
    private static function heroTextImageRow(): array
    {
        return [[
            'id' => 'row-pattern-hero',
            'columns' => [
                [
                    'id' => 'col-pattern-hero-text',
                    'width' => 6,
                    'blocks' => [
                        ['id' => 'blk-pattern-hero-h', 'type' => 'heading', 'text' => 'Willkommen', 'level' => 'h2'],
                        ['id' => 'blk-pattern-hero-t', 'type' => 'text', 'text' => 'Kurzer Text — hier können Sie Ihr Angebot beschreiben.'],
                    ],
                ],
                [
                    'id' => 'col-pattern-hero-image',
                    'width' => 6,
                    'blocks' => [
                        ['id' => 'blk-pattern-hero-img', 'type' => 'image', 'src' => '', 'alt' => ''],
                    ],
                ],
            ],
        ]];
    }

    /** @return list<array<string, mixed>> */
    private static function galleryRow(): array
    {
        return [[
            'id' => 'row-pattern-gallery',
            'columns' => [[
                'id' => 'col-pattern-gallery',
                'width' => 12,
                'blocks' => [[
                    'id' => 'blk-pattern-gallery',
                    'type' => 'gallery',
                    'images' => [],
                ]],
            ]],
        ]];
    }
}
