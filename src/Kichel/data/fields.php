<?php
declare(strict_types=1);

/**
 * Schicht B: Feld-Metadaten pro CRM-Bereich (deklarativ, erweiterbar).
 *
 * @return list<array{
 *   area_id: string,
 *   area_label: string,
 *   href: string,
 *   intro?: string,
 *   fields: list<array{
 *     label: string,
 *     format?: string,
 *     hint: string,
 *     keywords: list<string>
 *   }>
 * }>
 */
return [
    [
        'area_id' => 'settings:firmendaten',
        'area_label' => 'Firmendaten',
        'href' => '/app?page=einstellungen&tab=firmendaten',
        'intro' => 'Stammdaten der Firma — erscheinen auf Rechnungen, in E-Mails und im Impressum.',
        'fields' => [
            [
                'label' => 'USt-ID',
                'format' => 'DE + 9 Ziffern (z. B. DE123456789)',
                'hint' => 'Umsatzsteuer-Identifikationsnummer für innergemeinschaftliche Lieferungen und Rechnungen.',
                'keywords' => ['ust-id', 'ustid', 'umsatzsteuer-id', 'vat', 'ust'],
            ],
            [
                'label' => 'Steuernummer',
                'format' => 'Bundeslandabhängig, oft mit Schrägstrichen',
                'hint' => 'Vom Finanzamt vergebene Steuernummer — nicht mit der USt-ID verwechseln.',
                'keywords' => ['steuernummer', 'steuer-nummer', 'finanzamt'],
            ],
            [
                'label' => 'Firmenname',
                'format' => 'Freitext',
                'hint' => 'Offizieller Name für Dokumente und Impressum.',
                'keywords' => ['firmenname', 'firma', 'unternehmensname'],
            ],
            [
                'label' => 'E-Mail',
                'format' => 'name@domain.de',
                'hint' => 'Standard-Absender und Kontakt-E-Mail der Firma.',
                'keywords' => ['firmen-email', 'firmenmail', 'kontakt-email'],
            ],
        ],
    ],
    [
        'area_id' => 'settings:payment-terms',
        'area_label' => 'Zahlungsbedingungen & Mahnung',
        'href' => '/app?page=einstellungen&tab=payment-terms',
        'intro' => 'Skonto-Stufen, Zahlungsziele und Mahngebühren für Rechnungen.',
        'fields' => [
            [
                'label' => 'Skonto-Stufen',
                'format' => 'Prozent + Tage (z. B. 3 % bei Zahlung innerhalb 7 Tagen)',
                'hint' => 'Mehrere Stufen möglich — erscheinen auf Rechnungs-PDF und in Belegtexten.',
                'keywords' => ['skonto', 'zahlungsziel', 'skontostufe', 'zahlungsbedingung', 'mahnung', 'mahngebühr'],
            ],
        ],
    ],
    [
        'area_id' => 'bilder',
        'area_label' => 'Bilder / Medien',
        'href' => '/app?page=bilder',
        'intro' => 'Medienbibliothek für Logos, Fotos und Grafiken.',
        'fields' => [
            [
                'label' => 'CRM-Logo',
                'format' => 'PNG/SVG/JPG, Bild unter Bilder öffnen → „Als CRM-Logo in Kopfzeile“',
                'hint' => 'Das Logo erscheint in der CRM-Kopfzeile; für E-Mails zusätzlich unter Benachrichtigungen aktivieren.',
                'keywords' => ['logo', 'firmenlogo', 'crm-logo', 'logodatei', 'logos'],
            ],
        ],
    ],
    [
        'area_id' => 'settings:benachrichtigungen',
        'area_label' => 'Benachrichtigungen / E-Mail-Layout',
        'href' => '/app?page=einstellungen&tab=benachrichtigungen',
        'intro' => 'Kopf- und Fußzeile für System-E-Mails inkl. Social-Media-Leiste.',
        'fields' => [
            [
                'label' => 'Social-Media-Links',
                'format' => 'Vollständige URL mit https://',
                'hint' => 'Facebook, Instagram, LinkedIn usw. — nur ausgefüllte Profile erscheinen im E-Mail-Fuß. Kein Posten ins Netzwerk.',
                'keywords' => ['facebook', 'instagram', 'linkedin', 'xing', 'tiktok', 'youtube', 'social', 'sozial', 'verlinken'],
            ],
            [
                'label' => 'Logo in E-Mail-Kopfzeile',
                'format' => 'Checkbox — Logo kommt aus der Medienbibliothek (Bilder)',
                'hint' => 'Helles Logo auf transparentem Hintergrund für Dark Mode in Mail-Apps.',
                'keywords' => ['email-logo', 'mail-logo', 'kopfzeile'],
            ],
        ],
    ],
    [
        'area_id' => 'support-freigabe',
        'area_label' => 'Support-Freigabe',
        'href' => '/app?page=support-freigabe',
        'intro' => 'Zeitlich begrenzter CRM-Zugang für Ganz Soft — optional Bildschirm-Zuschauen.',
        'fields' => [
            [
                'label' => 'Freigabe-Dauer',
                'format' => 'Auswahl in Stunden (z. B. 4 h, 24 h)',
                'hint' => 'Nach Ablauf endet der Zugang automatisch; Sie können jederzeit manuell beenden.',
                'keywords' => ['support', 'supportfreigabe', 'support-freigabe', 'fernzugriff', 'fernwartung', 'bildschirm'],
            ],
        ],
    ],
    [
        'area_id' => 'settings:schriften',
        'area_label' => 'Schriften',
        'href' => '/app?page=einstellungen&tab=schriften',
        'intro' => 'Schriftarten für CRM-Oberfläche und HTML-E-Mails.',
        'fields' => [
            [
                'label' => 'Schriftart CRM',
                'format' => 'Auswahl aus Webfonts / Systemschriften',
                'hint' => 'Gilt für die eingeloggte CRM-Oberfläche.',
                'keywords' => ['schriftart', 'schriftarten', 'schriften', 'font', 'fonts', 'typografie'],
            ],
        ],
    ],
    [
        'area_id' => 'buchhaltung-kontenuebersicht',
        'area_label' => 'Kontenübersicht',
        'href' => '/app?page=buchhaltung-kontenuebersicht',
        'intro' => 'Salden und Kontoauszüge je Geschäftsjahr und Konto.',
        'fields' => [
            [
                'label' => 'Kontonummer',
                'format' => 'SKR-Kontonummer, z. B. 3200',
                'hint' => 'Konto in der Übersicht anklicken — alle Buchungen und Saldo erscheinen im Kontoauszug.',
                'keywords' => ['kontonummer', 'kontoauszug', 'kontenübersicht', 'kontenuebersicht', 'sachkonto'],
            ],
        ],
    ],
    [
        'area_id' => 'website-seiten',
        'area_label' => 'Website-Seiten',
        'href' => '/app?page=website-seiten',
        'intro' => 'Öffentliche Seiten inkl. Pflichtseiten Impressum, Datenschutz, AGB, Widerruf.',
        'fields' => [
            [
                'label' => 'Pflichtseiten',
                'format' => 'Slug impressum, datenschutz, agb, widerruf',
                'hint' => 'Eigener Block „Pflichtseiten“ auf der Seiten-Übersicht — fehlende Seiten können direkt angelegt werden.',
                'keywords' => ['pflichtseite', 'pflichtseiten', 'impressum', 'datenschutz', 'agb', 'widerruf', 'rechtstext'],
            ],
        ],
    ],
];
