<?php

declare(strict_types=1);

/**
 * Shop product catalog – mirrored from https://ganz-soft.de/preise
 * Net monthly prices (zzgl. 19% MwSt.). Yearly = 11 × monthly (1 Monat gratis).
 *
 * KDV API keys: starter→basic, business→business, premium→enterprise
 */
return [
    'currency' => 'EUR',
    'vat_rate' => 0.19,
    'vat_note' => 'Alle Preise zzgl. 19% MwSt.',
    'price_source' => 'https://ganz-soft.de/preise',
    'plans' => [
        'starter' => [
            'kdv_tariff' => 'basic',
            'name' => 'Starter',
            'tagline' => 'Für Einzelunternehmer und kleine Betriebe.',
            'monthly_net' => 29.0,
            'featured' => false,
            // Später: absolute URL zum Tarifbild (z. B. von ganz-soft.de) – erscheint nicht automatisch
            'image_url' => '',
            'features' => [
                '1 Domain inklusive',
                '3 E-Mail-Postfächer',
                'Terminkalender',
                'Buchhaltung (SKR03/04)',
                'Kontaktverwaltung',
                'Website-Builder',
                'Automatische Updates',
                'Tägliche Backups',
            ],
        ],
        'business' => [
            'kdv_tariff' => 'business',
            'name' => 'Business',
            'tagline' => 'Für wachsende Unternehmen.',
            'monthly_net' => 49.0,
            'featured' => true,
            'image_url' => '',
            'features' => [
                'Alles aus Starter',
                '1 Domain + 1 Zusatzdomain',
                '10 E-Mail-Postfächer',
                'Bis zu 5 Benutzer',
                'Mitarbeiter-Kalender',
                'Vorrang-Support',
            ],
        ],
        'premium' => [
            'kdv_tariff' => 'enterprise',
            'name' => 'Premium',
            'tagline' => 'Für größere Teams.',
            'monthly_net' => 89.0,
            'featured' => false,
            'image_url' => '',
            'features' => [
                'Alles aus Business',
                'Unbegrenzte Domains',
                'Unbegrenzte E-Mail-Postfächer',
                'Unbegrenzte Benutzer',
                'Individuelle Anpassungen',
                'Persönlicher Ansprechpartner',
            ],
        ],
    ],
];
